<?php
declare(strict_types=1);

/**
 * Horarios (migración 037).
 * Un bloque = una asignación académica (grupo + asignatura + docente) en un día y una franja.
 * Al guardar el horario de un grupo se revisan los cruces contra los OTROS grupos del mismo año:
 * mismo docente a la misma hora, mismo salón a la misma hora y clases en franjas de descanso.
 * Todo o nada: si hay un solo conflicto no se guarda nada.
 */
final class Horario
{
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
    public const DIAS_VISIBLES = 5;
    public const MAX_FRANJAS = 14;

    private static function hhmm(string $t): string
    {
        return substr($t, 0, 5);
    }

    private static function horaValida(string $h): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h);
    }

    // ------------------------------------------------------------------
    // Franjas
    // ------------------------------------------------------------------

    /** @return array<int, array{id:int, numero:int, nombre:string, inicio:string, fin:string, descanso:bool}> ordenadas */
    public static function franjas(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare('SELECT id, numero, nombre, hora_inicio, hora_fin, es_descanso FROM horario_franjas WHERE colegio_id = ? ORDER BY numero');
        $q->execute([$colegioId]);
        $res = [];
        foreach ($q->fetchAll() as $f) {
            $res[] = ['id' => (int) $f['id'], 'numero' => (int) $f['numero'], 'nombre' => (string) $f['nombre'],
                      'inicio' => self::hhmm((string) $f['hora_inicio']), 'fin' => self::hhmm((string) $f['hora_fin']),
                      'descanso' => (int) $f['es_descanso'] === 1];
        }
        return $res;
    }

    /** Propuesta para colegios que aún no configuran franjas (se muestra precargada en el formulario). */
    public static function franjasPorDefecto(): array
    {
        return [
            ['nombre' => '1.ª hora', 'inicio' => '07:00', 'fin' => '07:55', 'descanso' => false],
            ['nombre' => '2.ª hora', 'inicio' => '07:55', 'fin' => '08:50', 'descanso' => false],
            ['nombre' => '3.ª hora', 'inicio' => '08:50', 'fin' => '09:45', 'descanso' => false],
            ['nombre' => 'Descanso', 'inicio' => '09:45', 'fin' => '10:15', 'descanso' => true],
            ['nombre' => '4.ª hora', 'inicio' => '10:15', 'fin' => '11:10', 'descanso' => false],
            ['nombre' => '5.ª hora', 'inicio' => '11:10', 'fin' => '12:05', 'descanso' => false],
            ['nombre' => '6.ª hora', 'inicio' => '12:05', 'fin' => '13:00', 'descanso' => false],
        ];
    }

    /**
     * Reemplaza las franjas del colegio. Las filas vacías se ignoran; el número se asigna por orden.
     * Las franjas con clases asignadas no se pueden quitar. Todo o nada.
     * @param array $filas lista de ['nombre', 'inicio' (HH:MM), 'fin' (HH:MM), 'descanso' (bool)]
     * @return array{guardadas:int, errores:string[]}
     */
    public static function guardarFranjas(PDO $pdo, int $colegioId, array $filas): array
    {
        $res = ['guardadas' => 0, 'errores' => []];
        $limpias = [];
        foreach ($filas as $f) {
            $ini = trim((string) ($f['inicio'] ?? ''));
            $fin = trim((string) ($f['fin'] ?? ''));
            if ($ini === '' && $fin === '') {
                continue;
            }
            $n = count($limpias) + 1;
            $nombre = trim((string) ($f['nombre'] ?? ''));
            if ($nombre === '') {
                $nombre = $n . '.ª hora';
            }
            if (mb_strlen($nombre) > 30) {
                $res['errores'][] = "Franja $n: el nombre es demasiado largo (máximo 30 caracteres).";
            }
            if (!self::horaValida($ini) || !self::horaValida($fin)) {
                $res['errores'][] = "Franja $n: usa horas con formato HH:MM (por ejemplo 07:00).";
            } elseif ($ini >= $fin) {
                $res['errores'][] = "Franja $n: la hora de inicio debe ser menor que la de fin.";
            } elseif ($limpias && $ini < $limpias[count($limpias) - 1]['inicio_fin']) {
                $res['errores'][] = "Franja $n: empieza antes de que termine la anterior.";
            }
            $limpias[] = ['numero' => $n, 'nombre' => $nombre, 'inicio' => $ini, 'fin' => $fin, 'inicio_fin' => $fin, 'descanso' => !empty($f['descanso'])];
        }
        if (count($limpias) > self::MAX_FRANJAS) {
            $res['errores'][] = 'Máximo ' . self::MAX_FRANJAS . ' franjas por día.';
        }
        if (!$limpias) {
            $res['errores'][] = 'Define al menos una franja.';
        }
        if ($res['errores']) {
            return $res;
        }

        $existentes = [];
        foreach (self::franjas($pdo, $colegioId) as $f) {
            $existentes[$f['numero']] = $f;
        }
        $uso = $pdo->prepare('SELECT COUNT(*) FROM horario_bloques WHERE franja_id = ?');
        foreach ($existentes as $numero => $f) {
            if ($numero > count($limpias)) {
                $uso->execute([$f['id']]);
                if ((int) $uso->fetchColumn() > 0) {
                    $res['errores'][] = 'La franja «' . $f['nombre'] . '» (' . $f['inicio'] . '–' . $f['fin'] . ') tiene clases asignadas; libérala antes de quitarla.';
                }
            }
        }
        if ($res['errores']) {
            return $res;
        }

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare('UPDATE horario_franjas SET nombre = ?, hora_inicio = ?, hora_fin = ?, es_descanso = ? WHERE id = ?');
            $ins = $pdo->prepare('INSERT INTO horario_franjas (colegio_id, numero, nombre, hora_inicio, hora_fin, es_descanso) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($limpias as $f) {
                if (isset($existentes[$f['numero']])) {
                    $upd->execute([$f['nombre'], $f['inicio'] . ':00', $f['fin'] . ':00', $f['descanso'] ? 1 : 0, $existentes[$f['numero']]['id']]);
                } else {
                    $ins->execute([$colegioId, $f['numero'], $f['nombre'], $f['inicio'] . ':00', $f['fin'] . ':00', $f['descanso'] ? 1 : 0]);
                }
            }
            $del = $pdo->prepare('DELETE FROM horario_franjas WHERE colegio_id = ? AND numero > ?');
            $del->execute([$colegioId, count($limpias)]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Horario::guardarFranjas — ' . $e->getMessage());
            return ['guardadas' => 0, 'errores' => ['No se pudieron guardar las franjas. Inténtalo de nuevo.']];
        }
        $res['guardadas'] = count($limpias);
        return $res;
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /** Asignaciones (clases) de un grupo, indexadas por id. */
    public static function asignacionesDeGrupo(PDO $pdo, int $colegioId, int $anioId, int $grupoId): array
    {
        $q = $pdo->prepare(
            "SELECT a.id, a.docente_id, s.nombre AS asignatura, s.intensidad, u.nombre AS docente, CONCAT(gr.nombre, ' ', g.nombre) AS grupo
               FROM asignaciones_academicas a
               INNER JOIN asignaturas s ON s.id = a.asignatura_id
               INNER JOIN grupos g ON g.id = a.grupo_id
               INNER JOIN grados gr ON gr.id = g.grado_id
               LEFT JOIN usuarios u ON u.id = a.docente_id
              WHERE a.colegio_id = ? AND a.anio_lectivo_id = ? AND a.grupo_id = ?
              ORDER BY s.nombre"
        );
        $q->execute([$colegioId, $anioId, $grupoId]);
        $res = [];
        foreach ($q->fetchAll() as $r) {
            $res[(int) $r['id']] = $r;
        }
        return $res;
    }

    /** Horario de un grupo: [dia][franja_id] => ['asignacion_id', 'asignatura', 'docente', 'salon']. */
    public static function bloquesDeGrupo(PDO $pdo, int $colegioId, int $anioId, int $grupoId): array
    {
        $q = $pdo->prepare(
            "SELECT b.dia, b.franja_id, b.asignacion_id, b.salon, s.nombre AS asignatura, u.nombre AS docente
               FROM horario_bloques b
               INNER JOIN asignaciones_academicas a ON a.id = b.asignacion_id
               INNER JOIN asignaturas s ON s.id = a.asignatura_id
               LEFT JOIN usuarios u ON u.id = a.docente_id
              WHERE b.colegio_id = ? AND b.anio_lectivo_id = ? AND a.grupo_id = ?"
        );
        $q->execute([$colegioId, $anioId, $grupoId]);
        $res = [];
        foreach ($q->fetchAll() as $r) {
            $res[(int) $r['dia']][(int) $r['franja_id']] = ['asignacion_id' => (int) $r['asignacion_id'], 'asignatura' => (string) $r['asignatura'],
                                                           'docente' => $r['docente'], 'salon' => $r['salon']];
        }
        return $res;
    }

    /** Horario de un docente: [dia][franja_id] => ['grupo', 'asignatura', 'salon']. */
    public static function bloquesDeDocente(PDO $pdo, int $colegioId, int $anioId, int $docenteId): array
    {
        $q = $pdo->prepare(
            "SELECT b.dia, b.franja_id, b.salon, s.nombre AS asignatura, CONCAT(gr.nombre, ' ', g.nombre) AS grupo
               FROM horario_bloques b
               INNER JOIN asignaciones_academicas a ON a.id = b.asignacion_id
               INNER JOIN asignaturas s ON s.id = a.asignatura_id
               INNER JOIN grupos g ON g.id = a.grupo_id
               INNER JOIN grados gr ON gr.id = g.grado_id
              WHERE b.colegio_id = ? AND b.anio_lectivo_id = ? AND a.docente_id = ?"
        );
        $q->execute([$colegioId, $anioId, $docenteId]);
        $res = [];
        foreach ($q->fetchAll() as $r) {
            $res[(int) $r['dia']][(int) $r['franja_id']] = ['grupo' => (string) $r['grupo'], 'asignatura' => (string) $r['asignatura'], 'salon' => $r['salon']];
        }
        return $res;
    }

    /** Grupo del estudiante en el año (matrícula activa; si es el año actual y no hay matrícula, su grupo vigente). */
    public static function grupoDeEstudiante(PDO $pdo, int $estudianteId, array $anio): ?array
    {
        $q = $pdo->prepare(
            "SELECT g.id, CONCAT(gr.nombre, ' ', g.nombre) AS nombre FROM matriculas m
               INNER JOIN grupos g ON g.id = m.grupo_id INNER JOIN grados gr ON gr.id = g.grado_id
              WHERE m.estudiante_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'activa' LIMIT 1"
        );
        $q->execute([$estudianteId, (int) $anio['id']]);
        $r = $q->fetch();
        if (!$r && (int) ($anio['actual'] ?? 0) === 1) {
            $q = $pdo->prepare(
                "SELECT g.id, CONCAT(gr.nombre, ' ', g.nombre) AS nombre FROM usuarios u
                   INNER JOIN grupos g ON g.id = u.grupo_id INNER JOIN grados gr ON gr.id = g.grado_id
                  WHERE u.id = ? AND NOT EXISTS (SELECT 1 FROM matriculas m WHERE m.estudiante_id = u.id AND m.anio_lectivo_id = ?)"
            );
            $q->execute([$estudianteId, (int) $anio['id']]);
            $r = $q->fetch();
        }
        return $r ? ['id' => (int) $r['id'], 'nombre' => (string) $r['nombre']] : null;
    }

    /** Horas semanales asignadas vs. intensidad horaria de cada asignatura del grupo. */
    public static function horasPorAsignacion(PDO $pdo, int $colegioId, int $anioId, int $grupoId): array
    {
        $q = $pdo->prepare(
            "SELECT a.id, s.nombre AS asignatura, s.intensidad, COUNT(b.id) AS horas
               FROM asignaciones_academicas a
               INNER JOIN asignaturas s ON s.id = a.asignatura_id
               LEFT JOIN horario_bloques b ON b.asignacion_id = a.id
              WHERE a.colegio_id = ? AND a.anio_lectivo_id = ? AND a.grupo_id = ?
              GROUP BY a.id, s.nombre, s.intensidad
              ORDER BY s.nombre"
        );
        $q->execute([$colegioId, $anioId, $grupoId]);
        return $q->fetchAll();
    }

    // ------------------------------------------------------------------
    // Guardar el horario de un grupo (con detección de cruces)
    // ------------------------------------------------------------------

    /**
     * @param array $celdas [dia][franja_id] => ['asignacion_id' => int (0 = libre), 'salon' => string]
     * @return array{guardados:int, errores:string[]}
     */
    public static function guardarGrupo(PDO $pdo, int $colegioId, int $anioId, int $grupoId, array $celdas): array
    {
        $res = ['guardados' => 0, 'errores' => []];
        $asigs = self::asignacionesDeGrupo($pdo, $colegioId, $anioId, $grupoId);
        $franjas = [];
        foreach (self::franjas($pdo, $colegioId) as $f) {
            $franjas[$f['id']] = $f;
        }
        if (!$franjas) {
            return ['guardados' => 0, 'errores' => ['El colegio aún no tiene franjas horarias configuradas.']];
        }

        // 1) Validar y normalizar las celdas recibidas.
        $nuevos = [];
        foreach ($celdas as $dia => $porFranja) {
            $dia = (int) $dia;
            if (!isset(self::DIAS[$dia]) || !is_array($porFranja)) {
                continue;
            }
            foreach ($porFranja as $franjaId => $c) {
                $franjaId = (int) $franjaId;
                $asigId = (int) (is_array($c) ? ($c['asignacion_id'] ?? 0) : 0);
                if ($asigId === 0) {
                    continue; // celda libre
                }
                $etiqueta = self::DIAS[$dia] . ' · ' . ($franjas[$franjaId]['nombre'] ?? '?');
                if (!isset($franjas[$franjaId])) {
                    $res['errores'][] = "$etiqueta: la franja no existe.";
                    continue;
                }
                if ($franjas[$franjaId]['descanso']) {
                    $res['errores'][] = "$etiqueta: no se puede poner una clase en el descanso.";
                    continue;
                }
                if (!isset($asigs[$asigId])) {
                    $res['errores'][] = "$etiqueta: la clase elegida no pertenece a este grupo.";
                    continue;
                }
                $salon = trim((string) ($c['salon'] ?? ''));
                if (mb_strlen($salon) > 30) {
                    $res['errores'][] = "$etiqueta: el nombre del salón es demasiado largo (máximo 30 caracteres).";
                    continue;
                }
                $nuevos[] = ['dia' => $dia, 'franja_id' => $franjaId, 'asignacion_id' => $asigId, 'salon' => $salon === '' ? null : $salon,
                             'etiqueta' => $etiqueta, 'docente_id' => $asigs[$asigId]['docente_id'] !== null ? (int) $asigs[$asigId]['docente_id'] : null,
                             'asignatura' => $asigs[$asigId]['asignatura']];
            }
        }

        // 2) Cruces contra los OTROS grupos del mismo año.
        $q = $pdo->prepare(
            "SELECT b.dia, b.franja_id, b.salon, a.docente_id, u.nombre AS docente, s.nombre AS asignatura, CONCAT(gr.nombre, ' ', g.nombre) AS grupo
               FROM horario_bloques b
               INNER JOIN asignaciones_academicas a ON a.id = b.asignacion_id
               INNER JOIN asignaturas s ON s.id = a.asignatura_id
               INNER JOIN grupos g ON g.id = a.grupo_id
               INNER JOIN grados gr ON gr.id = g.grado_id
               LEFT JOIN usuarios u ON u.id = a.docente_id
              WHERE b.colegio_id = ? AND b.anio_lectivo_id = ? AND a.grupo_id <> ?"
        );
        $q->execute([$colegioId, $anioId, $grupoId]);
        $ocupado = [];
        foreach ($q->fetchAll() as $o) {
            $ocupado[$o['dia'] . '|' . $o['franja_id']][] = $o;
        }
        foreach ($nuevos as $n) {
            foreach ($ocupado[$n['dia'] . '|' . $n['franja_id']] ?? [] as $o) {
                if ($n['docente_id'] !== null && $o['docente_id'] !== null && (int) $o['docente_id'] === $n['docente_id']) {
                    $res['errores'][] = $n['etiqueta'] . ': ' . $o['docente'] . ' ya dicta ' . $o['asignatura'] . ' en ' . $o['grupo'] . ' a esa hora.';
                }
                if ($n['salon'] !== null && $o['salon'] !== null && mb_strtolower(trim((string) $o['salon'])) === mb_strtolower($n['salon'])) {
                    $res['errores'][] = $n['etiqueta'] . ': el salón «' . $n['salon'] . '» ya lo usa ' . $o['grupo'] . ' (' . $o['asignatura'] . ').';
                }
            }
        }
        if ($res['errores']) {
            return $res; // todo o nada
        }

        // 3) Reemplazar el horario del grupo.
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare(
                'DELETE b FROM horario_bloques b INNER JOIN asignaciones_academicas a ON a.id = b.asignacion_id
                  WHERE b.colegio_id = ? AND b.anio_lectivo_id = ? AND a.grupo_id = ?'
            );
            $del->execute([$colegioId, $anioId, $grupoId]);
            $ins = $pdo->prepare('INSERT INTO horario_bloques (colegio_id, anio_lectivo_id, asignacion_id, dia, franja_id, salon) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($nuevos as $n) {
                $ins->execute([$colegioId, $anioId, $n['asignacion_id'], $n['dia'], $n['franja_id'], $n['salon']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Horario::guardarGrupo — ' . $e->getMessage());
            return ['guardados' => 0, 'errores' => ['No se pudo guardar el horario. Inténtalo de nuevo.']];
        }
        $res['guardados'] = count($nuevos);
        return $res;
    }

    /**
     * Cruces que YA existen en el colegio (por ejemplo, tras cambiar el docente de una clase en Plan académico).
     * @return string[]
     */
    public static function conflictos(PDO $pdo, int $colegioId, int $anioId): array
    {
        $res = [];
        $q = $pdo->prepare(
            "SELECT b.dia, f.nombre AS franja, u.nombre AS docente,
                    GROUP_CONCAT(CONCAT(gr.nombre, ' ', g.nombre, ' ', s.nombre) ORDER BY gr.nombre, g.nombre SEPARATOR ' / ') AS clases
               FROM horario_bloques b
               INNER JOIN horario_franjas f ON f.id = b.franja_id
               INNER JOIN asignaciones_academicas a ON a.id = b.asignacion_id
               INNER JOIN asignaturas s ON s.id = a.asignatura_id
               INNER JOIN grupos g ON g.id = a.grupo_id
               INNER JOIN grados gr ON gr.id = g.grado_id
               INNER JOIN usuarios u ON u.id = a.docente_id
              WHERE b.colegio_id = ? AND b.anio_lectivo_id = ?
              GROUP BY b.dia, b.franja_id, f.numero, f.nombre, a.docente_id, u.nombre
             HAVING COUNT(*) > 1
              ORDER BY b.dia, f.numero"
        );
        $q->execute([$colegioId, $anioId]);
        foreach ($q->fetchAll() as $r) {
            $res[] = self::DIAS[(int) $r['dia']] . ' · ' . $r['franja'] . ': ' . $r['docente'] . ' tiene a la vez ' . $r['clases'] . '.';
        }
        $q = $pdo->prepare(
            "SELECT b.dia, f.nombre AS franja, MIN(b.salon) AS salon,
                    GROUP_CONCAT(CONCAT(gr.nombre, ' ', g.nombre, ' ', s.nombre) ORDER BY gr.nombre, g.nombre SEPARATOR ' / ') AS clases
               FROM horario_bloques b
               INNER JOIN horario_franjas f ON f.id = b.franja_id
               INNER JOIN asignaciones_academicas a ON a.id = b.asignacion_id
               INNER JOIN asignaturas s ON s.id = a.asignatura_id
               INNER JOIN grupos g ON g.id = a.grupo_id
               INNER JOIN grados gr ON gr.id = g.grado_id
              WHERE b.colegio_id = ? AND b.anio_lectivo_id = ? AND b.salon IS NOT NULL AND TRIM(b.salon) <> ''
              GROUP BY b.dia, b.franja_id, f.numero, f.nombre, LOWER(TRIM(b.salon))
             HAVING COUNT(DISTINCT a.grupo_id) > 1
              ORDER BY b.dia, f.numero"
        );
        $q->execute([$colegioId, $anioId]);
        foreach ($q->fetchAll() as $r) {
            $res[] = self::DIAS[(int) $r['dia']] . ' · ' . $r['franja'] . ': el salón «' . $r['salon'] . '» está asignado a la vez a ' . $r['clases'] . '.';
        }
        return $res;
    }
}
