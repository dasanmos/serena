/* ============================================================
   SERENA — voz.js
   Dictado por voz en los campos de texto (textarea e input de texto/búsqueda) de las páginas con sesión.
   Usa el reconocimiento de voz del navegador (Web Speech API, español de Colombia).
   Privacidad: en Chrome y Edge el audio lo procesa un servicio externo (Google / Microsoft). Serena NO
   graba ni guarda la voz. Por eso la primera vez se pide aceptar un aviso (se guarda en serena_voz).
   No aparece en: contraseñas, correos, números, códigos cortos, campos marcados con data-sin-voz,
   ni dentro de [role="search"].
   Comandos opcionales: decir «punto y aparte» o «nueva línea».
   ============================================================ */
(function () {
    'use strict';

    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { return; } // navegador sin soporte (p. ej. Firefox): no se muestra nada

    var CLAVE = 'serena_voz';
    var EXCLUIR = /(pass|clave|contrase|\bpin\b|pin_|_pin|codigo|code|token|captcha|otp|totp|correo|email|mail|tel[eé]fono|celular|dane|documento|cedula|usuario|login|csrf)/i;
    var ES_ANDROID = /Android/i.test(navigator.userAgent || '');

    var activo = null;      // { rec, campo, btn, detenido, vacios, antes, despues, texto }
    var aviso = null;       // aviso flotante (estado / errores)
    var temporizadorAviso = null;

    function aceptado() { try { return localStorage.getItem(CLAVE) === 'si'; } catch (e) { return false; } }
    function recordarAceptacion() { try { localStorage.setItem(CLAVE, 'si'); } catch (e) { /* modo privado */ } }

    // ---------- Aviso flotante ----------
    function decir(texto, error) {
        if (!aviso) {
            aviso = document.createElement('div');
            aviso.className = 'voz-aviso';
            aviso.setAttribute('role', 'status');
            aviso.setAttribute('aria-live', 'polite');
            document.body.appendChild(aviso);
        }
        aviso.textContent = texto;
        aviso.classList.toggle('voz-aviso-error', !!error);
        aviso.hidden = !texto;
        clearTimeout(temporizadorAviso);
        if (texto) { temporizadorAviso = setTimeout(function () { aviso.hidden = true; }, error ? 6000 : 3500); }
    }

    // ---------- Aviso de privacidad (primera vez) ----------
    function pedirAceptacion(alAceptar) {
        var mensaje = 'El dictado por voz usa el reconocimiento de voz de tu navegador. En Chrome y Edge, el audio se envía a Google o Microsoft para convertirlo en texto. ' +
            'Serena no graba ni guarda tu voz. No dictes contraseñas ni datos que no quieras compartir.';
        if (typeof HTMLDialogElement !== 'function') {
            if (window.confirm(mensaje + '\n\n¿Activar el dictado por voz?')) { recordarAceptacion(); alAceptar(); }
            return;
        }
        var d = document.createElement('dialog');
        d.className = 'voz-dialogo';
        d.setAttribute('aria-labelledby', 'voz-dialogo-titulo');
        var h = document.createElement('h2'); h.id = 'voz-dialogo-titulo'; h.textContent = 'Dictado por voz';
        var p = document.createElement('p'); p.textContent = mensaje;
        var acc = document.createElement('div'); acc.className = 'voz-dialogo-acciones';
        var no = document.createElement('button'); no.type = 'button'; no.className = 'voz-dialogo-sec'; no.textContent = 'Ahora no';
        var si = document.createElement('button'); si.type = 'button'; si.className = 'voz-dialogo-pri'; si.textContent = 'Activar dictado';
        no.addEventListener('click', function () { d.close(); });
        si.addEventListener('click', function () { recordarAceptacion(); d.close(); alAceptar(); });
        d.addEventListener('close', function () { if (d.parentNode) { d.parentNode.removeChild(d); } });
        acc.appendChild(no); acc.appendChild(si);
        d.appendChild(h); d.appendChild(p); d.appendChild(acc);
        document.body.appendChild(d);
        d.showModal();
        si.focus();
    }

    // ---------- Dictado ----------
    function procesar(t) {
        return t.replace(/\s*punto y aparte\s*/gi, '.\n').replace(/\s*(nueva l[ií]nea|nuevo rengl[oó]n)\s*/gi, '\n');
    }
    function mayuscula(antes, texto) {
        if (!texto) { return texto; }
        if (antes === '' || /[.!?¡¿\n]\s*$/.test(antes)) { return texto.charAt(0).toUpperCase() + texto.slice(1); }
        return texto;
    }
    function escribir(a, textoFinal, textoParcial) {
        var campo = a.campo;
        var sep = (a.antes && !/\s$/.test(a.antes) && !/^[\n,.;:]/.test(textoFinal + textoParcial)) ? ' ' : '';
        var insertado = mayuscula(a.antes, (textoFinal + textoParcial).replace(/^\s+/, ''));
        var valor = a.antes + sep + insertado + a.despues;
        if (campo.maxLength > 0 && valor.length > campo.maxLength) {
            valor = valor.slice(0, campo.maxLength);
        }
        campo.value = valor;
        var pos = Math.min(valor.length - a.despues.length, valor.length);
        try { campo.setSelectionRange(pos, pos); } catch (e) { /* algunos tipos no lo permiten */ }
        campo.dispatchEvent(new Event('input', { bubbles: true })); // contadores, guardado automático, etc.
    }

    function pintar(btn, escuchando) {
        btn.classList.toggle('voz-escuchando', escuchando);
        btn.setAttribute('aria-pressed', escuchando ? 'true' : 'false');
        btn.setAttribute('aria-label', escuchando ? 'Detener el dictado' : 'Dictar texto en este campo');
        btn.title = escuchando ? 'Detener el dictado' : 'Dictar por voz';
    }

    function detener(sinAviso) {
        if (!activo) { return; }
        var a = activo;
        a.detenido = true;
        try { a.rec.stop(); } catch (e) { /* ya detenido */ }
        pintar(a.btn, false);
        activo = null;
        if (!sinAviso) { decir(''); }
    }

    function iniciar(campo, btn, vacios) {
        var rec = new SR();
        rec.lang = 'es-CO';
        rec.interimResults = true;
        rec.continuous = !ES_ANDROID; // en Android continuous repite frases: se reinicia a mano
        rec.maxAlternatives = 1;

        var base = campo.value;
        var ini = typeof campo.selectionStart === 'number' ? campo.selectionStart : base.length;
        var fin = typeof campo.selectionEnd === 'number' ? campo.selectionEnd : ini;
        var a = { rec: rec, campo: campo, btn: btn, detenido: false, vacios: vacios || 0, antes: base.slice(0, ini), despues: base.slice(fin), huboTexto: false, error: false };
        activo = a;

        var finalAcum = '';
        rec.onresult = function (ev) {
            var parcial = '';
            for (var i = ev.resultIndex; i < ev.results.length; i++) {
                var t = ev.results[i][0].transcript;
                if (ev.results[i].isFinal) { finalAcum += procesar(t); } else { parcial += t; }
            }
            a.huboTexto = true;
            escribir(a, finalAcum, parcial);
        };
        rec.onerror = function (ev) {
            a.error = true;
            var m = {
                'not-allowed': 'El navegador no tiene permiso para usar el micrófono. Revisa el permiso del sitio.',
                'service-not-allowed': 'El navegador no permite el dictado en este momento.',
                'audio-capture': 'No se encontró un micrófono.',
                'network': 'No hay conexión con el servicio de voz.',
                'no-speech': 'No se escuchó nada. Intenta de nuevo, más cerca del micrófono.'
            }[ev.error] || 'No se pudo dictar. Intenta de nuevo.';
            if (ev.error !== 'aborted') { decir(m, true); }
        };
        rec.onend = function () {
            if (activo !== a) { return; }
            // En Android (no continuo) y tras silencios largos: sigue escuchando si la persona no ha parado.
            if (!a.detenido && !a.error && (a.huboTexto || a.vacios < 1)) {
                activo = null;
                iniciar(campo, btn, a.huboTexto ? 0 : a.vacios + 1);
                return;
            }
            pintar(btn, false);
            activo = null;
        };
        try {
            rec.start();
            pintar(btn, true);
            decir('Escuchando… habla y pulsa de nuevo el micrófono para terminar.');
        } catch (e) {
            activo = null;
            pintar(btn, false);
            decir('No se pudo iniciar el micrófono.', true);
        }
    }

    function alternar(campo, btn) {
        if (activo && activo.btn === btn) { detener(); return; }
        if (activo) { detener(true); }
        var arrancar = function () { campo.focus(); iniciar(campo, btn, 0); };
        if (aceptado()) { arrancar(); } else { pedirAceptacion(arrancar); }
    }

    // ---------- Qué campos reciben el botón ----------
    function elegible(el) {
        var tag = el.tagName;
        if (tag === 'INPUT') {
            var t = (el.getAttribute('type') || 'text').toLowerCase();
            if (t !== 'text' && t !== 'search') { return false; }
            if (el.maxLength > 0 && el.maxLength <= 15) { return false; } // códigos cortos
        } else if (tag !== 'TEXTAREA') {
            return false;
        }
        if (el.readOnly || el.disabled) { return false; }
        if (el.hasAttribute('data-sin-voz') || el.closest('[data-sin-voz], [role="search"], .leaflet-container, dialog.voz-dialogo')) { return false; }
        var ident = (el.name || '') + ' ' + (el.id || '') + ' ' + (el.getAttribute('autocomplete') || '');
        if (EXCLUIR.test(ident)) { return false; }
        if (/numeric|tel|email|decimal|url/.test((el.getAttribute('inputmode') || '').toLowerCase())) { return false; }
        return true;
    }

    function agregar(el) {
        if (el.getAttribute('data-voz-listo')) { return; }
        el.setAttribute('data-voz-listo', '1');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'voz-btn';
        btn.textContent = '🎤';
        pintar(btn, false);
        btn.addEventListener('click', function () { alternar(el, btn); });
        // El micrófono va DENTRO del campo (a la derecha), en una envoltura propia: antes quedaba
        // en una línea aparte debajo de cada campo y alargaba mucho los formularios.
        var envoltura = document.createElement('span');
        envoltura.className = 'voz-campo' + (el.tagName === 'TEXTAREA' ? ' voz-campo-area' : '');
        el.parentNode.insertBefore(envoltura, el);
        envoltura.appendChild(el);
        envoltura.appendChild(btn);
    }

    function escanear(raiz) {
        if (raiz.nodeType !== 1) { return; }
        if (raiz.matches && raiz.matches('textarea, input') && elegible(raiz)) { agregar(raiz); }
        Array.prototype.forEach.call(raiz.querySelectorAll('textarea, input'), function (el) { if (elegible(el)) { agregar(el); } });
    }

    document.addEventListener('DOMContentLoaded', function () {
        escanear(document.body);
        new MutationObserver(function (cambios) {
            cambios.forEach(function (c) { Array.prototype.forEach.call(c.addedNodes, escanear); });
        }).observe(document.body, { childList: true, subtree: true });
    });
    window.addEventListener('pagehide', function () { detener(true); });
})();
