<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/modelos/RolesUsuario.php';
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Evaluacion.php';

// Plan académico: asignaturas del colegio y asignación (docente + asignatura + grupo) por año lectivo.
requiereRol('administrador', 'rector', 'secretaria_academica');
$colegioId = colegioParaCrear();

$anios = Academico::anios($pdo, $colegioId);
$anioSel = (int) ($_GET['anio'] ?? $_POST['anio'] ?? 0);
if ($anioSel === 0) {
    $actual = Academico::anioActual($pdo, $colegioId);
    $anioSel = $actual ? (int) $actual['id'] : ($anios ? (int) $anios[0]['id'] : 0);
}
$anio = $anioSel > 0 ? Academico::anio($pdo, $anioSel, $colegioId) : null;

$flash = $_SESSION['flash_plan'] ?? null;
unset($_SESSION['flash_plan']);

$docq = $pdo->prepare("SELECT u.id, u.nombre FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                        WHERE (r.nombre = 'docente'" . RolesUsuario::sqlAdicional($pdo, 'u', 'docente') . ") AND u.colegio_id = ? AND u.activo = 1 ORDER BY u.nombre");
$docq->execute([$colegioId]);
$docentes = $docq->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    $docId = (int) ($_POST['docente_id'] ?? 0) ?: null;

    if ($accion === 'asignatura_guardar') {
        $err = Evaluacion::guardarAsignatura($pdo, $colegioId, (int) ($_POST['asignatura_id'] ?? 0) ?: null, (string) ($_POST['nombre'] ?? ''), (string) ($_POST['area'] ?? ''),
            ($_POST['intensidad'] ?? '') !== '' ? (int) $_POST['intensidad'] : null, isset($_POST['activa']));
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Asignatura guardada.' : $err;
    } elseif ($accion === 'asignatura_eliminar') {
        $err = Evaluacion::eliminarAsignatura($pdo, (int) ($_POST['asignatura_id'] ?? 0), $colegioId);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Asignatura eliminada.' : $err;
    } elseif ($anio === null) {
        $f['error'] = 'Elige un año lectivo.';
    } elseif ($accion === 'asignar_grupo') {
        $err = Evaluacion::crearAsignacion($pdo, $colegioId, $anioSel, (int) ($_POST['grupo_id'] ?? 0), (int) ($_POST['asignatura_id'] ?? 0), $docId);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Asignación creada.' : $err;
    } elseif ($accion === 'asignar_grado') {
        $n = Evaluacion::asignarAGrado($pdo, $colegioId, $anioSel, (int) ($_POST['grado_id'] ?? 0), (int) ($_POST['asignatura_id'] ?? 0), $docId);
        $f[$n > 0 ? 'exito' : 'error'] = $n > 0 ? "Se asignó a {$n} grupo(s) del grado." : 'No se creó ninguna (revisa el grado y la asignatura, o ya estaban asignadas).';
    } elseif ($accion === 'cambiar_docente') {
        $err = Evaluacion::cambiarDocente($pdo, (int) ($_POST['asignacion_id'] ?? 0), $colegioId, $docId);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Docente actualizado.' : $err;
        if ($err === '') {
            auditarAccion($pdo, 'cambiar_docente_asignacion', 'asignaciones_academicas', (int) ($_POST['asignacion_id'] ?? 0), [], ['docente_id' => $docId]);
        }
    } elseif ($accion === 'asignacion_eliminar') {
        $err = Evaluacion::eliminarAsignacion($pdo, (int) ($_POST['asignacion_id'] ?? 0), $colegioId);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Asignación eliminada.' : $err;
    }
    $_SESSION['flash_plan'] = $f;
    header('Location: plan_academico.php?anio=' . $anioSel);
    exit;
}

$asignaturas = Evaluacion::asignaturas($pdo, $colegioId);
$grados = Academico::grados($pdo, $colegioId);
$grupos = Academico::grupos($pdo, $colegioId);
$asignaciones = $anio ? Evaluacion::asignaciones($pdo, $colegioId, $anioSel) : [];
$e = static fn ($v): string => htmlspecialchars((string) $v);
$opcionesDocente = static function (?int $sel) use ($docentes, $e): string {
    $h = '<option value="0">— Sin docente —</option>';
    foreach ($docentes as $d) {
        $h .= '<option value="' . (int) $d['id'] . '"' . ($sel === (int) $d['id'] ? ' selected' : '') . '>' . $e($d['nombre']) . '</option>';
    }
    return $h;
};

$tituloPagina = 'Plan académico';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Plan académico</h1>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<section class="panel-seccion">
    <h2>1. Asignaturas</h2>
    <p class="texto-suave">La <strong>intensidad</strong> (horas por semana) es opcional: si todas las asignaturas la tienen, pondera el promedio general del boletín.</p>
    <?php foreach ($asignaturas as $s): ?>
        <div class="acad-acciones">
            <form action="plan_academico.php" method="POST" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="asignatura_guardar"><input type="hidden" name="asignatura_id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="anio" value="<?= $anioSel ?>">
                <label>Nombre <input type="text" name="nombre" maxlength="80" value="<?= $e($s['nombre']) ?>"></label>
                <label>Área <input type="text" name="area" maxlength="80" value="<?= $e($s['area']) ?>"></label>
                <label>Horas/sem. <input type="number" name="intensidad" min="1" max="40" value="<?= $e($s['intensidad']) ?>" style="width:5em"></label>
                <label><input type="checkbox" name="activa" <?= (int) $s['activa'] === 1 ? 'checked' : '' ?>> Activa</label>
                <button type="submit">Guardar</button>
            </form>
            <form action="plan_academico.php" method="POST" onsubmit="return confirm('¿Eliminar esta asignatura?');">
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="asignatura_eliminar"><input type="hidden" name="asignatura_id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="anio" value="<?= $anioSel ?>">
                <button type="submit" class="boton-peligro">Quitar</button>
            </form>
        </div>
    <?php endforeach; ?>
    <details <?= $asignaturas ? '' : 'open' ?>>
        <summary>Agregar una asignatura</summary>
        <form action="plan_academico.php" method="POST" class="acad-filtros" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="asignatura_guardar"><input type="hidden" name="anio" value="<?= $anioSel ?>"><input type="hidden" name="activa" value="1">
            <label>Nombre <input type="text" name="nombre" maxlength="80" required></label>
            <label>Área <input type="text" name="area" maxlength="80" placeholder="Ej.: Humanidades"></label>
            <label>Horas/sem. <input type="number" name="intensidad" min="1" max="40" style="width:5em"></label>
            <button type="submit">Agregar</button>
        </form>
    </details>
</section>

<section class="panel-seccion">
    <h2>2. Asignación académica por año</h2>
    <?php if (!$anios): ?>
        <p class="mensaje mensaje-aviso">Primero crea un año lectivo en <a href="anios_lectivos.php">Años lectivos</a>.</p>
    <?php else: ?>
    <form action="plan_academico.php" method="GET" class="acad-filtros">
        <label>Año lectivo
            <select name="anio" onchange="this.form.submit()">
                <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $anioSel === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?><?= (int) $a['actual'] === 1 ? ' (actual)' : '' ?></option><?php endforeach; ?>
            </select>
        </label>
    </form>

    <div class="terr-importar" style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
        <form action="plan_academico.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="asignar_grado"><input type="hidden" name="anio" value="<?= $anioSel ?>">
            <h3>A todos los grupos de un grado</h3>
            <label>Grado <select name="grado_id" required><option value="">Elige…</option><?php foreach ($grados as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $e($g['nombre']) ?></option><?php endforeach; ?></select></label>
            <label>Asignatura <select name="asignatura_id" required><option value="">Elige…</option><?php foreach ($asignaturas as $s): if ((int) $s['activa'] === 1): ?><option value="<?= (int) $s['id'] ?>"><?= $e($s['nombre']) ?></option><?php endif; endforeach; ?></select></label>
            <label>Docente <select name="docente_id"><?= $opcionesDocente(null) ?></select></label>
            <button type="submit">Asignar a todo el grado</button>
        </form>
        <form action="plan_academico.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="asignar_grupo"><input type="hidden" name="anio" value="<?= $anioSel ?>">
            <h3>A un grupo</h3>
            <label>Grupo <select name="grupo_id" required><option value="">Elige…</option><?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?></select></label>
            <label>Asignatura <select name="asignatura_id" required><option value="">Elige…</option><?php foreach ($asignaturas as $s): if ((int) $s['activa'] === 1): ?><option value="<?= (int) $s['id'] ?>"><?= $e($s['nombre']) ?></option><?php endif; endforeach; ?></select></label>
            <label>Docente <select name="docente_id"><?= $opcionesDocente(null) ?></select></label>
            <button type="submit">Asignar al grupo</button>
        </form>
    </div>

    <h3>Asignaciones del <?= (int) ($anio['anio'] ?? 0) ?> (<?= count($asignaciones) ?>)</h3>
    <?php if (!$asignaciones): ?><div class="estado-vacio"><p>Todavía no hay asignaciones en este año.</p></div><?php else: ?>
    <div class="acad-lista">
        <?php foreach ($asignaciones as $a): ?>
            <div class="acad-fila">
                <div class="acad-fila-info"><strong><?= $e($a['grado'] . ' ' . $a['grupo']) ?> · <?= $e($a['asignatura']) ?></strong>
                    <small><?= (int) $a['n_actividades'] ?> actividad(es) en planillas</small></div>
                <form action="plan_academico.php" method="POST" class="acad-acciones" novalidate>
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="cambiar_docente"><input type="hidden" name="asignacion_id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="anio" value="<?= $anioSel ?>">
                    <label>Docente <select name="docente_id"><?= $opcionesDocente($a['docente_id'] !== null ? (int) $a['docente_id'] : null) ?></select></label>
                    <button type="submit">Guardar</button>
                </form>
                <form action="plan_academico.php" method="POST" onsubmit="return confirm('¿Eliminar esta asignación?');">
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="asignacion_eliminar"><input type="hidden" name="asignacion_id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="anio" value="<?= $anioSel ?>">
                    <button type="submit" class="boton-peligro">Quitar</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
