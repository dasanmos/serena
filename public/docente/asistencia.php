<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Expediente.php';

// Asistencia: registro DIARIO por clase, o RESUMEN al final del período (totales de ausencias por estudiante).
requiereRol('docente', 'administrador', 'rector', 'secretaria_academica');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$esDocente = $rol === 'docente';

$anio = Academico::anioActual($pdo, $colegioId);
$asigs = $anio ? Evaluacion::asignaciones($pdo, $colegioId, (int) $anio['id'], $esDocente ? $usuarioId : null) : [];
$periodos = $anio ? Academico::periodos($pdo, (int) $anio['id']) : [];
$asigId = (int) ($_GET['asig'] ?? $_POST['asig'] ?? 0);
$asig = null;
foreach ($asigs as $a) {
    if ((int) $a['id'] === $asigId) {
        $asig = $a; // solo una asignación del propio alcance
    }
}
$fecha = (string) ($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));
if ($fecha > date('Y-m-d')) {
    $fecha = date('Y-m-d');
}
$periodoId = (int) ($_GET['periodo'] ?? $_POST['periodo'] ?? ($periodos ? (int) $periodos[0]['id'] : 0));
$modo = (string) ($_GET['modo'] ?? $_POST['modo'] ?? 'diaria') === 'resumen' ? 'resumen' : 'diaria';

$flash = $_SESSION['flash_asistencia'] ?? null;
unset($_SESSION['flash_asistencia']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    if ($asig === null || !$anio) {
        $f['error'] = 'Elige una asignación válida.';
    } else {
        $ests = Evaluacion::estudiantesDeGrupo($pdo, $colegioId, $anio, (int) $asig['grupo_id']);
        if ((string) ($_POST['accion'] ?? '') === 'guardar_diaria') {
            $n = Expediente::guardarAsistencia($pdo, (int) $asig['id'], $fecha, $ests, (array) ($_POST['estado'] ?? []), (array) ($_POST['obs'] ?? []), $usuarioId);
            $f[$n >= 0 ? 'exito' : 'error'] = $n >= 0 ? "Asistencia del {$fecha} guardada ({$n} estudiantes)." : 'La fecha no es válida (no puede ser futura).';
        } elseif ((string) ($_POST['accion'] ?? '') === 'guardar_resumen') {
            if (Evaluacion::periodo($pdo, $periodoId, $colegioId) === null) {
                $f['error'] = 'Elige un período válido.';
            } else {
                $n = Expediente::guardarResumenPeriodo($pdo, (int) $asig['id'], $periodoId, $ests, (array) ($_POST['ausencias'] ?? []), (array) ($_POST['excusadas'] ?? []));
                $f['exito'] = "Resumen del período guardado ({$n} estudiantes con datos). Reemplaza el conteo del registro diario en el boletín.";
            }
        }
    }
    $_SESSION['flash_asistencia'] = $f;
    header('Location: asistencia.php?' . http_build_query(['asig' => $asigId, 'fecha' => $fecha, 'periodo' => $periodoId, 'modo' => $modo]));
    exit;
}

$estudiantes = ($asig && $anio) ? Evaluacion::estudiantesDeGrupo($pdo, $colegioId, $anio, (int) $asig['grupo_id']) : [];
$delDia = $asig ? Expediente::asistenciaDelDia($pdo, (int) $asig['id'], $fecha) : [];
$guardado = ($asig && $periodoId) ? Expediente::resumenPeriodoGuardado($pdo, (int) $asig['id'], $periodoId) : [];
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Asistencia';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Asistencia</h1>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<?php if (!$anio): ?>
    <p class="mensaje mensaje-aviso">No hay un año lectivo actual configurado.</p>
<?php elseif (!$asigs): ?>
    <div class="estado-vacio"><p><?= $esDocente ? 'No tienes asignaturas asignadas en este año. Pídele al administrador que te asigne tus grupos.' : 'No hay asignaciones académicas en este año.' ?></p></div>
<?php else: ?>
<form action="asistencia.php" method="GET" class="acad-filtros">
    <label>Grupo y asignatura
        <select name="asig" onchange="this.form.submit()"><option value="0">Elige…</option>
            <?php foreach ($asigs as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $asigId === (int) $a['id'] ? 'selected' : '' ?>><?= $e($a['grado'] . ' ' . $a['grupo'] . ' · ' . $a['asignatura']) ?></option><?php endforeach; ?></select></label>
    <label>Modo
        <select name="modo" onchange="this.form.submit()">
            <option value="diaria" <?= $modo === 'diaria' ? 'selected' : '' ?>>Diaria (por clase)</option>
            <option value="resumen" <?= $modo === 'resumen' ? 'selected' : '' ?>>Resumen al final del período</option></select></label>
    <?php if ($modo === 'diaria'): ?>
        <label>Fecha <input type="date" name="fecha" value="<?= $e($fecha) ?>" max="<?= $e(date('Y-m-d')) ?>" onchange="this.form.submit()"></label>
    <?php else: ?>
        <label>Período <select name="periodo" onchange="this.form.submit()"><?php foreach ($periodos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $periodoId === (int) $p['id'] ? 'selected' : '' ?>><?= $e($p['nombre']) ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <button type="submit">Abrir</button>
</form>

<?php if ($asig && !$estudiantes): ?>
    <div class="estado-vacio"><p>Ese grupo no tiene estudiantes con matrícula activa.</p></div>
<?php elseif ($asig && $modo === 'diaria'): ?>
<section class="panel-seccion">
    <h2><?= $e($asig['grado'] . ' ' . $asig['grupo'] . ' · ' . $asig['asignatura']) ?> · <?= $e($fecha) ?></h2>
    <form action="asistencia.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="guardar_diaria"><input type="hidden" name="asig" value="<?= $asigId ?>"><input type="hidden" name="fecha" value="<?= $e($fecha) ?>"><input type="hidden" name="modo" value="diaria">
        <p><button type="button" class="boton-secundario" id="todos-presentes">Marcar todos presentes</button></p>
        <div class="acad-tabla-envoltura"><table class="acad-tabla">
            <thead><tr><th>Estudiante</th><th>Asistencia</th><th>Observación</th></tr></thead>
            <tbody>
            <?php foreach ($estudiantes as $s): $sid = (int) $s['id']; $act = $delDia[$sid]['estado'] ?? 'presente'; ?>
                <tr>
                    <td><?= $e($s['nombre']) ?></td>
                    <td><select name="estado[<?= $sid ?>]" class="sel-asistencia" aria-label="Asistencia de <?= $e($s['nombre']) ?>">
                        <?php foreach (Expediente::ESTADOS_ASISTENCIA as $k => $t): ?><option value="<?= $e($k) ?>" <?= $act === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></td>
                    <td><input type="text" name="obs[<?= $sid ?>]" maxlength="150" value="<?= $e($delDia[$sid]['observacion'] ?? '') ?>" aria-label="Observación de <?= $e($s['nombre']) ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <p><button type="submit">Guardar asistencia</button></p>
    </form>
    <script>document.getElementById('todos-presentes').addEventListener('click', function () {
        Array.prototype.forEach.call(document.querySelectorAll('.sel-asistencia'), function (s) { s.value = 'presente'; });
    });</script>
</section>
<?php elseif ($asig && $modo === 'resumen'): ?>
<section class="panel-seccion">
    <h2>Resumen del período · <?= $e($asig['grado'] . ' ' . $asig['grupo'] . ' · ' . $asig['asignatura']) ?></h2>
    <p class="texto-suave">Escribe el total de clases a las que faltó cada estudiante en el período. Si cargas el resumen, el boletín usa estos números en lugar del conteo del registro diario. Déjalo vacío para volver al conteo diario.</p>
    <form action="asistencia.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="guardar_resumen"><input type="hidden" name="asig" value="<?= $asigId ?>"><input type="hidden" name="periodo" value="<?= $periodoId ?>"><input type="hidden" name="modo" value="resumen">
        <div class="acad-tabla-envoltura"><table class="acad-tabla">
            <thead><tr><th>Estudiante</th><th>Ausencias</th><th>De ellas, excusadas</th></tr></thead>
            <tbody>
            <?php foreach ($estudiantes as $s): $sid = (int) $s['id']; $g = $guardado[$sid] ?? null; ?>
                <tr><td><?= $e($s['nombre']) ?></td>
                    <td><input type="number" name="ausencias[<?= $sid ?>]" min="0" max="999" value="<?= $g ? (int) $g['ausencias'] : '' ?>" style="width:6em" aria-label="Ausencias de <?= $e($s['nombre']) ?>"></td>
                    <td><input type="number" name="excusadas[<?= $sid ?>]" min="0" max="999" value="<?= $g ? (int) $g['excusadas'] : '' ?>" style="width:6em" aria-label="Excusadas de <?= $e($s['nombre']) ?>"></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <p><button type="submit">Guardar resumen</button></p>
    </form>
</section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
