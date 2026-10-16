<?php
declare(strict_types=1);

require_once __DIR__ . '/Simat.php';
require_once __DIR__ . '/Evaluacion.php';

/**
 * SIMPADE (Sistema de Información para el Monitoreo, Prevención y Análisis de la Deserción Escolar):
 * permanencia escolar. Serena ayuda al colegio a (1) detectar a tiempo a quien está en riesgo de abandonar,
 * (2) hacer y documentar el seguimiento, y (3) registrar la causa de cada retiro para reportarla.
 *
 * Reglas de oro:
 *  · El sistema SUGIERE, una persona DECIDE. Nada se marca como «en riesgo» sin que alguien lo confirme.
 *  · No reemplaza el criterio de orientación ni de coordinación; muestra por qué sugiere cada caso.
 *  · Serena no se conecta al SIMPADE (no hay API pública): deja los datos listos y exportables.
 *  · Los catálogos (factores, causas) son ORIENTATIVOS; ajústalos a la guía vigente de tu Secretaría de Educación.
 */
final class Simpade
{
    public const NIVELES = ['bajo' => 'Bajo', 'medio' => 'Medio', 'alto' => 'Alto'];
    public const ESTADOS = ['activo' => 'Activo', 'en_seguimiento' => 'En seguimiento', 'superado' => 'Superado', 'retirado' => 'Retirado'];
    public const TIPOS_SEGUIMIENTO = ['llamada' => 'Llamada', 'visita' => 'Visita domiciliaria', 'reunion' => 'Reunión con la familia', 'remision' => 'Remisión', 'compromiso' => 'Compromiso', 'otro' => 'Otra acción'];
    public const DESTINOS = ['otra_ie' => 'Otra institución educativa', 'otro_municipio' => 'Otro municipio', 'trabajo' => 'Trabajo', 'hogar' => 'Se quedó en casa', 'desconocido' => 'Se desconoce'];

    /** Umbrales del puntaje sugerido. */
    public const UMBRAL_ALTO = 6;
    public const UMBRAL_MEDIO = 3;
    /** Días sin seguimiento para considerar un caso desatendido. */
    public const DIAS_SIN_SEGUIMIENTO = 30;

    /** Factores de riesgo: clave => [categoría, etiqueta, se detecta solo]. */
    public const FACTORES = [
        'inasistencia'      => ['Individual', 'Inasistencia reiterada', true],
        'bajo_rendimiento'  => ['Académico', 'Bajo rendimiento (varias asignaturas perdidas)', true],
        'repitencia'        => ['Académico', 'Repitencia o año anterior reprobado', true],
        'extraedad'         => ['Académico', 'Extraedad (3 o más años sobre la edad del grado)', true],
        'convivencia'       => ['Institucional', 'Situaciones de convivencia (tipo II o III)', true],
        'cambios_ie'        => ['Institucional', 'Cambios frecuentes de institución', true],
        'trabajo'           => ['Socioeconómico', 'Trabaja actualmente', true],
        'victima'           => ['Familiar y contexto', 'Población víctima o vulnerable', true],
        'desplazamiento'    => ['Socioeconómico', 'Trayecto largo al colegio (60 min o más)', true],
        'salud'             => ['Individual', 'Condición de salud que afecta la asistencia', false],
        'embarazo'          => ['Individual', 'Embarazo o paternidad/maternidad adolescente', false],
        'consumo'           => ['Individual', 'Consumo de sustancias', false],
        'desmotivacion'     => ['Individual', 'Desmotivación o desinterés por el estudio', false],
        'conflicto_familiar' => ['Familiar y contexto', 'Conflicto o violencia intrafamiliar', false],
        'cuidador'          => ['Familiar y contexto', 'Asume cuidado de familiares', false],
        'cambio_domicilio'  => ['Familiar y contexto', 'Cambio de domicilio o migración', false],
        'economico'         => ['Socioeconómico', 'Dificultad económica de la familia', false],
        'entorno'           => ['Familiar y contexto', 'Inseguridad o violencia en el entorno', false],
        'relacion_escolar'  => ['Institucional', 'Dificultad en la relación con docentes o compañeros', false],
    ];

    /** Causas de retiro (orientativo, según las categorías habituales de deserción). */
    public const CAUSAS_RETIRO = [
        'traslado_ie' => 'Traslado a otra institución',
        'cambio_domicilio' => 'Cambio de residencia o domicilio',
        'cambio_pais' => 'Cambio de país',
        'trabajo' => 'Necesidad de trabajar',
        'embarazo' => 'Embarazo o paternidad/maternidad',
        'salud' => 'Enfermedad o condición de salud',
        'economico' => 'Dificultades económicas',
        'familiar' => 'Problemas o cambios familiares',
        'bajo_rendimiento' => 'Bajo rendimiento académico',
        'dificultad_academica' => 'Dificultades académicas',
        'poco_gusto' => 'Poco gusto por el estudio',
        'convivencia' => 'Convivencia o conflicto escolar',
        'inseguridad' => 'Inseguridad o violencia en el entorno',
        'desmotivacion' => 'Desmotivación o desinterés',
        'cuidado' => 'Cuidado de familiares',
        'fallecimiento' => 'Fallecimiento',
        'otra' => 'Otra causa',
        'desconocida' => 'Se desconoce',
    ];

    private const POR_PAGINA_SUGERENCIAS = 300;

    /** Menor valor que aprueba en la escala (si ningún nivel aprueba, el mínimo de la escala). */
    private static function minimoAprobatorio(array $escala): float
    {
        $min = null;
        foreach ((array) ($escala['niveles'] ?? []) as $n) {
            if ((int) ($n['aprueba'] ?? 0) === 1 && ($min === null || (float) $n['desde'] < $min)) {
                $min = (float) $n['desde'];
            }
        }
        return $min ?? (float) ($escala['valor_min'] ?? 0);
    }

    // ------------------------------------------------------------------ detección

    /**
     * Sugerencias automáticas para el año: estudiantes con matrícula activa cuyo puntaje de riesgo llega al umbral
     * y que todavía no tienen caso abierto. Cada una trae los factores con su detalle para que la persona lo verifique.
     */
    public static function sugerencias(PDO $pdo, int $colegioId, int $anioId): array
    {
        $anio = Academico::anio($pdo, $anioId, $colegioId);
        if ($anio === null) {
            return [];
        }
        $q = $pdo->prepare("SELECT m.estudiante_id, m.grado_id, u.nombre, u.fecha_nacimiento, gr.nombre AS grado, g.nombre AS grupo
                              FROM matriculas m
                              INNER JOIN usuarios u ON u.id = m.estudiante_id
                              INNER JOIN grados gr ON gr.id = m.grado_id
                              LEFT JOIN grupos g ON g.id = m.grupo_id
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'activa'");
        $q->execute([$colegioId, $anioId]);
        $alumnos = $q->fetchAll();
        if (!$alumnos) {
            return [];
        }
        $ids = array_map(static fn (array $a): int => (int) $a['estudiante_id'], $alumnos);
        $lista = implode(',', $ids);

        // Quienes ya tienen caso abierto este año no se vuelven a sugerir.
        $conCaso = [];
        if (Simat::tablaExiste($pdo, 'simpade_riesgos')) {
            $c = $pdo->prepare('SELECT estudiante_id FROM simpade_riesgos WHERE anio_lectivo_id = ?');
            $c->execute([$anioId]);
            $conCaso = array_flip(array_map('intval', $c->fetchAll(PDO::FETCH_COLUMN)));
        }

        // Cada señal es independiente: si una tabla falta o falla, esa señal queda vacía y el resto de la detección sigue funcionando.
        $hace30 = date('Y-m-d', strtotime('-30 days'));

        // Inasistencia: asistencia diaria por clase
        $asis = Simat::opcional(static function () use ($pdo, $colegioId, $anioId, $lista, $hace30): array {
            $o = [];
            $q = $pdo->prepare("SELECT a.estudiante_id, COUNT(*) AS total, SUM(a.estado IN ('ausente','excusa')) AS faltas,
                                       COUNT(DISTINCT CASE WHEN a.estado = 'ausente' AND a.fecha >= ? THEN a.fecha END) AS dias_recientes
                                  FROM asistencias a INNER JOIN asignaciones_academicas s ON s.id = a.asignacion_id
                                 WHERE s.colegio_id = ? AND s.anio_lectivo_id = ? AND a.estudiante_id IN ({$lista})
                                 GROUP BY a.estudiante_id");
            $q->execute([$hace30, $colegioId, $anioId]);
            foreach ($q as $r) {
                $o[(int) $r['estudiante_id']] = ['total' => (int) $r['total'], 'faltas' => (int) $r['faltas'], 'dias' => (int) $r['dias_recientes']];
            }
            return $o;
        });
        // Resumen por período (cuando el colegio no pasa lista diaria)
        $resumen = Simat::opcional(static function () use ($pdo, $colegioId, $anioId, $lista): array {
            $o = [];
            $q = $pdo->prepare("SELECT i.estudiante_id, SUM(i.ausencias) AS n
                                  FROM inasistencias_periodo i INNER JOIN asignaciones_academicas s ON s.id = i.asignacion_id
                                 WHERE s.colegio_id = ? AND s.anio_lectivo_id = ? AND i.estudiante_id IN ({$lista})
                                 GROUP BY i.estudiante_id");
            $q->execute([$colegioId, $anioId]);
            foreach ($q as $r) {
                $o[(int) $r['estudiante_id']] = (int) $r['n'];
            }
            return $o;
        });

        // Rendimiento: asignaturas por debajo del mínimo aprobatorio en el período más reciente con notas de cada estudiante
        $perdidas = Simat::opcional(function () use ($pdo, $colegioId, $anioId, $lista): array {
            $o = [];
            $escala = Evaluacion::escalaActiva($pdo, $colegioId);
            if ($escala === null) {
                return $o;
            }
            $minimo = self::minimoAprobatorio($escala);
            $q = $pdo->prepare("SELECT np.estudiante_id, np.periodo_id, p.numero, SUM(np.valor < ?) AS perdidas, COUNT(*) AS con_nota
                                  FROM notas_periodo np
                                  INNER JOIN periodos p ON p.id = np.periodo_id
                                  INNER JOIN asignaciones_academicas s ON s.id = np.asignacion_id
                                 WHERE s.colegio_id = ? AND s.anio_lectivo_id = ? AND np.valor IS NOT NULL AND np.estudiante_id IN ({$lista})
                                 GROUP BY np.estudiante_id, np.periodo_id, p.numero");
            $q->execute([$minimo, $colegioId, $anioId]);
            foreach ($q as $r) {
                $sid = (int) $r['estudiante_id'];
                if (!isset($o[$sid]) || (int) $r['numero'] > $o[$sid]['numero']) {
                    $o[$sid] = ['numero' => (int) $r['numero'], 'perdidas' => (int) $r['perdidas'], 'con_nota' => (int) $r['con_nota']];
                }
            }
            return $o;
        });

        // Hoja de vida (repitencia, trabajo, vulnerabilidad, trayecto)
        $hv = Simat::opcional(static function () use ($pdo, $lista): array {
            $o = [];
            $q = $pdo->query("SELECT estudiante_id, clave, valor FROM hoja_vida_datos WHERE estudiante_id IN ({$lista})
                                AND clave IN ('repitente','situacion_anterior','trabaja','poblacion_vulnerable','tiempo_llegar')");
            foreach ($q as $r) {
                $o[(int) $r['estudiante_id']][(string) $r['clave']] = (string) ($r['valor'] ?? '');
            }
            return $o;
        });
        $victimas = Simat::opcional(static function () use ($pdo, $lista): array {
            $o = [];
            foreach ($pdo->query("SELECT estudiante_id FROM caracterizacion_estudiante WHERE victima_conflicto = 1 AND estudiante_id IN ({$lista})") as $r) {
                $o[(int) $r['estudiante_id']] = true;
            }
            return $o;
        });
        // Convivencia: situaciones tipo II y III del año
        $conv = Simat::opcional(static function () use ($pdo, $colegioId, $anio, $lista): array {
            $o = [];
            $q = $pdo->prepare("SELECT estudiante_id, SUM(situacion_tipo IN ('II','III')) AS graves, COUNT(*) AS total
                                  FROM procesos_disciplinarios WHERE colegio_id = ? AND fecha >= ? AND estudiante_id IN ({$lista}) GROUP BY estudiante_id");
            $q->execute([$colegioId, ((int) $anio['anio']) . '-01-01']);
            foreach ($q as $r) {
                $o[(int) $r['estudiante_id']] = ['graves' => (int) $r['graves'], 'total' => (int) $r['total']];
            }
            return $o;
        });
        // Cambios de institución
        $cambios = Simat::opcional(static function () use ($pdo, $lista): array {
            $o = [];
            foreach ($pdo->query("SELECT estudiante_id, COUNT(*) AS n FROM establecimientos_previos WHERE estudiante_id IN ({$lista}) GROUP BY estudiante_id") as $r) {
                $o[(int) $r['estudiante_id']] = (int) $r['n'];
            }
            return $o;
        });

        $corte = ((int) $anio['anio']) . '-03-31';
        $codigosGrado = [];
        $salida = [];
        foreach ($alumnos as $al) {
            $sid = (int) $al['estudiante_id'];
            if (isset($conCaso[$sid])) {
                continue;
            }
            $h = $hv[$sid] ?? [];
            $factores = [];
            $puntos = 0;

            // 1. Inasistencia
            $a = $asis[$sid] ?? null;
            if ($a !== null && $a['total'] >= 10) {
                $pct = $a['faltas'] * 100 / $a['total'];
                $p = $pct >= 20 ? 4 : ($pct >= 10 ? 2 : 0);
                if ($a['dias'] >= 5) {
                    $p += 1;
                }
                if ($p > 0) {
                    $puntos += $p;
                    $factores['inasistencia'] = sprintf('%.0f %% de inasistencia (%d de %d clases)%s.', $pct, $a['faltas'], $a['total'],
                        $a['dias'] >= 5 ? ' y ' . $a['dias'] . ' días con falta sin justificar en el último mes' : '');
                }
            } elseif (($resumen[$sid] ?? 0) >= 20) {
                $puntos += 3;
                $factores['inasistencia'] = $resumen[$sid] . ' ausencias acumuladas en los resúmenes por período.';
            }

            // 2. Rendimiento
            $pf = $perdidas[$sid] ?? null;
            if ($pf !== null && $pf['perdidas'] >= 1) {
                $p = $pf['perdidas'] >= 3 ? 3 : 1;
                $puntos += $p;
                $factores['bajo_rendimiento'] = $pf['perdidas'] . ' de ' . $pf['con_nota'] . ' asignaturas por debajo del mínimo aprobatorio en el período ' . $pf['numero'] . '.';
            }

            // 3. Repitencia
            $rep = Simat::norm($h['repitente'] ?? '');
            $sitAnt = Simat::norm($h['situacion_anterior'] ?? '');
            if (in_array($rep, ['s', 'si'], true) || str_contains($sitAnt, 'reprob')) {
                $puntos += 2;
                $factores['repitencia'] = in_array($rep, ['s', 'si'], true) ? 'Registrado como repitente.' : 'Reprobó el año anterior.';
            }

            // 4. Extraedad
            $gid = (int) $al['grado_id'];
            if (!array_key_exists($gid, $codigosGrado)) {
                $codigosGrado[$gid] = Simat::codigoGradoColegio($pdo, $colegioId, $gid, (string) $al['grado']);
            }
            $codigo = $codigosGrado[$gid];
            if ($codigo !== null && !empty($al['fecha_nacimiento'])) {
                try {
                    $edad = (int) (new DateTimeImmutable((string) $al['fecha_nacimiento']))->diff(new DateTimeImmutable($corte))->y;
                    $esperada = (int) $codigo + 5;
                    if ($edad - $esperada >= 3) {
                        $puntos += 2;
                        $factores['extraedad'] = "Tiene {$edad} años y el grado corresponde a unos {$esperada}.";
                    }
                } catch (Throwable $e) {
                    // fecha ilegible: se ignora este factor
                }
            }

            // 5. Convivencia
            $cv = $conv[$sid] ?? null;
            if ($cv !== null && ($cv['graves'] >= 1 || $cv['total'] >= 3)) {
                $puntos += $cv['graves'] >= 1 ? 2 : 1;
                $factores['convivencia'] = $cv['graves'] . ' situación(es) tipo II/III y ' . $cv['total'] . ' en total este año.';
            }

            // 6. Cambios de institución
            if (($cambios[$sid] ?? 0) >= 3) {
                $puntos += 1;
                $factores['cambios_ie'] = 'Ha estudiado en ' . $cambios[$sid] . ' establecimientos antes.';
            }

            // 7. Trabajo
            if (Simat::norm($h['trabaja'] ?? '') === 's' || Simat::norm($h['trabaja'] ?? '') === 'si') {
                $puntos += 2;
                $factores['trabajo'] = 'Trabaja actualmente.';
            }

            // 8. Víctima o vulnerable
            $vul = Simat::norm($h['poblacion_vulnerable'] ?? '');
            if (isset($victimas[$sid]) || ($vul !== '' && $vul !== 'ninguna')) {
                $puntos += 1;
                $factores['victima'] = 'Registrado en población víctima o vulnerable.';
            }

            // 9. Trayecto
            if ((int) ($h['tiempo_llegar'] ?? 0) >= 60) {
                $puntos += 1;
                $factores['desplazamiento'] = 'Tarda ' . (int) $h['tiempo_llegar'] . ' minutos en llegar al colegio.';
            }

            if ($puntos >= self::UMBRAL_MEDIO) {
                $salida[] = ['estudiante_id' => $sid, 'nombre' => (string) $al['nombre'], 'grado' => (string) $al['grado'], 'grupo' => (string) ($al['grupo'] ?? ''),
                    'puntaje' => $puntos, 'nivel' => $puntos >= self::UMBRAL_ALTO ? 'alto' : 'medio', 'factores' => $factores];
            }
        }
        usort($salida, static fn (array $x, array $y): int => $y['puntaje'] <=> $x['puntaje'] ?: strcmp($x['nombre'], $y['nombre']));
        return array_slice($salida, 0, self::POR_PAGINA_SUGERENCIAS);
    }

    // ------------------------------------------------------------------ casos de riesgo

    private static function limpiarFactores(array $claves): string
    {
        $ok = [];
        foreach ($claves as $k) {
            if (isset(self::FACTORES[(string) $k]) && !in_array((string) $k, $ok, true)) {
                $ok[] = (string) $k;
            }
        }
        return implode(',', $ok);
    }

    /** Abre (o actualiza) el caso de riesgo de un estudiante en el año. Devuelve un error o ''. */
    public static function abrirCaso(PDO $pdo, int $colegioId, int $anioId, int $estudianteId, string $nivel, array $factores, string $observacion, ?int $responsableId, string $origen, int $puntaje, int $usuarioId): string
    {
        if (!Simat::tablaExiste($pdo, 'simpade_riesgos')) {
            return 'Falta ejecutar la migración 041_simat_simpade.sql.';
        }
        if (!isset(self::NIVELES[$nivel])) {
            return 'Elige el nivel de riesgo.';
        }
        $q = $pdo->prepare("SELECT 1 FROM matriculas WHERE estudiante_id = ? AND colegio_id = ? AND anio_lectivo_id = ? AND estado = 'activa'");
        $q->execute([$estudianteId, $colegioId, $anioId]);
        if (!$q->fetchColumn()) {
            return 'El estudiante no tiene matrícula activa en este año.';
        }
        if ($responsableId !== null && $responsableId > 0) {
            $r = $pdo->prepare('SELECT 1 FROM usuarios WHERE id = ? AND colegio_id = ? AND activo = 1');
            $r->execute([$responsableId, $colegioId]);
            if (!$r->fetchColumn()) {
                return 'La persona responsable no es válida.';
            }
        } else {
            $responsableId = null;
        }
        $claves = self::limpiarFactores($factores);
        $pdo->prepare("INSERT INTO simpade_riesgos (colegio_id, anio_lectivo_id, estudiante_id, nivel, estado, origen, puntaje, factores, observacion, responsable_id, fecha_deteccion, creado_por)
                       VALUES (?,?,?,?, 'activo', ?,?,?,?,?, CURDATE(), ?)
                       ON DUPLICATE KEY UPDATE cerrado_en = IF(estado = 'superado', NULL, cerrado_en), estado = IF(estado = 'superado', 'activo', estado),
                           nivel = VALUES(nivel), factores = VALUES(factores), observacion = VALUES(observacion), responsable_id = VALUES(responsable_id)")
            ->execute([$colegioId, $anioId, $estudianteId, $nivel, $origen === 'sugerido' ? 'sugerido' : 'manual', $puntaje, $claves !== '' ? $claves : null,
                mb_substr(trim($observacion), 0, 2000) ?: null, $responsableId, $usuarioId ?: null]);
        return '';
    }

    public static function casos(PDO $pdo, int $colegioId, int $anioId, string $estado = '', string $nivel = ''): array
    {
        if (!Simat::tablaExiste($pdo, 'simpade_riesgos')) {
            return [];
        }
        $sql = "SELECT r.*, u.nombre AS estudiante, gr.nombre AS grado, g.nombre AS grupo, resp.nombre AS responsable,
                       (SELECT MAX(s.fecha) FROM simpade_seguimientos s WHERE s.riesgo_id = r.id) AS ultimo_seguimiento,
                       (SELECT COUNT(*) FROM simpade_seguimientos s WHERE s.riesgo_id = r.id) AS n_seguimientos,
                       (SELECT s.proxima_fecha FROM simpade_seguimientos s WHERE s.riesgo_id = r.id ORDER BY s.fecha DESC, s.id DESC LIMIT 1) AS proxima
                  FROM simpade_riesgos r
                  INNER JOIN usuarios u ON u.id = r.estudiante_id
                  LEFT JOIN matriculas m ON m.estudiante_id = r.estudiante_id AND m.anio_lectivo_id = r.anio_lectivo_id AND m.colegio_id = r.colegio_id
                  LEFT JOIN grados gr ON gr.id = m.grado_id
                  LEFT JOIN grupos g ON g.id = m.grupo_id
                  LEFT JOIN usuarios resp ON resp.id = r.responsable_id
                 WHERE r.colegio_id = ? AND r.anio_lectivo_id = ?";
        $par = [$colegioId, $anioId];
        if (isset(self::ESTADOS[$estado])) {
            $sql .= ' AND r.estado = ?';
            $par[] = $estado;
        }
        if (isset(self::NIVELES[$nivel])) {
            $sql .= ' AND r.nivel = ?';
            $par[] = $nivel;
        }
        $q = $pdo->prepare($sql . " ORDER BY FIELD(r.estado, 'activo', 'en_seguimiento', 'superado', 'retirado'), FIELD(r.nivel, 'alto', 'medio', 'bajo'), u.nombre");
        $q->execute($par);
        return $q->fetchAll();
    }

    public static function caso(PDO $pdo, int $id, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT r.*, u.nombre AS estudiante FROM simpade_riesgos r INNER JOIN usuarios u ON u.id = r.estudiante_id WHERE r.id = ? AND r.colegio_id = ?');
        $q->execute([$id, $colegioId]);
        return $q->fetch() ?: null;
    }

    public static function seguimientos(PDO $pdo, int $riesgoId): array
    {
        $q = $pdo->prepare('SELECT s.*, u.nombre AS autor FROM simpade_seguimientos s LEFT JOIN usuarios u ON u.id = s.autor_id WHERE s.riesgo_id = ? ORDER BY s.fecha DESC, s.id DESC');
        $q->execute([$riesgoId]);
        return $q->fetchAll();
    }

    /** Agrega una acción de permanencia al caso. Devuelve un error o ''. El primer seguimiento pasa el caso a «en seguimiento». */
    public static function agregarSeguimiento(PDO $pdo, int $riesgoId, int $colegioId, string $tipo, string $fecha, string $nota, ?string $proxima, int $usuarioId): string
    {
        if (self::caso($pdo, $riesgoId, $colegioId) === null) {
            return 'El caso no existe.';
        }
        if (!isset(self::TIPOS_SEGUIMIENTO[$tipo])) {
            return 'Elige el tipo de acción.';
        }
        $nota = trim($nota);
        if ($nota === '') {
            return 'Escribe qué se hizo o qué se acordó.';
        }
        $f = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        if ($f === false || $f->format('Y-m-d') !== $fecha) {
            return 'La fecha no es válida.';
        }
        if ($proxima !== null && $proxima !== '') {
            $p = DateTimeImmutable::createFromFormat('Y-m-d', $proxima);
            if ($p === false || $p->format('Y-m-d') !== $proxima) {
                return 'La fecha del próximo contacto no es válida.';
            }
        } else {
            $proxima = null;
        }
        $pdo->prepare('INSERT INTO simpade_seguimientos (riesgo_id, autor_id, fecha, tipo, nota, proxima_fecha) VALUES (?,?,?,?,?,?)')
            ->execute([$riesgoId, $usuarioId ?: null, $fecha, $tipo, mb_substr($nota, 0, 3000), $proxima]);
        $pdo->prepare("UPDATE simpade_riesgos SET estado = 'en_seguimiento' WHERE id = ? AND colegio_id = ? AND estado = 'activo'")->execute([$riesgoId, $colegioId]);
        return '';
    }

    /** Cambia el estado/nivel/responsable de un caso. Devuelve un error o ''. */
    public static function actualizarCaso(PDO $pdo, int $id, int $colegioId, string $estado, string $nivel, ?int $responsableId): string
    {
        $c = self::caso($pdo, $id, $colegioId);
        if ($c === null) {
            return 'El caso no existe.';
        }
        if (!isset(self::ESTADOS[$estado]) || !isset(self::NIVELES[$nivel])) {
            return 'Estado o nivel no válido.';
        }
        if ($responsableId !== null && $responsableId > 0) {
            $r = $pdo->prepare('SELECT 1 FROM usuarios WHERE id = ? AND colegio_id = ? AND activo = 1');
            $r->execute([$responsableId, $colegioId]);
            if (!$r->fetchColumn()) {
                return 'La persona responsable no es válida.';
            }
        } else {
            $responsableId = null;
        }
        $cierra = in_array($estado, ['superado', 'retirado'], true);
        $pdo->prepare('UPDATE simpade_riesgos SET estado = ?, nivel = ?, responsable_id = ?, cerrado_en = ? WHERE id = ? AND colegio_id = ?')
            ->execute([$estado, $nivel, $responsableId, $cierra ? date('Y-m-d H:i:s') : null, $id, $colegioId]);
        return '';
    }

    // ------------------------------------------------------------------ retiros

    /**
     * Registra la causa de un retiro. Si $retirarMatricula, además pasa la matrícula a «retirada» (lo que deja la novedad
     * de retiro pendiente para el SIMAT) y cierra el caso de riesgo como «retirado». Devuelve un error o ''.
     */
    public static function registrarRetiro(PDO $pdo, int $colegioId, int $anioId, int $estudianteId, string $fecha, string $causa, string $detalle, string $destino, bool $contactada, string $estrategia, bool $retirarMatricula, int $usuarioId): string
    {
        if (!Simat::tablaExiste($pdo, 'simpade_retiros')) {
            return 'Falta ejecutar la migración 041_simat_simpade.sql.';
        }
        if (!isset(self::CAUSAS_RETIRO[$causa])) {
            return 'Elige la causa del retiro.';
        }
        if (!isset(self::DESTINOS[$destino])) {
            $destino = 'desconocido';
        }
        $f = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        if ($f === false || $f->format('Y-m-d') !== $fecha) {
            return 'La fecha no es válida.';
        }
        $q = $pdo->prepare('SELECT id, estado FROM matriculas WHERE estudiante_id = ? AND colegio_id = ? AND anio_lectivo_id = ?');
        $q->execute([$estudianteId, $colegioId, $anioId]);
        $m = $q->fetch();
        if (!$m) {
            return 'El estudiante no tiene matrícula en este año.';
        }
        // Primero la matrícula: si falla, no queda un retiro registrado a medias.
        if ($retirarMatricula && (string) $m['estado'] === 'activa') {
            $err = Academico::resolverMatricula($pdo, (int) $m['id'], $colegioId, 'retirada', null);
            if ($err !== '') {
                return $err;
            }
            // La novedad automática del SIMAT hereda la causa y el destino que se acaban de registrar.
            try {
                $pdo->prepare("UPDATE simat_novedades SET causa = ?, destino = ?, observacion = ?
                                WHERE matricula_id = ? AND colegio_id = ? AND tipo = 'retiro' AND estado = 'pendiente' AND origen = 'automatico'")
                    ->execute([mb_substr(self::CAUSAS_RETIRO[$causa], 0, 150), $destino !== 'desconocido' ? mb_substr(self::DESTINOS[$destino], 0, 150) : null,
                        mb_substr(trim($detalle), 0, 300) ?: null, (int) $m['id'], $colegioId]);
            } catch (Throwable $e) {
                error_log('Simpade::registrarRetiro (novedad SIMAT): ' . $e->getMessage());
            }
        }
        $pdo->prepare('INSERT INTO simpade_retiros (colegio_id, anio_lectivo_id, estudiante_id, matricula_id, fecha, causa, detalle, destino, familia_contactada, estrategia, creado_por)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE fecha = VALUES(fecha), causa = VALUES(causa), detalle = VALUES(detalle), destino = VALUES(destino),
                           familia_contactada = VALUES(familia_contactada), estrategia = VALUES(estrategia)')
            ->execute([$colegioId, $anioId, $estudianteId, (int) $m['id'], $fecha, $causa, mb_substr(trim($detalle), 0, 300) ?: null, $destino, $contactada ? 1 : 0,
                mb_substr(trim($estrategia), 0, 300) ?: null, $usuarioId ?: null]);
        $pdo->prepare("UPDATE simpade_riesgos SET estado = 'retirado', cerrado_en = NOW() WHERE colegio_id = ? AND anio_lectivo_id = ? AND estudiante_id = ?")
            ->execute([$colegioId, $anioId, $estudianteId]);
        return '';
    }

    public static function retiros(PDO $pdo, int $colegioId, int $anioId): array
    {
        if (!Simat::tablaExiste($pdo, 'simpade_retiros')) {
            return [];
        }
        $q = $pdo->prepare("SELECT t.*, u.nombre AS estudiante, gr.nombre AS grado, g.nombre AS grupo, hv.tipo_documento, hv.numero_documento
                              FROM simpade_retiros t
                              INNER JOIN usuarios u ON u.id = t.estudiante_id
                              LEFT JOIN matriculas m ON m.id = t.matricula_id
                              LEFT JOIN grados gr ON gr.id = m.grado_id
                              LEFT JOIN grupos g ON g.id = m.grupo_id
                              LEFT JOIN hojas_vida hv ON hv.estudiante_id = u.id
                             WHERE t.colegio_id = ? AND t.anio_lectivo_id = ? ORDER BY t.fecha DESC, t.id DESC");
        $q->execute([$colegioId, $anioId]);
        return $q->fetchAll();
    }

    public static function marcarRetiroReportado(PDO $pdo, int $id, int $colegioId, bool $reportado, int $usuarioId): void
    {
        $pdo->prepare('UPDATE simpade_retiros SET reportado = ?, reportado_en = ?, reportado_por = ? WHERE id = ? AND colegio_id = ?')
            ->execute([$reportado ? 1 : 0, $reportado ? date('Y-m-d H:i:s') : null, $reportado ? ($usuarioId ?: null) : null, $id, $colegioId]);
    }

    /** Estudiantes con matrícula activa (para elegir a quién abrir caso o registrar retiro). */
    public static function estudiantesActivos(PDO $pdo, int $colegioId, int $anioId): array
    {
        $q = $pdo->prepare("SELECT m.estudiante_id AS id, u.nombre, gr.nombre AS grado, g.nombre AS grupo
                              FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id
                              INNER JOIN grados gr ON gr.id = m.grado_id LEFT JOIN grupos g ON g.id = m.grupo_id
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'activa' ORDER BY gr.nombre, g.nombre, u.nombre");
        $q->execute([$colegioId, $anioId]);
        return $q->fetchAll();
    }

    /** Personas del colegio que pueden hacerse cargo de un caso. */
    public static function posiblesResponsables(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare("SELECT u.id, u.nombre, r.nombre AS rol FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                             WHERE u.colegio_id = ? AND u.activo = 1 AND r.nombre IN ('psicologo', 'orientador', 'coordinador', 'rector', 'docente') ORDER BY r.nombre, u.nombre");
        $q->execute([$colegioId]);
        return $q->fetchAll();
    }

    // ------------------------------------------------------------------ indicadores

    public static function indicadores(PDO $pdo, int $colegioId, int $anioId): array
    {
        $r = ['matriculados' => 0, 'retirados' => 0, 'tasa_retiro' => 0.0, 'casos' => ['activo' => 0, 'en_seguimiento' => 0, 'superado' => 0, 'retirado' => 0],
            'niveles' => ['alto' => 0, 'medio' => 0, 'bajo' => 0], 'sin_seguimiento' => 0, 'vencidos' => 0, 'por_causa' => [], 'sin_reportar' => 0, 'efectividad' => null];
        $q = $pdo->prepare("SELECT SUM(estado IN ('activa','retirada')) AS total, SUM(estado = 'retirada') AS retiradas FROM matriculas WHERE colegio_id = ? AND anio_lectivo_id = ?");
        $q->execute([$colegioId, $anioId]);
        $m = $q->fetch() ?: [];
        $r['matriculados'] = (int) ($m['total'] ?? 0);
        $r['retirados'] = (int) ($m['retiradas'] ?? 0);
        $r['tasa_retiro'] = $r['matriculados'] > 0 ? round($r['retirados'] * 100 / $r['matriculados'], 1) : 0.0;
        if (Simat::tablaExiste($pdo, 'simpade_riesgos')) {
            $q = $pdo->prepare('SELECT estado, nivel, COUNT(*) AS n FROM simpade_riesgos WHERE colegio_id = ? AND anio_lectivo_id = ? GROUP BY estado, nivel');
            $q->execute([$colegioId, $anioId]);
            foreach ($q as $f) {
                $r['casos'][$f['estado']] = ($r['casos'][$f['estado']] ?? 0) + (int) $f['n'];
                if (in_array($f['estado'], ['activo', 'en_seguimiento'], true)) {
                    $r['niveles'][$f['nivel']] = ($r['niveles'][$f['nivel']] ?? 0) + (int) $f['n'];
                }
            }
            $q = $pdo->prepare("SELECT COUNT(*) FROM simpade_riesgos r
                                 WHERE r.colegio_id = ? AND r.anio_lectivo_id = ? AND r.estado IN ('activo', 'en_seguimiento')
                                   AND COALESCE((SELECT MAX(s.fecha) FROM simpade_seguimientos s WHERE s.riesgo_id = r.id), DATE(r.creado_en)) < DATE_SUB(CURDATE(), INTERVAL " . self::DIAS_SIN_SEGUIMIENTO . ' DAY)');
            $q->execute([$colegioId, $anioId]);
            $r['sin_seguimiento'] = (int) $q->fetchColumn();
            $q = $pdo->prepare("SELECT COUNT(*) FROM simpade_riesgos r
                                 WHERE r.colegio_id = ? AND r.anio_lectivo_id = ? AND r.estado IN ('activo', 'en_seguimiento')
                                   AND (SELECT s.proxima_fecha FROM simpade_seguimientos s WHERE s.riesgo_id = r.id ORDER BY s.fecha DESC, s.id DESC LIMIT 1) < CURDATE()");
            $q->execute([$colegioId, $anioId]);
            $r['vencidos'] = (int) $q->fetchColumn();
            $cerrados = $r['casos']['superado'] + $r['casos']['retirado'];
            $r['efectividad'] = $cerrados > 0 ? round($r['casos']['superado'] * 100 / $cerrados, 0) : null;
        }
        if (Simat::tablaExiste($pdo, 'simpade_retiros')) {
            $q = $pdo->prepare('SELECT causa, COUNT(*) AS n FROM simpade_retiros WHERE colegio_id = ? AND anio_lectivo_id = ? GROUP BY causa ORDER BY n DESC');
            $q->execute([$colegioId, $anioId]);
            $r['por_causa'] = $q->fetchAll();
            $q = $pdo->prepare('SELECT COUNT(*) FROM simpade_retiros WHERE colegio_id = ? AND anio_lectivo_id = ? AND reportado = 0');
            $q->execute([$colegioId, $anioId]);
            $r['sin_reportar'] = (int) $q->fetchColumn();
        }
        return $r;
    }

    // ------------------------------------------------------------------ exportación

    private static function seguro(string $v): string
    {
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'" . $v : $v;
    }

    /** CSV con los casos de riesgo del año. */
    public static function escribirCsvCasos($salida, array $casos, string $sep): void
    {
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['ESTUDIANTE', 'GRADO', 'GRUPO', 'NIVEL', 'ESTADO', 'PUNTAJE', 'FACTORES', 'RESPONSABLE', 'FECHA_DETECCION', 'ULTIMO_SEGUIMIENTO', 'N_SEGUIMIENTOS'], $sep, '"', '\\');
        foreach ($casos as $c) {
            $fact = [];
            foreach (array_filter(explode(',', (string) $c['factores'])) as $k) {
                $fact[] = self::FACTORES[$k][1] ?? $k;
            }
            fputcsv($salida, array_map([self::class, 'seguro'], [(string) $c['estudiante'], (string) $c['grado'], (string) $c['grupo'], self::NIVELES[$c['nivel']] ?? $c['nivel'],
                self::ESTADOS[$c['estado']] ?? $c['estado'], (string) $c['puntaje'], implode('; ', $fact), (string) ($c['responsable'] ?? ''), (string) $c['fecha_deteccion'],
                (string) ($c['ultimo_seguimiento'] ?? ''), (string) $c['n_seguimientos']]), $sep, '"', '\\');
        }
    }

    /** CSV con los retiros del año y su causa. */
    public static function escribirCsvRetiros($salida, array $retiros, string $sep): void
    {
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['TIPO_DOCUMENTO', 'NUMERO_DOCUMENTO', 'ESTUDIANTE', 'GRADO', 'GRUPO', 'FECHA_RETIRO', 'CAUSA', 'DETALLE', 'DESTINO', 'FAMILIA_CONTACTADA', 'ESTRATEGIA_PERMANENCIA', 'REPORTADO'], $sep, '"', '\\');
        foreach ($retiros as $t) {
            fputcsv($salida, array_map([self::class, 'seguro'], [(string) ($t['tipo_documento'] ?? ''), (string) ($t['numero_documento'] ?? ''), (string) $t['estudiante'], (string) ($t['grado'] ?? ''),
                (string) ($t['grupo'] ?? ''), (string) $t['fecha'], self::CAUSAS_RETIRO[$t['causa']] ?? $t['causa'], (string) ($t['detalle'] ?? ''), self::DESTINOS[$t['destino']] ?? $t['destino'],
                (int) $t['familia_contactada'] === 1 ? 'S' : 'N', (string) ($t['estrategia'] ?? ''), (int) $t['reportado'] === 1 ? 'S' : 'N']), $sep, '"', '\\');
        }
    }
}
