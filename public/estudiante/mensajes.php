<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Mensaje.php';
requiereRol('estudiante');
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
// Separadas visualmente: emocional (psicólogo/orientador) vs académico (docente de grupo).
$conversacionesEmocional = array_filter($conversaciones, fn($c) => in_array($c['otro_rol'], ['psicologo','orientador']));
$conversacionesAcademico = array_filter($conversaciones, fn($c) => $c['otro_rol'] === 'docente');

$profesionalesDisponibles = Mensaje::profesionalesDisponiblesParaEstudiante($pdo, $uid);
$docenteDeGrupo = Mensaje::docenteDeGrupoDeEstudiante($pdo, $uid);
$yaTieneChatConDocente = !empty($conversacionesAcademico);

$mensajes = $convId ? Mensaje::listarMensajes($pdo, $convId) : [];
if ($convId) Mensaje::marcarLeidos($pdo, $convId, $uid);

$tituloPagina = 'Chat';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Chat</h1>
<p class="texto-ayuda-vencidas">Dos canales separados: uno con psicología/orientación para lo emocional, otro con tu docente de grupo para temas académicos (permisos, tareas, avisos). Tu docente nunca ve el canal emocional.</p>

<div class="columnas">
<section class="panel-seccion">
  <h2>💛 Psicología / Orientación</h2>
  <?php if (empty($conversacionesEmocional)): ?>
    <div class="estado-vacio"><p>Sin conversaciones todavía.</p></div>
  <?php else: foreach ($conversacionesEmocional as $c): ?>
    <div class="lista-conversaciones">
    <a href="mensajes.php?conv=<?= $c['id'] ?>" class="<?= $c['id']==$convId ? 'activa' : '' ?>">
      <span><strong><?= htmlspecialchars($c['otro_nombre']) ?></strong>
      <span class="pill pill-neutro" style="margin-left:6px"><?= ucfirst($c['otro_rol']) ?></span>
      <?php if ($c['ultimo_msg']): ?><small style="display:block;color:var(--color-texto-suave)"><?= htmlspecialchars(mb_substr($c['ultimo_msg'],0,50)) ?></small><?php endif; ?></span>
      <?php if ($c['no_leidos']): ?><span class="badge-no-leidos"><?= $c['no_leidos'] ?></span><?php endif; ?>
    </a>
    </div>
  <?php endforeach; endif; ?>
  <?php if (!empty($profesionalesDisponibles)): ?>
    <div class="lista-selector-personas" style="margin-top:12px">
      <?php foreach ($profesionalesDisponibles as $p): ?>
        <form action="mensajes.php" method="POST" class="item-selector-persona">
          <?php campoCSRF(); ?>
          <input type="hidden" name="accion" value="iniciar_chat">
          <input type="hidden" name="otro_usuario_id" value="<?= $p['id'] ?>">
          <button type="submit" data-sonido="clic">
            <span class="nombre-persona"><?= htmlspecialchars($p['nombre']) ?></span>
            <span class="pill pill-neutro"><?= ucfirst($p['rol']) ?></span>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="panel-seccion">
  <h2>📘 Mi docente</h2>
  <?php if (empty($conversacionesAcademico)): ?>
    <?php if ($docenteDeGrupo): ?>
      <p>Aún no has escrito a tu docente, <strong><?= htmlspecialchars($docenteDeGrupo['nombre']) ?></strong>.</p>
      <form action="mensajes.php" method="POST">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="iniciar_chat">
        <input type="hidden" name="otro_usuario_id" value="<?= $docenteDeGrupo['id'] ?>">
        <button type="submit" class="boton-acceso" data-sonido="clic">Escribirle</button>
      </form>
    <?php else: ?>
      <p>Tu grupo todavía no tiene un docente asignado.</p>
    <?php endif; ?>
  <?php else: foreach ($conversacionesAcademico as $c): ?>
    <div class="lista-conversaciones">
    <a href="mensajes.php?conv=<?= $c['id'] ?>" class="<?= $c['id']==$convId ? 'activa' : '' ?>">
      <span><strong><?= htmlspecialchars($c['otro_nombre']) ?></strong>
      <?php if ($c['ultimo_msg']): ?><small style="display:block;color:var(--color-texto-suave)"><?= htmlspecialchars(mb_substr($c['ultimo_msg'],0,50)) ?></small><?php endif; ?></span>
      <?php if ($c['no_leidos']): ?><span class="badge-no-leidos"><?= $c['no_leidos'] ?></span><?php endif; ?>
    </a>
    </div>
  <?php endforeach; endif; ?>
</section>
</div>

<?php if ($convId): ?>
<section class="panel-seccion" style="margin-top:20px">
  <div id="chat-mensajes" class="ventana-chat" data-sonido-nuevo-mensaje="<?= count($mensajes) ?>">
    <?php foreach ($mensajes as $m): ?>
      <?php $propio = (int)$m['emisor_id'] === $uid; ?>
      <div class="burbuja <?= $propio ? 'burbuja-propia' : 'burbuja-otro' ?>">
        <?php if (!$propio): ?><small style="display:block;font-weight:600;margin-bottom:4px"><?= htmlspecialchars($m['nombre_emisor']) ?></small><?php endif; ?>
        <?= nl2br(htmlspecialchars($m['contenido'])) ?>
        <small class="burbuja-hora"><?= date('H:i', strtotime($m['fecha_envio'])) ?></small>
      </div>
    <?php endforeach; ?>
    <?php if (empty($mensajes)): ?><p class="chat-vacio">Inicia la conversación</p><?php endif; ?>
  </div>
  <form action="mensajes.php?conv=<?= $convId ?>" method="POST" class="form-chat">
    <?php campoCSRF(); ?>
    <input type="text" name="mensaje" placeholder="Escribe un mensaje..." style="flex:1" autocomplete="off" required>
    <button type="submit" class="boton-acceso">Enviar</button>
  </form>
  <script>document.getElementById('chat-mensajes').scrollTop = 99999;</script>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
