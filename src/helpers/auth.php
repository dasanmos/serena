<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}
require_once __DIR__ . '/colegio.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

function estaAutenticado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

function requiereSesion(): void
{
    if (!estaAutenticado()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    // Si esta sesión fue cerrada remotamente desde "Mis sesiones" en otro
    // dispositivo, se destruye aquí y se manda a login — sin esto, la cookie
    // seguiría sirviendo indefinidamente aunque la persona la haya revocado.
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        require_once __DIR__ . '/../modelos/SesionActiva.php';
        $sid = session_id();
        if ($sid !== '' && !SesionActiva::sigueVigente($pdo, $sid)) {
            cerrarSesionUsuario();
            header('Location: ' . BASE_URL . '/login.php?sesionCerrada=1');
            exit;
        }
        if ($sid !== '') {
            SesionActiva::actualizarActividad($pdo, $sid);
        }
    }
    // Usuario MAESTRO: mientras no haya elegido con qué rol entrar, cualquier página
    // protegida lo manda al selector (login normal y login con 2FA pasan por aquí).
    if (!empty($_SESSION['maestro_elegir_rol'])
        && !in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ['elegir_rol.php', 'demo.php', 'logout.php', 'seguridad.php'], true)) {
        header('Location: ' . BASE_URL . '/elegir_rol.php');
        exit;
    }
    // Cuenta con varios roles: mientras no elija con cuál entrar, todo lleva al selector.
    if (!empty($_SESSION['elegir_rol_multiple'])
        && !in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ['ingresar_como.php', 'logout.php', 'seguridad.php'], true)) {
        header('Location: ' . BASE_URL . '/ingresar_como.php');
        exit;
    }
}

function requiereRol(string ...$rolesPermitidos): void
{
    requiereSesion();
    if (!in_array($_SESSION['rol'], $rolesPermitidos, true)) {
        // Página con el formato de Serena que lleva a la persona a SU panel (ver acceso_denegado.php).
        require_once __DIR__ . '/acceso_denegado.php';
        mostrarAccesoDenegado();
    }
}

function iniciarSesionUsuario(array $usuario, ?PDO $pdo = null): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['grupo_id'] = $usuario['grupo_id'];
    // Multi-colegio: el colegio viaja en la sesión y filtra TODAS las consultas
    // (ver src/helpers/colegio.php). El superadmin no tiene colegio (NULL).
    $_SESSION['colegio_id'] = ($usuario['colegio_id'] ?? null) !== null ? (int) $usuario['colegio_id'] : null;
    unset($_SESSION['colegio_contexto']);
    // Usuario MAESTRO (superadmin con es_maestro = 1): debe elegir rol antes de entrar (ver elegir_rol.php).
    unset($_SESSION['rol_real'], $_SESSION['maestro_elegir_rol']);
    if (($usuario['rol'] ?? '') === 'superadmin' && (int) ($usuario['es_maestro'] ?? 0) === 1) {
        $_SESSION['rol_real'] = 'superadmin';
        $_SESSION['maestro_elegir_rol'] = true;
    }
    // Se guarda en sesión (no solo en BD) para no hacer una consulta extra en
    // cada carga de página; preferencias.php actualiza ambas a la vez.
    $_SESSION['tema_preferido'] = $usuario['tema_preferido'] ?? 'sistema';
    $_SESSION['tamano_texto'] = $usuario['tamano_texto'] ?? 'normal';
    $_SESSION['alto_contraste'] = (int) ($usuario['alto_contraste'] ?? 0) === 1;

    // Un miembro del personal (típicamente orientador) puede tener varios grupos o un
    // grado completo a cargo — grupo_id solo alcanza para uno. grupos_ids trae la lista
    // completa desde personal_grupos; para estudiantes (y personal sin grupos asignados
    // ahí) queda vacía y el código sigue usando grupo_id como siempre.
    $_SESSION['grupos_ids'] = [];
    if ($pdo !== null) {
        require_once __DIR__ . '/../modelos/Grupo.php';
        $_SESSION['grupos_ids'] = Grupo::idsGruposDePersonal($pdo, (int) $usuario['id']);
    }

    // Cuenta con varios roles (migración 044): antes de entrar elige con cuál (ingresar_como.php).
    // La cuenta maestra tiene su propio selector (elegir_rol.php) y no pasa por aquí.
    unset($_SESSION['roles_disponibles'], $_SESSION['elegir_rol_multiple']);
    if ($pdo !== null && empty($_SESSION['maestro_elegir_rol'])) {
        try {
            require_once __DIR__ . '/../modelos/RolesUsuario.php';
            $rolesCuenta = RolesUsuario::roles($pdo, (int) $usuario['id']);
            if (count($rolesCuenta) > 1) {
                $_SESSION['roles_disponibles'] = $rolesCuenta;
                $_SESSION['elegir_rol_multiple'] = true;
            }
        } catch (\Throwable $e) {
            error_log('RolesUsuario::roles falló: ' . $e->getMessage());
        }
    }

    // Revisa (y envía si toca) el resumen semanal por correo — ver resumen_semanal.php
    // para el porqué de hacerlo aquí en vez de con una tarea programada. Envuelto en
    // try/catch a propósito: un problema de correo NUNCA debe impedir que alguien
    // pueda iniciar sesión.
    if ($pdo !== null) {
        try {
            require_once __DIR__ . '/resumen_semanal.php';
            $usuario['grupos_ids'] = $_SESSION['grupos_ids'];
            revisarYEnviarResumenSemanal($pdo, $usuario);
        } catch (\Throwable $e) {
            error_log('revisarYEnviarResumenSemanal falló: ' . $e->getMessage());
        }
    }

    // Igual que el resumen semanal: se aprovecha el login del PERSONAL (no de
    // estudiantes) para revisar inactividad prolongada en toda la plataforma —
    // ver notificador.php. Envuelto en try/catch por la misma razón.
    if ($pdo !== null && $usuario['rol'] !== 'estudiante') {
        try {
            require_once __DIR__ . '/notificador.php';
            require_once __DIR__ . '/../modelos/AlertaTemprana.php';
            revisarInactividadProlongada($pdo);
        } catch (\Throwable $e) {
            error_log('revisarInactividadProlongada falló: ' . $e->getMessage());
        }
    }

    // Bandera de un solo uso: hace que el panel de la barrita superior se abra
    // automáticamente en la primera página que se carga tras iniciar sesión.
    // encabezado.php la lee y la borra en la misma carga, así que no vuelve a
    // abrirse solo en las páginas siguientes (solo al entrar de nuevo).
    $_SESSION['sesion_recien_iniciada'] = true;

    // Registro para "Mis sesiones" (ver dispositivos activos y cerrar sesión
    // remota). session_regenerate_id() ya corrió arriba, así que session_id()
    // aquí es el ID nuevo y definitivo de esta sesión.
    if ($pdo !== null) {
        require_once __DIR__ . '/../modelos/SesionActiva.php';
        $sid = session_id();
        if ($sid !== '') {
            SesionActiva::registrarInicio($pdo, (int) $usuario['id'], $sid);
        }
    }
}

// Vigencia del paso 2 del login: pasados estos segundos hay que volver a poner la contraseña.
const DESAFIO_2FA_SEGUNDOS = 600;

// Contraseña correcta pero falta el segundo paso: todavía NO hay sesión iniciada
// (estaAutenticado() sigue siendo false). Solo queda anotado a quién se le está
// pidiendo el código.
function iniciarDesafio2FA(int $usuarioId): void
{
    session_regenerate_id(true);
    $_SESSION['desafio_2fa'] = ['usuario_id' => $usuarioId, 'creado' => time()];
}

function desafio2FAVigente(): ?array
{
    $desafio = $_SESSION['desafio_2fa'] ?? null;
    if (!is_array($desafio) || empty($desafio['usuario_id']) || empty($desafio['creado'])) {
        return null;
    }
    if (time() - (int) $desafio['creado'] > DESAFIO_2FA_SEGUNDOS) {
        unset($_SESSION['desafio_2fa']);
        return null;
    }
    return $desafio;
}

function cerrarSesionUsuario(): void
{
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $sid = session_id();
        if ($sid !== '') {
            require_once __DIR__ . '/../modelos/SesionActiva.php';
            SesionActiva::marcarCerrada($pdo, $sid);
        }
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parametros['path'],
            $parametros['domain'],
            $parametros['secure'],
            $parametros['httponly']
        );
    }
    session_destroy();
}

function rutaDashboardPorRol(string $rol): string
{
    $rutas = [
        'estudiante'    => '/estudiante/dashboard.php',
        'psicologo'     => '/psicologo/panel.php',
        'orientador'    => '/orientador/panel.php',
        'docente'       => '/docente/panel.php',
        'coordinador'   => '/coordinador/panel.php',
        'rector'        => '/rector/panel.php',
        'administrador' => '/administrador/panel.php',
        'superadmin'    => '/superadmin/panel.php',
        'secretaria'    => '/secretaria/panel.php',
        'secretaria_academica' => '/secretaria_academica/panel.php',
        'acudiente'     => '/acudiente/panel.php',
    ];
    $base = defined('BASE_URL') ? BASE_URL : '';
    return $base . ($rutas[$rol] ?? '/login.php');
}
