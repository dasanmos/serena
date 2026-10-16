(function () {
    'use strict';
    var imprimir = document.getElementById('inf-imprimir');
    if (imprimir) {
        imprimir.addEventListener('click', function () { window.print(); });
    }
    var buscar = document.getElementById('inf-buscar');
    if (buscar) {
        buscar.addEventListener('input', function () {
            var q = buscar.value.trim().toLowerCase();
            document.querySelectorAll('.inf-cat').forEach(function (cat) {
                var hay = 0;
                cat.querySelectorAll('.inf-card').forEach(function (c) {
                    var ok = q === '' || (c.getAttribute('data-nombre') || '').indexOf(q) !== -1;
                    c.style.display = ok ? '' : 'none';
                    if (ok) { hay++; }
                });
                cat.style.display = hay ? '' : 'none';
                if (q !== '' && hay) { cat.open = true; }
            });
        });
    }
    // Períodos y asignaturas: mostrar solo los del año elegido
    var anio = document.getElementById('f-anio');
    function filtrar() {
        if (!anio) { return; }
        ['f-periodo', 'f-asig'].forEach(function (id) {
            var s = document.getElementById(id);
            if (!s) { return; }
            Array.prototype.forEach.call(s.options, function (o) {
                var a = o.getAttribute('data-anio');
                o.hidden = a !== null && a !== anio.value;
                o.disabled = o.hidden;
            });
            if (s.selectedOptions.length && s.selectedOptions[0].disabled) { s.selectedIndex = 0; }
        });
    }
    if (anio) {
        anio.addEventListener('change', filtrar);
        filtrar();
    }
})();
