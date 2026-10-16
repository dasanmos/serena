/* SERENA — acceso_denegado.js
   Cuenta regresiva hacia el panel principal. "Quedarme aquí" la cancela
   (WCAG 2.2.1: el límite de tiempo se puede detener). Sin JavaScript queda
   el botón "Ir a mi panel ahora". */
(function () {
    'use strict';
    var caja = document.getElementById('acceso-denegado-redireccion');
    if (!caja) { return; }

    var destino = caja.getAttribute('data-destino') || '';
    var total = parseInt(caja.getAttribute('data-segundos') || '6', 10);
    var restan = total;
    var etiqueta = document.getElementById('acceso-denegado-segundos');
    var barra = document.getElementById('acceso-denegado-progreso');
    var cancelar = document.getElementById('acceso-denegado-cancelar');
    var temporizador = null;

    if (!destino || !(total > 0)) { return; }

    function pintar() {
        if (etiqueta) { etiqueta.textContent = String(restan); }
        // La barra avanza hacia el valor del SIGUIENTE segundo (transición de 1 s): llega justo al tic.
        if (barra) { barra.style.width = (Math.min(total, total - restan + 1) / total * 100) + '%'; }
    }

    function detener() {
        if (temporizador !== null) {
            window.clearInterval(temporizador);
            temporizador = null;
        }
    }

    function tic() {
        restan -= 1;
        pintar();
        if (restan <= 0) {
            detener();
            window.location.replace(destino);
        }
    }

    if (cancelar) {
        cancelar.hidden = false;
        cancelar.addEventListener('click', function () {
            detener();
            caja.hidden = true;
            cancelar.hidden = true;
        });
    }

    temporizador = window.setInterval(tic, 1000);
    // Primer tramo un instante después de pintar la página, para que la transición se vea.
    window.setTimeout(function () { if (temporizador !== null) { pintar(); } }, 30);
})();
