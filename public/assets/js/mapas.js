/* ============================================================
   SERENA — mapas.js
   Mapas con Leaflet + OpenStreetMap (sin clave de API).
   Tres tipos, según el atributo data-mapa del contenedor:
     · "colegios" → un pin por colegio (superadmin / secretaría).
     · "picker"   → ubica una dirección y deja ajustar el pin (formularios).
     · "apoyo"    → lugares de ayuda para el estudiante + "más cercanos a mí".
   Privacidad: Leaflet y las teselas vienen de servidores externos (unpkg y OpenStreetMap),
   que reciben la IP de quien mira el mapa. Por eso el mapa solo carga si la persona
   aceptó las cookies de terceros, o si pulsa «Cargar mapa». La ubicación del dispositivo
   (solo en "apoyo", solo al pulsar el botón) se calcula en el navegador y NO se envía a Serena.
   ============================================================ */
(function () {
    'use strict';

    var LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    var LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    var CSS_SRI = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
    var JS_SRI = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
    var TESELAS = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    var ATRIBUCION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
    var CENTRO_DEFECTO = [6.2442, -75.5812]; // Medellín: solo cuando no hay ningún punto que mostrar

    // ---------- Utilidades ----------
    function terceroPermitido() {
        try { return localStorage.getItem('serena_cookies') === 'todas'; } catch (e) { return false; }
    }
    function leerJson(el) {
        var s = el.querySelector('script.mapa-datos');
        if (!s) { return []; }
        try { return JSON.parse(s.textContent || '[]'); } catch (e) { return []; }
    }
    function crear(tag, clase, texto) {
        var n = document.createElement(tag);
        if (clase) { n.className = clase; }
        if (texto !== undefined) { n.textContent = texto; }
        return n;
    }
    function normalizar(t) {
        return String(t).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    var rutaScript = (document.currentScript && document.currentScript.src) || '';
    // Enlaces "Cómo llegar": abren la app o el sitio de mapas con la ruta al destino (la app calcula
    // el recorrido desde donde esté la persona). Son enlaces normales: no se envía nada a Serena.
    function enlacesRuta(lat, lng, nombre) {
        var ll = lat + ',' + lng, ua = navigator.userAgent || '', e = [];
        if (/Android/i.test(ua)) { e.push(['Mi app de mapas', 'geo:' + ll + '?q=' + ll + '(' + encodeURIComponent(nombre) + ')']); }
        e.push(['Google Maps', 'https://www.google.com/maps/dir/?api=1&destination=' + ll]);
        e.push(['Waze', 'https://waze.com/ul?ll=' + ll + '&navigate=yes']);
        if (/iPhone|iPad|iPod|Macintosh/i.test(ua)) { e.push(['Apple Maps', 'https://maps.apple.com/?daddr=' + ll + '&dirflg=d']); }
        e.push(['OpenStreetMap', 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lng + '#map=17/' + lat + '/' + lng]);
        return e;
    }
    function cajaComoLlegar(lat, lng, nombre) {
        var caja = crear('div', 'mapa-popup-ruta');
        caja.appendChild(crear('span', 'mapa-popup-suave', '🧭 Cómo llegar:'));
        enlacesRuta(lat, lng, nombre).forEach(function (par) {
            var a = crear('a', '', par[0]);
            a.href = par[1]; a.target = '_blank'; a.rel = 'noopener noreferrer';
            caja.appendChild(a);
        });
        return caja;
    }
    function urlCookies() {
        return rutaScript ? rutaScript.replace(/\/assets\/js\/mapas\.js.*$/, '/cookies.php') : (window.SERENA_BASE_URL || '') + '/cookies.php';
    }

    // ---------- Carga de Leaflet (una sola vez) ----------
    var promesaLeaflet = null;
    function cargarLeaflet() {
        if (window.L && window.L.map) { return Promise.resolve(window.L); }
        if (promesaLeaflet) { return promesaLeaflet; }
        promesaLeaflet = new Promise(function (ok, fallo) {
            var css = document.createElement('link');
            css.rel = 'stylesheet'; css.href = LEAFLET_CSS; css.integrity = CSS_SRI; css.crossOrigin = '';
            document.head.appendChild(css);
            var js = document.createElement('script');
            js.src = LEAFLET_JS; js.integrity = JS_SRI; js.crossOrigin = '';
            js.onload = function () { ok(window.L); };
            js.onerror = function () { promesaLeaflet = null; fallo(new Error('leaflet')); };
            document.head.appendChild(js);
        });
        return promesaLeaflet;
    }

    // Muestra el aviso previo (sin consentimiento) o arranca directamente.
    function conPermiso(lienzo, arrancar) {
        function ir() {
            lienzo.classList.remove('mapa-bloqueado');
            lienzo.textContent = 'Cargando mapa…';
            cargarLeaflet().then(function (L) {
                lienzo.textContent = '';
                arrancar(L);
            }).catch(function () {
                lienzo.classList.add('mapa-bloqueado');
                lienzo.textContent = '';
                lienzo.appendChild(crear('p', 'mapa-aviso', 'No se pudo cargar el mapa. Revisa tu conexión e inténtalo de nuevo.'));
                var b = crear('button', 'mapa-boton', 'Reintentar');
                b.type = 'button';
                b.addEventListener('click', ir);
                lienzo.appendChild(b);
            });
        }
        if (terceroPermitido()) { ir(); return; }
        lienzo.classList.add('mapa-bloqueado');
        lienzo.textContent = '';
        var p = crear('p', 'mapa-aviso', 'Este mapa usa OpenStreetMap, un servicio externo que recibe tu dirección IP al cargar el mapa.');
        var b = crear('button', 'mapa-boton', 'Cargar mapa');
        b.type = 'button';
        b.addEventListener('click', ir);
        var a = crear('a', 'mapa-enlace', 'Más información sobre cookies');
        a.href = urlCookies();
        lienzo.appendChild(p); lienzo.appendChild(b); lienzo.appendChild(a);
    }

    function nuevoMapa(L, lienzo) {
        var mapa = L.map(lienzo, { scrollWheelZoom: false });
        L.tileLayer(TESELAS, { maxZoom: 19, attribution: ATRIBUCION }).addTo(mapa);
        mapa.on('click focus', function () { mapa.scrollWheelZoom.enable(); });
        mapa.on('blur', function () { mapa.scrollWheelZoom.disable(); });
        return mapa;
    }
    function encuadrar(mapa, puntos) {
        if (!puntos.length) { mapa.setView(CENTRO_DEFECTO, 11); }
        else if (puntos.length === 1) { mapa.setView(puntos[0], 15); }
        else { mapa.fitBounds(puntos, { padding: [30, 30], maxZoom: 15 }); }
    }

    // ============================================================
    // 1) Mapa de colegios
    // ============================================================
    function iniciarColegios(el) {
        var lienzo = el.querySelector('.mapa-lienzo');
        var datos = leerJson(el);
        conPermiso(lienzo, function (L) {
            var mapa = nuevoMapa(L, lienzo);
            var marcadores = {};
            var puntos = [];
            datos.forEach(function (c) {
                if (c.lat === null || c.lng === null) { return; }
                var m = L.marker([c.lat, c.lng], { title: c.nombre, opacity: c.activo ? 1 : 0.55 }).addTo(mapa);
                var caja = crear('div', 'mapa-popup');
                caja.appendChild(crear('strong', '', c.nombre));
                caja.appendChild(crear('div', '', 'DANE: ' + c.dane));
                if (c.municipio) { caja.appendChild(crear('div', '', c.municipio)); }
                if (c.direccion) { caja.appendChild(crear('div', 'mapa-popup-suave', c.direccion)); }
                caja.appendChild(crear('div', 'mapa-popup-suave', (c.activo ? 'Activo' : 'Suspendido') + ' · ' + c.estudiantes + ' estudiantes'));
                caja.appendChild(cajaComoLlegar(c.lat, c.lng, c.nombre));
                m.bindPopup(caja);
                marcadores[c.id] = m;
                puntos.push([c.lat, c.lng]);
            });
            encuadrar(mapa, puntos);

            document.addEventListener('click', function (e) {
                var b = e.target.closest ? e.target.closest('[data-ver-mapa]') : null;
                if (!b) { return; }
                var m = marcadores[b.getAttribute('data-ver-mapa')];
                if (!m) { return; }
                e.preventDefault();
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                mapa.setView(m.getLatLng(), 16);
                m.openPopup();
            });
        });
    }

    // ============================================================
    // 2) Selector de ubicación (formularios): dirección → pin ajustable
    // ============================================================
    function simplificarDireccion(d) {
        return d.replace(/#/g, ' ').replace(/\b(no\.?|n°|nº|numero|número)\b/gi, ' ').replace(/\s+/g, ' ').trim();
    }
    function geocodificar(q) {
        var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&countrycodes=co&accept-language=es&q=' + encodeURIComponent(q);
        return fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (r) {
            if (!r.ok) { throw new Error('http'); }
            return r.json();
        });
    }

    function iniciarPicker(el) {
        var form = el.closest('form');
        var lienzo = el.querySelector('.mapa-lienzo');
        var estado = el.querySelector('.mapa-estado');
        var alternativas = el.querySelector('.mapa-alternativas');
        var coordsTxt = el.querySelector('.mapa-coords');
        var btnUbicar = el.querySelector('[data-accion="ubicar"]');
        var btnQuitar = el.querySelector('[data-accion="quitar"]');
        if (!form || !lienzo) { return; }
        var campo = function (n) { return form.querySelector('[name="' + n + '"]'); };
        var inLat = campo('latitud'), inLng = campo('longitud');
        var mapa = null, marcador = null, iniciado = false;

        function mensaje(t, error) {
            if (!estado) { return; }
            estado.textContent = t || '';
            estado.classList.toggle('mapa-estado-error', !!error);
        }
        function pintarCoords() {
            if (!coordsTxt) { return; }
            coordsTxt.textContent = (inLat.value && inLng.value) ? 'Ubicación guardada en el pin: ' + inLat.value + ', ' + inLng.value : 'Aún sin ubicar.';
            if (btnQuitar) { btnQuitar.hidden = !(inLat.value && inLng.value); }
        }
        function poner(L, lat, lng, centrar) {
            lat = Math.round(lat * 1e6) / 1e6; lng = Math.round(lng * 1e6) / 1e6;
            inLat.value = String(lat); inLng.value = String(lng);
            if (!marcador) {
                marcador = L.marker([lat, lng], { draggable: true }).addTo(mapa);
                marcador.on('dragend', function () {
                    var p = marcador.getLatLng();
                    poner(L, p.lat, p.lng, false);
                    mensaje('Pin ajustado a mano.');
                });
            } else {
                marcador.setLatLng([lat, lng]);
            }
            if (centrar) { mapa.setView([lat, lng], 17); }
            pintarCoords();
        }
        function quitar() {
            inLat.value = ''; inLng.value = '';
            if (marcador) { mapa.removeLayer(marcador); marcador = null; }
            mensaje('Ubicación quitada.');
            pintarCoords();
        }

        function arrancar(L) {
            iniciado = true;
            mapa = nuevoMapa(L, lienzo);
            var lat = parseFloat(inLat.value), lng = parseFloat(inLng.value);
            if (!isNaN(lat) && !isNaN(lng)) { poner(L, lat, lng, false); mapa.setView([lat, lng], 16); }
            else { mapa.setView(CENTRO_DEFECTO, 11); }
            mapa.on('click', function (ev) {
                poner(L, ev.latlng.lat, ev.latlng.lng, false);
                mensaje('Pin colocado donde hiciste clic.');
            });
            pintarCoords();
            if (btnUbicar) { btnUbicar.disabled = false; }

            if (btnUbicar) {
                btnUbicar.addEventListener('click', function () {
                    var dir = (campo('direccion') || {}).value || '';
                    var mun = (campo('municipio') || {}).value || '';
                    var nom = (campo('nombre') || {}).value || '';
                    dir = dir.trim(); mun = mun.trim();
                    if (!dir) { mensaje('Escribe primero la dirección.', true); return; }
                    var intentos = [
                        dir + ', ' + mun + ', Colombia',
                        simplificarDireccion(dir) + ', ' + mun + ', Colombia'
                    ];
                    if (nom.trim()) { intentos.push(nom.trim() + ', ' + mun + ', Colombia'); }
                    btnUbicar.disabled = true;
                    mensaje('Buscando…');
                    if (alternativas) { alternativas.textContent = ''; }

                    (function probar(i) {
                        if (i >= intentos.length) {
                            mensaje('No encontré esa dirección. Escríbela más simple (ej. «Carrera 50 24 15») o haz clic en el mapa para colocar el pin.', true);
                            setTimeout(function () { btnUbicar.disabled = false; }, 1100);
                            return;
                        }
                        // Nominatim pide máximo 1 consulta por segundo.
                        setTimeout(function () {
                            geocodificar(intentos[i]).then(function (res) {
                                if (!res || !res.length) { probar(i + 1); return; }
                                var r = res[0];
                                poner(L, parseFloat(r.lat), parseFloat(r.lon), true);
                                mensaje('Encontrada: ' + r.display_name + '. Si no es exacta, arrastra el pin.');
                                if (res.length > 1 && alternativas) {
                                    alternativas.appendChild(crear('span', 'mapa-alt-titulo', 'Otras coincidencias:'));
                                    res.slice(1).forEach(function (o) {
                                        var b = crear('button', 'mapa-alt', o.display_name);
                                        b.type = 'button';
                                        b.addEventListener('click', function () {
                                            poner(L, parseFloat(o.lat), parseFloat(o.lon), true);
                                            mensaje('Elegida: ' + o.display_name);
                                        });
                                        alternativas.appendChild(b);
                                    });
                                }
                                setTimeout(function () { btnUbicar.disabled = false; }, 1100);
                            }).catch(function () {
                                mensaje('No se pudo consultar el buscador de direcciones. Haz clic en el mapa para colocar el pin.', true);
                                setTimeout(function () { btnUbicar.disabled = false; }, 1100);
                            });
                        }, i === 0 ? 0 : 1100);
                    })(0);
                });
            }
            if (btnQuitar) { btnQuitar.addEventListener('click', quitar); }
        }

        function iniciar() {
            if (iniciado) { if (mapa) { mapa.invalidateSize(); } return; }
            if (btnUbicar) { btnUbicar.disabled = true; }
            conPermiso(lienzo, arrancar);
        }

        pintarCoords();
        var det = el.closest('details');
        if (det && !det.open) {
            det.addEventListener('toggle', function () { if (det.open) { iniciar(); } });
        } else {
            iniciar();
        }
    }

    // ============================================================
    // 3) Lugares de ayuda (estudiante) + "más cercanos a mí"
    // ============================================================
    function distanciaKm(a, b) {
        var R = 6371, rad = Math.PI / 180;
        var dLat = (b[0] - a[0]) * rad, dLng = (b[1] - a[1]) * rad;
        var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(a[0] * rad) * Math.cos(b[0] * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 2 * R * Math.asin(Math.sqrt(x));
    }
    function textoDistancia(km) {
        return km < 1 ? Math.round(km * 1000 / 10) * 10 + ' m' : km.toFixed(1).replace('.', ',') + ' km';
    }

    function iniciarApoyo(el) {
        var lienzo = el.querySelector('.mapa-lienzo');
        var lista = el.querySelector('.lugares-lista');
        var datos = leerJson(el);
        var btnCerca = el.querySelector('[data-accion="cerca"]');
        var estado = el.querySelector('.mapa-estado');
        var mapa = null, LRef = null, marcadores = {}, yo = null;

        function mensaje(t, error) {
            if (!estado) { return; }
            estado.textContent = t || '';
            estado.classList.toggle('mapa-estado-error', !!error);
        }

        conPermiso(lienzo, function (L) {
            LRef = L;
            mapa = nuevoMapa(L, lienzo);
            var puntos = [];
            datos.forEach(function (d) {
                var m = L.marker([d.lat, d.lng], { title: d.nombre }).addTo(mapa);
                var caja = crear('div', 'mapa-popup');
                caja.appendChild(crear('strong', '', d.nombre));
                caja.appendChild(crear('div', 'mapa-popup-suave', d.tipo));
                if (d.direccion) { caja.appendChild(crear('div', '', d.direccion)); }
                if (d.telefono) { caja.appendChild(crear('div', '', 'Tel. ' + d.telefono)); }
                caja.appendChild(cajaComoLlegar(d.lat, d.lng, d.nombre));
                m.bindPopup(caja);
                marcadores[d.id] = m;
                puntos.push([d.lat, d.lng]);
            });
            encuadrar(mapa, puntos);
        });

        // Clic en un lugar de la lista → lo muestra en el mapa
        if (lista) {
            lista.addEventListener('click', function (e) {
                var b = e.target.closest ? e.target.closest('[data-ver-lugar]') : null;
                if (!b || !mapa) { return; }
                var m = marcadores[b.getAttribute('data-ver-lugar')];
                if (!m) { return; }
                e.preventDefault();
                lienzo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                mapa.setView(m.getLatLng(), 16);
                m.openPopup();
            });
        }

        // "Más cercanos a mí": solo al pulsar el botón; no se envía a ningún servidor.
        if (btnCerca) {
            if (!('geolocation' in navigator)) { btnCerca.hidden = true; }
            btnCerca.addEventListener('click', function () {
                mensaje('Buscando tu ubicación…');
                navigator.geolocation.getCurrentPosition(function (pos) {
                    var yoPos = [pos.coords.latitude, pos.coords.longitude];
                    var filas = Array.prototype.slice.call(lista ? lista.querySelectorAll('[data-lugar]') : []);
                    var conDist = filas.map(function (f) {
                        var d = distanciaKm(yoPos, [parseFloat(f.getAttribute('data-lat')), parseFloat(f.getAttribute('data-lng'))]);
                        return { fila: f, km: d };
                    }).sort(function (a, b) { return a.km - b.km; });
                    conDist.forEach(function (o) {
                        var sp = o.fila.querySelector('.lugar-distancia');
                        if (sp) { sp.textContent = textoDistancia(o.km); sp.hidden = false; }
                        lista.appendChild(o.fila);
                    });
                    if (mapa && LRef) {
                        if (yo) { mapa.removeLayer(yo); }
                        yo = LRef.circleMarker(yoPos, { radius: 8, color: '#2E6E8E', fillColor: '#2E6E8E', fillOpacity: .7 }).addTo(mapa).bindTooltip('Tú estás aquí');
                        var cercanos = conDist.slice(0, 3).map(function (o) {
                            return [parseFloat(o.fila.getAttribute('data-lat')), parseFloat(o.fila.getAttribute('data-lng'))];
                        });
                        mapa.fitBounds(cercanos.concat([yoPos]), { padding: [30, 30], maxZoom: 15 });
                    }
                    mensaje('Ordenados del más cercano al más lejano. Tu ubicación no se guarda ni se envía.');
                }, function (err) {
                    mensaje(err && err.code === 1
                        ? 'No diste permiso para usar tu ubicación. La lista sigue disponible sin ordenar.'
                        : 'No pudimos obtener tu ubicación. Prueba de nuevo o usa la lista.', true);
                }, { enableHighAccuracy: false, timeout: 12000, maximumAge: 60000 });
            });
        }
    }

    // ---------- Arranque ----------
    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-mapa]'), function (el) {
            var tipo = el.getAttribute('data-mapa');
            if (tipo === 'colegios') { iniciarColegios(el); }
            else if (tipo === 'picker') { iniciarPicker(el); }
            else if (tipo === 'apoyo') { iniciarApoyo(el); }
        });
    });
})();
