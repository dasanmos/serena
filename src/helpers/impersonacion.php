<?php
declare(strict_types=1);

// Requiere que auth.php ya esté incluido (sesión iniciada, iniciarSesionUsuario/rutaDashboardPorRol
// disponibles). Carga aquí mismo sus propias dependencias de modelos para no depender de que
// quien incluya este archivo se acuerde de requerirlas primero.
require_once __DIR__ . '/../modelos/Grupo.php';
require_once __DIR__ . '/../modelos/Usuario.php';

// Regla de negocio única y centralizada de la funcionalidad "Ver como usuario":
// - Psicólogo, orientador, administrador, superadmin y secretaría: NUNCA (ver ROLES_NO_SUPLANTABLES).
// - Resto de roles distintos de "estudiante": permitido.
// - Rol "estudiante": SOLO el usuario cuyo correo coincide con CORREO_ESTUDIANTE_PRUEBA.
// Esta función es la que de verdad protege la restricción (no la lista que se muestra
// en pantalla) — se vuelve a llamar en el servidor cada vez que se intenta activar una
// vista, así que aunque alguien manipule el usuario_id enviado por POST, esta regla
// sigue evaluándose contra el usuario real que hay en la base de datos.
// Roles que NUNCA se pueden suplantar:
// - psicólogo y orientador: sus notas de sesión son privadas incluso entre colegas; entrar
//   "como" ellos le daría al administrador acceso a información clínica de menores.
// - otro administrador, superadmin o secretaría: sería escalar o mover privilegios.
const ROLES_NO_SUPLANTABLES = ['psicologo', 'orientador', 'administrador', 'superadmin', 'secretaria'];

function puedeVerComo(array $usuarioObjetivo): bool
{
    if (in_array($usuarioObjetivo['rol'] ?? '', ROLES_NO_SUPLANTABLES, true)) {
        return false;
    }
    if (($usuarioObjetivo['rol'] ?? '') !== 'estudiante') {
        return true;
    }
    return strcasecmp((string) $usuarioObjetivo['correo'], CORREO_ESTUDIANTE_PRUEBA) === 0;
}

function estaViendoComoOtro(): bool
{
    return !empty($_SESSION['admin_original']);
}

function identidadAdminOriginal(): ?array
{
    return $_SESSION['admin_original'] ?? null;
}

// Cambia la sesión activa a la identidad del usuario objetivo, guardando antes un
// snapshot completo de la identidad real del administrador para poder restaurarla
// exactamente con volverAAdmin(). No usa iniciarSesionUsuario() porque esa función
// está pensada para un login real (no debe tocar admin_original ni nada de esto).
function iniciarVistaComo(PDO $pdo, int $idObjetivo): array
{
    // Nunca permitir impersonación en cascada: si ya se está viendo como otro usuario,
    // hay que volver a la cuenta de administrador antes de poder elegir a alguien más.
    if (estaViendoComoOtro()) {
        return ['exito' => false, 'mensaje' => 'Ya estás viendo el sistema como otro usuario. Vuelve a tu cuenta primero.'];
    }

    if (($_SESSION['rol'] ?? '') !== 'administrador') {
        return ['exito' => false, 'mensaje' => 'Solo un administrador puede usar esta función.'];
    }

    if ($idObjetivo === (int) $_SESSION['usuario_id']) {
        return ['exito' => false, 'mensaje' => 'Ya tienes esta sesión activa.'];
    }

    $objetivo = Usuario::buscarPorId($pdo, $idObjetivo);
    if (!$objetivo || (int) $objetivo['activo'] === 0) {
        return ['exito' => false, 'mensaje' => 'Ese usuario no existe o está desactivado.'];
    }

    // Multi-colegio: solo se puede ver como alguien del MISMO colegio (y nunca como superadmin).
    if ($objetivo['rol'] === 'superadmin' || !usuarioEsDeMiColegio($pdo, (int) $objetivo['id'])) {
        return ['exito' => false, 'mensaje' => 'No puedes ver el sistema como este usuario.'];
    }

    if (!puedeVerComo($objetivo)) {
        return ['exito' => false, 'mensaje' => 'No puedes ver el sistema como este usuario.'];
    }

    $_SESSION['admin_original'] = [
        'usuario_id' => (int) $_SESSION['usuario_id'],
        'nombre'     => $_SESSION['nombre'],
        'rol'        => $_SESSION['rol'],
        'grupo_id'   => $_SESSION['grupo_id'],
        'grupos_ids' => $_SESSION['grupos_ids'] ?? [],
        'colegio_id' => $_SESSION['colegio_id'] ?? null,
    ];

    session_regenerate_id(true);
    $_SESSION['usuario_id']  = (int) $objetivo['id'];
    $_SESSION['nombre']      = $objetivo['nombre'];
    $_SESSION['rol']         = $objetivo['rol'];
    $_SESSION['grupo_id']    = $objetivo['grupo_id'];
    $_SESSION['colegio_id']  = $objetivo['colegio_id'] !== null ? (int) $objetivo['colegio_id'] : null;
    $_SESSION['grupos_ids']  = Grupo::idsGruposDePersonal($pdo, (int) $objetivo['id']);
    $_SESSION['sesion_recien_iniciada'] = true;

    return ['exito' => true, 'usuario' => $objetivo];
}

// Restaura exactamente la identidad de administrador guardada antes de impersonar.
// No requiere $pdo: todo lo necesario ya quedó guardado en la sesión en iniciarVistaComo().
function volverAAdmin(): array
{
    $original = identidadAdminOriginal();
    if ($original === null) {
        return ['exito' => false, 'mensaje' => 'No estás viendo el sistema como otro usuario.'];
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id']  = $original['usuario_id'];
    $_SESSION['nombre']      = $original['nombre'];
    $_SESSION['rol']         = $original['rol'];
    $_SESSION['grupo_id']    = $original['grupo_id'];
    $_SESSION['grupos_ids']  = $original['grupos_ids'];
    $_SESSION['colegio_id']  = $original['colegio_id'] ?? null;
    unset($_SESSION['admin_original']);
    $_SESSION['sesion_recien_iniciada'] = true;

    return ['exito' => true];
}
