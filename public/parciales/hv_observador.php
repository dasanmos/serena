<?php
// Pestaña «Hoja de vida» (observador): anotaciones con folio, tratamiento inmediato y revisiones de coordinación.
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
require __DIR__ . '/hv_helpers.php';
$filas = HojaVida::filtrarPorFecha(HojaVida::anotaciones($pdo, $estId, 'observador', $cap['ver_obs_reservado'], $cap['ver_obs_familia']), $rango);
$evid = HojaVida::evidencias($pdo, $estId, 'observador');
$revisiones = $perm['cap']['ver_observador'] || $cap['ver_obs_familia'] ? HojaVida::filtrarPorFecha(HojaVida::revisiones($pdo, $estId), $rango) : [];
$procesoLabel = $cap['gestionar_convivencia'] ? 'Proceso disciplinario' : 'Reportar a coordinación';
?>
<h2 class="hv-titulo">Observador</h2>
<p class="texto-suave">Hoja de vida del estudiante: <strong><?= $e($est['nombre']) ?></strong><?= $cap['ver_obs_familia'] ? '. Solo se muestran las anotaciones que el colegio compartió con la familia.' : '' ?></p>
<?php require __DIR__ . '/hv_filtro.php'; ?>
<div class="acad-tabla-envoltura"><table class="acad-tabla hv-tabla">
    <caption class="sr-only">Observador del estudiante</caption>
    <thead><tr><th scope="col">Fecha / Período</th><th scope="col">Descripción del hecho y/o petición de ayuda</th><th scope="col">Evidencias</th><th scope="col">Situación</th><th scope="col">Tratamiento inmediato</th><th scope="col" class="no-print">Pasar a</th><th scope="col">Folio</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $o): $oid = (int) $o['id']; ?>
        <tr>
            <td><strong><?= $e($o['fecha']) ?></strong><br><small class="texto-suave"><?= $e(HojaVida::etiquetaPeriodo($periodos, (string) $o['fecha'])) ?></small><?= $o['autor'] ? '<br><small class="texto-suave">' . $e($o['autor']) . '</small>' : '' ?></td>
            <td><span class="estado-pill"><?= $e(Expediente::TIPOS_OBSERVADOR[$o['tipo']] ?? $o['tipo']) ?></span>
                <div style="white-space:pre-line"><?= $e($o['descripcion']) ?><?= $o['compromiso'] ? "\n" . '<em>Compromiso: ' . $e($o['compromiso']) . '</em>' : '' ?><?= !$cap['ver_obs_familia'] && (int) $o['visible_familia'] === 0 ? "\n" . '<small class="texto-suave">No visible para la familia</small>' : '' ?></div></td>
            <td><?php $celdaEvidencias('observador', $oid, $evid[$oid] ?? [], $cap['escribir_observador'], $cap['gestionar_convivencia'], 'observador'); ?></td>
            <td><?= $e($etiquetaSituacion($o['situacion'] ?? null)) ?></td>
            <td style="white-space:pre-line"><?= $e($o['tratamiento_inmediato'] ?? '') ?: '—' ?></td>
            <td class="no-print">
                <?php if ($perm['pasar_proceso'] && empty($o['pasado_proceso_id'])): ?>
                <form action="estudiante_ficha.php" method="POST" class="hv-pasar">
                    <?php $ocultos('anot_pasar', ['obs_id' => $oid, 'grupo' => 'observador', 'destino' => 'proceso']); ?>
                    <button type="submit" class="boton-secundario"><?= $e($procesoLabel) ?></button>
                </form>
                <?php elseif (!empty($o['pasado_proceso_id'])): ?><small class="texto-suave">Pasó a proceso disciplinario</small><?php endif; ?>
                <?php if ($cap['gestionar_convivencia']): ?>
                <form action="estudiante_ficha.php" method="POST" onsubmit="return confirm('¿Eliminar esta anotación? Quedará registrado en auditoría.');"><?php $ocultos('anot_eliminar', ['obs_id' => $oid, 'grupo' => 'observador']); ?><button type="submit" class="boton-peligro">Eliminar</button></form>
                <?php endif; ?>
            </td>
            <td><?= $o['folio'] !== null ? (int) $o['folio'] : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$filas): ?><tr><td colspan="7" class="texto-suave">Sin registros en este período.</td></tr><?php endif; ?>
    </tbody></table></div>

<?php if ($cap['escribir_observador']): ?>
<details class="no-print"><summary>Nueva anotación en la hoja de vida</summary>
    <form action="estudiante_ficha.php" method="POST" enctype="multipart/form-data" novalidate>
        <?php $ocultos('anot_agregar', ['grupo' => 'observador']); ?>
        <div class="hv-rejilla">
            <label class="hv-campo"><span class="hv-etq">Fecha *</span><input type="date" name="fecha" value="<?= $e($hoyIso) ?>" max="<?= $e($hoyIso) ?>" required></label>
            <label class="hv-campo"><span class="hv-etq">Tipo *</span><select name="tipo"><?php foreach (Expediente::TIPOS_OBSERVADOR as $k => $t): if ($k === 'anecdota' || ($k === 'seguimiento' && !$cap['ver_obs_reservado'])) { continue; } ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
            <label class="hv-campo"><span class="hv-etq">Situación</span><select name="situacion"><option value="">—</option><?php foreach (Expediente::SITUACIONES as $k => $t): ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Descripción del hecho y/o petición de ayuda *</span><textarea name="descripcion" rows="4" maxlength="5000" required></textarea></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Tratamiento inmediato</span><input type="text" name="tratamiento" maxlength="500"></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Compromiso o acuerdo (opcional)</span><input type="text" name="compromiso" maxlength="500"></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Evidencia (imagen, opcional)</span><input type="file" name="evidencia" accept="image/jpeg,image/png,image/webp,image/gif"></label>
            <label class="hv-campo hv-ancho hv-check"><input type="checkbox" name="visible_familia" checked> Visible para el acudiente</label>
        </div>
        <p class="hv-acciones"><button type="submit">Registrar anotación</button> <span class="texto-suave">El folio se asigna automáticamente.</span></p>
    </form>
</details>
<?php endif; ?>

<h3>Revisiones</h3>
<div class="acad-tabla-envoltura"><table class="acad-tabla">
    <caption class="sr-only">Revisiones del observador</caption>
    <thead><tr><th scope="col">Fecha revisión</th><th scope="col">Observaciones</th><th scope="col">Coordinador</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($revisiones as $r): ?>
        <tr><td><?= $e($r['fecha']) ?></td><td style="white-space:pre-line"><?= $e($r['observaciones']) ?></td><td><?= $e($r['coordinador'] ?? '') ?: '—' ?></td>
            <td class="no-print"><?php if ($perm['revisar']): ?><form action="estudiante_ficha.php" method="POST" onsubmit="return confirm('¿Eliminar esta revisión?');"><?php $ocultos('rev_eliminar', ['rev_id' => (int) $r['id']]); ?><button type="submit" class="boton-peligro">Quitar</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$revisiones): ?><tr><td colspan="4" class="texto-suave">Sin revisiones en este período.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php if ($perm['revisar']): ?>
<details class="no-print"><summary>Registrar una revisión</summary>
    <form action="estudiante_ficha.php" method="POST" novalidate>
        <?php $ocultos('rev_agregar'); ?>
        <div class="hv-rejilla">
            <label class="hv-campo"><span class="hv-etq">Fecha de revisión *</span><input type="date" name="fecha" value="<?= $e($hoyIso) ?>" max="<?= $e($hoyIso) ?>" required></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Observaciones *</span><textarea name="observaciones" rows="3" maxlength="2000" required></textarea></label>
        </div>
        <p class="hv-acciones"><button type="submit">Registrar revisión</button></p>
    </form>
</details>
<?php endif; ?>
