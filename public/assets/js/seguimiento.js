/* SERENA — seguimiento.js
   Página «Seguimiento» (docente/seguimiento.php): recarga automática de los selectores, selección de estudiante
   (foto, nota parcial, fecha), edición de la nota desde el panel, copiar nota y aviso de cambios sin guardar. */
(function () {
    'use strict';

    // ---- Selectores: al cambiar uno se recarga la tabla; los que dependen de él se reinician. ----
    var filtros = document.getElementById('seg-filtros');
    if (filtros) {
        [].forEach.call(filtros.querySelectorAll('select[data-auto]'), function (sel) {
            sel.addEventListener('change', function () {
                (sel.getAttribute('data-limpiar') || '').split(',').forEach(function (par) {
                    var partes = par.split(':');
                    var campo = partes[0] ? filtros.elements[partes[0]] : null;
                    if (campo) { campo.disabled = false; campo.value = partes[1] || ''; }
                });
                filtros.submit();
            });
        });
    }

    var cfgEl = document.getElementById('seg-config');
    var tabla = document.getElementById('seg-tabla');
    if (!cfgEl || !tabla) { return; }
    var cfg = JSON.parse(cfgEl.textContent);
    var filas = [].slice.call(tabla.querySelectorAll('tbody tr'));

    var panel = document.getElementById('seg-panel');
    var imgFoto = document.getElementById('seg-foto');
    var lblNombre = document.getElementById('seg-nombre');
    var lblFecha = document.getElementById('seg-fecha');
    var campoNota = document.getElementById('seg-nota');
    var campoObs = document.getElementById('seg-obs');
    var archivoFoto = document.getElementById('seg-foto-archivo');
    var btnSubir = document.getElementById('seg-foto-subir');
    var btnQuitar = document.getElementById('seg-foto-quitar');
    var btnConfirmar = document.getElementById('seg-confirmar');
    var actual = null;
    var sucio = false;

    function marcarSucio() {
        if (sucio) { return; }
        sucio = true;
        if (btnConfirmar) { btnConfirmar.classList.add('seg-pendiente'); }
    }

    function nivelDeValor(v) {
        for (var i = 0; i < cfg.niveles.length; i++) {
            if (v >= cfg.niveles[i].desde - 0.0001 && v <= cfg.niveles[i].hasta + 0.0001) { return cfg.niveles[i]; }
        }
        return null;
    }
    function nivelDeId(id) {
        for (var i = 0; i < cfg.niveles.length; i++) {
            if (String(cfg.niveles[i].id) === String(id)) { return cfg.niveles[i]; }
        }
        return null;
    }

    // Pinta las celdas «Escala valorativa» y «Nota» de una fila según lo que hay en su campo oculto.
    function pintarFila(tr) {
        var oculto = tr.querySelector('input[data-nota]');
        if (!oculto) { return; }
        var escala = tr.querySelector('[data-celda="escala"]');
        var nota = tr.querySelector('[data-celda="nota"]');
        var txt = oculto.value.trim().replace(',', '.');
        if (txt === '') { escala.textContent = ''; nota.textContent = ''; return; }
        if (cfg.tipo === 'descriptiva') {
            var n = nivelDeId(txt);
            escala.textContent = n ? n.nombre : '';
            nota.textContent = n ? n.abrev : '';
        } else {
            var v = parseFloat(txt);
            if (isNaN(v)) { escala.textContent = ''; nota.textContent = txt; return; }
            var niv = nivelDeValor(v);
            escala.textContent = niv ? niv.nombre : '';
            nota.textContent = v.toFixed(cfg.dec);
        }
    }

    // Valor que muestra el panel (en «Nota parcial») para una fila.
    function valorPanel(tr) {
        var oculto = tr.querySelector('input[data-nota]');
        if (oculto) { return oculto.value; }
        return tr.querySelector('[data-celda="nota"]').textContent.trim(); // período: solo lectura
    }

    function seleccionar(tr, desplazar) {
        filas.forEach(function (f) { f.classList.remove('seg-fila-sel'); });
        if (!tr) { return; }
        actual = tr;
        tr.classList.add('seg-fila-sel');
        var sid = tr.getAttribute('data-sid');

        imgFoto.src = tr.getAttribute('data-foto');
        lblNombre.textContent = tr.getAttribute('data-nombre');
        lblFecha.textContent = tr.getAttribute('data-fecha') || '—';
        campoNota.disabled = !cfg.editable;
        campoNota.value = valorPanel(tr);
        var obs = tr.querySelector('input[data-obs]');
        campoObs.disabled = false;
        campoObs.value = obs ? obs.value : '';

        [].forEach.call(document.querySelectorAll('.seg-estudiante-id'), function (i) { i.value = sid; });
        [].forEach.call(document.querySelectorAll('.seg-sel-actual'), function (i) { i.value = sid; });
        archivoFoto.disabled = false;
        btnSubir.disabled = !archivoFoto.value;
        btnQuitar.disabled = tr.getAttribute('data-tiene-foto') !== '1';

        if (desplazar && window.matchMedia('(max-width: 860px)').matches) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    filas.forEach(function (tr) {
        tr.addEventListener('click', function () { seleccionar(tr, true); });
        tr.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); seleccionar(tr, true); }
            else if (ev.key === 'ArrowDown' && tr.nextElementSibling) { ev.preventDefault(); tr.nextElementSibling.focus(); seleccionar(tr.nextElementSibling, false); }
            else if (ev.key === 'ArrowUp' && tr.previousElementSibling) { ev.preventDefault(); tr.previousElementSibling.focus(); seleccionar(tr.previousElementSibling, false); }
        });
    });

    // ---- Editar la nota del estudiante elegido (se escribe en el campo oculto de su fila). ----
    function alCambiarNota() {
        if (!actual || !cfg.editable) { return; }
        var oculto = actual.querySelector('input[data-nota]');
        if (!oculto) { return; }
        oculto.value = campoNota.value;
        if (cfg.tipo !== 'descriptiva' && campoNota.value !== '') {
            var v = parseFloat(String(campoNota.value).replace(',', '.'));
            campoNota.classList.toggle('seg-invalido', isNaN(v) || v < cfg.min || v > cfg.max);
        } else {
            campoNota.classList.remove('seg-invalido');
        }
        pintarFila(actual);
        marcarSucio();
    }
    if (campoNota) {
        campoNota.addEventListener('input', alCambiarNota);
        campoNota.addEventListener('change', alCambiarNota);
    }

    if (campoObs) {
        campoObs.addEventListener('input', function () {
            if (!actual) { return; }
            var obs = actual.querySelector('input[data-obs]');
            if (obs) { obs.value = campoObs.value; marcarSucio(); }
        });
    }

    // ---- Copiar nota a todos (solo rellena la tabla; se guarda con «Confirmar»). ----
    var btnCopiar = document.getElementById('seg-copiar');
    if (btnCopiar) {
        btnCopiar.addEventListener('click', function () {
            var campo = document.getElementById('seg-copia');
            var soloVacios = document.getElementById('seg-copia-vacios').checked;
            if (!cfg.editable || campo.value === '') { campo.focus(); return; }
            var v = parseFloat(String(campo.value).replace(',', '.'));
            if (cfg.tipo !== 'descriptiva' && (isNaN(v) || v < cfg.min || v > cfg.max)) {
                window.alert('La nota debe estar entre ' + cfg.min + ' y ' + cfg.max + '.');
                return;
            }
            var cambiadas = 0;
            filas.forEach(function (tr) {
                var oculto = tr.querySelector('input[data-nota]');
                if (!oculto || (soloVacios && oculto.value.trim() !== '')) { return; }
                oculto.value = cfg.tipo === 'descriptiva' ? campo.value : v.toFixed(cfg.dec);
                pintarFila(tr);
                cambiadas++;
            });
            if (cambiadas > 0) { marcarSucio(); }
            if (actual) { campoNota.value = valorPanel(actual); }
        });
    }

    // ---- Foto: el botón «Subir» se activa cuando hay archivo. ----
    if (archivoFoto) {
        archivoFoto.addEventListener('change', function () { btnSubir.disabled = !archivoFoto.value || !actual; });
    }

    // ---- Aviso de cambios sin guardar. ----
    var formNotas = document.getElementById('seg-form');
    if (formNotas) {
        formNotas.addEventListener('submit', function () { sucio = false; });
    }
    window.addEventListener('beforeunload', function (ev) {
        if (sucio) { ev.preventDefault(); ev.returnValue = ''; }
    });

    // ================================================================
    // Dictado de notas: «Juan Pérez 4.5» → busca al estudiante por nombre y pone la nota.
    // Usa el reconocimiento de voz del navegador (Chrome/Edge); también se puede escribir la frase.
    // ================================================================
    var vozBtn = document.getElementById('seg-voz-btn');
    var vozTexto = document.getElementById('seg-voz-texto');
    var vozAplicar = document.getElementById('seg-voz-aplicar');
    var vozAuto = document.getElementById('seg-voz-auto');
    var vozLog = document.getElementById('seg-voz-log');

    var PALABRAS = { cero: 0, uno: 1, una: 1, dos: 2, tres: 3, cuatro: 4, cinco: 5, seis: 6, siete: 7, ocho: 8, nueve: 9, diez: 10 };
    var NUMPAL = '(?:cero|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)';
    var RELLENO = { de: 1, del: 1, la: 1, las: 1, los: 1, y: 1, el: 1 };

    function norm(t) {
        return String(t).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/(\d)\s*,\s*(\d)/g, '$1.$2').replace(/[^a-z0-9.\s]/g, ' ').replace(/\s+/g, ' ').trim();
    }
    function aDigito(p) { return String(PALABRAS[p]); }

    // Convierte números dichos con palabras a dígitos, solo donde es seguro (decimal o al final de la frase).
    function numerosADigitos(t) {
        t = t.replace(new RegExp('\\b(' + NUMPAL + ')\\s+(?:punto|coma|con)\\s+(' + NUMPAL + '|\\d)\\b', 'g'), function (m, a, b) { return aDigito(a) + '.' + (PALABRAS[b] !== undefined ? aDigito(b) : b); });
        t = t.replace(new RegExp('\\b(' + NUMPAL + '|\\d+)\\s+y\\s+medio\\b', 'g'), function (m, a) { return (PALABRAS[a] !== undefined ? aDigito(a) : a) + '.5'; });
        t = t.replace(/(\d)\s+(?:punto|coma|con)\s+(\d)/g, '$1.$2');
        t = t.replace(new RegExp('\\b(' + NUMPAL + ')\\s*$'), function (m, a) { return aDigito(a); });
        t = t.replace(/(\d)\s+(\d)(?!\d)/g, '$1.$2');        // «4 5» → 4.5
        t = t.replace(/\b(\d)(\d)\b/g, function (m, a, b) {  // «45» → 4.5 cuando se pasa de la escala
            return (parseFloat(m) > cfg.max && cfg.max <= 10) ? a + '.' + b : m;
        });
        return t.replace(/\.+(\s|$)/g, '$1').trim();
    }

    // Devuelve [{nombre, valor}] o, en escalas descriptivas, [{nombre, nivel}].
    function interpretar(texto) {
        var t = norm(texto);
        var res = [];
        var re, m;
        if (cfg.tipo === 'descriptiva') {
            var alt = cfg.niveles.map(function (n) { return norm(n.nombre); }).filter(Boolean).join('|');
            if (!alt) { return res; }
            re = new RegExp('([a-z][a-z\\s]*?)\\s+(' + alt + ')(?=\\s|$)', 'g');
            while ((m = re.exec(t))) {
                var niv = null;
                cfg.niveles.forEach(function (n) { if (norm(n.nombre) === m[2]) { niv = n; } });
                res.push({ nombre: m[1].trim(), nivel: niv });
            }
            return res;
        }
        t = numerosADigitos(t);
        re = /([a-z][a-z\s]*?)\s+(\d+(?:\.\d+)?)(?=\s|$)/g;
        while ((m = re.exec(t))) { res.push({ nombre: m[1].trim(), valor: parseFloat(m[2]) }); }
        return res;
    }

    function distancia1(a, b) { // ¿se diferencian en a lo sumo una letra?
        if (Math.abs(a.length - b.length) > 1) { return false; }
        var i = 0, j = 0, dif = 0;
        while (i < a.length && j < b.length) {
            if (a[i] === b[j]) { i++; j++; continue; }
            if (++dif > 1) { return false; }
            if (a.length > b.length) { i++; } else if (b.length > a.length) { j++; } else { i++; j++; }
        }
        return dif + (a.length - i) + (b.length - j) <= 1;
    }

    // Busca al estudiante cuyo nombre contenga todas las palabras dichas. {fila} | {ambiguos:[...]} | {}
    function buscarEstudiante(nombreDicho) {
        var dichos = nombreDicho.split(' ').filter(function (x) { return x && !RELLENO[x]; });
        if (!dichos.length) { return {}; }
        var candidatos = [];
        filas.forEach(function (tr) {
            var toks = norm(tr.getAttribute('data-nombre')).split(' ').filter(Boolean);
            var exactos = 0, todos = true;
            dichos.forEach(function (d) {
                var ok = false, exacto = false;
                toks.forEach(function (tk) {
                    if (tk === d) { ok = true; exacto = true; }
                    else if (!ok && ((d.length >= 3 && tk.indexOf(d) === 0) || (d.length >= 5 && tk.length >= 5 && distancia1(d, tk)))) { ok = true; }
                });
                if (!ok) { todos = false; }
                if (exacto) { exactos++; }
            });
            if (todos) { candidatos.push({ tr: tr, exactos: exactos }); }
        });
        if (!candidatos.length) { return {}; }
        candidatos.sort(function (a, b) { return b.exactos - a.exactos; });
        if (candidatos.length > 1 && candidatos[0].exactos === candidatos[1].exactos) {
            return { ambiguos: candidatos.filter(function (c) { return c.exactos === candidatos[0].exactos; }).slice(0, 4).map(function (c) { return c.tr.getAttribute('data-nombre'); }) };
        }
        return { fila: candidatos[0].tr };
    }

    function logVoz(texto, clase) {
        if (!vozLog) { return null; }
        var li = document.createElement('li');
        li.className = clase || '';
        li.textContent = texto;
        vozLog.insertBefore(li, vozLog.firstChild);
        while (vozLog.children.length > 8) { vozLog.removeChild(vozLog.lastChild); }
        return li;
    }
    function nombreCorto(tr) { return tr.getAttribute('data-nombre'); }

    function guardarUna(tr, li, textoLog) {
        var fd = new FormData();
        ['csrf_token', 'accion', 'anio', 'periodo', 'grupo', 'asig', 'eval'].forEach(function (n) {
            var el = formNotas.elements[n]; if (el) { fd.append(n, el.value); }
        });
        fd.append('ajax', '1');
        var oculto = tr.querySelector('input[data-nota]');
        fd.append(oculto.name, oculto.value);
        li.textContent = textoLog + ' · guardando…';
        fetch(formNotas.action, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.ok && !(j.errores && j.errores.length)) {
                    li.textContent = textoLog + ' · guardada ✓';
                    li.className = 'seg-ok';
                    tr.querySelector('[data-celda="nota"]').classList.add('seg-guardada');
                } else {
                    li.textContent = textoLog + ' · NO se guardó: ' + ((j.errores && j.errores[0]) || j.mensaje || 'error');
                    li.className = 'seg-mal';
                }
            })
            .catch(function () { li.textContent = textoLog + ' · sin conexión: pulsa «Confirmar» para guardar.'; li.className = 'seg-mal'; marcarSucio(); });
    }

    function aplicarFrase(frase) {
        if (!cfg.editable) { return; }
        var items = interpretar(frase);
        if (!items.length) {
            logVoz('No entendí «' + frase + '». Di el nombre y la nota, p. ej. «Juan Pérez 4.5».', 'seg-aviso');
            return;
        }
        items.forEach(function (it) {
            var b = buscarEstudiante(it.nombre);
            if (b.ambiguos) { logVoz('«' + it.nombre + '» coincide con varios: ' + b.ambiguos.join(', ') + '. Di más apellidos.', 'seg-aviso'); return; }
            if (!b.fila) { logVoz('No encontré a «' + it.nombre + '» en este grupo.', 'seg-mal'); return; }
            var tr = b.fila, oculto = tr.querySelector('input[data-nota]'), nuevo, mostrar;
            if (cfg.tipo === 'descriptiva') {
                if (!it.nivel) { return; }
                nuevo = String(it.nivel.id); mostrar = it.nivel.nombre;
            } else {
                if (isNaN(it.valor) || it.valor < cfg.min || it.valor > cfg.max) {
                    logVoz(nombreCorto(tr) + ': ' + it.valor + ' está fuera de ' + cfg.min + ' a ' + cfg.max + '. No se aplicó.', 'seg-mal');
                    return;
                }
                nuevo = it.valor.toFixed(cfg.dec); mostrar = nuevo;
            }
            oculto.value = nuevo;
            pintarFila(tr);
            tr.querySelector('[data-celda="nota"]').classList.remove('seg-guardada');
            seleccionar(tr, false);
            tr.classList.remove('seg-fila-nueva'); void tr.offsetWidth; tr.classList.add('seg-fila-nueva');
            if (tr.scrollIntoView) { tr.scrollIntoView({ block: 'nearest' }); }
            var texto = nombreCorto(tr) + ' → ' + mostrar;
            var li = logVoz(texto, 'seg-aviso');
            if (vozAuto && vozAuto.checked) { guardarUna(tr, li, texto); }
            else { li.textContent = texto + ' · pendiente: pulsa «Confirmar»'; marcarSucio(); }
        });
    }

    if (vozAplicar && vozTexto) {
        var desdeTexto = function () { var f = vozTexto.value.trim(); if (f) { aplicarFrase(f); vozTexto.value = ''; } };
        vozAplicar.addEventListener('click', desdeTexto);
        vozTexto.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); desdeTexto(); } });
    }

    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var reconocedor = null, escuchando = false;
    function pararVoz() {
        escuchando = false;
        if (reconocedor) { try { reconocedor.stop(); } catch (e) { /* ya detenido */ } }
        if (vozBtn) { vozBtn.classList.remove('seg-escuchando'); vozBtn.textContent = '🎤 Dictar notas'; }
    }
    function iniciarVoz() {
        reconocedor = new SR();
        reconocedor.lang = 'es-CO';
        reconocedor.continuous = true;
        reconocedor.interimResults = false;
        reconocedor.onresult = function (ev) {
            for (var i = ev.resultIndex; i < ev.results.length; i++) {
                if (ev.results[i].isFinal) { aplicarFrase(ev.results[i][0].transcript); }
            }
        };
        reconocedor.onerror = function (ev) {
            if (ev.error === 'no-speech' || ev.error === 'aborted') { return; }
            var m = { 'not-allowed': 'El navegador no tiene permiso para usar el micrófono.', 'service-not-allowed': 'El navegador no permite el dictado en este momento.',
                      'audio-capture': 'No se encontró un micrófono.', 'network': 'No hay conexión con el servicio de voz.' }[ev.error] || 'No se pudo dictar.';
            logVoz(m, 'seg-mal');
            pararVoz();
        };
        reconocedor.onend = function () { if (escuchando) { try { reconocedor.start(); } catch (e) { pararVoz(); } } }; // sigue escuchando tras silencios
        try {
            reconocedor.start();
            escuchando = true;
            vozBtn.classList.add('seg-escuchando');
            vozBtn.textContent = '⏹ Dejar de escuchar';
            logVoz('Escuchando… di, por ejemplo, «Juan Pérez 4.5».', 'seg-aviso');
        } catch (e) { logVoz('No se pudo iniciar el micrófono.', 'seg-mal'); }
    }
    if (vozBtn) {
        if (!SR) {
            vozBtn.disabled = true;
            vozBtn.title = 'Este navegador no permite dictar. Usa Chrome o Edge, o escribe la frase en el recuadro.';
        } else {
            vozBtn.addEventListener('click', function () {
                if (escuchando) { pararVoz(); return; }
                var ok = false;
                try { ok = localStorage.getItem('serena_voz') === 'si'; } catch (e) { /* sin almacenamiento */ }
                if (!ok) {
                    if (!window.confirm('El dictado por voz usa el reconocimiento de voz de tu navegador. En Chrome y Edge el audio se envía a Google o Microsoft para convertirlo en texto. Serena no graba ni guarda tu voz.\n\n¿Activar el dictado?')) { return; }
                    try { localStorage.setItem('serena_voz', 'si'); } catch (e) { /* sin almacenamiento */ }
                }
                iniciarVoz();
            });
        }
    }
    window.addEventListener('pagehide', pararVoz);

    // ---- Selección inicial: el estudiante pedido (tras subir una foto, p. ej.) o el primero. ----
    var inicial = null;
    filas.forEach(function (tr) { if (cfg.selInicial && String(cfg.selInicial) === tr.getAttribute('data-sid')) { inicial = tr; } });
    seleccionar(inicial || filas[0] || null, false);
})();
