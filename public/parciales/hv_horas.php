<?php
// Pestaña «Horas formativas»: servicio social (80 h) y Constitución Política (50 h).
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
$horas = Expediente::horas($pdo, $estId);
$totales = Expediente::totalesHoras($pdo, $estId);
$fmtH = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.') ?: '0';
?>
<h2 class="hv-titulo">Servicio social y horas de Constitución</h2>
<?php foreach ($totales as $t): $pct = $t['requeridas'] > 0 ? min(100, (int) round($t['cumplidas'] / $t['requeridas'] * 100)) : 0; ?>
    <p style="margin:6px 0 2px"><strong><?= $e($t['nombre']) ?>:</strong> <?= $e($fmtH($t['cumplidas'])) ?> de <?= (int) $t['requeridas'] ?> horas (<?= $pct ?> %) <?= $t['cumplidas'] >= $t['requeridas'] ? '✅ cumplido' : '' ?></p>
    <progress max="<?= (int) $t['requeridas'] ?>" value="<?= $e(min($t['requeridas'], $t['cumplidas'])) ?>" style="width:100%"></progress>
<?php endforeach; ?>
<?php if ($horas): ?>
<div class="acad-tabla-envoltura" style="margin-top:12px"><table class="acad-tabla">
    <caption class="sr-only">Horas registradas</caption>
    <thead><tr><th scope="col">Fecha</th><th scope="col">Tipo</th><th scope="col">Horas</th><th scope="col">Actividad</th><th scope="col">Registró</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($horas as $h): ?>
        <tr><td><?= $e($h['fecha']) ?></td><td><?= $e(Expediente::HORAS[$h['tipo']][0] ?? $h['tipo']) ?></td><td><?= $e($fmtH($h['horas'])) ?></td>
            <td><?= $e($h['actividad']) ?><?= $h['entidad'] ? '<br><small class="texto-suave">' . $e($h['entidad']) . '</small>' : '' ?></td><td><?= $e($h['registrador']) ?></td>
            <td><?php if ($cap['escribir_horas'] && $rol !== 'docente'): ?>
                <form action="estudiante_ficha.php" method="POST" onsubmit="return confirm('¿Eliminar este registro?');"><?php $ocultos('horas_eliminar', ['hora_id' => (int) $h['id']]); ?><button type="submit" class="boton-peligro">Quitar</button></form>
            <?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
<?php if ($cap['escribir_horas']): ?>
<details class="no-print"><summary>Registrar horas</summary>
    <form action="estudiante_ficha.php" method="POST" class="acad-filtros" novalidate>
        <?php $ocultos('horas_agregar'); ?>
        <label>Tipo <select name="tipo"><?php foreach (Expediente::HORAS as $k => [$n]): ?><option value="<?= $e($k) ?>"><?= $e($n) ?></option><?php endforeach; ?></select></label>
        <label>Fecha <input type="date" name="fecha" value="<?= $e($hoyIso) ?>" max="<?= $e($hoyIso) ?>"></label>
        <label>Horas <input type="number" name="horas" min="0.5" max="24" step="0.5" required style="width:6em"></label>
        <label>Actividad <input type="text" name="actividad" maxlength="200" required></label>
        <label>Entidad (opcional) <input type="text" name="entidad" maxlength="120"></label>
        <button type="submit">Registrar</button>
    </form>
</details>
<?php endif; ?>
