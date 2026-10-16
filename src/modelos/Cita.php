<?php
declare(strict_types=1);

class Cita
{
    /**
     * Franjas reservables de un profesional, agrupadas por fecha — ya descarta las
     * franjas que pasaron o que alguien más ya tomó. Es la vista que ve el estudiante.
     */
    public static function slotsDisponiblesDeProfesional(PDO $pdo, int $profesionalId): array
    {
        $bloques = Disponibilidad::listarDeProfesional($pdo, $profesionalId);
        if (empty($bloques)) {
            return [];
        }

        $ocupadas = $pdo->prepare(
            "SELECT fecha, hora_inicio FROM citas
             WHERE profesional_id = :pid AND estado = 'confirmada' AND fecha >= CURDATE()"
        );
        $ocupadas->execute(['pid' => $profesionalId]);
        $ocupadasSet = [];
        foreach ($ocupadas->fetchAll() as $o) {
            $ocupadasSet[$o['fecha'] . ' ' . $o['hora_inicio']] = true;
        }

        $ahora = time();
        $porFecha = [];
        foreach ($bloques as $b) {
            $inicio = strtotime($b['fecha'] . ' ' . $b['hora_inicio']);
            $finBloque = strtotime($b['fecha'] . ' ' . $b['hora_fin']);
            $duracionSeg = (int) $b['duracion_slot_minutos'] * 60;
            if ($duracionSeg <= 0) continue;
            for ($t = $inicio; $t + $duracionSeg <= $finBloque; $t += $duracionSeg) {
                if ($t <= $ahora) continue; // no ofrecer horarios que ya pasaron
                $horaIni = date('H:i:s', $t);
                $horaFin = date('H:i:s', $t + $duracionSeg);
                if (isset($ocupadasSet[$b['fecha'] . ' ' . $horaIni])) continue;
                $porFecha[$b['fecha']][$horaIni] = ['hora_inicio' => $horaIni, 'hora_fin' => $horaFin];
            }
        }

        $resultado = [];
        foreach ($porFecha as $fecha => $slots) {
            ksort($slots);
            $resultado[] = ['fecha' => $fecha, 'slots' => array_values($slots)];
        }
        ksort($resultado);
        return $resultado;
    }

    public static function reservar(PDO $pdo, int $profesionalId, int $estudianteId, string $fecha, string $horaInicio, string $horaFin, string $motivo = ''): array
    {
        // Multi-colegio: no se puede reservar con un profesional de otro colegio.
        $mismo = $pdo->prepare(
            'SELECT 1 FROM usuarios p INNER JOIN usuarios e ON e.colegio_id = p.colegio_id
             WHERE p.id = :pid AND e.id = :eid AND p.colegio_id IS NOT NULL LIMIT 1'
        );
        $mismo->execute(['pid' => $profesionalId, 'eid' => $estudianteId]);
        if (!$mismo->fetchColumn()) {
            return ['exito' => false, 'mensaje' => 'Ese profesional no está disponible para tu colegio.'];
        }
        try {
            $c = $pdo->prepare(
                'INSERT INTO citas (profesional_id, estudiante_id, fecha, hora_inicio, hora_fin, motivo)
                 VALUES (:pid, :eid, :fecha, :inicio, :fin, :motivo)'
            );
            $c->execute([
                'pid' => $profesionalId, 'eid' => $estudianteId, 'fecha' => $fecha,
                'inicio' => $horaInicio, 'fin' => $horaFin,
                'motivo' => $motivo !== '' ? mb_substr($motivo, 0, 255) : null,
            ]);
            return ['exito' => true, 'id' => (int) $pdo->lastInsertId()];
        } catch (PDOException $e) {
            // franja_unica: alguien más reservó exactamente esa franja un instante antes.
            if ($e->getCode() === '23000') {
                return ['exito' => false, 'mensaje' => 'Ese horario ya no está disponible — alguien más lo tomó justo antes. Elige otro.'];
            }
            throw $e;
        }
    }

    public static function listarDeEstudiante(PDO $pdo, int $estudianteId): array
    {
        $c = $pdo->prepare(
            "SELECT c.*, u.nombre AS profesional_nombre, r.nombre AS profesional_rol
             FROM citas c
             INNER JOIN usuarios u ON u.id = c.profesional_id
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE c.estudiante_id = :eid
             ORDER BY c.fecha DESC, c.hora_inicio DESC"
        );
        $c->execute(['eid' => $estudianteId]);
        return $c->fetchAll();
    }

    /** Próximas citas confirmadas de un profesional (para su agenda). */
    public static function listarDeProfesional(PDO $pdo, int $profesionalId): array
    {
        $c = $pdo->prepare(
            "SELECT c.*, u.nombre AS estudiante_nombre
             FROM citas c
             INNER JOIN usuarios u ON u.id = c.estudiante_id
             WHERE c.profesional_id = :pid AND c.fecha >= CURDATE() AND c.estado = 'confirmada'
             ORDER BY c.fecha ASC, c.hora_inicio ASC"
        );
        $c->execute(['pid' => $profesionalId]);
        return $c->fetchAll();
    }

    public static function buscarPorId(PDO $pdo, int $id): ?array
    {
        $c = $pdo->prepare('SELECT * FROM citas WHERE id = :id LIMIT 1');
        $c->execute(['id' => $id]);
        return $c->fetch() ?: null;
    }

    /** Cancela solo si quien cancela es el estudiante o el profesional de esa cita. */
    public static function cancelar(PDO $pdo, int $id, int $usuarioId): bool
    {
        $cita = self::buscarPorId($pdo, $id);
        if (!$cita || ((int) $cita['estudiante_id'] !== $usuarioId && (int) $cita['profesional_id'] !== $usuarioId)) {
            return false;
        }
        $pdo->prepare("UPDATE citas SET estado='cancelada', cancelado_por=:uid WHERE id=:id")
            ->execute(['uid' => $usuarioId, 'id' => $id]);
        return true;
    }

    /**
     * Mueve una cita confirmada a un horario nuevo del MISMO profesional, en vez de
     * cancelarla y crear una reserva aparte (así no se pierde el motivo escrito, y
     * es una sola operación en lugar de dos). La puede pedir el estudiante o el
     * profesional de esa cita — nadie más. Resetea recordatorio_enviado para que el
     * aviso de "un día antes" vuelva a salir, ahora con la fecha nueva.
     *
     * @return array{exito: bool, mensaje?: string}
     */
    public static function reprogramar(PDO $pdo, int $id, int $usuarioId, string $nuevaFecha, string $nuevaHoraInicio, string $nuevaHoraFin): array
    {
        $cita = self::buscarPorId($pdo, $id);
        if (!$cita || ((int) $cita['estudiante_id'] !== $usuarioId && (int) $cita['profesional_id'] !== $usuarioId)) {
            return ['exito' => false, 'mensaje' => 'Esa cita no existe o no te pertenece.'];
        }
        if ($cita['estado'] !== 'confirmada') {
            return ['exito' => false, 'mensaje' => 'Solo se pueden reprogramar citas confirmadas.'];
        }
        if (strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']) <= time()) {
            return ['exito' => false, 'mensaje' => 'Esta cita ya pasó, no se puede reprogramar.'];
        }
        try {
            $c = $pdo->prepare(
                'UPDATE citas SET fecha = :fecha, hora_inicio = :inicio, hora_fin = :fin, recordatorio_enviado = 0
                 WHERE id = :id'
            );
            $c->execute(['fecha' => $nuevaFecha, 'inicio' => $nuevaHoraInicio, 'fin' => $nuevaHoraFin, 'id' => $id]);
            return ['exito' => true];
        } catch (PDOException $e) {
            // franja_unica: alguien más tomó ese horario justo antes.
            if ($e->getCode() === '23000') {
                return ['exito' => false, 'mensaje' => 'Ese horario ya no está disponible — alguien más lo tomó justo antes. Elige otro.'];
            }
            throw $e;
        }
    }

    /**
     * Citas confirmadas que son MAÑANA y todavía no mandaron el recordatorio de un
     * día antes — para public/tareas/recordatorios.php.
     */
    public static function pendientesDeRecordatorio(PDO $pdo): array
    {
        return $pdo->query(
            "SELECT c.*,
                    ue.nombre AS estudiante_nombre, ue.correo AS estudiante_correo,
                    up.nombre AS profesional_nombre, up.correo AS profesional_correo, rp.nombre AS profesional_rol
             FROM citas c
             INNER JOIN usuarios ue ON ue.id = c.estudiante_id
             INNER JOIN usuarios up ON up.id = c.profesional_id
             INNER JOIN roles rp ON rp.id = up.rol_id
             WHERE c.estado = 'confirmada' AND c.recordatorio_enviado = 0
               AND c.fecha = CURDATE() + INTERVAL 1 DAY"
        )->fetchAll();
    }

    public static function marcarRecordatorioEnviado(PDO $pdo, int $id): void
    {
        $pdo->prepare('UPDATE citas SET recordatorio_enviado = 1 WHERE id = :id')->execute(['id' => $id]);
    }
}
