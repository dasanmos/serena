// Login: muestra el colegio al que pertenece el correo escrito y da feedback al enviar.
(function () {
    var correo = document.getElementById('correo');
    var caja = document.getElementById('colegio-login');
    var nombre = document.getElementById('colegio-login-nombre');
    var form = correo ? correo.form : null;
    if (!correo || !caja || !nombre) return;

    var ultimo = '';
    var temporizador = null;
    var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var DOC = /^(?=.*\d)[0-9A-Za-z.\-\s]{4,25}$/; // número de documento: con o sin puntos
    function valido(v) { return EMAIL.test(v) || (v.indexOf('@') === -1 && DOC.test(v)); }

    function ocultar() { caja.hidden = true; caja.classList.remove('visible'); ultimo = ''; }

    function consultar() {
        var valor = correo.value.trim();
        if (!valido(valor)) { ocultar(); return; }
        if (valor === ultimo) return;
        ultimo = valor;
        fetch((window.SERENA_BASE_URL || '') + '/api/colegio_login.php?q=' + encodeURIComponent(valor), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { colegio: null }; })
            .then(function (d) {
                if (correo.value.trim() !== valor) return; // el usuario siguió escribiendo
                if (d && d.colegio) {
                    nombre.textContent = d.colegio;
                    caja.hidden = false;
                    requestAnimationFrame(function () { caja.classList.add('visible'); });
                } else {
                    ocultar();
                    ultimo = valor;
                }
            })
            .catch(function () { ocultar(); });
    }

    correo.addEventListener('input', function () {
        clearTimeout(temporizador);
        if (!valido(correo.value.trim())) { ocultar(); return; }
        temporizador = setTimeout(consultar, 600);
    });
    correo.addEventListener('blur', consultar);
    if (correo.value) consultar();

    if (form) {
        // Aviso de Bloq Mayús en la contraseña
        var pass = document.getElementById('password');
        var aviso = document.getElementById('aviso-mayus');
        if (pass && aviso) {
            var revisar = function (e) { aviso.hidden = !(e.getModifierState && e.getModifierState('CapsLock')); };
            pass.addEventListener('keydown', revisar);
            pass.addEventListener('keyup', revisar);
            pass.addEventListener('blur', function () { aviso.hidden = true; });
        }
        form.addEventListener('submit', function () {
            var b = form.querySelector('button[type="submit"]');
            if (b) { b.classList.add('cargando'); b.setAttribute('aria-busy', 'true'); b.textContent = 'Ingresando…'; setTimeout(function(){ b.disabled = true; }, 0); }
        });
    }
})();
