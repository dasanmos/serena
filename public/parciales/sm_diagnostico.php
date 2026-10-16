<?php
declare(strict_types=1);

/**
 * Aviso de SIMAT/SIMPADE cuando el año elegido no tiene matrículas ACTIVAS.
 * Evita la pantalla «vacía» sin explicación: dice qué hay en este año y a qué año con datos se puede cambiar.
 * Variables que espera: $pdo, $colegioId, $anioId, $anio, $smUrl (closure), $e (closure), $vista.
 */
$smEstados = Simat::estadosMatricula($pdo, $colegioId, $anioId);
if (($smEstados['activa'] ?? 0) > 0) {
    return;
}
$smOtros = array_values(array_filter(Simat::aniosConActivas($pdo, $colegioId), static fn (array $a): bool => (int) $a['id'] !== $anioId));
$smNombres = ['pendiente' => 'pendientes', 'activa' => 'activas', 'rechazada' => 'rechazadas', 'retirada' => 'retiradas'];
?>
<div class="mensaje mensaje-aviso sm-diagnostico" role="status">
    <div>
        <strong>El año <?= (int) ($anio['anio'] ?? 0) ?> no tiene matrículas activas.</strong>
        <?php if ($smEstados): ?>
            Hay <?php
                $partes = [];
                foreach ($smEstados as $est => $n) {
                    $partes[] = (int) $n . ' ' . ($smNombres[$est] ?? $e($est));
                }
                echo implode(', ', $partes);
            ?>. SIMAT y SIMPADE solo cuentan las <em>activas</em>: activa las pendientes en Matrículas.
        <?php else: ?>
            Aún no hay ninguna matrícula en este año.
        <?php endif; ?>
        <?php if ($smOtros): ?>
            <br>Con datos:
            <?php foreach ($smOtros as $i => $o): ?><?= $i > 0 ? ' · ' : ' ' ?><a href="<?= $e($smUrl($vista, ['anio' => (int) $o['id']])) ?>">Año <?= (int) $o['anio'] ?> (<?= (int) $o['n'] ?> activas)</a><?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
