// SERENA — sonidos.js
// Sonidos sintetizados con Web Audio API (sin archivos externos, sin red).
// Tono suave y discreto, coherente con el enfoque de bienestar del proyecto:
// nada estridente, nada tipo "juego" — campanitas cortas y graves.

const Sonidos = (() => {
    let ctx = null;
    const CLAVE_MUTE = 'serena_sonido_silenciado';

    function contexto() {
        if (!ctx) {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return null;
            ctx = new AC();
        }
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    }

    function estaSilenciado() {
        return localStorage.getItem(CLAVE_MUTE) === '1';
    }

    function alternarSilencio() {
        const nuevo = !estaSilenciado();
        localStorage.setItem(CLAVE_MUTE, nuevo ? '1' : '0');
        return nuevo;
    }

    // Un tono simple, con envolvente suave para que no "chasquee" al empezar/terminar.
    function tono({ frecuencia, duracion = 0.18, tipo = 'sine', volumen = 0.09, retraso = 0 }) {
        if (estaSilenciado()) return;
        const c = contexto();
        if (!c) return;
        const inicio = c.currentTime + retraso;
        const osc = c.createOscillator();
        const gain = c.createGain();
        osc.type = tipo;
        osc.frequency.setValueAtTime(frecuencia, inicio);
        gain.gain.setValueAtTime(0, inicio);
        gain.gain.linearRampToValueAtTime(volumen, inicio + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, inicio + duracion);
        osc.connect(gain).connect(c.destination);
        osc.start(inicio);
        osc.stop(inicio + duracion + 0.02);
    }

    const sonidos = {
        // Clic ligero — cualquier botón/acción secundaria
        clic: () => tono({ frecuencia: 520, duracion: 0.06, volumen: 0.05 }),

        // Roce/hover — todavía más discreto que el clic, casi subliminal
        roce: () => tono({ frecuencia: 660, duracion: 0.035, volumen: 0.025, tipo: 'sine' }),

        // Confirmación de guardado — dos notas ascendentes, cortas
        exito: () => {
            tono({ frecuencia: 587, duracion: 0.14 });
            tono({ frecuencia: 784, duracion: 0.22, retraso: 0.09 });
        },

        // Notificación entrante — una nota suave, campana
        notificacion: () => tono({ frecuencia: 880, duracion: 0.3, tipo: 'triangle', volumen: 0.07 }),

        // Mensaje de chat entrante — dos notas cortas tipo "ding-ding"
        mensaje: () => {
            tono({ frecuencia: 700, duracion: 0.1, tipo: 'triangle', volumen: 0.06 });
            tono({ frecuencia: 900, duracion: 0.12, tipo: 'triangle', volumen: 0.06, retraso: 0.08 });
        },

        // Logro / racha / meta completada — arpegio ascendente de 3 notas, celebratorio pero breve
        logro: () => {
            [523, 659, 784].forEach((f, i) => tono({ frecuencia: f, duracion: 0.28, tipo: 'triangle', volumen: 0.08, retraso: i * 0.1 }));
        },

        // Error / advertencia — nota grave, corta, sin ser alarmante
        error: () => tono({ frecuencia: 220, duracion: 0.2, tipo: 'sine', volumen: 0.06 }),

        // Marcar/tildar algo (hábito completado, checkbox, subtarea) — un "pop" cortito
        marcar: () => tono({ frecuencia: 740, duracion: 0.08, tipo: 'triangle', volumen: 0.06 }),

        // Abrir/cerrar el menú
        menu: () => tono({ frecuencia: 440, duracion: 0.07, volumen: 0.04 }),

        // --- Sonidos de la capa de experiencia (experiencia.js) ---
        // Elegir una opción (radio, emoción, calidad de sueño...). Recibe un índice para que cada
        // opción de un mismo grupo suene a una nota distinta de una escala pentatónica: elegir
        // emociones "suena" como tocar un xilófono suave, nunca desafinado.
        elegir: (indice = 0) => {
            const escala = [523, 587, 659, 784, 880, 1047, 1175, 1319, 1568, 1760];
            tono({ frecuencia: escala[Math.abs(indice) % escala.length], duracion: 0.16, tipo: 'triangle', volumen: 0.055 });
        },
        // Mover un control deslizante: un tic más agudo a mayor valor (0 a 1).
        tic: (valor = 0.5) => tono({ frecuencia: 420 + Math.max(0, Math.min(1, valor)) * 480, duracion: 0.045, tipo: 'sine', volumen: 0.035 }),
        // Desplegar / recoger un bloque (details, índice, acordeones)
        abrir: () => {
            tono({ frecuencia: 494, duracion: 0.08, volumen: 0.045 });
            tono({ frecuencia: 659, duracion: 0.1, volumen: 0.04, retraso: 0.05 });
        },
        cerrar: () => {
            tono({ frecuencia: 659, duracion: 0.08, volumen: 0.04 });
            tono({ frecuencia: 494, duracion: 0.1, volumen: 0.035, retraso: 0.05 });
        },
        // Ir a otra pantalla desde el menú o un acceso rápido
        navegar: () => {
            tono({ frecuencia: 587, duracion: 0.07, tipo: 'sine', volumen: 0.045 });
            tono({ frecuencia: 880, duracion: 0.12, tipo: 'sine', volumen: 0.035, retraso: 0.045 });
        },
        // Escribir en un campo por primera vez (suave, una sola vez por campo)
        enfoque: () => tono({ frecuencia: 784, duracion: 0.05, tipo: 'sine', volumen: 0.025 }),
        // Celebración grande: meta cumplida, insignia, racha larga (acompaña al confeti)
        celebrar: () => {
            [523, 659, 784, 1047].forEach((f, i) => tono({ frecuencia: f, duracion: 0.32, tipo: 'triangle', volumen: 0.075, retraso: i * 0.09 }));
            [1568, 2093].forEach((f, i) => tono({ frecuencia: f, duracion: 0.18, tipo: 'sine', volumen: 0.03, retraso: 0.42 + i * 0.07 }));
        },
    };

    return {
        reproducir: (nombre, ...args) => { if (sonidos[nombre]) sonidos[nombre](...args); },
        estaSilenciado,
        alternarSilencio,
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    const boton = document.getElementById('boton-sonido');
    if (boton) {
        const actualizarBoton = () => {
            const silenciado = Sonidos.estaSilenciado();
            boton.textContent = silenciado ? '🔇' : '🔊';
            boton.setAttribute('aria-pressed', silenciado ? 'true' : 'false');
            boton.title = silenciado ? 'Sonidos silenciados (clic para activar)' : 'Sonidos activados (clic para silenciar)';
        };
        actualizarBoton();
        boton.addEventListener('click', () => {
            Sonidos.alternarSilencio();
            actualizarBoton();
            if (!Sonidos.estaSilenciado()) Sonidos.reproducir('clic');
        });
    }

    // Mensajes de éxito ya renderizados en la página reproducen su sonido al cargar.
    // Si el texto trae 🎉 (meta completada, logro, etc.) usa el arpegio de logro en vez del genérico.
    const mensajeExito = document.querySelector('.mensaje-exito');
    if (mensajeExito) {
        Sonidos.reproducir(mensajeExito.textContent.includes('🎉') ? 'celebrar' : (mensajeExito.textContent.includes('✨') ? 'logro' : 'exito'));
    }
    if (document.querySelector('.mensaje-error')) Sonidos.reproducir('error');

    // Chat: si la ventana trae mensajes y el contador subió desde la última vez que esta persona miró
    // esta conversación en esta pestaña, suena una notificación de mensaje.
    const ventanaChat = document.querySelector('[data-sonido-nuevo-mensaje]');
    if (ventanaChat) {
        const totalActual = ventanaChat.dataset.sonidoNuevoMensaje;
        const claveVista = 'serena_chat_visto_' + window.location.pathname + window.location.search;
        const totalPrevio = sessionStorage.getItem(claveVista);
        if (totalPrevio !== null && totalActual !== totalPrevio) Sonidos.reproducir('mensaje');
        sessionStorage.setItem(claveVista, totalActual);
    }

    // Cualquier elemento marcado explícitamente con data-sonido reproduce ESE sonido
    // exacto al hacer clic (tiene prioridad sobre el genérico de abajo).
    document.querySelectorAll('[data-sonido]').forEach((el) => {
        el.addEventListener('click', () => Sonidos.reproducir(el.dataset.sonido));
    });

    // --- Sonido genérico en TODO botón/enlace de acción interactivo, sin tener que
    // marcarlos uno por uno con data-sonido. Se salta los que ya tienen data-sonido
    // propio (evita sonar doble) y los enlaces del menú de navegación (esos ya usan
    // 'clic' vía data-sonido si hace falta, y no queremos sonido en cada link suelto
    // del texto normal de la página).
    // Los <a> (enlaces que navegan) se manejan aparte más abajo, no aquí: si dejamos
    // que sonara en el mismo clic que ya está navegando, el navegador empieza a
    // cambiar de página antes de que el sonido de 60ms alcance a reproducirse —
    // "funciona pero no suena", exactamente el bug que se ve en los accesos rápidos
    // del dashboard. ---
    const SELECTOR_INTERACTIVO = 'button:not([data-sonido]), .check-habito:not([data-sonido]), .boton-check-subtarea:not([data-sonido]), .item-selector-persona button:not([data-sonido])';

    document.querySelectorAll(SELECTOR_INTERACTIVO).forEach((el) => {
        el.addEventListener('click', () => {
            // Un checkbox/marcar-hábito suena distinto a un botón normal.
            const esMarcar = el.classList.contains('check-habito') || el.classList.contains('boton-check-subtarea');
            Sonidos.reproducir(esMarcar ? 'marcar' : 'clic');
        });
    });

    // --- Enlaces de acción que SÍ navegan a otra página (.boton-acceso, .boton-secundario,
    // .boton-peligro usados como <a href>, no como <button>): se retrasa la navegación
    // unos 110ms — justo lo que tarda el tono "clic" en sonar — para que el sonido
    // alcance a reproducirse antes de que el navegador empiece a descargar la página.
    // Se respetan Ctrl/Cmd/Shift+clic y target="_blank" (abrir en pestaña nueva no
    // descarga esta página, así que no hace falta retrasar nada). ---
    document.querySelectorAll('a.boton-acceso:not([data-sonido]), a.boton-secundario:not([data-sonido]), a.boton-peligro:not([data-sonido])').forEach((el) => {
        el.addEventListener('click', (e) => {
            if (el.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
            e.preventDefault();
            Sonidos.reproducir('clic');
            setTimeout(() => { window.location.href = el.href; }, 110);
        });
    });

    // --- Sonido de "roce" al pasar el mouse — solo en dispositivos con mouse real
    // (nunca en táctil, donde "hover" no existe de verdad y sonaría en cada toque).
    // Con un enfriamiento mínimo entre sonidos para no saturar si el mouse pasa
    // rápido sobre una lista larga de tarjetas/botones. ---
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        let ultimoRoce = 0;
        const ENFRIAMIENTO_MS = 90;
        const SELECTOR_ROCE = 'button, .boton-acceso, .boton-secundario, .tarjeta, .panel-seccion, .menubar-boton, a[href]';

        document.querySelectorAll(SELECTOR_ROCE).forEach((el) => {
            el.addEventListener('mouseenter', () => {
                const ahora = Date.now();
                if (ahora - ultimoRoce < ENFRIAMIENTO_MS) return;
                ultimoRoce = ahora;
                Sonidos.reproducir('roce');
            });
        });
    }

    // Sonido al abrir/cerrar el menú de navegación (además de lo que ya hace base.js).
    document.querySelectorAll('#boton-menu-movil, .menubar-boton').forEach((botonMenu) => {
        botonMenu.addEventListener('click', () => Sonidos.reproducir('menu'));
    });
});

window.Sonidos = Sonidos;
