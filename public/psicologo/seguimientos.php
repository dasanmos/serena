<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/PlanSeguimiento.php';
requiereRol('psicologo', 'orientador');

// Vista consolidada de todas las tareas de plan de seguimiento del profesional,
// en todos sus estudiantes — para no tener que abrir ficha por ficha para saber
// qué falta por hacer. El detalle por estudiante sigue en estudiante_detalle.php
// (ahí es donde se crean); aquí solo se marcan estados o se elimina.
$autorId = (int) $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accionPlan = $_POST['accion_plan'] ?? '';
    if ($accionPlan === 'estado' && in_array($_POST['estado'] ?? '', ['pendiente', 'en_curso', 'completado'], true)) {
        PlanSeguimiento::actualizarEstado($pdo, (int) ($_POST['tarea_id'] ?? 0), $autorId, $_POST['estado']);
    } elseif ($accionPlan === 'eliminar') {
        PlanSeguimiento::eliminar($pdo, (int) ($_POST['tarea_id'] ?? 0), $autorId);
    }
    header('Location: seguimientos.php');
    exit;
}

$todas = PlanSeguimiento::listarDeAutor($pdo, $autorId);
$hoy = date('Y-m-d');
$vencidas = [];
$pendientes = [];
$completadas = [];
foreach ($todas as $t) {
    if ($t['estado'] === 'completado') {
        $completadas[] = $t;
    } elseif ($t['fecha_limite'] !== null && $t['fecha_limite'] < $hoy) {
        $vencidas[] = $t;
    } else {
        $pendientes[] = $t;
    }
}

$tituloPagina = 'Mis seguimientos';
require __DIR__ . '/../parciales/encabezado.php';

// Cada grupo de tareas se dibuja igual — evita repetir el mismo bloque de HTML
// tres veces (vencidas / pendientes / completadas). El enlace siempre va a
// estudiante_detalle.php de ESTE mismo directorio (psicologo/): esa página ya
// atiende tanto a psicólogo como a orientador (con su propio chequeo de grupo),
// igual que orientador/estudiante_detalle.php simplemente redirige aquí.
$dibujarTarea = function (array $t): void {
    $vencida = $t['estado'] !== 'completado' && $t['fecha_limite'] !== null && $t['fecha_limite'] < date('Y-m-d');
    ?>
    <li class="tarea-seguimiento tarea-<?= $t['estado'] ?><?= $vencida ? ' tarea-vencida' : '' ?>">
      <div class="info-tarea-seguimiento">
        <a href="estudiante_detalle.php?id=<?= $t['estudiante_id'] ?>#seguimiento" class="estudiante-tarea-seguimiento"><?= htmlspecialchars($t['estudiante_nombre']) ?></a>
        <strong><?= htmlspecialchars($t['titulo']) ?></strong>
        <span class="pill pill-estado-<?= $t['estado'] ?>">
          <?= ['pendiente' => 'Pendiente', 'en_curso' => 'En curso', 'completado' => 'Completada'][$t['estado']] ?>
        </span>
        <?php if ($t['fecha_limite']): ?>
          <span class="texto-suave">Fecha límite: <?= date('d/m/Y', strtotime($t['fecha_limite'])) ?></span>
        <?php endif; ?>
        <?php if ($t['descripcion']): ?><p><?= nl2br(htmlspecialchars($t['descripcion'])) ?></p><?php endif; ?>
      </div>
      <div class="acciones-tarea-seguimiento">
        <?php if ($t['estado'] !== 'en_curso' && $t['estado'] !== 'completado'): ?>
        <form action="seguimientos.php" method="POST">
          <?php campoCSRF(); ?>
          <input type="hidden" name="accion_plan" value="estado">
          <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">
          <input type="hidden" name="estado" value="en_curso">
          <button type="submit" class="boton-secundario">En curso</button>
        </form>
        <?php endif; ?>
        <?php if ($t['estado'] !== 'completado'): ?>
        <form action="seguimientos.php" method="POST">
          <?php campoCSRF(); ?>
          <input type="hidden" name="accion_plan" value="estado">
          <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">
          <input type="hidden" name="estado" value="completado">
          <button type="submit" class="boton-acceso">Completar</button>
        </form>
        <?php else: ?>
        <form action="seguimientos.php" method="POST">
          <?php campoCSRF(); ?>
          <input type="hidden" name="accion_plan" value="estado">
          <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">
          <input type="hidden" name="estado" value="pendiente">
          <button type="submit" class="boton-secundario">Reabrir</button>
        </form>
        <?php endif; ?>
        <form action="seguimientos.php" method="POST" onsubmit="return confirm('¿Eliminar esta tarea de seguimiento?')">
          <?php campoCSRF(); ?>
          <input type="hidden" name="accion_plan" value="eliminar">
          <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">
          <button type="submit" class="boton-peligro">Eliminar</button>
        </form>
      </div>
    </li>
    <?php
};
?>
<h1>Mis seguimientos</h1>
<p class="texto-suave">Las tareas de control que te has propuesto para tus estudiantes, en un solo lugar. Para agregar una nueva, entra a la ficha del estudiante.</p>

<?php if (empty($todas)): ?>
  <div class="estado-vacio"><p>Todavía no tienes tareas de seguimiento. Créalas desde la ficha de cada estudiante.</p></div>
<?php else: ?>

<?php if (!empty($vencidas)): ?>
<section class="panel-seccion grupo-seguimientos">
  <h2>Vencidas (<?= count($vencidas) ?>)</h2>
  <ul class="lista-plan-seguimiento"><?php foreach ($vencidas as $t) { $dibujarTarea($t); } ?></ul>
</section>
<?php endif; ?>

<?php if (!empty($pendientes)): ?>
<section class="panel-seccion grupo-seguimientos">
  <h2>Pendientes y en curso (<?= count($pendientes) ?>)</h2>
  <ul class="lista-plan-seguimiento"><?php foreach ($pendientes as $t) { $dibujarTarea($t); } ?></ul>
</section>
<?php endif; ?>

<?php if (!empty($completadas)): ?>
<section class="panel-seccion grupo-seguimientos">
  <h2>Completadas (<?= count($completadas) ?>)</h2>
  <ul class="lista-plan-seguimiento"><?php foreach (array_slice($completadas, 0, 20) as $t) { $dibujarTarea($t); } ?></ul>
</section>
<?php endif; ?>

<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
