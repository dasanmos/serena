<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';

require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

// A diferencia de los demás roles, el superadmin edita su nombre y correo directo:
// no tiene colegio ni administrador que apruebe solicitudes. Tampoco puede
// solicitar su propia eliminación (no hay quién la apruebe).
$uid = (int) $_SESSION['usuario_id'];
$usuario = Usuario::buscarPorId($pdo, $uid);
$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cambiar_nombre') {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            $error = 'El nombre es obligatorio (máximo 100 caracteres).';
        } else {
            $pdo->prepare('UPDATE usuarios SET nombre = :n WHERE id = :id')->execute(['n' => $nombre, 'id' => $uid]);
            $_SESSION['nombre'] = $nombre;
            $exito = 'Nombre actualizado.';
        }
    } elseif ($accion === 'cambiar_correo') {
        $correo = trim($_POST['correo'] ?? '');
        $password = $_POST['password_actual'] ?? '';
        if (!validarCorreo($correo)) {
            $error = 'Correo inválido.';
        } elseif (!password_verify($password, $usuario['password_hash'])) {
            $error = 'La contraseña actual es incorrecta.';
        } elseif (strcasecmp($correo, $usuario['correo']) !== 0 && Usuario::correoExiste($pdo, $correo)) {
            $error = 'Ese correo ya está en uso.';
        } else {
            $pdo->prepare('UPDATE usuarios SET correo = :c WHERE id = :id')->execute(['c' => $correo, 'id' => $uid]);
            $_SESSION['correo'] = $correo;
            $exito = 'Correo actualizado.';
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
            session_regenerate_id(true);
            $exito = 'Contraseña actualizada.';
        }
    }
    $usuario = Usuario::buscarPorId($pdo, $uid);
}

$tituloPagina = 'Mi perfil';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Mi perfil</h1>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>
<div class="columnas">
<section class="panel-seccion">
  <h2>Información</h2>
  <p><strong>Nombre:</strong> <?= htmlspecialchars($usuario['nombre']) ?></p>
  <p><strong>Correo:</strong> <?= htmlspecialchars($usuario['correo']) ?></p>
  <p><strong>Rol:</strong> <?= esSecretaria() ? 'Secretaría de Educación' : 'Superadmin' ?></p>
  <p><strong>Registrado desde:</strong> <?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></p>
</section>
<section class="panel-seccion">
  <h2>Cambiar nombre</h2>
  <form action="perfil.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="cambiar_nombre">
    <label>Nombre nuevo</label><input type="text" name="nombre" maxlength="100" value="<?= htmlspecialchars($usuario['nombre']) ?>">
    <button type="submit">Guardar nombre</button>
  </form>
</section>
<section class="panel-seccion">
  <h2>Cambiar correo</h2>
  <form action="perfil.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="cambiar_correo">
    <label for="correo-nuevo">Correo nuevo</label>
    <input type="email" id="correo-nuevo" name="correo" value="<?= htmlspecialchars($usuario['correo']) ?>">
    <label for="password-actual-correo">Contraseña actual</label>
    <input type="password" id="password-actual-correo" name="password_actual" autocomplete="current-password">
    <button type="submit">Guardar correo</button>
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
  <p class="texto-suave">Protege tu cuenta con un código adicional, además de la contraseña.</p>
  <a href="<?= BASE_URL ?>/seguridad.php" class="boton-secundario">Ir a Seguridad</a>
</section>
<section class="panel-seccion">
  <h2>Sesiones activas</h2>
  <p class="texto-suave">Revisa desde qué dispositivos tienes la sesión abierta y ciérrala en los que no reconozcas.</p>
  <a href="<?= BASE_URL ?>/sesiones.php" class="boton-secundario">Ver mis sesiones</a>
</section>
</div>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
