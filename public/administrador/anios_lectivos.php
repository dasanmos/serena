<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Academico.php';

// Años lectivos y períodos del colegio. Lo usan administración, rectoría y la secretaría académica.
requiereRol('administrador', 'rector', 'secretaria_academica');
$colegioId = colegioParaCrear();

$flash = $_SESSION['flash_anios'] ?? null;
unset($_SESSION['flash_anios']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    $anioId = (int) ($_POST['anio_id'] ?? 0);
    $anio = $anioId > 0 ? Academico::anio($pdo, $anioId, $colegioId) : null;

    if ($accion === 'crear_anio') {
        [$id, $err] = Academico::crearAnio($pdo, $colegioId, (int) ($_POST['anio'] ?? 0), (string) ($_POST['nombre'] ?? ''), $_POST['fecha_inicio'] ?? null, $_POST['fecha_fin'] ?? null);
        if ($err === '') {
            $f['exito'] = 'Año lectivo creado. Ahora define sus períodos.';
            auditarAccion($pdo, 'crear_anio_lectivo', 'anios_lectivos', $id, [], ['anio' => (int) ($_POST['anio'] ?? 0)]);
        } else {
            $f['error'] = $err;
        }
    } elseif ($anio === null) {
        $f['error'] = 'El año lectivo no existe.';
    } elseif ($accion === 'guardar_anio') {
        $err = Academico::actualizarAnio($pdo, $anioId, $colegioId, (string) ($_POST['nombre'] ?? ''), $_POST['fecha_inicio'] ?? null, $_POST['fecha_fin'] ?? null,
            isset($_POST['inscripciones_abiertas']), isset($_POST['renovacion_abierta']));
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Año lectivo guardado.' : $err;
        if ($err === '') {
            auditarAccion($pdo, 'editar_anio_lectivo', 'anios_lectivos', $anioId, [], ['inscripciones' => isset($_POST['inscripciones_abiertas']), 'renovacion' => isset($_POST['renovacion_abierta'])]);
        }
    } elseif ($accion === 'marcar_actual') {
        $n = Academico::marcarActual($pdo, $anioId, $colegioId);
        $f['exito'] = "El año {$anio['anio']} es ahora el año actual." . ($n > 0 ? " {$n} estudiante(s) pasaron a su grupo nuevo." : '');
        auditarAccion($pdo, 'marcar_anio_actual', 'anios_lectivos', $anioId, [], ['estudiantes_movidos' => $n]);
    } elseif ($accion === 'eliminar_anio') {
        $err = Academico::eliminarAnio($pdo, $anioId, $colegioId);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Año lectivo eliminado.' : $err;
        if ($err === '') {
            auditarAccion($pdo, 'eliminar_anio_lectivo', 'anios_lectivos', $anioId, ['anio' => $anio['anio']], []);
        }
    } elseif ($accion === 'generar_periodos') {
        $err = Academico::generarPeriodos($pdo, $anio, (int) ($_POST['cantidad'] ?? 0));
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Períodos generados. Puedes ajustar fechas y pesos.' : $err;
    } elseif ($accion === 'guardar_periodo') {
        $err = Academico::guardarPeriodo($pdo, $anio, (int) ($_POST['numero'] ?? 0), (string) ($_POST['nombre'] ?? ''),
            (string) ($_POST['fecha_inicio'] ?? ''), (string) ($_POST['fecha_fin'] ?? ''), (float) str_replace(',', '.', (string) ($_POST['peso'] ?? '0')));
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Período guardado.' : $err;
    } elseif ($accion === 'eliminar_periodo') {
        Academico::eliminarPeriodo($pdo, (int) ($_POST['periodo_id'] ?? 0), $anioId);
        $f['exito'] = 'Período eliminado.';
    }
    $_SESSION['flash_anios'] = $f;
    header('Location: anios_lectivos.php');
    exit;
}

$anios = Academico::anios($pdo, $colegioId);
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Años lectivos';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Años lectivos y períodos</h1>
<p class="texto-suave">
    Abre las <strong>inscripciones</strong> (formulario público) y la <strong>renovación de matrícula</strong> (para los acudientes) de cada año.
    El año <strong>actual</strong> es el que está en curso: al marcarlo, los estudiantes con matrícula activa y grupo asignado pasan a ese grupo.
    Formulario de inscripción para compartir: <a href="<?= $e(BASE_URL) ?>/inscripcion.php" target="_blank" rel="noopener"><?= $e(BASE_URL) ?>/inscripcion.php</a>
</p>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<section class="panel-seccion">
    <h2>Nuevo año lectivo</h2>
    <form action="anios_lectivos.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="crear_anio">
        <div class="acad-filtros">
            <label>Año <input type="number" name="anio" min="2000" max="2100" value="<?= (int) date('Y') + 1 ?>" required></label>
            <label>Nombre (opcional) <input type="text" name="nombre" maxlength="60" placeholder="Año lectivo <?= (int) date('Y') + 1 ?>"></label>
            <label>Inicio <input type="date" name="fecha_inicio"></label>
            <label>Fin <input type="date" name="fecha_fin"></label>
            <button type="submit">Crear año</button>
        </div>
    </form>
</section>

<?php if (!$anios): ?>
    <p class="texto-suave">Todavía no hay años lectivos. Crea el primero arriba.</p>
<?php endif; ?>

<?php foreach ($anios as $a): ?>
    <?php $periodos = Academico::periodos($pdo, (int) $a['id']); $sumaPesos = array_sum(array_map(static fn (array $p): float => (float) $p['peso'], $periodos)); ?>
    <section class="anio-tarjeta <?= (int) $a['actual'] === 1 ? 'anio-actual' : '' ?>">
        <h2>
            <?= $e($a['nombre']) ?> <span class="texto-suave">(<?= (int) $a['anio'] ?>)</span>
            <?php if ((int) $a['actual'] === 1): ?><span class="estado-pill estado-activa">Año actual</span><?php endif; ?>
        </h2>
        <p class="texto-suave"><?= (int) $a['n_inscripciones'] ?> inscripción(es) · <?= (int) $a['n_matriculas'] ?> matrícula(s) · <?= (int) $a['n_periodos'] ?> período(s)</p>

        <form action="anios_lectivos.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="guardar_anio">
            <input type="hidden" name="anio_id" value="<?= (int) $a['id'] ?>">
            <div class="acad-filtros">
                <label>Nombre <input type="text" name="nombre" maxlength="60" value="<?= $e($a['nombre']) ?>"></label>
                <label>Inicio <input type="date" name="fecha_inicio" value="<?= $e($a['fecha_inicio']) ?>"></label>
                <label>Fin <input type="date" name="fecha_fin" value="<?= $e($a['fecha_fin']) ?>"></label>
            </div>
            <div class="anio-flags">
                <label><input type="checkbox" name="inscripciones_abiertas" <?= (int) $a['inscripciones_abiertas'] === 1 ? 'checked' : '' ?>> Inscripciones web abiertas</label>
                <label><input type="checkbox" name="renovacion_abierta" <?= (int) $a['renovacion_abierta'] === 1 ? 'checked' : '' ?>> Renovación de matrícula abierta (acudientes)</label>
            </div>
            <button type="submit">Guardar año</button>
        </form>

        <div class="acad-acciones">
            <?php if ((int) $a['actual'] !== 1): ?>
                <form action="anios_lectivos.php" method="POST" onsubmit="return confirm('¿Marcar este año como el actual? Los estudiantes con matrícula activa y grupo asignado pasarán a ese grupo.');">
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="marcar_actual"><input type="hidden" name="anio_id" value="<?= (int) $a['id'] ?>">
                    <button type="submit" class="boton-secundario">Marcar como año actual</button>
                </form>
                <form action="anios_lectivos.php" method="POST" onsubmit="return confirm('¿Eliminar este año lectivo?');">
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="eliminar_anio"><input type="hidden" name="anio_id" value="<?= (int) $a['id'] ?>">
                    <button type="submit" class="boton-peligro">Eliminar año</button>
                </form>
            <?php endif; ?>
        </div>

        <h3>Períodos</h3>
        <?php if (!$periodos): ?>
            <form action="anios_lectivos.php" method="POST" class="acad-filtros" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="generar_periodos"><input type="hidden" name="anio_id" value="<?= (int) $a['id'] ?>">
                <label>Repartir el año en <input type="number" name="cantidad" min="1" max="12" value="4" style="width:5em"> períodos iguales</label>
                <button type="submit" class="boton-secundario">Generar períodos</button>
                <?php if (!$a['fecha_inicio'] || !$a['fecha_fin']): ?><span class="texto-suave">(primero guarda las fechas de inicio y fin del año)</span><?php endif; ?>
            </form>
        <?php else: ?>
            <?php foreach ($periodos as $p): ?>
                <div class="acad-acciones">
                    <form action="anios_lectivos.php" method="POST" novalidate>
                        <?php campoCSRF(); ?>
                        <input type="hidden" name="accion" value="guardar_periodo"><input type="hidden" name="anio_id" value="<?= (int) $a['id'] ?>">
                        <input type="hidden" name="numero" value="<?= (int) $p['numero'] ?>">
                        <strong style="align-self:center">N.º <?= (int) $p['numero'] ?></strong>
                        <label>Nombre <input type="text" name="nombre" maxlength="40" value="<?= $e($p['nombre']) ?>"></label>
                        <label>Inicio <input type="date" name="fecha_inicio" value="<?= $e($p['fecha_inicio']) ?>"></label>
                        <label>Fin <input type="date" name="fecha_fin" value="<?= $e($p['fecha_fin']) ?>"></label>
                        <label>Peso % <input type="number" name="peso" min="0" max="100" step="0.01" value="<?= $e($p['peso']) ?>" style="width:6em"></label>
                        <button type="submit">Guardar</button>
                    </form>
                    <form action="anios_lectivos.php" method="POST" onsubmit="return confirm('¿Eliminar este período?');">
                        <?php campoCSRF(); ?>
                        <input type="hidden" name="accion" value="eliminar_periodo"><input type="hidden" name="anio_id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="periodo_id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" class="boton-peligro">Quitar</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (abs($sumaPesos - 100.0) > 0.01): ?>
                <p class="mensaje mensaje-aviso">Los pesos suman <?= $e(rtrim(rtrim(number_format($sumaPesos, 2, '.', ''), '0'), '.')) ?> %. Para calcular notas finales deberían sumar 100 %.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php $siguiente = count($periodos) + 1; ?>
        <?php if ($siguiente <= 12): ?>
        <details>
            <summary>Agregar un período</summary>
            <form action="anios_lectivos.php" method="POST" class="acad-filtros" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="guardar_periodo"><input type="hidden" name="anio_id" value="<?= (int) $a['id'] ?>">
                <label>N.º <input type="number" name="numero" min="1" max="12" value="<?= $siguiente ?>" style="width:5em"></label>
                <label>Nombre <input type="text" name="nombre" maxlength="40" value="Período <?= $siguiente ?>"></label>
                <label>Inicio <input type="date" name="fecha_inicio" required></label>
                <label>Fin <input type="date" name="fecha_fin" required></label>
                <label>Peso % <input type="number" name="peso" min="0" max="100" step="0.01" value="25" style="width:6em"></label>
                <button type="submit">Agregar</button>
            </form>
        </details>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
