<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Evaluacion.php';
require_once __DIR__ . '/../src/modelos/Asistencia.php';

// Asistencia de un estudiante. Mismo alcance que el boletín: el estudiante ve la suya, el acudiente la de sus hijos
// y el personal académico la de cualquier estudiante de su colegio.
requiereRol('estudiante', 'acudiente', 'docente', 'coordinador', 'administrador', 'rector', 'secretaria_academica');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaConsulta();
if ($colegioId === null) {
    http_response_code(403);
    exit('No se pudo determinar el colegio.');
}
$e = static fn ($v): string => htmlspecialchars((string) $v);

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

$nombreEst = '';
$resumen = null;
$incidencias = [];
if ($permitido && $anio) {
    $q = $pdo->prepare('SELECT nombre FROM usuarios WHERE id = ?');
    $q->execute([$estId]);
    $nombreEst = (string) $q->fetchColumn();
    $resumen = Asistencia::resumenEstudiante($pdo, $estId, (int) $anio['id']);
    $incidencias = Asistencia::incidencias($pdo, $estId, (int) $anio['id']);
}
$esPersonal = !in_array($rol, ['estudiante', 'acudiente'], true);

$tituloPagina = 'Asistencia';
$cssExtra = ['/assets/css/academico.css', '/assets/css/asistencia.css'];
require __DIR__ . '/parciales/encabezado.php';
?>

<h1>Asistencia</h1>

<?php if ($rol === 'acudiente' && count($hijos) > 1): ?>
    <form method="GET" class="acad-filtros">
        <label>Estudiante
            <select name="est" onchange="this.form.submit()">
                <?php foreach ($hijos as $h): ?><option value="<?= (int) $h['id'] ?>" <?= (int) $h['id'] === $estId ? 'selected' : '' ?>><?= $e($h['nombre']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <noscript><button type="submit">Ver</button></noscript>
    </form>
<?php endif; ?>

<?php if ($estId <= 0): ?>
    <div class="estado-vacio"><p><?= $esPersonal ? 'Elige un estudiante desde el reporte de asistencia del grupo.' : 'No hay estudiantes asociados a tu cuenta.' ?></p>
        <?php if ($esPersonal): ?><p><a href="<?= $e(BASE_URL) ?>/asistencia_reporte.php">Ir al reporte de asistencia</a></p><?php endif; ?></div>
<?php elseif (!$permitido): ?>
    <p class="mensaje mensaje-error" role="alert">No tienes permiso para ver la asistencia de este estudiante.</p>
<?php elseif (!$anio): ?>
    <p class="mensaje mensaje-aviso">No hay un año lectivo configurado.</p>
<?php else: ?>

    <?php if (count($anios) > 1): ?>
        <form method="GET" class="acad-filtros">
            <input type="hidden" name="est" value="<?= $estId ?>">
            <label>Año
                <select name="anio" onchange="this.form.submit()">
                    <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === (int) $anio['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?></option><?php endforeach; ?>
                </select>
            </label>
            <noscript><button type="submit">Ver</button></noscript>
        </form>
    <?php endif; ?>

    <h2><?= $e($nombreEst) ?> — <?= (int) $anio['anio'] ?></h2>

    <?php if ($resumen['total'] === 0): ?>
        <div class="estado-vacio"><p>Todavía no hay asistencia registrada en este año.</p></div>
    <?php else: ?>
        <div class="asist-tarjetas">
            <div class="asist-tarjeta"><strong><?= (int) $resumen['total'] ?></strong><span>Clases registradas</span></div>
            <?php foreach (Asistencia::ESTADOS as $clave => $texto): ?>
                <div class="asist-tarjeta"><strong><?= (int) $resumen[$clave] ?></strong><span><?= $e($texto) ?></span></div>
            <?php endforeach; ?>
            <div class="asist-tarjeta"><strong><?= $e(number_format((float) $resumen['pct_inasistencia'], 1, ',', '')) ?>%</strong><span>Inasistencia</span></div>
            <div class="asist-tarjeta"><strong><?= (int) $resumen['dias_ausente'] ?></strong><span>Días con ausencia sin justificar</span></div>
        </div>
        <p class="asist-nota">Inasistencia = clases con ausencia (con o sin excusa) sobre las clases registradas. Un día con varias clases perdidas cuenta como un solo día.</p>

        <section class="panel-seccion">
            <h2>Llegadas tarde, ausencias y permisos</h2>
            <?php if (!$incidencias): ?>
                <p class="texto-suave">No hay incidencias: todas las clases registradas fueron de asistencia completa.</p>
            <?php else: ?>
                <div class="acad-tabla-envoltura">
                    <table class="acad-tabla asist-tabla">
                        <caption class="sr-only">Incidencias de asistencia de <?= $e($nombreEst) ?></caption>
                        <thead><tr><th scope="col">Fecha</th><th scope="col">Clase</th><th scope="col">Estado</th><th scope="col">Nota</th></tr></thead>
                        <tbody>
                        <?php foreach ($incidencias as $i): ?>
                            <tr>
                                <td><?= $e(date('d/m/Y', strtotime((string) $i['fecha']))) ?></td>
                                <td><?= $e($i['asignatura']) ?></td>
                                <td><span class="estado-asist estado-asist-<?= $e($i['estado']) ?>"><?= $e(Asistencia::ESTADOS[$i['estado']] ?? $i['estado']) ?></span></td>
                                <td><?= $e($i['observacion'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/parciales/pie.php'; ?>
