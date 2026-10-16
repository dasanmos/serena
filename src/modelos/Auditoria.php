<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/colegio.php';

class Auditoria
{
    public static function registrar(PDO $pdo, ?int $usuarioId, string $accion, string $tabla, int $registroId, array $anterior = [], array $nuevo = []): void
    {
        $c = $pdo->prepare(
            'INSERT INTO auditoria (usuario_id, colegio_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos)
             VALUES (?,?,?,?,?,?,?)'
        );
        $colegio = colegioParaConsulta();
        $c->execute([
            $usuarioId, ($colegio !== null && $colegio > 0) ? $colegio : null, $accion, $tabla, $registroId,
            $anterior ? json_encode($anterior) : null,
            $nuevo ? json_encode($nuevo) : null,
        ]);
    }

    public static function listar(PDO $pdo, int $limite = 100, int $offset = 0): array
    {
        $params = [];
        $c = $pdo->prepare(
            'SELECT a.*, u.nombre AS usuario_nombre FROM auditoria a
             LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE 1=1'
            . condicionColegio('a.colegio_id', $params) .
            ' ORDER BY a.fecha DESC LIMIT :limite OFFSET :offset'
        );
        foreach ($params as $k => $v) { $c->bindValue($k, $v); }
        $c->bindValue('limite', $limite, PDO::PARAM_INT);
        $c->bindValue('offset', $offset, PDO::PARAM_INT);
        $c->execute();
        return $c->fetchAll();
    }

    public static function contarTotal(PDO $pdo): int
    {
        $params = [];
        $c = $pdo->prepare('SELECT COUNT(*) FROM auditoria a WHERE 1=1' . condicionColegio('a.colegio_id', $params));
        $c->execute($params);
        return (int) $c->fetchColumn();
    }

    public static function listarFiltrado(PDO $pdo, ?string $desde = null, ?string $hasta = null, ?string $usuario = null, int $limite = 500, int $offset = 0): array
    {
        $sql = 'SELECT a.*, u.nombre AS usuario_nombre FROM auditoria a
                LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE 1=1';
        $params = [];
        $sql .= condicionColegio('a.colegio_id', $params);
        if ($desde) { $sql .= ' AND DATE(a.fecha) >= :desde'; $params['desde'] = $desde; }
        if ($hasta) { $sql .= ' AND DATE(a.fecha) <= :hasta'; $params['hasta'] = $hasta; }
        if ($usuario) { $sql .= ' AND (u.nombre LIKE :usuario OR u.correo LIKE :usuario)'; $params['usuario'] = '%' . $usuario . '%'; }
        $sql .= ' ORDER BY a.fecha DESC LIMIT :limite OFFSET :offset';
        $c = $pdo->prepare($sql);
        foreach ($params as $k => $v) { $c->bindValue($k, $v); }
        $c->bindValue('limite', $limite, PDO::PARAM_INT);
        $c->bindValue('offset', $offset, PDO::PARAM_INT);
        $c->execute();
        return $c->fetchAll();
    }

    public static function contarFiltrado(PDO $pdo, ?string $desde = null, ?string $hasta = null, ?string $usuario = null): int
    {
        $sql = 'SELECT COUNT(*) FROM auditoria a LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE 1=1';
        $params = [];
        $sql .= condicionColegio('a.colegio_id', $params);
        if ($desde) { $sql .= ' AND DATE(a.fecha) >= :desde'; $params['desde'] = $desde; }
        if ($hasta) { $sql .= ' AND DATE(a.fecha) <= :hasta'; $params['hasta'] = $hasta; }
        if ($usuario) { $sql .= ' AND (u.nombre LIKE :usuario OR u.correo LIKE :usuario)'; $params['usuario'] = '%' . $usuario . '%'; }
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return (int) $c->fetchColumn();
    }
}
