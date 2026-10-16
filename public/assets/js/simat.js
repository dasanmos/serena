/* SERENA — simat.js
 * Mejoras de SIMAT y SIMPADE sin dependencias. Todo está protegido: si algo falla aquí,
 * el resto de la página (menú, formularios, botones) sigue funcionando.
 *  1. Filtro en vivo de tablas:      <input data-sm-filtro="#idTabla">
 *  2. Contador de filas visibles:    <span data-sm-contador="#idTabla">
 *  3. Doble envío evitado:           los formularios POST deshabilitan el botón al enviar.
 *  4. Confirmación declarativa:      <button data-sm-confirmar="¿Seguro?">
 *  5. Marcar/desmarcar grupo:        <button type="button" data-sm-marcar="#contenedor" data-sm-valor="1">
 */
(function () {
    'use strict';

    function seguro(fn) {
        return function () {
            try { return fn.apply(this, arguments); } catch (e) { if (window.console) { console.warn('simat.js', e); } }
        };
    }

    function normalizar(t) {
        t = String(t || '').toLowerCase();
        try { t = t.normalize('NFD').replace(/[̀-ͯ]/g, ''); } catch (e) { /* navegador antiguo */ }
        return t.replace(/\s+/g, ' ').trim();
    }

    function iniciarFiltros() {
        var campos = document.querySelectorAll('[data-sm-filtro]');
        Array.prototype.forEach.call(campos, function (campo) {
            var tabla = document.querySelector(campo.getAttribute('data-sm-filtro'));
            if (!tabla) { return; }
            var filas = Array.prototype.slice.call(tabla.querySelectorAll('tbody tr'));
            var textos = filas.map(function (f) { return normalizar(f.textContent); });
            var contador = document.querySelector('[data-sm-contador="' + campo.getAttribute('data-sm-filtro') + '"]');
            var aplicar = seguro(function () {
                var q = normalizar(campo.value);
                var visibles = 0;
                filas.forEach(function (f, i) {
                    var ok = q === '' || textos[i].indexOf(q) !== -1;
                    f.hidden = !ok;
                    if (ok) { visibles++; }
                });
                if (contador) { contador.textContent = visibles + ' de ' + filas.length; }
            });
            campo.addEventListener('input', aplicar);
            aplicar();
        });
    }

    function iniciarEnvios() {
        document.addEventListener('submit', seguro(function (ev) {
            var f = ev.target;
            if (!f || f.tagName !== 'FORM' || String(f.method).toLowerCase() !== 'post') { return; }
            if (ev.defaultPrevented) { return; }
            var botones = f.querySelectorAll('button[type="submit"], button:not([type])');
            // Se deshabilita en el siguiente ciclo para no perder el valor name/value del botón pulsado.
            setTimeout(function () {
                Array.prototype.forEach.call(botones, function (b) { b.disabled = true; b.setAttribute('aria-busy', 'true'); });
            }, 0);
            // Si el usuario vuelve con «atrás» el botón no debe quedar bloqueado.
            window.addEventListener('pageshow', function () {
                Array.prototype.forEach.call(botones, function (b) { b.disabled = false; b.removeAttribute('aria-busy'); });
            }, { once: true });
        }));
    }

    function iniciarConfirmaciones() {
        document.addEventListener('click', seguro(function (ev) {
            var b = ev.target && ev.target.closest ? ev.target.closest('[data-sm-confirmar]') : null;
            if (b && !window.confirm(b.getAttribute('data-sm-confirmar'))) {
                ev.preventDefault();
                ev.stopPropagation();
            }
        }), true);
    }

    function iniciarMarcar() {
        document.addEventListener('click', seguro(function (ev) {
            var b = ev.target && ev.target.closest ? ev.target.closest('[data-sm-marcar]') : null;
            if (!b) { return; }
            var cont = document.querySelector(b.getAttribute('data-sm-marcar'));
            if (!cont) { return; }
            var valor = b.getAttribute('data-sm-valor') === '1';
            Array.prototype.forEach.call(cont.querySelectorAll('input[type="checkbox"]'), function (c) { c.checked = valor; });
        }));
    }

    function listo(fn) {
        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fn); } else { fn(); }
    }

    listo(seguro(function () {
        iniciarFiltros();
        iniciarEnvios();
        iniciarConfirmaciones();
        iniciarMarcar();
    }));
})();
