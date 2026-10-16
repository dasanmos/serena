<?php
declare(strict_types=1);

class Rol
{
    public static function listarTodos(PDO $pdo): array
    {
        return $pdo->query('SELECT * FROM roles ORDER BY nombre')->fetchAll();
    }

    public static function buscarPorNombre(PDO $pdo, string $nombre): ?array
    {
        $c = $pdo->prepare('SELECT * FROM roles WHERE nombre=:n LIMIT 1');
        $c->execute(['n' => $nombre]);
        return $c->fetch() ?: null;
    }
}
