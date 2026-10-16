<?php
declare(strict_types=1);

require_once __DIR__ . '/Evaluacion.php';

/**
 * Expediente del estudiante y gestión docente: hoja de vida, observador, convivencia, horas formativas,
 * aula de apoyo, asistencia, planeador y diario de campo. Requiere la migración 036_expediente.sql.
 *
 * DATOS SENSIBLES DE MENORES: el acceso se decide aquí, por rol, en dos pasos que SIEMPRE deben ir juntos:
 *   1) Expediente::puedeAccederEstudiante()  → ¿puede esta persona abrir a ESTE estudiante?
 *   2) Expediente::capacidades($rol)         → ¿qué secciones puede ver o escribir?
 */
class Expediente
{
    public const TIPOS_OBSERVADOR = [
        'anecdota' => 'Anecdotario (hecho relevante)', 'academica' => 'Académica',
        'convivencia' => 'Convivencia', 'seguimiento' => 'Seguimiento (reservado)',
    ];
    public const SITUACIONES = [
        'I' => 'Tipo I · conflictos manejables y faltas leves',
        'II' => 'Tipo II · agresión, acoso o ciberacoso sin delito',
        'III' => 'Tipo III · presuntos delitos',
    ];
    public const ESTADOS_PROCESO = ['reportado' => 'Reportado por docente', 'abierto' => 'Abierto', 'seguimiento' => 'En seguimiento', 'cerrado' => 'Cerrado'];
    public const TIPOS_ACTUACION = [
        'citacion_acudiente' => 'Citación al acudiente', 'descargos' => 'Descargos del estudiante', 'medida_pedagogica' => 'Medida pedagógica',
        'remision_orientacion' => 'Remisión a orientación', 'comite_convivencia' => 'Comité de convivencia', 'compromiso' => 'Compromiso firmado', 'otra' => 'Otra actuación',
    ];
    /** tipo => [nombre, horas obligatorias]. Servicio social: Res. 4210/1996 (80 h). Constitución Política: Ley 107/1994 (50 h). */
    public const HORAS = ['servicio_social' => ['Servicio social estudiantil', 80], 'constitucion' => ['Constitución Política', 50]];
    public const ESTADOS_ASISTENCIA = ['presente' => 'Presente', 'ausente' => 'Ausente', 'tarde' => 'Tarde', 'excusa' => 'Excusa'];
    public const ESTADOS_PLANEACION = ['borrador' => 'Borrador', 'listo' => 'Listo', 'ejecutado' => 'Ejecutado'];
    public const GRUPOS_SANGUINEOS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    // ------------------------------------------------------------------
    // Permisos
    // ------------------------------------------------------------------

    /** Qué puede hacer cada rol. Cualquier rol no listado no tiene ninguna capacidad. */
    public static function capacidades(string $rol): array
    {
        $directivos = in_array($rol, ['coordinador', 'rector', 'administrador'], true);
        $orient = in_array($rol, ['psicologo', 'orientador'], true);
        $docente = $rol === 'docente';
        $sec = $rol === 'secretaria_academica';
        $plat = in_array($rol, ['secretaria', 'superadmin'], true); // solo lectura de la hoja de vida
        return [
            'personal' => $directivos || $orient || $docente || $sec || $plat,
            'editar_hoja' => $directivos || $orient || $sec,
            'salud_sensible' => $directivos || $orient,           // condiciones de salud, medicamentos y desarrollo
            'ver_observador' => $directivos || $orient || $docente,
            'ver_obs_reservado' => $directivos || $orient,        // tipo «seguimiento»
            'escribir_observador' => $directivos || $orient || $docente,
            'ver_obs_familia' => $rol === 'acudiente',
            'ver_convivencia' => $directivos || $orient,
            'gestionar_convivencia' => $directivos,
            'reportar_convivencia' => $docente,
            'escribir_horas' => $directivos || $sec || $docente,
            'ver_apoyo_completo' => $directivos || $orient,
            'ver_apoyo_ajustes' => $docente,
            'escribir_apoyo' => $directivos || $orient,
            'ver_asistencia' => true,
            'ver_horas' => true,
        ];
    }

    /** ¿Esta persona puede abrir a ESTE estudiante? (siempre dentro de su colegio) */
    public static function puedeAccederEstudiante(PDO $pdo, string $rol, int $usuarioId, int $estudianteId, int $colegioId): bool
    {
        if ($rol === 'estudiante') {
            return $usuarioId === $estudianteId;
        }
        if ($rol === 'acudiente') {
            $q = $pdo->prepare('SELECT 1 FROM acudientes_estudiantes WHERE acudiente_id = ? AND estudiante_id = ?');
            $q->execute([$usuarioId, $estudianteId]);
            return (bool) $q->fetchColumn();
        }
        if (!self::capacidades($rol)['personal']) {
            return false;
        }
        $base = "SELECT 1 FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
                  WHERE u.id = ? AND u.colegio_id = ? AND u.activo = 1";
        if ($rol !== 'docente') {
            $q = $pdo->prepare($base);
            $q->execute([$estudianteId, $colegioId]);
            return (bool) $q->fetchColumn();
        }
        // Docente: solo estudiantes de los grupos donde da clase (año actual) o de los que dirige
        $q = $pdo->prepare($base . ' AND u.grupo_id IS NOT NULL AND (
                EXISTS (SELECT 1 FROM asignaciones_academicas a INNER JOIN anios_lectivos y ON y.id = a.anio_lectivo_id AND y.actual = 1
                         WHERE a.grupo_id = u.grupo_id AND a.docente_id = ?)
                OR EXISTS (SELECT 1 FROM grupos g WHERE g.id = u.grupo_id AND g.docente_id = ?))');
        $q->execute([$estudianteId, $colegioId, $usuarioId, $usuarioId]);
        return (bool) $q->fetchColumn();
    }

    /** Directorio de estudiantes que esta persona puede abrir. */
    public static function estudiantes(PDO $pdo, string $rol, int $usuarioId, int $colegioId, int $grupoId, string $busqueda): array
    {
        $sql = "SELECT u.id, u.nombre, u.correo, g.id AS grupo_id, g.nombre AS grupo, gr.nombre AS grado
                  FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
                  LEFT JOIN grupos g ON g.id = u.grupo_id LEFT JOIN grados gr ON gr.id = g.grado_id
                 WHERE u.colegio_id = ? AND u.activo = 1";
        $p = [$colegioId];
        if ($rol === 'docente') {
            $sql .= ' AND u.grupo_id IS NOT NULL AND (
                EXISTS (SELECT 1 FROM asignaciones_academicas a INNER JOIN anios_lectivos y ON y.id = a.anio_lectivo_id AND y.actual = 1
                         WHERE a.grupo_id = u.grupo_id AND a.docente_id = ?)
                OR EXISTS (SELECT 1 FROM grupos gg WHERE gg.id = u.grupo_id AND gg.docente_id = ?))';
            array_push($p, $usuarioId, $usuarioId);
        }
        if ($grupoId > 0) {
            $sql .= ' AND u.grupo_id = ?';
            $p[] = $grupoId;
        }
        if (trim($busqueda) !== '') {
            $sql .= ' AND (u.nombre LIKE ? OR u.correo LIKE ?)';
            $p[] = '%' . trim($busqueda) . '%';
            $p[] = '%' . trim($busqueda) . '%';
        }
        $q = $pdo->prepare($sql . ' ORDER BY gr.nombre, g.nombre, u.nombre LIMIT 400');
        $q->execute($p);
        return $q->fetchAll();
    }

    public static function estudianteBasico(PDO $pdo, int $estudianteId, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT u.id, u.nombre, u.correo, u.fecha_nacimiento, u.telefono, g.nombre AS grupo, gr.nombre AS grado
                              FROM usuarios u LEFT JOIN grupos g ON g.id = u.grupo_id LEFT JOIN grados gr ON gr.id = g.grado_id
                             WHERE u.id = ? AND u.colegio_id = ?');
        $q->execute([$estudianteId, $colegioId]);
        return $q->fetch() ?: null;
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    private static function txt(mixed $v, int $max): ?string
    {
        $t = mb_substr(trim((string) $v), 0, $max);
        return $t === '' ? null : $t;
    }

    private static function fechaOk(string $f): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $f);
        return $d !== false && $d->format('Y-m-d') === $f;
    }

    // ------------------------------------------------------------------
    // Hoja de vida y contactos
    // ------------------------------------------------------------------

    public static function hojaVida(PDO $pdo, int $estudianteId): ?array
    {
        $q = $pdo->prepare('SELECT * FROM hojas_vida WHERE estudiante_id = ?');
        $q->execute([$estudianteId]);
        return $q->fetch() ?: null;
    }

    /** Guarda la hoja de vida. Los campos de salud sensible y desarrollo solo se tocan si $sensible es true. */
    public static function guardarHojaVida(PDO $pdo, int $colegioId, int $estudianteId, array $d, bool $sensible, int $porId): string
    {
        $tipoDoc = (string) ($d['tipo_documento'] ?? '');
        if ($tipoDoc !== '' && !isset(Academico::TIPOS_DOCUMENTO[$tipoDoc])) {
            return 'Tipo de documento no válido.';
        }
        $rh = (string) ($d['grupo_sanguineo'] ?? '');
        if ($rh !== '' && !in_array($rh, self::GRUPOS_SANGUINEOS, true)) {
            return 'Grupo sanguíneo no válido.';
        }
        $vals = [
            'tipo_documento' => $tipoDoc !== '' ? $tipoDoc : null,
            'numero_documento' => self::txt(preg_replace('/[^A-Za-z0-9]/', '', (string) ($d['numero_documento'] ?? '')), 20),
            'genero' => self::txt($d['genero'] ?? '', 20),
            'lugar_nacimiento' => self::txt($d['lugar_nacimiento'] ?? '', 100),
            'direccion' => self::txt($d['direccion'] ?? '', 200),
            'barrio' => self::txt($d['barrio'] ?? '', 80),
            'telefono_alterno' => self::txt(preg_replace('/[^0-9+]/', '', (string) ($d['telefono_alterno'] ?? '')), 20),
            'vive_con' => self::txt($d['vive_con'] ?? '', 120),
            'eps' => self::txt($d['eps'] ?? '', 80),
            'grupo_sanguineo' => $rh !== '' ? $rh : null,
            'alergias' => self::txt($d['alergias'] ?? '', 255),
        ];
        if ($sensible) {
            $vals['condiciones_salud'] = self::txt($d['condiciones_salud'] ?? '', 500);
            $vals['medicamentos'] = self::txt($d['medicamentos'] ?? '', 255);
            $vals['desarrollo'] = self::txt($d['desarrollo'] ?? '', 5000);
        }
        $cols = array_keys($vals);
        $sql = 'INSERT INTO hojas_vida (estudiante_id, colegio_id, actualizado_por, ' . implode(', ', $cols) . ') VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')
                ON DUPLICATE KEY UPDATE actualizado_por = VALUES(actualizado_por), ' . implode(', ', array_map(static fn (string $c): string => "{$c} = VALUES({$c})", $cols));
        $pdo->prepare($sql)->execute(array_merge([$estudianteId, $colegioId, $porId], array_values($vals)));
        // El documento de la hoja de vida también sirve para iniciar sesión (si nadie más lo tiene en este colegio).
        require_once __DIR__ . '/Usuario.php';
        $doc = Usuario::normalizarDocumento((string) ($vals['numero_documento'] ?? ''));
        if (Usuario::documentoValido($doc) && !Usuario::documentoEnUso($pdo, $doc, $colegioId, $estudianteId)) {
            Usuario::guardarDocumento($pdo, $estudianteId, $doc);
        }
        return '';
    }

    public static function contactos(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare('SELECT * FROM contactos_emergencia WHERE estudiante_id = ? ORDER BY id');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    public static function agregarContacto(PDO $pdo, int $estudianteId, string $nombre, string $parentesco, string $telefono, bool $recoger): string
    {
        $nombre = self::txt($nombre, 120);
        $parentesco = self::txt($parentesco, 40);
        $tel = preg_replace('/[^0-9+]/', '', $telefono) ?? '';
        if ($nombre === null || $parentesco === null || strlen(preg_replace('/\D/', '', $tel) ?? '') < 7) {
            return 'Escribe nombre, parentesco y un teléfono válido del contacto.';
        }
        $pdo->prepare('INSERT INTO contactos_emergencia (estudiante_id, nombre, parentesco, telefono, autorizado_recoger) VALUES (?,?,?,?,?)')
            ->execute([$estudianteId, $nombre, $parentesco, mb_substr($tel, 0, 20), $recoger ? 1 : 0]);
        return '';
    }

    public static function eliminarContacto(PDO $pdo, int $id, int $estudianteId): void
    {
        $pdo->prepare('DELETE FROM contactos_emergencia WHERE id = ? AND estudiante_id = ?')->execute([$id, $estudianteId]);
    }

    // ------------------------------------------------------------------
    // Observador y anecdotario
    // ------------------------------------------------------------------

    public static function observaciones(PDO $pdo, int $estudianteId, bool $verReservado, bool $soloFamilia): array
    {
        $sql = 'SELECT o.*, u.nombre AS autor FROM observador o LEFT JOIN usuarios u ON u.id = o.autor_id WHERE o.estudiante_id = ?';
        if (!$verReservado) {
            $sql .= " AND o.tipo <> 'seguimiento'";
        }
        if ($soloFamilia) {
            $sql .= ' AND o.visible_familia = 1';
        }
        $q = $pdo->prepare($sql . ' ORDER BY o.fecha DESC, o.id DESC LIMIT 300');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    public static function agregarObservacion(PDO $pdo, int $colegioId, int $estudianteId, int $autorId, string $fecha, string $tipo, string $descripcion, string $compromiso, bool $visibleFamilia, bool $puedeReservado): string
    {
        if (!isset(self::TIPOS_OBSERVADOR[$tipo])) {
            return 'Tipo de anotación no válido.';
        }
        if ($tipo === 'seguimiento' && !$puedeReservado) {
            return 'Las anotaciones de seguimiento son reservadas a coordinación y orientación.';
        }
        if (!self::fechaOk($fecha) || $fecha > date('Y-m-d')) {
            return 'La fecha no es válida (no puede ser futura).';
        }
        $desc = self::txt($descripcion, 5000);
        if ($desc === null) {
            return 'Describe lo ocurrido.';
        }
        $pdo->prepare('INSERT INTO observador (colegio_id, estudiante_id, autor_id, fecha, tipo, descripcion, compromiso, visible_familia) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, $estudianteId, $autorId, $fecha, $tipo, $desc, self::txt($compromiso, 500), ($tipo === 'seguimiento' ? false : $visibleFamilia) ? 1 : 0]);
        return '';
    }

    public static function eliminarObservacion(PDO $pdo, int $id, int $estudianteId, int $colegioId): void
    {
        $pdo->prepare('DELETE FROM observador WHERE id = ? AND estudiante_id = ? AND colegio_id = ?')->execute([$id, $estudianteId, $colegioId]);
    }

    // ------------------------------------------------------------------
    // Convivencia (procesos disciplinarios)
    // ------------------------------------------------------------------

    public static function procesos(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare('SELECT p.*, u.nombre AS abierto_por_nombre FROM procesos_disciplinarios p LEFT JOIN usuarios u ON u.id = p.abierto_por
                             WHERE p.estudiante_id = ? ORDER BY p.fecha DESC, p.id DESC');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    public static function proceso(PDO $pdo, int $id, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT * FROM procesos_disciplinarios WHERE id = ? AND colegio_id = ?');
        $q->execute([$id, $colegioId]);
        return $q->fetch() ?: null;
    }

    public static function actuaciones(PDO $pdo, int $procesoId): array
    {
        $q = $pdo->prepare('SELECT a.*, u.nombre AS autor FROM actuaciones_disciplinarias a LEFT JOIN usuarios u ON u.id = a.autor_id WHERE a.proceso_id = ? ORDER BY a.fecha, a.id');
        $q->execute([$procesoId]);
        return $q->fetchAll();
    }

    /** Abre un proceso (estado «abierto») o, si lo envía un docente, un reporte (estado «reportado»). */
    public static function abrirProceso(PDO $pdo, int $colegioId, int $estudianteId, int $porId, string $fecha, string $situacion, string $descripcion, bool $comoReporte): string
    {
        if (!isset(self::SITUACIONES[$situacion])) {
            return 'Elige el tipo de situación.';
        }
        if (!self::fechaOk($fecha) || $fecha > date('Y-m-d')) {
            return 'La fecha no es válida (no puede ser futura).';
        }
        $desc = self::txt($descripcion, 5000);
        if ($desc === null) {
            return 'Describe la situación.';
        }
        $pdo->prepare('INSERT INTO procesos_disciplinarios (colegio_id, estudiante_id, abierto_por, fecha, situacion_tipo, descripcion, estado) VALUES (?,?,?,?,?,?,?)')
            ->execute([$colegioId, $estudianteId, $porId, $fecha, $situacion, $desc, $comoReporte ? 'reportado' : 'abierto']);
        return '';
    }

    public static function agregarActuacion(PDO $pdo, int $procesoId, int $autorId, string $fecha, string $tipo, string $detalle): string
    {
        if (!isset(self::TIPOS_ACTUACION[$tipo])) {
            return 'Tipo de actuación no válido.';
        }
        if (!self::fechaOk($fecha)) {
            return 'La fecha no es válida.';
        }
        $det = self::txt($detalle, 5000);
        if ($det === null) {
            return 'Describe la actuación.';
        }
        $pdo->prepare('INSERT INTO actuaciones_disciplinarias (proceso_id, autor_id, fecha, tipo, detalle) VALUES (?,?,?,?,?)')->execute([$procesoId, $autorId, $fecha, $tipo, $det]);
        return '';
    }

    public static function cambiarEstadoProceso(PDO $pdo, int $id, int $colegioId, string $estado, string $decision, int $porId): string
    {
        if (!isset(self::ESTADOS_PROCESO[$estado]) || $estado === 'reportado') {
            return 'Estado no válido.';
        }
        $dec = self::txt($decision, 5000);
        if ($estado === 'cerrado' && $dec === null) {
            return 'Para cerrar el proceso escribe la decisión final.';
        }
        $pdo->prepare('UPDATE procesos_disciplinarios SET estado = ?, decision = COALESCE(?, decision), cerrado_en = ?, cerrado_por = ? WHERE id = ? AND colegio_id = ?')
            ->execute([$estado, $dec, $estado === 'cerrado' ? date('Y-m-d H:i:s') : null, $estado === 'cerrado' ? $porId : null, $id, $colegioId]);
        return '';
    }

    public static function listarProcesos(PDO $pdo, int $colegioId, string $estado, string $situacion): array
    {
        $sql = 'SELECT p.*, u.nombre AS estudiante, g.nombre AS grupo, gr.nombre AS grado
                  FROM procesos_disciplinarios p INNER JOIN usuarios u ON u.id = p.estudiante_id
                  LEFT JOIN grupos g ON g.id = u.grupo_id LEFT JOIN grados gr ON gr.id = g.grado_id
                 WHERE p.colegio_id = ?';
        $pr = [$colegioId];
        if (isset(self::ESTADOS_PROCESO[$estado])) {
            $sql .= ' AND p.estado = ?';
            $pr[] = $estado;
        }
        if (isset(self::SITUACIONES[$situacion])) {
            $sql .= ' AND p.situacion_tipo = ?';
            $pr[] = $situacion;
        }
        $q = $pdo->prepare($sql . " ORDER BY FIELD(p.estado, 'reportado', 'abierto', 'seguimiento', 'cerrado'), p.fecha DESC LIMIT 300");
        $q->execute($pr);
        return $q->fetchAll();
    }

    // ------------------------------------------------------------------
    // Horas formativas (servicio social y Constitución Política)
    // ------------------------------------------------------------------

    public static function horas(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare('SELECT h.*, u.nombre AS registrador FROM horas_formativas h LEFT JOIN usuarios u ON u.id = h.registrado_por WHERE h.estudiante_id = ? ORDER BY h.fecha DESC, h.id DESC');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    /** @return array<string, array{nombre: string, requeridas: int, cumplidas: float}> */
    public static function totalesHoras(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare('SELECT tipo, COALESCE(SUM(horas), 0) FROM horas_formativas WHERE estudiante_id = ? GROUP BY tipo');
        $q->execute([$estudianteId]);
        $sumas = $q->fetchAll(PDO::FETCH_KEY_PAIR);
        $res = [];
        foreach (self::HORAS as $tipo => [$nombre, $req]) {
            $res[$tipo] = ['nombre' => $nombre, 'requeridas' => $req, 'cumplidas' => (float) ($sumas[$tipo] ?? 0)];
        }
        return $res;
    }

    public static function agregarHoras(PDO $pdo, int $colegioId, int $estudianteId, int $porId, string $tipo, string $fecha, float $horas, string $actividad, string $entidad): string
    {
        if (!isset(self::HORAS[$tipo])) {
            return 'Tipo de horas no válido.';
        }
        if (!self::fechaOk($fecha) || $fecha > date('Y-m-d')) {
            return 'La fecha no es válida (no puede ser futura).';
        }
        if ($horas <= 0 || $horas > 24) {
            return 'Las horas deben estar entre 0.5 y 24 por registro.';
        }
        $act = self::txt($actividad, 200);
        if ($act === null) {
            return 'Describe la actividad realizada.';
        }
        $pdo->prepare('INSERT INTO horas_formativas (colegio_id, estudiante_id, tipo, fecha, horas, actividad, entidad, registrado_por) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, $estudianteId, $tipo, $fecha, round($horas, 1), $act, self::txt($entidad, 120), $porId]);
        return '';
    }

    public static function eliminarHoras(PDO $pdo, int $id, int $estudianteId, int $colegioId): void
    {
        $pdo->prepare('DELETE FROM horas_formativas WHERE id = ? AND estudiante_id = ? AND colegio_id = ?')->execute([$id, $estudianteId, $colegioId]);
    }

    // ------------------------------------------------------------------
    // Aula de apoyo (PIAR)
    // ------------------------------------------------------------------

    public static function apoyo(PDO $pdo, int $estudianteId): ?array
    {
        $q = $pdo->prepare('SELECT a.*, u.nombre AS responsable FROM apoyos_pedagogicos a LEFT JOIN usuarios u ON u.id = a.responsable_id WHERE a.estudiante_id = ?');
        $q->execute([$estudianteId]);
        return $q->fetch() ?: null;
    }

    public static function guardarApoyo(PDO $pdo, int $colegioId, int $estudianteId, array $d, int $porId): string
    {
        $estado = (string) ($d['estado'] ?? 'activo');
        if (!in_array($estado, ['activo', 'cerrado'], true)) {
            return 'Estado no válido.';
        }
        $piar = trim((string) ($d['piar_fecha'] ?? ''));
        if ($piar !== '' && !self::fechaOk($piar)) {
            return 'La fecha del PIAR no es válida.';
        }
        $pdo->prepare('INSERT INTO apoyos_pedagogicos (colegio_id, estudiante_id, estado, condicion, barreras, ajustes, estrategias, responsable_id, piar_fecha)
                       VALUES (?,?,?,?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE estado = VALUES(estado), condicion = VALUES(condicion), barreras = VALUES(barreras), ajustes = VALUES(ajustes),
                                               estrategias = VALUES(estrategias), responsable_id = VALUES(responsable_id), piar_fecha = VALUES(piar_fecha)')
            ->execute([$colegioId, $estudianteId, $estado, self::txt($d['condicion'] ?? '', 255), self::txt($d['barreras'] ?? '', 5000), self::txt($d['ajustes'] ?? '', 5000),
                self::txt($d['estrategias'] ?? '', 5000), $porId, $piar !== '' ? $piar : null]);
        return '';
    }

    public static function seguimientosApoyo(PDO $pdo, int $apoyoId): array
    {
        $q = $pdo->prepare('SELECT s.*, u.nombre AS autor FROM apoyo_seguimientos s LEFT JOIN usuarios u ON u.id = s.autor_id WHERE s.apoyo_id = ? ORDER BY s.fecha DESC, s.id DESC');
        $q->execute([$apoyoId]);
        return $q->fetchAll();
    }

    public static function agregarSeguimientoApoyo(PDO $pdo, int $apoyoId, int $autorId, string $fecha, string $nota): string
    {
        if (!self::fechaOk($fecha)) {
            return 'La fecha no es válida.';
        }
        $n = self::txt($nota, 5000);
        if ($n === null) {
            return 'Escribe la nota de seguimiento.';
        }
        $pdo->prepare('INSERT INTO apoyo_seguimientos (apoyo_id, autor_id, fecha, nota) VALUES (?,?,?,?)')->execute([$apoyoId, $autorId, $fecha, $n]);
        return '';
    }

    public static function listarApoyos(PDO $pdo, int $colegioId, string $estado): array
    {
        $sql = 'SELECT a.id, a.estudiante_id, a.estado, a.piar_fecha, u.nombre AS estudiante, g.nombre AS grupo, gr.nombre AS grado, r.nombre AS responsable,
                       (SELECT MAX(s.fecha) FROM apoyo_seguimientos s WHERE s.apoyo_id = a.id) AS ultimo_seguimiento
                  FROM apoyos_pedagogicos a INNER JOIN usuarios u ON u.id = a.estudiante_id
                  LEFT JOIN grupos g ON g.id = u.grupo_id LEFT JOIN grados gr ON gr.id = g.grado_id LEFT JOIN usuarios r ON r.id = a.responsable_id
                 WHERE a.colegio_id = ?';
        $p = [$colegioId];
        if (in_array($estado, ['activo', 'cerrado'], true)) {
            $sql .= ' AND a.estado = ?';
            $p[] = $estado;
        }
        $q = $pdo->prepare($sql . ' ORDER BY a.estado, gr.nombre, g.nombre, u.nombre');
        $q->execute($p);
        return $q->fetchAll();
    }

    // ------------------------------------------------------------------
    // Asistencia
    // ------------------------------------------------------------------

    /** Asistencia de un día en una asignación: [estudiante_id => fila]. */
    public static function asistenciaDelDia(PDO $pdo, int $asignacionId, string $fecha): array
    {
        $q = $pdo->prepare('SELECT * FROM asistencias WHERE asignacion_id = ? AND fecha = ?');
        $q->execute([$asignacionId, $fecha]);
        $res = [];
        foreach ($q->fetchAll() as $r) {
            $res[(int) $r['estudiante_id']] = $r;
        }
        return $res;
    }

    /** Guarda la asistencia del día. Devuelve cuántos estudiantes registró, o -1 si la fecha no es válida. */
    public static function guardarAsistencia(PDO $pdo, int $asignacionId, string $fecha, array $estudiantes, array $estados, array $observaciones, int $porId): int
    {
        if (!self::fechaOk($fecha) || $fecha > date('Y-m-d')) {
            return -1;
        }
        $up = $pdo->prepare('INSERT INTO asistencias (asignacion_id, fecha, estudiante_id, estado, observacion, registrado_por) VALUES (?,?,?,?,?,?)
                             ON DUPLICATE KEY UPDATE estado = VALUES(estado), observacion = VALUES(observacion), registrado_por = VALUES(registrado_por)');
        $n = 0;
        foreach ($estudiantes as $e) {
            $eid = (int) $e['id'];
            $est = (string) ($estados[$eid] ?? 'presente');
            if (!isset(self::ESTADOS_ASISTENCIA[$est])) {
                $est = 'presente';
            }
            $up->execute([$asignacionId, $fecha, $eid, $est, self::txt($observaciones[$eid] ?? '', 150), $porId]);
            $n++;
        }
        return $n;
    }

    /** Modo «al final del período»: totales de ausencias y excusadas por estudiante. */
    public static function guardarResumenPeriodo(PDO $pdo, int $asignacionId, int $periodoId, array $estudiantes, array $ausencias, array $excusadas): int
    {
        $up = $pdo->prepare('INSERT INTO inasistencias_periodo (asignacion_id, periodo_id, estudiante_id, ausencias, excusadas) VALUES (?,?,?,?,?)
                             ON DUPLICATE KEY UPDATE ausencias = VALUES(ausencias), excusadas = VALUES(excusadas)');
        $del = $pdo->prepare('DELETE FROM inasistencias_periodo WHERE asignacion_id = ? AND periodo_id = ? AND estudiante_id = ?');
        $n = 0;
        foreach ($estudiantes as $e) {
            $eid = (int) $e['id'];
            $a = trim((string) ($ausencias[$eid] ?? ''));
            $x = trim((string) ($excusadas[$eid] ?? ''));
            if ($a === '' && $x === '') {
                $del->execute([$asignacionId, $periodoId, $eid]);
                continue;
            }
            $up->execute([$asignacionId, $periodoId, $eid, max(0, min(999, (int) $a)), max(0, min(999, (int) $x))]);
            $n++;
        }
        return $n;
    }

    public static function resumenPeriodoGuardado(PDO $pdo, int $asignacionId, int $periodoId): array
    {
        $q = $pdo->prepare('SELECT * FROM inasistencias_periodo WHERE asignacion_id = ? AND periodo_id = ?');
        $q->execute([$asignacionId, $periodoId]);
        $res = [];
        foreach ($q->fetchAll() as $r) {
            $res[(int) $r['estudiante_id']] = $r;
        }
        return $res;
    }

    /**
     * Inasistencias de un estudiante por asignatura y período. Si el docente cargó el resumen del período, se usa ese;
     * si no, se cuentan las ausencias del registro diario dentro de las fechas del período.
     * @return array<int, array{asignatura: string, periodos: array<int, array{ausencias: int, excusadas: int, tardes: int, origen: string}>}>
     */
    public static function resumenAsistencia(PDO $pdo, int $colegioId, int $estudianteId, array $anio, array $periodos): array
    {
        $grupoId = Evaluacion::grupoDeEstudiante($pdo, $estudianteId, $anio);
        if ($grupoId === null || !$periodos) {
            return [];
        }
        $res = [];
        $guardado = $pdo->prepare('SELECT ausencias, excusadas FROM inasistencias_periodo WHERE asignacion_id = ? AND periodo_id = ? AND estudiante_id = ?');
        $diario = $pdo->prepare("SELECT SUM(estado = 'ausente'), SUM(estado = 'excusa'), SUM(estado = 'tarde') FROM asistencias
                                  WHERE asignacion_id = ? AND estudiante_id = ? AND fecha BETWEEN ? AND ?");
        foreach (Evaluacion::asignaciones($pdo, $colegioId, (int) $anio['id'], null, $grupoId) as $a) {
            $fila = ['asignatura' => (string) $a['asignatura'], 'periodos' => []];
            foreach ($periodos as $p) {
                $guardado->execute([(int) $a['id'], (int) $p['id'], $estudianteId]);
                $g = $guardado->fetch();
                if ($g) {
                    $fila['periodos'][(int) $p['id']] = ['ausencias' => (int) $g['ausencias'], 'excusadas' => (int) $g['excusadas'], 'tardes' => 0, 'origen' => 'resumen'];
                    continue;
                }
                $diario->execute([(int) $a['id'], $estudianteId, $p['fecha_inicio'], $p['fecha_fin']]);
                $d = $diario->fetch(PDO::FETCH_NUM);
                $fila['periodos'][(int) $p['id']] = ['ausencias' => (int) ($d[0] ?? 0), 'excusadas' => (int) ($d[1] ?? 0), 'tardes' => (int) ($d[2] ?? 0), 'origen' => 'diario'];
            }
            $res[] = $fila;
        }
        return $res;
    }

    // ------------------------------------------------------------------
    // Planeador y diario de campo (solo el propio docente)
    // ------------------------------------------------------------------

    public static function planeaciones(PDO $pdo, int $docenteId, int $asignacionId): array
    {
        $q = $pdo->prepare('SELECT p.*, pe.nombre AS periodo FROM planeaciones p LEFT JOIN periodos pe ON pe.id = p.periodo_id
                             WHERE p.docente_id = ?' . ($asignacionId > 0 ? ' AND p.asignacion_id = ?' : '') . ' ORDER BY p.fecha_inicio DESC, p.id DESC LIMIT 200');
        $q->execute($asignacionId > 0 ? [$docenteId, $asignacionId] : [$docenteId]);
        return $q->fetchAll();
    }

    private static function asignacionDelDocente(PDO $pdo, int $asignacionId, int $docenteId, int $colegioId): bool
    {
        $q = $pdo->prepare('SELECT 1 FROM asignaciones_academicas WHERE id = ? AND docente_id = ? AND colegio_id = ?');
        $q->execute([$asignacionId, $docenteId, $colegioId]);
        return (bool) $q->fetchColumn();
    }

    public static function guardarPlaneacion(PDO $pdo, int $colegioId, int $docenteId, ?int $id, array $d): string
    {
        $asig = (int) ($d['asignacion_id'] ?? 0);
        if (!self::asignacionDelDocente($pdo, $asig, $docenteId, $colegioId)) {
            return 'Elige una de tus asignaciones.';
        }
        $ini = (string) ($d['fecha_inicio'] ?? '');
        $fin = trim((string) ($d['fecha_fin'] ?? ''));
        if (!self::fechaOk($ini) || ($fin !== '' && (!self::fechaOk($fin) || $fin < $ini))) {
            return 'Revisa las fechas (la final no puede ser anterior a la inicial).';
        }
        $tema = self::txt($d['tema'] ?? '', 150);
        if ($tema === null) {
            return 'Escribe el tema o la unidad.';
        }
        $estado = (string) ($d['estado'] ?? 'borrador');
        if (!isset(self::ESTADOS_PLANEACION[$estado])) {
            $estado = 'borrador';
        }
        $periodo = (int) ($d['periodo_id'] ?? 0) ?: null;
        if ($periodo !== null && Evaluacion::periodo($pdo, $periodo, $colegioId) === null) {
            $periodo = null;
        }
        $campos = [$asig, $periodo, $ini, $fin !== '' ? $fin : null, $tema, self::txt($d['estandar'] ?? '', 500), self::txt($d['objetivos'] ?? '', 5000),
            self::txt($d['actividades'] ?? '', 8000), self::txt($d['recursos'] ?? '', 500), self::txt($d['evaluacion'] ?? '', 500), $estado];
        if ($id !== null && $id > 0) {
            $pdo->prepare('UPDATE planeaciones SET asignacion_id = ?, periodo_id = ?, fecha_inicio = ?, fecha_fin = ?, tema = ?, estandar = ?, objetivos = ?, actividades = ?, recursos = ?, evaluacion = ?, estado = ?
                            WHERE id = ? AND docente_id = ?')->execute(array_merge($campos, [$id, $docenteId]));
        } else {
            $pdo->prepare('INSERT INTO planeaciones (asignacion_id, periodo_id, fecha_inicio, fecha_fin, tema, estandar, objetivos, actividades, recursos, evaluacion, estado, colegio_id, docente_id)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute(array_merge($campos, [$colegioId, $docenteId]));
        }
        return '';
    }

    public static function eliminarPlaneacion(PDO $pdo, int $id, int $docenteId): void
    {
        $pdo->prepare('DELETE FROM planeaciones WHERE id = ? AND docente_id = ?')->execute([$id, $docenteId]);
    }

    public static function diarios(PDO $pdo, int $docenteId): array
    {
        $q = $pdo->prepare('SELECT d.*, s.nombre AS asignatura, g.nombre AS grupo, gr.nombre AS grado
                              FROM diario_campo d
                              LEFT JOIN asignaciones_academicas a ON a.id = d.asignacion_id LEFT JOIN asignaturas s ON s.id = a.asignatura_id
                              LEFT JOIN grupos g ON g.id = a.grupo_id LEFT JOIN grados gr ON gr.id = g.grado_id
                             WHERE d.docente_id = ? ORDER BY d.fecha DESC, d.id DESC LIMIT 200');
        $q->execute([$docenteId]);
        return $q->fetchAll();
    }

    public static function guardarDiario(PDO $pdo, int $colegioId, int $docenteId, array $d): string
    {
        $fecha = (string) ($d['fecha'] ?? '');
        if (!self::fechaOk($fecha) || $fecha > date('Y-m-d')) {
            return 'La fecha no es válida (no puede ser futura).';
        }
        $obs = self::txt($d['observaciones'] ?? '', 8000);
        if ($obs === null) {
            return 'Escribe tus observaciones de la clase.';
        }
        $asig = (int) ($d['asignacion_id'] ?? 0) ?: null;
        if ($asig !== null && !self::asignacionDelDocente($pdo, $asig, $docenteId, $colegioId)) {
            return 'Esa asignación no es tuya.';
        }
        $plan = (int) ($d['planeacion_id'] ?? 0) ?: null;
        if ($plan !== null) {
            $q = $pdo->prepare('SELECT 1 FROM planeaciones WHERE id = ? AND docente_id = ?');
            $q->execute([$plan, $docenteId]);
            if (!$q->fetchColumn()) {
                $plan = null;
            }
        }
        $pdo->prepare('INSERT INTO diario_campo (colegio_id, docente_id, asignacion_id, planeacion_id, fecha, observaciones, dificultades, ajustes) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, $docenteId, $asig, $plan, $fecha, $obs, self::txt($d['dificultades'] ?? '', 5000), self::txt($d['ajustes'] ?? '', 5000)]);
        return '';
    }

    public static function eliminarDiario(PDO $pdo, int $id, int $docenteId): void
    {
        $pdo->prepare('DELETE FROM diario_campo WHERE id = ? AND docente_id = ?')->execute([$id, $docenteId]);
    }
}
