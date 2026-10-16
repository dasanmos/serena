<?php
// Pestaña «Desarrollo»: por semestre, 14 criterios con valoración y tratamiento familiar e institucional.
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
$anioDev = $anioSel;
$dev = $anioDev ? HojaVida::desarrollo($pdo, $estId, (int) $anioDev['id']) : [];
?>
<h2 class="hv-titulo">Desarrollo</h2>
<?php if (!$anioDev): ?>
    <div class="estado-vacio"><p>Todavía no hay un año lectivo creado.</p></div>
<?php else: ?>
<form action="estudiante_ficha.php" method="GET" class="hv-filtro no-print">
    <input type="hidden" name="est" value="<?= $estId ?>"><input type="hidden" name="seccion" value="desarrollo">
    <label>Año
        <select name="anio" onchange="this.form.submit()">
            <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === (int) $anioDev['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?><?= (int) $a['actual'] === 1 ? ' (actual)' : '' ?></option><?php endforeach; ?>
        </select>
    </label>
</form>
<?php foreach (HojaVida::SEMESTRES as $sem => $nombreSem): ?>
<section class="hv-semestre">
    <h3><?= $e($nombreSem) ?></h3>
    <?php if ($perm['escribir_desarrollo']): ?><form action="estudiante_ficha.php" method="POST" novalidate><?php $ocultos('dev_guardar', ['anio_id' => (int) $anioDev['id'], 'semestre' => $sem]); ?><?php endif; ?>
    <div class="acad-tabla-envoltura"><table class="acad-tabla hv-tabla-desarrollo">
        <caption class="sr-only"><?= $e($nombreSem) ?> del año <?= (int) $anioDev['anio'] ?></caption>
        <thead><tr><th scope="col">Criterio</th><th scope="col">Valoración</th><th scope="col">Tratamiento familiar</th><th scope="col">Tratamiento institucional</th></tr></thead>
        <tbody>
        <?php foreach (HojaVida::CRITERIOS as $k => $nombre): $r = $dev[$sem][$k] ?? []; ?>
            <tr>
                <th scope="row"><?= $e($nombre) ?></th>
                <?php if ($perm['escribir_desarrollo']): ?>
                <td><select name="d[<?= $e($k) ?>][v]" aria-label="Valoración: <?= $e($nombre) ?>"><option value="">—</option><?php foreach (HojaVida::ESCALA as $ek => $et): ?><option value="<?= $e($ek) ?>" <?= ($r['valoracion'] ?? '') === $ek ? 'selected' : '' ?>><?= $e($et) ?></option><?php endforeach; ?></select></td>
                <td><input type="text" name="d[<?= $e($k) ?>][tf]" maxlength="300" value="<?= $e($r['tratamiento_familiar'] ?? '') ?>" aria-label="Tratamiento familiar: <?= $e($nombre) ?>" data-sin-voz></td>
                <td><input type="text" name="d[<?= $e($k) ?>][ti]" maxlength="300" value="<?= $e($r['tratamiento_institucional'] ?? '') ?>" aria-label="Tratamiento institucional: <?= $e($nombre) ?>" data-sin-voz></td>
                <?php else: ?>
                <td><?= $e(HojaVida::ESCALA[$r['valoracion'] ?? ''] ?? '—') ?></td>
                <td><?= $e($r['tratamiento_familiar'] ?? '') ?: '—' ?></td>
                <td><?= $e($r['tratamiento_institucional'] ?? '') ?: '—' ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php if ($perm['escribir_desarrollo']): ?><p class="hv-acciones"><button type="submit">Guardar <?= $e(mb_strtolower($nombreSem)) ?></button></p></form><?php endif; ?>
</section>
<?php endforeach; ?>
<p class="texto-suave">Información reservada: incluye valoraciones de percepción y estabilidad emocional. <?= $rol === 'docente' ? 'La ves por ser director de grupo de este estudiante.' : '' ?></p>
<?php endif; ?>
