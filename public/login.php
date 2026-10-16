<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/limiteIntentos.php';
require_once __DIR__ . '/../src/helpers/recaptcha.php';
require_once __DIR__ . '/../src/modelos/Usuario.php';
require __DIR__ . '/../src/controladores/AuthControlador.php';
if (estaAutenticado()) {
    header('Location: ' . rutaDashboardPorRol($_SESSION['rol']));
    exit;
}
$error = '';
if (isset($_GET['cancelar2fa'])) {
    unset($_SESSION['desafio_2fa']); // "Volver" desde el paso 2 del login
}
$exitoRegistro = isset($_GET['registrado']);
if (isset($_GET['expiro2fa'])) {
    $error = 'Pasó demasiado tiempo sin verificar el código. Inicia sesión de nuevo.';
}
if (isset($_GET['sesionCerrada'])) {
    $error = 'Cerraste esta sesión desde otro dispositivo. Inicia sesión de nuevo.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    if (!verificarRecaptcha($_POST['g-recaptcha-response'] ?? '')) {
        $error = 'Confirma que no eres un robot.';
    } else {
        $resultado = AuthControlador::login($pdo, trim($_POST['correo'] ?? ''), $_POST['password'] ?? '');
        if ($resultado['exito'] && !empty($resultado['requiere_2fa'])) {
            header('Location: ' . BASE_URL . '/verificacion_2pasos.php');
            exit;
        }
        if ($resultado['exito']) {
            header('Location: ' . rutaDashboardPorRol($resultado['usuario']['rol']));
            exit;
        }
        $error = $resultado['mensaje'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Iniciar sesión</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="assets/css/login_pro.css?v=<?= assetVersion('/assets/css/login_pro.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <link rel="apple-touch-icon" href="assets/img/logo/icon-192.png">
    <script>window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
    <?php if (recaptchaConfigurado()): ?>
    <script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoad&render=explicit" async defer></script>
    <script>
        // Renderizado explícito (no automático) para poder fijar el tema claro/oscuro
        // ANTES de que la casilla se pinte — con el modo automático de Google hay una
        // carrera entre su script (async) y el nuestro, y el tema puede no aplicarse.
        function onRecaptchaLoad() {
            var oscuro = localStorage.getItem('serena_tema') === 'oscuro';
            grecaptcha.render('recaptcha-widget', {
                sitekey: '<?= htmlspecialchars(recaptchaSiteKey(), ENT_QUOTES) ?>',
                theme: oscuro ? 'dark' : 'light',
            });
        }
    </script>
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro">
    <div class="contenedor-login">
        <div class="card-login">
            <aside class="login-marca-lado">
                <div>
                    <a href="index.php"><img src="assets/img/logo/logo.png" alt="Serena" class="logo-login"></a>
                    <h2>Bienestar emocional para tu colegio</h2>
                    <p>Cuerpo • Mente • Bienestar. Acompañamiento cercano para estudiantes, familias y equipos docentes.</p>
                </div>
                <ul class="login-puntos">
                    <li><span>💛</span> Seguimiento emocional diario</li>
                    <li><span>🔔</span> Alertas tempranas para actuar a tiempo</li>
                    <li><span>🔒</span> Tus datos protegidos, con verificación en 2 pasos</li>
                </ul>
            </aside>
            <div class="login-form-lado">
                <h1>Iniciar sesión</h1>
                <p class="login-sub">Ingresa con tu cuenta de Serena.</p>
                <?php if ($error): ?>
                    <p class="mensaje mensaje-error" role="alert"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <?php if ($exitoRegistro): ?>
                    <p class="mensaje mensaje-exito">Cuenta creada. Ya puedes iniciar sesión.</p>
                <?php endif; ?>
                <form action="login.php" method="POST" novalidate>
                    <?php campoCSRF(); ?>
                    <label for="correo">Correo o documento</label>
                    <input type="text" id="correo" name="correo" required autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="120" autofocus aria-describedby="ayuda-identificador">
                    <small id="ayuda-identificador" class="texto-suave">Escribe tu correo o tu número de documento (sin puntos).</small>
                    <div id="colegio-login" class="colegio-login" hidden aria-live="polite">
                        <span class="colegio-login-icono" aria-hidden="true">🏫</span>
                        <div><small>Estás ingresando a</small><strong id="colegio-login-nombre"></strong></div>
                    </div>
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <p id="aviso-mayus" class="aviso-mayus" hidden>⚠️ Tienes activado Bloq Mayús</p>
                    <?php if (recaptchaConfigurado()): ?>
                    <div id="recaptcha-widget"></div>
                    <?php endif; ?>
                    <button type="submit">Ingresar</button>
                </form>
                <div class="enlaces-login">
                    <a href="recuperar_password.php">¿Olvidaste tu contraseña?</a>
                    <a href="registro.php">Crear una cuenta</a>
                    <a href="ayuda.php">Ayuda</a>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/login_colegio.js?v=<?= assetVersion('/assets/js/login_colegio.js') ?>"></script>
    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>