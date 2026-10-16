// Portada: aparición suave al hacer scroll, sombra del menú y contadores. Todo opcional:
// sin JS o con "reducir movimiento" la página se ve completa igual.
(function () {
    var reducir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var nav = document.querySelector('.nav-publica');
    if (nav) {
        var onScroll = function () { nav.classList.toggle('con-sombra', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    if (reducir || !('IntersectionObserver' in window)) return;

    var objetivos = document.querySelectorAll('.seccion-landing, .paso-camino, .bloque-bento, .tarjeta-secretaria, .cifra-landing, .item-faq');
    objetivos.forEach(function (el) { el.classList.add('revelar'); });
    var io = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
        });
    }, { threshold: 0.12 });
    objetivos.forEach(function (el) { io.observe(el); });

    document.querySelectorAll('[data-contar]').forEach(function (el) {
        var meta = parseInt(el.getAttribute('data-contar'), 10);
        if (!meta || meta < 2) return;
        var obs = new IntersectionObserver(function (es) {
            if (!es[0].isIntersecting) return;
            obs.disconnect();
            var t0 = performance.now();
            (function paso(t) {
                var p = Math.min(1, (t - t0) / 900);
                el.textContent = Math.round(meta * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(paso);
            })(t0);
        }, { threshold: 0.6 });
        obs.observe(el);
    });
})();
