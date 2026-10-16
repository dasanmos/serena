<?php
declare(strict_types=1);

/**
 * Quién abrió la ficha de un estudiante (o exportó su caso en PDF), y cuándo.
 * A propósito NO vive en la tabla `auditoria`: esa bitácora registra cambios
 * (crear, editar, aprobar...) y mezclarla con cientos de vistas rutinarias del
 * día a día la haría inútil para lo que sirve hoy. Este registro se muestra
 * directamente dentro de la ficha del estudiante — ver
 * public/psicologo/estudiante_detalle.php, sección "Quién ha visto esta ficha".
 */
class AccesoFicha
{
    // Si la misma persona vuelve a abrir la misma ficha antes de que pase este
    // tiempo, no se guarda una fila nueva — evita que un solo vistazo (que
    // recarga la página varias veces: guardar una nota, cambiar una tarea de
    // seguimiento, etc.) aparezca como visitas repetidas en la lista.
    private const MINUTOS_ANTES_DE_REPETIR = 15;

    public static function registrar(PDO $pdo, int $usuarioId, int $estudianteId): void
    {
        $c = $pdo->prepare(
            'SELECT 1 FROM accesos_ficha
             WHERE usuario_id = :u AND estudiante_id = :e AND fecha >= DATE_SUB(NOW(), INTERVAL :min MINUTE)
             LIMIT 1'
        );
        $c->execute(['u' => $usuarioId, 'e' => $estudianteId, 'min' => self::MINUTOS_ANTES_DE_REPETIR]);
        if ($c->fetchColumn()) {
            return;
        }
        $pdo->prepare('INSERT INTO accesos_ficha (usuario_id, estudiante_id) VALUES (:u, :e)')
            ->execute(['u' => $usuarioId, 'e' => $estudianteId]);
    }

    /** Últimos accesos a la ficha de un estudiante, de cualquier profesional. */
    public static function listarDeEstudiante(PDO $pdo, int $estudianteId, int $limite = 10): array
    {
        $c = $pdo->prepare(
            "SELECT af.fecha, u.nombre, r.nombre AS rol
             FROM accesos_ficha af
             INNER JOIN usuarios u ON u.id = af.usuario_id
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE af.estudiante_id = :est
             ORDER BY af.fecha DESC
             LIMIT " . max(1, $limite)
        );
        $c->execute(['est' => $estudianteId]);
        return $c->fetchAll();
    }
}
