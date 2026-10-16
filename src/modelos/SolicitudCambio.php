<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/colegio.php';

class SolicitudCambio
{
    public static function crear(PDO $pdo, int $usuarioId, string $campo, ?string $valorAnterior, string $valorNuevo): int
    {
        $c = $pdo->prepare(
            'INSERT INTO solicitudes_cambio (usuario_id, campo, valor_anterior, valor_nuevo) VALUES (?,?,?,?)'
        );
        $c->execute([$usuarioId, $campo, $valorAnterior, $valorNuevo]);
        return (int)$pdo->lastInsertId();
    }

    public static function tienePendiente(PDO $pdo, int $usuarioId, string $campo): bool
    {
        $c = $pdo->prepare(
            "SELECT id FROM solicitudes_cambio WHERE usuario_id=:uid AND campo=:campo AND estado='pendiente' LIMIT 1"
        );
        $c->execute(['uid' => $usuarioId, 'campo' => $campo]);
        return (bool) $c->fetch();
    }

    public static function listarPendientes(PDO $pdo): array
    {
        $params = [];
        $c = $pdo->prepare(
            "SELECT sc.*, u.nombre AS usuario_nombre, u.correo AS usuario_correo FROM solicitudes_cambio sc
             INNER JOIN usuarios u ON u.id=sc.usuario_id WHERE sc.estado='pendiente'"
            . condicionColegio('u.colegio_id', $params) . " ORDER BY sc.fecha_solicitud"
        );
        $c->execute($params);
        return $c->fetchAll();
    }

    public static function aprobar(PDO $pdo, int $id, int $adminId): void
    {
        $c = $pdo->prepare("UPDATE solicitudes_cambio SET estado='aprobada', revisado_por=:uid, fecha_revision=NOW() WHERE id=:id");
        $c->execute(['uid' => $adminId, 'id' => $id]);
    }

    public static function rechazar(PDO $pdo, int $id, int $adminId): void
    {
        $c = $pdo->prepare("UPDATE solicitudes_cambio SET estado='rechazada', revisado_por=:uid, fecha_revision=NOW() WHERE id=:id");
        $c->execute(['uid' => $adminId, 'id' => $id]);
    }
}
