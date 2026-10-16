<?php
// Pestaña «Aprobación anticipada» (promoción anticipada). Solo registra; no cambia el grado ni el grupo.
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
$aprobs = HojaVida::aprobaciones($pdo, $estId);
$grados = $perm['escribir_aprobacion'] ? Academico::grados($pdo, $colegioId) : [];
?>
<h2 class="hv-titulo">Aprobación anticipada</h2>
<?php if (!$aprobs): ?>
    <p>Este estudiante no tiene aprobación anticipada.</p>
<?php else: ?>
<div class="acad-tabla-envoltura"><table class="acad-tabla">
    <caption class="sr-only">Aprobaciones anticipadas</caption>
    <thead><tr><th scope="col">Fecha</th><th scope="col">Año</th><th scope="col">Del grado</th><th scope="col">Al grado</th><th scope="col">Acta</th><th scope="col">Motivo</th><th scope="col">Registró</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($aprobs as $a): ?>
        <tr><td><?= $e($a['fecha']) ?></td><td><?= $e($a['anio'] ?? '') ?: '—' ?></td><td><?= $e($a['grado_origen'] ?? '') ?: '—' ?></td><td><?= $e($a['grado_destino'] ?? '') ?: '—' ?></td>
            <td><?= $e($a['acta'] ?? '') ?: '—' ?></td><td style="white-space:pre-line"><?= $e($a['motivo'] ?? '') ?: '—' ?></td><td><?= $e($a['registrador'] ?? '') ?: '—' ?></td>
            <td><?php if ($perm['escribir_aprobacion']): ?><form action="estudiante_ficha.php" method="POST" onsubmit="return confirm('¿Eliminar este registro?');"><?php $ocultos('aprob_eliminar', ['aprob_id' => (int) $a['id']]); ?><button type="submit" class="boton-peligro">Quitar</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
<?php if ($perm['escribir_aprobacion']): ?>
<details class="no-print"><summary>Registrar una aprobación anticipada</summary>
    <form action="estudiante_ficha.php" method="POST" novalidate>
        <?php $ocultos('aprob_agregar'); ?>
        <div class="hv-rejilla">
            <label class="hv-campo"><span class="hv-etq">Fecha *</span><input type="date" name="fecha" value="<?= $e($hoyIso) ?>" required></label>
            <label class="hv-campo"><span class="hv-etq">Acta o resolución</span><input type="text" name="acta" maxlength="60"></label>
            <label class="hv-campo"><span class="hv-etq">Del grado</span><select name="grado_origen"><option value="0">—</option><?php foreach ($grados as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $e($g['nombre']) ?></option><?php endforeach; ?></select></label>
            <label class="hv-campo"><span class="hv-etq">Al grado</span><select name="grado_destino"><option value="0">—</option><?php foreach ($grados as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $e($g['nombre']) ?></option><?php endforeach; ?></select></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Motivo</span><textarea name="motivo" rows="3" maxlength="5000"></textarea></label>
        </div>
        <p class="hv-acciones"><button type="submit">Registrar</button> <span class="texto-suave">Esto deja constancia en la hoja de vida; el cambio de grado o grupo se hace en Matrículas.</span></p>
    </form>
</details>
<?php endif; ?>
