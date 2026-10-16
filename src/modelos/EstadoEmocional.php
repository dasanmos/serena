<?php
declare(strict_types=1);

class EstadoEmocional
{
    public static function registrar(PDO $pdo, int $usuarioId, array $datos): int
    {
        $consulta = $pdo->prepare(
            'INSERT INTO estado_emocional (usuario_id, emocion, nivel, nota, momento_dia, fecha_hora)
             VALUES (:usuario_id, :emocion, :nivel, :nota, :momento_dia, NOW())'
        );
        $consulta->execute([
            'usuario_id'  => $usuarioId,
            'emocion'     => $datos['emocion'],
            'nivel'       => (int) $datos['nivel'],
            'nota'        => $datos['nota'] ?? null,
            'momento_dia' => $datos['momento_dia'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function listarDeUsuario(PDO $pdo, int $usuarioId, string $desde = '', string $hasta = ''): array
    {
        $sql = 'SELECT * FROM estado_emocional WHERE usuario_id = :usuario_id';
        $params = ['usuario_id' => $usuarioId];
        if ($desde) { $sql .= ' AND DATE(fecha_hora) >= :desde'; $params['desde'] = $desde; }
        if ($hasta) { $sql .= ' AND DATE(fecha_hora) <= :hasta'; $params['hasta'] = $hasta; }
        $sql .= ' ORDER BY fecha_hora DESC';
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return $c->fetchAll();
    }

    public static function buscarPorId(PDO $pdo, int $id): ?array
    {
        $c = $pdo->prepare('SELECT * FROM estado_emocional WHERE id = :id LIMIT 1');
        $c->execute(['id' => $id]);
        return $c->fetch() ?: null;
    }

    public static function actualizar(PDO $pdo, int $id, array $datos): void
    {
        $c = $pdo->prepare(
            'UPDATE estado_emocional SET emocion=:emocion, nivel=:nivel, nota=:nota, editado_en=NOW()
             WHERE id=:id'
        );
        $c->execute(['emocion' => $datos['emocion'], 'nivel' => (int)$datos['nivel'],
                     'nota' => $datos['nota'] ?? null, 'id' => $id]);
    }

    public static function promedioNivelUltimos7Dias(PDO $pdo, int $usuarioId): ?float
    {
        $c = $pdo->prepare(
            'SELECT AVG(nivel) FROM estado_emocional
             WHERE usuario_id=:uid AND fecha_hora >= NOW() - INTERVAL 7 DAY'
        );
        $c->execute(['uid' => $usuarioId]);
        $v = $c->fetchColumn();
        return $v !== false ? round((float)$v, 1) : null;
    }

    public static function diasConsecutivosBajos(PDO $pdo, int $usuarioId, int $nivelMaximo = 2): int
    {
        $c = $pdo->prepare(
            'SELECT DATE(fecha_hora) AS dia, AVG(nivel) AS promedio
             FROM estado_emocional WHERE usuario_id=:uid
             GROUP BY dia ORDER BY dia DESC LIMIT 10'
        );
        $c->execute(['uid' => $usuarioId]);
        $dias = $c->fetchAll();
        $consecutivos = 0;
        foreach ($dias as $d) {
            if ((float)$d['promedio'] <= $nivelMaximo) $consecutivos++;
            else break;
        }
        return $consecutivos;
    }

    public static function resumenPorPeriodo(PDO $pdo, int $usuarioId, string $desde, string $hasta): array
    {
        $c = $pdo->prepare(
            'SELECT DATE(fecha_hora) AS dia, AVG(nivel) AS promedio, COUNT(*) AS registros
             FROM estado_emocional
             WHERE usuario_id=:uid AND DATE(fecha_hora) BETWEEN :desde AND :hasta
             GROUP BY dia ORDER BY dia ASC'
        );
        $c->execute(['uid' => $usuarioId, 'desde' => $desde, 'hasta' => $hasta]);
        return $c->fetchAll();
    }

    // Cuántas veces se registró cada emoción en el período — insumo del gráfico de torta.
    public static function distribucionPorEmocion(PDO $pdo, int $usuarioId, string $desde, string $hasta): array
    {
        $c = $pdo->prepare(
            'SELECT emocion, COUNT(*) AS total FROM estado_emocional
             WHERE usuario_id=:uid AND DATE(fecha_hora) BETWEEN :desde AND :hasta
             GROUP BY emocion ORDER BY total DESC'
        );
        $c->execute(['uid' => $usuarioId, 'desde' => $desde, 'hasta' => $hasta]);
        return $c->fetchAll();
    }

    // Promedio de nivel emocional en un mes calendario completo — insumo de la comparativa mensual.
    public static function promedioPorMes(PDO $pdo, int $usuarioId, int $year, int $month): ?float
    {
        $c = $pdo->prepare(
            'SELECT AVG(nivel) FROM estado_emocional
             WHERE usuario_id=:uid AND YEAR(fecha_hora)=:y AND MONTH(fecha_hora)=:m'
        );
        $c->execute(['uid' => $usuarioId, 'y' => $year, 'm' => $month]);
        $v = $c->fetchColumn();
        return $v !== false && $v !== null ? round((float)$v, 2) : null;
    }
}
