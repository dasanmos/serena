<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/colegio.php';

class Grado
{
    public static function listarTodos(PDO $pdo, ?int $colegioId = null): array
    {
        $params = [];
        $sql = 'SELECT id, nombre FROM grados WHERE 1=1' . condicionColegio('colegio_id', $params, $colegioId) . ' ORDER BY nombre';
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return $c->fetchAll();
    }

    /** Crea el grado en el colegio indicado o, por defecto, en el colegio de la sesión. */
    public static function crear(PDO $pdo, string $nombre, ?int $colegioId = null): int
    {
        $colegio = $colegioId ?? colegioParaCrear();
        $consulta = $pdo->prepare('INSERT INTO grados (colegio_id, nombre) VALUES (:colegio, :nombre)');
        $consulta->execute(['colegio' => $colegio, 'nombre' => $nombre]);
        return (int) $pdo->lastInsertId();
    }
}
