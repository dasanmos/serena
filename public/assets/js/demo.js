/* SERENA — demo.js
   Guion de la demostración: marcas de «Presentado» (se guardan en este navegador), barra de avance,
   buscador de módulos (guion y mapa) e impresión. Nada de esto toca datos del servidor. */
(function () {
    'use strict';
    var CLAVE = 'serena_demo_vistos';

    function leer() {
        try { return JSON.parse(window.localStorage.getItem(CLAVE) || '{}') || {}; } catch (e) { return {}; }
    }
    function guardar(obj) {
        try { window.localStorage.setItem(CLAVE, JSON.stringify(obj)); } catch (e) { /* sin almacenamiento: las marcas duran solo esta visita */ }
    }

    // ---- Marcas «Presentado» y progreso
    var marcas = document.querySelectorAll('.demo-marca');
    var texto = document.getElementById('demo-progreso-texto');
    var barra = document.getElementById('demo-progreso-barra');
    var vistos = leer();

    function pintar() {
        var total = marcas.length, hechos = 0;
        marcas.forEach(function (m) {
            var on = !!vistos[m.getAttribute('data-clave')];
            m.checked = on;
            var art = m.closest('.demo-modulo');
            if (art) { art.classList.toggle('demo-hecho', on); }
            if (on) { hechos++; }
        });
        if (texto) { texto.textContent = hechos + ' de ' + total + ' presentados'; }
        if (barra) { barra.style.width = (total ? Math.round(hechos / total * 100) : 0) + '%'; }
    }
    if (marcas.length) {
        marcas.forEach(function (m) {
            m.addEventListener('change', function () {
                var k = m.getAttribute('data-clave');
                if (m.checked) { vistos[k] = 1; } else { delete vistos[k]; }
                guardar(vistos);
                pintar();
            });
        });
        var limpiar = document.getElementById('demo-limpiar');
        if (limpiar) {
            limpiar.addEventListener('click', function () {
                var cont = document.querySelector('.demo-guion');
                var rol = cont ? (cont.getAttribute('data-rol') || '') : '';
                Object.keys(vistos).forEach(function (k) { if (!rol || k.indexOf(rol + ':') === 0) { delete vistos[k]; } });
                guardar(vistos);
                pintar();
            });
        }
        pintar();
    }

    // ---- Impresión
    var imprimir = document.getElementById('demo-imprimir');
    if (imprimir) { imprimir.addEventListener('click', function () { window.print(); }); }

    // ---- Buscador (guion y mapa)
    var filtro = document.getElementById('demo-filtro');
    if (filtro) {
        filtro.addEventListener('input', function () {
            var q = filtro.value.trim().toLowerCase();
            document.querySelectorAll('.demo-modulo, .demo-mapa-fila').forEach(function (el) {
                el.hidden = q !== '' && el.textContent.toLowerCase().indexOf(q) === -1;
            });
            document.querySelectorAll('.demo-grupo').forEach(function (g) {
                g.hidden = q !== '' && !g.querySelector('.demo-modulo:not([hidden])');
            });
        });
    }
})();
