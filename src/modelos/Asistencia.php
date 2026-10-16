<?php
declare(strict_types=1);

/**
 * Asistencia por clase (migración 036).
 * Un registro = estudiante + asignación académica (clase) + fecha.
 *
 * Definiciones (se muestran tal cual en los reportes):
 *  · «Clases con ausencia»  = registros con estado ausente o excusa.
 *  · «Días con ausencia sin justificar» = fechas distintas con al menos un registro «ausente»
 *    (faltar a 6 clases el mismo día cuenta como 1 día, no como 6).
 *  · % de inasistencia = (ausente + excusa) / registros. % sin justificar = ausente / registros.
 */
final class Asistencia
{
    public const ESTADOS = [
        'presente' => 'Presente',
        'tarde'    => 'Llegó tarde',
        'ausente'  => 'Ausente',
        'excusa'   => 'Excusa',
        'permiso'  => 'Permiso',
    ];
    /** Ayuda corta para cada estado (se muestra en la pantalla de tomar lista). */
    public const AYUDA = [
        'presente' => 'Asistió a la clase.',
        'tarde'    => 'Llegó después de iniciada la clase.',
        'ausente'  => 'No asistió y no hay justificación.',
        'excusa'   => 'No asistió, con justificación.',
        'permiso'  => 'Salió con autorización.',
    ];
    /** Alerta al director de grupo: días con ausencia sin justificar dentro de la ventana. */
    public const UMBRAL_DIAS = 3;
    public const VENTANA_DIAS = 14;

    /** Valida «YYYY-MM-DD» real. */
    public static function fechaValida(string $f): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $f);
        return $d !== false && $d->format('Y-m-d') === $f;
    }

    /** @return array<int, array{estado: string, observacion: ?string}> por estudiante_id */
    public static function sesion(PDO $pdo, int $asignacionId, string $fecha): array
    {
        $q = $pdo->prepare('SELECT estudiante_id, estado, observacion FROM asistencia_registros WHERE asignacion_id = ? AND fecha = ?');
        $q->execute([$asignacionId, $fecha]);
        $res = [];
        foreach ($q->fetchAll() as $r) {
            $res[(int) $r['estudiante_id']] = ['estado' => (string) $r['estado'], 'observacion' => $r['observacion']];
        }
        return $res;
    }

    /** Últimas fechas en que se tomó lista en una asignación: [fecha => n_registros]. */
    public static function fechasRecientes(PDO $pdo, int $asignacionId, int $limite = 8): array
    {
        $q = $pdo->prepare('SELECT fecha, COUNT(*) AS n FROM asistencia_registros WHERE asignacion_id = ? GROUP BY fecha ORDER BY fecha DESC LIMIT ' . (int) $limite);
        $q->execute([$asignacionId]);
        $res = [];
        foreach ($q->fetchAll() as $r) {
            $res[(string) $r['fecha']] = (int) $r['n'];
        }
        return $res;
    }

    /**
     * Guarda la lista de una clase y fecha. Solo se aceptan estudiantes que pertenezcan a $estudiantes
     * (los del grupo de la asignación) y estados válidos.
     * @param array $asig        fila de asignaciones_academicas (id, colegio_id, anio_lectivo_id, grupo_id)
     * @param array $anio        fila de anios_lectivos
     * @param array $estudiantes lista de ['id' => ..., 'nombre' => ...]
     * @param array $estados     [estudiante_id => estado]
     * @param array $obs         [estudiante_id => observación]
     * @return array{guardados: int, errores: string[], alertas: int}  alertas = estudiantes de esta lista que ya acumulan el umbral
     */
    public static function guardarSesion(PDO $pdo, array $asig, array $anio, string $fecha, array $estudiantes, array $estados, array $obs, int $usuarioId): array
    {
        $res = ['guardados' => 0, 'errores' => [], 'alertas' => 0];
        if (!self::fechaValida($fecha)) {
            $res['errores'][] = 'La fecha no es válida.';
            return $res;
        }
        if ($fecha > date('Y-m-d')) {
            $res['errores'][] = 'No se puede tomar lista de una fecha futura.';
            return $res;
        }
        if (!empty($anio['fecha_inicio']) && $fecha < $anio['fecha_inicio']) {
            $res['errores'][] = 'La fecha es anterior al inicio del año lectivo (' . $anio['fecha_inicio'] . ').';
            return $res;
        }
        if (!empty($anio['fecha_fin']) && $fecha > $anio['fecha_fin']) {
            $res['errores'][] = 'La fecha es posterior al fin del año lectivo (' . $anio['fecha_fin'] . ').';
            return $res;
        }
        $ins = $pdo->prepare(
            'INSERT INTO asistencia_registros (colegio_id, anio_lectivo_id, asignacion_id, estudiante_id, fecha, estado, observacion, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE estado = VALUES(estado), observacion = VALUES(observacion), registrado_por = VALUES(registrado_por)'
        );
        $ausentes = [];
        $pdo->beginTransaction();
        try {
            foreach ($estudiantes as $s) {
                $sid = (int) $s['id'];
                $estado = (string) ($estados[$sid] ?? '');
                if (!isset(self::ESTADOS[$estado])) {
                    continue; // sin dato para ese estudiante: no se toca
                }
                $o = trim((string) ($obs[$sid] ?? ''));
                $o = $o === '' ? null : mb_substr($o, 0, 255);
                $ins->execute([(int) $asig['colegio_id'], (int) $asig['anio_lectivo_id'], (int) $asig['id'], $sid, $fecha, $estado, $o, $usuarioId]);
                $res['guardados']++;
                if ($estado === 'ausente') {
                    $ausentes[] = $s;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Asistencia::guardarSesion — ' . $e->getMessage());
            $res['errores'][] = 'No se pudo guardar la lista. Inténtalo de nuevo.';
            $res['guardados'] = 0;
            return $res;
        }
        foreach ($ausentes as $s) {
            if (self::diasAusenteRecientes($pdo, (int) $s['id'], (int) $asig['anio_lectivo_id']) >= self::UMBRAL_DIAS) {
                $res['alertas']++; // estudiantes de esta lista que ya están en el umbral
            }
        }
        return $res;
    }

    /** Días distintos con ausencia sin justificar en los últimos $dias (hasta hoy). */
    public static function diasAusenteRecientes(PDO $pdo, int $estudianteId, int $anioId, int $dias = self::VENTANA_DIAS): int
    {
        $q = $pdo->prepare(
            "SELECT COUNT(DISTINCT fecha) FROM asistencia_registros
              WHERE estudiante_id = ? AND anio_lectivo_id = ? AND estado = 'ausente'
                AND fecha > DATE_SUB(CURDATE(), INTERVAL ? DAY) AND fecha <= CURDATE()"
        );
        $q->execute([$estudianteId, $anioId, $dias]);
        return (int) $q->fetchColumn();
    }

    /**
     * Estudiantes de los grupos que dirige $directorId con >= umbral días de ausencia sin justificar en la ventana.
     * Se consulta en vivo (lo muestra el panel del docente): en Serena solo el estudiante recibe notificaciones.
     */
    public static function deDirector(PDO $pdo, int $colegioId, int $anioId, int $directorId, int $umbral = self::UMBRAL_DIAS, int $ventana = self::VENTANA_DIAS): array
    {
        $q = $pdo->prepare(
            "SELECT u.id, u.nombre, CONCAT(gr.nombre, ' ', g.nombre) AS grupo, COUNT(DISTINCT r.fecha) AS dias, MAX(r.fecha) AS ultima
               FROM grupos g
               INNER JOIN grados gr ON gr.id = g.grado_id
               INNER JOIN matriculas m ON m.grupo_id = g.id AND m.anio_lectivo_id = ? AND m.estado = 'activa'
               INNER JOIN asistencia_registros r ON r.estudiante_id = m.estudiante_id AND r.anio_lectivo_id = m.anio_lectivo_id
                    AND r.estado = 'ausente' AND r.fecha > DATE_SUB(CURDATE(), INTERVAL ? DAY) AND r.fecha <= CURDATE()
               INNER JOIN usuarios u ON u.id = m.estudiante_id
              WHERE g.docente_id = ? AND gr.colegio_id = ?
              GROUP BY u.id, u.nombre, gr.nombre, g.nombre
             HAVING dias >= ?
              ORDER BY dias DESC, u.nombre"
        );
        $q->execute([$anioId, $ventana, $directorId, $colegioId, $umbral]);
        return $q->fetchAll();
    }

    private static function resumenVacio(): array
    {
        return ['presente' => 0, 'tarde' => 0, 'ausente' => 0, 'excusa' => 0, 'permiso' => 0, 'total' => 0,
                'dias_ausente' => 0, 'pct_inasistencia' => null, 'pct_sin_justificar' => null];
    }

    private static function completarPorcentajes(array $r): array
    {
        $r['pct_inasistencia'] = $r['total'] > 0 ? round(100 * ($r['ausente'] + $r['excusa']) / $r['total'], 1) : null;
        $r['pct_sin_justificar'] = $r['total'] > 0 ? round(100 * $r['ausente'] / $r['total'], 1) : null;
        return $r;
    }

    /** Resumen de un estudiante en un año (opcionalmente entre dos fechas). */
    public static function resumenEstudiante(PDO $pdo, int $estudianteId, int $anioId, ?string $desde = null, ?string $hasta = null): array
    {
        $sql = 'SELECT estado, COUNT(*) AS n, COUNT(DISTINCT fecha) AS dias FROM asistencia_registros WHERE estudiante_id = ? AND anio_lectivo_id = ?';
        $p = [$estudianteId, $anioId];
        if ($desde !== null) { $sql .= ' AND fecha >= ?'; $p[] = $desde; }
        if ($hasta !== null) { $sql .= ' AND fecha <= ?'; $p[] = $hasta; }
        $q = $pdo->prepare($sql . ' GROUP BY estado');
        $q->execute($p);
        $r = self::resumenVacio();
        foreach ($q->fetchAll() as $f) {
            if (isset(self::ESTADOS[$f['estado']])) {
                $r[$f['estado']] = (int) $f['n'];
                $r['total'] += (int) $f['n'];
                if ($f['estado'] === 'ausente') {
                    $r['dias_ausente'] = (int) $f['dias'];
                }
            }
        }
        return self::completarPorcentajes($r);
    }

    /** Últimos registros que NO son «presente» (lo que la familia necesita ver). */
    public static function incidencias(PDO $pdo, int $estudianteId, int $anioId, int $limite = 40): array
    {
        $q = $pdo->prepare(
            "SELECT r.fecha, r.estado, r.observacion, s.nombre AS asignatura
               FROM asistencia_registros r
               INNER JOIN asignaciones_academicas a ON a.id = r.asignacion_id
               INNER JOIN asignaturas s ON s.id = a.asignatura_id
              WHERE r.estudiante_id = ? AND r.anio_lectivo_id = ? AND r.estado <> 'presente'
              ORDER BY r.fecha DESC, s.nombre LIMIT " . (int) $limite
        );
        $q->execute([$estudianteId, $anioId]);
        return $q->fetchAll();
    }

    /**
     * Resumen por estudiante de un grupo (todas sus clases) en un rango.
     * @param array $estudiantes lista de ['id', 'nombre']
     * @return array<int, array> por estudiante_id, con 'nombre' y los contadores
     */
    public static function resumenGrupo(PDO $pdo, int $colegioId, int $anioId, array $estudiantes, int $grupoId, ?string $desde, ?string $hasta): array
    {
        $res = [];
        foreach ($estudiantes as $s) {
            $res[(int) $s['id']] = self::resumenVacio() + ['nombre' => (string) $s['nombre']];
        }
        if (!$res) {
            return [];
        }
        $sql = "SELECT r.estudiante_id, r.estado, COUNT(*) AS n, COUNT(DISTINCT r.fecha) AS dias
                  FROM asistencia_registros r
                  INNER JOIN asignaciones_academicas a ON a.id = r.asignacion_id
                 WHERE a.colegio_id = ? AND r.anio_lectivo_id = ? AND a.grupo_id = ?";
        $p = [$colegioId, $anioId, $grupoId];
        if ($desde !== null) { $sql .= ' AND r.fecha >= ?'; $p[] = $desde; }
        if ($hasta !== null) { $sql .= ' AND r.fecha <= ?'; $p[] = $hasta; }
        $q = $pdo->prepare($sql . ' GROUP BY r.estudiante_id, r.estado');
        $q->execute($p);
        foreach ($q->fetchAll() as $f) {
            $id = (int) $f['estudiante_id'];
            if (!isset($res[$id]) || !isset(self::ESTADOS[$f['estado']])) {
                continue;
            }
            $res[$id][$f['estado']] = (int) $f['n'];
            $res[$id]['total'] += (int) $f['n'];
            if ($f['estado'] === 'ausente') {
                $res[$id]['dias_ausente'] = (int) $f['dias'];
            }
        }
        foreach ($res as $id => $r) {
            $res[$id] = self::completarPorcentajes($r);
        }
        return $res;
    }

    /** Estudiantes del colegio con ≥ umbral días de ausencia sin justificar en la ventana (para el panel de coordinación). */
    public static function enRiesgo(PDO $pdo, int $colegioId, int $anioId, int $umbral = self::UMBRAL_DIAS, int $ventana = self::VENTANA_DIAS): array
    {
        $q = $pdo->prepare(
            "SELECT u.id, u.nombre, COUNT(DISTINCT r.fecha) AS dias, MAX(r.fecha) AS ultima,
                    (SELECT CONCAT(gr.nombre, ' ', g.nombre) FROM matriculas m
                       INNER JOIN grupos g ON g.id = m.grupo_id INNER JOIN grados gr ON gr.id = g.grado_id
                      WHERE m.estudiante_id = u.id AND m.anio_lectivo_id = r.anio_lectivo_id AND m.estado = 'activa' LIMIT 1) AS grupo
               FROM asistencia_registros r
               INNER JOIN usuarios u ON u.id = r.estudiante_id
              WHERE r.colegio_id = ? AND r.anio_lectivo_id = ? AND r.estado = 'ausente'
                AND r.fecha > DATE_SUB(CURDATE(), INTERVAL ? DAY) AND r.fecha <= CURDATE()
              GROUP BY u.id, u.nombre, r.anio_lectivo_id
             HAVING dias >= ?
              ORDER BY dias DESC, u.nombre"
        );
        $q->execute([$colegioId, $anioId, $ventana, $umbral]);
        return $q->fetchAll();
    }
}
