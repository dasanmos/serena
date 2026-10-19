/* Ayuda de Serena: buscador, índice móvil y resaltado del capítulo visible. */
(function () {
    'use strict';
    var raiz = document.getElementById('ayuda');
    if (!raiz) { return; }
    raiz.classList.add('ayuda-js');

    function normalizar(t) {
        return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    var capitulos = Array.prototype.slice.call(raiz.querySelectorAll('[data-capitulo]'));
    var enlaces = {};
    Array.prototype.forEach.call(raiz.querySelectorAll('[data-capitulo-enlace]'), function (a) {
        enlaces[a.getAttribute('data-capitulo-enlace')] = a;
    });
    var partes = Array.prototype.slice.call(raiz.querySelectorAll('.ayuda-parte'));
    var indicePartes = Array.prototype.slice.call(raiz.querySelectorAll('.ayuda-indice-parte'));

    // Por capítulo: texto de cabecera (título + resumen) y texto de cada sección.
    var modelo = capitulos.map(function (c) {
        var cab = c.querySelector('.ayuda-cap-cab');
        var res = c.querySelector('.ayuda-cap-resumen');
        var secciones = Array.prototype.slice.call(c.querySelectorAll('.ayuda-seccion'));
        return {
            el: c,
            cabecera: normalizar(((cab && cab.textContent) || '') + ' ' + ((res && res.textContent) || '')),
            secciones: secciones.map(function (s) { return { el: s, texto: normalizar(s.textContent || '') }; })
        };
    });

    // --- Buscador ---
    var campo = document.getElementById('ayuda-buscar');
    var mensaje = document.getElementById('ayuda-resultados');
    var vacio = document.getElementById('ayuda-vacio');
    var limpiar = document.getElementById('ayuda-limpiar');
    var cuadro = campo ? campo.closest('.ayuda-busqueda') : null;
    if (cuadro) { cuadro.classList.add('ayuda-busqueda-activa'); }

    function quitarMarcas() {
        Array.prototype.forEach.call(raiz.querySelectorAll('mark.ayuda-marca'), function (m) {
            var padre = m.parentNode;
            padre.replaceChild(document.createTextNode(m.textContent), m);
            padre.normalize();
        });
    }

    // Resalta las palabras buscadas dentro de un elemento (respeta tildes: compara normalizado
    // pero conserva el texto original; la normalización NFD cambia longitudes, así que se
    // recorre carácter a carácter con un mapa de posiciones).
    function resaltar(elemento, palabras) {
        var walker = document.createTreeWalker(elemento, NodeFilter.SHOW_TEXT, null);
        var nodos = [];
        while (walker.nextNode()) { nodos.push(walker.currentNode); }
        nodos.forEach(function (nodo) {
            var original = nodo.nodeValue;
            if (!original.trim() || nodo.parentNode.closest('a, button, mark, script, style')) { return; }
            var plano = '';
            var mapa = [];
            for (var i = 0; i < original.length; i++) {
                var n = normalizar(original.charAt(i));
                for (var k = 0; k < n.length; k++) { plano += n.charAt(k); mapa.push(i); }
            }
            var rangos = [];
            palabras.forEach(function (p) {
                var desde = 0, pos;
                while ((pos = plano.indexOf(p, desde)) !== -1) {
                    rangos.push([mapa[pos], mapa[pos + p.length - 1] + 1]);
                    desde = pos + p.length;
                }
            });
            if (!rangos.length) { return; }
            rangos.sort(function (x, y) { return x[0] - y[0]; });
            var fusion = [rangos[0].slice()];
            rangos.slice(1).forEach(function (r) {
                var ult = fusion[fusion.length - 1];
                if (r[0] <= ult[1]) { ult[1] = Math.max(ult[1], r[1]); } else { fusion.push(r.slice()); }
            });
            var frag = document.createDocumentFragment();
            var cursor = 0;
            fusion.forEach(function (r) {
                if (r[0] > cursor) { frag.appendChild(document.createTextNode(original.slice(cursor, r[0]))); }
                var m = document.createElement('mark');
                m.className = 'ayuda-marca';
                m.textContent = original.slice(r[0], r[1]);
                frag.appendChild(m);
                cursor = r[1];
            });
            if (cursor < original.length) { frag.appendChild(document.createTextNode(original.slice(cursor))); }
            nodo.parentNode.replaceChild(frag, nodo);
        });
    }

    function filtrar() {
        var q = normalizar(campo.value.trim());
        var palabras = q ? q.split(/\s+/) : [];
        var visibles = 0, coincidencias = 0;
        quitarMarcas();

        modelo.forEach(function (m) {
            var c = m.el;
            var ok = true, mostrarTodas = true, algunaSeccion = false;
            if (palabras.length) {
                mostrarTodas = palabras.every(function (p) { return m.cabecera.indexOf(p) !== -1; });
                m.secciones.forEach(function (s) {
                    var coincide = palabras.every(function (p) { return s.texto.indexOf(p) !== -1; });
                    s.coincide = coincide;
                    if (coincide) { algunaSeccion = true; }
                });
                ok = mostrarTodas || algunaSeccion;
            }
            c.classList.toggle('ayuda-oculto', !ok);
            m.secciones.forEach(function (s) {
                var ver = !palabras.length || mostrarTodas || s.coincide;
                s.el.classList.toggle('ayuda-oculto', !ver);
            });
            var li = enlaces[c.id] ? enlaces[c.id].parentNode : null;
            if (li) { li.classList.toggle('ayuda-oculto', !ok); }
            if (ok) {
                visibles++;
                if (palabras.length) { resaltar(c, palabras); }
            }
        });
        coincidencias = raiz.querySelectorAll('mark.ayuda-marca').length;

        partes.forEach(function (p, i) {
            var hay = p.querySelector('[data-capitulo]:not(.ayuda-oculto)') !== null;
            p.classList.toggle('ayuda-oculto', !hay);
            if (indicePartes[i]) { indicePartes[i].classList.toggle('ayuda-oculto', !hay); }
        });

        // Etiquetas de grupo del índice: solo se ven si queda algún capítulo visible debajo.
        Array.prototype.forEach.call(raiz.querySelectorAll('.ayuda-indice-grupo'), function (g) {
            var sig = g.nextElementSibling, hay = false;
            while (sig && !sig.hasAttribute('data-grupo')) {
                if (!sig.classList.contains('ayuda-oculto')) { hay = true; break; }
                sig = sig.nextElementSibling;
            }
            g.classList.toggle('ayuda-oculto', !hay);
        });

        if (!palabras.length) {
            mensaje.textContent = '';
        } else if (!visibles) {
            mensaje.textContent = 'Sin resultados';
        } else {
            mensaje.textContent = coincidencias + (coincidencias === 1 ? ' coincidencia' : ' coincidencias') +
                ' en ' + visibles + (visibles === 1 ? ' capítulo' : ' capítulos');
        }
        if (vacio) { vacio.hidden = visibles !== 0; }
        if (limpiar) { limpiar.hidden = !campo.value; }
        raiz.classList.toggle('ayuda-buscando', palabras.length > 0);

        // Guarda la búsqueda en la URL para poder compartirla (sin recargar ni llenar el historial).
        try {
            var url = new URL(window.location.href);
            if (palabras.length) { url.searchParams.set('q', campo.value.trim()); } else { url.searchParams.delete('q'); }
            window.history.replaceState(null, '', url.toString());
        } catch (e) { /* navegador antiguo: se omite */ }
    }

    var temporizador = null;
    if (campo) {
        campo.addEventListener('input', function () {
            if (limpiar) { limpiar.hidden = !campo.value; }
            clearTimeout(temporizador);
            temporizador = setTimeout(filtrar, 120);
        });
        campo.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && campo.value) { e.preventDefault(); campo.value = ''; filtrar(); }
            if (e.key === 'Enter') {
                e.preventDefault();
                var primera = raiz.querySelector('mark.ayuda-marca') || raiz.querySelector('[data-capitulo]:not(.ayuda-oculto)');
                if (primera && primera.scrollIntoView) { primera.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            }
        });
    }
    if (limpiar) {
        limpiar.addEventListener('click', function () { campo.value = ''; filtrar(); campo.focus(); });
    }
    // Atajo "/" para ir al buscador (si no se está escribiendo en otro campo).
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || !campo) { return; }
        var t = e.target;
        if (t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable)) { return; }
        e.preventDefault();
        campo.focus();
        campo.select();
    });
    // Búsqueda inicial desde ?q=...
    if (campo) {
        try {
            var inicial = new URL(window.location.href).searchParams.get('q');
            if (inicial) { campo.value = inicial; filtrar(); }
        } catch (e) { /* se omite */ }
    }

    // --- Temas frecuentes: un toque llena el buscador. Se ocultan los que no aparecen en esta guía. ---
    var temas = document.getElementById('ayuda-temas');
    if (temas && campo) {
        var textoCompleto = modelo.map(function (m) {
            return m.cabecera + ' ' + m.secciones.map(function (x) { return x.texto; }).join(' ');
        }).join(' ');
        var visiblesTemas = 0;
        Array.prototype.forEach.call(temas.querySelectorAll('[data-buscar]'), function (b) {
            var palabrasTema = normalizar(b.getAttribute('data-buscar')).split(/\s+/);
            var existe = palabrasTema.every(function (p) { return textoCompleto.indexOf(p) !== -1; });
            b.hidden = !existe;
            if (existe) { visiblesTemas++; }
            b.addEventListener('click', function () {
                campo.value = b.getAttribute('data-buscar');
                filtrar();
                campo.focus();
                var primera = raiz.querySelector('mark.ayuda-marca');
                if (primera && primera.scrollIntoView) { primera.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            });
        });
        temas.hidden = visiblesTemas === 0;
    }

    // --- Índice desplegable en móvil ---
    var indice = raiz.querySelector('.ayuda-indice');
    var toggle = raiz.querySelector('.ayuda-indice-toggle');
    if (indice && toggle) {
        toggle.addEventListener('click', function () {
            var abierto = indice.classList.toggle('abierto');
            toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        });
        indice.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('a[href^="#"]')) {
                indice.classList.remove('abierto');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // --- Resaltar el capítulo que se está leyendo ---
    if ('IntersectionObserver' in window) {
        var actual = null;
        var obs = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (en) {
                if (en.isIntersecting && enlaces[en.target.id]) {
                    if (actual) { actual.classList.remove('activa'); }
                    actual = enlaces[en.target.id];
                    actual.classList.add('activa');
                }
            });
        }, { rootMargin: '0px 0px -70% 0px' });
        capitulos.forEach(function (c) { obs.observe(c); });
    }
})();
