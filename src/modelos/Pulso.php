<?php
declare(strict_types=1);

/**
 * Pulso de grupo: el docente lanza una pregunta rápida (escala 1-5) a todo su
 * curso. A propósito NO es información emocional individual — el docente
 * nunca ve qué respondió cada estudiante, solo el agregado, y solo cuando hay
 * suficientes respuestas para que ninguna quede identificable (ver
 * RESPUESTAS_MINIMAS_PARA_VER). Complementa, no reemplaza, las alertas
 * tempranas de psicología/orientación, que sí trabajan con datos individuales.
 */
class Pulso
{
    public const DURACION_DIAS = 7;
    public const RESPUESTAS_MINIMAS_PARA_VER = 3;

    public const PREGUNTAS_SUGERIDAS = [
        '¿Cómo sientes el ambiente en el salón esta semana?',
        '¿Qué tan pesada sintió la carga académica esta semana?',
        '¿Qué tanto sentiste que el grupo se apoyó entre sí esta semana?',
    ];

    /**
     * Crea un pulso nuevo para el grupo. Cierra primero cualquier pulso abierto
     * del mismo grupo (nunca hay dos activos a la vez, para no confundir a los
     * estudiantes sobre cuál responder).
     */
    public static function crear(PDO $pdo, int $docenteId, int $grupoId, string $pregunta): int
    {
        self::cerrarAbiertosDelGrupo($pdo, $grupoId);
        $c = $pdo->prepare(
            'INSERT INTO pulsos_grupo (docente_id, grupo_id, pregunta, fecha_cierre)
             VALUES (:docente, :grupo, :pregunta, DATE_ADD(NOW(), INTERVAL :dias DAY))'
        );
        $c->execute([
            'docente' => $docenteId,
            'grupo'   => $grupoId,
            'pregunta' => $pregunta,
            'dias'    => self::DURACION_DIAS,
        ]);
        return (int) $pdo->lastInsertId();
    }

    private static function cerrarAbiertosDelGrupo(PDO $pdo, int $grupoId): void
    {
        $c = $pdo->prepare(
            "UPDATE pulsos_grupo SET cerrado_manualmente = 1
             WHERE grupo_id = :grupo AND cerrado_manualmente = 0 AND fecha_cierre > NOW()"
        );
        $c->execute(['grupo' => $grupoId]);
    }

    /** El pulso activo del grupo ahora mismo (ni vencido ni cerrado a mano), o null si no hay. */
    public static function activoDeGrupo(PDO $pdo, int $grupoId): ?array
    {
        $c = $pdo->prepare(
            "SELECT * FROM pulsos_grupo
             WHERE grupo_id = :grupo AND cerrado_manualmente = 0 AND fecha_cierre > NOW()
             ORDER BY fecha_creacion DESC LIMIT 1"
        );
        $c->execute(['grupo' => $grupoId]);
        $fila = $c->fetch();
        return $fila ?: null;
    }

    public static function yaRespondio(PDO $pdo, int $pulsoId, int $estudianteId): bool
    {
        $c = $pdo->prepare('SELECT 1 FROM pulso_respuestas WHERE pulso_id = :p AND estudiante_id = :e');
        $c->execute(['p' => $pulsoId, 'e' => $estudianteId]);
        return (bool) $c->fetchColumn();
    }

    /** @param int $valor 1 a 5 */
    public static function responder(PDO $pdo, int $pulsoId, int $estudianteId, int $valor): void
    {
        // INSERT IGNORE + la restricción UNIQUE(pulso_id, estudiante_id) de la tabla:
        // si el estudiante ya respondió (doble clic, dos pestañas), la segunda
        // respuesta simplemente no se guarda, en vez de dar error.
        $c = $pdo->prepare('INSERT IGNORE INTO pulso_respuestas (pulso_id, estudiante_id, valor) VALUES (:p, :e, :v)');
        $c->execute(['p' => $pulsoId, 'e' => $estudianteId, 'v' => max(1, min(5, $valor))]);
    }

    /**
     * Historial de pulsos del docente para este grupo, con el conteo de
     * respuestas de cada uno (para la lista en public/docente/pulso.php).
     */
    public static function historialDeGrupo(PDO $pdo, int $grupoId, int $limite = 20): array
    {
        $c = $pdo->prepare(
            "SELECT pg.*, (SELECT COUNT(*) FROM pulso_respuestas pr WHERE pr.pulso_id = pg.id) AS total_respuestas
             FROM pulsos_grupo pg
             WHERE pg.grupo_id = :grupo
             ORDER BY pg.fecha_creacion DESC
             LIMIT " . max(1, $limite)
        );
        $c->execute(['grupo' => $grupoId]);
        return $c->fetchAll();
    }

    /**
     * Agregado de un pulso: promedio, total de respuestas y cuántas hubo de
     * cada valor (1 a 5) — nunca quién respondió qué. Devuelve null si todavía
     * no hay suficientes respuestas para mostrarlo sin arriesgar el anonimato.
     */
    public static function resultados(PDO $pdo, int $pulsoId): ?array
    {
        $c = $pdo->prepare('SELECT valor, COUNT(*) AS n FROM pulso_respuestas WHERE pulso_id = :p GROUP BY valor');
        $c->execute(['p' => $pulsoId]);
        $distribucion = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $total = 0;
        $suma = 0;
        foreach ($c->fetchAll() as $fila) {
            $v = (int) $fila['valor'];
            $n = (int) $fila['n'];
            $distribucion[$v] = $n;
            $total += $n;
            $suma += $v * $n;
        }
        if ($total < self::RESPUESTAS_MINIMAS_PARA_VER) {
            return ['total' => $total, 'insuficiente' => true];
        }
        return [
            'total' => $total,
            'insuficiente' => false,
            'promedio' => round($suma / $total, 2),
            'distribucion' => $distribucion,
        ];
    }

    public static function cerrarManual(PDO $pdo, int $pulsoId, int $docenteId): void
    {
        $c = $pdo->prepare('UPDATE pulsos_grupo SET cerrado_manualmente = 1 WHERE id = :id AND docente_id = :docente');
        $c->execute(['id' => $pulsoId, 'docente' => $docenteId]);
    }
}
