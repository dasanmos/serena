<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Mensaje.php';
requiereRol('orientador');
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'iniciar_chat') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $otroId = (int)($_POST['otro_usuario_id'] ?? 0);
    $convId = Mensaje::iniciarConversacion($pdo, $uid, $otroId);
    if ($convId) { header("Location: mensajes.php?conv=$convId"); exit; }
    header('Location: mensajes.php'); exit;
}

$convId = (int)($_GET['conv'] ?? 0);
if ($convId && !Mensaje::perteneceAUsuario($pdo, $convId, $uid)) {
    require_once __DIR__ . '/../../src/helpers/acceso_denegado.php';
    mostrarAccesoDenegado('Esa conversación no es tuya, así que no puedes abrirla.', 'No tienes acceso a esta conversación');
}

if ($convId && $_SERVER['REQUEST_METHOD'] === 'POST' && trim($_POST['mensaje'] ?? '') !== '') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    Mensaje::enviar($pdo, $convId, $uid, trim($_POST['mensaje']));
    header("Location: mensajes.php?conv=$convId"); exit;
}

$conversaciones = Mensaje::conversacionesDeUsuario($pdo, $uid);
$personalDisponible = Mensaje::personalDisponibleParaChat($pdo, $uid);
$mensajes = $convId ? Mensaje::listarMensajes($pdo, $convId) : [];
if ($convId) Mensaje::marcarLeidos($pdo, $convId, $uid);

$tituloPagina = 'Chat';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Chat</h1>
<div class="columnas">
<section class="panel-seccion">
  <h2>Conversaciones</h2>
  <?php if (empty($conversaciones)): ?>
    <div class="estado-vacio">
      <p>Sin conversaciones todavía. Elige a alguien de la lista de abajo para empezar.</p>
    </div>
  <?php else: foreach ($conversaciones as $c): ?>
    <div class="lista-conversaciones">
    <a href="mensajes.php?conv=<?= $c['id'] ?>" class="<?= $c['id']==$convId ? 'activa' : '' ?>">
      <span><strong><?= htmlspecialchars($c['otro_nombre']) ?></strong>
      <span class="pill pill-neutro" style="margin-left:6px"><?= ucfirst($c['otro_rol']) ?></span>
      <?php if ($c['ultimo_msg']): ?><small style="display:block;color:var(--color-texto-suave)"><?= htmlspecialchars(mb_substr($c['ultimo_msg'],0,50)) ?></small><?php endif; ?></span>
      <?php if ($c['no_leidos']): ?><span class="badge-no-leidos"><?= $c['no_leidos'] ?></span><?php endif; ?>
    </a>
    </div>
  <?php endforeach; endif; ?>
</section>
<?php if ($convId): ?>
<section class="panel-seccion">
  <div id="mensajes" class="ventana-chat" style="height:340px" data-sonido-nuevo-mensaje="<?= count($mensajes) ?>">
    <?php if (empty($mensajes)): ?><p class="chat-vacio">Inicia la conversación</p><?php endif; ?>
    <?php foreach ($mensajes as $m): $propio = (int)$m['emisor_id']===$uid; ?>
    <div class="burbuja <?= $propio ? 'burbuja-propia' : 'burbuja-otro' ?>">
      <?= nl2br(htmlspecialchars($m['contenido'])) ?>
      <small class="burbuja-hora"><?= date('H:i', strtotime($m['fecha_envio'])) ?></small>
    </div>
    <?php endforeach; ?>
  </div>
  <form action="mensajes.php?conv=<?= $convId ?>" method="POST" class="form-chat">
    <?php campoCSRF(); ?>
    <input type="text" name="mensaje" placeholder="Escribe un mensaje..." style="flex:1" autocomplete="off" required>
    <button type="submit" class="boton-acceso">Enviar</button>
  </form>
</section>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../parciales/selector_personas_mensajes.php'; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
