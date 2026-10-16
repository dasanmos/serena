/* SERENA — seguridad.js
   Verificación en 2 pasos: entrada del código, QR local, copiar/descargar/imprimir
   los códigos de recuperación. Nada de esto envía datos a terceros: el código QR
   se dibuja aquí mismo con qrcode.js. */
(function () {
    'use strict';

    // --- Campo del código de 6 dígitos: solo números y envío automático al llegar a 6 ---
    document.querySelectorAll('[data-codigo-2fa]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            var digitos = campo.value.replace(/\D/g, '').slice(0, 6);
            if (campo.value !== digitos) campo.value = digitos;
            if (digitos.length === 6 && campo.form) {
                if (typeof campo.form.requestSubmit === 'function') campo.form.requestSubmit();
                else campo.form.submit();
            }
        });
    });

    // --- Código QR (local, sin servicios externos) ---
    var contenedorQR = document.querySelector('[data-qr-uri]');
    if (contenedorQR && typeof qrcode === 'function') {
        try {
            var qr = qrcode(0, 'M');
            qr.addData(contenedorQR.getAttribute('data-qr-uri'));
            qr.make();
            contenedorQR.innerHTML = qr.createSvgTag({ cellSize: 2, margin: 8, scalable: true });
        } catch (e) {
            contenedorQR.textContent = 'No se pudo dibujar el código QR. Usa la clave manual.';
        }
    }

    // --- Copiar ---
    function copiar(texto, boton) {
        var original = boton.textContent;
        function listo() {
            boton.textContent = 'Copiado';
            setTimeout(function () { boton.textContent = original; }, 1800);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(listo, function () { window.prompt('Copia manualmente:', texto); });
        } else {
            window.prompt('Copia manualmente:', texto);
        }
    }

    var botonClave = document.querySelector('[data-copiar-clave]');
    if (botonClave) {
        botonClave.addEventListener('click', function () {
            var clave = document.querySelector('[data-clave-manual]').textContent.replace(/\s+/g, '');
            copiar(clave, botonClave);
        });
    }

    // --- Códigos de recuperación ---
    var lista = document.querySelector('[data-codigos]');
    if (lista) {
        function codigos() {
            return Array.prototype.map.call(lista.querySelectorAll('code'), function (c) { return c.textContent.trim(); });
        }

        var botonCopiar = document.querySelector('[data-copiar-codigos]');
        if (botonCopiar) {
            botonCopiar.addEventListener('click', function () { copiar(codigos().join('\n'), botonCopiar); });
        }

        var botonDescargar = document.querySelector('[data-descargar-codigos]');
        if (botonDescargar) {
            botonDescargar.addEventListener('click', function () {
                var texto = 'Serena — códigos de recuperación\r\n'
                    + 'Cuenta: ' + botonDescargar.getAttribute('data-cuenta') + '\r\n'
                    + 'Vencen el: ' + botonDescargar.getAttribute('data-vence') + '\r\n'
                    + 'Cada código sirve una sola vez. Guárdalos en un lugar seguro.\r\n\r\n'
                    + codigos().join('\r\n') + '\r\n';
                var blob = new Blob([texto], { type: 'text/plain;charset=utf-8' });
                var enlace = document.createElement('a');
                enlace.href = URL.createObjectURL(blob);
                enlace.download = 'serena-codigos-de-recuperacion.txt';
                document.body.appendChild(enlace);
                enlace.click();
                document.body.removeChild(enlace);
                setTimeout(function () { URL.revokeObjectURL(enlace.href); }, 1000);
            });
        }

        var botonImprimir = document.querySelector('[data-imprimir]');
        if (botonImprimir) botonImprimir.addEventListener('click', function () { window.print(); });

        // "Listo" solo se habilita al confirmar que se guardaron (sin JS el botón queda activo).
        var casilla = document.querySelector('[data-confirma-codigos]');
        var botonListo = document.querySelector('[data-boton-confirma]');
        if (casilla && botonListo) {
            botonListo.disabled = true;
            casilla.addEventListener('change', function () { botonListo.disabled = !casilla.checked; });
        }
    }

    // --- Reenviar código de 2FA por correo: cuenta regresiva local, sin recargar ---
    var botonReenviar = document.querySelector('[data-reenviar-2fa]');
    if (botonReenviar) {
        var restante = parseInt(botonReenviar.getAttribute('data-espera'), 10) || 0;
        var textoBase = 'Reenviar código';
        function pintar() {
            if (restante > 0) {
                botonReenviar.disabled = true;
                botonReenviar.textContent = textoBase + ' (' + restante + 's)';
            } else {
                botonReenviar.disabled = false;
                botonReenviar.textContent = textoBase;
            }
        }
        if (restante > 0) {
            pintar();
            var intervalo = setInterval(function () {
                restante--;
                if (restante <= 0) { clearInterval(intervalo); }
                pintar();
            }, 1000);
        }
    }
})();
