<?php
declare(strict_types=1);

class Notificacion
{
    public static function crear(PDO $pdo, int $usuarioId, string $tipo, string $mensaje, string $url = ''): void
    {
        $c = $pdo->prepare(
            'INSERT INTO notificaciones (usuario_id, tipo, mensaje, url_destino) VALUES (:uid, :tipo, :msg, :url)'
        );
        $c->execute(['uid' => $usuarioId, 'tipo' => $tipo, 'msg' => $mensaje, 'url' => $url ?: null]);
    }

    public static function listarDeUsuario(PDO $pdo, int $usuarioId, bool $soloNoLeidas = false): array
    {
        $sql = 'SELECT * FROM notificaciones WHERE usuario_id=:uid';
        if ($soloNoLeidas) $sql .= ' AND leida=0';
        $sql .= ' ORDER BY fecha_creacion DESC LIMIT 50';
        $c = $pdo->prepare($sql);
        $c->execute(['uid' => $usuarioId]);
        return $c->fetchAll();
    }

    public static function marcarLeida(PDO $pdo, int $id, int $usuarioId): void
    {
        $pdo->prepare('UPDATE notificaciones SET leida=1 WHERE id=:id AND usuario_id=:uid')
            ->execute(['id' => $id, 'uid' => $usuarioId]);
    }

    public static function marcarTodasLeidas(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare('UPDATE notificaciones SET leida=1 WHERE usuario_id=:uid')->execute(['uid' => $usuarioId]);
    }

    public static function contarNoLeidas(PDO $pdo, int $usuarioId): int
    {
        $c = $pdo->prepare('SELECT COUNT(*) FROM notificaciones WHERE usuario_id=:uid AND leida=0');
        $c->execute(['uid' => $usuarioId]);
        return (int)$c->fetchColumn();
    }
}
