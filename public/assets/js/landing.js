// Portada pública: borde del menú al hacer scroll y la tarjeta de muestra «¿Cómo te sientes hoy?».
// Todo es opcional: sin JS la página se lee completa y la tarjeta igual se puede marcar.
(function () {
    var nav = document.querySelector('.nav-publica');
    if (nav) {
        var alDesplazar = function () { nav.classList.toggle('con-sombra', window.scrollY > 8); };
        window.addEventListener('scroll', alDesplazar, { passive: true });
        alDesplazar();
    }

    var form = document.getElementById('demo-emocion');
    var salida = document.getElementById('demo-respuesta');
    if (!form || !salida) return;

    // Respuestas de muestra. Describen lo que de verdad hace Serena (no prometen nada que no haga).
    var dificiles = ['ansioso', 'triste', 'cansado'];
    var textos = {
        feliz: '¡Qué bien! Guardarlo te ayuda a ver qué días te sientan mejor.',
        tranquilo: 'Anotado. Con el tiempo verás tu semana en un gráfico y lo que la acompaña.',
        ansioso: 'Gracias por contarlo. Si te sirve, prueba un ejercicio de respiración de dos minutos.',
        triste: 'Gracias por contarlo. Puedes escribirlo en tu diario: solo tú lo lees.',
        cansado: 'Anotado. Revisa cómo vienes durmiendo: a veces la respuesta está ahí.'
    };
    var avisoAlto = ' Si los días difíciles se repiten, psicología y orientación reciben un aviso para buscarte.';

    function valor(nombre) {
        var el = form.querySelector('input[name="' + nombre + '"]:checked');
        return el ? el.value : '';
    }

    function actualizar() {
        var emocion = valor('demo_emocion');
        var nivel = parseInt(valor('demo_nivel'), 10) || 0;
        form.querySelectorAll('.punto-intensidad-escena').forEach(function (p, i) {
            p.classList.toggle('lleno', i < nivel);
        });
        var texto = textos[emocion] || '';
        if (dificiles.indexOf(emocion) !== -1 && nivel >= 4) texto += avisoAlto;
        if (salida.textContent.trim() !== texto) {
            salida.textContent = texto;
            salida.classList.remove('cambio');
            void salida.offsetWidth; // reinicia la animación
            salida.classList.add('cambio');
        }
    }

    form.addEventListener('change', actualizar);
    actualizar();
})();
