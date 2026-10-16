<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Expediente.php';

// Planeador de clases y diario de campo. Es PRIVADO del docente: nadie más lo ve desde la plataforma.
requiereRol('docente');
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$anio = Academico::anioActual($pdo, $colegioId);
$asigs = $anio ? Evaluacion::asignaciones($pdo, $colegioId, (int) $anio['id'], $usuarioId) : [];
$periodos = $anio ? Academico::periodos($pdo, (int) $anio['id']) : [];

$flash = $_SESSION['flash_planeador'] ?? null;
unset($_SESSION['flash_planeador']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    if ($accion === 'plan_guardar') {
        $err = Expediente::guardarPlaneacion($pdo, $colegioId, $usuarioId, (int) ($_POST['plan_id'] ?? 0) ?: null, $_POST);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Planeación guardada.' : $err;
    } elseif ($accion === 'plan_eliminar') {
        Expediente::eliminarPlaneacion($pdo, (int) ($_POST['plan_id'] ?? 0), $usuarioId);
        $f['exito'] = 'Planeación eliminada.';
    } elseif ($accion === 'diario_guardar') {
        $err = Expediente::guardarDiario($pdo, $colegioId, $usuarioId, $_POST);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Entrada del diario guardada.' : $err;
    } elseif ($accion === 'diario_eliminar') {
        Expediente::eliminarDiario($pdo, (int) ($_POST['diario_id'] ?? 0), $usuarioId);
        $f['exito'] = 'Entrada eliminada.';
    }
    $_SESSION['flash_planeador'] = $f;
    header('Location: planeador.php');
    exit;
}

$filtroAsig = (int) ($_GET['asig'] ?? 0);
$planes = Expediente::planeaciones($pdo, $usuarioId, $filtroAsig);
$diarios = Expediente::diarios($pdo, $usuarioId);
$editarId = (int) ($_GET['editar'] ?? 0);
$edit = null;
foreach ($planes as $p) {
    if ((int) $p['id'] === $editarId) {
        $edit = $p;
    }
}
$e = static fn ($v): string => htmlspecialchars((string) $v);
$ev = static fn (string $k): string => $e($edit[$k] ?? '');
$hoy = date('Y-m-d');

$tituloPagina = 'Planeador y diario de campo';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Planeador y diario de campo</h1>
<p class="texto-suave">Tu planeación y tus reflexiones son privadas. Puedes dictar con el botón 🎤 de cada campo.</p>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<?php if (!$asigs): ?>
    <div class="estado-vacio"><p>No tienes asignaturas asignadas en el año actual. Pídele al administrador que te asigne tus grupos en Plan académico.</p></div>
<?php else: ?>

<section class="panel-seccion" id="planeacion">
    <h2><?= $edit ? 'Editar planeación' : 'Nueva planeación' ?></h2>
    <form action="planeador.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="plan_guardar"><input type="hidden" name="plan_id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
        <div class="insc-grid">
            <div><label>Grupo y asignatura
                <select name="asignacion_id" required><option value="">Elige…</option>
                    <?php foreach ($asigs as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $edit && (int) $edit['asignacion_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= $e($a['grado'] . ' ' . $a['grupo'] . ' · ' . $a['asignatura']) ?></option><?php endforeach; ?></select></label></div>
            <div><label>Período
                <select name="periodo_id"><option value="0">—</option>
                    <?php foreach ($periodos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $edit && (int) $edit['periodo_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= $e($p['nombre']) ?></option><?php endforeach; ?></select></label></div>
            <div><label>Desde <input type="date" name="fecha_inicio" required value="<?= $edit ? $ev('fecha_inicio') : $e($hoy) ?>"></label></div>
            <div><label>Hasta (opcional) <input type="date" name="fecha_fin" value="<?= $ev('fecha_fin') ?>"></label></div>
            <div class="ancho"><label>Tema o unidad <input type="text" name="tema" maxlength="150" required value="<?= $ev('tema') ?>"></label></div>
            <div class="ancho"><label>Estándar, competencia o DBA <input type="text" name="estandar" maxlength="500" value="<?= $ev('estandar') ?>"></label></div>
            <div class="ancho"><label>Objetivos de aprendizaje <textarea name="objetivos" rows="3" maxlength="5000"><?= $ev('objetivos') ?></textarea></label></div>
            <div class="ancho"><label>Actividades (inicio, desarrollo y cierre) <textarea name="actividades" rows="5" maxlength="8000"><?= $ev('actividades') ?></textarea></label></div>
            <div><label>Recursos <input type="text" name="recursos" maxlength="500" value="<?= $ev('recursos') ?>"></label></div>
            <div><label>Evaluación <input type="text" name="evaluacion" maxlength="500" value="<?= $ev('evaluacion') ?>"></label></div>
            <div><label>Estado
                <select name="estado"><?php foreach (Expediente::ESTADOS_PLANEACION as $k => $t): ?><option value="<?= $e($k) ?>" <?= ($edit['estado'] ?? 'borrador') === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label></div>
        </div>
        <button type="submit"><?= $edit ? 'Guardar cambios' : 'Guardar planeación' ?></button>
        <?php if ($edit): ?><a class="boton-secundario" href="planeador.php">Cancelar</a><?php endif; ?>
    </form>
</section>

<section class="panel-seccion">
    <h2>Mis planeaciones (<?= count($planes) ?>)</h2>
    <form action="planeador.php" method="GET" class="acad-filtros">
        <label>Filtrar por
            <select name="asig" onchange="this.form.submit()"><option value="0">Todas mis asignaturas</option>
                <?php foreach ($asigs as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $filtroAsig === (int) $a['id'] ? 'selected' : '' ?>><?= $e($a['grado'] . ' ' . $a['grupo'] . ' · ' . $a['asignatura']) ?></option><?php endforeach; ?></select></label>
    </form>
    <?php if (!$planes): ?><div class="estado-vacio"><p>Todavía no hay planeaciones.</p></div><?php endif; ?>
    <?php foreach ($planes as $p): ?>
        <details class="anio-tarjeta">
            <summary><strong><?= $e($p['tema']) ?></strong> · <?= $e($p['fecha_inicio']) ?><?= $p['fecha_fin'] ? ' a ' . $e($p['fecha_fin']) : '' ?>
                <span class="estado-pill estado-<?= $p['estado'] === 'ejecutado' ? 'activa' : ($p['estado'] === 'listo' ? 'en_revision' : 'pendiente') ?>"><?= $e(Expediente::ESTADOS_PLANEACION[$p['estado']] ?? $p['estado']) ?></span></summary>
            <dl class="acad-detalle">
                <?php if ($p['estandar']): ?><div><dt>Estándar / competencia</dt><dd><?= $e($p['estandar']) ?></dd></div><?php endif; ?>
                <?php if ($p['objetivos']): ?><div><dt>Objetivos</dt><dd style="white-space:pre-line"><?= $e($p['objetivos']) ?></dd></div><?php endif; ?>
                <?php if ($p['actividades']): ?><div><dt>Actividades</dt><dd style="white-space:pre-line"><?= $e($p['actividades']) ?></dd></div><?php endif; ?>
                <?php if ($p['recursos']): ?><div><dt>Recursos</dt><dd><?= $e($p['recursos']) ?></dd></div><?php endif; ?>
                <?php if ($p['evaluacion']): ?><div><dt>Evaluación</dt><dd><?= $e($p['evaluacion']) ?></dd></div><?php endif; ?>
            </dl>
            <div class="acad-acciones no-print">
                <a class="boton-secundario" href="planeador.php?editar=<?= (int) $p['id'] ?>#planeacion">Editar</a>
                <form action="planeador.php" method="POST" onsubmit="return confirm('¿Eliminar esta planeación?');">
                    <?php campoCSRF(); ?><input type="hidden" name="accion" value="plan_eliminar"><input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="boton-peligro">Eliminar</button>
                </form>
            </div>
        </details>
    <?php endforeach; ?>
</section>

<section class="panel-seccion" id="diario">
    <h2>Diario de campo</h2>
    <p class="texto-suave">Registra lo que pasó en clase: qué funcionó, qué dificultades hubo y qué vas a ajustar.</p>
    <form action="planeador.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="diario_guardar">
        <div class="insc-grid">
            <div><label>Fecha <input type="date" name="fecha" required value="<?= $e($hoy) ?>" max="<?= $e($hoy) ?>"></label></div>
            <div><label>Grupo y asignatura (opcional)
                <select name="asignacion_id"><option value="0">—</option>
                    <?php foreach ($asigs as $a): ?><option value="<?= (int) $a['id'] ?>"><?= $e($a['grado'] . ' ' . $a['grupo'] . ' · ' . $a['asignatura']) ?></option><?php endforeach; ?></select></label></div>
            <div class="ancho"><label>Planeación relacionada (opcional)
                <select name="planeacion_id"><option value="0">—</option>
                    <?php foreach ($planes as $p): ?><option value="<?= (int) $p['id'] ?>"><?= $e($p['fecha_inicio'] . ' · ' . $p['tema']) ?></option><?php endforeach; ?></select></label></div>
            <div class="ancho"><label>¿Qué observaste? <textarea name="observaciones" rows="4" maxlength="8000" required></textarea></label></div>
            <div class="ancho"><label>Dificultades <textarea name="dificultades" rows="3" maxlength="5000"></textarea></label></div>
            <div class="ancho"><label>Ajustes para la próxima clase <textarea name="ajustes" rows="3" maxlength="5000"></textarea></label></div>
        </div>
        <button type="submit">Guardar entrada</button>
    </form>
    <?php foreach ($diarios as $d): ?>
        <article class="acad-credenciales" style="background:transparent;border-color:var(--color-borde)">
            <p style="margin:0"><strong><?= $e($d['fecha']) ?></strong><?= $d['asignatura'] ? ' · ' . $e($d['grado'] . ' ' . $d['grupo'] . ' · ' . $d['asignatura']) : '' ?></p>
            <p style="white-space:pre-line"><?= $e($d['observaciones']) ?></p>
            <?php if ($d['dificultades']): ?><p class="boletin-obs"><strong>Dificultades:</strong> <?= $e($d['dificultades']) ?></p><?php endif; ?>
            <?php if ($d['ajustes']): ?><p class="boletin-obs"><strong>Ajustes:</strong> <?= $e($d['ajustes']) ?></p><?php endif; ?>
            <form action="planeador.php" method="POST" onsubmit="return confirm('¿Eliminar esta entrada?');">
                <?php campoCSRF(); ?><input type="hidden" name="accion" value="diario_eliminar"><input type="hidden" name="diario_id" value="<?= (int) $d['id'] ?>">
                <button type="submit" class="boton-peligro">Eliminar</button>
            </form>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
