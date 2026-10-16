<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
requiereRol('estudiante');
$tituloPagina = 'Respiración guiada';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Un momento para respirar</h1>
<p class="texto-suave">Sigue el círculo: inhala mientras crece, sostén, exhala mientras se encoge. No hay prisa.</p>

<section class="panel-seccion panel-respiracion">
    <div class="circulo-respiracion" id="circulo-respiracion">
        <span id="texto-respiracion">Presiona empezar</span>
    </div>

    <div class="controles-respiracion">
        <button type="button" class="boton-acceso" id="boton-empezar-respiracion" data-sonido="clic">Empezar</button>
        <button type="button" class="boton-secundario" id="boton-detener-respiracion" data-sonido="clic" style="display:none">Detener</button>
    </div>

    <p class="texto-suave u-mt-md">Ciclos completados: <strong id="contador-ciclos">0</strong></p>
</section>


<script>
(function () {
    const circulo = document.getElementById('circulo-respiracion');
    const texto = document.getElementById('texto-respiracion');
    const botonEmpezar = document.getElementById('boton-empezar-respiracion');
    const botonDetener = document.getElementById('boton-detener-respiracion');
    const contadorEl = document.getElementById('contador-ciclos');

    // Patrón 4-4-6: inhala 4s, sostén 4s, exhala 6s — un ciclo tranquilo y fácil
    // de seguir, no hace falta que el estudiante cuente nada, solo mirar y seguir.
    const FASES = [
        { clase: 'inhalar', texto: 'Inhala…', duracion: 4000 },
        { clase: 'sostener', texto: 'Sostén…', duracion: 4000 },
        { clase: 'exhalar', texto: 'Exhala…', duracion: 6000 },
    ];

    let activo = false;
    let indiceFase = 0;
    let ciclos = 0;
    let temporizador = null;

    function siguienteFase() {
        if (!activo) return;
        const fase = FASES[indiceFase];
        circulo.className = 'circulo-respiracion ' + fase.clase;
        texto.textContent = fase.texto;

        temporizador = setTimeout(() => {
            indiceFase = (indiceFase + 1) % FASES.length;
            if (indiceFase === 0) {
                ciclos += 1;
                contadorEl.textContent = String(ciclos);
            }
            siguienteFase();
        }, fase.duracion);
    }

    botonEmpezar.addEventListener('click', () => {
        activo = true;
        indiceFase = 0;
        botonEmpezar.style.display = 'none';
        botonDetener.style.display = 'inline-flex';
        siguienteFase();
    });

    botonDetener.addEventListener('click', () => {
        activo = false;
        clearTimeout(temporizador);
        circulo.className = 'circulo-respiracion';
        texto.textContent = 'Presiona empezar';
        botonEmpezar.style.display = 'inline-flex';
        botonDetener.style.display = 'none';
    });
})();
</script>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
