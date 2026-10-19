<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/limiteIntentos.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/modelos/Usuario.php';
require_once __DIR__ . '/../src/modelos/VerificacionDosPasos.php';
require_once __DIR__ . '/../src/modelos/VerificacionDosPasosCorreo.php';
require_once __DIR__ . '/../src/modelos/VerificacionDosPasosWhatsapp.php';
require_once __DIR__ . '/../src/modelos/VerificacionDosPasosRostro.php';

// Esta pantalla nunca debe quedar guardada en caché (ni del navegador ni del service worker).
header('Cache-Control: no-store');

if (estaAutenticado()) {
    header('Location: ' . rutaDashboardPorRol($_SESSION['rol']));
    exit;
}

$desafio = desafio2FAVigente();
if ($desafio === null) {
    header('Location: ' . BASE_URL . '/login.php?expiro2fa=1');
    exit;
}

$usuario = Usuario::buscarPorId($pdo, (int) $desafio['usuario_id']);
$usaCorreo = $usuario && VerificacionDosPasosCorreo::estaActivo($usuario);
$usaWhatsapp = $usuario && VerificacionDosPasosWhatsapp::estaActivo($usuario);
$usaRostro = $usuario && VerificacionDosPasosRostro::estaActivo($usuario);
$usaTotp = $usuario && VerificacionDosPasos::estaActivo($usuario);
if (!$usuario || (int) $usuario['activo'] === 0 || (!$usaTotp && !$usaCorreo && !$usaWhatsapp && !$usaRostro)) {
    unset($_SESSION['desafio_2fa']);
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Correo y WhatsApp comparten el mismo patrón (código enviado + botón de
// reenviar); esta bandera evita repetir la rama dos veces.
$usaEnvio = $usaCorreo || $usaWhatsapp;

// Los 4 métodos comparten el mismo lote de códigos de recuperación (solo uno
// está activo a la vez), así que la opción de "usar un código de recuperación"
// es un respaldo válido sin importar cuál sea el método activo.
$usaRecuperacion = ($_GET['usar'] ?? $_POST['modo'] ?? '') === 'recuperacion';
$error = mensajeBloqueo2FA($pdo, (int) $usuario['id']);
$reenviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? 'verificar';

    if ($usaEnvio && $accion === 'reenviar' && $error === '' && !$usaRecuperacion) {
        $segundosRestantes = $usaCorreo
            ? VerificacionDosPasosCorreo::segundosParaReenvio($pdo, (int) $usuario['id'])
            : VerificacionDosPasosWhatsapp::segundosParaReenvio($pdo, (int) $usuario['id']);
        if ($segundosRestantes <= 0) {
            if ($usaCorreo) {
                VerificacionDosPasosCorreo::generarYEnviar($pdo, (int) $usuario['id'], $usuario['correo']);
            } else {
                VerificacionDosPasosWhatsapp::generarYEnviar($pdo, $usuario);
            }
            $reenviado = true;
        }
    } elseif ($accion === 'verificar') {
        $codigo = (string) ($_POST['codigo'] ?? '');

        if ($error === '') {
            if ($usaRecuperacion) {
                $valido = VerificacionDosPasos::consumirCodigoRecuperacion($pdo, (int) $usuario['id'], $codigo);
            } elseif ($usaRostro) {
                $valido = VerificacionDosPasosRostro::verificar($usuario, (string) ($_POST['descriptor'] ?? ''));
            } elseif ($usaCorreo) {
                $valido = VerificacionDosPasosCorreo::verificarCodigo($pdo, (int) $usuario['id'], $codigo);
            } elseif ($usaWhatsapp) {
                $valido = VerificacionDosPasosWhatsapp::verificarCodigo($pdo, (int) $usuario['id'], $codigo);
            } else {
                $valido = VerificacionDosPasos::verificarCodigoApp($pdo, $usuario, $codigo);
            }

            if ($valido) {
                limpiarIntentos2FA($pdo, (int) $usuario['id']);
                unset($_SESSION['desafio_2fa']);
                iniciarSesionUsuario($usuario, $pdo);
                Usuario::actualizarUltimoAcceso($pdo, (int) $usuario['id']);
                // Solo se registra el camino que realmente se usó — antes podían
                // quedar dos entradas de auditoría para un mismo login (p. ej. si
                // el usuario tenía correo activo pero entró con un código de
                // recuperación, ambos "if" independientes se disparaban).
                if ($usaRecuperacion) {
                    auditarAccion($pdo, 'login_codigo_recuperacion_2fa', 'usuarios', (int) $usuario['id']);
                } elseif ($usaCorreo) {
                    auditarAccion($pdo, 'login_codigo_correo_2fa', 'usuarios', (int) $usuario['id']);
                } elseif ($usaWhatsapp) {
                    auditarAccion($pdo, 'login_codigo_whatsapp_2fa', 'usuarios', (int) $usuario['id']);
                } elseif ($usaRostro) {
                    auditarAccion($pdo, 'login_rostro_2fa', 'usuarios', (int) $usuario['id']);
                } else {
                    auditarAccion($pdo, 'login_totp_2fa', 'usuarios', (int) $usuario['id']);
                }

                // Los 4 métodos comparten el mismo lote de códigos de recuperación:
                // si ya no queda ninguno utilizable (se gastaron o vencieron), se
                // crea un lote nuevo y se muestra una sola vez, sin importar cuál
                // método usó la persona para entrar.
                $nuevos = VerificacionDosPasos::renovarSiHaceFalta($pdo, (int) $usuario['id']);
                if ($nuevos !== null) {
                    $_SESSION['codigos_2fa_pendientes'] = $nuevos + ['origen' => 'login'];
                    auditarAccion($pdo, 'renovar_codigos_recuperacion_2fa', 'usuarios', (int) $usuario['id'], [], ['motivo' => $nuevos['motivo']]);
                    header('Location: ' . BASE_URL . '/seguridad.php');
                    exit;
                }
                header('Location: ' . rutaDashboardPorRol($usuario['rol']));
                exit;
            }

            registrarIntento2FAFallido($pdo, (int) $usuario['id']);
            $error = mensajeBloqueo2FA($pdo, (int) $usuario['id'])
                ?: ($usaRecuperacion
                    ? 'Ese código de recuperación no es válido, ya se usó o venció.'
                    : ($usaRostro
                        ? 'No pudimos reconocer tu rostro. Acércate a una luz suave de frente e inténtalo de nuevo.'
                        : ($usaEnvio
                            ? 'Ese código no es correcto o ya venció.'
                            : 'Ese código no es correcto. Revisa que la hora de tu teléfono sea automática.')));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Serena — Verificación en 2 pasos</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="assets/css/seguridad.css?v=<?= assetVersion('/assets/css/seguridad.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <script>
        window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;
        (function () {
            var tema = localStorage.getItem('serena_tema');
            if (tema === 'oscuro' || (!tema && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-tema', 'oscuro');
            }
        })();
    </script>
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro">
    <div class="contenedor-login">
        <div class="card-login">
<?php $marcaTitulo = "Un paso más para proteger tu cuenta"; $marcaTexto = "La verificación en 2 pasos mantiene tu información a salvo."; require __DIR__ . '/parciales/auth_marca.php'; ?>
<div class="login-form-lado">
            <h1 class="titulo-2fa">Verificación en 2 pasos</h1>
            <p class="texto-suave subtitulo-2fa">
                <?php if ($usaRecuperacion): ?>
                    Escribe uno de tus códigos de recuperación. Cada uno sirve una sola vez.
                <?php elseif ($usaRostro): ?>
                    Mira a la cámara para confirmar que eres tú. La imagen se procesa en tu dispositivo y no se guarda.
                <?php elseif ($usaCorreo): ?>
                    Te enviamos un código de 6 dígitos a <strong><?= htmlspecialchars($usuario['correo']) ?></strong>.
                <?php elseif ($usaWhatsapp): ?>
                    Te enviamos un código de 6 dígitos por WhatsApp. Puede tardar hasta un minuto en llegar.
                <?php else: ?>
                    Abre tu app de autenticación y escribe el código de 6 dígitos de Serena.
                <?php endif; ?>
            </p>
            <?php if ($error): ?>
                <p class="mensaje mensaje-error" role="alert"><?= htmlspecialchars($error) ?></p>
            <?php elseif ($reenviado): ?>
                <p class="mensaje mensaje-exito" role="status">Te enviamos un código nuevo.</p>
            <?php endif; ?>
            <form action="verificacion_2pasos.php<?= $usaRecuperacion ? '?usar=recuperacion' : '' ?>" method="POST" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="verificar">
                <input type="hidden" name="modo" value="<?= $usaRecuperacion ? 'recuperacion' : 'app' ?>">
                <?php if ($usaRecuperacion): ?>
                    <label for="codigo">Código de recuperación</label>
                    <input type="text" id="codigo" name="codigo" class="campo-codigo campo-codigo-recuperacion"
                           placeholder="XXXX-XXXX" autocomplete="off" autocapitalize="characters"
                           spellcheck="false" autofocus required>
                    <button type="submit">Verificar</button>
                <?php elseif ($usaRostro): ?>
                    <?php $rostroModo = 'verificar'; require __DIR__ . '/parciales/visor_rostro.php'; ?>
                <?php else: ?>
                    <label for="codigo">
                        <?php if ($usaCorreo): ?>Código de tu correo
                        <?php elseif ($usaWhatsapp): ?>Código de WhatsApp
                        <?php else: ?>Código de 6 dígitos
                        <?php endif; ?>
                    </label>
                    <input type="text" id="codigo" name="codigo" class="campo-codigo"
                           inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code"
                           placeholder="000000" data-codigo-2fa autofocus required>
                    <button type="submit">Verificar</button>
                <?php endif; ?>
            </form>
            <?php if ($usaEnvio && !$usaRecuperacion): ?>
                <?php $esperaReenvio = $usaCorreo
                    ? VerificacionDosPasosCorreo::segundosParaReenvio($pdo, (int) $usuario['id'])
                    : VerificacionDosPasosWhatsapp::segundosParaReenvio($pdo, (int) $usuario['id']); ?>
                <form action="verificacion_2pasos.php" method="POST" class="reenviar-2fa-correo" novalidate>
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="reenviar">
                    <button type="submit" class="boton-secundario" data-reenviar-2fa
                            data-espera="<?= $esperaReenvio ?>" <?= $esperaReenvio > 0 ? 'disabled' : '' ?>>
                        <?= $esperaReenvio > 0 ? "Reenviar código ({$esperaReenvio}s)" : 'Reenviar código' ?>
                    </button>
                </form>
            <?php endif; ?>
            <div class="enlaces-login enlaces-2fa">
                <?php if ($usaRecuperacion): ?>
                    <a href="verificacion_2pasos.php">
                        Volver a
                        <?= $usaRostro ? ' mi rostro' : ($usaCorreo ? ' mi correo' : ($usaWhatsapp ? ' mi WhatsApp' : ' mi app de autenticación')) ?>
                    </a>
                <?php else: ?>
                    <a href="verificacion_2pasos.php?usar=recuperacion">Usar un código de recuperación</a>
                <?php endif; ?>
                <a href="login.php?cancelar2fa=1">Volver</a>
            </div>
        </div><!-- /login-form-lado -->
        </div>
    </div>
    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/seguridad.js?v=<?= assetVersion('/assets/js/seguridad.js') ?>"></script>
    <?php if ($usaRostro): ?>
    <script src="assets/js/rostro.js?v=<?= assetVersion('/assets/js/rostro.js') ?>"></script>
    <?php endif; ?>
    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
