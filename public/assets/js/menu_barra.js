// SERENA — barra de menú horizontal: desplegables (mouse, toque, teclado) y cajón con acordeón en celular.
(function () {
    'use strict';
    var barra = document.getElementById('menubar');
    if (!barra) { return; }
    var fondo = document.getElementById('menubar-fondo');
    var botonMovil = document.getElementById('boton-menu-movil');
    var botonCerrar = document.getElementById('menubar-cerrar');
    var esMovil = window.matchMedia('(max-width: 899.98px)');
    var conHover = window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 900px)');
    var grupos = Array.prototype.slice.call(barra.querySelectorAll('.menubar-grupo'));
    var CLAVE = 'serena_menu_grupos';
    var guardado = {};
    try { guardado = JSON.parse(localStorage.getItem(CLAVE) || '{}') || {}; } catch (e) { guardado = {}; }

    function fijar(g, abierto) {
        g.classList.toggle('abierto', abierto);
        var b = g.querySelector('.menubar-boton');
        if (b) { b.setAttribute('aria-expanded', abierto ? 'true' : 'false'); }
    }
    function cerrarTodos(menos) { grupos.forEach(function (g) { if (g !== menos) { fijar(g, false); } }); }
    function alinear(g) {
        g.classList.remove('alinear-der');
        var sub = g.querySelector('.menubar-submenu');
        if (!sub || esMovil.matches) { return; }
        var r = sub.getBoundingClientRect();
        if (r.right > window.innerWidth - 8) { g.classList.add('alinear-der'); }
    }

    // Cajón (celular)
    function abrirCajon() {
        barra.classList.add('abierto');
        if (fondo) { fondo.classList.add('visible'); }
        if (botonMovil) { botonMovil.setAttribute('aria-expanded', 'true'); }
        document.documentElement.style.overflow = 'hidden';
    }
    function cerrarCajon() {
        barra.classList.remove('abierto');
        if (fondo) { fondo.classList.remove('visible'); }
        if (botonMovil) { botonMovil.setAttribute('aria-expanded', 'false'); }
        document.documentElement.style.overflow = '';
    }
    if (botonMovil) { botonMovil.addEventListener('click', function (e) { e.stopPropagation(); barra.classList.contains('abierto') ? cerrarCajon() : abrirCajon(); }); }
    if (botonCerrar) { botonCerrar.addEventListener('click', cerrarCajon); }
    if (fondo) { fondo.addEventListener('click', cerrarCajon); }

    // Estado inicial: en celular se abre la categoría actual (y las que la persona dejó abiertas); en escritorio, todo cerrado.
    function estadoInicial() {
        cerrarCajon();
        grupos.forEach(function (g) {
            var nombre = g.getAttribute('data-grupo');
            var abierto = false;
            if (esMovil.matches) {
                abierto = g.classList.contains('activo') || (Object.prototype.hasOwnProperty.call(guardado, nombre) && !!guardado[nombre]);
            }
            fijar(g, abierto);
        });
    }
    estadoInicial();
    if (esMovil.addEventListener) { esMovil.addEventListener('change', estadoInicial); } else if (esMovil.addListener) { esMovil.addListener(estadoInicial); }

    grupos.forEach(function (g) {
        var boton = g.querySelector('.menubar-boton');
        var nombre = g.getAttribute('data-grupo');
        var temporizador = null;
        if (!boton) { return; }
        boton.addEventListener('click', function (e) {
            e.stopPropagation();
            var abrir = !g.classList.contains('abierto');
            if (!esMovil.matches) { cerrarTodos(g); alinear(g); }
            fijar(g, abrir);
            if (esMovil.matches) {
                guardado[nombre] = abrir;
                try { localStorage.setItem(CLAVE, JSON.stringify(guardado)); } catch (err) { /* sin almacenamiento: no pasa nada */ }
            }
        });
        boton.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' && !esMovil.matches) {
                e.preventDefault(); cerrarTodos(g); alinear(g); fijar(g, true);
                var primero = g.querySelector('.menubar-enlace'); if (primero) { primero.focus(); }
            }
        });
        g.addEventListener('mouseenter', function () {
            if (!conHover.matches) { return; }
            clearTimeout(temporizador); cerrarTodos(g); alinear(g); fijar(g, true);
        });
        g.addEventListener('mouseleave', function () {
            if (!conHover.matches) { return; }
            temporizador = setTimeout(function () { fijar(g, false); }, 160);
        });
        g.addEventListener('focusout', function (e) {
            if (esMovil.matches) { return; }
            if (!g.contains(e.relatedTarget)) { fijar(g, false); }
        });
        g.querySelectorAll('.menubar-enlace').forEach(function (a) {
            a.addEventListener('keydown', function (e) {
                if (esMovil.matches) { return; }
                var items = Array.prototype.slice.call(g.querySelectorAll('.menubar-enlace'));
                var i = items.indexOf(a);
                if (e.key === 'ArrowDown') { e.preventDefault(); (items[i + 1] || items[0]).focus(); }
                if (e.key === 'ArrowUp') { e.preventDefault(); (items[i - 1] || items[items.length - 1]).focus(); }
            });
        });
    });

    document.addEventListener('click', function (e) { if (!esMovil.matches && !barra.contains(e.target)) { cerrarTodos(null); } });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        var abierto = grupos.filter(function (g) { return g.classList.contains('abierto'); })[0];
        if (!esMovil.matches) { cerrarTodos(null); if (abierto) { var b = abierto.querySelector('.menubar-boton'); if (b) { b.focus(); } } }
        else { cerrarCajon(); }
    });
    barra.querySelectorAll('.menubar-enlace').forEach(function (a) { a.addEventListener('click', cerrarCajon); });
})();
