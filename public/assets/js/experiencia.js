/* ============================================================
   SERENA — experiencia.js
   Lo dinámico de los paneles (ver experiencia.css):
     - cabecera con icono y color del módulo,
     - onda al tocar botones,
     - cifras que cuentan hasta su valor,
     - sonidos al elegir, deslizar, abrir, navegar y escribir,
     - confeti al cumplir hábitos y metas.
   Todo respeta «reducir movimiento», el alto contraste y el botón de silencio.
   En «Ayuda urgente» no hay celebraciones.
   ============================================================ */
(function () {
    'use strict';

    var raiz = document.documentElement;
    var cuerpo = document.body;
    if (!cuerpo || !cuerpo.classList.contains('panel-body')) { return; }

    var modulo = cuerpo.getAttribute('data-modulo') || '';
    var reducir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var contrasteAlto = raiz.getAttribute('data-contraste') === 'alto';
    var animar = !reducir && !contrasteAlto;
    var momentoSensible = modulo === 'ayuda_urgente';

    function sonar(nombre, arg) {
        if (window.Sonidos) { window.Sonidos.reproducir(nombre, arg); }
    }

    // ---------------------------------------------------------
    // 1. Cabecera del módulo: icono + color, alrededor del primer h1
    // ---------------------------------------------------------
    var ICONOS = {
        emociones: 'icono_emociones', sueno: 'icono_sueno', diario: 'icono_diario', habitos: 'icono_habitos',
        metas: 'icono_metas', recordatorios: 'icono_recordatorios', citas: 'icono_citas', mensajes: 'icono_mensajes',
        respiracion: 'icono_respiracion', estadisticas: 'icono_estadisticas', calendario: 'icono_calendario',
        encuestas: 'icono_encuestas', recursos_apoyo: 'icono_recursos', ayuda_urgente: 'icono_urgente',
        notificaciones: 'icono_notificaciones', perfil: 'icono_perfil', agenda: 'icono_agenda', alertas: 'icono_alertas',
        seguimientos: 'icono_seguimiento', seguimiento: 'icono_seguimiento', asistencia: 'icono_asistencia',
        planeador: 'icono_planeador', pulso: 'icono_pulso', grupo: 'icono_grupo', estudiantes: 'icono_estudiantes',
        reportes: 'icono_estadisticas', horario: 'icono_horario', boletin: 'icono_documento', informes: 'icono_documento',
        // Administración y académico
        usuarios: 'icono_usuarios', usuario_crear: 'icono_usuarios', usuario_editar: 'icono_usuarios', usuarios_importar: 'icono_importar',
        grupos: 'icono_grupo', asignar_grupos_personal: 'icono_asignar', promocion: 'icono_promocion', aprobaciones: 'icono_aprobaciones',
        importar_estudiantes: 'icono_importar', resetear_password: 'icono_password', ver_como: 'icono_ver_como',
        anios_lectivos: 'icono_academico', plan_academico: 'icono_academico', evaluacion_config: 'icono_academico',
        refuerzos: 'icono_academico', institucion: 'icono_configuracion', configuracion: 'icono_configuracion',
        salud: 'icono_salud', auditoria: 'icono_auditoria', lugares_apoyo: 'icono_recursos',
        matriculas: 'icono_usuarios', inscripciones: 'icono_importar', boletines: 'icono_documento',
        certificados: 'icono_certificado', certificados_plantillas: 'icono_certificado',
        simat: 'icono_estadisticas', simpade: 'icono_seguimiento', horarios_admin: 'icono_horario',
        asistencia_reporte: 'icono_estadisticas', restaurante: 'icono_recursos', cartera: 'icono_estadisticas',
        caracterizacion: 'icono_usuarios', casos: 'icono_alertas', seguridad: 'icono_password', sesiones: 'icono_sesiones',
        preferencias: 'icono_configuracion', mensajes_personal: 'icono_mensajes'
    };
    var contenido = document.querySelector('.contenido-panel');
    var h1 = contenido ? contenido.querySelector(':scope > h1') : null;
    if (h1 && ICONOS[modulo]) {
        var cab = document.createElement('div');
        cab.className = 'cabecera-modulo';
        var marco = document.createElement('span');
        marco.className = 'cabecera-modulo-icono';
        marco.setAttribute('aria-hidden', 'true');
        var img = document.createElement('img');
        img.alt = '';
        img.src = (window.SERENA_BASE_URL || '') + '/assets/img/recursos/' + ICONOS[modulo] + '.png';
        img.onerror = function () { marco.remove(); };
        marco.appendChild(img);
        h1.parentNode.insertBefore(cab, h1);
        cab.appendChild(marco);
        cab.appendChild(h1);
    }

    // ---------------------------------------------------------
    // 2. Onda al tocar (botones y accesos)
    // ---------------------------------------------------------
    if (animar) {
        document.addEventListener('pointerdown', function (e) {
            var el = e.target.closest && e.target.closest('button, .boton-acceso, .boton-secundario, .accesos-rapidos a');
            if (!el || el.disabled || el.classList.contains('boton-tema')) { return; }
            var r = el.getBoundingClientRect();
            var tam = Math.max(r.width, r.height);
            var onda = document.createElement('span');
            onda.className = 'onda-toque';
            onda.style.width = onda.style.height = tam + 'px';
            onda.style.left = (e.clientX - r.left - tam / 2) + 'px';
            onda.style.top = (e.clientY - r.top - tam / 2) + 'px';
            el.appendChild(onda);
            setTimeout(function () { onda.remove(); }, 650);
        }, { passive: true });
    }

    // ---------------------------------------------------------
    // 3. Cifras que cuentan hasta su valor («2/3», «8.1», «4»)
    // ---------------------------------------------------------
    if (animar) {
        Array.prototype.forEach.call(document.querySelectorAll('.tarjeta-numero, [data-contar]'), function (el) {
            if (el.children.length) { return; }
            var original = el.textContent;
            var numeros = original.match(/\d+(?:[.,]\d+)?/g);
            if (!numeros) { return; }
            var t0 = null;
            var DUR = 900;
            function paso(t) {
                if (t0 === null) { t0 = t; }
                var p = Math.min(1, (t - t0) / DUR);
                var suave = 1 - Math.pow(1 - p, 3);
                var i = 0;
                el.textContent = original.replace(/\d+(?:[.,]\d+)?/g, function (n) {
                    var dec = (n.split(/[.,]/)[1] || '').length;
                    var sep = n.indexOf(',') !== -1 ? ',' : '.';
                    var v = parseFloat(numeros[i++].replace(',', '.')) * suave;
                    return dec ? v.toFixed(dec).replace('.', sep) : String(Math.round(v));
                });
                if (p < 1) { requestAnimationFrame(paso); } else { el.textContent = original; }
            }
            requestAnimationFrame(paso);
        });
    }

    // ---------------------------------------------------------
    // 4. Sonidos al usar la página
    // ---------------------------------------------------------
    // Elegir: radios (cada opción de un grupo, una nota) y listas desplegables.
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.matches) { return; }
        if (t.matches('input[type="radio"]')) {
            var grupo = document.querySelectorAll('input[type="radio"][name="' + (window.CSS && CSS.escape ? CSS.escape(t.name) : t.name) + '"]');
            sonar('elegir', Array.prototype.indexOf.call(grupo, t));
        } else if (t.matches('input[type="checkbox"]') && !t.closest('.check-habito, .boton-check-subtarea')) {
            sonar(t.checked ? 'marcar' : 'clic');
        } else if (t.matches('select')) {
            sonar('elegir', t.selectedIndex);
        }
    });

    // Deslizar: un tic por valor y la cifra que «salta».
    var ultimoTic = 0;
    document.addEventListener('input', function (e) {
        var t = e.target;
        if (!t || !t.matches || !t.matches('input[type="range"]')) { return; }
        var ahora = Date.now();
        if (ahora - ultimoTic > 45) {
            ultimoTic = ahora;
            var min = parseFloat(t.min || 0), max = parseFloat(t.max || 100);
            sonar('tic', (parseFloat(t.value) - min) / ((max - min) || 1));
        }
        var etiqueta = document.querySelector('.valor-slider');
        if (etiqueta && animar) {
            etiqueta.classList.remove('cambio');
            void etiqueta.offsetWidth;
            etiqueta.classList.add('cambio');
            setTimeout(function () { etiqueta.classList.remove('cambio'); }, 200);
        }
    });

    // Abrir y cerrar bloques desplegables.
    document.addEventListener('toggle', function (e) {
        if (e.target && e.target.tagName === 'DETAILS') { sonar(e.target.open ? 'abrir' : 'cerrar'); }
    }, true);

    // Escribir: una nota muy suave la primera vez que se entra a cada campo de texto.
    document.addEventListener('focusin', function (e) {
        var t = e.target;
        if (!t || !t.matches || !t.matches('textarea, input[type="text"], input[type="search"], input:not([type])')) { return; }
        if (t.dataset.expSonado) { return; }
        t.dataset.expSonado = '1';
        sonar('enfoque');
    });

    // Navegar: enlaces del menú y accesos rápidos. Se espera lo justo para que el sonido alcance
    // a sonar antes de cambiar de página (mismo truco que sonidos.js usa con los botones-enlace).
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('.menubar-enlace[href], .menubar a[href], .accesos-rapidos a[href]');
        if (!a || a.target === '_blank' || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) { return; }
        var href = a.getAttribute('href') || '';
        if (href.charAt(0) === '#' || /^javascript:/i.test(href)) { return; }
        if (window.Sonidos && window.Sonidos.estaSilenciado()) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        sonar('navegar');
        setTimeout(function () { window.location.href = a.href; }, 120);
    }, true);

    // ---------------------------------------------------------
    // 4b. Gráficos de Chart.js: alto fijo (los circulares ya no ocupan media pantalla),
    //     color del rol o del módulo y animación de entrada al recrearlos.
    // ---------------------------------------------------------
    function ajustarGraficos() {
        if (!window.Chart || !window.Chart.getChart) { return; }
        var estilo = getComputedStyle(cuerpo);
        var color = estilo.getPropertyValue('--mod').trim() || '#E8B923';
        var fuente = (getComputedStyle(raiz).getPropertyValue('--fuente-base') || '').trim() || 'Poppins, sans-serif';
        Array.prototype.forEach.call(document.querySelectorAll('.contenido-panel canvas'), function (canvas) {
            var ch = window.Chart.getChart(canvas);
            if (!ch || canvas.parentNode.classList.contains('grafico-caja')) { return; }
            var bruto = ch.config._config || {};
            var tipo = ch.config.type;
            var circular = ['doughnut', 'pie', 'polarArea'].indexOf(tipo) !== -1;
            var datos = ch.config.data;
            var opciones = bruto.options || {};
            var complementos = bruto.plugins || [];
            ch.destroy();

            var caja = document.createElement('div');
            caja.className = 'grafico-caja' + (circular ? ' grafico-caja--circular' : '');
            canvas.parentNode.insertBefore(caja, canvas);
            caja.appendChild(canvas);
            canvas.removeAttribute('height');
            canvas.removeAttribute('style');

            // Una sola serie de línea o barras: se pinta con el color del rol/módulo.
            if (!circular && datos.datasets && datos.datasets.length === 1) {
                var ds = datos.datasets[0];
                ds.borderColor = color;
                if (tipo === 'line') {
                    var ctx = canvas.getContext('2d');
                    var degradado = ctx.createLinearGradient(0, 0, 0, 300);
                    degradado.addColorStop(0, color + '55');
                    degradado.addColorStop(1, color + '05');
                    ds.backgroundColor = degradado;
                    ds.pointBackgroundColor = color;
                    ds.pointRadius = ds.pointRadius === undefined ? 3 : ds.pointRadius;
                    ds.pointHoverRadius = 6;
                } else if (tipo === 'bar') {
                    ds.backgroundColor = color + 'CC';
                    ds.hoverBackgroundColor = color;
                    ds.borderRadius = 8;
                }
            }
            opciones.maintainAspectRatio = false;
            opciones.responsive = true;
            opciones.animation = animar ? Object.assign({ duration: 1100, easing: 'easeOutQuart' }, opciones.animation || {}) : false;
            if (circular) {
                opciones.cutout = opciones.cutout || '62%';
                // Separadores del color de la tarjeta (en modo oscuro ya no quedan líneas blancas).
                var superficie = getComputedStyle(raiz).getPropertyValue('--color-superficie').trim() || '#fff';
                (datos.datasets || []).forEach(function (d) { d.borderColor = superficie; d.borderWidth = 3; d.hoverOffset = 8; });
            }
            opciones.plugins = opciones.plugins || {};
            opciones.plugins.tooltip = Object.assign({ padding: 10, cornerRadius: 10, titleFont: { family: fuente }, bodyFont: { family: fuente } }, opciones.plugins.tooltip || {});
            new window.Chart(canvas, { type: tipo, data: datos, options: opciones, plugins: complementos });
        });
    }
    if (document.readyState === 'complete') { setTimeout(ajustarGraficos, 0); } else { window.addEventListener('load', function () { setTimeout(ajustarGraficos, 0); }); }

    // ---------------------------------------------------------
    // 4c. Iniciales de color junto al nombre en las tablas de personas
    // ---------------------------------------------------------
    var TONOS = ['#7B5BB5', '#3E9E68', '#D98E22', '#E0663F', '#1F97A3', '#B04A6E', '#4A7BD0', '#2FA39A'];
    Array.prototype.forEach.call(document.querySelectorAll('.contenido-panel .tabla-datos'), function (tabla) {
        var primera = tabla.querySelector('thead th');
        if (!primera || !/^(nombre|estudiante|persona|usuario)/i.test(primera.textContent.trim())) { return; }
        Array.prototype.forEach.call(tabla.querySelectorAll('tbody tr'), function (tr) {
            var td = tr.querySelector('td');
            if (!td || td.querySelector('.avatar-iniciales, img')) { return; }
            var nombre = td.textContent.trim().split('\n')[0].trim();
            var partes = nombre.split(/\s+/).filter(function (p) { return /^[A-Za-zÁÉÍÓÚÑáéíóúñ]/.test(p); });
            if (!partes.length) { return; }
            var iniciales = (partes[0][0] + (partes[1] ? partes[1][0] : '')).toUpperCase();
            var h = 0;
            for (var i = 0; i < nombre.length; i++) { h = (h * 31 + nombre.charCodeAt(i)) >>> 0; }
            var av = document.createElement('span');
            av.className = 'avatar-iniciales';
            av.setAttribute('aria-hidden', 'true');
            av.style.setProperty('--tono-avatar', TONOS[h % TONOS.length]);
            av.textContent = iniciales;
            td.insertBefore(av, td.firstChild);
        });
    });

    // ---------------------------------------------------------
    // 5. Celebraciones: confeti con los colores de Serena y del módulo
    // ---------------------------------------------------------
    function confeti(cantidad, origen) {
        if (!animar || momentoSensible) { return; }
        var capa = document.createElement('div');
        capa.className = 'capa-confeti';
        capa.setAttribute('aria-hidden', 'true');
        var colorModulo = getComputedStyle(cuerpo).getPropertyValue('--mod').trim() || '#E8B923';
        var colores = ['#F9D976', '#E8B923', colorModulo, '#F08A24', '#2FA39A', '#D45D8C'];
        var x0 = origen ? origen.x : window.innerWidth / 2;
        var y0 = origen ? origen.y : window.innerHeight * 0.25;
        for (var i = 0; i < cantidad; i++) {
            var c = document.createElement('i');
            c.className = 'confeti';
            var angulo = Math.random() * Math.PI * 2;
            var fuerza = 80 + Math.random() * 220;
            c.style.left = x0 + 'px';
            c.style.top = y0 + 'px';
            c.style.background = colores[i % colores.length];
            c.style.setProperty('--dx', Math.cos(angulo) * fuerza + 'px');
            c.style.setProperty('--dy', (Math.sin(angulo) * fuerza * 0.6 + 260 + Math.random() * 260) + 'px');
            c.style.setProperty('--giro', (Math.random() * 900 - 450) + 'deg');
            c.style.setProperty('--dur', (1.2 + Math.random() * 0.9) + 's');
            if (i % 3 === 0) { c.style.borderRadius = '50%'; c.style.width = c.style.height = '8px'; }
            capa.appendChild(c);
        }
        document.body.appendChild(capa);
        setTimeout(function () { capa.remove(); }, 2400);
    }
    window.SerenaConfeti = confeti;

    var exito = document.querySelector('.mensaje-exito');
    if (exito) {
        var texto = exito.textContent || '';
        if (texto.indexOf('🎉') !== -1) {
            setTimeout(function () { confeti(90); }, 250);
        } else if (texto.indexOf('✨') !== -1) {
            var r = exito.getBoundingClientRect();
            setTimeout(function () { confeti(36, { x: r.left + r.width / 2, y: r.top + 10 }); }, 200);
        }
    }
})();
