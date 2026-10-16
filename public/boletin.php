<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Evaluacion.php';

// Boletín de notas. Estudiantes y acudientes solo ven los períodos con boletín PUBLICADO; el personal ve todo.
requiereRol('estudiante', 'acudiente', 'docente', 'coordinador', 'administrador', 'rector', 'secretaria_academica');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaConsulta();
if ($colegioId === null) {
    http_response_code(403);
    exit('No se pudo determinar el colegio.');
}
$esFamilia = in_array($rol, ['estudiante', 'acudiente'], true);
$e = static fn ($v): string => htmlspecialchars((string) $v);

// ¿De qué estudiante?
$hijos = [];
if ($rol === 'estudiante') {
    $estId = $usuarioId;
} elseif ($rol === 'acudiente') {
    $hijos = Academico::hijos($pdo, $usuarioId);
    $estId = (int) ($_GET['est'] ?? ($hijos[0]['id'] ?? 0));
} else {
    $estId = (int) ($_GET['est'] ?? 0);
}
$permitido = $estId > 0 && Evaluacion::puedeVerBoletin($pdo, $rol, $usuarioId, $estId, $colegioId);

$anios = Academico::anios($pdo, $colegioId);
$anioSel = (int) ($_GET['anio'] ?? 0);
$anio = $anioSel > 0 ? Academico::anio($pdo, $anioSel, $colegioId) : Academico::anioActual($pdo, $colegioId);
if ($anio === null && $anios) {
    $anio = $anios[0];
}
$b = ($permitido && $anio) ? Evaluacion::boletin($pdo, $colegioId, $estId, $anio, $esFamilia) : null;
$cq = $pdo->prepare('SELECT nombre FROM colegios WHERE id = ?');
$cq->execute([$colegioId]);
$colegioNombre = (string) $cq->fetchColumn();

$tituloPagina = 'Boletín de notas';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/parciales/encabezado.php';
?>

<h1>Boletín de notas</h1>

<?php if (!$esFamilia && $estId === 0): ?>
    <p class="mensaje mensaje-aviso">Elige un estudiante desde <a href="<?= $e(BASE_URL) ?>/secretaria_academica/boletines.php">Boletines</a>.</p>
<?php elseif (!$permitido): ?>
    <div class="estado-vacio"><p><?= $rol === 'acudiente' && !$hijos ? 'Todavía no tienes estudiantes vinculados.' : 'No tienes permiso para ver ese boletín.' ?></p></div>
<?php else: ?>

<form action="boletin.php" method="GET" class="acad-filtros no-print">
    <?php if ($rol === 'acudiente' && count($hijos) > 1): ?>
        <label>Estudiante
            <select name="est" onchange="this.form.submit()">
                <?php foreach ($hijos as $h): ?><option value="<?= (int) $h['id'] ?>" <?= $estId === (int) $h['id'] ? 'selected' : '' ?>><?= $e($h['nombre']) ?></option><?php endforeach; ?>
            </select>
        </label>
    <?php else: ?>
        <input type="hidden" name="est" value="<?= $estId ?>">
    <?php endif; ?>
    <label>Año
        <select name="anio" onchange="this.form.submit()">
            <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $anio && (int) $anio['id'] === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?></option><?php endforeach; ?>
        </select>
    </label>
    <button type="button" class="boton-secundario" onclick="window.print()">Imprimir / guardar PDF</button>
</form>

<?php if ($b === null): ?>
    <div class="estado-vacio"><p>No hay un año lectivo configurado.</p></div>
<?php elseif ($b['escala'] === null): ?>
    <p class="mensaje mensaje-aviso">El colegio aún no ha configurado su sistema de evaluación.</p>
<?php elseif (!$b['grupo']): ?>
    <div class="estado-vacio"><p>El estudiante no tiene grupo asignado en <?= (int) $anio['anio'] ?>.</p></div>
<?php elseif (!$b['periodos']): ?>
    <div class="estado-vacio"><p><?= $esFamilia ? 'Todavía no hay boletines publicados para este año. El colegio los publicará al cierre de cada período.' : 'Este año no tiene períodos.' ?></p></div>
<?php else: ?>
    <?php $esc = $b['escala']; $desc = $esc['tipo'] === 'descriptiva'; ?>
    <section class="panel-seccion">
        <div class="boletin-cabecera">
            <div>
                <h2 style="margin:0"><?= $e($b['estudiante']['nombre']) ?></h2>
                <p class="texto-suave" style="margin:0"><?= $e($colegioNombre) ?></p>
            </div>
            <div class="texto-suave">Grado <?= $e($b['grupo']['grado'] . ' ' . $b['grupo']['nombre']) ?> · Año lectivo <?= (int) $anio['anio'] ?></div>
        </div>
        <?php if (!$b['filas']): ?>
            <p class="texto-suave">Este grupo aún no tiene asignaturas asignadas.</p>
        <?php else: ?>
        <div class="acad-tabla-envoltura">
            <table class="acad-tabla boletin-tabla">
                <thead>
                    <tr>
                        <th>Asignatura</th>
                        <?php foreach ($b['periodos'] as $p): ?><th class="nota"><?= $e($p['nombre']) ?></th><?php endforeach; ?>
                        <th class="nota">Definitiva</th>
                    </tr>
                </thead>
                <tbody>
                <?php $areaActual = null; $hayAreas = (bool) array_filter(array_column($b['filas'], 'area')); foreach ($b['filas'] as $f): ?>
                    <?php $areaFila = $f['area'] ?: ($hayAreas ? 'Otras asignaturas' : null); ?>
                    <?php if ($areaFila && $areaFila !== $areaActual): $areaActual = $areaFila; ?>
                        <tr class="area"><td colspan="<?= count($b['periodos']) + 2 ?>"><?= $e($areaActual) ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td><?= $e($f['asignatura']) ?><?= $f['docente'] ? '<br><small class="texto-suave">' . $e($f['docente']) . '</small>' : '' ?></td>
                        <?php foreach ($b['periodos'] as $p): $c = $f['periodos'][(int) $p['id']]; ?>
                            <td class="nota <?= $c['nivel'] && (int) $c['nivel']['aprueba'] === 0 ? 'nota-baja' : '' ?>"><?= $e(Evaluacion::textoNota($c['valor'], $c['nivel'], $esc)) ?></td>
                        <?php endforeach; ?>
                        <td class="nota <?= $f['nivel_definitiva'] && (int) $f['nivel_definitiva']['aprueba'] === 0 ? 'nota-baja' : '' ?>"><strong><?= $e(Evaluacion::textoNota($f['definitiva'], $f['nivel_definitiva'], $esc)) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if (!$desc): ?>
                <tfoot>
                    <tr>
                        <th>Promedio general</th>
                        <?php foreach ($b['periodos'] as $p): ?><th class="nota"><?= isset($b['promedios'][(int) $p['id']]) ? $e(number_format($b['promedios'][(int) $p['id']], (int) $esc['decimales'], '.', '')) : '—' ?></th><?php endforeach; ?>
                        <th class="nota"><?= $b['promedio_final'] !== null ? $e(number_format($b['promedio_final'], (int) $esc['decimales'], '.', '')) : '—' ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <p class="texto-suave" style="margin-top:12px"><strong>Escala:</strong>
            <?php foreach ($esc['niveles'] as $n): ?>
                <?= $e($n['abreviatura']) ?> = <?= $e($n['nombre']) ?><?= $desc ? '' : ' (' . $e(rtrim(rtrim(number_format((float) $n['desde'], 2, '.', ''), '0'), '.')) . '–' . $e(rtrim(rtrim(number_format((float) $n['hasta'], 2, '.', ''), '0'), '.')) . ')' ?><?= (int) $n['aprueba'] === 0 ? ' · no aprueba' : '' ?>;
            <?php endforeach; ?>
        </p>

        <?php
          $hayObs = false;
          foreach ($b['filas'] as $f) { foreach ($f['periodos'] as $c) { if (!empty($c['observacion'])) { $hayObs = true; } } }
        ?>
        <?php if ($hayObs): ?>
            <h3>Observaciones de los docentes</h3>
            <?php foreach ($b['filas'] as $f): foreach ($b['periodos'] as $p): $c = $f['periodos'][(int) $p['id']]; if (empty($c['observacion'])) { continue; } ?>
                <p class="boletin-obs"><strong><?= $e($f['asignatura']) ?> · <?= $e($p['nombre']) ?>:</strong> <?= $e($c['observacion']) ?></p>
            <?php endforeach; endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/parciales/pie.php'; ?>
