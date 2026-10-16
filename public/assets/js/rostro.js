/* ============================================================
   SERENA — rostro.js
   Reconocimiento facial como segundo paso (seguridad.php y
   verificacion_2pasos.php). Todo el análisis ocurre en el navegador con
   face-api.js: al servidor solo llegan números (descriptores), nunca la
   imagen. Ver src/modelos/VerificacionDosPasosRostro.php.
   ============================================================ */
(function () {
    'use strict';

    var raiz = document.querySelector('[data-rostro]');
    if (!raiz) return;

    var modo = raiz.getAttribute('data-modo');                    // 'registro' | 'verificar'
    var total = parseInt(raiz.getAttribute('data-muestras'), 10) || 1;
    var urlModelos = raiz.getAttribute('data-modelos');
    var urlLibreria = raiz.getAttribute('data-libreria');
    var sriLibreria = raiz.getAttribute('data-libreria-sri'); // vacío = sin verificación (ver config.php)
    var video = raiz.querySelector('[data-rostro-video]');
    var estadoEl = raiz.querySelector('[data-rostro-estado]');
    var campo = raiz.querySelector('[data-rostro-campo]');
    var boton = raiz.querySelector('[data-rostro-iniciar]');
    var progreso = raiz.querySelector('[data-rostro-progreso]');
    var formulario = raiz.closest('form');

    var flujo = null;            // stream de la cámara, para poder apagarla
    var modelosListos = false;
    var TIEMPO_MAX_MS = 30000;   // si en 30 s no hay captura buena, se detiene y se puede reintentar
    var PAUSA_ENTRE_MUESTRAS_MS = 700;

    function decir(texto, tipo) {
        estadoEl.textContent = texto;
        if (tipo) estadoEl.setAttribute('data-tipo', tipo); else estadoEl.removeAttribute('data-tipo');
    }

    function pintarProgreso(n) {
        if (!progreso) return;
        var puntos = progreso.querySelectorAll('.punto-codigo');
        for (var i = 0; i < puntos.length; i++) {
            puntos[i].classList.toggle('punto-disponible', i < n);
        }
        progreso.setAttribute('aria-label', 'Capturas completadas: ' + n + ' de ' + total);
    }

    function esperar(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    function apagarCamara() {
        if (flujo) {
            flujo.getTracks().forEach(function (t) { t.stop(); });
            flujo = null;
        }
        video.srcObject = null;
        raiz.setAttribute('data-camara', 'off');
        raiz.removeAttribute('data-fase');
    }

    function cargarLibreria() {
        if (window.faceapi) return Promise.resolve();
        return new Promise(function (resolver, rechazar) {
            var s = document.createElement('script');
            s.src = urlLibreria;
            s.async = true;
            // Si config.php trae FACEAPI_SRI_HASH configurado, el navegador
            // verifica que el archivo descargado sea exactamente ese antes de
            // ejecutarlo — protege contra un CDN o paquete comprometido.
            if (sriLibreria) {
                s.integrity = sriLibreria;
                s.crossOrigin = 'anonymous';
            }
            s.onload = function () { resolver(); };
            s.onerror = function () { rechazar(new Error('libreria')); };
            document.head.appendChild(s);
        });
    }

    function cargarModelos() {
        if (modelosListos) return Promise.resolve();
        var nets = window.faceapi.nets;
        return Promise.all([
            nets.tinyFaceDetector.loadFromUri(urlModelos),
            nets.faceLandmark68Net.loadFromUri(urlModelos),
            nets.faceRecognitionNet.loadFromUri(urlModelos)
        ]).then(function () { modelosListos = true; });
    }

    function encenderCamara() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return Promise.reject({ name: 'SinCamaraApi' });
        }
        return navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
            audio: false
        }).then(function (stream) {
            flujo = stream;
            video.srcObject = stream;
            raiz.setAttribute('data-camara', 'on');
            return video.play().catch(function () { /* autoplay ya lo intenta */ });
        });
    }

    function mensajeDeError(e) {
        var n = e && e.name;
        if (n === 'NotAllowedError' || n === 'SecurityError') {
            return 'La cámara está bloqueada. Permítela desde el candado de la barra de direcciones y vuelve a intentar.';
        }
        if (n === 'NotFoundError' || n === 'OverconstrainedError') return 'No encontramos una cámara en este dispositivo.';
        if (n === 'NotReadableError') return 'Otra aplicación está usando la cámara. Ciérrala e inténtalo de nuevo.';
        if (n === 'SinCamaraApi') return 'Este navegador no permite usar la cámara aquí (hace falta una conexión segura https).';
        if (e && e.message === 'libreria') return 'No pudimos cargar el reconocimiento facial. Revisa tu conexión e inténtalo de nuevo.';
        return 'No pudimos iniciar el reconocimiento facial. Inténtalo de nuevo.';
    }

    // Una lectura del video: devuelve { descriptor } o { pista } con qué corregir.
    function leerRostro() {
        var opciones = new window.faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });
        return window.faceapi.detectAllFaces(video, opciones).withFaceLandmarks().withFaceDescriptors()
            .then(function (caras) {
                if (caras.length === 0) return { pista: 'No veo tu rostro. Colócalo dentro del óvalo, de frente.' };
                if (caras.length > 1) return { pista: 'Se ven varias caras. Que aparezca solo la tuya.' };
                var caja = caras[0].detection.box;
                if (video.videoWidth && caja.width / video.videoWidth < 0.28) {
                    return { pista: 'Acércate un poco a la cámara.' };
                }
                var d = Array.prototype.map.call(caras[0].descriptor, function (n) { return Math.round(n * 1e6) / 1e6; });
                return { descriptor: d };
            });
    }

    function capturar() {
        var muestras = [];
        var inicio = Date.now();

        function paso() {
            if (Date.now() - inicio > TIEMPO_MAX_MS) return Promise.reject({ name: 'TiempoAgotado' });
            return leerRostro().then(function (r) {
                if (!r.descriptor) {
                    raiz.removeAttribute('data-fase');
                    decir(r.pista);
                    return esperar(350).then(paso);
                }
                muestras.push(r.descriptor);
                raiz.setAttribute('data-fase', 'ok');
                pintarProgreso(muestras.length);
                if (muestras.length >= total) return muestras;
                decir('Bien. Mantente quieta o quieto un momento… (' + muestras.length + ' de ' + total + ')');
                return esperar(PAUSA_ENTRE_MUESTRAS_MS).then(paso);
            });
        }
        return paso();
    }

    function terminar(muestras) {
        campo.value = JSON.stringify(modo === 'registro' ? muestras : muestras[0]);
        decir(modo === 'registro' ? 'Listo, guardando tu rostro…' : 'Rostro leído. Verificando…', 'ok');
        apagarCamara();
        if (typeof formulario.requestSubmit === 'function') formulario.requestSubmit(); else formulario.submit();
    }

    function iniciar() {
        boton.disabled = true;
        campo.value = '';
        pintarProgreso(0);
        decir('Preparando…');

        cargarLibreria()
            .then(function () { decir('Cargando el reconocimiento facial (la primera vez tarda unos segundos)…'); return cargarModelos(); })
            .then(function () { decir('Permite el acceso a la cámara cuando el navegador te lo pida.'); return encenderCamara(); })
            .then(function () { decir('Mira a la cámara, de frente y con buena luz.'); return capturar(); })
            .then(terminar)
            .catch(function (e) {
                apagarCamara();
                pintarProgreso(0);
                if (e && e.name === 'TiempoAgotado') {
                    decir('No logramos ver tu rostro con claridad. Busca una luz suave de frente y vuelve a intentar.', 'error');
                } else {
                    decir(mensajeDeError(e), 'error');
                }
                boton.textContent = 'Intentar de nuevo';
                boton.disabled = false;
            });
    }

    boton.addEventListener('click', iniciar);
    window.addEventListener('pagehide', apagarCamara);
})();
