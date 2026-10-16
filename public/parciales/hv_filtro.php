<?php
// Filtro Año / Período (como en Master2000). Variables: $anios, $anioSel, $periodos, $periodoSel, $estId, $seccion.
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
?>
<form action="estudiante_ficha.php" method="GET" class="hv-filtro no-print">
    <input type="hidden" name="est" value="<?= $estId ?>"><input type="hidden" name="seccion" value="<?= $e($seccion) ?>">
    <strong>Filtro</strong>
    <label>Año
        <select name="anio" onchange="this.form.periodo.value='0'; this.form.submit()">
            <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $anioSel && (int) $a['id'] === (int) $anioSel['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?><?= (int) $a['actual'] === 1 ? ' (actual)' : '' ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Período
        <select name="periodo" onchange="this.form.submit()">
            <option value="0">Todo el año</option>
            <?php foreach ($periodos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $periodoSel === (int) $p['id'] ? 'selected' : '' ?>><?= $e($p['nombre']) ?></option><?php endforeach; ?>
        </select>
    </label>
    <noscript><button type="submit">Filtrar</button></noscript>
</form>
