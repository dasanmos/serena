<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/limiteIntentos.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
$error = ''; $accion = $_GET['accion'] ?? 'verificar';
$segundosRestantes = segundosBloqueoPin($pdo, $uid);

$pinHash = $pdo->prepare('SELECT pin_diario_hash FROM usuarios WHERE id=:id LIMIT 1');
$pinHash->execute(['id' => $uid]);
$hash = $pinHash->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $segundosRestantes <= 0) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    if ($accion === 'establecer') {
        $pin = $_POST['pin'] ?? '';
        if (strlen($pin) < 4) { $error = 'El PIN debe tener al menos 4 dígitos.'; }
        else {
            $pdo->prepare('UPDATE usuarios SET pin_diario_hash=:h WHERE id=:id')
                ->execute(['h' => password_hash($pin, PASSWORD_DEFAULT), 'id' => $uid]);
            $_SESSION['diario_desbloqueado'] = true;
            header('Location: ' . BASE_URL . '/estudiante/diario.php'); exit;
        }
    } else {
        $pin = $_POST['pin'] ?? '';
        if ($hash && password_verify($pin, $hash)) {
            limpiarIntentosPin($pdo, $uid);
            $_SESSION['diario_desbloqueado'] = true;
            header('Location: ' . BASE_URL . '/estudiante/diario.php'); exit;
        }
        registrarIntentoPinFallido($pdo, $uid);
        $segundosRestantes = segundosBloqueoPin($pdo, $uid);
        $error = $segundosRestantes > 0
            ? 'Demasiados intentos fallidos. Intenta de nuevo en ' . (int)ceil($segundosRestantes / 60) . ' minuto(s).'
            : 'PIN incorrecto.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Demasiados intentos fallidos. Intenta de nuevo en ' . (int)ceil($segundosRestantes / 60) . ' minuto(s).';
}
$tituloPagina = 'Acceso al diario';
?><!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Serena — Acceso al diario</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head><body class="login-body">
<div class="contenedor-login"><div class="card-login">
  <img src="<?= BASE_URL ?>/assets/img/logo/logo.png" alt="Serena" class="logo-login">
  <h2><?= $hash ? 'Ingresa tu PIN' : 'Establece tu PIN' ?></h2>
  <?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form action="diario_pin.php<?= !$hash ? '?accion=establecer' : '' ?>" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <label for="pin">PIN numérico</label>
    <input type="password" id="pin" name="pin" inputmode="numeric" autocomplete="off" maxlength="10" required placeholder="••••" class="campo-pin <?= $segundosRestantes > 0 ? 'pin-bloqueado' : '' ?>" <?= $segundosRestantes > 0 ? 'disabled' : '' ?>>
    <button type="submit" <?= $segundosRestantes > 0 ? 'disabled' : '' ?>><?= $hash ? 'Entrar' : 'Guardar PIN' ?></button>
  </form>
  <div class="enlaces-login"><a href="<?= BASE_URL ?>/estudiante/dashboard.php">Volver al inicio</a></div>
</div></div>
<script src="<?= BASE_URL ?>/assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
</body></html>
