<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/validaciones.php';
require_once __DIR__ . '/Usuario.php';

/**
 * Base académica: años lectivos, períodos, inscripciones web, matrículas (nueva y renovación) y acudientes.
 * Requiere la migración 034_academico_base.sql. Todas las consultas reciben el colegio y lo usan para
 * aislar los datos: nunca confíes en un id que venga del navegador sin pasarlo por aquí.
 */
class Academico
{
    public const TIPOS_DOCUMENTO = [
        'RC' => 'Registro civil', 'TI' => 'Tarjeta de identidad', 'CC' => 'Cédula de ciudadanía',
        'CE' => 'Cédula de extranjería', 'PPT' => 'Permiso por protección temporal', 'NUIP' => 'NUIP',
    ];
    public const PARENTESCOS = ['Madre', 'Padre', 'Abuelo/a', 'Tío/a', 'Hermano/a mayor', 'Acudiente legal', 'Otro'];
    public const ESTADOS_INSCRIPCION = [
        'pendiente' => 'Pendiente', 'en_revision' => 'En revisión', 'admitida' => 'Admitida',
        'rechazada' => 'Rechazada', 'matriculada' => 'Matriculada',
    ];
    public const ESTADOS_MATRICULA = ['pendiente' => 'Pendiente', 'activa' => 'Activa', 'rechazada' => 'Rechazada', 'retirada' => 'Retirada'];

    /** Máximo de inscripciones por IP y hora (frena el envío masivo desde un mismo lugar). */
    public const MAX_INSCRIPCIONES_POR_IP_HORA = 8;

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** Contraseña temporal que cumple las reglas de Serena (8-64, mayúscula, minúscula, número y especial). */
    public static function contrasenaTemporal(): string
    {
        $pick = static function (string $set, int $n): array {
            $r = [];
            for ($i = 0; $i < $n; $i++) {
                $r[] = $set[random_int(0, strlen($set) - 1)];
            }
            return $r;
        };
        $c = array_merge(
            $pick('ABCDEFGHJKLMNPQRSTUVWXYZ', 2), $pick('abcdefghijkmnpqrstuvwxyz', 4),
            $pick('23456789', 2), $pick('!@#$%&*?', 1)
        );
        for ($i = count($c) - 1; $i > 0; $i--) { // Fisher-Yates con random_int
            $j = random_int(0, $i);
            [$c[$i], $c[$j]] = [$c[$j], $c[$i]];
        }
        return implode('', $c);
    }

    private static function fechaValida(string $f): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $f);
        return $d !== false && $d->format('Y-m-d') === $f;
    }

    private static function fechaONulo(?string $f): ?string
    {
        $f = trim((string) $f);
        return ($f !== '' && self::fechaValida($f)) ? $f : null;
    }

    /** Grados del colegio (para formularios). */
    public static function grados(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare('SELECT id, nombre FROM grados WHERE colegio_id = ? ORDER BY nombre');
        $q->execute([$colegioId]);
        return $q->fetchAll();
    }

    /** Grupos del colegio con su grado. */
    public static function grupos(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare('SELECT g.id, g.nombre, g.grado_id, gr.nombre AS grado
                              FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id
                             WHERE gr.colegio_id = ? ORDER BY gr.nombre, g.nombre');
        $q->execute([$colegioId]);
        return $q->fetchAll();
    }

    // ------------------------------------------------------------------
    // Años lectivos y períodos
    // ------------------------------------------------------------------

    public static function anios(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare('SELECT a.*,
                                   (SELECT COUNT(*) FROM periodos p WHERE p.anio_lectivo_id = a.id) AS n_periodos,
                                   (SELECT COUNT(*) FROM inscripciones i WHERE i.anio_lectivo_id = a.id) AS n_inscripciones,
                                   (SELECT COUNT(*) FROM matriculas m WHERE m.anio_lectivo_id = a.id) AS n_matriculas
                              FROM anios_lectivos a WHERE a.colegio_id = ? ORDER BY a.anio DESC');
        $q->execute([$colegioId]);
        return $q->fetchAll();
    }

    public static function anio(PDO $pdo, int $id, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT * FROM anios_lectivos WHERE id = ? AND colegio_id = ?');
        $q->execute([$id, $colegioId]);
        return $q->fetch() ?: null;
    }

    public static function anioActual(PDO $pdo, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT * FROM anios_lectivos WHERE colegio_id = ? AND actual = 1 LIMIT 1');
        $q->execute([$colegioId]);
        return $q->fetch() ?: null;
    }

    /** Crea un año. Devuelve [id, error]. El primer año del colegio queda como «actual». */
    public static function crearAnio(PDO $pdo, int $colegioId, int $anio, string $nombre, ?string $ini, ?string $fin): array
    {
        $nombre = trim($nombre) !== '' ? mb_substr(trim($nombre), 0, 60) : 'Año lectivo ' . $anio;
        if ($anio < 2000 || $anio > 2100) {
            return [0, 'El año debe estar entre 2000 y 2100.'];
        }
        $ini = self::fechaONulo($ini);
        $fin = self::fechaONulo($fin);
        if ($ini !== null && $fin !== null && $ini > $fin) {
            return [0, 'La fecha de inicio no puede ser posterior a la de fin.'];
        }
        $q = $pdo->prepare('SELECT COUNT(*) FROM anios_lectivos WHERE colegio_id = ?');
        $q->execute([$colegioId]);
        $primero = (int) $q->fetchColumn() === 0;
        try {
            $pdo->prepare('INSERT INTO anios_lectivos (colegio_id, anio, nombre, fecha_inicio, fecha_fin, actual) VALUES (?,?,?,?,?,?)')
                ->execute([$colegioId, $anio, $nombre, $ini, $fin, $primero ? 1 : 0]);
        } catch (PDOException $e) {
            return [0, $e->getCode() === '23000' ? "Ya existe el año {$anio} en este colegio." : 'No se pudo crear el año.'];
        }
        return [(int) $pdo->lastInsertId(), ''];
    }

    public static function actualizarAnio(PDO $pdo, int $id, int $colegioId, string $nombre, ?string $ini, ?string $fin, bool $inscripciones, bool $renovacion): string
    {
        $ini = self::fechaONulo($ini);
        $fin = self::fechaONulo($fin);
        if ($ini !== null && $fin !== null && $ini > $fin) {
            return 'La fecha de inicio no puede ser posterior a la de fin.';
        }
        $pdo->prepare('UPDATE anios_lectivos SET nombre = ?, fecha_inicio = ?, fecha_fin = ?, inscripciones_abiertas = ?, renovacion_abierta = ?
                        WHERE id = ? AND colegio_id = ?')
            ->execute([mb_substr(trim($nombre), 0, 60) ?: 'Año lectivo', $ini, $fin, $inscripciones ? 1 : 0, $renovacion ? 1 : 0, $id, $colegioId]);
        return '';
    }

    /**
     * Marca el año como el actual y pasa a cada estudiante con matrícula ACTIVA y grupo asignado a ese grupo.
     * Devuelve cuántos estudiantes cambiaron de grupo.
     */
    public static function marcarActual(PDO $pdo, int $id, int $colegioId): int
    {
        if (self::anio($pdo, $id, $colegioId) === null) {
            return 0;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE anios_lectivos SET actual = 0 WHERE colegio_id = ?')->execute([$colegioId]);
            $pdo->prepare('UPDATE anios_lectivos SET actual = 1 WHERE id = ? AND colegio_id = ?')->execute([$id, $colegioId]);
            $u = $pdo->prepare("UPDATE usuarios u INNER JOIN matriculas m ON m.estudiante_id = u.id
                                   SET u.grupo_id = m.grupo_id
                                 WHERE m.anio_lectivo_id = ? AND m.colegio_id = ? AND m.estado = 'activa' AND m.grupo_id IS NOT NULL");
            $u->execute([$id, $colegioId]);
            $n = $u->rowCount();
            $pdo->commit();
            return $n;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Elimina el año solo si no tiene inscripciones ni matrículas. Devuelve un error o ''. */
    public static function eliminarAnio(PDO $pdo, int $id, int $colegioId): string
    {
        $a = self::anio($pdo, $id, $colegioId);
        if ($a === null) {
            return 'El año no existe.';
        }
        $q = $pdo->prepare('SELECT (SELECT COUNT(*) FROM inscripciones WHERE anio_lectivo_id = ?) + (SELECT COUNT(*) FROM matriculas WHERE anio_lectivo_id = ?)');
        $q->execute([$id, $id]);
        if ((int) $q->fetchColumn() > 0) {
            return 'Ese año ya tiene inscripciones o matrículas; no se puede eliminar.';
        }
        if ((int) $a['actual'] === 1) {
            return 'Es el año actual: marca otro como actual antes de eliminarlo.';
        }
        $pdo->prepare('DELETE FROM anios_lectivos WHERE id = ? AND colegio_id = ?')->execute([$id, $colegioId]);
        return '';
    }

    public static function periodos(PDO $pdo, int $anioId): array
    {
        $q = $pdo->prepare('SELECT * FROM periodos WHERE anio_lectivo_id = ? ORDER BY numero');
        $q->execute([$anioId]);
        return $q->fetchAll();
    }

    /** Crea o actualiza un período (clave: año + número). Devuelve un error o ''. */
    public static function guardarPeriodo(PDO $pdo, array $anio, int $numero, string $nombre, string $ini, string $fin, float $peso): string
    {
        if ($numero < 1 || $numero > 12) {
            return 'El número de período debe estar entre 1 y 12.';
        }
        if (!self::fechaValida($ini) || !self::fechaValida($fin) || $ini > $fin) {
            return 'Revisa las fechas del período (inicio no puede ser posterior al fin).';
        }
        if (($anio['fecha_inicio'] && $ini < $anio['fecha_inicio']) || ($anio['fecha_fin'] && $fin > $anio['fecha_fin'])) {
            return 'Las fechas del período deben estar dentro del año lectivo.';
        }
        if ($peso < 0 || $peso > 100) {
            return 'El peso del período debe estar entre 0 y 100.';
        }
        $pdo->prepare('INSERT INTO periodos (anio_lectivo_id, numero, nombre, fecha_inicio, fecha_fin, peso) VALUES (?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), fecha_inicio = VALUES(fecha_inicio), fecha_fin = VALUES(fecha_fin), peso = VALUES(peso)')
            ->execute([(int) $anio['id'], $numero, mb_substr(trim($nombre), 0, 40) ?: "Período {$numero}", $ini, $fin, round($peso, 2)]);
        return '';
    }

    /** Reparte el año en N períodos iguales (solo si el año aún no tiene períodos). Devuelve un error o ''. */
    public static function generarPeriodos(PDO $pdo, array $anio, int $n): string
    {
        if ($n < 1 || $n > 12) {
            return 'Elige entre 1 y 12 períodos.';
        }
        if (!$anio['fecha_inicio'] || !$anio['fecha_fin']) {
            return 'Primero define las fechas de inicio y fin del año lectivo.';
        }
        if (self::periodos($pdo, (int) $anio['id'])) {
            return 'Este año ya tiene períodos. Elimínalos antes de generarlos de nuevo.';
        }
        $ini = new DateTimeImmutable((string) $anio['fecha_inicio']);
        $fin = new DateTimeImmutable((string) $anio['fecha_fin']);
        $dias = (int) $ini->diff($fin)->days + 1;
        $base = intdiv($dias, $n);
        if ($base < 1) {
            return 'El año es demasiado corto para ese número de períodos.';
        }
        $peso = round(100 / $n, 2);
        $acum = 0.0;
        $desde = $ini;
        for ($i = 1; $i <= $n; $i++) {
            $hasta = $i === $n ? $fin : $desde->modify('+' . ($base - 1) . ' days');
            $p = $i === $n ? round(100 - $acum, 2) : $peso;
            $acum += $p;
            self::guardarPeriodo($pdo, $anio, $i, "Período {$i}", $desde->format('Y-m-d'), $hasta->format('Y-m-d'), $p);
            $desde = $hasta->modify('+1 day');
        }
        return '';
    }

    public static function eliminarPeriodo(PDO $pdo, int $id, int $anioId): void
    {
        $pdo->prepare('DELETE FROM periodos WHERE id = ? AND anio_lectivo_id = ?')->execute([$id, $anioId]);
    }

    // ------------------------------------------------------------------
    // Inscripciones (formulario web público)
    // ------------------------------------------------------------------

    /** Colegios activos con inscripciones abiertas (lista pública: solo nombre y municipio). */
    public static function colegiosConInscripcion(PDO $pdo): array
    {
        return $pdo->query('SELECT c.id, c.nombre, c.municipio, a.id AS anio_id, a.anio
                              FROM anios_lectivos a INNER JOIN colegios c ON c.id = a.colegio_id
                             WHERE a.inscripciones_abiertas = 1 AND c.activo = 1
                          ORDER BY c.nombre')->fetchAll();
    }

    public static function anioConInscripcionAbierta(PDO $pdo, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT a.*, c.nombre AS colegio_nombre FROM anios_lectivos a INNER JOIN colegios c ON c.id = a.colegio_id
                             WHERE a.colegio_id = ? AND a.inscripciones_abiertas = 1 AND c.activo = 1 ORDER BY a.anio DESC LIMIT 1');
        $q->execute([$colegioId]);
        return $q->fetch() ?: null;
    }

    public static function inscripcionesRecientesPorIp(PDO $pdo, string $ipHash): int
    {
        $q = $pdo->prepare('SELECT COUNT(*) FROM inscripciones WHERE ip_hash = ? AND creado_en >= (NOW() - INTERVAL 1 HOUR)');
        $q->execute([$ipHash]);
        return (int) $q->fetchColumn();
    }

    private static function codigoSeguimiento(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $c = '';
        for ($i = 0; $i < 10; $i++) {
            $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        return $c;
    }

    private static function limpiar(mixed $v, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v) ?? ''), 0, $max);
    }

    /**
     * Valida y guarda una inscripción. $d trae los campos del formulario.
     * @return array{ok: bool, codigo: string, error: string}
     */
    public static function crearInscripcion(PDO $pdo, int $colegioId, array $d, string $ipHash): array
    {
        $fallo = static fn (string $m): array => ['ok' => false, 'codigo' => '', 'error' => $m];

        $anio = self::anioConInscripcionAbierta($pdo, $colegioId);
        if ($anio === null) {
            return $fallo('Este colegio no tiene inscripciones abiertas.');
        }
        if (self::inscripcionesRecientesPorIp($pdo, $ipHash) >= self::MAX_INSCRIPCIONES_POR_IP_HORA) {
            return $fallo('Se enviaron demasiadas solicitudes desde esta conexión. Intenta de nuevo en una hora.');
        }
        $gradoId = (int) ($d['grado_id'] ?? 0);
        $q = $pdo->prepare('SELECT 1 FROM grados WHERE id = ? AND colegio_id = ?');
        $q->execute([$gradoId, $colegioId]);
        if (!$q->fetchColumn()) {
            return $fallo('Elige el grado al que aspira.');
        }

        $nombres = self::limpiar($d['nombres'] ?? '', 80);
        $apellidos = self::limpiar($d['apellidos'] ?? '', 80);
        $tipoDoc = (string) ($d['tipo_documento'] ?? '');
        $numDoc = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($d['numero_documento'] ?? '')) ?? '');
        $nace = (string) ($d['fecha_nacimiento'] ?? '');
        $correoEst = self::limpiar($d['correo_estudiante'] ?? '', 150);
        $aNombre = self::limpiar($d['acudiente_nombre'] ?? '', 120);
        $aParentesco = self::limpiar($d['acudiente_parentesco'] ?? '', 30);
        $aDoc = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($d['acudiente_documento'] ?? '')) ?? '');
        $aCorreo = strtolower(self::limpiar($d['acudiente_correo'] ?? '', 150));
        $aTel = preg_replace('/[^0-9+]/', '', (string) ($d['acudiente_telefono'] ?? '')) ?? '';

        if (mb_strlen($nombres) < 2 || mb_strlen($apellidos) < 2) {
            return $fallo('Escribe los nombres y apellidos del aspirante.');
        }
        if (!isset(self::TIPOS_DOCUMENTO[$tipoDoc])) {
            return $fallo('Elige el tipo de documento del aspirante.');
        }
        if (strlen($numDoc) < 5 || strlen($numDoc) > 20) {
            return $fallo('El número de documento del aspirante debe tener entre 5 y 20 caracteres.');
        }
        if (!self::fechaValida($nace)) {
            return $fallo('Escribe la fecha de nacimiento del aspirante.');
        }
        $edad = (int) (new DateTime($nace))->diff(new DateTime('today'))->y;
        if ($nace > date('Y-m-d') || $edad < 3 || $edad > 25) {
            return $fallo('Revisa la fecha de nacimiento: la edad debe estar entre 3 y 25 años.');
        }
        if ($correoEst !== '' && !validarCorreo($correoEst)) {
            return $fallo('El correo del aspirante no es válido (puedes dejarlo vacío).');
        }
        if (mb_strlen($aNombre) < 3 || !in_array($aParentesco, self::PARENTESCOS, true)) {
            return $fallo('Escribe el nombre del acudiente y elige su parentesco.');
        }
        if (strlen($aDoc) < 5 || strlen($aDoc) > 20) {
            return $fallo('El número de documento del acudiente debe tener entre 5 y 20 caracteres.');
        }
        if (!validarCorreo($aCorreo)) {
            return $fallo('El correo del acudiente no es válido.');
        }
        if (strlen(preg_replace('/\D/', '', $aTel) ?? '') < 7 || strlen($aTel) > 20) {
            return $fallo('Escribe un teléfono de contacto válido (mínimo 7 dígitos).');
        }
        if (empty($d['consentimiento'])) {
            return $fallo('Debes aceptar el tratamiento de datos personales para continuar.');
        }

        $sql = 'INSERT INTO inscripciones
                    (colegio_id, anio_lectivo_id, grado_id, codigo, nombres, apellidos, tipo_documento, numero_documento, fecha_nacimiento,
                     correo_estudiante, direccion, colegio_anterior, acudiente_nombre, acudiente_parentesco, acudiente_documento,
                     acudiente_correo, acudiente_telefono, observaciones, consentimiento, consentimiento_en, ip_hash)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),?)';
        for ($intento = 0; $intento < 4; $intento++) {
            $codigo = self::codigoSeguimiento();
            try {
                $pdo->prepare($sql)->execute([
                    $colegioId, (int) $anio['id'], $gradoId, $codigo, $nombres, $apellidos, $tipoDoc, $numDoc, $nace,
                    $correoEst !== '' ? strtolower($correoEst) : null,
                    self::limpiar($d['direccion'] ?? '', 200) ?: null, self::limpiar($d['colegio_anterior'] ?? '', 150) ?: null,
                    $aNombre, $aParentesco, $aDoc, $aCorreo, $aTel, self::limpiar($d['observaciones'] ?? '', 500) ?: null, $ipHash,
                ]);
                return ['ok' => true, 'codigo' => $codigo, 'error' => ''];
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    return $fallo('No se pudo guardar la inscripción. Intenta de nuevo.');
                }
                if (strpos($e->getMessage(), 'inscripcion_documento_anio') !== false) {
                    return $fallo('Ya hay una inscripción con ese documento para este año. Consulta su estado con el código que recibiste o comunícate con el colegio.');
                }
                // choque de código de seguimiento: se reintenta con otro
            }
        }
        return $fallo('No se pudo generar el código de seguimiento. Intenta de nuevo.');
    }

    /** Consulta pública: exige el código Y el documento del aspirante. */
    public static function inscripcionPorCodigo(PDO $pdo, string $codigo, string $documento): ?array
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? '');
        $documento = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $documento) ?? '');
        if ($codigo === '' || $documento === '') {
            return null;
        }
        $q = $pdo->prepare('SELECT i.codigo, i.estado, i.nombres, i.apellidos, i.nota_resolucion, i.creado_en, i.resuelto_en,
                                   c.nombre AS colegio, g.nombre AS grado, a.anio
                              FROM inscripciones i
                              INNER JOIN colegios c ON c.id = i.colegio_id
                              INNER JOIN grados g ON g.id = i.grado_id
                              INNER JOIN anios_lectivos a ON a.id = i.anio_lectivo_id
                             WHERE i.codigo = ? AND i.numero_documento = ? LIMIT 1');
        $q->execute([$codigo, $documento]);
        return $q->fetch() ?: null;
    }

    public static function inscripciones(PDO $pdo, int $colegioId, ?int $anioId, string $estado, string $busqueda): array
    {
        $sql = 'SELECT i.*, g.nombre AS grado, a.anio
                  FROM inscripciones i INNER JOIN grados g ON g.id = i.grado_id INNER JOIN anios_lectivos a ON a.id = i.anio_lectivo_id
                 WHERE i.colegio_id = ?';
        $p = [$colegioId];
        if ($anioId) {
            $sql .= ' AND i.anio_lectivo_id = ?';
            $p[] = $anioId;
        }
        if (isset(self::ESTADOS_INSCRIPCION[$estado])) {
            $sql .= ' AND i.estado = ?';
            $p[] = $estado;
        }
        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $sql .= ' AND (i.nombres LIKE ? OR i.apellidos LIKE ? OR i.numero_documento LIKE ? OR i.acudiente_nombre LIKE ? OR i.codigo LIKE ?)';
            $like = '%' . $busqueda . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }
        $sql .= " ORDER BY FIELD(i.estado, 'pendiente', 'en_revision', 'admitida', 'matriculada', 'rechazada'), i.creado_en DESC LIMIT 300";
        $q = $pdo->prepare($sql);
        $q->execute($p);
        return $q->fetchAll();
    }

    /** Admitir, rechazar o pasar a revisión. Devuelve un error o ''. */
    public static function resolverInscripcion(PDO $pdo, int $id, int $colegioId, string $estado, ?string $nota, int $usuarioId): string
    {
        if (!in_array($estado, ['en_revision', 'admitida', 'rechazada'], true)) {
            return 'Estado no válido.';
        }
        $q = $pdo->prepare('SELECT estado FROM inscripciones WHERE id = ? AND colegio_id = ?');
        $q->execute([$id, $colegioId]);
        $actual = $q->fetchColumn();
        if ($actual === false) {
            return 'La inscripción no existe.';
        }
        if ($actual === 'matriculada') {
            return 'Esa inscripción ya fue matriculada.';
        }
        $pdo->prepare('UPDATE inscripciones SET estado = ?, nota_resolucion = ?, resuelto_por = ?, resuelto_en = NOW() WHERE id = ? AND colegio_id = ?')
            ->execute([$estado, self::limpiar($nota ?? '', 255) ?: null, $usuarioId, $id, $colegioId]);
        return '';
    }

    // ------------------------------------------------------------------
    // Acudientes
    // ------------------------------------------------------------------

    private static function rolId(PDO $pdo, string $rol): int
    {
        $q = $pdo->prepare('SELECT id FROM roles WHERE nombre = ?');
        $q->execute([$rol]);
        return (int) $q->fetchColumn();
    }

    /**
     * Matricula a un aspirante: crea al estudiante y a su acudiente (o reutiliza al acudiente si ya existe),
     * los vincula y crea la matrícula ACTIVA. Todo en una transacción.
     *
     * @return array{error: string, estudiante?: array, acudiente?: array, aviso?: string}
     */
    public static function matricularDesdeInscripcion(PDO $pdo, int $inscripcionId, int $colegioId, int $grupoId, int $usuarioId): array
    {
        $q = $pdo->prepare('SELECT * FROM inscripciones WHERE id = ? AND colegio_id = ?');
        $q->execute([$inscripcionId, $colegioId]);
        $i = $q->fetch();
        if (!$i) {
            return ['error' => 'La inscripción no existe.'];
        }
        if (in_array($i['estado'], ['matriculada', 'rechazada'], true)) {
            return ['error' => 'Esa inscripción está ' . strtolower(self::ESTADOS_INSCRIPCION[$i['estado']]) . ': no se puede matricular.'];
        }
        $g = $pdo->prepare('SELECT g.id, g.grado_id FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE g.id = ? AND gr.colegio_id = ?');
        $g->execute([$grupoId, $colegioId]);
        $grupo = $g->fetch();
        if (!$grupo || (int) $grupo['grado_id'] !== (int) $i['grado_id']) {
            return ['error' => 'Elige un grupo del grado al que aspira el estudiante.'];
        }
        $anio = self::anio($pdo, (int) $i['anio_lectivo_id'], $colegioId);
        if ($anio === null) {
            return ['error' => 'El año lectivo de la inscripción no existe.'];
        }

        $correoEst = (string) ($i['correo_estudiante'] ?? '');
        if ($correoEst === '' || !validarCorreo($correoEst)) {
            $correoEst = 'est.' . strtolower((string) $i['numero_documento']) . '@serena.local';
        }
        if (Usuario::correoExiste($pdo, $correoEst)) {
            return ['error' => "Ya existe un usuario con el correo {$correoEst}. Si es el mismo estudiante, usa Matrículas → Renovación."];
        }

        $rolAcud = self::rolId($pdo, 'acudiente');
        if ($rolAcud === 0) {
            return ['error' => 'Falta el rol «acudiente»: aplica la migración 034_academico_base.sql.'];
        }
        $existente = Usuario::buscarPorCorreo($pdo, (string) $i['acudiente_correo']);

        $pdo->beginTransaction();
        try {
            // Estudiante: el grupo se aplica a su cuenta solo si el año es el actual; si no, al activar el año.
            $passEst = self::contrasenaTemporal();
            $estudianteId = Usuario::crearEstudiante($pdo, [
                'nombre' => $i['nombres'] . ' ' . $i['apellidos'], 'correo' => $correoEst, 'password' => $passEst,
                'colegio_id' => $colegioId, 'grupo_id' => (int) $anio['actual'] === 1 ? $grupoId : null,
                'fecha_nacimiento' => $i['fecha_nacimiento'],
            ]);

            // El documento de la inscripción también sirve para iniciar sesión.
            $docEst = Usuario::normalizarDocumento((string) ($i['numero_documento'] ?? ''));
            if (Usuario::documentoValido($docEst) && !Usuario::documentoEnUso($pdo, $docEst, $colegioId, $estudianteId)) {
                Usuario::guardarDocumento($pdo, $estudianteId, $docEst);
            }

            // Acudiente: se reutiliza si ya es acudiente de este colegio; si el correo es de otro rol, se detiene.
            $acudiente = ['nombre' => $i['acudiente_nombre'], 'correo' => $i['acudiente_correo'], 'password' => null, 'nuevo' => false];
            if ($existente) {
                if ((int) $existente['rol_id'] !== $rolAcud || (int) $existente['colegio_id'] !== $colegioId) {
                    throw new RuntimeException('El correo del acudiente ya pertenece a otra cuenta que no es de acudiente en este colegio.');
                }
                $acudienteId = (int) $existente['id'];
            } else {
                $passAcud = self::contrasenaTemporal();
                $pdo->prepare('INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, telefono, consentimiento_datos, fecha_consentimiento, fecha_registro)
                               VALUES (?,?,?,?,?,?,1,NOW(),NOW())')
                    ->execute([$i['acudiente_nombre'], $i['acudiente_correo'], password_hash($passAcud, PASSWORD_DEFAULT), $rolAcud, $colegioId, mb_substr((string) $i['acudiente_telefono'], 0, 20)]);
                $acudienteId = (int) $pdo->lastInsertId();
                $acudiente['password'] = $passAcud;
                $acudiente['nuevo'] = true;
            }
            $pdo->prepare('INSERT IGNORE INTO acudientes_estudiantes (acudiente_id, estudiante_id, parentesco) VALUES (?,?,?)')
                ->execute([$acudienteId, $estudianteId, $i['acudiente_parentesco']]);

            $pdo->prepare("INSERT INTO matriculas (colegio_id, anio_lectivo_id, estudiante_id, grado_id, grupo_id, tipo, estado, inscripcion_id, solicitado_por, resuelto_en)
                           VALUES (?,?,?,?,?, 'nueva', 'activa', ?, ?, NOW())")
                ->execute([$colegioId, (int) $i['anio_lectivo_id'], $estudianteId, (int) $i['grado_id'], $grupoId, $inscripcionId, $usuarioId]);
            $pdo->prepare("UPDATE inscripciones SET estado = 'matriculada', estudiante_id = ?, resuelto_por = ?, resuelto_en = NOW() WHERE id = ?")
                ->execute([$estudianteId, $usuarioId, $inscripcionId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo matricular (no se guardó nada): ' . $e->getMessage()];
        }
        self::asegurarCodigosMatricula($pdo, $colegioId);
        return [
            'error' => '',
            'estudiante' => ['nombre' => $i['nombres'] . ' ' . $i['apellidos'], 'correo' => $correoEst, 'password' => $passEst, 'id' => $estudianteId],
            'acudiente' => $acudiente,
            'aviso' => (int) $anio['actual'] === 1 ? '' : 'El año ' . $anio['anio'] . ' aún no es el actual: el estudiante quedará en su grupo cuando actives ese año lectivo.',
        ];
    }

    /**
     * Número de matrícula (columna «Código» de Seguimiento): consecutivo por colegio, fijo de por vida.
     * Se asigna, en orden de id, a los estudiantes que aún no lo tienen. Es reejecutable y nunca rompe
     * quien lo llama: si la migración 038_codigo_matricula.sql aún no se aplicó, solo deja un aviso en el log.
     */
    public static function asegurarCodigosMatricula(PDO $pdo, int $colegioId): void
    {
        try {
            $pendientes = $pdo->prepare(
                "SELECT u.id FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                 WHERE u.colegio_id = ? AND r.nombre = 'estudiante' AND u.codigo_matricula IS NULL
                 ORDER BY u.id"
            );
            $pendientes->execute([$colegioId]);
            $ids = $pendientes->fetchAll(PDO::FETCH_COLUMN);
            if (!$ids) {
                return;
            }

            $maximo = $pdo->prepare('SELECT COALESCE(MAX(codigo_matricula), 0) FROM usuarios WHERE colegio_id = ?');
            $asignar = $pdo->prepare('UPDATE usuarios SET codigo_matricula = ? WHERE id = ? AND codigo_matricula IS NULL');

            $maximo->execute([$colegioId]);
            $siguiente = (int) $maximo->fetchColumn();

            foreach ($ids as $id) {
                for ($intento = 0; $intento < 3; $intento++) {
                    $siguiente++;
                    try {
                        $asignar->execute([$siguiente, (int) $id]);
                        break;
                    } catch (PDOException $e) {
                        // Índice único (colegio_id, codigo_matricula): otro proceso tomó ese número. Se relee el máximo y se reintenta.
                        $maximo->execute([$colegioId]);
                        $siguiente = (int) $maximo->fetchColumn();
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Academico::asegurarCodigosMatricula: ' . $e->getMessage() . ' (¿falta aplicar sql/migraciones/038_codigo_matricula.sql?)');
        }
    }

    // ------------------------------------------------------------------
    // Matrículas y renovación
    // ------------------------------------------------------------------

    public static function matriculas(PDO $pdo, int $colegioId, int $anioId, string $estado, string $busqueda): array
    {
        $sql = 'SELECT m.*, u.nombre AS estudiante, u.correo, g.nombre AS grado, gp.nombre AS grupo
                  FROM matriculas m
                  INNER JOIN usuarios u ON u.id = m.estudiante_id
                  INNER JOIN grados g ON g.id = m.grado_id
                  LEFT JOIN grupos gp ON gp.id = m.grupo_id
                 WHERE m.colegio_id = ? AND m.anio_lectivo_id = ?';
        $p = [$colegioId, $anioId];
        if (isset(self::ESTADOS_MATRICULA[$estado])) {
            $sql .= ' AND m.estado = ?';
            $p[] = $estado;
        }
        if (trim($busqueda) !== '') {
            $sql .= ' AND (u.nombre LIKE ? OR u.correo LIKE ?)';
            $p[] = '%' . trim($busqueda) . '%';
            $p[] = '%' . trim($busqueda) . '%';
        }
        $sql .= " ORDER BY FIELD(m.estado, 'pendiente', 'activa', 'retirada', 'rechazada'), g.nombre, gp.nombre, u.nombre LIMIT 500";
        $q = $pdo->prepare($sql);
        $q->execute($p);
        return $q->fetchAll();
    }

    /** Confirmar (con grupo), rechazar o retirar una matrícula. Devuelve un error o ''. */
    public static function resolverMatricula(PDO $pdo, int $id, int $colegioId, string $estado, ?int $grupoId): string
    {
        if (!in_array($estado, ['activa', 'rechazada', 'retirada'], true)) {
            return 'Estado no válido.';
        }
        $q = $pdo->prepare('SELECT m.*, a.actual FROM matriculas m INNER JOIN anios_lectivos a ON a.id = m.anio_lectivo_id WHERE m.id = ? AND m.colegio_id = ?');
        $q->execute([$id, $colegioId]);
        $m = $q->fetch();
        if (!$m) {
            return 'La matrícula no existe.';
        }
        $grupoFinal = $m['grupo_id'] !== null ? (int) $m['grupo_id'] : null;
        if ($grupoId !== null && $grupoId > 0) {
            $g = $pdo->prepare('SELECT g.grado_id FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE g.id = ? AND gr.colegio_id = ?');
            $g->execute([$grupoId, $colegioId]);
            $gg = $g->fetchColumn();
            if ($gg === false || (int) $gg !== (int) $m['grado_id']) {
                return 'El grupo debe ser del mismo grado de la matrícula.';
            }
            $grupoFinal = $grupoId;
        }
        $pdo->prepare('UPDATE matriculas SET estado = ?, grupo_id = ?, resuelto_en = NOW() WHERE id = ? AND colegio_id = ?')
            ->execute([$estado, $grupoFinal, $id, $colegioId]);
        // SIMAT: un retiro queda como novedad pendiente de reportar (no interfiere si el módulo aún no está instalado).
        if ($estado === 'retirada' && (string) $m['estado'] !== 'retirada' && is_file(__DIR__ . '/Simat.php')) {
            require_once __DIR__ . '/Simat.php';
            Simat::retiroAutomatico($pdo, $id, $colegioId, (int) ($_SESSION['usuario_id'] ?? 0));
        }
        // Si es el año en curso y la matrícula quedó activa con grupo, el estudiante pasa a ese grupo ahora.
        if ($estado === 'activa' && $grupoFinal !== null && (int) $m['actual'] === 1) {
            $pdo->prepare('UPDATE usuarios SET grupo_id = ? WHERE id = ? AND colegio_id = ?')->execute([$grupoFinal, (int) $m['estudiante_id'], $colegioId]);
        }
        return '';
    }

    /** Estudiantes activos del colegio que aún no tienen matrícula en el año (candidatos a renovar), con su grado actual. */
    public static function elegiblesRenovacion(PDO $pdo, int $colegioId, int $anioId): array
    {
        $q = $pdo->prepare("SELECT u.id, u.nombre, u.correo, gr.id AS grado_id, gr.nombre AS grado, gp.nombre AS grupo
                              FROM usuarios u
                              INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
                              LEFT JOIN grupos gp ON gp.id = u.grupo_id
                              LEFT JOIN grados gr ON gr.id = gp.grado_id
                             WHERE u.colegio_id = ? AND u.activo = 1
                               AND NOT EXISTS (SELECT 1 FROM matriculas m WHERE m.anio_lectivo_id = ? AND m.estudiante_id = u.id)
                          ORDER BY gr.nombre, gp.nombre, u.nombre LIMIT 1000");
        $q->execute([$colegioId, $anioId]);
        return $q->fetchAll();
    }

    /**
     * Renovación directa por la secretaría: $items = [estudianteId => gradoId]. Crea matrículas ACTIVAS (sin grupo).
     * @return array{creadas: int, omitidas: int}
     */
    public static function renovar(PDO $pdo, int $colegioId, int $anioId, array $items, int $usuarioId): array
    {
        $res = ['creadas' => 0, 'omitidas' => 0];
        if (self::anio($pdo, $anioId, $colegioId) === null) {
            return $res;
        }
        $esEst = $pdo->prepare("SELECT 1 FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante' WHERE u.id = ? AND u.colegio_id = ?");
        $esGrado = $pdo->prepare('SELECT 1 FROM grados WHERE id = ? AND colegio_id = ?');
        $ins = $pdo->prepare("INSERT IGNORE INTO matriculas (colegio_id, anio_lectivo_id, estudiante_id, grado_id, tipo, estado, solicitado_por, resuelto_en)
                              VALUES (?,?,?,?, 'renovacion', 'activa', ?, NOW())");
        foreach ($items as $estId => $gradoId) {
            $estId = (int) $estId;
            $gradoId = (int) $gradoId;
            $esEst->execute([$estId, $colegioId]);
            $esGrado->execute([$gradoId, $colegioId]);
            if (!$esEst->fetchColumn() || !$esGrado->fetchColumn()) {
                $res['omitidas']++;
                continue;
            }
            $ins->execute([$colegioId, $anioId, $estId, $gradoId, $usuarioId]);
            $ins->rowCount() > 0 ? $res['creadas']++ : $res['omitidas']++;
        }
        return $res;
    }

    // ------------------------------------------------------------------
    // Acudiente: sus hijos y la solicitud de renovación
    // ------------------------------------------------------------------

    /** Hijos vinculados al acudiente, con su colegio y su grado/grupo actual. */
    public static function hijos(PDO $pdo, int $acudienteId): array
    {
        $q = $pdo->prepare('SELECT u.id, u.nombre, u.colegio_id, c.nombre AS colegio, ae.parentesco,
                                   gr.id AS grado_id, gr.nombre AS grado, gp.nombre AS grupo
                              FROM acudientes_estudiantes ae
                              INNER JOIN usuarios u ON u.id = ae.estudiante_id
                              INNER JOIN colegios c ON c.id = u.colegio_id
                              LEFT JOIN grupos gp ON gp.id = u.grupo_id
                              LEFT JOIN grados gr ON gr.id = gp.grado_id
                             WHERE ae.acudiente_id = ? AND u.activo = 1 ORDER BY u.nombre');
        $q->execute([$acudienteId]);
        return $q->fetchAll();
    }

    public static function matriculasDeEstudiante(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare('SELECT m.id, m.estado, m.tipo, a.anio, g.nombre AS grado, gp.nombre AS grupo
                              FROM matriculas m
                              INNER JOIN anios_lectivos a ON a.id = m.anio_lectivo_id
                              INNER JOIN grados g ON g.id = m.grado_id
                              LEFT JOIN grupos gp ON gp.id = m.grupo_id
                             WHERE m.estudiante_id = ? ORDER BY a.anio DESC');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    public static function anioRenovacionAbierta(PDO $pdo, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT * FROM anios_lectivos WHERE colegio_id = ? AND renovacion_abierta = 1 ORDER BY anio DESC LIMIT 1');
        $q->execute([$colegioId]);
        return $q->fetch() ?: null;
    }

    /** El acudiente pide renovar la matrícula de su hijo. Queda PENDIENTE hasta que la secretaría la confirme. Devuelve un error o ''. */
    public static function solicitarRenovacion(PDO $pdo, int $acudienteId, int $estudianteId, int $gradoId): string
    {
        $q = $pdo->prepare('SELECT u.colegio_id FROM acudientes_estudiantes ae INNER JOIN usuarios u ON u.id = ae.estudiante_id
                             WHERE ae.acudiente_id = ? AND ae.estudiante_id = ? AND u.activo = 1');
        $q->execute([$acudienteId, $estudianteId]);
        $colegioId = $q->fetchColumn();
        if ($colegioId === false) {
            return 'Ese estudiante no está vinculado a tu cuenta.';
        }
        $colegioId = (int) $colegioId;
        $anio = self::anioRenovacionAbierta($pdo, $colegioId);
        if ($anio === null) {
            return 'La renovación de matrícula no está abierta en este momento.';
        }
        $g = $pdo->prepare('SELECT 1 FROM grados WHERE id = ? AND colegio_id = ?');
        $g->execute([$gradoId, $colegioId]);
        if (!$g->fetchColumn()) {
            return 'Elige el grado para el que se solicita la matrícula.';
        }
        $ins = $pdo->prepare("INSERT IGNORE INTO matriculas (colegio_id, anio_lectivo_id, estudiante_id, grado_id, tipo, estado, solicitado_por)
                              VALUES (?,?,?,?, 'renovacion', 'pendiente', ?)");
        $ins->execute([$colegioId, (int) $anio['id'], $estudianteId, $gradoId, $acudienteId]);
        return $ins->rowCount() > 0 ? '' : 'Ya existe una matrícula (o solicitud) de este estudiante para ' . $anio['anio'] . '.';
    }

    // ------------------------------------------------------------------
    // Resumen para el panel de la secretaría académica
    // ------------------------------------------------------------------

    public static function resumen(PDO $pdo, int $colegioId): array
    {
        $uno = static function (PDO $pdo, string $sql, array $p): int {
            $q = $pdo->prepare($sql);
            $q->execute($p);
            return (int) $q->fetchColumn();
        };
        $actual = self::anioActual($pdo, $colegioId);
        return [
            'anio' => $actual,
            'inscripciones_por_revisar' => $uno($pdo, "SELECT COUNT(*) FROM inscripciones WHERE colegio_id = ? AND estado IN ('pendiente','en_revision')", [$colegioId]),
            'inscripciones_admitidas' => $uno($pdo, "SELECT COUNT(*) FROM inscripciones WHERE colegio_id = ? AND estado = 'admitida'", [$colegioId]),
            'matriculas_activas' => $actual ? $uno($pdo, "SELECT COUNT(*) FROM matriculas WHERE colegio_id = ? AND anio_lectivo_id = ? AND estado = 'activa'", [$colegioId, (int) $actual['id']]) : 0,
            'renovaciones_pendientes' => $uno($pdo, "SELECT COUNT(*) FROM matriculas WHERE colegio_id = ? AND estado = 'pendiente'", [$colegioId]),
        ];
    }
}
