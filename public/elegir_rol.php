<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/impersonacion.php';
require_once __DIR__ . '/../src/helpers/secretaria.php';
require_once __DIR__ . '/../src/modelos/Colegio.php';
require_once __DIR__ . '/../src/helpers/demo_guion.php';

requiereSesion();

// Solo el usuario MAESTRO (superadmin con es_maestro = 1). Se valida contra la BD,
// no solo contra la sesión.
function esMaestroReal(PDO $pdo): bool
{
    if (($_SESSION['rol_real'] ?? '') !== 'superadmin' || empty($_SESSION['usuario_id'])) {
        return false;
    }
    $q = $pdo->prepare(
        "SELECT 1 FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
         WHERE u.id = :id AND u.activo = 1 AND u.es_maestro = 1 AND r.nombre = 'superadmin'"
    );
    $q->execute(['id' => (int) $_SESSION['usuario_id']]);
    return (bool) $q->fetchColumn();
}

if (!esMaestroReal($pdo) || estaViendoComoOtro()) {
    require_once __DIR__ . '/../src/helpers/acceso_denegado.php';
    mostrarAccesoDenegado('Esta pantalla es solo para la cuenta maestra.');
}

// Llegar aquí (login o "Cambiar de rol") devuelve la sesión a la identidad base de superadmin.
$_SESSION['rol'] = 'superadmin';
$_SESSION['colegio_id'] = null;
$_SESSION['grupo_id'] = null;
$_SESSION['grupos_ids'] = [];
unset($_SESSION['colegio_contexto']);
$_SESSION['maestro_elegir_rol'] = true;

// Roles con los que se puede entrar a un colegio REAL (los que existan en la BD), agrupados como en la demo.
// Estudiante y acudiente no están aquí: necesitan un grupo y vínculos familiares, así que solo se presentan en el modo demostración.
$ROLES = [
    'superadmin'           => ['🛡️', 'Superadministrador',    'Vista global de todos los colegios'],
    'secretaria'           => ['🏢', 'Secretaría de Educación', 'Colegios de la secretaría asociada a tu cuenta'],
    'administrador'        => ['⚙️', 'Administrador',          'Gestión de un colegio'],
    'rector'               => ['🏛️', 'Rector',                 'Vista institucional'],
    'secretaria_academica' => ['🗂️', 'Secretaría académica',   'Inscripciones, matrículas, boletines y SIMAT'],
    'coordinador'          => ['📋', 'Coordinador',            'Convivencia, asistencia y horarios'],
    'psicologo'            => ['🧠', 'Psicólogo/a',            'Atención y seguimiento'],
    'orientador'           => ['🧭', 'Orientador/a',           'Alertas y grupos'],
    'docente'              => ['📚', 'Docente',                'Clase, notas y pulso del grupo'],
];
$existentes = $pdo->query('SELECT nombre FROM roles')->fetchAll(PDO::FETCH_COLUMN);
$ROLES = array_filter($ROLES, fn($k) => in_array($k, $existentes, true), ARRAY_FILTER_USE_KEY);
$FAMILIAS = [
    'Plataforma y territorio' => ['superadmin', 'secretaria'],
    'Dirección y administración' => ['administrador', 'rector', 'secretaria_academica', 'coordinador'],
    'Bienestar y aula' => ['psicologo', 'orientador', 'docente'],
];
// Solo en la demo (con datos ficticios): lo que ven los estudiantes y las familias.
$SOLO_DEMO = [
    'estudiante' => ['🎓', 'Estudiante', 'Su espacio de bienestar y notas'],
    'acudiente'  => ['👨‍👩‍👧', 'Acudiente', 'Boletín, asistencia y horario de sus hijos'],
];
$conteosModulos = demoConteos();

$colegios = Colegio::listarTodos($pdo, true);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? null);
    $rol = (string) ($_POST['rol'] ?? '');
    $colegioId = (int) ($_POST['colegio_id'] ?? 0);

    if (!isset($ROLES[$rol])) {
        $error = 'Elige un rol.';
    } elseif (!in_array($rol, ['superadmin', 'secretaria'], true) && !in_array($colegioId, array_map(fn($c) => (int) $c['id'], $colegios), true)) {
        $error = 'Elige el colegio con el que quieres entrar.';
    } elseif ($rol === 'secretaria' && !$pdo->query(
        "SELECT 1 FROM usuarios u INNER JOIN secretarias s ON s.id = u.secretaria_id
         WHERE s.activa = 1 AND u.id = " . (int) $_SESSION['usuario_id']
    )->fetchColumn()) {
        $error = 'Tu cuenta aún no está asociada a ninguna Secretaría de Educación activa. Asóciala desde Secretarías (o con la migración 031).';
    } else {
        $esGlobal = in_array($rol, ['superadmin', 'secretaria'], true);
        $_SESSION['rol'] = $rol;
        $_SESSION['colegio_id'] = $esGlobal ? null : $colegioId;
        unset($_SESSION['maestro_elegir_rol']);
        $_SESSION['sesion_recien_iniciada'] = true;
        auditarAccion($pdo, 'maestro_elegir_rol', 'usuarios', (int) $_SESSION['usuario_id'], [], [
            'rol' => $rol,
            'colegio_id' => $esGlobal ? null : $colegioId,
        ]);
        header('Location: ' . rutaDashboardPorRol($rol));
        exit;
    }
}
?>
<?php
$inicialesMaestro = '';
foreach (preg_split('/\s+/', trim((string) $_SESSION['nombre'])) as $parte) {
    if ($parte !== '') { $inicialesMaestro .= mb_strtoupper(mb_substr($parte, 0, 1)); }
    if (mb_strlen($inicialesMaestro) >= 2) { break; }
}
$rolMarcado = (string) ($_POST['rol'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Elegir rol</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="assets/css/elegir_rol.css?v=<?= assetVersion('/assets/css/elegir_rol.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <link rel="apple-touch-icon" href="assets/img/logo/icon-192.png">
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro elegir-rol">
    <div class="contenedor-login">
        <div class="card-login">
            <aside class="login-marca-lado">
                <div>
                    <a href="index.php"><img src="assets/img/logo/logo.png" alt="Serena" class="logo-login"></a>
                    <h2>Elige cómo quieres entrar</h2>
                    <p>Tu cuenta maestra puede actuar con cualquier rol. Puedes cambiar de rol cuando quieras desde la barra superior.</p>
                </div>
                <ul class="login-puntos">
                    <li><span>🔑</span> Una sola cuenta, todos los roles</li>
                    <li><span>🏫</span> Entra al colegio que necesites</li>
                    <li><span>📝</span> Cada cambio queda en la auditoría</li>
                </ul>
            </aside>
            <div class="login-form-lado">
                <span class="cuenta-maestra"><span class="iniciales" aria-hidden="true"><?= htmlspecialchars($inicialesMaestro) ?></span><?= htmlspecialchars((string) $_SESSION['nombre']) ?></span>
                <h1>¿Con qué rol quieres entrar?</h1>
                <p class="login-sub">Elige un rol para continuar.</p>
                <?php if ($error): ?>
                    <p class="mensaje mensaje-error" role="alert"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <p class="login-sub"><a href="demo.php"><strong>🎬 Modo demostración</strong></a> — presenta los <?= count($ROLES) + count($SOLO_DEMO) ?> roles y <?= (int) $conteosModulos['distintos'] ?> módulos con esta misma cuenta, con datos ficticios.
                    <a href="demo.php?mapa=1">Mapa de módulos →</a></p>
                <form action="elegir_rol.php" method="POST">
                    <?php campoCSRF(); ?>
                    <fieldset class="rol-grupo">
                        <legend>Rol</legend>
                        <?php foreach ($FAMILIAS as $familia => $claves): ?>
                            <p class="rol-familia"><?= htmlspecialchars($familia) ?></p>
                            <div class="roles-rejilla">
                            <?php foreach ($claves as $clave): if (!isset($ROLES[$clave])) { continue; } [$icono, $titulo, $detalle] = $ROLES[$clave]; ?>
                                <label class="rol-opcion">
                                    <input type="radio" name="rol" value="<?= htmlspecialchars($clave) ?>" required
                                           <?= $rolMarcado === $clave ? 'checked' : '' ?>>
                                    <span class="rol-tarjeta">
                                        <span class="rol-icono" aria-hidden="true"><?= $icono ?></span>
                                        <span class="rol-texto"><strong><?= htmlspecialchars($titulo) ?></strong>
                                            <small><?= htmlspecialchars($detalle) ?></small>
                                            <small class="rol-chip"><?= (int) ($conteosModulos['por_rol'][$clave] ?? 0) ?> módulos</small></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        <p class="rol-familia">Estudiantes y familias <small>(solo en modo demostración)</small></p>
                        <div class="roles-rejilla">
                            <?php foreach ($SOLO_DEMO as $clave => [$icono, $titulo, $detalle]): ?>
                                <a class="rol-opcion rol-demo" href="demo.php?guion=<?= htmlspecialchars($clave) ?>">
                                    <span class="rol-tarjeta">
                                        <span class="rol-icono" aria-hidden="true"><?= $icono ?></span>
                                        <span class="rol-texto"><strong><?= htmlspecialchars($titulo) ?></strong>
                                            <small><?= htmlspecialchars($detalle) ?></small>
                                            <small class="rol-chip">🎬 Ver en la demo · <?= (int) ($conteosModulos['por_rol'][$clave] ?? 0) ?> módulos</small></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div id="campo-colegio" class="campo-colegio">
                        <label for="colegio_id">Colegio</label>
                        <select id="colegio_id" name="colegio_id">
                            <option value="">— Elige un colegio —</option>
                            <?php foreach ($colegios as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= ((int) ($_POST['colegio_id'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Entrar</button>
                </form>
                <div class="enlaces-login">
                    <a href="logout.php">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script>
        // Superadmin y secretaría no necesitan colegio: se oculta el selector.
        (function () {
            var caja = document.getElementById('campo-colegio');
            function actualizar() {
                var r = document.querySelector('input[name=rol]:checked');
                caja.hidden = !!r && (r.value === 'superadmin' || r.value === 'secretaria');
            }
            document.querySelectorAll('input[name=rol]').forEach(function (i) { i.addEventListener('change', actualizar); });
            actualizar();
        })();
    </script>
    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
