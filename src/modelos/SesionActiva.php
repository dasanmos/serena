<?php
declare(strict_types=1);

// Registro de sesiones activas por usuario. auth.php la usa en 3 momentos:
//   - Al iniciar sesión: registrarInicio() crea la fila.
//   - En cada petición protegida (requiereSesion): actualizarActividad() toca
//     "última actividad" y sigueVigente() comprueba que nadie la haya cerrado
//     desde otro dispositivo.
//   - Al cerrar sesión: revocar() marca la fila (por el propio dueño o por él
//     mismo desde otro dispositivo).
class SesionActiva
{
    // Tras cuánta inactividad una sesión deja de listarse como "activa" aunque
    // nadie la haya cerrado a mano (el archivo de sesión de PHP también expira
    // por su cuenta, esto es solo para que la lista no muestre fantasmas).
    private const INACTIVA_TRAS_HORAS = 72;

    public static function registrarInicio(PDO $pdo, int $usuarioId, string $sessionId): void
    {
        $ahora = date('Y-m-d H:i:s');
        // INSERT ... ON DUPLICATE: session_regenerate_id ya cambia el ID en cada
        // login, así que un choque real es prácticamente imposible, pero cubre
        // el caso sin lanzar una excepción innecesaria.
        // Con EMULATE_PREPARES=false un mismo :nombre no puede repetirse en la consulta
        // (HY093), por eso cada valor lleva su propio nombre.
        $pdo->prepare(
            'INSERT INTO sesiones_activas (usuario_id, session_id, ip, agente_usuario, iniciada_en, ultima_actividad)
             VALUES (:u, :s, :ip, :ua, :ini, :act)
             ON DUPLICATE KEY UPDATE usuario_id=:u2, ip=:ip2, agente_usuario=:ua2, iniciada_en=:ini2, ultima_actividad=:act2, revocada_en=NULL'
        )->execute([
            'u' => $usuarioId,
            's' => $sessionId,
            'ip' => self::ipCliente(),
            'ua' => self::agenteUsuario(),
            'ini' => $ahora,
            'act' => $ahora,
            'u2' => $usuarioId,
            'ip2' => self::ipCliente(),
            'ua2' => self::agenteUsuario(),
            'ini2' => $ahora,
            'act2' => $ahora,
        ]);
    }

    public static function actualizarActividad(PDO $pdo, string $sessionId): void
    {
        $pdo->prepare('UPDATE sesiones_activas SET ultima_actividad=:ahora WHERE session_id=:s AND revocada_en IS NULL')
            ->execute(['ahora' => date('Y-m-d H:i:s'), 's' => $sessionId]);
    }

    /** false si esta sesión fue revocada desde otro dispositivo (o nunca se registró). */
    public static function sigueVigente(PDO $pdo, string $sessionId): bool
    {
        $c = $pdo->prepare('SELECT revocada_en FROM sesiones_activas WHERE session_id=:s LIMIT 1');
        $c->execute(['s' => $sessionId]);
        $fila = $c->fetch();
        // Si no hay fila (sesiones creadas antes de esta migración, o algún caso
        // límite), no se bloquea: se deja pasar y quedará registrada en el
        // próximo login. Bloquear aquí cerraría sesión a todo el mundo el día
        // que se aplique esta migración.
        return $fila === false || $fila['revocada_en'] === null;
    }

    /** Todas las sesiones vigentes de la persona, más recientes primero. */
    public static function listarDeUsuario(PDO $pdo, int $usuarioId): array
    {
        $limite = date('Y-m-d H:i:s', time() - self::INACTIVA_TRAS_HORAS * 3600);
        $c = $pdo->prepare(
            'SELECT * FROM sesiones_activas
             WHERE usuario_id=:u AND revocada_en IS NULL AND ultima_actividad > :limite
             ORDER BY ultima_actividad DESC'
        );
        $c->execute(['u' => $usuarioId, 'limite' => $limite]);
        return $c->fetchAll();
    }

    /** Revoca una sesión de la persona por su id de fila. true si existía y era suya. */
    public static function revocarPorId(PDO $pdo, int $usuarioId, int $sesionId, string $sessionIdActual): bool
    {
        $esLaActual = false;
        $fila = $pdo->prepare('SELECT session_id FROM sesiones_activas WHERE id=:id AND usuario_id=:u AND revocada_en IS NULL');
        $fila->execute(['id' => $sesionId, 'u' => $usuarioId]);
        $encontrada = $fila->fetch();
        if (!$encontrada) {
            return false;
        }
        $esLaActual = hash_equals($sessionIdActual, (string) $encontrada['session_id']);

        $c = $pdo->prepare(
            'UPDATE sesiones_activas SET revocada_en=:ahora, es_actual_al_revocar=:actual
             WHERE id=:id AND usuario_id=:u AND revocada_en IS NULL'
        );
        $c->execute(['ahora' => date('Y-m-d H:i:s'), 'actual' => $esLaActual ? 1 : 0, 'id' => $sesionId, 'u' => $usuarioId]);
        return $c->rowCount() === 1;
    }

    /** Revoca todas las sesiones de la persona EXCEPTO la actual ("cerrar en otros dispositivos"). */
    public static function revocarTodasMenosActual(PDO $pdo, int $usuarioId, string $sessionIdActual): int
    {
        $c = $pdo->prepare(
            'UPDATE sesiones_activas SET revocada_en=:ahora
             WHERE usuario_id=:u AND revocada_en IS NULL AND session_id != :actual'
        );
        $c->execute(['ahora' => date('Y-m-d H:i:s'), 'u' => $usuarioId, 'actual' => $sessionIdActual]);
        return $c->rowCount();
    }

    public static function marcarCerrada(PDO $pdo, string $sessionId): void
    {
        $pdo->prepare('UPDATE sesiones_activas SET revocada_en=:ahora WHERE session_id=:s AND revocada_en IS NULL')
            ->execute(['ahora' => date('Y-m-d H:i:s'), 's' => $sessionId]);
    }

    private static function ipCliente(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private static function agenteUsuario(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return $ua !== null ? mb_substr($ua, 0, 255) : null;
    }

    /** Descripción legible del navegador/dispositivo a partir del user-agent, sin librerías externas. */
    public static function descripcionDispositivo(?string $agenteUsuario): string
    {
        if (!$agenteUsuario) {
            return 'Dispositivo desconocido';
        }
        $ua = $agenteUsuario;

        $so = 'un dispositivo';
        if (stripos($ua, 'Android') !== false) $so = 'Android';
        elseif (stripos($ua, 'iPhone') !== false) $so = 'iPhone';
        elseif (stripos($ua, 'iPad') !== false) $so = 'iPad';
        elseif (stripos($ua, 'Windows') !== false) $so = 'Windows';
        elseif (stripos($ua, 'Mac OS') !== false) $so = 'Mac';
        elseif (stripos($ua, 'Linux') !== false) $so = 'Linux';

        $navegador = 'un navegador';
        if (stripos($ua, 'Edg/') !== false) $navegador = 'Edge';
        elseif (stripos($ua, 'Chrome') !== false && stripos($ua, 'Chromium') === false) $navegador = 'Chrome';
        elseif (stripos($ua, 'Firefox') !== false) $navegador = 'Firefox';
        elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) $navegador = 'Safari';

        return "{$navegador} en {$so}";
    }
}
