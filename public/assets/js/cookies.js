/* ============================================================
   SERENA — cookies.js
   Aviso de cookies. La decisión se guarda en localStorage ("serena_cookies"):
     'necesarias' → solo la cookie de sesión y las preferencias de apariencia.
     'todas'      → además, servicios de terceros opcionales (hoy: mapas de OpenStreetMap).
   Otros scripts la leen con window.serenaCookies.estado() (mapas.js usa el mismo valor).
   Enlaces/botones con data-cookies-abrir reabren el aviso; con data-cookies-elegir="todas|necesarias"
   guardan la decisión directamente (página cookies.php).
   ============================================================ */
(function () {
    'use strict';

    var CLAVE = 'serena_cookies';
    var CLAVE_FECHA = 'serena_cookies_fecha';

    function leer() {
        try { var v = localStorage.getItem(CLAVE); return (v === 'todas' || v === 'necesarias') ? v : null; } catch (e) { return null; }
    }
    function guardar(valor) {
        try {
            localStorage.setItem(CLAVE, valor);
            localStorage.setItem(CLAVE_FECHA, new Date().toISOString().slice(0, 10));
        } catch (e) { /* modo privado: la decisión vale solo para esta visita */ }
        pintarEstado();
        document.dispatchEvent(new CustomEvent('serena:cookies', { detail: { estado: valor } }));
    }
    // La política está en la raíz pública: se deduce de la ruta de este mismo script (funciona en cualquier carpeta).
    var rutaScript = (document.currentScript && document.currentScript.src) || '';
    function urlPolitica() {
        return rutaScript ? rutaScript.replace(/\/assets\/js\/cookies\.js.*$/, '/cookies.php') : (window.SERENA_BASE_URL || '') + '/cookies.php';
    }

    function pintarEstado() {
        var e = leer();
        var texto = e === 'todas' ? 'Aceptaste todas las cookies (necesarias y servicios de terceros opcionales).'
            : e === 'necesarias' ? 'Elegiste solo las cookies necesarias.'
            : 'Todavía no has elegido.';
        Array.prototype.forEach.call(document.querySelectorAll('[data-cookies-estado]'), function (n) { n.textContent = texto; });
    }

    var banner = null;
    function cerrar() {
        if (banner && banner.parentNode) { banner.parentNode.removeChild(banner); }
        banner = null;
    }
    function crear(tag, clase, texto) {
        var n = document.createElement(tag);
        if (clase) { n.className = clase; }
        if (texto) { n.textContent = texto; }
        return n;
    }

    function mostrar(enfocar) {
        if (banner) { return; }
        banner = crear('section', 'aviso-cookies');
        banner.setAttribute('role', 'region');
        banner.setAttribute('aria-labelledby', 'aviso-cookies-titulo');

        var cuerpo = crear('div', 'aviso-cookies-texto');
        var h = crear('h2', 'aviso-cookies-titulo', 'Tu privacidad en Serena');
        h.id = 'aviso-cookies-titulo';
        var p = crear('p', '', 'Usamos una cookie necesaria para que puedas iniciar sesión y mantener tu cuenta segura, y guardamos en tu navegador tus preferencias (como el modo oscuro). ' +
            'Si lo permites, también cargamos mapas de OpenStreetMap. No usamos cookies de publicidad ni de análisis. ');
        var a = crear('a', '', 'Más información');
        a.href = urlPolitica();
        p.appendChild(a);
        p.appendChild(document.createTextNode('.'));
        cuerpo.appendChild(h); cuerpo.appendChild(p);

        var acciones = crear('div', 'aviso-cookies-acciones');
        var solo = crear('button', 'aviso-cookies-sec', 'Solo necesarias');
        solo.type = 'button';
        var todas = crear('button', 'aviso-cookies-pri', 'Aceptar todas');
        todas.type = 'button';
        solo.addEventListener('click', function () { guardar('necesarias'); cerrar(); });
        todas.addEventListener('click', function () { guardar('todas'); cerrar(); });
        acciones.appendChild(solo); acciones.appendChild(todas);

        banner.appendChild(cuerpo); banner.appendChild(acciones);
        // Primer elemento del <body>: así se llega con el teclado sin recorrer toda la página.
        document.body.insertBefore(banner, document.body.firstChild);
        if (enfocar) { todas.focus(); }
    }

    window.serenaCookies = { estado: leer, abrir: function () { mostrar(true); } };

    document.addEventListener('DOMContentLoaded', function () {
        pintarEstado();
        // En la página de la política no se muestra solo: ya tiene sus propios botones.
        if (leer() === null && !document.querySelector('[data-cookies-pagina]')) { mostrar(false); }

        document.addEventListener('click', function (e) {
            var t = e.target.closest ? e.target.closest('[data-cookies-abrir],[data-cookies-elegir]') : null;
            if (!t) { return; }
            e.preventDefault();
            if (t.hasAttribute('data-cookies-elegir')) { guardar(t.getAttribute('data-cookies-elegir')); cerrar(); }
            else { mostrar(true); }
        });
    });
})();
