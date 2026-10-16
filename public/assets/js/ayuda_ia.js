/* Ayuda de Serena: «Pregúntale a Serena» (asistente con IA basado en el propio manual). */
(function () {
    'use strict';
    var caja = document.getElementById('ayuda-ia');
    if (!caja) { return; }
    var campo = document.getElementById('ayuda-ia-pregunta');
    var boton = document.getElementById('ayuda-ia-enviar');
    var salida = document.getElementById('ayuda-ia-respuesta');
    var ocupado = false;

    function pintar(texto, fuentes, clase) {
        salida.hidden = false;
        salida.className = 'ayuda-ia-respuesta' + (clase ? ' ' + clase : '');
        salida.textContent = '';
        var p = document.createElement('p');
        p.textContent = texto; // siempre textContent: la respuesta nunca se interpreta como HTML
        salida.appendChild(p);
        if (fuentes && fuentes.length) {
            var f = document.createElement('p');
            f.className = 'ayuda-ia-fuentes';
            f.appendChild(document.createTextNode('Ver en la ayuda: '));
            fuentes.forEach(function (s, i) {
                if (!s.ancla || !/^cap-[A-Za-z0-9_-]+$/.test(s.ancla)) { return; }
                var a = document.createElement('a');
                a.href = '#' + s.ancla;
                a.textContent = s.titulo;
                f.appendChild(a);
                if (i < fuentes.length - 1) { f.appendChild(document.createTextNode(' · ')); }
            });
            salida.appendChild(f);
        }
    }

    function preguntar() {
        var pregunta = (campo.value || '').trim();
        if (ocupado || pregunta.length < 3) { return; }
        ocupado = true;
        boton.disabled = true;
        boton.textContent = 'Buscando…';
        pintar('Estoy buscando en el manual…', null, 'ayuda-ia-espera');

        fetch(caja.getAttribute('data-endpoint'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ pregunta: pregunta, csrf_token: caja.getAttribute('data-csrf'), rol: caja.getAttribute('data-rol') || '' })
        }).then(function (r) {
            return r.json().catch(function () { return { exito: false, mensaje: 'Respuesta inesperada del servidor.' }; });
        }).then(function (j) {
            if (j && j.exito && j.datos) {
                pintar(j.datos.respuesta, j.datos.fuentes, j.datos.crisis ? 'ayuda-ia-crisis' : '');
            } else {
                pintar((j && j.mensaje) || 'No pude responder ahora.', null, 'ayuda-ia-error');
            }
        }).catch(function () {
            pintar('No hay conexión con el servidor. Prueba con el buscador de la ayuda.', null, 'ayuda-ia-error');
        }).then(function () {
            ocupado = false;
            boton.disabled = false;
            boton.textContent = 'Preguntar';
        });
    }

    boton.addEventListener('click', preguntar);
    campo.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); preguntar(); }
    });
})();
