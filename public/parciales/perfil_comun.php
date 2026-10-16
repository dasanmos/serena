<?php
declare(strict_types=1);
// Incluido por <rol>/perfil.php de los 7 roles, DESPUÉS de requiereRol(). No se
// abre directo: si no hay sesión, requiereRol() ya cortó la ejecución antes de
// llegar aquí.
if (!isset($_SESSION['usuario_id'])) { http_response_code(403); exit; }

$uid = (int) $_SESSION['usuario_id'];
$usuario = Usuario::buscarPorId($pdo, $uid);
$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'cambiar_nombre') {
        $nombre = trim($_POST['nombre'] ?? '');
        if (empty($nombre)) {
            $error = 'El nombre no puede estar vacío.';
        } else {
            SolicitudCambio::crear($pdo, $uid, 'nombre', $usuario['nombre'], $nombre);
            $exito = 'Solicitud enviada. El administrador la revisará.';
        }
    } elseif ($accion === 'cambiar_correo') {
        $correo = trim($_POST['correo'] ?? '');
        if (!validarCorreo($correo)) {
            $error = 'Correo inválido.';
        } else {
            SolicitudCambio::crear($pdo, $uid, 'correo', $usuario['correo'], $correo);
            $exito = 'Solicitud enviada. El administrador la revisará.';
        }
    } elseif ($accion === 'cambiar_password') {
        $actual = $_POST['password_actual'] ?? '';
        $nueva = $_POST['password_nueva'] ?? '';
        if (!password_verify($actual, $usuario['password_hash'])) {
            $error = 'La contraseña actual es incorrecta.';
        } elseif (!validarPassword($nueva)) {
            $error = mensajeRequisitosPassword();
        } else {
            Usuario::actualizarPassword($pdo, $uid, $nueva);
            // Regenera el ID de sesión tras cambiar la contraseña: si alguien más
            // tenía esta sesión abierta (por ejemplo, un equipo compartido), el
            // ID viejo deja de servir.
            session_regenerate_id(true);
            $exito = 'Contraseña actualizada.';
        }
    } elseif ($accion === 'solicitar_eliminacion') {
        // No se elimina de una vez: queda como solicitud pendiente, igual que un
        // cambio de nombre o correo, y OTRO administrador la aprueba desde
        // administrador/aprobaciones.php — ahí sí se borra la cuenta de verdad.
        if (SolicitudCambio::tienePendiente($pdo, $uid, 'eliminar_cuenta')) {
            $error = 'Ya tienes una solicitud de eliminación pendiente de revisión.';
        } else {
            SolicitudCambio::crear($pdo, $uid, 'eliminar_cuenta', null, 'Solicitud de eliminación de cuenta');
            $exito = 'Solicitud de eliminación enviada. Un administrador la va a revisar antes de que se borre nada.';
        }
    }
    $usuario = Usuario::buscarPorId($pdo, $uid);
}

$tituloPagina = 'Mi perfil';
$mostrarInsignias = class_exists('Insignia') && $_SESSION['rol'] === 'estudiante';
if ($mostrarInsignias) {
    $insigniasObtenidas = Insignia::listarDeUsuario($pdo, $uid);
    $insigniasTodas = Insignia::listarTodas($pdo);
    $idsObtenidas = array_column($insigniasObtenidas, 'id');
}
require __DIR__ . '/encabezado.php';
?>
<h1>Mi perfil</h1>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>
<?php if ($mostrarInsignias): ?>
<section class="panel-seccion">
  <h2>Insignias</h2>
  <div class="vitrina-insignias">
    <?php foreach ($insigniasTodas as $i): $obtenida = in_array($i['id'], $idsObtenidas); ?>
      <div class="insignia <?= $obtenida ? 'insignia-obtenida' : 'insignia-bloqueada' ?>" title="<?= htmlspecialchars($i['descripcion']) ?>">
        <?php if (function_exists('existeIconoRecurso') && !existeIconoRecurso((string) $i['icono'])): ?>
          <span class="insignia-emoji" aria-hidden="true">🏅</span>
        <?php else: ?>
          <img src="<?= BASE_URL ?>/assets/img/recursos/<?= htmlspecialchars($i['icono']) ?>" alt="<?= htmlspecialchars($i['nombre']) ?>">
        <?php endif; ?>
        <span><?= htmlspecialchars($i['nombre']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<div class="columnas">
<section class="panel-seccion">
  <h2>Información</h2>
  <p><strong>Nombre:</strong> <?= htmlspecialchars($usuario['nombre']) ?></p>
  <p><strong>Correo:</strong> <?= htmlspecialchars($usuario['correo']) ?></p>
  <?php if (!empty($usuario['fecha_nacimiento'])): ?>
    <p><strong>Fecha de nacimiento:</strong> <?= date('d/m/Y', strtotime($usuario['fecha_nacimiento'])) ?></p>
  <?php endif; ?>
  <p><strong>Rol:</strong> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $usuario['rol']))) ?></p>
  <p><strong>Registrado desde:</strong> <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></p>
</section>
<section class="panel-seccion">
  <h2>Cambiar nombre</h2>
  <form action="perfil.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="cambiar_nombre">
    <label>Nombre nuevo</label><input type="text" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>">
    <button type="submit">Solicitar cambio</button>
  </form>
</section>
<section class="panel-seccion">
  <h2>Cambiar correo</h2>
  <form action="perfil.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="cambiar_correo">
    <label>Correo nuevo</label><input type="email" name="correo" value="<?= htmlspecialchars($usuario['correo']) ?>">
    <button type="submit">Solicitar cambio</button>
  </form>
</section>
<section class="panel-seccion">
  <h2>Cambiar contraseña</h2>
  <form action="perfil.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="cambiar_password">
    <label for="password-actual">Contraseña actual</label>
    <input type="password" id="password-actual" name="password_actual" autocomplete="current-password">
    <label for="password-nueva">Contraseña nueva</label>
    <input type="password" id="password-nueva" name="password_nueva" autocomplete="new-password" minlength="8" maxlength="64" data-requisitos="1"
           pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}"
           title="Entre 8 y 64 caracteres, con mayúscula, minúscula, número y carácter especial.">
    <button type="submit">Cambiar contraseña</button>
  </form>
</section>
<section class="panel-seccion">
  <h2>Verificación en 2 pasos</h2>
  <p class="texto-suave">
    <?php
      if ((int) ($usuario['totp_activo'] ?? 0) === 1) {
          echo 'Está activa con Google Authenticator: al iniciar sesión te pedimos el código de tu app.';
      } elseif ((int) ($usuario['correo_2fa_activo'] ?? 0) === 1) {
          echo 'Está activa por correo: al iniciar sesión te enviamos un código a tu correo.';
      } elseif ((int) ($usuario['whatsapp_2fa_activo'] ?? 0) === 1) {
          echo 'Está activa por WhatsApp: al iniciar sesión te enviamos un código por WhatsApp.';
      } elseif ((int) ($usuario['rostro_2fa_activo'] ?? 0) === 1) {
          echo 'Está activa con reconocimiento facial: al iniciar sesión verificamos tu rostro con la cámara.';
      } else {
          echo 'Protege tu cuenta con un código adicional, además de la contraseña.';
      }
    ?>
  </p>
  <a href="<?= BASE_URL ?>/seguridad.php" class="boton-secundario">Ir a Seguridad</a>
</section>
<section class="panel-seccion">
  <h2>Sesiones activas</h2>
  <p class="texto-suave">Revisa desde qué dispositivos tienes la sesión abierta y ciérrala en los que no reconozcas.</p>
  <a href="<?= BASE_URL ?>/sesiones.php" class="boton-secundario">Ver mis sesiones</a>
</section>
<section class="panel-seccion">
  <h2>Eliminar mi cuenta</h2>
  <p class="texto-suave">
    Puedes pedir que se elimine tu cuenta y todos tus datos por completo, como dice
    la política de tratamiento de datos que aceptaste al registrarte. No se borra
    de inmediato: un administrador revisa la solicitud primero.
  </p>
  <form action="perfil.php" method="POST" novalidate onsubmit="return confirm('¿Seguro que quieres solicitar la eliminación de tu cuenta? Esta acción no se puede deshacer una vez que se apruebe.');">
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="solicitar_eliminacion">
    <button type="submit" class="boton-peligro">Solicitar eliminación de mi cuenta</button>
  </form>
</section>
</div>
<?php require __DIR__ . '/pie.php'; ?>
