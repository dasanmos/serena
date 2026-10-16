<?php
declare(strict_types=1);

require_once __DIR__ . '/Academico.php';
require_once __DIR__ . '/Evaluacion.php';
require_once __DIR__ . '/Expediente.php';
require_once __DIR__ . '/Certificados.php';

/** Error «de negocio» de un informe: el mensaje se muestra tal cual a quien lo pidió. */
final class InformeError extends RuntimeException
{
}

/**
 * Núcleo del módulo de informes.
 *
 * Un informe = una entrada del catálogo (src/informes/catalogo.php) + un generador (src/informes/Gen*.php).
 * El generador NO dibuja nada: devuelve una estructura estándar y los tres formatos (HTML imprimible, PDF y CSV)
 * se pintan a partir de ella con los datos institucionales de la institución (logo, DANE, resolución, firmas).
 *
 * Estructura que devuelve un generador:
 *   ['titulo' => ?, 'subtitulo' => ?, 'orient' => 'v'|'h', 'bloques' => [ ... ]]
 * Bloques:
 *   ['t' => 'tabla',  'titulo' => ?, 'enc' => [..], 'filas' => [[..]], 'alin' => 'lccr', 'pie' => [..]?, 'anchos' => [..]?]
 *   ['t' => 'pares',  'titulo' => ?, 'items' => [[clave, valor], ...]]
 *   ['t' => 'texto',  'titulo' => ?, 'texto' => '...']
 *   ['t' => 'nota',   'texto' => '...']           (letra pequeña: aclaraciones y avisos)
 *   ['t' => 'banda',  'texto' => '...']           (encabezado de sección, p. ej. el nombre de cada estudiante)
 *   ['t' => 'firmas', 'items' => [[nombre, cargo], ...]]
 *   ['t' => 'salto']                              (nueva página)
 *
 * Alcance: todo se consulta con el colegio de la sesión; el docente solo ve sus grupos.
 */
final class Informes
{
    public const ROL_TODOS = ['secretaria_academica', 'administrador', 'rector', 'coordinador', 'docente'];
    public const ROL_DIRECTIVOS = ['secretaria_academica', 'administrador', 'rector', 'coordinador'];
    public const ROL_SECRETARIA = ['secretaria_academica', 'administrador', 'rector'];
    public const ROL_ADMIN = ['administrador', 'rector'];
    public const ROL_SENSIBLE = ['administrador', 'rector', 'coordinador', 'psicologo', 'orientador'];
    public const ROL_SEGUIMIENTO = ['secretaria_academica', 'administrador', 'rector', 'coordinador', 'docente', 'psicologo', 'orientador'];

    public const MESES = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

    private static ?array $catalogo = null;
    /** @var array<string,bool> */
    private static array $tablas = [];
    /** @var array<string,array> */
    private static array $cacheRoster = [];

    // ------------------------------------------------------------------
    // Catálogo y permisos
    // ------------------------------------------------------------------

    public static function catalogo(): array
    {
        if (self::$catalogo === null) {
            self::$catalogo = require __DIR__ . '/../informes/catalogo.php';
        }
        return self::$catalogo;
    }

    public static function definicion(string $clave): ?array
    {
        $c = self::catalogo()['informes'];
        return isset($c[$clave]) ? $c[$clave] + ['clave' => $clave] : null;
    }

    public static function permitido(array $def, string $rol): bool
    {
        return in_array($rol, $def['roles'], true);
    }

    /** Roles que pueden entrar al módulo (unión de los roles de todos los informes). */
    public static function rolesDelModulo(): array
    {
        $r = [];
        foreach (self::catalogo()['informes'] as $d) {
            foreach ($d['roles'] as $x) {
                $r[$x] = true;
            }
        }
        return array_keys($r);
    }

    /** @return array<int, array{nombre:string, desc:string, informes: array<string,array>}> categorías con los informes que ese rol puede abrir */
    public static function visibles(string $rol): array
    {
        $cat = self::catalogo();
        $out = [];
        foreach ($cat['categorias'] as $n => $c) {
            $out[$n] = ['nombre' => $c['nombre'], 'desc' => $c['desc'], 'informes' => []];
        }
        foreach ($cat['informes'] as $clave => $d) {
            if (self::permitido($d, $rol)) {
                $out[$d['cat']]['informes'][$clave] = $d + ['clave' => $clave];
            }
        }
        return array_filter($out, static fn (array $c): bool => $c['informes'] !== []);
    }

    public static function tablaExiste(PDO $pdo, string $tabla): bool
    {
        if (!isset(self::$tablas[$tabla])) {
            try {
                $q = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
                $q->execute([$tabla]);
                self::$tablas[$tabla] = (bool) $q->fetchColumn();
            } catch (PDOException $e) {
                self::$tablas[$tabla] = false;
            }
        }
        return self::$tablas[$tabla];
    }

    /** Falla con un mensaje claro si falta alguna tabla. */
    public static function exigirTablas(PDO $pdo, array $tablas): void
    {
        foreach ($tablas as $t) {
            if (!self::tablaExiste($pdo, $t)) {
                throw new InformeError("Este informe necesita la tabla «{$t}». Aplica sql/migraciones/038_informes.sql (y las anteriores).");
            }
        }
    }

    // ------------------------------------------------------------------
    // Utilidades de formato
    // ------------------------------------------------------------------

    /** Lista de enteros lista para un IN (...). Vacía → «0» para que no devuelva nada. */
    public static function in(array $ids): string
    {
        $l = array_values(array_unique(array_map('intval', $ids)));
        return $l ? implode(',', $l) : '0';
    }

    public static function fecha(?string $f): string
    {
        $f = (string) $f;
        if (strlen($f) < 10) {
            return $f === '' ? '—' : $f;
        }
        return substr($f, 8, 2) . '/' . substr($f, 5, 2) . '/' . substr($f, 0, 4);
    }

    public static function moneda(float $v): string
    {
        return '$ ' . number_format($v, 0, ',', '.');
    }

    public static function pct(float $parte, float $total, int $dec = 1): string
    {
        return $total > 0 ? number_format($parte * 100 / $total, $dec, ',', '') . ' %' : '—';
    }

    public static function nota(array $ctx, ?float $v): string
    {
        return $v === null ? '—' : number_format($v, (int) ($ctx['escala']['decimales'] ?? 1), '.', '');
    }

    public static function edad(?string $nacimiento): string
    {
        if (!$nacimiento || strlen($nacimiento) < 10) {
            return '';
        }
        try {
            return (string) (new DateTimeImmutable($nacimiento))->diff(new DateTimeImmutable('today'))->y;
        } catch (Exception $e) {
            return '';
        }
    }

    public static function si(mixed $v): string
    {
        return (int) $v === 1 ? 'Sí' : 'No';
    }

    /** Cortar texto largo con «…». */
    public static function corto(?string $t, int $max): string
    {
        $t = trim(preg_replace('/\s+/', ' ', (string) $t) ?? '');
        return mb_strlen($t, 'UTF-8') > $max ? mb_substr($t, 0, $max - 1, 'UTF-8') . '…' : $t;
    }

    // ------------------------------------------------------------------
    // Contexto y filtros
    // ------------------------------------------------------------------

    public static function contexto(PDO $pdo, int $colegioId, string $rol, int $usuarioId): array
    {
        $perm = null;
        if ($rol === 'docente') {
            $q = $pdo->prepare('SELECT DISTINCT a.grupo_id AS id FROM asignaciones_academicas a WHERE a.docente_id = ? AND a.colegio_id = ?
                                UNION SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE g.docente_id = ? AND gr.colegio_id = ?');
            $q->execute([$usuarioId, $colegioId, $usuarioId, $colegioId]);
            $perm = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        }
        $escala = null;
        try {
            $escala = Evaluacion::escalaActiva($pdo, $colegioId);
        } catch (PDOException $e) {
            $escala = null;
        }
        return [
            'colegioId' => $colegioId, 'rol' => $rol, 'usuarioId' => $usuarioId,
            'ins' => Certificados::institucion($pdo, $colegioId),
            'escala' => $escala, 'cap' => Expediente::capacidades($rol), 'gruposPermitidos' => $perm,
        ];
    }

    /** Grupos que esta persona puede consultar. */
    public static function grupos(PDO $pdo, array $ctx): array
    {
        $todos = Academico::grupos($pdo, $ctx['colegioId']);
        $perm = $ctx['gruposPermitidos'];
        return $perm === null ? $todos : array_values(array_filter($todos, static fn (array $g): bool => in_array((int) $g['id'], $perm, true)));
    }

    /** Asignaciones del año que esta persona puede consultar (el docente, solo las suyas). */
    public static function asignaciones(PDO $pdo, array $ctx, int $anioId): array
    {
        return Evaluacion::asignaciones($pdo, $ctx['colegioId'], $anioId, $ctx['rol'] === 'docente' ? $ctx['usuarioId'] : null, null);
    }

    public static function docentes(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare("SELECT u.id, u.nombre, u.correo, u.telefono FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'docente'
                             WHERE u.colegio_id = ? AND u.activo = 1 ORDER BY u.nombre");
        $q->execute([$colegioId]);
        return $q->fetchAll();
    }

    private static function fechaOk(string $f): bool
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $f);
        return $d !== false && $d->format('Y-m-d') === $f;
    }

    /**
     * Lee y VALIDA los filtros que ese informe usa. Todo identificador se comprueba contra el colegio y el alcance del rol.
     * @return array<string,mixed>
     */
    public static function filtros(PDO $pdo, array $ctx, array $def, array $in): array
    {
        $col = $ctx['colegioId'];
        $usa = $def['filtros'];
        $f = ['anio' => 0, 'anio_row' => null, 'periodo' => 0, 'periodo_row' => null, 'grado' => 0, 'grupo' => 0, 'estudiante' => 0, 'asignacion' => 0, 'docente' => 0,
            'desde' => '', 'hasta' => '', 'umbral' => null, 'estado' => '', 'solo_pub' => false, 'tipo_cert' => '', 'mes' => 0, 'txt' => []];

        $anio = null;
        $anioId = (int) ($in['anio'] ?? 0);
        if ($anioId > 0) {
            $anio = Academico::anio($pdo, $anioId, $col);
            if ($anio === null) {
                throw new InformeError('El año lectivo elegido no existe en este colegio.');
            }
        } else {
            $anio = Academico::anioActual($pdo, $col);
            if ($anio === null) {
                $lista = Academico::anios($pdo, $col);
                $anio = $lista[0] ?? null;
            }
        }
        if ($anio === null) {
            throw new InformeError('Primero crea un año lectivo (menú «Años lectivos»).');
        }
        $f['anio'] = (int) $anio['id'];
        $f['anio_row'] = $anio;
        $f['txt']['anio'] = 'Año lectivo ' . $anio['anio'];

        if (in_array('periodo', $usa, true) && (int) ($in['periodo'] ?? 0) > 0) {
            $p = Evaluacion::periodo($pdo, (int) $in['periodo'], $col);
            if ($p === null || (int) $p['anio_lectivo_id'] !== $f['anio']) {
                throw new InformeError('El período elegido no pertenece a ese año lectivo.');
            }
            $f['periodo'] = (int) $p['id'];
            $f['periodo_row'] = $p;
            $f['txt']['periodo'] = (string) $p['nombre'];
        }
        if (in_array('grado', $usa, true) && (int) ($in['grado'] ?? 0) > 0) {
            foreach (Academico::grados($pdo, $col) as $g) {
                if ((int) $g['id'] === (int) $in['grado']) {
                    $f['grado'] = (int) $g['id'];
                    $f['txt']['grado'] = 'Grado ' . $g['nombre'];
                }
            }
            if ($f['grado'] === 0) {
                throw new InformeError('El grado elegido no existe.');
            }
        }
        if (in_array('grupo', $usa, true) && (int) ($in['grupo'] ?? 0) > 0) {
            foreach (self::grupos($pdo, $ctx) as $g) {
                if ((int) $g['id'] === (int) $in['grupo']) {
                    $f['grupo'] = (int) $g['id'];
                    $f['txt']['grupo'] = 'Grupo ' . $g['grado'] . ' ' . $g['nombre'];
                }
            }
            if ($f['grupo'] === 0) {
                throw new InformeError('El grupo elegido no existe o no tienes acceso a él.');
            }
        }
        if (in_array('estudiante', $usa, true) && (int) ($in['estudiante'] ?? 0) > 0) {
            $eid = (int) $in['estudiante'];
            if (!Expediente::puedeAccederEstudiante($pdo, $ctx['rol'], $ctx['usuarioId'], $eid, $col)) {
                throw new InformeError('No tienes acceso a ese estudiante.');
            }
            $b = Expediente::estudianteBasico($pdo, $eid, $col);
            $f['estudiante'] = $eid;
            $f['txt']['estudiante'] = (string) ($b['nombre'] ?? '');
        }
        if (in_array('asignacion', $usa, true) && (int) ($in['asignacion'] ?? 0) > 0) {
            foreach (self::asignaciones($pdo, $ctx, $f['anio']) as $a) {
                if ((int) $a['id'] === (int) $in['asignacion']) {
                    $f['asignacion'] = (int) $a['id'];
                    $f['txt']['asignacion'] = $a['asignatura'] . ' · ' . $a['grado'] . ' ' . $a['grupo'];
                }
            }
            if ($f['asignacion'] === 0) {
                throw new InformeError('La asignatura elegida no existe en ese año o no es tuya.');
            }
        }
        if (in_array('docente', $usa, true) && (int) ($in['docente'] ?? 0) > 0) {
            foreach (self::docentes($pdo, $col) as $d) {
                if ((int) $d['id'] === (int) $in['docente']) {
                    $f['docente'] = (int) $d['id'];
                    $f['txt']['docente'] = (string) $d['nombre'];
                }
            }
            if ($f['docente'] === 0) {
                throw new InformeError('El docente elegido no existe.');
            }
        }
        if ($ctx['rol'] === 'docente' && in_array('docente', $usa, true)) {
            $f['docente'] = $ctx['usuarioId'];
        }
        foreach (['desde', 'hasta'] as $k) {
            if (in_array($k, $usa, true)) {
                $v = trim((string) ($in[$k] ?? ''));
                if ($v !== '' && !self::fechaOk($v)) {
                    throw new InformeError('Fecha no válida en «' . $k . '».');
                }
                $f[$k] = $v;
            }
        }
        if ($f['desde'] !== '' && $f['hasta'] !== '' && $f['desde'] > $f['hasta']) {
            throw new InformeError('La fecha «desde» no puede ser posterior a «hasta».');
        }
        if ($f['desde'] !== '' || $f['hasta'] !== '') {
            $f['txt']['rango'] = 'Del ' . ($f['desde'] !== '' ? self::fecha($f['desde']) : 'inicio') . ' al ' . ($f['hasta'] !== '' ? self::fecha($f['hasta']) : 'hoy');
        }
        if (in_array('umbral', $usa, true)) {
            $u = str_replace(',', '.', trim((string) ($in['umbral'] ?? '')));
            $f['umbral'] = ($u !== '' && is_numeric($u)) ? (float) $u : null;
        }
        if (in_array('estado', $usa, true)) {
            $e = (string) ($in['estado'] ?? '');
            $f['estado'] = preg_match('/^[a-z_]{1,20}$/', $e) === 1 && isset(($def['estados'] ?? [])[$e]) ? $e : '';
            if ($f['estado'] !== '') {
                $f['txt']['estado'] = (string) $def['estados'][$f['estado']];
            }
        }
        if (in_array('solo_pub', $usa, true)) {
            $f['solo_pub'] = !empty($in['solo_pub']);
        }
        if (in_array('tipo_cert', $usa, true)) {
            $t = (string) ($in['tipo_cert'] ?? '');
            $f['tipo_cert'] = isset(Certificados::TIPOS[$t]) ? $t : '';
        }
        if (in_array('mes', $usa, true)) {
            $m = (int) ($in['mes'] ?? 0);
            $f['mes'] = ($m >= 1 && $m <= 12) ? $m : 0;
            if ($f['mes'] > 0) {
                $f['txt']['mes'] = self::MESES[$f['mes']];
            }
        }
        return $f;
    }

    // ------------------------------------------------------------------
    // Estudiantes del año y matriz de notas (base de los informes académicos)
    // ------------------------------------------------------------------

    /**
     * Estudiantes del año elegido (matrícula activa; en el año actual, también los activos sin matrícula del grupo),
     * respetando grupo/grado/estudiante y el alcance del rol.
     * @return array<int, array<string,mixed>>
     */
    public static function roster(PDO $pdo, array $ctx, array $f): array
    {
        $clave = md5(json_encode([$ctx['colegioId'], $ctx['rol'], $ctx['usuarioId'], $f['anio'], $f['grupo'], $f['grado'], $f['estudiante']]));
        if (isset(self::$cacheRoster[$clave])) {
            return self::$cacheRoster[$clave];
        }
        $col = $ctx['colegioId'];
        $anio = $f['anio_row'];
        $perm = $ctx['gruposPermitidos'];
        $res = [];

        $w = '';
        $p = [$col, $f['anio']];
        if ($f['grupo'] > 0) {
            $w .= ' AND m.grupo_id = ?';
            $p[] = $f['grupo'];
        }
        if ($f['grado'] > 0) {
            $w .= ' AND m.grado_id = ?';
            $p[] = $f['grado'];
        }
        if ($f['estudiante'] > 0) {
            $w .= ' AND u.id = ?';
            $p[] = $f['estudiante'];
        }
        if ($perm !== null) {
            $w .= ' AND m.grupo_id IN (' . self::in($perm) . ')';
        }
        $q = $pdo->prepare("SELECT u.id, u.nombre, u.correo, u.telefono, u.fecha_nacimiento, m.grupo_id, g.nombre AS grupo, gr.id AS grado_id, gr.nombre AS grado
                              FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id
                              INNER JOIN grados gr ON gr.id = m.grado_id LEFT JOIN grupos g ON g.id = m.grupo_id
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'activa' AND u.activo = 1" . $w);
        $q->execute($p);
        foreach ($q->fetchAll() as $r) {
            $res[(int) $r['id']] = $r;
        }

        if ((int) $anio['actual'] === 1) {
            $w2 = '';
            $p2 = [$col, $f['anio']];
            if ($f['grupo'] > 0) {
                $w2 .= ' AND g.id = ?';
                $p2[] = $f['grupo'];
            }
            if ($f['grado'] > 0) {
                $w2 .= ' AND gr.id = ?';
                $p2[] = $f['grado'];
            }
            if ($f['estudiante'] > 0) {
                $w2 .= ' AND u.id = ?';
                $p2[] = $f['estudiante'];
            }
            if ($perm !== null) {
                $w2 .= ' AND g.id IN (' . self::in($perm) . ')';
            }
            $q2 = $pdo->prepare("SELECT u.id, u.nombre, u.correo, u.telefono, u.fecha_nacimiento, g.id AS grupo_id, g.nombre AS grupo, gr.id AS grado_id, gr.nombre AS grado
                                   FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
                                   INNER JOIN grupos g ON g.id = u.grupo_id INNER JOIN grados gr ON gr.id = g.grado_id
                                  WHERE u.colegio_id = ? AND u.activo = 1
                                    AND NOT EXISTS (SELECT 1 FROM matriculas mm WHERE mm.estudiante_id = u.id AND mm.anio_lectivo_id = ?)" . $w2);
            $q2->execute($p2);
            foreach ($q2->fetchAll() as $r) {
                $res[(int) $r['id']] = $r;
            }
        }

        foreach ($res as &$r) {
            $r['grupo_id'] = $r['grupo_id'] !== null ? (int) $r['grupo_id'] : 0;
            $r['grupo'] = (string) ($r['grupo'] ?? '');
            $r['doc'] = '';
            $r['doc_tipo'] = '';
            $r['doc_num'] = '';
            $r['genero'] = '';
        }
        unset($r);
        if ($res && self::tablaExiste($pdo, 'hojas_vida')) {
            $h = $pdo->query('SELECT estudiante_id, tipo_documento, numero_documento, genero FROM hojas_vida WHERE estudiante_id IN (' . self::in(array_keys($res)) . ')');
            foreach ($h->fetchAll() as $x) {
                $i = (int) $x['estudiante_id'];
                $res[$i]['doc_tipo'] = (string) ($x['tipo_documento'] ?? '');
                $res[$i]['doc_num'] = (string) ($x['numero_documento'] ?? '');
                $res[$i]['doc'] = trim($res[$i]['doc_tipo'] . ' ' . $res[$i]['doc_num']);
                $res[$i]['genero'] = (string) ($x['genero'] ?? '');
            }
        }
        $lista = array_values($res);
        usort($lista, static fn (array $a, array $b): int => strnatcasecmp($a['grado'] . ' ' . $a['grupo'], $b['grado'] . ' ' . $b['grupo']) ?: strcasecmp((string) $a['nombre'], (string) $b['nombre']));
        return self::$cacheRoster[$clave] = $lista;
    }

    /** Promedio ponderado por intensidad horaria si TODAS la tienen; si no, simple. @param array<int,array{0:float,1:float}> $pares */
    public static function promedio(array $pares): ?float
    {
        if (!$pares) {
            return null;
        }
        $todas = !in_array(0.0, array_column($pares, 1), true);
        if ($todas) {
            $pesos = array_sum(array_column($pares, 1));
            $s = 0.0;
            foreach ($pares as [$v, $w]) {
                $s += $v * $w;
            }
            return $pesos > 0 ? $s / $pesos : null;
        }
        return array_sum(array_column($pares, 0)) / count($pares);
    }

    public static function nivel(array $escala, ?float $v): ?array
    {
        return $v === null ? null : Evaluacion::nivelParaValor($escala['niveles'], $v);
    }

    /** true = aprueba, false = pierde, null = sin nota. */
    public static function aprueba(array $escala, ?float $v): ?bool
    {
        $n = self::nivel($escala, $v);
        return $n === null ? null : (int) $n['aprueba'] === 1;
    }

    /** Menor valor que aprueba (para umbrales por defecto). */
    public static function minimoAprobatorio(array $escala): float
    {
        $min = null;
        foreach ($escala['niveles'] as $n) {
            if ((int) $n['aprueba'] === 1 && ($min === null || (float) $n['desde'] < $min)) {
                $min = (float) $n['desde'];
            }
        }
        return $min ?? (float) $escala['valor_min'];
    }

    /**
     * Notas del año/período para todos los estudiantes del alcance.
     * 'val' = nota analizada (la del período elegido o, sin período, la definitiva anual); 'n' = notas por período.
     * @return array<string,mixed>
     */
    public static function matriz(PDO $pdo, array $ctx, array $f, ?array $roster = null): array
    {
        if ($ctx['escala'] === null) {
            throw new InformeError('El colegio aún no tiene un sistema de evaluación configurado (Administración → Evaluación).');
        }
        $roster ??= self::roster($pdo, $ctx, $f);
        $escala = $ctx['escala'];
        $dec = (int) $escala['decimales'];
        $periodos = Academico::periodos($pdo, $f['anio']);
        $asigs = [];
        $porGrupo = [];
        foreach (Evaluacion::asignaciones($pdo, $ctx['colegioId'], $f['anio']) as $a) {
            $asigs[(int) $a['id']] = $a;
            $porGrupo[(int) $a['grupo_id']][] = $a;
        }
        $notas = [];
        if ($roster && $asigs) {
            $q = $pdo->query('SELECT asignacion_id, periodo_id, estudiante_id, valor FROM notas_periodo
                               WHERE valor IS NOT NULL AND estudiante_id IN (' . self::in(array_column($roster, 'id')) . ') AND asignacion_id IN (' . self::in(array_keys($asigs)) . ')');
            foreach ($q->fetchAll() as $r) {
                $notas[(int) $r['estudiante_id']][(int) $r['asignacion_id']][(int) $r['periodo_id']] = (float) $r['valor'];
            }
        }
        $n = [];
        $val = [];
        $prom = [];
        $promP = [];
        foreach ($roster as $r) {
            $e = (int) $r['id'];
            $pares = [];
            $paresP = [];
            foreach ($porGrupo[(int) $r['grupo_id']] ?? [] as $a) {
                $aid = (int) $a['id'];
                $porP = [];
                foreach ($periodos as $p) {
                    $v = $notas[$e][$aid][(int) $p['id']] ?? null;
                    $n[$e][$aid][(int) $p['id']] = $v;
                    $porP[(int) $p['id']] = $v;
                    if ($v !== null) {
                        $paresP[(int) $p['id']][] = [$v, (float) ($a['intensidad'] ?? 0)];
                    }
                }
                $v = $f['periodo'] > 0 ? ($porP[$f['periodo']] ?? null) : Evaluacion::definitiva($porP, $periodos);
                $v = $v !== null ? round($v, $dec) : null;
                $val[$e][$aid] = $v;
                if ($v !== null) {
                    $pares[] = [$v, (float) ($a['intensidad'] ?? 0)];
                }
            }
            $pr = self::promedio($pares);
            $prom[$e] = $pr !== null ? round($pr, $dec) : null;
            foreach ($paresP as $pid => $pp) {
                $x = self::promedio($pp);
                $promP[$e][$pid] = $x !== null ? round($x, $dec) : null;
            }
        }
        // Puesto dentro del grupo (empates comparten puesto)
        $puesto = [];
        $porG = [];
        foreach ($roster as $r) {
            if ($prom[(int) $r['id']] !== null) {
                $porG[(int) $r['grupo_id']][(int) $r['id']] = $prom[(int) $r['id']];
            }
        }
        foreach ($porG as $lista) {
            arsort($lista);
            $i = 0;
            $ant = null;
            $pos = 0;
            foreach ($lista as $e => $v) {
                $i++;
                if ($ant === null || abs($v - $ant) > 0.00001) {
                    $pos = $i;
                    $ant = $v;
                }
                $puesto[$e] = $pos;
            }
        }
        return ['roster' => $roster, 'periodos' => $periodos, 'asigs' => $asigs, 'porGrupo' => $porGrupo, 'n' => $n, 'val' => $val, 'prom' => $prom, 'promP' => $promP, 'puesto' => $puesto, 'escala' => $escala, 'dec' => $dec];
    }

    /** Asignaturas perdidas por estudiante según 'val': [estudiante][] = [asignacion, valor]. */
    public static function perdidas(array $m): array
    {
        $out = [];
        foreach ($m['roster'] as $r) {
            $e = (int) $r['id'];
            foreach ($m['porGrupo'][(int) $r['grupo_id']] ?? [] as $a) {
                $v = $m['val'][$e][(int) $a['id']] ?? null;
                if (self::aprueba($m['escala'], $v) === false) {
                    $out[$e][] = [$a, $v];
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Generación y bitácora
    // ------------------------------------------------------------------

    /** @return array<string,mixed> estructura estándar (ver cabecera de la clase) */
    public static function generar(PDO $pdo, array $ctx, array $def, array $f): array
    {
        if (!self::permitido($def, $ctx['rol'])) {
            throw new InformeError('Tu rol no tiene acceso a este informe.');
        }
        foreach ($def['req'] ?? [] as $k) {
            $vacio = in_array($k, ['desde', 'hasta'], true) ? $f[$k] === '' : empty($f[$k]);
            if ($vacio) {
                throw new InformeError('Falta elegir: ' . (self::FILTROS[$k] ?? $k) . '.');
            }
        }
        self::exigirTablas($pdo, $def['tablas'] ?? []);
        [$clase, $metodo] = $def['gen'];
        require_once __DIR__ . '/../informes/' . $clase . '.php';
        try {
            $res = $clase::$metodo($pdo, $ctx, $f);
        } catch (PDOException $e) {
            throw new InformeError('No se pudo consultar la información (¿falta aplicar alguna migración?). Detalle: ' . $e->getMessage());
        }
        $res += ['titulo' => $def['nombre'], 'subtitulo' => '', 'orient' => $def['orient'] ?? 'v', 'bloques' => []];
        if ($res['subtitulo'] === '') {
            $res['subtitulo'] = implode(' · ', array_filter([$f['txt']['anio'] ?? '', $f['txt']['periodo'] ?? '', $f['txt']['grado'] ?? '', $f['txt']['grupo'] ?? '', $f['txt']['estudiante'] ?? '',
                $f['txt']['asignacion'] ?? '', $f['txt']['docente'] ?? '', $f['txt']['mes'] ?? '', $f['txt']['rango'] ?? '', $f['txt']['estado'] ?? '']));
        }
        $filas = 0;
        foreach ($res['bloques'] as $b) {
            if (($b['t'] ?? '') === 'tabla') {
                $filas += count($b['filas']);
            }
        }
        $res['filas'] = $filas;
        $res['sensible'] = !empty($def['sensible']);
        return $res;
    }

    /** Etiquetas de los filtros (para mensajes y formularios). */
    public const FILTROS = [
        'anio' => 'Año lectivo', 'periodo' => 'Período', 'grado' => 'Grado', 'grupo' => 'Grupo', 'estudiante' => 'Estudiante', 'asignacion' => 'Asignatura y grupo',
        'docente' => 'Docente', 'desde' => 'Desde', 'hasta' => 'Hasta', 'umbral' => 'Umbral', 'estado' => 'Estado', 'solo_pub' => 'Solo boletines publicados',
        'tipo_cert' => 'Tipo de certificado', 'mes' => 'Mes',
    ];

    /** Deja constancia de qué informe se generó, en qué formato y con qué filtros. Devuelve el id (0 si no se pudo). */
    public static function registrar(PDO $pdo, array $ctx, string $clave, string $formato, array $f, int $filas): int
    {
        try {
            if (!self::tablaExiste($pdo, 'informes_generados')) {
                return 0;
            }
            $filtros = [];
            foreach (['anio', 'periodo', 'grado', 'grupo', 'estudiante', 'asignacion', 'docente', 'desde', 'hasta', 'umbral', 'estado', 'tipo_cert', 'mes'] as $k) {
                if (!empty($f[$k])) {
                    $filtros[$k] = $f[$k];
                }
            }
            $pdo->prepare('INSERT INTO informes_generados (colegio_id, usuario_id, rol, clave, formato, filtros, filas) VALUES (?,?,?,?,?,?,?)')
                ->execute([$ctx['colegioId'], $ctx['usuarioId'], $ctx['rol'], $clave, $formato, json_encode($filtros, JSON_UNESCAPED_UNICODE), $filas]);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /** Últimos informes generados por esta persona (o por todo el colegio si es directivo). */
    public static function recientes(PDO $pdo, array $ctx, int $limite = 8): array
    {
        if (!self::tablaExiste($pdo, 'informes_generados')) {
            return [];
        }
        $sql = 'SELECT i.id, i.clave, i.formato, i.creado_en, u.nombre AS usuario FROM informes_generados i LEFT JOIN usuarios u ON u.id = i.usuario_id WHERE i.colegio_id = ?';
        $p = [$ctx['colegioId']];
        if (!in_array($ctx['rol'], self::ROL_ADMIN, true)) {
            $sql .= ' AND i.usuario_id = ?';
            $p[] = $ctx['usuarioId'];
        }
        $q = $pdo->prepare($sql . ' ORDER BY i.id DESC LIMIT ' . (int) $limite);
        $q->execute($p);
        return $q->fetchAll();
    }
}
