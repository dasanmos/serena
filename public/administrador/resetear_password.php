<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
requiereRol('administrador');
$error = ''; $exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $uid = (int)($_POST['usuario_id'] ?? 0);
    $nueva = $_POST['password_nueva'] ?? '';
    if (!$uid) { $error = 'Selecciona un usuario.'; }
    elseif (!validarPassword($nueva)) { $error = mensajeRequisitosPassword(); }
    else {
        $u = usuarioEsDeMiColegio($pdo, $uid) ? Usuario::buscarPorId($pdo, $uid) : null;
        if (!$u) { $error = 'Usuario no encontrado.'; }
        else {
            Usuario::actualizarPassword($pdo, $uid, $nueva);
            auditarAccion($pdo, 'resetear_password_admin', 'usuarios', $uid);
            $exito = "Contraseña de {$u['nombre']} restablecida.";
        }
    }
}

$filtroRol = $_GET['rol'] ?? '';
$busqueda = trim($_GET['q'] ?? '');
$usuarios = Usuario::buscarConFiltro($pdo, $filtroRol, $busqueda);
$roles = $pdo->query("SELECT nombre FROM roles WHERE nombre != 'superadmin' ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$idPreseleccionado = (int) ($_GET['id'] ?? 0);

$tituloPagina = 'Resetear contraseña';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Resetear contraseña</h1>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<form action="resetear_password.php" method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px">
    <div>
        <label for="q">Buscar por nombre o correo</label>
        <input type="search" id="q" name="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Ej. Ana">
    </div>
    <div>
        <label for="rol">Tipo de usuario</label>
        <select id="rol" name="rol">
            <option value="">Todos</option>
            <?php foreach ($roles as $rol): ?>
                <option value="<?= htmlspecialchars($rol) ?>" <?= $filtroRol === $rol ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($rol)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit">Buscar</button>
</form>

<form action="resetear_password.php" method="POST" class="formulario-panel" novalidate>
  <?php campoCSRF(); ?>
  <label>Usuario (<?= count($usuarios) ?> con este filtro)</label>
  <select name="usuario_id" required>
    <option value="">Selecciona un usuario</option>
    <?php foreach ($usuarios as $u): ?><option value="<?= $u['id'] ?>" <?= (int) $u['id'] === $idPreseleccionado ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre'].' — '.$u['correo'].' ('.ucfirst($u['rol']).')') ?></option><?php endforeach; ?>
  </select>
  <label for="password-nueva">Nueva contraseña</label>
  <input type="password" id="password-nueva" name="password_nueva" autocomplete="new-password" required
         minlength="8" maxlength="64" data-requisitos="1"
         pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}"
         title="Entre 8 y 64 caracteres, con mayúscula, minúscula, número y carácter especial.">
  <button type="submit">Restablecer</button>
</form>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
