<?php
declare(strict_types=1);

require_once __DIR__ . '/RolesUsuario.php';
require_once __DIR__ . '/Academico.php';

/**
 * Evaluación: escalas (numérica o descriptiva), estructura de la planilla (actividades, procesos, logros,
 * procesos con logros, logros con procesos), asignaturas, asignaciones, planillas de notas, notas de período y boletín.
 * Requiere la migración 035_evaluacion.sql. Todo se aísla por colegio.
 */
class Evaluacion
{
    public const ESTRUCTURAS = [
        'actividades' => ['Por actividades', 'Cada actividad pesa lo que defina el docente (o todas igual). La nota del período es el promedio ponderado.'],
        'procesos' => ['Por procesos', 'Las actividades se agrupan en procesos con peso institucional (p. ej. cognitivo 40 %, procedimental 30 %, actitudinal 30 %).'],
        'logros' => ['Por logros', 'Cada logro del período se evalúa con sus actividades; la nota es el promedio de los logros.'],
        'procesos_logros' => ['Procesos con logros', 'Primero los procesos (con su peso) y, dentro de cada uno, los logros.'],
        'logros_procesos' => ['Logros con procesos', 'Primero los logros y, dentro de cada uno, los procesos que lo evalúan.'],
    ];

    // ------------------------------------------------------------------
    // Cálculo (función pura: se puede probar sin base de datos)
    // ------------------------------------------------------------------

    private static function promedioPonderado(array $pares): float
    {
        $suma = 0.0;
        $sp = 0.0;
        foreach ($pares as [$v, $p]) {
            $suma += $v * $p;
            $sp += $p;
        }
        if ($sp <= 0) {
            return array_sum(array_column($pares, 0)) / count($pares); // sin pesos: todos iguales
        }
        return $suma / $sp;
    }

    /**
     * Nota del período de UN estudiante en UNA asignación.
     *  $actividades: [['id','peso','componente_id','logro_id'], ...]
     *  $califs:      [actividad_id => valor|null]  (las actividades sin nota no cuentan: se reparte el peso entre las demás)
     *  $pesoComp / $pesoLogro: [id => peso] de procesos y de logros.
     * Devuelve null si no hay ninguna nota.
     */
    public static function calcularNota(array $actividades, array $califs, string $estructura, array $pesoComp, array $pesoLogro): ?float
    {
        $arbol = []; // k1 => k2 => [[valor, peso], ...]
        foreach ($actividades as $a) {
            $v = $califs[(int) $a['id']] ?? null;
            if ($v === null) {
                continue;
            }
            $c = (int) ($a['componente_id'] ?? 0);
            $l = (int) ($a['logro_id'] ?? 0);
            switch ($estructura) {
                case 'procesos':
                    $k1 = $c; $k2 = 0; break;
                case 'logros':
                    $k1 = $l; $k2 = 0; break;
                case 'procesos_logros':
                    $k1 = $c; $k2 = $l; break;
                case 'logros_procesos':
                    $k1 = $l; $k2 = $c; break;
                default:
                    $k1 = 0; $k2 = 0;
            }
            $arbol[$k1][$k2][] = [(float) $v, (float) $a['peso']];
        }
        if (!$arbol) {
            return null;
        }
        $peso1 = in_array($estructura, ['procesos', 'procesos_logros'], true) ? $pesoComp
            : (in_array($estructura, ['logros', 'logros_procesos'], true) ? $pesoLogro : []);
        $peso2 = $estructura === 'procesos_logros' ? $pesoLogro : ($estructura === 'logros_procesos' ? $pesoComp : []);
        $nivel1 = [];
        foreach ($arbol as $k1 => $hijos) {
            $nivel2 = [];
            foreach ($hijos as $k2 => $pares) {
                $nivel2[] = [self::promedioPonderado($pares), (float) ($peso2[$k2] ?? 0)];
            }
            $nivel1[] = [self::promedioPonderado($nivel2), (float) ($peso1[$k1] ?? 0)];
        }
        return self::promedioPonderado($nivel1);
    }

    /** Nivel de desempeño que corresponde a un valor (rangos desde-hasta). */
    public static function nivelParaValor(array $niveles, float $valor): ?array
    {
        foreach ($niveles as $n) {
            if ($valor >= (float) $n['desde'] - 0.0001 && $valor <= (float) $n['hasta'] + 0.0001) {
                return $n;
            }
        }
        // Huecos entre rangos: el nivel más alto cuyo «desde» no supere el valor
        $mejor = null;
        foreach ($niveles as $n) {
            if ((float) $n['desde'] <= $valor && ($mejor === null || (float) $n['desde'] > (float) $mejor['desde'])) {
                $mejor = $n;
            }
        }
        return $mejor ?? ($niveles ? $niveles[count($niveles) - 1] : null);
    }

    /** Promedio de períodos ponderado por el peso de cada período (solo los que tienen nota). */
    public static function definitiva(array $notasPorPeriodo, array $periodos): ?float
    {
        $pares = [];
        foreach ($periodos as $p) {
            $v = $notasPorPeriodo[(int) $p['id']] ?? null;
            if ($v !== null) {
                $pares[] = [(float) $v, (float) $p['peso']];
            }
        }
        return $pares ? self::promedioPonderado($pares) : null;
    }

    // ------------------------------------------------------------------
    // Configuración, escalas y niveles
    // ------------------------------------------------------------------

    public static function configuracion(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare('SELECT estructura, escala_id FROM configuracion_evaluacion WHERE colegio_id = ?');
        $q->execute([$colegioId]);
        $c = $q->fetch();
        return $c ? ['estructura' => (string) $c['estructura'], 'escala_id' => $c['escala_id'] !== null ? (int) $c['escala_id'] : null]
                  : ['estructura' => 'actividades', 'escala_id' => null];
    }

    public static function guardarConfiguracion(PDO $pdo, int $colegioId, string $estructura, ?int $escalaId): string
    {
        if (!isset(self::ESTRUCTURAS[$estructura])) {
            return 'Estructura no válida.';
        }
        if ($escalaId !== null && self::escala($pdo, $escalaId, $colegioId) === null) {
            return 'La escala elegida no existe.';
        }
        $pdo->prepare('INSERT INTO configuracion_evaluacion (colegio_id, estructura, escala_id) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE estructura = VALUES(estructura), escala_id = VALUES(escala_id)')
            ->execute([$colegioId, $estructura, $escalaId]);
        return '';
    }

    public static function escalas(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare('SELECT e.*, (SELECT COUNT(*) FROM escala_niveles n WHERE n.escala_id = e.id) AS n_niveles
                              FROM escalas_evaluacion e WHERE e.colegio_id = ? ORDER BY e.nombre');
        $q->execute([$colegioId]);
        return $q->fetchAll();
    }

    public static function escala(PDO $pdo, int $id, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT * FROM escalas_evaluacion WHERE id = ? AND colegio_id = ?');
        $q->execute([$id, $colegioId]);
        $e = $q->fetch();
        if (!$e) {
            return null;
        }
        $e['niveles'] = self::niveles($pdo, (int) $e['id']);
        return $e;
    }

    /** Escala en uso del colegio (con sus niveles), o null si aún no se configuró. */
    public static function escalaActiva(PDO $pdo, int $colegioId): ?array
    {
        $c = self::configuracion($pdo, $colegioId);
        return $c['escala_id'] !== null ? self::escala($pdo, $c['escala_id'], $colegioId) : null;
    }

    public static function niveles(PDO $pdo, int $escalaId): array
    {
        $q = $pdo->prepare('SELECT * FROM escala_niveles WHERE escala_id = ? ORDER BY orden, id');
        $q->execute([$escalaId]);
        return $q->fetchAll();
    }

    /** Crea una escala vacía. Devuelve [id, error]. */
    public static function crearEscala(PDO $pdo, int $colegioId, string $nombre, string $tipo, float $min, float $max, int $decimales): array
    {
        $nombre = mb_substr(trim($nombre), 0, 60);
        if ($nombre === '') {
            return [0, 'Escribe el nombre de la escala.'];
        }
        if (!in_array($tipo, ['numerica', 'descriptiva'], true)) {
            return [0, 'Tipo de escala no válido.'];
        }
        if ($min >= $max || $min < 0 || $max > 1000) {
            return [0, 'El valor mínimo debe ser menor que el máximo (entre 0 y 1000).'];
        }
        $pdo->prepare('INSERT INTO escalas_evaluacion (colegio_id, nombre, tipo, valor_min, valor_max, decimales) VALUES (?,?,?,?,?,?)')
            ->execute([$colegioId, $nombre, $tipo, $min, $max, max(0, min(2, $decimales))]);
        return [(int) $pdo->lastInsertId(), ''];
    }

    /** Escala 1.0–5.0 con los cuatro desempeños nacionales (Superior, Alto, Básico, Bajo). Los rangos se pueden editar. */
    public static function crearEscalaPredeterminada(PDO $pdo, int $colegioId, string $tipo): int
    {
        $nombre = $tipo === 'descriptiva' ? 'Descriptiva (Superior, Alto, Básico, Bajo)' : 'Numérica 1.0 a 5.0';
        [$id] = self::crearEscala($pdo, $colegioId, $nombre . ' · ' . date('Y'), $tipo, 1.0, 5.0, 1);
        $niveles = [
            [1, 'Desempeño Superior', 'S', 4.6, 5.0, 4.8, 1],
            [2, 'Desempeño Alto', 'A', 4.0, 4.5, 4.2, 1],
            [3, 'Desempeño Básico', 'B', 3.0, 3.9, 3.4, 1],
            [4, 'Desempeño Bajo', 'Bj', 1.0, 2.9, 2.0, 0],
        ];
        $ins = $pdo->prepare('INSERT INTO escala_niveles (escala_id, orden, nombre, abreviatura, desde, hasta, valor_equivalente, aprueba) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($niveles as $n) {
            $ins->execute([$id, $n[0], $n[1], $n[2], $n[3], $n[4], $n[5], $n[6]]);
        }
        return $id;
    }

    public static function guardarNivel(PDO $pdo, int $escalaId, ?int $id, string $nombre, string $abrev, float $desde, float $hasta, float $equiv, bool $aprueba, int $orden): string
    {
        $nombre = mb_substr(trim($nombre), 0, 40);
        $abrev = mb_substr(trim($abrev), 0, 6);
        if ($nombre === '' || $abrev === '') {
            return 'Escribe el nombre y la abreviatura del nivel.';
        }
        if ($desde > $hasta) {
            return 'El «desde» no puede ser mayor que el «hasta».';
        }
        if ($id !== null && $id > 0) {
            $pdo->prepare('UPDATE escala_niveles SET orden = ?, nombre = ?, abreviatura = ?, desde = ?, hasta = ?, valor_equivalente = ?, aprueba = ? WHERE id = ? AND escala_id = ?')
                ->execute([$orden, $nombre, $abrev, $desde, $hasta, $equiv, $aprueba ? 1 : 0, $id, $escalaId]);
        } else {
            $pdo->prepare('INSERT INTO escala_niveles (escala_id, orden, nombre, abreviatura, desde, hasta, valor_equivalente, aprueba) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$escalaId, $orden, $nombre, $abrev, $desde, $hasta, $equiv, $aprueba ? 1 : 0]);
        }
        return '';
    }

    public static function eliminarNivel(PDO $pdo, int $id, int $escalaId): void
    {
        $pdo->prepare('DELETE FROM escala_niveles WHERE id = ? AND escala_id = ?')->execute([$id, $escalaId]);
    }

    public static function eliminarEscala(PDO $pdo, int $id, int $colegioId): string
    {
        if (self::escala($pdo, $id, $colegioId) === null) {
            return 'La escala no existe.';
        }
        if (self::configuracion($pdo, $colegioId)['escala_id'] === $id) {
            return 'Es la escala en uso: elige otra antes de eliminarla.';
        }
        $pdo->prepare('DELETE FROM escalas_evaluacion WHERE id = ? AND colegio_id = ?')->execute([$id, $colegioId]);
        return '';
    }

    // ------------------------------------------------------------------
    // Procesos (componentes)
    // ------------------------------------------------------------------

    public static function componentes(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare('SELECT * FROM componentes_evaluacion WHERE colegio_id = ? ORDER BY orden, id');
        $q->execute([$colegioId]);
        return $q->fetchAll();
    }

    public static function guardarComponente(PDO $pdo, int $colegioId, ?int $id, string $nombre, float $peso, int $orden): string
    {
        $nombre = mb_substr(trim($nombre), 0, 60);
        if ($nombre === '') {
            return 'Escribe el nombre del proceso.';
        }
        if ($peso < 0 || $peso > 100) {
            return 'El peso debe estar entre 0 y 100.';
        }
        if ($id !== null && $id > 0) {
            $pdo->prepare('UPDATE componentes_evaluacion SET nombre = ?, peso = ?, orden = ? WHERE id = ? AND colegio_id = ?')->execute([$nombre, $peso, $orden, $id, $colegioId]);
        } else {
            $pdo->prepare('INSERT INTO componentes_evaluacion (colegio_id, nombre, peso, orden) VALUES (?,?,?,?)')->execute([$colegioId, $nombre, $peso, $orden]);
        }
        return '';
    }

    public static function eliminarComponente(PDO $pdo, int $id, int $colegioId): void
    {
        $pdo->prepare('DELETE FROM componentes_evaluacion WHERE id = ? AND colegio_id = ?')->execute([$id, $colegioId]);
    }

    public static function procesosPredeterminados(PDO $pdo, int $colegioId): void
    {
        foreach ([['Cognitivo (saber)', 40, 1], ['Procedimental (hacer)', 30, 2], ['Actitudinal (ser)', 30, 3]] as $c) {
            self::guardarComponente($pdo, $colegioId, null, $c[0], (float) $c[1], $c[2]);
        }
    }

    // ------------------------------------------------------------------
    // Asignaturas y asignaciones académicas
    // ------------------------------------------------------------------

    public static function asignaturas(PDO $pdo, int $colegioId, bool $soloActivas = false): array
    {
        $q = $pdo->prepare('SELECT * FROM asignaturas WHERE colegio_id = ?' . ($soloActivas ? ' AND activa = 1' : '') . ' ORDER BY area, nombre');
        $q->execute([$colegioId]);
        return $q->fetchAll();
    }

    public static function guardarAsignatura(PDO $pdo, int $colegioId, ?int $id, string $nombre, string $area, ?int $intensidad, bool $activa): string
    {
        $nombre = mb_substr(trim($nombre), 0, 80);
        if ($nombre === '') {
            return 'Escribe el nombre de la asignatura.';
        }
        $area = mb_substr(trim($area), 0, 80) ?: null;
        $intensidad = ($intensidad !== null && $intensidad > 0 && $intensidad <= 40) ? $intensidad : null;
        try {
            if ($id !== null && $id > 0) {
                $pdo->prepare('UPDATE asignaturas SET nombre = ?, area = ?, intensidad = ?, activa = ? WHERE id = ? AND colegio_id = ?')
                    ->execute([$nombre, $area, $intensidad, $activa ? 1 : 0, $id, $colegioId]);
            } else {
                $pdo->prepare('INSERT INTO asignaturas (colegio_id, nombre, area, intensidad, activa) VALUES (?,?,?,?,?)')
                    ->execute([$colegioId, $nombre, $area, $intensidad, $activa ? 1 : 0]);
            }
        } catch (PDOException $e) {
            return $e->getCode() === '23000' ? "Ya existe una asignatura llamada «{$nombre}»." : 'No se pudo guardar la asignatura.';
        }
        return '';
    }

    public static function eliminarAsignatura(PDO $pdo, int $id, int $colegioId): string
    {
        $q = $pdo->prepare('SELECT COUNT(*) FROM asignaciones_academicas WHERE asignatura_id = ? AND colegio_id = ?');
        $q->execute([$id, $colegioId]);
        if ((int) $q->fetchColumn() > 0) {
            return 'Esa asignatura ya está asignada a grupos. Desactívala en lugar de eliminarla.';
        }
        $pdo->prepare('DELETE FROM asignaturas WHERE id = ? AND colegio_id = ?')->execute([$id, $colegioId]);
        return '';
    }

    /** Asignaciones del año, con nombres. Filtros opcionales por docente y por grupo. */
    public static function asignaciones(PDO $pdo, int $colegioId, int $anioId, ?int $docenteId = null, ?int $grupoId = null): array
    {
        $sql = 'SELECT a.*, s.nombre AS asignatura, s.area, s.intensidad, g.nombre AS grupo, gr.id AS grado_id, gr.nombre AS grado, u.nombre AS docente,
                       (SELECT COUNT(*) FROM actividades_evaluacion x WHERE x.asignacion_id = a.id) AS n_actividades
                  FROM asignaciones_academicas a
                  INNER JOIN asignaturas s ON s.id = a.asignatura_id
                  INNER JOIN grupos g ON g.id = a.grupo_id
                  INNER JOIN grados gr ON gr.id = g.grado_id
                  LEFT JOIN usuarios u ON u.id = a.docente_id
                 WHERE a.colegio_id = ? AND a.anio_lectivo_id = ?';
        $p = [$colegioId, $anioId];
        if ($docenteId !== null) {
            $sql .= ' AND a.docente_id = ?';
            $p[] = $docenteId;
        }
        if ($grupoId !== null) {
            $sql .= ' AND a.grupo_id = ?';
            $p[] = $grupoId;
        }
        $q = $pdo->prepare($sql . ' ORDER BY gr.nombre, g.nombre, s.nombre');
        $q->execute($p);
        return $q->fetchAll();
    }

    public static function asignacion(PDO $pdo, int $id, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT a.*, s.nombre AS asignatura, g.nombre AS grupo, gr.nombre AS grado, u.nombre AS docente
                              FROM asignaciones_academicas a
                              INNER JOIN asignaturas s ON s.id = a.asignatura_id
                              INNER JOIN grupos g ON g.id = a.grupo_id
                              INNER JOIN grados gr ON gr.id = g.grado_id
                              LEFT JOIN usuarios u ON u.id = a.docente_id
                             WHERE a.id = ? AND a.colegio_id = ?');
        $q->execute([$id, $colegioId]);
        return $q->fetch() ?: null;
    }

    private static function esDocenteDelColegio(PDO $pdo, int $docenteId, int $colegioId): bool
    {
        $q = $pdo->prepare("SELECT 1 FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE u.id = ? AND u.colegio_id = ? AND u.activo = 1 AND (r.nombre = 'docente'" . RolesUsuario::sqlAdicional($pdo, 'u', 'docente') . ")");
        $q->execute([$docenteId, $colegioId]);
        return (bool) $q->fetchColumn();
    }

    /** Asigna una asignatura a un grupo (y opcionalmente un docente). Devuelve un error o ''. */
    public static function crearAsignacion(PDO $pdo, int $colegioId, int $anioId, int $grupoId, int $asignaturaId, ?int $docenteId): string
    {
        if (Academico::anio($pdo, $anioId, $colegioId) === null) {
            return 'El año lectivo no existe.';
        }
        $g = $pdo->prepare('SELECT 1 FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE g.id = ? AND gr.colegio_id = ?');
        $g->execute([$grupoId, $colegioId]);
        $s = $pdo->prepare('SELECT 1 FROM asignaturas WHERE id = ? AND colegio_id = ?');
        $s->execute([$asignaturaId, $colegioId]);
        if (!$g->fetchColumn() || !$s->fetchColumn()) {
            return 'Elige un grupo y una asignatura del colegio.';
        }
        if ($docenteId !== null && !self::esDocenteDelColegio($pdo, $docenteId, $colegioId)) {
            return 'El docente elegido no es válido.';
        }
        $ins = $pdo->prepare('INSERT IGNORE INTO asignaciones_academicas (colegio_id, anio_lectivo_id, grupo_id, asignatura_id, docente_id) VALUES (?,?,?,?,?)');
        $ins->execute([$colegioId, $anioId, $grupoId, $asignaturaId, $docenteId]);
        return $ins->rowCount() > 0 ? '' : 'Ese grupo ya tiene esa asignatura en este año.';
    }

    /** Asigna una asignatura a TODOS los grupos de un grado. Devuelve cuántas creó. */
    public static function asignarAGrado(PDO $pdo, int $colegioId, int $anioId, int $gradoId, int $asignaturaId, ?int $docenteId): int
    {
        $q = $pdo->prepare('SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE gr.id = ? AND gr.colegio_id = ?');
        $q->execute([$gradoId, $colegioId]);
        $n = 0;
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $gid) {
            if (self::crearAsignacion($pdo, $colegioId, $anioId, (int) $gid, $asignaturaId, $docenteId) === '') {
                $n++;
            }
        }
        return $n;
    }

    public static function cambiarDocente(PDO $pdo, int $id, int $colegioId, ?int $docenteId): string
    {
        if ($docenteId !== null && !self::esDocenteDelColegio($pdo, $docenteId, $colegioId)) {
            return 'El docente elegido no es válido.';
        }
        $pdo->prepare('UPDATE asignaciones_academicas SET docente_id = ? WHERE id = ? AND colegio_id = ?')->execute([$docenteId, $id, $colegioId]);
        return '';
    }

    public static function eliminarAsignacion(PDO $pdo, int $id, int $colegioId): string
    {
        $a = self::asignacion($pdo, $id, $colegioId);
        if ($a === null) {
            return 'La asignación no existe.';
        }
        $q = $pdo->prepare('SELECT COUNT(*) FROM actividades_evaluacion WHERE asignacion_id = ?');
        $q->execute([$id]);
        if ((int) $q->fetchColumn() > 0) {
            return 'Esa asignación ya tiene actividades y notas. No se puede eliminar; cambia el docente si hace falta.';
        }
        $pdo->prepare('DELETE FROM asignaciones_academicas WHERE id = ? AND colegio_id = ?')->execute([$id, $colegioId]);
        return '';
    }

    // ------------------------------------------------------------------
    // Planilla: logros, actividades y notas
    // ------------------------------------------------------------------

    /** Período del colegio (valida que el período pertenezca a un año del colegio). */
    public static function periodo(PDO $pdo, int $periodoId, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT p.*, a.anio, a.colegio_id FROM periodos p INNER JOIN anios_lectivos a ON a.id = p.anio_lectivo_id WHERE p.id = ? AND a.colegio_id = ?');
        $q->execute([$periodoId, $colegioId]);
        return $q->fetch() ?: null;
    }

    public static function actividades(PDO $pdo, int $asignacionId, int $periodoId): array
    {
        $q = $pdo->prepare('SELECT * FROM actividades_evaluacion WHERE asignacion_id = ? AND periodo_id = ? ORDER BY orden, id');
        $q->execute([$asignacionId, $periodoId]);
        return $q->fetchAll();
    }

    public static function logros(PDO $pdo, int $asignacionId, int $periodoId): array
    {
        $q = $pdo->prepare('SELECT * FROM logros WHERE asignacion_id = ? AND periodo_id = ? ORDER BY orden, id');
        $q->execute([$asignacionId, $periodoId]);
        return $q->fetchAll();
    }

    public static function guardarLogro(PDO $pdo, int $asignacionId, int $periodoId, ?int $id, string $descripcion, float $peso): string
    {
        $descripcion = mb_substr(trim($descripcion), 0, 500);
        if ($descripcion === '') {
            return 'Escribe la descripción del logro.';
        }
        if ($peso < 0 || $peso > 100) {
            return 'El peso debe estar entre 0 y 100.';
        }
        if ($id !== null && $id > 0) {
            $pdo->prepare('UPDATE logros SET descripcion = ?, peso = ? WHERE id = ? AND asignacion_id = ? AND periodo_id = ?')->execute([$descripcion, $peso, $id, $asignacionId, $periodoId]);
        } else {
            $orden = count(self::logros($pdo, $asignacionId, $periodoId)) + 1;
            $pdo->prepare('INSERT INTO logros (asignacion_id, periodo_id, descripcion, peso, orden) VALUES (?,?,?,?,?)')->execute([$asignacionId, $periodoId, $descripcion, $peso, min(250, $orden)]);
        }
        return '';
    }

    public static function eliminarLogro(PDO $pdo, int $id, int $asignacionId): void
    {
        $pdo->prepare('DELETE FROM logros WHERE id = ? AND asignacion_id = ?')->execute([$id, $asignacionId]);
    }

    /** Crea o edita una actividad (columna de la planilla). Devuelve un error o ''. */
    public static function guardarActividad(PDO $pdo, int $colegioId, int $asignacionId, int $periodoId, ?int $id, array $d, string $estructura): string
    {
        $nombre = mb_substr(trim((string) ($d['nombre'] ?? '')), 0, 100);
        $peso = (float) str_replace(',', '.', (string) ($d['peso'] ?? '0'));
        $comp = (int) ($d['componente_id'] ?? 0) ?: null;
        $logro = (int) ($d['logro_id'] ?? 0) ?: null;
        $fecha = trim((string) ($d['fecha'] ?? ''));
        if ($nombre === '') {
            return 'Escribe el nombre de la actividad.';
        }
        if ($peso < 0 || $peso > 100) {
            return 'El peso debe estar entre 0 y 100.';
        }
        if ($fecha !== '') {
            $f = DateTime::createFromFormat('Y-m-d', $fecha);
            if ($f === false || $f->format('Y-m-d') !== $fecha) {
                return 'La fecha no es válida.';
            }
        }
        $usaProc = in_array($estructura, ['procesos', 'procesos_logros', 'logros_procesos'], true);
        $usaLogr = in_array($estructura, ['logros', 'procesos_logros', 'logros_procesos'], true);
        if ($usaProc) {
            $ok = $comp !== null && (function () use ($pdo, $comp, $colegioId): bool {
                $q = $pdo->prepare('SELECT 1 FROM componentes_evaluacion WHERE id = ? AND colegio_id = ?');
                $q->execute([$comp, $colegioId]);
                return (bool) $q->fetchColumn();
            })();
            if (!$ok) {
                return 'Esta estructura de evaluación exige elegir el proceso de la actividad.';
            }
        } else {
            $comp = null;
        }
        if ($usaLogr) {
            $q = $pdo->prepare('SELECT 1 FROM logros WHERE id = ? AND asignacion_id = ? AND periodo_id = ?');
            $q->execute([$logro ?? 0, $asignacionId, $periodoId]);
            if ($logro === null || !$q->fetchColumn()) {
                return 'Esta estructura de evaluación exige elegir el logro de la actividad (créalo primero en la sección Logros).';
            }
        } else {
            $logro = null;
        }
        if ($id !== null && $id > 0) {
            $pdo->prepare('UPDATE actividades_evaluacion SET nombre = ?, peso = ?, componente_id = ?, logro_id = ?, fecha = ? WHERE id = ? AND asignacion_id = ? AND periodo_id = ?')
                ->execute([$nombre, $peso, $comp, $logro, $fecha ?: null, $id, $asignacionId, $periodoId]);
        } else {
            $orden = count(self::actividades($pdo, $asignacionId, $periodoId)) + 1;
            $pdo->prepare('INSERT INTO actividades_evaluacion (asignacion_id, periodo_id, nombre, peso, componente_id, logro_id, fecha, orden) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$asignacionId, $periodoId, $nombre, $peso, $comp, $logro, $fecha ?: null, min(250, $orden)]);
        }
        return '';
    }

    public static function eliminarActividad(PDO $pdo, int $id, int $asignacionId): void
    {
        $pdo->prepare('DELETE FROM actividades_evaluacion WHERE id = ? AND asignacion_id = ?')->execute([$id, $asignacionId]);
    }

    /** Estudiantes del grupo en ese año: con matrícula activa en el grupo; y, en el año actual, los activos del grupo que aún no tienen matrícula. */
    public static function estudiantesDeGrupo(PDO $pdo, int $colegioId, array $anio, int $grupoId): array
    {
        $q = $pdo->prepare("SELECT u.id, u.nombre FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.grupo_id = ? AND m.estado = 'activa' AND u.activo = 1");
        $q->execute([$colegioId, (int) $anio['id'], $grupoId]);
        $res = [];
        foreach ($q->fetchAll() as $r) {
            $res[(int) $r['id']] = $r;
        }
        if ((int) $anio['actual'] === 1) {
            $q2 = $pdo->prepare("SELECT u.id, u.nombre FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
                                  WHERE u.colegio_id = ? AND u.grupo_id = ? AND u.activo = 1
                                    AND NOT EXISTS (SELECT 1 FROM matriculas m WHERE m.estudiante_id = u.id AND m.anio_lectivo_id = ?)");
            $q2->execute([$colegioId, $grupoId, (int) $anio['id']]);
            foreach ($q2->fetchAll() as $r) {
                $res[(int) $r['id']] = $r;
            }
        }
        uasort($res, static fn (array $a, array $b): int => strcasecmp((string) $a['nombre'], (string) $b['nombre']));
        return array_values($res);
    }

    /** Calificaciones de las actividades: [estudiante_id][actividad_id] => ['valor', 'nivel_id']. */
    public static function calificaciones(PDO $pdo, array $actividadIds): array
    {
        if (!$actividadIds) {
            return [];
        }
        $in = implode(',', array_map('intval', $actividadIds));
        $res = [];
        foreach ($pdo->query("SELECT actividad_id, estudiante_id, valor, nivel_id FROM calificaciones WHERE actividad_id IN ({$in})") as $r) {
            $res[(int) $r['estudiante_id']][(int) $r['actividad_id']] = ['valor' => $r['valor'] !== null ? (float) $r['valor'] : null, 'nivel_id' => $r['nivel_id'] !== null ? (int) $r['nivel_id'] : null];
        }
        return $res;
    }

    public static function notasPeriodo(PDO $pdo, int $asignacionId, int $periodoId): array
    {
        $q = $pdo->prepare('SELECT * FROM notas_periodo WHERE asignacion_id = ? AND periodo_id = ?');
        $q->execute([$asignacionId, $periodoId]);
        $res = [];
        foreach ($q->fetchAll() as $r) {
            $res[(int) $r['estudiante_id']] = $r;
        }
        return $res;
    }

    /**
     * Guarda la planilla completa (notas + observaciones) y recalcula la nota del período.
     * $entrada: [estudiante_id => [actividad_id => texto]] (número en escalas numéricas, id de nivel en descriptivas).
     * @return array{guardadas: int, errores: array<int,string>}
     */
    public static function guardarPlanilla(PDO $pdo, array $asig, int $periodoId, string $estructura, array $escala, array $estudiantes, array $actividades, array $entrada, array $observaciones): array
    {
        $res = ['guardadas' => 0, 'errores' => []];
        $esDesc = $escala['tipo'] === 'descriptiva';
        $min = (float) $escala['valor_min'];
        $max = (float) $escala['valor_max'];
        $dec = (int) $escala['decimales'];
        $niveles = [];
        foreach ($escala['niveles'] as $n) {
            $niveles[(int) $n['id']] = $n;
        }
        $upsert = $pdo->prepare('INSERT INTO calificaciones (actividad_id, estudiante_id, valor, nivel_id) VALUES (?,?,?,?)
                                 ON DUPLICATE KEY UPDATE valor = VALUES(valor), nivel_id = VALUES(nivel_id), actualizado_en = NOW()');
        $borrar = $pdo->prepare('DELETE FROM calificaciones WHERE actividad_id = ? AND estudiante_id = ?');

        $pdo->beginTransaction();
        try {
            foreach ($estudiantes as $est) {
                $eid = (int) $est['id'];
                foreach ($actividades as $act) {
                    $aid = (int) $act['id'];
                    if (!isset($entrada[$eid]) || !array_key_exists($aid, (array) $entrada[$eid])) {
                        continue; // el campo no vino en el formulario: no se toca
                    }
                    $txt = trim((string) $entrada[$eid][$aid]);
                    if ($txt === '') {
                        $borrar->execute([$aid, $eid]);
                        continue;
                    }
                    if ($esDesc) {
                        $nid = (int) $txt;
                        if (!isset($niveles[$nid])) {
                            $res['errores'][] = "{$est['nombre']} · {$act['nombre']}: nivel no válido.";
                            continue;
                        }
                        $upsert->execute([$aid, $eid, (float) $niveles[$nid]['valor_equivalente'], $nid]);
                    } else {
                        $txt = str_replace(',', '.', $txt);
                        if (!is_numeric($txt) || (float) $txt < $min - 0.0001 || (float) $txt > $max + 0.0001) {
                            $res['errores'][] = "{$est['nombre']} · {$act['nombre']}: «{$txt}» está fuera de {$min} a {$max}.";
                            continue;
                        }
                        $v = round((float) $txt, $dec);
                        $n = self::nivelParaValor($escala['niveles'], $v);
                        $upsert->execute([$aid, $eid, $v, $n ? (int) $n['id'] : null]);
                    }
                    $res['guardadas']++;
                }
            }
            self::recalcularPeriodo($pdo, $asig, $periodoId, $estructura, $escala, $estudiantes, $actividades);
            $obs = $pdo->prepare('UPDATE notas_periodo SET observacion = ? WHERE asignacion_id = ? AND periodo_id = ? AND estudiante_id = ?');
            $insObs = $pdo->prepare('INSERT INTO notas_periodo (asignacion_id, periodo_id, estudiante_id, observacion) VALUES (?,?,?,?)
                                     ON DUPLICATE KEY UPDATE observacion = VALUES(observacion)');
            foreach ($estudiantes as $est) {
                $eid = (int) $est['id'];
                if (!array_key_exists($eid, $observaciones)) {
                    continue;
                }
                $t = mb_substr(trim((string) $observaciones[$eid]), 0, 500);
                $insObs->execute([(int) $asig['id'], $periodoId, $eid, $t !== '' ? $t : null]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return $res;
    }

    /** Recalcula y guarda la nota del período de todos los estudiantes de la asignación (conserva la observación). */
    public static function recalcularPeriodo(PDO $pdo, array $asig, int $periodoId, string $estructura, array $escala, array $estudiantes, array $actividades): void
    {
        $pesoComp = [];
        foreach (self::componentes($pdo, (int) $asig['colegio_id']) as $c) {
            $pesoComp[(int) $c['id']] = (float) $c['peso'];
        }
        $pesoLogro = [];
        foreach (self::logros($pdo, (int) $asig['id'], $periodoId) as $l) {
            $pesoLogro[(int) $l['id']] = (float) $l['peso'];
        }
        $califs = self::calificaciones($pdo, array_map(static fn (array $a): int => (int) $a['id'], $actividades));
        $dec = (int) $escala['decimales'];
        $upsert = $pdo->prepare('INSERT INTO notas_periodo (asignacion_id, periodo_id, estudiante_id, valor, nivel_id) VALUES (?,?,?,?,?)
                                 ON DUPLICATE KEY UPDATE valor = VALUES(valor), nivel_id = VALUES(nivel_id), calculado_en = NOW()');
        foreach ($estudiantes as $est) {
            $eid = (int) $est['id'];
            $mapa = [];
            foreach (($califs[$eid] ?? []) as $aid => $c) {
                $mapa[$aid] = $c['valor'];
            }
            $nota = self::calcularNota($actividades, $mapa, $estructura, $pesoComp, $pesoLogro);
            if ($nota === null) {
                $upsert->execute([(int) $asig['id'], $periodoId, $eid, null, null]);
                continue;
            }
            $nota = round($nota, $dec);
            $nivel = self::nivelParaValor($escala['niveles'], $nota);
            $upsert->execute([(int) $asig['id'], $periodoId, $eid, $nota, $nivel ? (int) $nivel['id'] : null]);
        }
    }

    // ------------------------------------------------------------------
    // Boletín, consolidado y publicación
    // ------------------------------------------------------------------

    /** Grupo del estudiante en ese año: el de su matrícula activa, o el actual si el año es el actual. */
    public static function grupoDeEstudiante(PDO $pdo, int $estudianteId, array $anio): ?int
    {
        $q = $pdo->prepare("SELECT grupo_id FROM matriculas WHERE estudiante_id = ? AND anio_lectivo_id = ? AND estado = 'activa' AND grupo_id IS NOT NULL");
        $q->execute([$estudianteId, (int) $anio['id']]);
        $g = $q->fetchColumn();
        if ($g !== false) {
            return (int) $g;
        }
        if ((int) $anio['actual'] === 1) {
            $q = $pdo->prepare('SELECT grupo_id FROM usuarios WHERE id = ?');
            $q->execute([$estudianteId]);
            $g = $q->fetchColumn();
            return $g ? (int) $g : null;
        }
        return null;
    }

    /**
     * Datos del boletín de un estudiante en un año. Si $soloPublicados, solo incluye períodos con boletín publicado.
     * @return array{estudiante: array, grupo: ?array, periodos: array, filas: array, promedios: array, promedio_final: ?float, escala: ?array}
     */
    public static function boletin(PDO $pdo, int $colegioId, int $estudianteId, array $anio, bool $soloPublicados): array
    {
        $q = $pdo->prepare('SELECT u.id, u.nombre, u.correo FROM usuarios u WHERE u.id = ? AND u.colegio_id = ?');
        $q->execute([$estudianteId, $colegioId]);
        $est = $q->fetch() ?: ['id' => $estudianteId, 'nombre' => '', 'correo' => ''];
        $escala = self::escalaActiva($pdo, $colegioId);
        $grupoId = self::grupoDeEstudiante($pdo, $estudianteId, $anio);
        $grupo = null;
        if ($grupoId !== null) {
            $g = $pdo->prepare('SELECT g.id, g.nombre, gr.nombre AS grado FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE g.id = ? AND gr.colegio_id = ?');
            $g->execute([$grupoId, $colegioId]);
            $grupo = $g->fetch() ?: null;
        }
        $periodos = Academico::periodos($pdo, (int) $anio['id']);
        if ($soloPublicados) {
            $periodos = array_values(array_filter($periodos, static fn (array $p): bool => (int) ($p['boletin_publicado'] ?? 0) === 1));
        }
        $res = ['estudiante' => $est, 'grupo' => $grupo, 'periodos' => $periodos, 'filas' => [], 'promedios' => [], 'promedio_final' => null, 'escala' => $escala];
        if ($grupoId === null || $escala === null || !$periodos) {
            return $res;
        }
        $asigs = self::asignaciones($pdo, $colegioId, (int) $anio['id'], null, $grupoId);
        $niveles = [];
        foreach ($escala['niveles'] as $n) {
            $niveles[(int) $n['id']] = $n;
        }
        $ids = array_map(static fn (array $p): int => (int) $p['id'], $periodos);
        $notas = [];
        if ($asigs) {
            $inA = implode(',', array_map(static fn (array $a): int => (int) $a['id'], $asigs));
            $inP = implode(',', $ids);
            $n = $pdo->prepare("SELECT asignacion_id, periodo_id, valor, nivel_id, observacion FROM notas_periodo WHERE estudiante_id = ? AND asignacion_id IN ({$inA}) AND periodo_id IN ({$inP})");
            $n->execute([$estudianteId]);
            foreach ($n->fetchAll() as $r) {
                $notas[(int) $r['asignacion_id']][(int) $r['periodo_id']] = $r;
            }
        }
        $sumas = [];
        foreach ($asigs as $a) {
            $porPeriodo = [];
            $valores = [];
            foreach ($periodos as $p) {
                $r = $notas[(int) $a['id']][(int) $p['id']] ?? null;
                $v = ($r && $r['valor'] !== null) ? (float) $r['valor'] : null;
                $valores[(int) $p['id']] = $v;
                $porPeriodo[(int) $p['id']] = ['valor' => $v, 'nivel' => ($r && $r['nivel_id'] !== null) ? ($niveles[(int) $r['nivel_id']] ?? null) : null, 'observacion' => $r['observacion'] ?? null];
                if ($v !== null) {
                    $sumas[(int) $p['id']][] = [$v, (float) ($a['intensidad'] ?? 0)];
                }
            }
            $def = self::definitiva($valores, $periodos);
            $defRed = $def !== null ? round($def, (int) $escala['decimales']) : null;
            $res['filas'][] = [
                'asignatura' => $a['asignatura'], 'area' => $a['area'], 'docente' => $a['docente'], 'intensidad' => $a['intensidad'],
                'periodos' => $porPeriodo, 'definitiva' => $defRed,
                'nivel_definitiva' => $defRed !== null ? self::nivelParaValor($escala['niveles'], $defRed) : null,
            ];
        }
        // Agrupar por ÁREA: cada área aparece una sola vez y sus asignaturas van juntas debajo (orden de aparición; sin área al final).
        // La clave ignora mayúsculas y espacios para que «Humanidades» y «humanidades » cuenten como la misma área.
        $grupos = [];
        $etiquetas = [];
        $sinArea = [];
        foreach ($res['filas'] as $fila) {
            $nombreArea = trim((string) ($fila['area'] ?? ''));
            if ($nombreArea === '') {
                $fila['area'] = null;
                $sinArea[] = $fila;
                continue;
            }
            $clave = mb_strtolower((string) preg_replace('/\s+/u', ' ', $nombreArea), 'UTF-8');
            $etiquetas[$clave] ??= $nombreArea;
            $fila['area'] = $etiquetas[$clave];
            $grupos[$clave][] = $fila;
        }
        $res['filas'] = [];
        foreach ($grupos as $filasArea) {
            foreach ($filasArea as $fila) {
                $res['filas'][] = $fila;
            }
        }
        foreach ($sinArea as $fila) {
            $res['filas'][] = $fila;
        }
        // Promedio por período: ponderado por intensidad horaria si TODAS las asignaturas con nota la tienen; si no, simple.
        $promPeriodo = [];
        foreach ($sumas as $pid => $pares) {
            $todas = !in_array(0.0, array_column($pares, 1), true);
            $promPeriodo[$pid] = $todas ? self::promedioPonderado($pares) : array_sum(array_column($pares, 0)) / count($pares);
        }
        foreach ($promPeriodo as $pid => $v) {
            $res['promedios'][$pid] = round($v, (int) $escala['decimales']);
        }
        $final = self::definitiva($promPeriodo, $periodos);
        $res['promedio_final'] = $final !== null ? round($final, (int) $escala['decimales']) : null;
        return $res;
    }

    /** Matriz del grupo: estudiantes × asignaturas para un período. */
    public static function consolidadoGrupo(PDO $pdo, int $colegioId, array $anio, int $grupoId, int $periodoId): array
    {
        $asigs = self::asignaciones($pdo, $colegioId, (int) $anio['id'], null, $grupoId);
        $ests = self::estudiantesDeGrupo($pdo, $colegioId, $anio, $grupoId);
        $notas = [];
        if ($asigs) {
            $inA = implode(',', array_map(static fn (array $a): int => (int) $a['id'], $asigs));
            $q = $pdo->prepare("SELECT n.asignacion_id, n.estudiante_id, n.valor, e.abreviatura FROM notas_periodo n LEFT JOIN escala_niveles e ON e.id = n.nivel_id
                                 WHERE n.periodo_id = ? AND n.asignacion_id IN ({$inA})");
            $q->execute([$periodoId]);
            foreach ($q->fetchAll() as $r) {
                $notas[(int) $r['estudiante_id']][(int) $r['asignacion_id']] = $r;
            }
        }
        return ['asignaciones' => $asigs, 'estudiantes' => $ests, 'notas' => $notas];
    }

    public static function publicarPeriodo(PDO $pdo, int $periodoId, int $colegioId, bool $publicar): bool
    {
        if (self::periodo($pdo, $periodoId, $colegioId) === null) {
            return false;
        }
        $pdo->prepare('UPDATE periodos SET boletin_publicado = ? WHERE id = ?')->execute([$publicar ? 1 : 0, $periodoId]);
        return true;
    }

    /** ¿Esta persona puede ver el boletín de ese estudiante? (alcance de colegio ya validado por quien llama). */
    public static function puedeVerBoletin(PDO $pdo, string $rol, int $usuarioId, int $estudianteId, int $colegioId): bool
    {
        if ($rol === 'estudiante') {
            return $usuarioId === $estudianteId;
        }
        if ($rol === 'acudiente') {
            $q = $pdo->prepare('SELECT 1 FROM acudientes_estudiantes WHERE acudiente_id = ? AND estudiante_id = ?');
            $q->execute([$usuarioId, $estudianteId]);
            return (bool) $q->fetchColumn();
        }
        if (in_array($rol, ['docente', 'coordinador', 'administrador', 'rector', 'secretaria_academica'], true)) {
            $q = $pdo->prepare('SELECT 1 FROM usuarios WHERE id = ? AND colegio_id = ?');
            $q->execute([$estudianteId, $colegioId]);
            return (bool) $q->fetchColumn();
        }
        return false;
    }

    /** Texto de una nota según la escala: «4.2 · A» (numérica) o «Alto» (descriptiva). */
    public static function textoNota(?float $valor, ?array $nivel, array $escala): string
    {
        if ($valor === null) {
            return '—';
        }
        $n = number_format($valor, (int) $escala['decimales'], '.', '');
        if ($escala['tipo'] === 'descriptiva') {
            return $nivel ? (string) $nivel['nombre'] : $n;
        }
        return $nivel ? $n . ' · ' . $nivel['abreviatura'] : $n;
    }
}
