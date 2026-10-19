<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/validaciones.php';
require __DIR__ . '/../src/controladores/AuthControlador.php';
require_once __DIR__ . '/../src/modelos/Usuario.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = AuthControlador::restablecerPassword($pdo, $token, $_POST['password'] ?? '');

    if ($resultado['exito']) {
        $exito = true;
    } else {
        $error = $resultado['mensaje'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Restablecer contraseña</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <link rel="apple-touch-icon" href="assets/img/logo/icon-192.png">
    <script>window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro">

    <div class="contenedor-login">
        <div class="card-login">
<?php $marcaTitulo = "Elige una contraseña nueva"; $marcaTexto = "Usa una contraseña segura que solo tú conozcas."; require __DIR__ . '/parciales/auth_marca.php'; ?>
<div class="login-form-lado">

            <h2>Restablecer contraseña</h2>

            <?php if ($exito): ?>
                <p class="mensaje mensaje-exito">Tu contraseña fue actualizada.</p>
                <div class="enlaces-login">
                    <a href="login.php">Iniciar sesión</a>
                </div>
            <?php else: ?>

                <?php if ($error): ?>
                    <p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <form action="restablecer_password.php" method="POST" novalidate>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <label for="password">Nueva contraseña</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required
                           minlength="8" maxlength="64" data-requisitos="1"
                           pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}"
                           title="Entre 8 y 64 caracteres, con mayúscula, minúscula, número y carácter especial.">

                    <button type="submit">Guardar contraseña</button>
                </form>
            <?php endif; ?>

        </div><!-- /login-form-lado -->
        </div>
    </div>

    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/requisitosPassword.js?v=<?= assetVersion('/assets/js/requisitosPassword.js') ?>"></script>
    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
