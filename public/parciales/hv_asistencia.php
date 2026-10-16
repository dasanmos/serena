<?php
// Pestaña «Asistencia»: inasistencias por asignatura y período del año actual.
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
$anioAct = Academico::anioActual($pdo, $colegioId);
$periodosAct = $anioAct ? Academico::periodos($pdo, (int) $anioAct['id']) : [];
$asistencia = $anioAct ? Expediente::resumenAsistencia($pdo, $colegioId, $estId, $anioAct, $periodosAct) : [];
?>
<h2 class="hv-titulo">Asistencia <?= $anioAct ? (int) $anioAct['anio'] : '' ?></h2>
<?php if (!$asistencia): ?>
    <p class="texto-suave">Todavía no hay datos de asistencia.</p>
<?php else: ?>
<div class="acad-tabla-envoltura"><table class="acad-tabla boletin-tabla">
    <caption class="sr-only">Asistencia por asignatura y período</caption>
    <thead><tr><th scope="col">Asignatura</th><?php foreach ($periodosAct as $p): ?><th scope="col" class="nota"><?= $e($p['nombre']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($asistencia as $fila): ?>
        <tr><td><?= $e($fila['asignatura']) ?></td>
        <?php foreach ($periodosAct as $p): $c = $fila['periodos'][(int) $p['id']]; ?>
            <td class="nota"><?= $c['ausencias'] ?><?= $c['excusadas'] > 0 ? ' <small class="texto-suave">(' . $c['excusadas'] . ' exc.)</small>' : '' ?><?= $c['tardes'] > 0 ? ' <small class="texto-suave">+' . $c['tardes'] . ' tarde(s)</small>' : '' ?></td>
        <?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody></table></div>
<p class="texto-suave">Inasistencias (clases a las que no asistió) por asignatura y período.</p>
<?php endif; ?>
