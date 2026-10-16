// SERENA — requisitosPassword.js
//
// Nota emergente de requisitos de contraseña, estilo "comentario de Excel":
// un globo con checklist que aparece junto al campo al enfocarlo y marca en
// vivo cada requisito según se va cumpliendo. Espejo en JS del mismo regex
// que valida el backend (ver REGEX_PASSWORD en src/helpers/validaciones.php)
// — si uno cambia, el otro debe actualizarse igual.
//
// Uso: agregar data-requisitos="1" al <input type="password"> que se quiera
// validar con la regla completa de Serena (8-64 caracteres, mayúscula,
// minúscula, dígito y carácter especial). Los campos que NO llevan ese
// atributo (login, PIN del diario) se ignoran a propósito.
(function () {
    var REQUISITOS = [
        { texto: 'Entre 8 y 64 caracteres', prueba: function (v) { return v.length >= 8 && v.length <= 64; } },
        { texto: 'Al menos una mayúscula (A-Z)', prueba: function (v) { return /[A-Z]/.test(v); } },
        { texto: 'Al menos una minúscula (a-z)', prueba: function (v) { return /[a-z]/.test(v); } },
        { texto: 'Al menos un número (0-9)', prueba: function (v) { return /[0-9]/.test(v); } },
        { texto: 'Al menos un carácter especial', prueba: function (v) { return /[^A-Za-z0-9]/.test(v); } }
    ];

    function construirNota(campo, id) {
        var nota = document.createElement('div');
        nota.className = 'nota-requisitos-password';
        nota.id = id;
        nota.setAttribute('role', 'status');
        nota.setAttribute('aria-live', 'polite');

        var titulo = document.createElement('strong');
        titulo.textContent = 'La contraseña debe tener:';
        nota.appendChild(titulo);

        var lista = document.createElement('ul');
        REQUISITOS.forEach(function (req) {
            var item = document.createElement('li');
            item.textContent = req.texto;
            lista.appendChild(item);
        });
        nota.appendChild(lista);
        return { nota: nota, items: lista.querySelectorAll('li') };
    }

    function inicializarCampo(campo) {
        // Evita envolver dos veces si el script se carga más de una vez por error.
        if (campo.dataset.requisitosListo === '1') return;
        campo.dataset.requisitosListo = '1';

        var envoltura = document.createElement('div');
        envoltura.className = 'envoltura-campo-password';
        campo.parentNode.insertBefore(envoltura, campo);
        envoltura.appendChild(campo);

        var idNota = 'nota-requisitos-' + (campo.id || Math.random().toString(36).slice(2));
        var construido = construirNota(campo, idNota);
        envoltura.appendChild(construido.nota);

        var descritoAntes = campo.getAttribute('aria-describedby');
        campo.setAttribute('aria-describedby', descritoAntes ? descritoAntes + ' ' + idNota : idNota);

        function actualizar() {
            var valor = campo.value;
            REQUISITOS.forEach(function (req, indice) {
                var item = construido.items[indice];
                if (req.prueba(valor)) {
                    item.classList.add('cumplido');
                } else {
                    item.classList.remove('cumplido');
                }
            });
        }

        campo.addEventListener('focus', function () {
            actualizar();
            construido.nota.classList.add('visible');
        });
        campo.addEventListener('input', actualizar);
        campo.addEventListener('blur', function () {
            construido.nota.classList.remove('visible');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var campos = document.querySelectorAll('input[type="password"][data-requisitos]');
        campos.forEach(inicializarCampo);
    });
})();
