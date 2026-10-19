<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/validaciones.php';
require_once __DIR__ . '/../src/helpers/correo.php';
require_once __DIR__ . '/../src/modelos/Usuario.php';
require __DIR__ . '/../src/controladores/AuthControlador.php';

$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    // Freno por sesión: evita usar la página para llenar de correos a quien tenga un documento adivinado.
    $ahora = time();
    $marcas = array_values(array_filter((array) ($_SESSION['rec_marcas'] ?? []), static fn ($t) => $t > $ahora - 600));
    if (count($marcas) < 5) {
        $marcas[] = $ahora;
        $_SESSION['rec_marcas'] = $marcas;
        AuthControlador::solicitarRecuperacion($pdo, trim($_POST['correo'] ?? ''));
    }
    $enviado = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Recuperar contraseña</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <link rel="apple-touch-icon" href="assets/img/logo/icon-192.png">
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro">

    <div class="contenedor-login">
        <div class="card-login">
<?php $marcaTitulo = "Recupera tu acceso"; $marcaTexto = "Te enviamos un enlace seguro a tu correo para que elijas una contraseña nueva."; require __DIR__ . '/parciales/auth_marca.php'; ?>
<div class="login-form-lado">

            <h2>Recuperar contraseña</h2>

            <?php if ($enviado): ?>
                <p class="mensaje mensaje-exito">
                    Si la cuenta existe, te enviamos un enlace para restablecer tu contraseña al correo registrado.
                </p>
            <?php else: ?>
                <form action="recuperar_password.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
                    <label for="correo">Correo o documento</label>
                    <input type="text" id="correo" name="correo" required autocapitalize="none" spellcheck="false" maxlength="120">

                    <button type="submit">Enviar enlace</button>
                </form>
            <?php endif; ?>

            <div class="enlaces-login">
                <a href="login.php">Volver a iniciar sesión</a>
            </div>

        </div><!-- /login-form-lado -->
        </div>
    </div>

    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
