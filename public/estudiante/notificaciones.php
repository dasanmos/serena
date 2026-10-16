<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Notificacion.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];

if (isset($_GET['marcar_todas'])) { Notificacion::marcarTodasLeidas($pdo, $uid); header('Location: notificaciones.php'); exit; }
if (isset($_GET['marcar']) && (int)$_GET['marcar']) { Notificacion::marcarLeida($pdo, (int)$_GET['marcar'], $uid); header('Location: notificaciones.php'); exit; }

$notificaciones = Notificacion::listarDeUsuario($pdo, $uid);
$tituloPagina = 'Notificaciones';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Notificaciones</h1>
<div style="margin-bottom:16px">
  <a href="notificaciones.php?marcar_todas=1" class="boton-acceso" data-sonido="clic">Marcar todas como leídas</a>
</div>
<?php if (empty($notificaciones)): ?>
  <div class="estado-vacio">
    <img src="<?= BASE_URL ?>/assets/img/recursos/estado_vacio_calendario.png" alt="">
    <p>No tienes notificaciones por ahora.</p>
  </div>
<?php else: foreach ($notificaciones as $n): ?>
<div class="panel-seccion tarjeta-notificacion <?= $n['leida'] ? 'leida' : 'no-leida' ?> animar-entrada">
  <?php if (!$n['leida']): ?><span class="pill pill-info pill-nueva">Nueva</span><?php endif; ?>
  <strong><?= htmlspecialchars($n['tipo']) ?></strong>
  <small class="fecha-notificacion"> · <?= date('d/m H:i', strtotime($n['fecha_creacion'])) ?></small>
  <p class="texto-notificacion"><?= htmlspecialchars($n['mensaje']) ?></p>
  <?php if (!$n['leida']): ?>
    <a href="notificaciones.php?marcar=<?= $n['id'] ?>" class="boton-secundario" data-sonido="clic">Marcar leída</a>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
