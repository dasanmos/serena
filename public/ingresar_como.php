<?php
declare(strict_types=1);

// ¿Con qué rol quieres entrar? — para cuentas con varios roles (migración 044_multirol.sql).
// Ejemplo: una docente que también es madre de un estudiante entra como «Docente» o como «Familia».
// Se llega aquí al iniciar sesión (auth.php lo exige mientras no elija) y desde Cuenta → «Cambiar de rol».
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/impersonacion.php';
require_once __DIR__ . '/../src/modelos/RolesUsuario.php';

requiereSesion();

if (estaViendoComoOtro()) {
    require_once __DIR__ . '/../src/helpers/acceso_denegado.php';
    mostrarAccesoDenegado('Termina primero la vista como otro usuario.');
}

// Siempre se consulta la BD: la lista de la sesión es solo una pista.
$usuarioId = (int) $_SESSION['usuario_id'];
$roles = array_values(array_filter(RolesUsuario::roles($pdo, $usuarioId), static fn (string $r): bool => isset(RolesUsuario::NOMBRES[$r])));
$_SESSION['roles_disponibles'] = $roles;

if (count($roles) < 2) {
    // Ya no tiene varios roles (se los quitaron): entra con el que tenga.
    unset($_SESSION['elegir_rol_multiple']);
    if ($roles) { $_SESSION['rol'] = $roles[0]; }
    header('Location: ' . rutaDashboardPorRol((string) $_SESSION['rol']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $elegido = (string) ($_POST['rol'] ?? '');
    if (in_array($elegido, $roles, true)) {
        $anterior = (string) ($_SESSION['rol'] ?? '');
        $_SESSION['rol'] = $elegido;
        unset($_SESSION['elegir_rol_multiple']);
        $_SESSION['sesion_recien_iniciada'] = true;
        try {
            auditarAccion($pdo, 'cambiar_rol', 'usuarios', $usuarioId, ['rol' => $anterior], ['rol' => $elegido]);
        } catch (Throwable $e) {
            error_log('auditoría cambiar_rol: ' . $e->getMessage());
        }
        header('Location: ' . rutaDashboardPorRol($elegido));
        exit;
    }
}

$actual = empty($_SESSION['elegir_rol_multiple']) ? (string) ($_SESSION['rol'] ?? '') : '';
$partes = preg_split('/\s+/', trim((string) $_SESSION['nombre'])) ?: [];
$iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — ¿Con qué rol quieres entrar?</title>
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
                    <h2>Una cuenta, varios roles</h2>
                    <p>Tu cuenta tiene más de un rol en el colegio. Cada rol ve sus propias pantallas: elige con cuál quieres trabajar ahora.</p>
                </div>
                <ul class="login-puntos">
                    <li><span>🔁</span> Cambia de rol cuando quieras desde Cuenta</li>
                    <li><span>🔑</span> La misma contraseña para todos tus roles</li>
                    <li><span>📝</span> Cada cambio de rol queda registrado</li>
                </ul>
            </aside>
            <div class="login-form-lado">
                <span class="cuenta-maestra"><span class="iniciales" aria-hidden="true"><?= htmlspecialchars($iniciales) ?></span><?= htmlspecialchars((string) $_SESSION['nombre']) ?></span>
                <h1>¿Con qué rol quieres entrar?</h1>
                <p class="login-sub">Toca uno para continuar.</p>
                <form action="ingresar_como.php" method="POST" class="roles-rejilla roles-multiples">
                    <?php campoCSRF(); ?>
                    <?php foreach ($roles as $r): [$icono, $titulo, $detalle] = RolesUsuario::NOMBRES[$r]; ?>
                        <button type="submit" name="rol" value="<?= htmlspecialchars($r) ?>" class="rol-opcion rol-boton<?= $r === $actual ? ' rol-actual' : '' ?>">
                            <span class="rol-tarjeta">
                                <span class="rol-icono" aria-hidden="true"><?= $icono ?></span>
                                <span class="rol-texto"><strong><?= htmlspecialchars($titulo) ?></strong>
                                    <small><?= htmlspecialchars($detalle) ?></small>
                                    <?php if ($r === $actual): ?><small class="rol-chip">Rol actual</small><?php endif; ?></span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </form>
                <div class="enlaces-login">
                    <?php if ($actual !== ''): ?><a href="<?= htmlspecialchars(rutaDashboardPorRol($actual)) ?>">Volver sin cambiar</a><?php endif; ?>
                    <a href="logout.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/sonidos.js?v=<?= assetVersion('/assets/js/sonidos.js') ?>"></script>
</body>
</html>
