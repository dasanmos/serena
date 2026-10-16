<?php
declare(strict_types=1);

// Límite de intentos genérico, respaldado por columnas en la tabla usuarios.
// Se usa tanto para el login (correo+contraseña) como para el PIN del diario,
// que al ser de solo 4 dígitos numéricos (10.000 combinaciones) necesita un límite más estricto.

const LIMITE_INTENTOS_LOGIN = 5;
const BLOQUEO_LOGIN_MINUTOS = 15;

const LIMITE_INTENTOS_PIN = 5;
const BLOQUEO_PIN_MINUTOS = 15;

// Devuelve el número de segundos restantes de bloqueo, o 0 si no está bloqueado.
function segundosBloqueoLogin(PDO $pdo, int $usuarioId): int
{
    $c = $pdo->prepare('SELECT login_bloqueado_hasta FROM usuarios WHERE id=:id LIMIT 1');
    $c->execute(['id' => $usuarioId]);
    $hasta = $c->fetchColumn();
    if (!$hasta) return 0;
    $restante = strtotime($hasta) - time();
    return $restante > 0 ? $restante : 0;
}

function registrarIntentoLoginFallido(PDO $pdo, int $usuarioId): void
{
    $c = $pdo->prepare('SELECT intentos_login_fallidos FROM usuarios WHERE id=:id LIMIT 1');
    $c->execute(['id' => $usuarioId]);
    $intentos = (int)$c->fetchColumn() + 1;

    if ($intentos >= LIMITE_INTENTOS_LOGIN) {
        $bloqueoHasta = (new DateTime('+' . BLOQUEO_LOGIN_MINUTOS . ' minutes'))->format('Y-m-d H:i:s');
        $pdo->prepare('UPDATE usuarios SET intentos_login_fallidos=0, login_bloqueado_hasta=:h WHERE id=:id')
            ->execute(['h' => $bloqueoHasta, 'id' => $usuarioId]);
    } else {
        $pdo->prepare('UPDATE usuarios SET intentos_login_fallidos=:i WHERE id=:id')
            ->execute(['i' => $intentos, 'id' => $usuarioId]);
    }
}

function limpiarIntentosLogin(PDO $pdo, int $usuarioId): void
{
    $pdo->prepare('UPDATE usuarios SET intentos_login_fallidos=0, login_bloqueado_hasta=NULL WHERE id=:id')
        ->execute(['id' => $usuarioId]);
}

function segundosBloqueoPin(PDO $pdo, int $usuarioId): int
{
    $c = $pdo->prepare('SELECT pin_bloqueado_hasta FROM usuarios WHERE id=:id LIMIT 1');
    $c->execute(['id' => $usuarioId]);
    $hasta = $c->fetchColumn();
    if (!$hasta) return 0;
    $restante = strtotime($hasta) - time();
    return $restante > 0 ? $restante : 0;
}

function registrarIntentoPinFallido(PDO $pdo, int $usuarioId): void
{
    $c = $pdo->prepare('SELECT intentos_pin_fallidos FROM usuarios WHERE id=:id LIMIT 1');
    $c->execute(['id' => $usuarioId]);
    $intentos = (int)$c->fetchColumn() + 1;

    if ($intentos >= LIMITE_INTENTOS_PIN) {
        $bloqueoHasta = (new DateTime('+' . BLOQUEO_PIN_MINUTOS . ' minutes'))->format('Y-m-d H:i:s');
        $pdo->prepare('UPDATE usuarios SET intentos_pin_fallidos=0, pin_bloqueado_hasta=:h WHERE id=:id')
            ->execute(['h' => $bloqueoHasta, 'id' => $usuarioId]);
    } else {
        $pdo->prepare('UPDATE usuarios SET intentos_pin_fallidos=:i WHERE id=:id')
            ->execute(['i' => $intentos, 'id' => $usuarioId]);
    }
}

function limpiarIntentosPin(PDO $pdo, int $usuarioId): void
{
    $pdo->prepare('UPDATE usuarios SET intentos_pin_fallidos=0, pin_bloqueado_hasta=NULL WHERE id=:id')
        ->execute(['id' => $usuarioId]);
}

// --- Verificación en 2 pasos ---
// Un código TOTP tiene solo 1.000.000 de combinaciones, así que igual que el PIN
// necesita un límite estricto. Este mismo contador cubre el paso 2 del login y las
// acciones sensibles de seguridad.php (desactivar, generar códigos nuevos).

const LIMITE_INTENTOS_2FA = 5;
const BLOQUEO_2FA_MINUTOS = 15;

function segundosBloqueo2FA(PDO $pdo, int $usuarioId): int
{
    $c = $pdo->prepare('SELECT bloqueo_2fa_hasta FROM usuarios WHERE id=:id LIMIT 1');
    $c->execute(['id' => $usuarioId]);
    $hasta = $c->fetchColumn();
    if (!$hasta) return 0;
    $restante = strtotime($hasta) - time();
    return $restante > 0 ? $restante : 0;
}

function registrarIntento2FAFallido(PDO $pdo, int $usuarioId): void
{
    $c = $pdo->prepare('SELECT intentos_2fa_fallidos FROM usuarios WHERE id=:id LIMIT 1');
    $c->execute(['id' => $usuarioId]);
    $intentos = (int)$c->fetchColumn() + 1;

    if ($intentos >= LIMITE_INTENTOS_2FA) {
        $bloqueoHasta = (new DateTime('+' . BLOQUEO_2FA_MINUTOS . ' minutes'))->format('Y-m-d H:i:s');
        $pdo->prepare('UPDATE usuarios SET intentos_2fa_fallidos=0, bloqueo_2fa_hasta=:h WHERE id=:id')
            ->execute(['h' => $bloqueoHasta, 'id' => $usuarioId]);
    } else {
        $pdo->prepare('UPDATE usuarios SET intentos_2fa_fallidos=:i WHERE id=:id')
            ->execute(['i' => $intentos, 'id' => $usuarioId]);
    }
}

function limpiarIntentos2FA(PDO $pdo, int $usuarioId): void
{
    $pdo->prepare('UPDATE usuarios SET intentos_2fa_fallidos=0, bloqueo_2fa_hasta=NULL WHERE id=:id')
        ->execute(['id' => $usuarioId]);
}

// Texto listo para mostrar cuando hay bloqueo (o cadena vacía si no lo hay).
function mensajeBloqueo2FA(PDO $pdo, int $usuarioId): string
{
    $restante = segundosBloqueo2FA($pdo, $usuarioId);
    if ($restante <= 0) return '';
    $minutos = (int) ceil($restante / 60);
    return "Demasiados intentos fallidos. Intenta de nuevo en {$minutos} minuto(s).";
}
