<?php
declare(strict_types=1);

// ¿A qué institución quieres entrar? — la misma persona (mismo documento y misma contraseña) tiene
// cuenta en más de un colegio. AuthControlador::login() deja aquí la lista de cuentas que ya
// comprobaron la contraseña; la elección caduca a los 5 minutos y solo vale para esas cuentas.
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/limiteIntentos.php';
require_once __DIR__ . '/../src/modelos/Usuario.php';
require_once __DIR__ . '/../src/modelos/RolesUsuario.php';
require_once __DIR__ . '/../src/controladores/AuthControlador.php';

$pendiente = $_SESSION['login_elegir_colegio'] ?? null;
if (!is_array($pendiente) || (int) ($pendiente['expira'] ?? 0) < time() || empty($pendiente['ids'])) {
    unset($_SESSION['login_elegir_colegio']);
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
$verificadas = array_map('intval', $pendiente['ids']);
$otras = array_map('intval', $pendiente['otros'] ?? []);
$ids = array_merge($verificadas, $otras);
$marcas = implode(',', array_fill(0, count($ids), '?'));
$q = $pdo->prepare("SELECT u.*, r.nombre AS rol, c.activo AS colegio_activo, c.nombre AS colegio_nombre, c.municipio AS colegio_municipio
                      FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id LEFT JOIN colegios c ON c.id = u.colegio_id
                     WHERE u.id IN ({$marcas}) ORDER BY c.nombre");
$q->execute($ids);
$cuentas = $q->fetchAll();

$error = '';
$errorCuenta = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $elegido = (int) ($_POST['cuenta'] ?? 0);
    $usuario = null;
    foreach ($cuentas as $c) {
        if ((int) $c['id'] === $elegido) { $usuario = $c; }
    }
    if ($usuario !== null && in_array($elegido, $otras, true)) {
        // Institución con otra contraseña: se comprueba la suya (con el mismo límite de intentos del login).
        $restante = segundosBloqueoLogin($pdo, $elegido);
        if ($restante > 0) {
            $error = 'Demasiados intentos fallidos en esa institución. Intenta de nuevo en ' . (int) ceil($restante / 60) . ' minuto(s).';
            $usuario = null;
        } elseif (!password_verify((string) ($_POST['password'] ?? ''), (string) $usuario['password_hash'])) {
            registrarIntentoLoginFallido($pdo, $elegido);
            $error = 'Contraseña incorrecta para esa institución.';
            $errorCuenta = $elegido;
            $usuario = null;
        } else {
            limpiarIntentosLogin($pdo, $elegido);
        }
    } elseif ($usuario === null) {
        $error = 'Elige una de las instituciones de la lista.';
    }
    if ($usuario !== null) {
        unset($_SESSION['login_elegir_colegio']);
        $r = AuthControlador::completarLogin($pdo, $usuario);
        if ($r['exito'] && !empty($r['requiere_2fa'])) {
            header('Location: ' . BASE_URL . '/verificacion_2pasos.php');
            exit;
        }
        if ($r['exito']) {
            header('Location: ' . rutaDashboardPorRol((string) $usuario['rol']));
            exit;
        }
        // Esa institución no está disponible (suspendida o cuenta inactiva): se puede elegir otra.
        $_SESSION['login_elegir_colegio'] = $pendiente;
        $error = $r['mensaje'];
    }
}
$nombre = (string) ($cuentas[0]['nombre'] ?? '');
$partes = preg_split('/\s+/', trim($nombre)) ?: [];
$iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — ¿A qué institución quieres entrar?</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="assets/css/elegir_rol.css?v=<?= assetVersion('/assets/css/elegir_rol.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro elegir-rol">
    <div class="contenedor-login">
        <div class="card-login">
            <aside class="login-marca-lado">
                <div>
                    <a href="index.php"><img src="assets/img/logo/logo.png" alt="Serena" class="logo-login"></a>
                    <h2>Una persona, varias instituciones</h2>
                    <p>Tu documento está registrado en más de una institución. Elige en cuál quieres trabajar ahora.</p>
                </div>
                <ul class="login-puntos">
                    <li><span>🏫</span> Cada institución tiene sus propios datos</li>
                    <li><span>🔁</span> Para cambiar, cierra sesión y vuelve a entrar</li>
                    <li><span>🔒</span> Esta elección vence en 5 minutos</li>
                </ul>
            </aside>
            <div class="login-form-lado">
                <span class="cuenta-maestra"><span class="iniciales" aria-hidden="true"><?= htmlspecialchars($iniciales) ?></span><?= htmlspecialchars($nombre) ?></span>
                <h1>¿A qué institución quieres entrar?</h1>
                <p class="login-sub">Toca una para continuar.</p>
                <?php if ($error !== ''): ?><p class="mensaje mensaje-error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
                <form action="elegir_colegio.php" method="POST" class="roles-rejilla roles-multiples">
                    <?php campoCSRF(); ?>
                    <?php foreach ($cuentas as $c):
                        if (!in_array((int) $c['id'], $verificadas, true)) { continue; }
                        $rolesC = array_map(static fn (string $r): string => RolesUsuario::NOMBRES[$r][1] ?? ucfirst($r), RolesUsuario::roles($pdo, (int) $c['id']));
                        $suspendido = $c['colegio_id'] !== null && (int) $c['colegio_activo'] !== 1; ?>
                        <button type="submit" name="cuenta" value="<?= (int) $c['id'] ?>" class="rol-opcion rol-boton">
                            <span class="rol-tarjeta">
                                <span class="rol-icono" aria-hidden="true">🏫</span>
                                <span class="rol-texto"><strong><?= htmlspecialchars((string) ($c['colegio_nombre'] ?? 'Plataforma Serena')) ?></strong>
                                    <small><?= htmlspecialchars(trim(($c['colegio_municipio'] ?? '') . ' · ' . implode(', ', $rolesC), ' ·')) ?></small>
                                    <?php if ($suspendido): ?><small class="rol-chip">Acceso suspendido</small><?php endif; ?></span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </form>
                <?php foreach ($cuentas as $c):
                    if (!in_array((int) $c['id'], $otras, true)) { continue; }
                    $rolesC = array_map(static fn (string $r): string => RolesUsuario::NOMBRES[$r][1] ?? ucfirst($r), RolesUsuario::roles($pdo, (int) $c['id'])); ?>
                <form action="elegir_colegio.php" method="POST" class="colegio-con-clave">
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="cuenta" value="<?= (int) $c['id'] ?>">
                    <span class="rol-tarjeta">
                        <span class="rol-icono" aria-hidden="true">🔑</span>
                        <span class="rol-texto"><strong><?= htmlspecialchars((string) ($c['colegio_nombre'] ?? 'Plataforma Serena')) ?></strong>
                            <small><?= htmlspecialchars(trim(($c['colegio_municipio'] ?? '') . ' · ' . implode(', ', $rolesC), ' ·')) ?> · tiene otra contraseña</small></span>
                    </span>
                    <div class="colegio-clave-fila">
                        <label class="sr-only" for="clave-<?= (int) $c['id'] ?>">Contraseña de esta institución</label>
                        <input type="password" id="clave-<?= (int) $c['id'] ?>" name="password" placeholder="Contraseña de esta institución" autocomplete="current-password" required<?= $errorCuenta === (int) $c['id'] ? ' autofocus aria-invalid="true"' : '' ?>>
                        <button type="submit" class="boton-entrar-colegio">Entrar</button>
                    </div>
                </form>
                <?php endforeach; ?>
                <div class="enlaces-login"><a href="login.php">Volver al inicio de sesión</a></div>
            </div>
        </div>
    </div>
    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
</body>
</html>
