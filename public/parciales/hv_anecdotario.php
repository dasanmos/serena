<?php
// Pestaña «Anecdotario»: hechos relevantes o peticiones de ayuda. «Pasar a» los envía al observador o a un proceso.
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
require __DIR__ . '/hv_helpers.php';
$filas = HojaVida::filtrarPorFecha(HojaVida::anotaciones($pdo, $estId, 'anecdotario', $cap['ver_obs_reservado'], $cap['ver_obs_familia']), $rango);
$evid = HojaVida::evidencias($pdo, $estId, 'observador');
$opcionesPasar = [];
if ($perm['pasar_observador']) { $opcionesPasar['academica'] = 'Hoja de vida · académica'; $opcionesPasar['convivencia'] = 'Hoja de vida · convivencia'; }
if ($perm['pasar_proceso']) { $opcionesPasar['proceso'] = $cap['gestionar_convivencia'] ? 'Proceso disciplinario' : 'Reportar a coordinación'; }
?>
<h2 class="hv-titulo">Anecdotario</h2>
<p class="texto-suave">Anecdotario del estudiante: <strong><?= $e($est['nombre']) ?></strong><?= $cap['ver_obs_familia'] ? '. Solo se muestran las anotaciones que el colegio compartió con la familia.' : '' ?></p>
<?php require __DIR__ . '/hv_filtro.php'; ?>
<div class="acad-tabla-envoltura"><table class="acad-tabla hv-tabla">
    <caption class="sr-only">Anecdotario</caption>
    <thead><tr><th scope="col">Fecha / Período</th><th scope="col">Descripción del hecho y/o petición de ayuda</th><th scope="col">Evidencias</th><th scope="col">Situación</th><th scope="col" class="no-print">Pasar a</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $o): $oid = (int) $o['id']; ?>
        <tr>
            <td><strong><?= $e($o['fecha']) ?></strong><br><small class="texto-suave"><?= $e(HojaVida::etiquetaPeriodo($periodos, (string) $o['fecha'])) ?></small><?= $o['autor'] ? '<br><small class="texto-suave">' . $e($o['autor']) . '</small>' : '' ?></td>
            <td style="white-space:pre-line"><?= $e($o['descripcion']) ?><?= $o['compromiso'] ? "\n" . '<em>Compromiso: ' . $e($o['compromiso']) . '</em>' : '' ?><?= !$cap['ver_obs_familia'] && (int) $o['visible_familia'] === 0 ? "\n" . '<small class="texto-suave">No visible para la familia</small>' : '' ?></td>
            <td><?php $celdaEvidencias('observador', $oid, $evid[$oid] ?? [], $cap['escribir_observador'], $cap['gestionar_convivencia'], 'anecdotario'); ?></td>
            <td><?= $e($etiquetaSituacion($o['situacion'] ?? null)) ?></td>
            <td class="no-print">
                <?php if ($opcionesPasar && empty($o['pasado_proceso_id'])): ?>
                <form action="estudiante_ficha.php" method="POST" class="hv-pasar">
                    <?php $ocultos('anot_pasar', ['obs_id' => $oid, 'grupo' => 'anecdotario']); ?>
                    <select name="destino" aria-label="Pasar a"><?php foreach ($opcionesPasar as $k => $t): ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select>
                    <button type="submit" class="boton-secundario">Pasar</button>
                </form>
                <?php elseif (!empty($o['pasado_proceso_id'])): ?><small class="texto-suave">Pasó a proceso disciplinario</small><?php endif; ?>
                <?php if ($cap['gestionar_convivencia']): ?>
                <form action="estudiante_ficha.php" method="POST" onsubmit="return confirm('¿Eliminar esta anotación? Quedará registrado en auditoría.');"><?php $ocultos('anot_eliminar', ['obs_id' => $oid, 'grupo' => 'anecdotario']); ?><button type="submit" class="boton-peligro">Eliminar</button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$filas): ?><tr><td colspan="5" class="texto-suave">Sin registros en este período.</td></tr><?php endif; ?>
    </tbody></table></div>

<?php if ($cap['escribir_observador']): ?>
<details class="no-print"><summary>Nueva anotación en el anecdotario</summary>
    <form action="estudiante_ficha.php" method="POST" enctype="multipart/form-data" novalidate>
        <?php $ocultos('anot_agregar', ['grupo' => 'anecdotario']); ?>
        <div class="hv-rejilla">
            <label class="hv-campo"><span class="hv-etq">Fecha *</span><input type="date" name="fecha" value="<?= $e($hoyIso) ?>" max="<?= $e($hoyIso) ?>" required></label>
            <label class="hv-campo"><span class="hv-etq">Situación</span><select name="situacion"><option value="">—</option><?php foreach (Expediente::SITUACIONES as $k => $t): ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Descripción del hecho y/o petición de ayuda *</span><textarea name="descripcion" rows="4" maxlength="5000" required></textarea></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Compromiso o acuerdo (opcional)</span><input type="text" name="compromiso" maxlength="500"></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Evidencia (imagen, opcional)</span><input type="file" name="evidencia" accept="image/jpeg,image/png,image/webp,image/gif"></label>
            <label class="hv-campo hv-ancho hv-check"><input type="checkbox" name="visible_familia" checked> Visible para el acudiente</label>
        </div>
        <p class="hv-acciones"><button type="submit">Registrar anotación</button></p>
    </form>
</details>
<?php endif; ?>
