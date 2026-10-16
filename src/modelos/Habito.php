<?php
declare(strict_types=1);

class Habito
{
    public static function crear(PDO $pdo, int $usuarioId, array $datos): int
    {
        $c = $pdo->prepare(
            'INSERT INTO habitos (usuario_id, nombre, categoria, frecuencia, dias_semana)
             VALUES (:uid, :nombre, :categoria, :frecuencia, :dias)'
        );
        $c->execute([
            'uid'       => $usuarioId,
            'nombre'    => $datos['nombre'],
            'categoria' => $datos['categoria'],
            'frecuencia'=> $datos['frecuencia'],
            'dias'      => $datos['dias_semana'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function listarDeUsuario(PDO $pdo, int $usuarioId, bool $soloActivos = true): array
    {
        $sql = 'SELECT * FROM habitos WHERE usuario_id=:uid';
        if ($soloActivos) $sql .= ' AND activo=1';
        $sql .= ' ORDER BY nombre';
        $c = $pdo->prepare($sql);
        $c->execute(['uid' => $usuarioId]);
        return $c->fetchAll();
    }

    public static function buscarPorId(PDO $pdo, int $id, int $usuarioId): ?array
    {
        $c = $pdo->prepare('SELECT * FROM habitos WHERE id=:id AND usuario_id=:uid LIMIT 1');
        $c->execute(['id' => $id, 'uid' => $usuarioId]);
        return $c->fetch() ?: null;
    }

    public static function actualizar(PDO $pdo, int $id, array $datos): void
    {
        $c = $pdo->prepare('UPDATE habitos SET nombre=:nombre, categoria=:categoria, frecuencia=:frecuencia, dias_semana=:dias WHERE id=:id');
        $c->execute(['nombre' => $datos['nombre'], 'categoria' => $datos['categoria'],
                     'frecuencia' => $datos['frecuencia'], 'dias' => $datos['dias_semana'] ?? null, 'id' => $id]);
    }

    public static function archivar(PDO $pdo, int $id): void
    {
        $pdo->prepare('UPDATE habitos SET activo=0 WHERE id=:id')->execute(['id' => $id]);
    }

    public static function marcarCompletado(PDO $pdo, int $habitoId, string $fecha, bool $completado): void
    {
        $c = $pdo->prepare(
            'INSERT INTO seguimiento_habitos (habito_id, fecha, completado) VALUES (:hid, :fecha, :comp)
             ON DUPLICATE KEY UPDATE completado=VALUES(completado)'
        );
        $c->execute(['hid' => $habitoId, 'fecha' => $fecha, 'comp' => $completado ? 1 : 0]);
    }

    public static function historialMensual(PDO $pdo, int $habitoId, int $year, int $month): array
    {
        $c = $pdo->prepare(
            'SELECT fecha, completado FROM seguimiento_habitos
             WHERE habito_id=:hid AND YEAR(fecha)=:y AND MONTH(fecha)=:m ORDER BY fecha'
        );
        $c->execute(['hid' => $habitoId, 'y' => $year, 'm' => $month]);
        return $c->fetchAll();
    }

    // Total de marcas "completado" en todos los hábitos del usuario, en un mes calendario completo —
    // insumo de la comparativa mensual en estadísticas.
    public static function completadosPorMes(PDO $pdo, int $usuarioId, int $year, int $month): int
    {
        $c = $pdo->prepare(
            'SELECT COUNT(*) FROM seguimiento_habitos sh
             INNER JOIN habitos h ON h.id = sh.habito_id
             WHERE h.usuario_id=:uid AND sh.completado=1 AND YEAR(sh.fecha)=:y AND MONTH(sh.fecha)=:m'
        );
        $c->execute(['uid' => $usuarioId, 'y' => $year, 'm' => $month]);
        return (int) $c->fetchColumn();
    }

    public static function rachaActual(PDO $pdo, int $habitoId): int
    {
        $c = $pdo->prepare(
            'SELECT fecha FROM seguimiento_habitos WHERE habito_id=:hid AND completado=1 ORDER BY fecha DESC LIMIT 60'
        );
        $c->execute(['hid' => $habitoId]);
        $fechas = $c->fetchAll(PDO::FETCH_COLUMN);
        if (empty($fechas)) return 0;
        $racha = 0;
        $esperada = new DateTime();
        foreach ($fechas as $f) {
            $d = new DateTime($f);
            $diff = (int)$esperada->diff($d)->days;
            if ($diff <= 1) { $racha++; $esperada = $d; }
            else break;
        }
        return $racha;
    }
}
