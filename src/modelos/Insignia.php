<?php
declare(strict_types=1);

class Insignia
{
    public static function listarTodas(PDO $pdo): array
    {
        return $pdo->query('SELECT * FROM insignias ORDER BY id')->fetchAll();
    }

    public static function listarDeUsuario(PDO $pdo, int $usuarioId): array
    {
        $c = $pdo->prepare(
            'SELECT i.*, ui.fecha_obtenida FROM usuario_insignias ui
             INNER JOIN insignias i ON i.id = ui.insignia_id
             WHERE ui.usuario_id = :uid ORDER BY ui.fecha_obtenida DESC'
        );
        $c->execute(['uid' => $usuarioId]);
        return $c->fetchAll();
    }

    public static function tieneInsignia(PDO $pdo, int $usuarioId, string $criterio): bool
    {
        $c = $pdo->prepare(
            'SELECT ui.id FROM usuario_insignias ui
             INNER JOIN insignias i ON i.id = ui.insignia_id
             WHERE ui.usuario_id = :uid AND i.criterio = :criterio LIMIT 1'
        );
        $c->execute(['uid' => $usuarioId, 'criterio' => $criterio]);
        return (bool) $c->fetchColumn();
    }

    // Otorga la insignia si no la tiene ya. Devuelve el nombre de la insignia si la acaba de otorgar
    // (para poder notificar), o null si ya la tenía o el criterio no existe.
    public static function otorgarPorCriterio(PDO $pdo, int $usuarioId, string $criterio): ?string
    {
        if (self::tieneInsignia($pdo, $usuarioId, $criterio)) return null;

        $ins = $pdo->prepare('SELECT id, nombre FROM insignias WHERE criterio = :c LIMIT 1');
        $ins->execute(['c' => $criterio]);
        $insignia = $ins->fetch();
        if (!$insignia) return null;

        $c = $pdo->prepare('INSERT IGNORE INTO usuario_insignias (usuario_id, insignia_id) VALUES (:uid, :iid)');
        $c->execute(['uid' => $usuarioId, 'iid' => $insignia['id']]);

        return $c->rowCount() > 0 ? $insignia['nombre'] : null;
    }
}
