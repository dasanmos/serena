<?php
declare(strict_types=1);

class Recordatorio
{
    private const TIPOS_VALIDOS = ['habito', 'sueno', 'diario', 'meta', 'personalizado'];
    private const DIAS_VALIDOS = ['lun', 'mar', 'mie', 'jue', 'vie', 'sab', 'dom'];

    public static function crear(PDO $pdo, int $usuarioId, array $datos): int
    {
        $tipo = in_array($datos['tipo'] ?? '', self::TIPOS_VALIDOS, true) ? $datos['tipo'] : 'personalizado';
        $dias = array_values(array_intersect((array)($datos['dias_semana'] ?? []), self::DIAS_VALIDOS));
        if (empty($dias)) $dias = self::DIAS_VALIDOS; // sin selección explícita = todos los días

        $c = $pdo->prepare(
            'INSERT INTO recordatorios (usuario_id, tipo, hora, dias_semana) VALUES (:uid, :tipo, :hora, :dias)'
        );
        $c->execute([
            'uid'  => $usuarioId,
            'tipo' => $tipo,
            'hora' => $datos['hora'] ?? '20:00',
            'dias' => implode(',', $dias),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function listarDeUsuario(PDO $pdo, int $usuarioId): array
    {
        $c = $pdo->prepare('SELECT * FROM recordatorios WHERE usuario_id=:uid ORDER BY hora');
        $c->execute(['uid' => $usuarioId]);
        return $c->fetchAll();
    }

    public static function buscarPorId(PDO $pdo, int $id, int $usuarioId): ?array
    {
        $c = $pdo->prepare('SELECT * FROM recordatorios WHERE id=:id AND usuario_id=:uid LIMIT 1');
        $c->execute(['id' => $id, 'uid' => $usuarioId]);
        return $c->fetch() ?: null;
    }

    public static function alternarActivo(PDO $pdo, int $id, int $usuarioId): void
    {
        $pdo->prepare('UPDATE recordatorios SET activo = 1 - activo WHERE id=:id AND usuario_id=:uid')
            ->execute(['id' => $id, 'uid' => $usuarioId]);
    }

    public static function eliminar(PDO $pdo, int $id, int $usuarioId): void
    {
        $pdo->prepare('DELETE FROM recordatorios WHERE id=:id AND usuario_id=:uid')
            ->execute(['id' => $id, 'uid' => $usuarioId]);
    }

    // Recordatorios activos de HOY para un usuario, usados para mostrar el aviso en
    // pantalla mientras tiene la pestaña abierta (no hay tarea programada en el servidor
    // que envíe nada fuera de eso — ver nota en estudiante/recordatorios.php).
    public static function activosDeHoy(PDO $pdo, int $usuarioId): array
    {
        $diasAbrev = ['lun', 'mar', 'mie', 'jue', 'vie', 'sab', 'dom'];
        $diaHoy = $diasAbrev[(int) date('N') - 1];
        $c = $pdo->prepare(
            "SELECT * FROM recordatorios
             WHERE usuario_id=:uid AND activo=1 AND FIND_IN_SET(:dia, dias_semana)
             ORDER BY hora"
        );
        $c->execute(['uid' => $usuarioId, 'dia' => $diaHoy]);
        return $c->fetchAll();
    }

    // Recordatorios activos cuya hora programada ya pasó (o está pasando) en el
    // día de hoy y que TODAVÍA no se enviaron hoy. Usado por el endpoint de cron
    // (public/tareas/recordatorios.php), que corre cada 15 minutos: "ya pasó la
    // hora" en vez de "es justo esta hora" cubre que el cron no cae siempre exacto
    // en el minuto programado por el usuario.
    public static function pendientesDeEnvio(PDO $pdo, string $ahora, string $hoy): array
    {
        $diasAbrev = ['lun', 'mar', 'mie', 'jue', 'vie', 'sab', 'dom'];
        $diaHoy = $diasAbrev[(int) date('N', strtotime($ahora)) - 1];
        $c = $pdo->prepare(
            "SELECT r.*, u.nombre, u.correo, u.whatsapp_2fa_numero, u.whatsapp_2fa_apikey
             FROM recordatorios r
             INNER JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.activo = 1
               AND FIND_IN_SET(:dia, r.dias_semana)
               AND r.hora <= TIME(:ahora)
               AND (r.ultimo_envio IS NULL OR r.ultimo_envio <> :hoy)
               AND u.activo = 1
             ORDER BY r.hora"
        );
        $c->execute(['dia' => $diaHoy, 'ahora' => $ahora, 'hoy' => $hoy]);
        return $c->fetchAll();
    }

    public static function marcarEnviadoHoy(PDO $pdo, int $id, string $hoy): void
    {
        $pdo->prepare('UPDATE recordatorios SET ultimo_envio = :hoy WHERE id = :id')
            ->execute(['hoy' => $hoy, 'id' => $id]);
    }
}
