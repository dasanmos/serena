// SERENA — base.js
// Menú de navegación (clic en cualquier dispositivo, hover extra en desktop vía CSS)
// y toggle de modo oscuro.

// --- Ex-PWA: Serena ya no usa Service Worker (InfinityFree no lo soporta bien
// en hosting compartido). Este bloque solo desregistra cualquier Service Worker
// que haya quedado activo de una visita anterior a esta misma app y borra su
// caché — así nadie se queda atascado viendo una versión vieja cacheada. No
// registra nada nuevo. ---
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then((registros) => {
        registros.forEach((registro) => registro.unregister());
    });
}
if ('caches' in window) {
    caches.keys().then((nombres) => {
        nombres.filter((n) => n.startsWith('serena-')).forEach((n) => caches.delete(n));
    });
}

// Al cerrar sesión, se borra el caché del service worker antes de salir — en un equipo
// compartido del colegio, así la siguiente persona no ve por un instante contenido
// cacheado de la cuenta anterior mientras carga su propia sesión.
document.addEventListener('DOMContentLoaded', () => {
    const enlaceCerrarSesion = document.getElementById('enlace-cerrar-sesion');
    if (enlaceCerrarSesion && 'caches' in window) {
        enlaceCerrarSesion.addEventListener('click', (evento) => {
            evento.preventDefault();
            const destino = enlaceCerrarSesion.href;
            caches.keys()
                .then((nombres) => Promise.all(nombres.map((n) => caches.delete(n))))
                .finally(() => { window.location.href = destino; });
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    // La barra de menú horizontal vive en menu_barra.js.

    // --- Botones «Descargar PDF» y «PDF con QR» en las páginas de informes (las habilita el servidor con SERENA_PDF) ---
    if (window.SERENA_PDF) {
        const enlacePdf = (texto, modo, ayuda) => {
            const a = document.createElement('a');
            const u = new URL(window.location.href);
            u.searchParams.set('pdf', modo);
            a.href = u.toString();
            a.className = 'boton-secundario boton-pdf';
            a.textContent = texto;
            a.title = ayuda;
            a.setAttribute('download', '');
            return a;
        };
        const pdf = enlacePdf('Descargar PDF', '1', 'PDF con el formato institucional');
        const qr = enlacePdf('PDF con QR', 'qr', 'PDF con código QR para verificar su autenticidad');
        const impresion = document.querySelector('button[onclick*="print"]');
        if (impresion) {
            impresion.after(pdf, qr);
        } else {
            const barra = document.createElement('div');
            barra.className = 'barra-pdf';
            barra.append(pdf, qr);
            const titulo = document.querySelector('.contenido-panel h1');
            const principal = document.querySelector('.contenido-panel');
            if (titulo) { titulo.after(barra); } else if (principal) { principal.prepend(barra); }
        }
    }


    // --- Panel desplegable de la barrita dorada superior: aparece al pasar el
    // mouse o hacer clic, y se cierra solo tras unos segundos sin interacción
    // (igual que el resto de la app, esto se recalcula en cada carga de página,
    // así que nunca queda "atascado" abierto entre un módulo y otro). ---
    const botonAcento = document.getElementById('boton-acento-superior');
    const panelAcento = document.getElementById('panel-acento-superior');
    const fondoAcento = document.getElementById('panel-acento-fondo');

    if (botonAcento && panelAcento) {
        let temporizadorAcento = null;
        const SEGUNDOS_AUTOCIERRE = 4000;

        const mostrarAcento = () => {
            clearTimeout(temporizadorAcento);
            panelAcento.classList.add('visible');
            if (fondoAcento) fondoAcento.classList.add('visible');
            botonAcento.setAttribute('aria-expanded', 'true');
        };
        const ocultarAcento = () => {
            panelAcento.classList.remove('visible');
            if (fondoAcento) fondoAcento.classList.remove('visible');
            botonAcento.setAttribute('aria-expanded', 'false');
        };
        const programarCierreAcento = () => {
            clearTimeout(temporizadorAcento);
            temporizadorAcento = setTimeout(ocultarAcento, SEGUNDOS_AUTOCIERRE);
        };

        botonAcento.addEventListener('click', (e) => {
            e.stopPropagation();
            panelAcento.classList.contains('visible') ? ocultarAcento() : mostrarAcento();
        });
        botonAcento.addEventListener('mouseenter', mostrarAcento);
        botonAcento.addEventListener('mouseleave', programarCierreAcento);
        panelAcento.addEventListener('mouseenter', () => clearTimeout(temporizadorAcento));
        panelAcento.addEventListener('mouseleave', programarCierreAcento);
        panelAcento.addEventListener('focusin', mostrarAcento);
        panelAcento.addEventListener('focusout', programarCierreAcento);
        if (fondoAcento) fondoAcento.addEventListener('click', ocultarAcento);

        document.addEventListener('click', (e) => {
            if (!panelAcento.contains(e.target) && e.target !== botonAcento) ocultarAcento();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') ocultarAcento();
        });

        // Se abre solo (una vez) justo después de iniciar sesión, de usar
        // "Ver como usuario", o de volver a la cuenta de administrador —
        // window.SERENA_ABRIR_PANEL_INICIO lo pone encabezado.php y se
        // consume server-side, así que no vuelve a abrirse solo después.
        //
        // El doble requestAnimationFrame es a propósito: si se agrega la clase
        // "visible" en el mismo instante en que el script corre, el navegador
        // puede pintar el estado abierto de una sola vez sin animar la
        // transición (se ve como que "aparece" en vez de "bajar"). Esperando
        // dos frames se garantiza que ya pintó el estado cerrado inicial antes
        // de pedirle que anime hacia el abierto.
        if (window.SERENA_ABRIR_PANEL_INICIO) {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    mostrarAcento();
                    programarCierreAcento();
                });
            });
        }
    }

    // --- Modo oscuro ---
    const CLAVE_TEMA = 'serena_tema';
    const botonTema = document.getElementById('boton-tema');
    if (botonTema) {
        const actualizarBotonTema = () => {
            const oscuro = document.documentElement.getAttribute('data-tema') === 'oscuro';
            botonTema.textContent = oscuro ? '☀️' : '🌙';
            botonTema.setAttribute('aria-pressed', oscuro ? 'true' : 'false');
            botonTema.title = oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
        };
        actualizarBotonTema();
        botonTema.addEventListener('click', () => {
            const oscuroActual = document.documentElement.getAttribute('data-tema') === 'oscuro';
            if (oscuroActual) {
                document.documentElement.removeAttribute('data-tema');
                localStorage.setItem(CLAVE_TEMA, 'claro');
            } else {
                document.documentElement.setAttribute('data-tema', 'oscuro');
                localStorage.setItem(CLAVE_TEMA, 'oscuro');
            }
            actualizarBotonTema();
            if (window.Sonidos) window.Sonidos.reproducir('clic');
        });
    }

    // --- Forzar animaciones aunque el sistema operativo pida "reducir movimiento" ---
    // Pensado para equipos donde la persona no tiene permisos para cambiar esa
    // configuración (computadores del colegio administrados por IT, por ejemplo).
    // Por defecto Serena SIEMPRE respeta la preferencia del sistema — este botón
    // es una elección explícita de la propia persona dentro de la app, no un
    // cambio silencioso del comportamiento por defecto.
    const CLAVE_FORZAR_ANIMACIONES = 'serena_forzar_animaciones';
    const botonAnimacion = document.getElementById('boton-animacion');
    if (botonAnimacion && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        botonAnimacion.classList.remove('boton-animacion-oculto');
        const actualizarBotonAnimacion = () => {
            const forzado = localStorage.getItem(CLAVE_FORZAR_ANIMACIONES) === '1';
            botonAnimacion.textContent = forzado ? '✨' : '🚫✨';
            botonAnimacion.setAttribute('aria-pressed', forzado ? 'true' : 'false');
            botonAnimacion.title = forzado
                ? 'Animaciones forzadas activas (tu sistema pide reducir movimiento) — clic para volver a respetarlo'
                : 'Tu sistema pide reducir movimiento — clic para forzar animaciones de todos modos';
        };
        actualizarBotonAnimacion();
        botonAnimacion.addEventListener('click', () => {
            const forzadoActual = localStorage.getItem(CLAVE_FORZAR_ANIMACIONES) === '1';
            localStorage.setItem(CLAVE_FORZAR_ANIMACIONES, forzadoActual ? '0' : '1');
            actualizarBotonAnimacion();
            if (window.Sonidos) window.Sonidos.reproducir('clic');
        });
    }
});
// --- Mostrar/ocultar contraseña (el "ojo") ---
// Se aplica solo a CUALQUIER input[type="password"] de cualquier página que cargue este
// script, sin marcar cada campo. Para excluir uno: data-sin-ojo. Estructura que crea:
//   <div class="campo-password-wrapper"><input ...><button class="boton-mostrar-password">…</button></div>
// El estado visible/oculto lo dibuja el CSS (clase .es-visible); aquí no hay estilos en línea.
(function () {
    const ICONOS = `
        <svg class="icono-ojo-abierto" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
        <svg class="icono-ojo-cerrado" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
            <line x1="1" y1="1" x2="23" y2="23"/>
        </svg>`;

    function agregarOjo(campo) {
        if (campo.dataset.ojoListo === '1' || campo.hasAttribute('data-sin-ojo')) return;
        campo.dataset.ojoListo = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'campo-password-wrapper';
        campo.parentNode.insertBefore(wrapper, campo);
        wrapper.appendChild(campo);

        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'boton-mostrar-password';
        // La etiqueta no cambia: el estado lo anuncia aria-pressed.
        boton.setAttribute('aria-label', campo.name === 'pin' ? 'Mostrar PIN' : 'Mostrar contraseña');
        boton.setAttribute('aria-pressed', 'false');
        if (campo.id) boton.setAttribute('aria-controls', campo.id);
        boton.innerHTML = ICONOS;
        wrapper.appendChild(boton);

        const fijar = (visible) => {
            campo.type = visible ? 'text' : 'password';
            boton.classList.toggle('es-visible', visible);
            boton.setAttribute('aria-pressed', visible ? 'true' : 'false');
        };

        boton.addEventListener('click', () => {
            fijar(campo.type === 'password');
            if (window.Sonidos) window.Sonidos.reproducir('clic');
        });

        // Siempre se envía y se guarda como contraseña (gestores de contraseñas, historial).
        if (campo.form) campo.form.addEventListener('submit', () => fijar(false));
        window.addEventListener('pagehide', () => fijar(false));
    }

    function iniciar() {
        document.querySelectorAll('input[type="password"]').forEach(agregarOjo);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
// --- "Pills" de radio (emociones, horas de sueño, calidad de sueño, días de
// la semana): el color dorado al seleccionar depende de :has(), que no todos
// los navegadores/tablets del colegio soportan todavía. Este listener hace lo
// mismo a mano con JS normal, así que funciona sin importar el navegador. ---
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll(
        '.grilla-emociones, .selector-horas-sueno, .grilla-calidad-sueno, .selector-dias-semana, .escala-likert'
    ).forEach((grupo) => {
        const marcarActiva = () => {
            grupo.querySelectorAll('label').forEach((etiqueta) => {
                const radio = etiqueta.querySelector('input[type="radio"], input[type="checkbox"]');
                if (!radio) return;
                // El elemento visual puede ser la propia etiqueta (.btn-emocion) o un
                // <span> hijo (.opcion-horas-sueno, .dia-semana-boton, .opcion-likert).
                const visual = etiqueta.querySelector('span') || etiqueta;
                visual.classList.toggle('activa', radio.checked);
            });
        };
        grupo.addEventListener('change', marcarActiva);
        marcarActiva();
    });
});

// --- Efecto de escritura para saludos (.efecto-escritura) ---
// El texto completo ya viene del servidor dentro del span (accesible para
// lectores de pantalla y visible de inmediato si este script no llega a
// correr). Si corre, lo reemplaza letra por letra como mejora progresiva.
document.addEventListener('DOMContentLoaded', () => {
    const prefiereMovimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const animacionesForzadas = localStorage.getItem('serena_forzar_animaciones') === '1';
    const saltarAnimacion = prefiereMovimientoReducido && !animacionesForzadas;

    document.querySelectorAll('.efecto-escritura').forEach((el) => {
        const textoCompleto = el.textContent.trim();
        if (!textoCompleto || saltarAnimacion) return;

        el.textContent = '';
        el.classList.add('escribiendo');
        let i = 0;
        const VELOCIDAD_MS = 45;
        const escribirSiguienteLetra = () => {
            i++;
            el.textContent = textoCompleto.slice(0, i);
            if (i < textoCompleto.length) {
                setTimeout(escribirSiguienteLetra, VELOCIDAD_MS);
            } else {
                setTimeout(() => el.classList.remove('escribiendo'), 900);
            }
        };
        setTimeout(escribirSiguienteLetra, 200);
    });
});

// --- Ventanas modales genéricas: cualquier elemento con data-abrir-modal="id"
// abre el modal con ese id; cualquier [data-cerrar-modal] o clic en el fondo lo
// cierra. Un solo manejador para toda la app, en vez de repetir la lógica en
// cada página que necesite una confirmación. ---
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-abrir-modal]').forEach((disparador) => {
        disparador.addEventListener('click', () => {
            const modal = document.getElementById(disparador.dataset.abrirModal);
            if (modal) modal.classList.add('visible');
        });
    });

    document.querySelectorAll('.modal-fondo').forEach((fondo) => {
        const cerrar = () => fondo.classList.remove('visible');
        fondo.addEventListener('click', (e) => {
            if (e.target === fondo) cerrar();
        });
        fondo.querySelectorAll('[data-cerrar-modal]').forEach((boton) => boton.addEventListener('click', cerrar));
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') document.querySelectorAll('.modal-fondo.visible').forEach((f) => f.classList.remove('visible'));
    });
});


