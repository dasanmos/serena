// Menú lateral por categorías: cajón en pantallas chicas y memoria de qué categorías dejó abiertas cada persona.
(function () {
    'use strict';
    var menu = document.getElementById('menu-lat');
    if (!menu) { return; }
    var boton = document.getElementById('menu-lat-boton');
    var fondo = document.getElementById('menu-lat-fondo');

    function abrir() {
        menu.classList.add('abierto');
        if (fondo) { fondo.classList.add('visible'); }
        if (boton) { boton.setAttribute('aria-expanded', 'true'); }
    }
    function cerrar() {
        menu.classList.remove('abierto');
        if (fondo) { fondo.classList.remove('visible'); }
        if (boton) { boton.setAttribute('aria-expanded', 'false'); }
    }
    if (boton) { boton.addEventListener('click', function () { menu.classList.contains('abierto') ? cerrar() : abrir(); }); }
    if (fondo) { fondo.addEventListener('click', cerrar); }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { cerrar(); } });

    // Categorías abiertas/cerradas: se recuerdan entre páginas. La categoría de la página actual siempre queda abierta.
    var CLAVE = 'serena_menu_grupos';
    var guardado = {};
    try { guardado = JSON.parse(localStorage.getItem(CLAVE) || '{}') || {}; } catch (e) { guardado = {}; }
    menu.querySelectorAll('details.menu-lat-grupo').forEach(function (d) {
        var nombre = d.getAttribute('data-grupo');
        var tieneActivo = !!d.querySelector('a.activo');
        if (!tieneActivo && Object.prototype.hasOwnProperty.call(guardado, nombre)) { d.open = !!guardado[nombre]; }
        d.addEventListener('toggle', function () {
            guardado[nombre] = d.open;
            try { localStorage.setItem(CLAVE, JSON.stringify(guardado)); } catch (e) { /* sin almacenamiento: no pasa nada */ }
        });
    });
    // Mantener visible el enlace activo dentro del menú
    var activo = menu.querySelector('a.activo');
    if (activo && activo.scrollIntoView) { activo.scrollIntoView({ block: 'nearest' }); }
})();
