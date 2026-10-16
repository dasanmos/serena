<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/limiteIntentos.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/impersonacion.php';
require_once __DIR__ . '/../src/modelos/Usuario.php';
require_once __DIR__ . '/../src/modelos/VerificacionDosPasos.php';
require_once __DIR__ . '/../src/modelos/VerificacionDosPasosCorreo.php';
require_once __DIR__ . '/../src/modelos/VerificacionDosPasosWhatsapp.php';
require_once __DIR__ . '/../src/modelos/VerificacionDosPasosRostro.php';

requiereSesion();

// Aquí se muestran el secreto del autenticador y los códigos de recuperación:
// nada de esto puede quedar en la caché del navegador ni del service worker.
header('Cache-Control: no-store');

$uid = (int) $_SESSION['usuario_id'];
$viendoComo = estaViendoComoOtro(); // un administrador NO debe tocar la seguridad de otra persona desde "Ver como"
$error = '';
$exito = '';

$mensajesOk = [
    'activada'             => 'Verificación en 2 pasos activada. Desde ahora te pediremos el código al iniciar sesión.',
    'codigos'              => 'Listo, tus códigos nuevos ya están activos.',
    'desactivada'          => 'Verificación en 2 pasos desactivada.',
    'correo_activada'      => 'Verificación por correo activada. Desde ahora te pediremos un código en tu correo al iniciar sesión.',
    'correo_desactivada'   => 'Verificación por correo desactivada.',
    'correo_reenviado'     => 'Te enviamos un código nuevo a tu correo.',
    'whatsapp_activada'    => 'Verificación por WhatsApp activada. Desde ahora te pediremos un código por WhatsApp al iniciar sesión.',
    'whatsapp_desactivada' => 'Verificación por WhatsApp desactivada.',
    'whatsapp_reenviado'   => 'Te enviamos un código nuevo por WhatsApp.',
    'whatsapp_conectado'   => 'Listo, guardamos tu número y tu apikey de CallMeBot. Ahora puedes activar la verificación por WhatsApp.',
    'whatsapp_borrado'     => 'Borramos tu número y tu apikey de CallMeBot.',
    'rostro_activada'      => 'Reconocimiento facial activado. Desde ahora te pediremos verificar tu rostro al iniciar sesión.',
    'rostro_desactivada'   => 'Reconocimiento facial desactivado y tus datos faciales borrados.',
];
if (isset($_GET['ok'], $mensajesOk[$_GET['ok']])) {
    $exito = $mensajesOk[$_GET['ok']];
}

// $quedarseEn: sigue mostrando el detalle del método (para pasos intermedios
// como "código incorrecto, intenta de nuevo"). Sin eso, la persona vuelve al
// panel de cards — apropiado para "activada"/"desactivada" finales, donde ya
// no hay nada más que hacer en el detalle.
function redirigirSeguridad(string $ok = '', ?string $quedarseEn = 'totp'): void
{
    $partes = [];
    if ($quedarseEn !== null) $partes[] = 'metodo=' . $quedarseEn;
    if ($ok !== '') $partes[] = 'ok=' . $ok;
    $query = $partes ? '?' . implode('&', $partes) : '';
    header('Location: ' . BASE_URL . '/seguridad.php' . $query);
    exit;
}

if (!$viendoComo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';
    $usuario = Usuario::buscarPorId($pdo, $uid);
    $activo = $usuario && VerificacionDosPasos::estaActivo($usuario);
    $activoCorreo = $usuario && VerificacionDosPasosCorreo::estaActivo($usuario);
    $activoWhatsapp = $usuario && VerificacionDosPasosWhatsapp::estaActivo($usuario);
    $activoRostro = $usuario && VerificacionDosPasosRostro::estaActivo($usuario);
    $bloqueo = mensajeBloqueo2FA($pdo, $uid);

    if ($accion === 'iniciar_activacion') {
        if ($activo) {
            $error = 'La verificación en 2 pasos ya está activa.';
        } else {
            VerificacionDosPasos::iniciarActivacion($pdo, $uid);
            redirigirSeguridad();
        }
    } elseif ($accion === 'cancelar_activacion') {
        if (!$activo) VerificacionDosPasos::cancelarActivacion($pdo, $uid);
        redirigirSeguridad();
    } elseif ($accion === 'confirmar_activacion') {
        if ($activo) {
            redirigirSeguridad();
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } else {
            $lote = VerificacionDosPasos::confirmarActivacion($pdo, $uid, (string) ($_POST['codigo'] ?? ''));
            if ($lote !== null) {
                limpiarIntentos2FA($pdo, $uid);
                $_SESSION['codigos_2fa_pendientes'] = $lote + ['origen' => 'activacion_totp'];
                auditarAccion($pdo, 'activar_2fa', 'usuarios', $uid);
                redirigirSeguridad();
            }
            registrarIntento2FAFallido($pdo, $uid);
            $error = mensajeBloqueo2FA($pdo, $uid)
                ?: 'Ese código no es correcto. Revisa que la hora de tu teléfono sea automática e inténtalo con el código nuevo.';
        }
    } elseif ($accion === 'codigos_guardados') {
        $origen = $_SESSION['codigos_2fa_pendientes']['origen'] ?? '';
        unset($_SESSION['codigos_2fa_pendientes']);
        if ($origen === 'login') {
            header('Location: ' . rutaDashboardPorRol($_SESSION['rol']));
            exit;
        }
        $mensajePorOrigen = [
            'activacion_totp'     => 'activada',
            'activacion_correo'   => 'correo_activada',
            'activacion_whatsapp' => 'whatsapp_activada',
            'activacion_rostro'   => 'rostro_activada',
            'regenerar'           => 'codigos',
        ];
        redirigirSeguridad($mensajePorOrigen[$origen] ?? 'codigos', null);
    } elseif ($accion === 'regenerar_codigos') {
        $algunMetodoActivo = $activo || $activoCorreo || $activoWhatsapp || $activoRostro;
        if (!$algunMetodoActivo) {
            $error = 'Primero activa la verificación en 2 pasos.';
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (!password_verify((string) ($_POST['password'] ?? ''), $usuario['password_hash'])) {
            registrarIntento2FAFallido($pdo, $uid);
            $error = mensajeBloqueo2FA($pdo, $uid) ?: 'La contraseña no es correcta.';
        } else {
            limpiarIntentos2FA($pdo, $uid);
            $_SESSION['codigos_2fa_pendientes'] = VerificacionDosPasos::generarLote($pdo, $uid) + ['origen' => 'regenerar'];
            auditarAccion($pdo, 'regenerar_codigos_recuperacion_2fa', 'usuarios', $uid);
            redirigirSeguridad();
        }
    } elseif ($accion === 'desactivar') {
        if (!$activo) {
            redirigirSeguridad();
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (
            !password_verify((string) ($_POST['password'] ?? ''), $usuario['password_hash'])
            || !VerificacionDosPasos::verificarCualquiera($pdo, $usuario, (string) ($_POST['codigo'] ?? ''))
        ) {
            registrarIntento2FAFallido($pdo, $uid);
            $error = mensajeBloqueo2FA($pdo, $uid) ?: 'La contraseña o el código no son correctos.';
        } else {
            VerificacionDosPasos::desactivar($pdo, $uid);
            unset($_SESSION['codigos_2fa_pendientes']);
            auditarAccion($pdo, 'desactivar_2fa', 'usuarios', $uid);
            redirigirSeguridad('desactivada', null);
        }

    // ---------- Verificación por correo electrónico ----------
    } elseif ($accion === 'correo_iniciar_activacion') {
        if ($activoCorreo) {
            $error = 'La verificación por correo ya está activa.';
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (VerificacionDosPasosCorreo::segundosParaReenvio($pdo, $uid) > 0) {
            // Ya se envió un código hace muy poco: no se manda otro (evita spam al correo).
            $_SESSION['correo_2fa_activando'] = $uid;
            redirigirSeguridad('', 'correo');
        } elseif (VerificacionDosPasosCorreo::generarYEnviar($pdo, $uid, $usuario['correo']) === null) {
            $error = 'No pudimos enviarte el código al correo. Inténtalo de nuevo en unos minutos.';
        } else {
            $_SESSION['correo_2fa_activando'] = $uid;
            redirigirSeguridad('', 'correo');
        }
    } elseif ($accion === 'correo_reenviar') {
        if ($activoCorreo) {
            redirigirSeguridad('', 'correo');
        } elseif (VerificacionDosPasosCorreo::segundosParaReenvio($pdo, $uid) <= 0) {
            VerificacionDosPasosCorreo::generarYEnviar($pdo, $uid, $usuario['correo']);
            redirigirSeguridad('correo_reenviado', 'correo');
        } else {
            redirigirSeguridad('', 'correo');
        }
    } elseif ($accion === 'correo_confirmar_activacion') {
        if ($activoCorreo) {
            redirigirSeguridad('', 'correo');
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (VerificacionDosPasosCorreo::verificarCodigo($pdo, $uid, (string) ($_POST['codigo'] ?? ''))) {
            limpiarIntentos2FA($pdo, $uid);
            $lote = VerificacionDosPasosCorreo::activar($pdo, $uid);
            auditarAccion($pdo, 'activar_2fa_correo', 'usuarios', $uid);
            unset($_SESSION['correo_2fa_activando']);
            if ($lote !== null) {
                $_SESSION['codigos_2fa_pendientes'] = $lote + ['origen' => 'activacion_correo'];
            }
            redirigirSeguridad();
        } else {
            registrarIntento2FAFallido($pdo, $uid);
            $error = mensajeBloqueo2FA($pdo, $uid) ?: 'Ese código no es correcto o ya venció.';
        }
    } elseif ($accion === 'correo_cancelar_activacion') {
        if (!$activoCorreo) VerificacionDosPasosCorreo::cancelarActivacion($pdo, $uid);
        unset($_SESSION['correo_2fa_activando']);
        redirigirSeguridad('', null);
    } elseif ($accion === 'correo_desactivar') {
        if (!$activoCorreo) {
            redirigirSeguridad('', null);
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (!password_verify((string) ($_POST['password'] ?? ''), $usuario['password_hash'])) {
            registrarIntento2FAFallido($pdo, $uid);
            $error = mensajeBloqueo2FA($pdo, $uid) ?: 'La contraseña no es correcta.';
        } else {
            VerificacionDosPasosCorreo::desactivar($pdo, $uid);
            auditarAccion($pdo, 'desactivar_2fa_correo', 'usuarios', $uid);
            redirigirSeguridad('correo_desactivada', null);
        }

    // ---------- Verificación por WhatsApp (CallMeBot) ----------
    } elseif ($accion === 'whatsapp_guardar_credenciales') {
        $numero = whatsappNormalizarNumero((string) ($_POST['whatsapp_numero'] ?? ''));
        $apikey = trim((string) ($_POST['whatsapp_apikey'] ?? ''));
        if ($activoWhatsapp) {
            $error = 'Desactiva primero la verificación por WhatsApp para cambiar tu número o tu apikey.';
        } elseif (!whatsappNumeroValido($numero)) {
            $error = 'Escribe tu número con el indicativo del país (ej. +57 300 1234567) o tu identificador @lid si CallMeBot te dio uno.';
        } elseif (!whatsappApikeyValida($apikey)) {
            $error = 'La apikey que te envió CallMeBot solo lleva letras y números (por ejemplo 123456).';
        } else {
            VerificacionDosPasosWhatsapp::guardarCredenciales($pdo, $uid, $numero, $apikey);
            redirigirSeguridad('whatsapp_conectado', 'whatsapp');
        }
    } elseif ($accion === 'whatsapp_borrar_credenciales') {
        if ($activoWhatsapp) {
            $error = 'Desactiva primero la verificación por WhatsApp para borrar tu número y tu apikey.';
        } else {
            VerificacionDosPasosWhatsapp::cancelarActivacion($pdo, $uid);
            VerificacionDosPasosWhatsapp::borrarCredenciales($pdo, $uid);
            unset($_SESSION['whatsapp_2fa_activando']);
            redirigirSeguridad('whatsapp_borrado', 'whatsapp');
        }
    } elseif ($accion === 'whatsapp_iniciar_activacion') {
        if ($activoWhatsapp) {
            $error = 'La verificación por WhatsApp ya está activa.';
        } elseif (!VerificacionDosPasosWhatsapp::tieneCredencialesGuardadas($usuario)) {
            $error = 'Primero conecta tu número con CallMeBot (paso 1).';
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (VerificacionDosPasosWhatsapp::segundosParaReenvio($pdo, $uid) > 0) {
            // Ya se envió un código hace muy poco: no se manda otro.
            $_SESSION['whatsapp_2fa_activando'] = $uid;
            redirigirSeguridad('', 'whatsapp');
        } elseif (VerificacionDosPasosWhatsapp::generarYEnviar($pdo, $usuario) === null) {
            $error = 'No pudimos enviarte el código por WhatsApp. Revisa que tu número y tu apikey de CallMeBot sean correctos e inténtalo de nuevo.';
        } else {
            $_SESSION['whatsapp_2fa_activando'] = $uid;
            redirigirSeguridad('', 'whatsapp');
        }
    } elseif ($accion === 'whatsapp_reenviar') {
        if ($activoWhatsapp) {
            redirigirSeguridad('', 'whatsapp');
        } elseif (VerificacionDosPasosWhatsapp::segundosParaReenvio($pdo, $uid) <= 0) {
            VerificacionDosPasosWhatsapp::generarYEnviar($pdo, $usuario);
            redirigirSeguridad('whatsapp_reenviado', 'whatsapp');
        } else {
            redirigirSeguridad('', 'whatsapp');
        }
    } elseif ($accion === 'whatsapp_confirmar_activacion') {
        if ($activoWhatsapp) {
            redirigirSeguridad('', 'whatsapp');
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (VerificacionDosPasosWhatsapp::verificarCodigo($pdo, $uid, (string) ($_POST['codigo'] ?? ''))) {
            limpiarIntentos2FA($pdo, $uid);
            $lote = VerificacionDosPasosWhatsapp::activar($pdo, $uid);
            auditarAccion($pdo, 'activar_2fa_whatsapp', 'usuarios', $uid);
            unset($_SESSION['whatsapp_2fa_activando']);
            if ($lote !== null) {
                $_SESSION['codigos_2fa_pendientes'] = $lote + ['origen' => 'activacion_whatsapp'];
            }
            redirigirSeguridad();
        } else {
            registrarIntento2FAFallido($pdo, $uid);
            $error = mensajeBloqueo2FA($pdo, $uid) ?: 'Ese código no es correcto o ya venció.';
        }
    } elseif ($accion === 'whatsapp_cancelar_activacion') {
        if (!$activoWhatsapp) VerificacionDosPasosWhatsapp::cancelarActivacion($pdo, $uid);
        unset($_SESSION['whatsapp_2fa_activando']);
        redirigirSeguridad('', null);
    } elseif ($accion === 'whatsapp_desactivar') {
        if (!$activoWhatsapp) {
            redirigirSeguridad('', null);
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (!password_verify((string) ($_POST['password'] ?? ''), $usuario['password_hash'])) {
            registrarIntento2FAFallido($pdo, $uid);
            $error = mensajeBloqueo2FA($pdo, $uid) ?: 'La contraseña no es correcta.';
        } else {
            VerificacionDosPasosWhatsapp::desactivar($pdo, $uid);
            auditarAccion($pdo, 'desactivar_2fa_whatsapp', 'usuarios', $uid);
            redirigirSeguridad('whatsapp_desactivada', null);
        }

    // ---------- Verificación por reconocimiento facial ----------
    } elseif ($accion === 'rostro_iniciar_activacion') {
        if ($activoRostro) {
            $error = 'El reconocimiento facial ya está activo.';
        } elseif (empty($_POST['consentimiento'])) {
            $error = 'Para continuar tienes que aceptar el uso de tus datos faciales.';
        } else {
            // La bandera marca que ya aceptó y está en el paso de la cámara.
            $_SESSION['rostro_2fa_activando'] = $uid;
            redirigirSeguridad('', 'rostro');
        }
    } elseif ($accion === 'rostro_confirmar_activacion') {
        if ($activoRostro) {
            redirigirSeguridad('', 'rostro');
        } elseif (($_SESSION['rostro_2fa_activando'] ?? null) !== $uid) {
            $error = 'Acepta primero el uso de tus datos faciales para continuar.';
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (($lote = VerificacionDosPasosRostro::activar($pdo, $uid, (string) ($_POST['muestras'] ?? ''))) !== null) {
            auditarAccion($pdo, 'activar_2fa_rostro', 'usuarios', $uid);
            unset($_SESSION['rostro_2fa_activando']);
            $_SESSION['codigos_2fa_pendientes'] = $lote + ['origen' => 'activacion_rostro'];
            redirigirSeguridad();
        } else {
            $error = 'No pudimos registrar tu rostro. Las tres capturas deben ser de la misma persona, de frente y con buena luz. Inténtalo de nuevo.';
        }
    } elseif ($accion === 'rostro_cancelar_activacion') {
        unset($_SESSION['rostro_2fa_activando']);
        redirigirSeguridad('', null);
    } elseif ($accion === 'rostro_desactivar') {
        if (!$activoRostro) {
            redirigirSeguridad('', null);
        } elseif ($bloqueo !== '') {
            $error = $bloqueo;
        } elseif (!password_verify((string) ($_POST['password'] ?? ''), $usuario['password_hash'])) {
            registrarIntento2FAFallido($pdo, $uid);
            $error = mensajeBloqueo2FA($pdo, $uid) ?: 'La contraseña no es correcta.';
        } else {
            VerificacionDosPasosRostro::desactivar($pdo, $uid);
            auditarAccion($pdo, 'desactivar_2fa_rostro', 'usuarios', $uid);
            redirigirSeguridad('rostro_desactivada', null);
        }
    }
}

// --- Estado para pintar la pantalla ---
$usuario = Usuario::buscarPorId($pdo, $uid);
$activo = $usuario && VerificacionDosPasos::estaActivo($usuario);
$activoCorreo = $usuario && VerificacionDosPasosCorreo::estaActivo($usuario);
$activoWhatsapp = $usuario && VerificacionDosPasosWhatsapp::estaActivo($usuario);
$activoRostro = $usuario && VerificacionDosPasosRostro::estaActivo($usuario);
// Cualquiera de los 4 métodos genera y comparte el mismo lote de códigos de
// recuperación — así que "¿hay que seguir mostrando los códigos / el estado de
// los códigos?" depende de si ALGUNO está activo, no solo TOTP.
$algunMetodoActivo = $activo || $activoCorreo || $activoWhatsapp || $activoRostro;
$pendientes = $_SESSION['codigos_2fa_pendientes'] ?? null;
if ($pendientes !== null && (!$algunMetodoActivo || $viendoComo)) {
    unset($_SESSION['codigos_2fa_pendientes']);
    $pendientes = null;
}
$secretoPendiente = (!$activo && !$viendoComo) ? VerificacionDosPasos::secretoPendiente($pdo, $uid) : null;
$estadoCodigos = ($algunMetodoActivo && !$viendoComo) ? VerificacionDosPasos::estadoCodigos($pdo, $uid) : null;

// Una activación de correo/WhatsApp queda "en curso" desde que se envía el
// primer código hasta que se confirma o se cancela. La marca de sesión solo se
// pone al enviar (en el POST de arriba), así que entrar a ?metodo=... a mirar
// NO deja la pantalla atrapada en "escribe el código". El respaldo por tiempo
// (segundosParaReenvio > 0) cubre el caso de sesión nueva con un código vivo.
$correoActivacionPendiente = (!$activoCorreo && !$viendoComo)
    && (($_SESSION['correo_2fa_activando'] ?? null) === $uid
        || VerificacionDosPasosCorreo::segundosParaReenvio($pdo, $uid) > 0);
$whatsappActivacionPendiente = (!$activoWhatsapp && !$viendoComo)
    && (($_SESSION['whatsapp_2fa_activando'] ?? null) === $uid
        || VerificacionDosPasosWhatsapp::segundosParaReenvio($pdo, $uid) > 0);
// Rostro: "en curso" = ya aceptó el consentimiento y está en el paso de la cámara.
$rostroActivacionPendiente = (!$activoRostro && !$viendoComo)
    && ($_SESSION['rostro_2fa_activando'] ?? null) === $uid;
if ($activoCorreo) unset($_SESSION['correo_2fa_activando']);
if ($activoWhatsapp) unset($_SESSION['whatsapp_2fa_activando']);
if ($activoRostro) unset($_SESSION['rostro_2fa_activando']);

$bloqueoActual = $viendoComo ? '' : mensajeBloqueo2FA($pdo, $uid);
if ($error === '' && $bloqueoActual !== '') {
    $error = $bloqueoActual;
}

function fechaCorta(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '—';
}

$accionesTotp = ['iniciar_activacion', 'cancelar_activacion', 'confirmar_activacion', 'regenerar_codigos', 'desactivar'];
$accionesCorreo = ['correo_iniciar_activacion', 'correo_reenviar', 'correo_confirmar_activacion', 'correo_cancelar_activacion', 'correo_desactivar'];
$accionesWhatsapp = ['whatsapp_guardar_credenciales', 'whatsapp_borrar_credenciales', 'whatsapp_iniciar_activacion', 'whatsapp_reenviar', 'whatsapp_confirmar_activacion', 'whatsapp_cancelar_activacion', 'whatsapp_desactivar'];
$accionesRostro = ['rostro_iniciar_activacion', 'rostro_confirmar_activacion', 'rostro_cancelar_activacion', 'rostro_desactivar'];
$metodoGet = $_GET['metodo'] ?? '';
$esPost = !$viendoComo && $_SERVER['REQUEST_METHOD'] === 'POST';
$accionPost = $_POST['accion'] ?? '';

// Qué método se está viendo. Los códigos pendientes por mostrar, un error dentro
// del flujo o una acción POST propia del método cuentan como "estar dentro" de él
// aunque no venga ?metodo= (por ejemplo, tras un código incorrecto).
$verTotp = $metodoGet === 'totp'
    || $pendientes !== null
    || ($secretoPendiente !== null && !$activo)
    || ($esPost && in_array($accionPost, $accionesTotp, true));
$verCorreo = !$verTotp && (
    $metodoGet === 'correo'
    || $correoActivacionPendiente
    || ($esPost && in_array($accionPost, $accionesCorreo, true))
);
$verWhatsapp = !$verTotp && !$verCorreo && (
    $metodoGet === 'whatsapp'
    || $whatsappActivacionPendiente
    || ($esPost && in_array($accionPost, $accionesWhatsapp, true))
);
$verRostro = !$verTotp && !$verCorreo && !$verWhatsapp && (
    $metodoGet === 'rostro'
    || $rostroActivacionPendiente
    || ($esPost && in_array($accionPost, $accionesRostro, true))
);
$verPanel = !$verTotp && !$verCorreo && !$verWhatsapp && !$verRostro;

$tituloPagina = 'Seguridad';
$cssExtra = ['/assets/css/seguridad.css'];
$jsExtra = ['/assets/js/qrcode.js', '/assets/js/seguridad.js'];
if ($verRostro && !$viendoComo && $usuario && !$activoRostro) {
    $jsExtra[] = '/assets/js/rostro.js';
}
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Seguridad de la cuenta</h1>
<?php if ($error): ?><p class="mensaje mensaje-error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito" role="status"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<?php if ($verPanel): ?>
<section class="rejilla-metodos" aria-label="Métodos de seguridad disponibles">

    <article class="tarjeta-metodo">
        <div class="tarjeta-metodo-icono">🔒</div>
        <h2>Cambiar contraseña</h2>
        <p>Actualiza tu contraseña de acceso periódicamente para mantener la integridad de tu cuenta. Recomendamos el uso de frases secretas.</p>
        <a href="<?= BASE_URL ?>/<?= htmlspecialchars((string) $_SESSION['rol'], ENT_QUOTES, 'UTF-8') ?>/perfil.php" class="tarjeta-metodo-enlace">Configurar →</a>
    </article>

    <article class="tarjeta-metodo">
        <span class="pill <?= $activoCorreo ? 'pill-exito' : 'pill-neutro' ?> tarjeta-metodo-estado"><?= $activoCorreo ? 'Activo' : 'Inactivo' ?></span>
        <div class="tarjeta-metodo-icono">✉️</div>
        <h2>Correo electrónico</h2>
        <p>Utiliza tu dirección de correo electrónico principal para recibir códigos de verificación y alertas de inicio de sesión sospechosos.</p>
        <a href="<?= BASE_URL ?>/seguridad.php?metodo=correo" class="tarjeta-metodo-enlace">Configurar →</a>
    </article>

    <article class="tarjeta-metodo">
        <span class="pill <?= $activoRostro ? 'pill-exito' : 'pill-neutro' ?> tarjeta-metodo-estado"><?= $activoRostro ? 'Activo' : 'Inactivo' ?></span>
        <div class="tarjeta-metodo-icono">🙂</div>
        <h2>Reconocimiento facial</h2>
        <p>La validación biométrica es el estándar de oro en seguridad digital. Utiliza la cámara de tu dispositivo para verificar tu identidad de forma instantánea.</p>
        <a href="<?= BASE_URL ?>/seguridad.php?metodo=rostro" class="tarjeta-metodo-enlace">Configurar →</a>
    </article>

    <article class="tarjeta-metodo">
        <span class="pill <?= $activo ? 'pill-exito' : 'pill-neutro' ?> tarjeta-metodo-estado"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
        <div class="tarjeta-metodo-icono">🔑</div>
        <h2>Google Authenticator</h2>
        <p>Sincroniza tu cuenta con aplicaciones de autenticación de terceros para generar códigos 2FA offline y mejorar la protección.</p>
        <a href="<?= BASE_URL ?>/seguridad.php?metodo=totp" class="tarjeta-metodo-enlace">Configurar →</a>
    </article>

    <article class="tarjeta-metodo">
        <span class="pill <?= $activoWhatsapp ? 'pill-exito' : 'pill-neutro' ?> tarjeta-metodo-estado"><?= $activoWhatsapp ? 'Activo' : 'Inactivo' ?></span>
        <div class="tarjeta-metodo-icono">💬</div>
        <h2>WhatsApp</h2>
        <p>Opción de respaldo: recibe códigos temporales por WhatsApp usando un servicio externo gratuito (CallMeBot), sin garantía de entrega.</p>
        <a href="<?= BASE_URL ?>/seguridad.php?metodo=whatsapp" class="tarjeta-metodo-enlace">Configurar →</a>
    </article>

</section>

<?php elseif ($verTotp): ?>
<p class="volver-metodos"><a href="<?= BASE_URL ?>/seguridad.php">← Volver a Seguridad</a></p>
<?php if ($viendoComo): ?>
<section class="panel-seccion seccion-2fa">
    <h2>No disponible en "Ver como usuario"</h2>
    <p class="texto-suave">
        La seguridad de una cuenta solo la puede cambiar su dueño desde su propia sesión.
        Vuelve a tu cuenta de administrador para gestionar la tuya.
    </p>
</section>

<?php elseif ($pendientes !== null): ?>
<?php
    $codigosPend = $pendientes['codigos'];
    $diasVigencia = VerificacionDosPasos::DIAS_VIGENCIA;
    $titulosMotivo = [
        'agotados' => 'Se te acabaron los códigos de recuperación. Aquí tienes un juego nuevo.',
        'vencidos' => 'Tus códigos de recuperación vencieron. Aquí tienes un juego nuevo.',
        'nuevos'   => 'Aquí tienes tus códigos de recuperación.',
    ];
    $intro = $titulosMotivo[$pendientes['motivo'] ?? ''] ?? 'Guarda estos códigos de recuperación.';
?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-codigos">
    <h2 id="titulo-codigos">Guarda tus códigos de recuperación</h2>
    <p><?= htmlspecialchars($intro) ?></p>
    <p class="texto-suave">
        Sirven si pierdes tu teléfono. Cada código funciona <strong>una sola vez</strong> y todos vencen el
        <strong><?= fechaCorta($pendientes['expira_en']) ?></strong> (<?= $diasVigencia ?> días).
        Esta es la única vez que se muestran.
    </p>

    <ul class="codigos-2fa" data-codigos aria-label="Códigos de recuperación">
        <?php foreach ($codigosPend as $codigo): ?>
            <li><code><?= htmlspecialchars($codigo) ?></code></li>
        <?php endforeach; ?>
    </ul>

    <div class="acciones-2fa">
        <button type="button" class="boton-secundario" data-copiar-codigos>Copiar</button>
        <button type="button" class="boton-secundario" data-descargar-codigos
                data-cuenta="<?= htmlspecialchars($usuario['correo']) ?>"
                data-vence="<?= htmlspecialchars(fechaCorta($pendientes['expira_en'])) ?>">Descargar .txt</button>
        <button type="button" class="boton-secundario" data-imprimir>Imprimir</button>
    </div>

    <form action="seguridad.php" method="POST" class="cierre-codigos" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="codigos_guardados">
        <label class="opcion-consentimiento confirmacion-codigos">
            <input type="checkbox" data-confirma-codigos>
            <span>Ya guardé mis códigos en un lugar seguro.</span>
        </label>
        <button type="submit" data-boton-confirma>Listo</button>
    </form>
</section>

<?php elseif (!$activo && $secretoPendiente !== null): ?>
<?php $uriTotp = totpUriAprovisionamiento($secretoPendiente, $usuario['correo']); ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-activar">
    <h2 id="titulo-activar">Activar verificación en 2 pasos</h2>
    <ol class="pasos-2fa">
        <li>
            <strong>Instala una app de autenticación</strong>
            <span class="texto-suave">Google Authenticator, Microsoft Authenticator, Authy o 2FAS.</span>
        </li>
        <li>
            <strong>Escanea este código QR con la app</strong>
            <div class="qr-2fa" data-qr-uri="<?= htmlspecialchars($uriTotp) ?>" role="img" aria-label="Código QR para agregar Serena a tu app de autenticación"></div>
            <noscript><p class="mensaje mensaje-aviso">Para ver el código QR activa JavaScript, o usa la clave manual de abajo.</p></noscript>
            <details class="bloque-plegable">
                <summary>¿No puedes escanear? Escribe la clave a mano</summary>
                <p class="texto-suave">En tu app elige "Ingresar clave de configuración", con tu correo como cuenta y esta clave (tipo: basada en tiempo).</p>
                <div class="clave-manual">
                    <code data-clave-manual><?= htmlspecialchars(totpClaveAgrupada($secretoPendiente)) ?></code>
                    <button type="button" class="boton-secundario" data-copiar-clave>Copiar</button>
                </div>
            </details>
        </li>
        <li>
            <strong>Escribe el código de 6 dígitos que muestra la app</strong>
            <form action="seguridad.php" method="POST" class="formulario-codigo" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="confirmar_activacion">
                <label for="codigo-activar" class="sr-only">Código de 6 dígitos</label>
                <input type="text" id="codigo-activar" name="codigo" class="campo-codigo"
                       inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code"
                       placeholder="000000" data-codigo-2fa required>
                <button type="submit">Verificar y activar</button>
            </form>
        </li>
    </ol>
    <form action="seguridad.php" method="POST" class="cancelar-2fa" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="cancelar_activacion">
        <button type="submit" class="boton-secundario">Cancelar</button>
    </form>
</section>

<?php elseif (!$activo): ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-intro">
    <div class="encabezado-2fa">
        <h2 id="titulo-intro">Verificación en 2 pasos</h2>
        <span class="estado-2fa estado-2fa-off">Desactivada</span>
    </div>
    <p>
        Además de tu contraseña, te pediremos un código de 6 dígitos de tu teléfono al iniciar sesión.
        Así, aunque alguien conozca tu contraseña, no podrá entrar a tu cuenta.
    </p>
    <ul class="beneficios-2fa">
        <li>Funciona con Google Authenticator y otras apps gratuitas.</li>
        <li>Recibes 10 códigos de recuperación por si pierdes el teléfono.</li>
        <li>Tarda unos 2 minutos.</li>
    </ul>
    <form action="seguridad.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="iniciar_activacion">
        <button type="submit">Activar verificación en 2 pasos</button>
    </form>
</section>

<?php else: ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-estado">
    <div class="encabezado-2fa">
        <h2 id="titulo-estado">Verificación en 2 pasos</h2>
        <span class="estado-2fa estado-2fa-on">Activada</span>
    </div>
    <p class="texto-suave">
        Activa desde el <?= fechaCorta($usuario['totp_activado_en']) ?>. Al iniciar sesión te pediremos el código de tu app de autenticación.
    </p>
</section>

<?php require __DIR__ . '/parciales/estado_codigos_recuperacion.php'; ?>

<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-desactivar">
    <h2 id="titulo-desactivar">Desactivar</h2>
    <details class="bloque-plegable">
        <summary>Desactivar verificación en 2 pasos</summary>
        <p class="texto-suave">
            Tu cuenta volverá a protegerse solo con la contraseña y se borrarán tus códigos de recuperación.
            Para confirmar, escribe tu contraseña y un código de tu app (o uno de recuperación).
        </p>
        <form action="seguridad.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="desactivar">
            <label for="password-desactivar">Contraseña</label>
            <input type="password" id="password-desactivar" name="password" autocomplete="current-password" required>
            <label for="codigo-desactivar">Código de tu app o de recuperación</label>
            <input type="text" id="codigo-desactivar" name="codigo" autocomplete="one-time-code"
                   spellcheck="false" autocapitalize="characters" placeholder="000000" required>
            <button type="submit" class="boton-peligro">Desactivar verificación</button>
        </form>
    </details>
</section>
<?php endif; ?>

<?php elseif ($verCorreo): ?>
<p class="volver-metodos"><a href="<?= BASE_URL ?>/seguridad.php">← Volver a Seguridad</a></p>
<?php if ($viendoComo): ?>
<section class="panel-seccion seccion-2fa">
    <h2>No disponible en "Ver como usuario"</h2>
    <p class="texto-suave">
        La seguridad de una cuenta solo la puede cambiar su dueño desde su propia sesión.
        Vuelve a tu cuenta de administrador para gestionar la tuya.
    </p>
</section>

<?php elseif (!$activoCorreo && $correoActivacionPendiente): ?>
<?php $esperaReenvioSeg = VerificacionDosPasosCorreo::segundosParaReenvio($pdo, $uid); ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-activar-correo">
    <h2 id="titulo-activar-correo">Activar verificación por correo</h2>
    <p class="texto-suave">
        Te enviamos un código de 6 dígitos a <strong><?= htmlspecialchars($usuario['correo']) ?></strong>.
        Escríbelo aquí para confirmar. Vence en <?= VerificacionDosPasosCorreo::VIGENCIA_MINUTOS ?> minutos.
    </p>
    <form action="seguridad.php" method="POST" class="formulario-codigo" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="correo_confirmar_activacion">
        <label for="codigo-activar-correo" class="sr-only">Código de 6 dígitos</label>
        <input type="text" id="codigo-activar-correo" name="codigo" class="campo-codigo"
               inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code"
               placeholder="000000" data-codigo-2fa autofocus required>
        <button type="submit">Verificar y activar</button>
    </form>
    <form action="seguridad.php" method="POST" class="reenviar-2fa-correo" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="correo_reenviar">
        <button type="submit" class="boton-secundario" data-reenviar-2fa
                data-espera="<?= $esperaReenvioSeg ?>" <?= $esperaReenvioSeg > 0 ? 'disabled' : '' ?>>
            <?= $esperaReenvioSeg > 0 ? "Reenviar código ({$esperaReenvioSeg}s)" : 'Reenviar código' ?>
        </button>
    </form>
    <form action="seguridad.php" method="POST" class="cancelar-2fa" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="correo_cancelar_activacion">
        <button type="submit" class="boton-secundario">Cancelar</button>
    </form>
</section>

<?php elseif (!$activoCorreo): ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-intro-correo">
    <div class="encabezado-2fa">
        <h2 id="titulo-intro-correo">Verificación por correo</h2>
        <span class="estado-2fa estado-2fa-off">Desactivada</span>
    </div>
    <p>
        Además de tu contraseña, te pediremos un código de 6 dígitos que enviaremos a tu correo al iniciar sesión.
        Así, aunque alguien conozca tu contraseña, no podrá entrar a tu cuenta.
    </p>
    <ul class="beneficios-2fa">
        <li>No necesitas instalar ninguna app.</li>
        <li>El código llega a <?= htmlspecialchars($usuario['correo']) ?>.</li>
        <li>Tarda menos de un minuto.</li>
    </ul>
    <?php if ($activo): ?>
        <p class="mensaje mensaje-aviso">
            Ya tienes activa la verificación con Google Authenticator. Activar esta la reemplazará
            (solo puedes tener un método de verificación en 2 pasos activo a la vez).
        </p>
    <?php endif; ?>
    <form action="seguridad.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="correo_iniciar_activacion">
        <button type="submit">Activar verificación por correo</button>
    </form>
</section>

<?php else: ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-estado-correo">
    <div class="encabezado-2fa">
        <h2 id="titulo-estado-correo">Verificación por correo</h2>
        <span class="estado-2fa estado-2fa-on">Activada</span>
    </div>
    <p class="texto-suave">
        Activa desde el <?= fechaCorta($usuario['correo_2fa_activado_en']) ?>.
        Al iniciar sesión te enviaremos un código a <?= htmlspecialchars($usuario['correo']) ?>.
    </p>
</section>

<?php require __DIR__ . '/parciales/estado_codigos_recuperacion.php'; ?>

<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-desactivar-correo">
    <h2 id="titulo-desactivar-correo">Desactivar</h2>
    <details class="bloque-plegable">
        <summary>Desactivar verificación por correo</summary>
        <p class="texto-suave">
            Tu cuenta volverá a protegerse solo con la contraseña.
            Para confirmar, escribe tu contraseña.
        </p>
        <form action="seguridad.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="correo_desactivar">
            <label for="password-desactivar-correo">Contraseña</label>
            <input type="password" id="password-desactivar-correo" name="password" autocomplete="current-password" required>
            <button type="submit" class="boton-peligro">Desactivar verificación</button>
        </form>
    </details>
</section>
<?php endif; ?>

<?php elseif ($verWhatsapp): ?>
<?php require __DIR__ . '/parciales/seguridad_whatsapp.php'; ?>

<?php elseif ($verRostro): ?>
<?php require __DIR__ . '/parciales/seguridad_rostro.php'; ?>

<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
