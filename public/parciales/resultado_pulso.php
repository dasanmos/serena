<?php
declare(strict_types=1);
/**
 * Espera $resultadosActivo con la forma de Pulso::resultados() ya validada
 * (insuficiente === false). Se incluye desde docente/pulso.php.
 */
?>
<p>Promedio: <strong><?= number_format($resultadosActivo['promedio'], 1) ?> / 5</strong> — <?= $resultadosActivo['total'] ?> respuestas</p>
<div class="barras-pulso">
    <?php $maxConteo = max($resultadosActivo['distribucion']) ?: 1; ?>
    <?php for ($valor = 1; $valor <= 5; $valor++): $n = $resultadosActivo['distribucion'][$valor]; ?>
    <div class="fila-barra-pulso">
        <span class="etiqueta-barra-pulso"><?= $valor ?></span>
        <div class="pista-barra-pulso">
            <div class="relleno-barra-pulso" style="width: <?= (int) round($n / $maxConteo * 100) ?>%"></div>
        </div>
        <span class="numero-barra-pulso"><?= $n ?></span>
    </div>
    <?php endfor; ?>
</div>
