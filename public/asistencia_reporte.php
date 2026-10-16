<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Evaluacion.php';
require_once __DIR__ . '/../src/modelos/Asistencia.php';

// Reporte de asistencia por grupo. Coordinación, rectoría, administración y secretaría académica ven todos los grupos
// del colegio; un docente ve solo los grupos donde dicta clase o es director.
requiereRol('docente', 'coordinador', 'administrador', 'rector', 'secretaria_academica');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaConsulta();
if ($colegioId === null) {
    http_response_code(403);
    exit('No se pudo determinar el colegio.');
}
$esDocente = $rol === 'docente';
$e = static fn ($v): string => htmlspecialchars((string) $v);

$anios = Academico::anios($pdo, $colegioId);
$anioSel = (int) ($_GET['anio'] ?? 0);
$anio = $anioSel > 0 ? Academico::anio($pdo, $anioSel, $colegioId) : Academico::anioActual($pdo, $colegioId);
if ($anio === null && $anios) {
    $anio = $anios[0];
}

// Grupos visibles.
$grupos = Academico::grupos($pdo, $colegioId);
if ($esDocente) {
    $permitidos = [];
    if ($anio) {
        foreach (Evaluacion::asignaciones($pdo, $colegioId, (int) $anio['id'], $usuarioId) as $a) {
            $permitidos[(int) $a['grupo_id']] = true;
        }
    }
    $d = $pdo->prepare('SELECT id FROM grupos WHERE docente_id = ?');
    $d->execute([$usuarioId]);
    foreach ($d->fetchAll() as $r) {
        $permitidos[(int) $r['id']] = true;
    }
    $grupos = array_values(array_filter($grupos, static fn (array $g): bool => isset($permitidos[(int) $g['id']])));
}

$grupoId = (int) ($_GET['grupo'] ?? 0);
$grupo = null;
foreach ($grupos as $g) {
    if ((int) $g['id'] === $grupoId) {
        $grupo = $g; // solo se acepta un grupo del propio alcance
    }
}

$hoy = date('Y-m-d');
$desde = (string) ($_GET['desde'] ?? date('Y-m-d', strtotime('-30 days')));
$hasta = (string) ($_GET['hasta'] ?? $hoy);
if (!Asistencia::fechaValida($desde)) { $desde = date('Y-m-d', strtotime('-30 days')); }
if (!Asistencia::fechaValida($hasta)) { $hasta = $hoy; }
if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

$filas = [];
$atencion = [];
if ($grupo !== null && $anio !== null) {
    $ests = Evaluacion::estudiantesDeGrupo($pdo, $colegioId, $anio, (int) $grupo['id']);
    $filas = Asistencia::resumenGrupo($pdo, $colegioId, (int) $anio['id'], $ests, (int) $grupo['id'], $desde, $hasta);
    foreach ($filas as $sid => $f) {
        $atencion[$sid] = Asistencia::diasAusenteRecientes($pdo, (int) $sid, (int) $anio['id']) >= Asistencia::UMBRAL_DIAS;
    }
}

// Descarga CSV (UTF-8 con BOM para que Excel respete las tildes).
if ($grupo !== null && ($_GET['export'] ?? '') === 'csv') {
    $nombreArchivo = 'asistencia_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $grupo['grado'] . '_' . $grupo['nombre']) . '_' . $desde . '_' . $hasta . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Estudiante', 'Clases registradas', 'Presente', 'Llegó tarde', 'Ausente', 'Excusa', 'Permiso', 'Días con ausencia sin justificar', '% inasistencia', '% sin justificar'], ',', '"', '');
    foreach ($filas as $f) {
        // Evita que Excel interprete un nombre como fórmula.
        $nom = preg_match('/^[=+\-@]/', $f['nombre']) ? "'" . $f['nombre'] : $f['nombre'];
        fputcsv($o, [$nom, $f['total'], $f['presente'], $f['tarde'], $f['ausente'], $f['excusa'], $f['permiso'], $f['dias_ausente'],
            $f['pct_inasistencia'] ?? '', $f['pct_sin_justificar'] ?? ''], ',', '"', '');
    }
    fclose($o);
    exit;
}

$enRiesgo = (!$esDocente && $anio) ? Asistencia::enRiesgo($pdo, $colegioId, (int) $anio['id']) : [];
$pct = static fn (?float $v): string => $v === null ? '—' : number_format($v, 1, ',', '') . '%';

$tituloPagina = 'Reporte de asistencia';
$cssExtra = ['/assets/css/academico.css', '/assets/css/asistencia.css'];
require __DIR__ . '/parciales/encabezado.php';
?>

<h1>Reporte de asistencia</h1>

<?php if (!$anio): ?>
    <p class="mensaje mensaje-aviso">No hay un año lectivo configurado.</p>
<?php else: ?>

    <?php if ($enRiesgo): ?>
        <section class="panel-seccion">
            <h2>Requieren atención</h2>
            <p class="asist-nota"><?= Asistencia::UMBRAL_DIAS ?> o más días con ausencia sin justificar en las últimas <?= (int) (Asistencia::VENTANA_DIAS / 7) ?> semanas.</p>
            <ul class="lista-riesgo">
                <?php foreach ($enRiesgo as $r): ?>
                    <li><a href="<?= $e(BASE_URL) ?>/asistencia.php?est=<?= (int) $r['id'] ?>"><strong><?= $e($r['nombre']) ?></strong></a>
                        <?= $r['grupo'] ? '(' . $e($r['grupo']) . ')' : '' ?> — <?= (int) $r['dias'] ?> días, última ausencia el <?= $e(date('d/m', strtotime((string) $r['ultima']))) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <form method="GET" class="acad-filtros">
        <label>Año
            <select name="anio">
                <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $anio['id'] === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Grupo
            <select name="grupo">
                <?php if ($grupo === null): ?><option value="">Elige un grupo…</option><?php endif; ?>
                <?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $grupo && (int) $grupo['id'] === (int) $g['id'] ? 'selected' : '' ?>><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Desde <input type="date" name="desde" value="<?= $e($desde) ?>" max="<?= $e($hoy) ?>"></label>
        <label>Hasta <input type="date" name="hasta" value="<?= $e($hasta) ?>" max="<?= $e($hoy) ?>"></label>
        <button type="submit">Ver</button>
    </form>

    <?php if ($grupo === null): ?>
        <p class="texto-suave"><?= $grupos ? 'Elige un grupo para ver su asistencia.' : 'No hay grupos disponibles para ti en este colegio.' ?></p>
    <?php elseif (!$filas): ?>
        <div class="estado-vacio"><p>Este grupo no tiene estudiantes con matrícula activa en <?= (int) $anio['anio'] ?>.</p></div>
    <?php else: ?>
        <section class="panel-seccion">
            <h2><?= $e($grupo['grado'] . ' ' . $grupo['nombre']) ?> — del <?= $e(date('d/m/Y', strtotime($desde))) ?> al <?= $e(date('d/m/Y', strtotime($hasta))) ?></h2>
            <div class="acad-acciones">
                <a class="boton-secundario" href="?<?= $e(http_build_query(['anio' => (int) $anio['id'], 'grupo' => (int) $grupo['id'], 'desde' => $desde, 'hasta' => $hasta, 'export' => 'csv'])) ?>">Descargar CSV (Excel)</a>
                <button type="button" class="boton-secundario" onclick="window.print()">Imprimir</button>
            </div>
            <div class="acad-tabla-envoltura">
                <table class="acad-tabla asist-tabla">
                    <caption class="sr-only">Asistencia por estudiante del grupo <?= $e($grupo['grado'] . ' ' . $grupo['nombre']) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col">Estudiante</th>
                            <th scope="col" class="num">Clases</th>
                            <th scope="col" class="num">Presente</th>
                            <th scope="col" class="num">Tarde</th>
                            <th scope="col" class="num">Ausente</th>
                            <th scope="col" class="num">Excusa</th>
                            <th scope="col" class="num">Permiso</th>
                            <th scope="col" class="num">Días con ausencia sin justificar</th>
                            <th scope="col" class="num">% inasistencia</th>
                            <th scope="col" class="num">% sin justificar</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filas as $sid => $f): ?>
                        <tr<?= !empty($atencion[$sid]) ? ' class="atencion"' : '' ?>>
                            <td><a href="<?= $e(BASE_URL) ?>/asistencia.php?est=<?= (int) $sid ?>&amp;anio=<?= (int) $anio['id'] ?>"><?= $e($f['nombre']) ?></a><?= !empty($atencion[$sid]) ? '<span class="marca-atencion">Requiere atención</span>' : '' ?></td>
                            <td class="num"><?= (int) $f['total'] ?></td>
                            <td class="num"><?= (int) $f['presente'] ?></td>
                            <td class="num"><?= (int) $f['tarde'] ?></td>
                            <td class="num"><?= (int) $f['ausente'] ?></td>
                            <td class="num"><?= (int) $f['excusa'] ?></td>
                            <td class="num"><?= (int) $f['permiso'] ?></td>
                            <td class="num"><?= (int) $f['dias_ausente'] ?></td>
                            <td class="num"><?= $e($pct($f['pct_inasistencia'])) ?></td>
                            <td class="num"><?= $e($pct($f['pct_sin_justificar'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="asist-nota">Inasistencia = clases con ausencia (con o sin excusa) sobre las clases registradas en el rango. «Requiere atención» usa las últimas <?= (int) (Asistencia::VENTANA_DIAS / 7) ?> semanas, sin importar el rango elegido.</p>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/parciales/pie.php'; ?>
