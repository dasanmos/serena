<?php
declare(strict_types=1);

class Sueno
{
    public static function registrar(PDO $pdo, int $usuarioId, array $datos): int
    {
        $c = $pdo->prepare(
            'INSERT INTO sueno (usuario_id, fecha, horas, calidad, hora_dormir, hora_despertar)
             VALUES (:uid, :fecha, :horas, :calidad, :dormir, :despertar)
             ON DUPLICATE KEY UPDATE horas=VALUES(horas), calidad=VALUES(calidad),
             hora_dormir=VALUES(hora_dormir), hora_despertar=VALUES(hora_despertar)'
        );
        $c->execute([
            'uid'      => $usuarioId,
            'fecha'    => $datos['fecha'],
            'horas'    => $datos['horas'],
            'calidad'  => $datos['calidad'],
            'dormir'   => $datos['hora_dormir'] ?: null,
            'despertar'=> $datos['hora_despertar'] ?: null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function listarDeUsuario(PDO $pdo, int $usuarioId, int $limite = 30): array
    {
        $c = $pdo->prepare('SELECT * FROM sueno WHERE usuario_id=:uid ORDER BY fecha DESC LIMIT '.(int)$limite);
        $c->execute(['uid' => $usuarioId]);
        return $c->fetchAll();
    }

    public static function deHoy(PDO $pdo, int $usuarioId): ?array
    {
        $c = $pdo->prepare('SELECT * FROM sueno WHERE usuario_id=:uid AND fecha=CURDATE() LIMIT 1');
        $c->execute(['uid' => $usuarioId]);
        return $c->fetch() ?: null;
    }

    public static function resumenPorPeriodo(PDO $pdo, int $usuarioId, string $desde, string $hasta): array
    {
        $c = $pdo->prepare(
            'SELECT fecha, horas, calidad FROM sueno
             WHERE usuario_id=:uid AND fecha BETWEEN :desde AND :hasta ORDER BY fecha ASC'
        );
        $c->execute(['uid' => $usuarioId, 'desde' => $desde, 'hasta' => $hasta]);
        return $c->fetchAll();
    }

    // Promedio de horas de sueño en un mes calendario completo — insumo de la comparativa mensual.
    public static function promedioPorMes(PDO $pdo, int $usuarioId, int $year, int $month): ?float
    {
        $c = $pdo->prepare(
            'SELECT AVG(horas) FROM sueno WHERE usuario_id=:uid AND YEAR(fecha)=:y AND MONTH(fecha)=:m'
        );
        $c->execute(['uid' => $usuarioId, 'y' => $year, 'm' => $month]);
        $v = $c->fetchColumn();
        return $v !== false && $v !== null ? round((float)$v, 1) : null;
    }
}
