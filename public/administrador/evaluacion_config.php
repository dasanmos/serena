<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Evaluacion.php';

// Sistema Institucional de Evaluación del colegio: estructura de la planilla, escala (numérica o descriptiva),
// niveles de desempeño y procesos. Es adaptable: se puede cambiar cuando haga falta (las notas ya guardadas
// se recalculan al volver a guardar cada planilla).
requiereRol('administrador', 'rector', 'secretaria_academica');
$colegioId = colegioParaCrear();

$flash = $_SESSION['flash_evalconfig'] ?? null;
unset($_SESSION['flash_evalconfig']);

$num = static fn ($v): float => (float) str_replace(',', '.', (string) $v);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    $escalaId = (int) ($_POST['escala_id'] ?? 0);
    $escala = $escalaId > 0 ? Evaluacion::escala($pdo, $escalaId, $colegioId) : null;

    if ($accion === 'config') {
        $err = Evaluacion::guardarConfiguracion($pdo, $colegioId, (string) ($_POST['estructura'] ?? ''), $escalaId > 0 ? $escalaId : null);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Configuración guardada.' : $err;
        if ($err === '') {
            auditarAccion($pdo, 'configurar_evaluacion', 'configuracion_evaluacion', $colegioId, [], ['estructura' => (string) ($_POST['estructura'] ?? ''), 'escala_id' => $escalaId]);
        }
    } elseif ($accion === 'escala_predeterminada') {
        $id = Evaluacion::crearEscalaPredeterminada($pdo, $colegioId, (string) ($_POST['tipo'] ?? 'numerica'));
        $f['exito'] = 'Escala creada con los cuatro desempeños nacionales. Ajusta los rangos si tu SIEE usa otros y elígela como escala en uso.';
    } elseif ($accion === 'escala_nueva') {
        [$id, $err] = Evaluacion::crearEscala($pdo, $colegioId, (string) ($_POST['nombre'] ?? ''), (string) ($_POST['tipo'] ?? ''), $num($_POST['valor_min'] ?? 1), $num($_POST['valor_max'] ?? 5), (int) ($_POST['decimales'] ?? 1));
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Escala creada. Agrega sus niveles de desempeño.' : $err;
    } elseif ($accion === 'escala_eliminar') {
        $err = Evaluacion::eliminarEscala($pdo, $escalaId, $colegioId);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Escala eliminada.' : $err;
    } elseif ($accion === 'nivel_guardar' && $escala !== null) {
        $err = Evaluacion::guardarNivel($pdo, $escalaId, (int) ($_POST['nivel_id'] ?? 0) ?: null, (string) ($_POST['nombre'] ?? ''), (string) ($_POST['abreviatura'] ?? ''),
            $num($_POST['desde'] ?? 0), $num($_POST['hasta'] ?? 0), $num($_POST['valor_equivalente'] ?? 0), isset($_POST['aprueba']), (int) ($_POST['orden'] ?? 1));
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Nivel guardado.' : $err;
    } elseif ($accion === 'nivel_eliminar' && $escala !== null) {
        Evaluacion::eliminarNivel($pdo, (int) ($_POST['nivel_id'] ?? 0), $escalaId);
        $f['exito'] = 'Nivel eliminado.';
    } elseif ($accion === 'proceso_guardar') {
        $err = Evaluacion::guardarComponente($pdo, $colegioId, (int) ($_POST['componente_id'] ?? 0) ?: null, (string) ($_POST['nombre'] ?? ''), $num($_POST['peso'] ?? 0), (int) ($_POST['orden'] ?? 1));
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Proceso guardado.' : $err;
    } elseif ($accion === 'proceso_eliminar') {
        Evaluacion::eliminarComponente($pdo, (int) ($_POST['componente_id'] ?? 0), $colegioId);
        $f['exito'] = 'Proceso eliminado.';
    } elseif ($accion === 'procesos_predeterminados') {
        Evaluacion::procesosPredeterminados($pdo, $colegioId);
        $f['exito'] = 'Procesos creados: cognitivo 40 %, procedimental 30 %, actitudinal 30 %.';
    }
    $_SESSION['flash_evalconfig'] = $f;
    header('Location: evaluacion_config.php' . ($escalaId > 0 ? '?escala=' . $escalaId : ''));
    exit;
}

$config = Evaluacion::configuracion($pdo, $colegioId);
$escalas = Evaluacion::escalas($pdo, $colegioId);
$escalaVer = (int) ($_GET['escala'] ?? ($config['escala_id'] ?? 0));
$escalaDetalle = $escalaVer > 0 ? Evaluacion::escala($pdo, $escalaVer, $colegioId) : null;
$componentes = Evaluacion::componentes($pdo, $colegioId);
$sumaProcesos = array_sum(array_map(static fn (array $c): float => (float) $c['peso'], $componentes));
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Sistema de evaluación';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Sistema institucional de evaluación</h1>
<p class="texto-suave">Define cómo se califica en tu colegio. Es adaptable: puedes cambiarlo cuando lo necesites.</p>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<section class="panel-seccion">
    <h2>1. Estructura de la planilla y escala en uso</h2>
    <form action="evaluacion_config.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="config">
        <?php foreach (Evaluacion::ESTRUCTURAS as $clave => [$titulo, $descr]): ?>
            <label class="opcion-consentimiento">
                <input type="radio" name="estructura" value="<?= $e($clave) ?>" <?= $config['estructura'] === $clave ? 'checked' : '' ?>>
                <span><strong><?= $e($titulo) ?>.</strong> <?= $e($descr) ?></span>
            </label>
        <?php endforeach; ?>
        <label>Escala en uso
            <select name="escala_id">
                <option value="0">— Sin escala (no se podrá calificar) —</option>
                <?php foreach ($escalas as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $config['escala_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= $e($s['nombre']) ?> (<?= $s['tipo'] === 'descriptiva' ? 'descriptiva' : 'numérica' ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="texto-suave">Si cambias la estructura con notas ya cargadas, cada planilla se recalcula al guardarla de nuevo.</p>
        <button type="submit">Guardar</button>
    </form>
</section>

<section class="panel-seccion">
    <h2>2. Escalas y niveles de desempeño</h2>
    <p class="texto-suave">
        <strong>Numérica:</strong> el docente escribe un número (p. ej. 1.0 a 5.0) y Serena calcula el desempeño por rangos.
        <strong>Descriptiva:</strong> el docente elige el desempeño (Superior, Alto, Básico, Bajo) y no se ven números.
    </p>
    <div class="acad-acciones">
        <form action="evaluacion_config.php" method="POST"><?php campoCSRF(); ?><input type="hidden" name="accion" value="escala_predeterminada"><input type="hidden" name="tipo" value="numerica"><button type="submit" class="boton-secundario">+ Escala numérica 1.0–5.0 con 4 desempeños</button></form>
        <form action="evaluacion_config.php" method="POST"><?php campoCSRF(); ?><input type="hidden" name="accion" value="escala_predeterminada"><input type="hidden" name="tipo" value="descriptiva"><button type="submit" class="boton-secundario">+ Escala descriptiva con 4 desempeños</button></form>
    </div>
    <details>
        <summary>Crear una escala personalizada (otro rango, p. ej. 0 a 100)</summary>
        <form action="evaluacion_config.php" method="POST" class="acad-filtros" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="escala_nueva">
            <label>Nombre <input type="text" name="nombre" maxlength="60" required></label>
            <label>Tipo <select name="tipo"><option value="numerica">Numérica</option><option value="descriptiva">Descriptiva</option></select></label>
            <label>Mínimo <input type="number" name="valor_min" step="0.01" value="0" style="width:6em"></label>
            <label>Máximo <input type="number" name="valor_max" step="0.01" value="100" style="width:6em"></label>
            <label>Decimales <input type="number" name="decimales" min="0" max="2" value="0" style="width:5em"></label>
            <button type="submit">Crear escala</button>
        </form>
    </details>

    <?php if ($escalas): ?>
    <form action="evaluacion_config.php" method="GET" class="acad-filtros">
        <label>Ver y editar los niveles de
            <select name="escala" onchange="this.form.submit()">
                <option value="0">Elige una escala…</option>
                <?php foreach ($escalas as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $escalaVer === (int) $s['id'] ? 'selected' : '' ?>><?= $e($s['nombre']) ?></option><?php endforeach; ?>
            </select>
        </label>
    </form>
    <?php endif; ?>

    <?php if ($escalaDetalle): ?>
        <h3><?= $e($escalaDetalle['nombre']) ?> — <?= $escalaDetalle['tipo'] === 'descriptiva' ? 'descriptiva' : 'numérica' ?>, de <?= $e($escalaDetalle['valor_min']) ?> a <?= $e($escalaDetalle['valor_max']) ?></h3>
        <?php foreach ($escalaDetalle['niveles'] as $n): ?>
            <div class="acad-acciones">
                <form action="evaluacion_config.php" method="POST" novalidate>
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="nivel_guardar"><input type="hidden" name="escala_id" value="<?= (int) $escalaDetalle['id'] ?>"><input type="hidden" name="nivel_id" value="<?= (int) $n['id'] ?>">
                    <label>Orden <input type="number" name="orden" min="1" max="20" value="<?= (int) $n['orden'] ?>" style="width:4.5em"></label>
                    <label>Nombre <input type="text" name="nombre" maxlength="40" value="<?= $e($n['nombre']) ?>"></label>
                    <label>Abrev. <input type="text" name="abreviatura" maxlength="6" value="<?= $e($n['abreviatura']) ?>" style="width:5em"></label>
                    <label>Desde <input type="number" name="desde" step="0.01" value="<?= $e($n['desde']) ?>" style="width:6em"></label>
                    <label>Hasta <input type="number" name="hasta" step="0.01" value="<?= $e($n['hasta']) ?>" style="width:6em"></label>
                    <label>Valor equiv. <input type="number" name="valor_equivalente" step="0.01" value="<?= $e($n['valor_equivalente']) ?>" style="width:6em"></label>
                    <label><input type="checkbox" name="aprueba" <?= (int) $n['aprueba'] === 1 ? 'checked' : '' ?>> Aprueba</label>
                    <button type="submit">Guardar</button>
                </form>
                <form action="evaluacion_config.php" method="POST" onsubmit="return confirm('¿Eliminar este nivel?');">
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="nivel_eliminar"><input type="hidden" name="escala_id" value="<?= (int) $escalaDetalle['id'] ?>"><input type="hidden" name="nivel_id" value="<?= (int) $n['id'] ?>">
                    <button type="submit" class="boton-peligro">Quitar</button>
                </form>
            </div>
        <?php endforeach; ?>
        <details>
            <summary>Agregar un nivel</summary>
            <form action="evaluacion_config.php" method="POST" class="acad-filtros" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="nivel_guardar"><input type="hidden" name="escala_id" value="<?= (int) $escalaDetalle['id'] ?>">
                <label>Orden <input type="number" name="orden" min="1" max="20" value="<?= count($escalaDetalle['niveles']) + 1 ?>" style="width:4.5em"></label>
                <label>Nombre <input type="text" name="nombre" maxlength="40" required></label>
                <label>Abrev. <input type="text" name="abreviatura" maxlength="6" required style="width:5em"></label>
                <label>Desde <input type="number" name="desde" step="0.01" required style="width:6em"></label>
                <label>Hasta <input type="number" name="hasta" step="0.01" required style="width:6em"></label>
                <label>Valor equiv. <input type="number" name="valor_equivalente" step="0.01" required style="width:6em"></label>
                <label><input type="checkbox" name="aprueba" checked> Aprueba</label>
                <button type="submit">Agregar</button>
            </form>
        </details>
        <p class="texto-suave">El «valor equivalente» se usa para promediar en escalas descriptivas (p. ej. Alto = 4.2). Los rangos «desde–hasta» definen qué desempeño corresponde a cada nota.</p>
        <form action="evaluacion_config.php" method="POST" onsubmit="return confirm('¿Eliminar esta escala?');">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="escala_eliminar"><input type="hidden" name="escala_id" value="<?= (int) $escalaDetalle['id'] ?>">
            <button type="submit" class="boton-peligro">Eliminar esta escala</button>
        </form>
    <?php endif; ?>
</section>

<section class="panel-seccion">
    <h2>3. Procesos (solo para estructuras con procesos)</h2>
    <p class="texto-suave">Dimensiones de la evaluación con su peso institucional, por ejemplo cognitivo, procedimental y actitudinal.</p>
    <?php if (!$componentes): ?>
        <form action="evaluacion_config.php" method="POST"><?php campoCSRF(); ?><input type="hidden" name="accion" value="procesos_predeterminados"><button type="submit" class="boton-secundario">Crear cognitivo 40 % · procedimental 30 % · actitudinal 30 %</button></form>
    <?php endif; ?>
    <?php foreach ($componentes as $c): ?>
        <div class="acad-acciones">
            <form action="evaluacion_config.php" method="POST" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="proceso_guardar"><input type="hidden" name="componente_id" value="<?= (int) $c['id'] ?>">
                <label>Orden <input type="number" name="orden" min="1" max="20" value="<?= (int) $c['orden'] ?>" style="width:4.5em"></label>
                <label>Nombre <input type="text" name="nombre" maxlength="60" value="<?= $e($c['nombre']) ?>"></label>
                <label>Peso % <input type="number" name="peso" min="0" max="100" step="0.01" value="<?= $e($c['peso']) ?>" style="width:6em"></label>
                <button type="submit">Guardar</button>
            </form>
            <form action="evaluacion_config.php" method="POST" onsubmit="return confirm('¿Eliminar este proceso? Las actividades que lo usaban quedarán sin proceso.');">
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="proceso_eliminar"><input type="hidden" name="componente_id" value="<?= (int) $c['id'] ?>">
                <button type="submit" class="boton-peligro">Quitar</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if ($componentes && abs($sumaProcesos - 100.0) > 0.01): ?>
        <p class="mensaje mensaje-aviso">Los pesos de los procesos suman <?= $e(rtrim(rtrim(number_format($sumaProcesos, 2, '.', ''), '0'), '.')) ?> %; deberían sumar 100 %.</p>
    <?php endif; ?>
    <details>
        <summary>Agregar un proceso</summary>
        <form action="evaluacion_config.php" method="POST" class="acad-filtros" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="proceso_guardar">
            <label>Orden <input type="number" name="orden" min="1" max="20" value="<?= count($componentes) + 1 ?>" style="width:4.5em"></label>
            <label>Nombre <input type="text" name="nombre" maxlength="60" required></label>
            <label>Peso % <input type="number" name="peso" min="0" max="100" step="0.01" value="0" style="width:6em"></label>
            <button type="submit">Agregar</button>
        </form>
    </details>
</section>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
