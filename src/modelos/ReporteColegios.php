<?php
declare(strict_types=1);

/**
 * Métricas AGREGADAS por colegio para la Secretaría. Nunca devuelve datos de un
 * estudiante individual: solo conteos y promedios.
 */
require_once __DIR__ . '/../helpers/secretaria.php';

class ReporteColegios
{
    /** Mínimo de estudiantes con registro para mostrar un promedio emocional (evita identificar casos). */
    public const MIN_PARA_PROMEDIO = 5;

    public static function metricas(PDO $pdo, string $desde, string $hasta): array
    {
        $d = $pdo->quote($desde);
        $h = $pdo->quote($hasta);
        $sql = "SELECT c.id, c.nombre, c.dane, c.municipio, c.activo,
            (SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
              WHERE u.colegio_id = c.id AND r.nombre = 'estudiante' AND u.activo = 1) AS estudiantes,
            (SELECT COUNT(DISTINCT e.usuario_id) FROM estado_emocional e INNER JOIN usuarios u ON u.id = e.usuario_id
              WHERE u.colegio_id = c.id AND e.fecha_hora >= {$d} AND e.fecha_hora < {$h}) AS con_registro,
            (SELECT COUNT(*) FROM estado_emocional e INNER JOIN usuarios u ON u.id = e.usuario_id
              WHERE u.colegio_id = c.id AND e.fecha_hora >= {$d} AND e.fecha_hora < {$h}) AS registros,
            (SELECT COALESCE(SUM(e.nivel), 0) FROM estado_emocional e INNER JOIN usuarios u ON u.id = e.usuario_id
              WHERE u.colegio_id = c.id AND e.fecha_hora >= {$d} AND e.fecha_hora < {$h}) AS suma_nivel,
            (SELECT COUNT(*) FROM alertas_tempranas a INNER JOIN usuarios u ON u.id = a.usuario_id
              WHERE u.colegio_id = c.id AND a.fecha_generada >= {$d} AND a.fecha_generada < {$h}) AS alertas,
            (SELECT COUNT(*) FROM alertas_tempranas a INNER JOIN usuarios u ON u.id = a.usuario_id
              WHERE u.colegio_id = c.id AND a.fecha_generada >= {$d} AND a.fecha_generada < {$h} AND a.nivel_urgencia = 'alto') AS alertas_altas,
            (SELECT COUNT(*) FROM alertas_tempranas a INNER JOIN usuarios u ON u.id = a.usuario_id
              WHERE u.colegio_id = c.id AND a.fecha_generada >= {$d} AND a.fecha_generada < {$h} AND a.atendida = 1) AS alertas_atendidas,
            (SELECT COUNT(*) FROM alertas_tempranas a INNER JOIN usuarios u ON u.id = a.usuario_id
              WHERE u.colegio_id = c.id AND a.atendida = 0 AND a.nivel_urgencia = 'alto') AS altas_pendientes,
            (SELECT MAX(u.ultimo_acceso) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
              WHERE u.colegio_id = c.id AND r.nombre <> 'estudiante') AS ultimo_acceso_personal
            FROM colegios c WHERE 1 = 1" . sqlAlcanceColegios($pdo, 'c.id') . " ORDER BY c.nombre";
        return $pdo->query($sql)->fetchAll();
    }

    /** % de estudiantes con al menos un registro emocional en el periodo. */
    public static function participacion(array $f): ?float
    {
        return (int) $f['estudiantes'] > 0 ? round(100 * (int) $f['con_registro'] / (int) $f['estudiantes'], 1) : null;
    }

    /** Promedio emocional (1-5) o null si hay muy pocos estudiantes para mostrarlo sin riesgo. */
    public static function promedio(array $f): ?float
    {
        if ((int) $f['registros'] === 0 || (int) $f['con_registro'] < self::MIN_PARA_PROMEDIO) {
            return null;
        }
        return round((int) $f['suma_nivel'] / (int) $f['registros'], 2);
    }

    /** 'verde' | 'amarillo' | 'rojo' | 'gris' (suspendido o sin estudiantes). */
    public static function semaforo(array $f): string
    {
        $uso = self::participacion($f);
        if ((int) $f['activo'] !== 1 || $uso === null) {
            return 'gris';
        }
        if ($uso < 30 || (int) $f['altas_pendientes'] >= 5) {
            return 'rojo';
        }
        if ($uso < 60 || (int) $f['altas_pendientes'] >= 1) {
            return 'amarillo';
        }
        return 'verde';
    }

    /** Evita inyección de fórmulas al abrir un CSV en Excel. */
    public static function csvSeguro($valor)
    {
        return is_string($valor) && preg_match('/^[=+\-@]/', $valor) ? "'" . $valor : $valor;
    }
}
