<?php
declare(strict_types=1);

class Disponibilidad
{
    private const DURACIONES_VALIDAS = [15, 20, 30, 45, 60];

    public static function crear(PDO $pdo, int $profesionalId, string $fecha, string $horaInicio, string $horaFin, int $duracionMinutos): array
    {
        if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
            return ['exito' => false, 'mensaje' => 'La fecha no puede ser en el pasado.'];
        }
        if (strtotime($horaFin) <= strtotime($horaInicio)) {
            return ['exito' => false, 'mensaje' => 'La hora de fin debe ser después de la hora de inicio.'];
        }
        if (!in_array($duracionMinutos, self::DURACIONES_VALIDAS, true)) {
            return ['exito' => false, 'mensaje' => 'Duración de turno inválida.'];
        }
        $minutosBloque = (strtotime($horaFin) - strtotime($horaInicio)) / 60;
        if ($minutosBloque < $duracionMinutos) {
            return ['exito' => false, 'mensaje' => 'El bloque es más corto que la duración de turno elegida.'];
        }

        $c = $pdo->prepare(
            'INSERT INTO disponibilidad_citas (profesional_id, fecha, hora_inicio, hora_fin, duracion_slot_minutos)
             VALUES (:pid, :fecha, :inicio, :fin, :duracion)'
        );
        $c->execute([
            'pid' => $profesionalId, 'fecha' => $fecha,
            'inicio' => $horaInicio, 'fin' => $horaFin, 'duracion' => $duracionMinutos,
        ]);
        return ['exito' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /** Bloques futuros (hoy en adelante) de un profesional, más recientes primero. */
    public static function listarDeProfesional(PDO $pdo, int $profesionalId): array
    {
        $c = $pdo->prepare(
            'SELECT * FROM disponibilidad_citas
             WHERE profesional_id = :pid AND fecha >= CURDATE()
             ORDER BY fecha ASC, hora_inicio ASC'
        );
        $c->execute(['pid' => $profesionalId]);
        return $c->fetchAll();
    }

    public static function eliminar(PDO $pdo, int $id, int $profesionalId): void
    {
        $pdo->prepare('DELETE FROM disponibilidad_citas WHERE id = :id AND profesional_id = :pid')
            ->execute(['id' => $id, 'pid' => $profesionalId]);
    }
}
