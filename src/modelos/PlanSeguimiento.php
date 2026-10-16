<?php
declare(strict_types=1);

/**
 * Plan de seguimiento del psicólogo/orientador: tareas de control por estudiante
 * (título, fecha límite opcional, estado), a diferencia de notas_sesion (texto
 * libre sin estado). Privado por autor — mismo criterio que notas_sesion: cada
 * profesional solo ve y modifica sus propias tareas.
 */
class PlanSeguimiento
{
    public static function crear(PDO $pdo, int $autorId, int $estudianteId, string $titulo, ?string $descripcion, ?string $fechaLimite): int
    {
        $c = $pdo->prepare(
            'INSERT INTO planes_seguimiento (autor_id, estudiante_id, titulo, descripcion, fecha_limite)
             VALUES (:autor, :est, :titulo, :desc, :fecha)'
        );
        $c->execute([
            'autor' => $autorId,
            'est'   => $estudianteId,
            'titulo' => $titulo,
            'desc'  => ($descripcion !== null && $descripcion !== '') ? $descripcion : null,
            'fecha' => ($fechaLimite !== null && $fechaLimite !== '') ? $fechaLimite : null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** Tareas de un autor sobre un estudiante concreto (sección en estudiante_detalle.php). */
    public static function listarDeEstudiante(PDO $pdo, int $autorId, int $estudianteId): array
    {
        $c = $pdo->prepare(
            "SELECT * FROM planes_seguimiento
             WHERE autor_id = :autor AND estudiante_id = :est
             ORDER BY (estado = 'completado') ASC,
                      (fecha_limite IS NULL) ASC, fecha_limite ASC,
                      fecha_creacion DESC"
        );
        $c->execute(['autor' => $autorId, 'est' => $estudianteId]);
        return $c->fetchAll();
    }

    /**
     * Todas las tareas de un autor, en todos sus estudiantes (página «Mis
     * seguimientos»). Incluye el nombre del estudiante para poder listarlas sin
     * abrir cada ficha una por una.
     */
    public static function listarDeAutor(PDO $pdo, int $autorId): array
    {
        $c = $pdo->prepare(
            "SELECT ps.*, u.nombre AS estudiante_nombre
             FROM planes_seguimiento ps
             INNER JOIN usuarios u ON u.id = ps.estudiante_id
             WHERE ps.autor_id = :autor
             ORDER BY (ps.estado = 'completado') ASC,
                      (ps.fecha_limite IS NULL) ASC, ps.fecha_limite ASC,
                      ps.fecha_creacion DESC"
        );
        $c->execute(['autor' => $autorId]);
        return $c->fetchAll();
    }

    public static function contarPendientesVencidas(PDO $pdo, int $autorId): int
    {
        $c = $pdo->prepare(
            "SELECT COUNT(*) FROM planes_seguimiento
             WHERE autor_id = :autor AND estado != 'completado'
               AND fecha_limite IS NOT NULL AND fecha_limite < CURDATE()"
        );
        $c->execute(['autor' => $autorId]);
        return (int) $c->fetchColumn();
    }

    /** @param string $estado 'pendiente' | 'en_curso' | 'completado' */
    public static function actualizarEstado(PDO $pdo, int $id, int $autorId, string $estado): void
    {
        // autor_id en el WHERE, no solo en el id: así nadie puede marcar o reabrir
        // una tarea ajena aunque adivine el id en el formulario.
        $c = $pdo->prepare(
            "UPDATE planes_seguimiento
             SET estado = :estado, fecha_completado = IF(:estado2 = 'completado', NOW(), NULL)
             WHERE id = :id AND autor_id = :autor"
        );
        $c->execute(['estado' => $estado, 'estado2' => $estado, 'id' => $id, 'autor' => $autorId]);
    }

    public static function eliminar(PDO $pdo, int $id, int $autorId): void
    {
        $c = $pdo->prepare('DELETE FROM planes_seguimiento WHERE id = :id AND autor_id = :autor');
        $c->execute(['id' => $id, 'autor' => $autorId]);
    }
}
