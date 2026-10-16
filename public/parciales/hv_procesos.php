<?php
// Pestaña «Procesos disciplinarios» (Ley 1620 de 2013). El docente solo puede reportar; coordinación gestiona.
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
require __DIR__ . '/hv_helpers.php';
$verLista = $perm['ver_procesos_lista'];
$procesos = $verLista ? HojaVida::filtrarPorFecha(Expediente::procesos($pdo, $estId), $rango) : [];
$acts = $verLista ? HojaVida::actuacionesDe($pdo, array_column($procesos, 'id')) : [];
$evid = $verLista ? HojaVida::evidencias($pdo, $estId, 'proceso') : [];
$puedeAbrir = $cap['gestionar_convivencia'] || $cap['reportar_convivencia'];
?>
<h2 class="hv-titulo">Procesos disciplinarios</h2>
<p class="texto-suave">Situaciones tipo I, II y III de la Ley 1620 de 2013 y su ruta de atención. Se registra el debido proceso: hechos, descargos, actuaciones y decisión.</p>
<?php if ($verLista): ?>
<?php require __DIR__ . '/hv_filtro.php'; ?>
<div class="acad-tabla-envoltura"><table class="acad-tabla hv-tabla">
    <caption class="sr-only">Procesos disciplinarios</caption>
    <thead><tr><th scope="col">Fecha / Período</th><th scope="col">Descripción del hecho y/o petición de ayuda</th><th scope="col">Evidencias</th><th scope="col">Situación</th><th scope="col">Citaciones y/o cese de labores</th><th scope="col">Tratamiento inmediato</th><th scope="col">Compromiso</th><th scope="col" class="no-print">Pasar a</th><th scope="col">Folio</th></tr></thead>
    <tbody>
    <?php foreach ($procesos as $p): $pid = (int) $p['id']; $lista = $acts[$pid] ?? [];
        $citas = array_filter($lista, static fn (array $a): bool => $a['tipo'] === 'citacion_acudiente');
        $compr = array_filter($lista, static fn (array $a): bool => $a['tipo'] === 'compromiso');
        $otras = array_filter($lista, static fn (array $a): bool => !in_array($a['tipo'], ['citacion_acudiente', 'compromiso'], true)); ?>
        <tr>
            <td><strong><?= $e($p['fecha']) ?></strong><br><small class="texto-suave"><?= $e(HojaVida::etiquetaPeriodo($periodos, (string) $p['fecha'])) ?></small><?= $p['abierto_por_nombre'] ? '<br><small class="texto-suave">' . $e($p['abierto_por_nombre']) . '</small>' : '' ?></td>
            <td style="white-space:pre-line"><?= $e($p['descripcion']) ?></td>
            <td><?php $celdaEvidencias('proceso', $pid, $evid[$pid] ?? [], $cap['gestionar_convivencia'], $cap['gestionar_convivencia'], 'procesos'); ?></td>
            <td><?= $e($etiquetaSituacion($p['situacion_tipo'])) ?><br><span class="estado-pill estado-<?= $p['estado'] === 'cerrado' ? 'matriculada' : ($p['estado'] === 'reportado' ? 'pendiente' : 'en_revision') ?>"><?= $e(Expediente::ESTADOS_PROCESO[$p['estado']] ?? $p['estado']) ?></span></td>
            <td><?php foreach ($citas as $a): ?><p class="hv-mini"><strong><?= $e($a['fecha']) ?>:</strong> <?= $e($a['detalle']) ?></p><?php endforeach; ?><?= $citas ? '' : '—' ?></td>
            <td style="white-space:pre-line"><?= $e($p['tratamiento_inmediato'] ?? '') ?: '—' ?></td>
            <td><?php foreach ($compr as $a): ?><p class="hv-mini"><strong><?= $e($a['fecha']) ?>:</strong> <?= $e($a['detalle']) ?></p><?php endforeach; ?><?= $compr ? '' : '—' ?></td>
            <td class="no-print">
                <?php if ($cap['gestionar_convivencia'] && $p['estado'] !== 'cerrado'): ?>
                <form action="estudiante_ficha.php" method="POST" class="hv-pasar">
                    <?php $ocultos('proceso_pasar', ['proceso_id' => $pid]); ?>
                    <select name="destino" aria-label="Pasar a"><option value="orientacion">Orientación</option><option value="comite">Comité de convivencia</option></select>
                    <button type="submit" class="boton-secundario">Pasar</button>
                </form>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= $p['folio'] !== null ? (int) $p['folio'] : '—' ?></td>
        </tr>
        <tr class="hv-detalle"><td colspan="9">
            <details>
                <summary>Actuaciones, decisión y gestión del proceso (<?= count($lista) ?>)</summary>
                <?php foreach ($otras as $a): ?><p class="boletin-obs"><strong><?= $e($a['fecha']) ?> · <?= $e(Expediente::TIPOS_ACTUACION[$a['tipo']] ?? $a['tipo']) ?><?= $a['autor'] ? ' · ' . $e($a['autor']) : '' ?>:</strong> <?= $e($a['detalle']) ?></p><?php endforeach; ?>
                <?php if (!$otras): ?><p class="texto-suave">Sin otras actuaciones.</p><?php endif; ?>
                <?php if ($p['decision']): ?><p><strong>Decisión:</strong> <?= $e($p['decision']) ?></p><?php endif; ?>
                <?php if ($cap['gestionar_convivencia'] && $p['estado'] !== 'cerrado'): ?>
                <form action="estudiante_ficha.php" method="POST" class="acad-filtros no-print" novalidate>
                    <?php $ocultos('actuacion_agregar', ['proceso_id' => $pid]); ?>
                    <label>Fecha <input type="date" name="fecha" value="<?= $e($hoyIso) ?>"></label>
                    <label>Tipo <select name="tipo"><?php foreach (Expediente::TIPOS_ACTUACION as $k => $t): ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
                    <label style="flex:1 1 280px">Detalle <textarea name="detalle" rows="2" maxlength="5000" required style="width:100%" data-sin-voz></textarea></label>
                    <button type="submit">Registrar actuación</button>
                </form>
                <form action="estudiante_ficha.php" method="POST" class="acad-filtros no-print" novalidate>
                    <?php $ocultos('proceso_estado', ['proceso_id' => $pid]); ?>
                    <label>Estado <select name="estado"><?php foreach (Expediente::ESTADOS_PROCESO as $k => $t): if ($k === 'reportado') { continue; } ?><option value="<?= $e($k) ?>" <?= $p['estado'] === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
                    <label style="flex:1 1 280px">Decisión final (obligatoria para cerrar) <textarea name="decision" rows="2" maxlength="5000" style="width:100%" data-sin-voz><?= $e($p['decision']) ?></textarea></label>
                    <button type="submit" class="boton-secundario">Actualizar estado</button>
                </form>
                <?php endif; ?>
            </details>
        </td></tr>
    <?php endforeach; ?>
    <?php if (!$procesos): ?><tr><td colspan="9" class="texto-suave">Sin procesos en este período.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php endif; ?>

<?php if ($puedeAbrir): ?>
<details class="no-print" <?= $verLista ? '' : 'open' ?>><summary><?= $cap['gestionar_convivencia'] ? 'Abrir un proceso' : 'Reportar una situación a coordinación' ?></summary>
    <form action="estudiante_ficha.php" method="POST" enctype="multipart/form-data" novalidate>
        <?php $ocultos('proceso_abrir'); ?>
        <div class="hv-rejilla">
            <label class="hv-campo"><span class="hv-etq">Fecha de los hechos</span><input type="date" name="fecha" value="<?= $e($hoyIso) ?>" max="<?= $e($hoyIso) ?>"></label>
            <label class="hv-campo"><span class="hv-etq">Tipo de situación *</span><select name="situacion"><?php foreach (Expediente::SITUACIONES as $k => $t): ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Descripción objetiva de los hechos *</span><textarea name="descripcion" rows="4" maxlength="5000" required data-sin-voz></textarea></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Tratamiento inmediato</span><input type="text" name="tratamiento" maxlength="500" data-sin-voz></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Evidencia (imagen, opcional)</span><input type="file" name="evidencia" accept="image/jpeg,image/png,image/webp,image/gif"></label>
        </div>
        <p class="hv-acciones"><button type="submit"><?= $cap['gestionar_convivencia'] ? 'Abrir proceso' : 'Enviar reporte' ?></button></p>
    </form>
</details>
<?php endif; ?>
