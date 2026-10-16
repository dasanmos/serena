<?php
declare(strict_types=1);

require_once __DIR__ . '/Colegio.php';
require_once __DIR__ . '/Academico.php';
require_once __DIR__ . '/../helpers/configuracion.php';

/**
 * SIMAT (Sistema Integrado de Matrícula): prepara, valida y concilia la matrícula del colegio para reportarla.
 *
 * Qué hace Serena y qué NO hace:
 *  · Serena NO se conecta al SIMAT (no hay una API pública para colegios). Lo que hace es dejar la información LISTA
 *    para el cargue: valida estudiante por estudiante, exporta un CSV con los campos del SIMAT, lleva el control de
 *    novedades (retiros, traslados, ingresos) por reportar y concilia el listado que descargas del SIMAT contra Serena.
 *  · Los catálogos de abajo (tipos de documento, códigos de grado, columnas) son ORIENTATIVOS. Verifícalos contra la
 *    guía y la plantilla vigentes de tu Secretaría de Educación; las columnas y su orden se ajustan en «Ajustes».
 */
final class Simat
{
    /** Tipos de documento aceptados. Si tu plantilla usa otros códigos, cámbialos aquí. */
    public const DOCUMENTOS = [
        'RC' => 'Registro civil', 'TI' => 'Tarjeta de identidad', 'CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería',
        'PPT' => 'Permiso por protección temporal', 'PEP' => 'Permiso especial de permanencia', 'NUIP' => 'NUIP',
        'NES' => 'Número establecido por la secretaría',
    ];
    public const PROVIENE = ['oficial' => 'Sector oficial', 'no_oficial' => 'Sector no oficial', 'otro_municipio' => 'Otro municipio', 'ninguno' => 'Ninguno (primera vez)'];
    public const NOVEDADES = ['retiro' => 'Retiro', 'traslado' => 'Traslado', 'ingreso' => 'Ingreso', 'cambio_grupo' => 'Cambio de grupo', 'cambio_grado' => 'Cambio de grado', 'otra' => 'Otra novedad'];
    public const JORNADAS = ['Mañana', 'Tarde', 'Única', 'Nocturna', 'Sabatina', 'Dominical'];
    public const FORMATOS_FECHA = ['d/m/Y' => 'DD/MM/AAAA', 'Y-m-d' => 'AAAA-MM-DD', 'd-m-Y' => 'DD-MM-AAAA'];
    public const SEPARADORES = [';' => 'Punto y coma (Excel en español)', ',' => 'Coma', "\t" => 'Tabulador'];
    /** Día de corte para calcular la edad frente al grado (mes-día del año lectivo). */
    private const CORTE_EDAD = '03-31';
    /** Número de DANE de marcador que trae la instalación inicial. */
    private const DANE_MARCADOR = '000000000000';

    /** Columnas disponibles del CSV: clave => encabezado. El orden y la selección se ajustan en «Ajustes». */
    public const COLUMNAS = [
        'dane_ie' => 'DANE_ESTABLECIMIENTO', 'sede' => 'SEDE', 'jornada' => 'JORNADA', 'grado' => 'GRADO', 'grado_nombre' => 'NOMBRE_GRADO',
        'grupo' => 'GRUPO', 'tipo_documento' => 'TIPO_DOCUMENTO', 'numero_documento' => 'NUMERO_DOCUMENTO',
        'apellido1' => 'PRIMER_APELLIDO', 'apellido2' => 'SEGUNDO_APELLIDO', 'nombre1' => 'PRIMER_NOMBRE', 'nombre2' => 'SEGUNDO_NOMBRE',
        'fecha_nacimiento' => 'FECHA_NACIMIENTO', 'genero' => 'GENERO', 'municipio_nacimiento' => 'MUNICIPIO_NACIMIENTO',
        'direccion' => 'DIRECCION', 'barrio' => 'BARRIO', 'municipio_residencia' => 'MUNICIPIO_RESIDENCIA', 'zona' => 'ZONA',
        'estrato' => 'ESTRATO', 'sisben' => 'SISBEN', 'eps' => 'EPS', 'telefono' => 'TELEFONO', 'correo' => 'CORREO',
        'estudiante_nuevo' => 'ESTUDIANTE_NUEVO', 'repitente' => 'REPITENTE', 'situacion_anterior' => 'SITUACION_ANIO_ANTERIOR',
        'proviene_de' => 'PROVIENE_DE', 'victima_conflicto' => 'VICTIMA_CONFLICTO', 'etnia' => 'ETNIA', 'discapacidad' => 'DISCAPACIDAD',
        'capacidad_excepcional' => 'CAPACIDAD_EXCEPCIONAL', 'subsidio_condicionado' => 'SUBSIDIO_CONDICIONADO', 'internado' => 'INTERNADO',
        'matricula_contratada' => 'MATRICULA_CONTRATADA', 'metodologia' => 'METODOLOGIA', 'codigo_matricula' => 'CODIGO_INTERNO',
    ];
    /** Columnas que NO salen por defecto (se pueden activar en Ajustes). */
    private const COLUMNAS_OPCIONALES = ['grado_nombre', 'correo', 'codigo_matricula'];

    /** Códigos de grado más usados (nombre normalizado => código). Cualquier otro se asigna a mano en Ajustes. */
    private const GRADOS_TEXTO = [
        'prejardin' => '-2', 'pre jardin' => '-2', 'jardin' => '-1', 'transicion' => '0', 'cero' => '0',
        'primero' => '1', 'segundo' => '2', 'tercero' => '3', 'cuarto' => '4', 'quinto' => '5', 'sexto' => '6', 'septimo' => '7',
        'octavo' => '8', 'noveno' => '9', 'decimo' => '10', 'undecimo' => '11', 'once' => '11',
    ];

    private static array $existe = [];

    // ------------------------------------------------------------------ utilidades

    /**
     * ¿Existe la tabla? Se prueba con un SELECT ... LIMIT 0 (instantáneo). Antes se consultaba information_schema, que en
     * hostings compartidos (InfinityFree) puede tardar segundos por consulta y dejar la página colgada hasta el límite de PHP.
     */
    public static function tablaExiste(PDO $pdo, string $tabla): bool
    {
        $k = 't:' . $tabla;
        if (!isset(self::$existe[$k])) {
            if (preg_match('/^[A-Za-z0-9_]+$/', $tabla) !== 1) {
                return self::$existe[$k] = false;
            }
            try {
                $pdo->query('SELECT 1 FROM `' . $tabla . '` LIMIT 0')->closeCursor();
                self::$existe[$k] = true;
            } catch (Throwable $e) {
                self::$existe[$k] = false;
            }
        }
        return self::$existe[$k];
    }

    /** ¿Existe la columna? Misma técnica que tablaExiste (sin information_schema). */
    public static function columnaExiste(PDO $pdo, string $tabla, string $columna): bool
    {
        $k = 'c:' . $tabla . '.' . $columna;
        if (!isset(self::$existe[$k])) {
            if (preg_match('/^[A-Za-z0-9_]+$/', $tabla) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $columna) !== 1) {
                return self::$existe[$k] = false;
            }
            try {
                $pdo->query('SELECT `' . $columna . '` FROM `' . $tabla . '` LIMIT 0')->closeCursor();
                self::$existe[$k] = true;
            } catch (Throwable $e) {
                self::$existe[$k] = false;
            }
        }
        return self::$existe[$k];
    }

    /** Minúsculas, sin tildes, espacios simples. Sirve para comparar textos. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /** Número de documento sin puntos, espacios ni guiones, en mayúsculas. */
    public static function limpiarDocumento(string $n): string
    {
        return strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $n));
    }

    private static function sn(mixed $v): string
    {
        $n = self::norm((string) $v);
        if (in_array($n, ['s', 'si', '1', 'true', 'x'], true)) {
            return 'S';
        }
        if (in_array($n, ['n', 'no', '0', 'false'], true)) {
            return 'N';
        }
        return '';
    }

    private static function snDesdeBool(mixed $v): string
    {
        return $v === null || $v === '' ? '' : ((int) $v === 1 ? 'S' : 'N');
    }

    /** Código SIMAT del grado a partir de su nombre («Sexto», «6°», «Transición»…). null si no se reconoce. */
    public static function codigoGrado(string $nombre): ?string
    {
        $n = self::norm(str_replace(['°', 'º', 'ª', '.', ','], ' ', $nombre));
        $n = trim((string) preg_replace('/\bgrado\b/u', ' ', $n));
        $n = trim((string) preg_replace('/\s+/', ' ', $n));
        if ($n === '') {
            return null;
        }
        if (isset(self::GRADOS_TEXTO[$n])) {
            return self::GRADOS_TEXTO[$n];
        }
        if (preg_match('/^-?\d{1,2}$/', $n) === 1) {
            $v = (int) $n;
            return ($v >= -2 && $v <= 11) ? (string) $v : null;
        }
        $claves = array_keys(self::GRADOS_TEXTO);
        usort($claves, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($claves as $k) {
            if (str_starts_with($n, $k . ' ')) {
                return self::GRADOS_TEXTO[$k];
            }
        }
        if (preg_match('/^(\d{1,2})\s/', $n, $m) === 1) {
            $v = (int) $m[1];
            return ($v >= 0 && $v <= 11) ? (string) $v : null;
        }
        return null;
    }

    /** Código efectivo de un grado del colegio: el que se fijó a mano en Ajustes o el detectado por el nombre. */
    public static function codigoGradoColegio(PDO $pdo, int $colegioId, int $gradoId, string $nombre): ?string
    {
        $manual = trim(configuracionObtener($pdo, 'simat_codigo_grado_' . $gradoId, '', $colegioId));
        return $manual !== '' ? $manual : self::codigoGrado($nombre);
    }

    private static function edadAl(string $fechaNac, string $corte): ?int
    {
        try {
            $n = new DateTimeImmutable($fechaNac);
            $c = new DateTimeImmutable($corte);
        } catch (Throwable $e) {
            return null;
        }
        return $n > $c ? -1 : (int) $n->diff($c)->y;
    }

    // ------------------------------------------------------------------ ajustes

    public static function colegio(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare('SELECT id, nombre, dane, municipio FROM colegios WHERE id = ?');
        $q->execute([$colegioId]);
        return $q->fetch() ?: ['id' => $colegioId, 'nombre' => '', 'dane' => '', 'municipio' => ''];
    }

    /**
     * Año que se abre por defecto en SIMAT/SIMPADE: el «actual» si tiene matrículas activas; si no (p. ej. recién
     * promovido el año y aún sin matricular), el año más reciente que sí las tenga. Antes de esto los módulos
     * abrían un año vacío aunque hubiera datos en otro.
     */
    public static function anioPredeterminado(PDO $pdo, int $colegioId): ?array
    {
        $actual = Academico::anioActual($pdo, $colegioId);
        try {
            $q = $pdo->prepare("SELECT COUNT(*) FROM matriculas WHERE colegio_id = ? AND anio_lectivo_id = ? AND estado = 'activa'");
            if ($actual !== null) {
                $q->execute([$colegioId, (int) $actual['id']]);
                if ((int) $q->fetchColumn() > 0) {
                    return $actual;
                }
            }
            $q = $pdo->prepare("SELECT a.id FROM anios_lectivos a
                                 WHERE a.colegio_id = ? AND EXISTS (SELECT 1 FROM matriculas m WHERE m.anio_lectivo_id = a.id AND m.colegio_id = a.colegio_id AND m.estado = 'activa')
                                 ORDER BY a.anio DESC LIMIT 1");
            $q->execute([$colegioId]);
            $id = (int) $q->fetchColumn();
            if ($id > 0) {
                return Academico::anio($pdo, $id, $colegioId);
            }
        } catch (Throwable $e) {
            error_log('Simat::anioPredeterminado: ' . $e->getMessage());
        }
        return $actual;
    }

    /** Matrículas del año por estado: ['activa' => n, 'pendiente' => n, ...]. */
    public static function estadosMatricula(PDO $pdo, int $colegioId, int $anioId): array
    {
        $r = [];
        try {
            $q = $pdo->prepare('SELECT estado, COUNT(*) AS n FROM matriculas WHERE colegio_id = ? AND anio_lectivo_id = ? GROUP BY estado');
            $q->execute([$colegioId, $anioId]);
            foreach ($q as $f) {
                $r[(string) $f['estado']] = (int) $f['n'];
            }
        } catch (Throwable $e) {
            error_log('Simat::estadosMatricula: ' . $e->getMessage());
        }
        return $r;
    }

    /** Años del colegio que sí tienen matrículas activas (para sugerir a cuál cambiar cuando el elegido está vacío). */
    public static function aniosConActivas(PDO $pdo, int $colegioId): array
    {
        try {
            $q = $pdo->prepare("SELECT a.id, a.anio, COUNT(*) AS n FROM matriculas m INNER JOIN anios_lectivos a ON a.id = m.anio_lectivo_id
                                 WHERE m.colegio_id = ? AND m.estado = 'activa' GROUP BY a.id, a.anio ORDER BY a.anio DESC");
            $q->execute([$colegioId]);
            return $q->fetchAll();
        } catch (Throwable $e) {
            error_log('Simat::aniosConActivas: ' . $e->getMessage());
            return [];
        }
    }

    /** Ejecuta una consulta opcional: si falla (tabla o columna ausente) lo registra y devuelve $defecto en vez de cortar la página. */
    public static function opcional(callable $f, mixed $defecto = []): mixed
    {
        try {
            return $f();
        } catch (Throwable $e) {
            error_log('SIMAT consulta opcional: ' . $e->getMessage());
            return $defecto;
        }
    }

    public static function ajustes(PDO $pdo, int $colegioId): array
    {
        $col = configuracionObtener($pdo, 'simat_columnas', '', $colegioId);
        $claves = array_values(array_filter(array_map('trim', explode(',', $col)), static fn (string $k): bool => isset(self::COLUMNAS[$k])));
        if (!$claves) {
            $claves = array_values(array_diff(array_keys(self::COLUMNAS), self::COLUMNAS_OPCIONALES));
        }
        $sep = configuracionObtener($pdo, 'simat_separador', ';', $colegioId);
        $fmt = configuracionObtener($pdo, 'simat_formato_fecha', 'd/m/Y', $colegioId);
        return [
            'sede' => configuracionObtener($pdo, 'simat_sede', '', $colegioId),
            'dane_sede' => configuracionObtener($pdo, 'simat_dane_sede', '', $colegioId),
            'jornada' => configuracionObtener($pdo, 'simat_jornada', '', $colegioId),
            'metodologia' => configuracionObtener($pdo, 'simat_metodologia', 'Educación tradicional', $colegioId),
            'formato_fecha' => isset(self::FORMATOS_FECHA[$fmt]) ? $fmt : 'd/m/Y',
            'separador' => isset(self::SEPARADORES[$sep]) ? $sep : ';',
            'columnas' => $claves,
        ];
    }

    /** Guarda los ajustes. Devuelve un error o ''. */
    public static function guardarAjustes(PDO $pdo, int $colegioId, array $d): string
    {
        $sede = mb_substr(trim((string) ($d['sede'] ?? '')), 0, 120);
        $daneSede = (string) preg_replace('/\D+/', '', (string) ($d['dane_sede'] ?? ''));
        $jornada = trim((string) ($d['jornada'] ?? ''));
        $metodologia = mb_substr(trim((string) ($d['metodologia'] ?? '')), 0, 80);
        $fmt = (string) ($d['formato_fecha'] ?? 'd/m/Y');
        $sepEntrada = (string) ($d['separador'] ?? ';');
        $sep = $sepEntrada === 'tab' ? "\t" : $sepEntrada;
        if ($daneSede !== '' && strlen($daneSede) > 15) {
            return 'El código DANE de la sede no es válido (solo números, máximo 15 dígitos).';
        }
        if ($jornada !== '' && !in_array($jornada, self::JORNADAS, true)) {
            return 'Elige una jornada de la lista.';
        }
        if (!isset(self::FORMATOS_FECHA[$fmt])) {
            return 'Formato de fecha no válido.';
        }
        if (!isset(self::SEPARADORES[$sep])) {
            return 'Separador no válido.';
        }
        $cols = [];
        foreach ((array) ($d['columnas'] ?? []) as $k) {
            if (isset(self::COLUMNAS[(string) $k]) && !in_array((string) $k, $cols, true)) {
                $cols[] = (string) $k;
            }
        }
        if (!$cols) {
            return 'Elige al menos una columna para el CSV.';
        }
        configuracionGuardar($pdo, 'simat_sede', $sede, $colegioId);
        configuracionGuardar($pdo, 'simat_dane_sede', $daneSede, $colegioId);
        configuracionGuardar($pdo, 'simat_jornada', $jornada, $colegioId);
        configuracionGuardar($pdo, 'simat_metodologia', $metodologia, $colegioId);
        configuracionGuardar($pdo, 'simat_formato_fecha', $fmt, $colegioId);
        configuracionGuardar($pdo, 'simat_separador', $sep === ';' ? '' : $sep, $colegioId);
        configuracionGuardar($pdo, 'simat_columnas', implode(',', $cols), $colegioId);
        foreach ((array) ($d['codigo_grado'] ?? []) as $gid => $cod) {
            $gid = (int) $gid;
            $cod = trim((string) $cod);
            $g = $pdo->prepare('SELECT 1 FROM grados WHERE id = ? AND colegio_id = ?');
            $g->execute([$gid, $colegioId]);
            if ($gid <= 0 || !$g->fetchColumn()) {
                continue;
            }
            if ($cod !== '' && preg_match('/^-?\d{1,2}$/', $cod) !== 1) {
                return 'Los códigos de grado son números (por ejemplo -1, 0, 1… 11).';
            }
            configuracionGuardar($pdo, 'simat_codigo_grado_' . $gid, $cod, $colegioId);
        }
        return '';
    }

    // ------------------------------------------------------------------ estudiantes

    /**
     * Estudiantes con matrícula en el año (por defecto, solo las activas) con todos los campos del SIMAT y
     * sus problemas de validación. Cada elemento: id, matricula_id, nombre, d (valores por columna), ext, errores, advertencias.
     */
    public static function estudiantes(PDO $pdo, int $colegioId, int $anioId, array $estados = ['activa']): array
    {
        $anio = Academico::anio($pdo, $anioId, $colegioId);
        if ($anio === null) {
            return [];
        }
        $ajustes = self::ajustes($pdo, $colegioId);
        $col = self::colegio($pdo, $colegioId);
        $in = implode(',', array_map(static fn (string $s): string => $pdo->quote($s), $estados));
        $codMat = self::columnaExiste($pdo, 'usuarios', 'codigo_matricula') ? 'u.codigo_matricula' : 'NULL';
        // Si falta la tabla de hoja de vida o de inscripciones, las columnas salen vacías en vez de romper el listado.
        $conHv = self::tablaExiste($pdo, 'hojas_vida');
        $conIns = self::tablaExiste($pdo, 'inscripciones');
        $hvSel = $conHv
            ? 'hv.tipo_documento AS hv_tipo, hv.numero_documento AS hv_numero, hv.genero AS hv_genero, hv.lugar_nacimiento AS hv_lugar_nac,
                       hv.direccion AS hv_direccion, hv.barrio AS hv_barrio, hv.eps AS hv_eps, hv.telefono_alterno AS hv_tel'
            : 'NULL AS hv_tipo, NULL AS hv_numero, NULL AS hv_genero, NULL AS hv_lugar_nac, NULL AS hv_direccion, NULL AS hv_barrio, NULL AS hv_eps, NULL AS hv_tel';
        $insSel = $conIns
            ? 'i.nombres AS i_nombres, i.apellidos AS i_apellidos, i.tipo_documento AS i_tipo, i.numero_documento AS i_numero,
                       i.fecha_nacimiento AS i_nacimiento, i.direccion AS i_direccion'
            : 'NULL AS i_nombres, NULL AS i_apellidos, NULL AS i_tipo, NULL AS i_numero, NULL AS i_nacimiento, NULL AS i_direccion';
        $hvJoin = $conHv ? 'LEFT JOIN hojas_vida hv ON hv.estudiante_id = u.id' : '';
        $insJoin = $conIns ? 'LEFT JOIN inscripciones i ON i.id = (SELECT MAX(i2.id) FROM inscripciones i2 WHERE i2.estudiante_id = m.estudiante_id)' : '';
        $sql = "SELECT m.id AS matricula_id, m.estudiante_id, m.tipo AS tipo_matricula, m.estado AS estado_matricula, m.grado_id, m.grupo_id,
                       u.nombre, u.correo, u.telefono, u.fecha_nacimiento, {$codMat} AS codigo_matricula,
                       gr.nombre AS grado, g.nombre AS grupo,
                       {$hvSel},
                       {$insSel}
                  FROM matriculas m
                  INNER JOIN usuarios u ON u.id = m.estudiante_id
                  INNER JOIN grados gr ON gr.id = m.grado_id
                  LEFT JOIN grupos g ON g.id = m.grupo_id
                  {$hvJoin}
                  {$insJoin}
                 WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado IN ({$in})
                 ORDER BY gr.nombre, g.nombre, u.nombre";
        $q = $pdo->prepare($sql);
        $q->execute([$colegioId, $anioId]);
        $filas = $q->fetchAll();
        if (!$filas) {
            return [];
        }
        $ids = array_map(static fn (array $f): int => (int) $f['estudiante_id'], $filas);
        $lista = implode(',', $ids);

        // Datos adicionales de la hoja de vida (clave/valor)
        $claves = ['apellido1', 'apellido2', 'nombre1', 'nombre2', 'estrato', 'zona', 'sisben', 'municipio_residencia', 'celular', 'repitente',
            'situacion_anterior', 'estudiante_antiguo', 'poblacion_vulnerable', 'grupo_etnico', 'indigena', 'discapacidad', 'genero'];
        // Estas lecturas son complementarias: si una tabla falta o falla, el estudiante igual aparece (con avisos de datos faltantes).
        $hvd = [];
        if (self::tablaExiste($pdo, 'hoja_vida_datos')) {
            $hvd = self::opcional(static function () use ($pdo, $lista, $claves): array {
                $o = [];
                foreach ($pdo->query("SELECT estudiante_id, clave, valor FROM hoja_vida_datos WHERE estudiante_id IN ({$lista}) AND clave IN ('" . implode("','", $claves) . "')") as $r) {
                    $o[(int) $r['estudiante_id']][(string) $r['clave']] = (string) ($r['valor'] ?? '');
                }
                return $o;
            });
        }
        $car = [];
        if (self::tablaExiste($pdo, 'caracterizacion_estudiante')) {
            $car = self::opcional(static function () use ($pdo, $lista): array {
                $o = [];
                foreach ($pdo->query("SELECT estudiante_id, victima_conflicto, poblacion_etnica, sisben, estrato FROM caracterizacion_estudiante WHERE estudiante_id IN ({$lista})") as $r) {
                    $o[(int) $r['estudiante_id']] = $r;
                }
                return $o;
            });
        }
        $sd = [];
        if (self::tablaExiste($pdo, 'simat_datos')) {
            $sd = self::opcional(static function () use ($pdo, $lista): array {
                $o = [];
                foreach ($pdo->query("SELECT * FROM simat_datos WHERE estudiante_id IN ({$lista})") as $r) {
                    $o[(int) $r['estudiante_id']] = $r;
                }
                return $o;
            });
        }
        $nee = [];
        if (self::tablaExiste($pdo, 'apoyos_pedagogicos')) {
            $nee = self::opcional(static function () use ($pdo, $lista): array {
                $o = [];
                foreach ($pdo->query("SELECT estudiante_id FROM apoyos_pedagogicos WHERE estado = 'activo' AND estudiante_id IN ({$lista})") as $r) {
                    $o[(int) $r['estudiante_id']] = true;
                }
                return $o;
            });
        }
        $codigosGrado = [];
        $fmtFecha = $ajustes['formato_fecha'];
        $corte = ((int) $anio['anio']) . '-' . self::CORTE_EDAD;

        $salida = [];
        foreach ($filas as $f) {
            $id = (int) $f['estudiante_id'];
            $h = $hvd[$id] ?? [];
            $c = $car[$id] ?? [];
            $s = $sd[$id] ?? [];
            $gid = (int) $f['grado_id'];
            if (!array_key_exists($gid, $codigosGrado)) {
                $codigosGrado[$gid] = self::codigoGradoColegio($pdo, $colegioId, $gid, (string) $f['grado']);
            }

            // Documento y nacimiento: hoja de vida; si no está, la última inscripción.
            $tipo = strtoupper(trim((string) ($f['hv_tipo'] ?: $f['i_tipo'] ?: '')));
            $numero = self::limpiarDocumento((string) ($f['hv_numero'] ?: $f['i_numero'] ?: ''));
            $nac = (string) ($f['fecha_nacimiento'] ?: $f['i_nacimiento'] ?: '');

            // Nombres y apellidos: hoja de vida > inscripción > separar el nombre completo (se avisa).
            $inferido = false;
            $ap1 = trim($h['apellido1'] ?? '');
            $ap2 = trim($h['apellido2'] ?? '');
            $no1 = trim($h['nombre1'] ?? '');
            $no2 = trim($h['nombre2'] ?? '');
            if ($ap1 === '' || $no1 === '') {
                if (trim((string) $f['i_apellidos']) !== '' && trim((string) $f['i_nombres']) !== '') {
                    [$ap1, $ap2] = self::partir((string) $f['i_apellidos']);
                    [$no1, $no2] = self::partir((string) $f['i_nombres']);
                } else {
                    [$no1, $no2, $ap1, $ap2] = self::separarNombreCompleto((string) $f['nombre']);
                }
                $inferido = true;
            }
            $genero = strtoupper(trim((string) ($f['hv_genero'] ?: ($h['genero'] ?? ''))));
            $estrato = trim((string) ($h['estrato'] ?? '')) !== '' ? trim((string) $h['estrato']) : trim((string) ($c['estrato'] ?? ''));
            $sisben = trim((string) ($h['sisben'] ?? '')) !== '' ? trim((string) $h['sisben']) : trim((string) ($c['sisben'] ?? ''));
            $vulnerable = self::norm((string) ($h['poblacion_vulnerable'] ?? ''));
            $victima = !empty($c['victima_conflicto']) || ($vulnerable !== '' && $vulnerable !== 'ninguna' && str_contains($vulnerable, 'victima'))
                || str_contains($vulnerable, 'desplaz') ? 'S' : ((isset($c['victima_conflicto']) || $vulnerable !== '') ? 'N' : '');
            $etnia = trim((string) ($h['grupo_etnico'] ?? ''));
            if ($etnia === '' && trim((string) ($c['poblacion_etnica'] ?? '')) !== '') {
                $etnia = trim((string) $c['poblacion_etnica']);
            }
            if ($etnia === '' && self::sn($h['indigena'] ?? '') === 'S') {
                $etnia = 'Indígena';
            }
            $nuevo = $f['tipo_matricula'] === 'nueva' ? 'S' : 'N';
            $situ = trim((string) ($h['situacion_anterior'] ?? ''));
            $telefono = trim((string) ($f['telefono'] ?: ($h['celular'] ?? '') ?: $f['hv_tel'] ?: ''));

            $d = [
                'dane_ie' => (string) $col['dane'], 'sede' => $ajustes['sede'], 'jornada' => $ajustes['jornada'],
                'grado' => $codigosGrado[$gid] ?? '', 'grado_nombre' => (string) $f['grado'], 'grupo' => (string) ($f['grupo'] ?? ''),
                'tipo_documento' => $tipo, 'numero_documento' => $numero,
                'apellido1' => $ap1, 'apellido2' => $ap2, 'nombre1' => $no1, 'nombre2' => $no2,
                'fecha_nacimiento' => $nac,
                'genero' => $genero, 'municipio_nacimiento' => trim((string) $f['hv_lugar_nac']),
                'direccion' => trim((string) ($f['hv_direccion'] ?: $f['i_direccion'] ?: '')), 'barrio' => trim((string) $f['hv_barrio']),
                'municipio_residencia' => trim((string) ($h['municipio_residencia'] ?? '')), 'zona' => trim((string) ($h['zona'] ?? '')),
                'estrato' => $estrato, 'sisben' => $sisben, 'eps' => trim((string) $f['hv_eps']), 'telefono' => $telefono, 'correo' => (string) $f['correo'],
                'estudiante_nuevo' => $nuevo, 'repitente' => self::sn($h['repitente'] ?? ''), 'situacion_anterior' => $situ,
                'proviene_de' => (string) ($s['proviene_de'] ?? ''), 'victima_conflicto' => $victima, 'etnia' => $etnia,
                'discapacidad' => trim((string) ($h['discapacidad'] ?? '')) !== '' ? trim((string) $h['discapacidad']) : (isset($nee[$id]) ? 'Con apoyo pedagógico (PIAR)' : ''),
                'capacidad_excepcional' => (string) ($s['capacidad_excepcional'] ?? ''),
                'subsidio_condicionado' => self::snDesdeBool($s['subsidio_condicionado'] ?? null),
                'internado' => self::snDesdeBool($s['internado'] ?? null), 'matricula_contratada' => self::snDesdeBool($s['matricula_contratada'] ?? null),
                'metodologia' => $ajustes['metodologia'],
                'codigo_matricula' => $f['codigo_matricula'] !== null ? str_pad((string) $f['codigo_matricula'], 6, '0', STR_PAD_LEFT) : '',
            ];
            $rec = [
                'id' => $id, 'matricula_id' => (int) $f['matricula_id'], 'nombre' => (string) $f['nombre'], 'estado_matricula' => (string) $f['estado_matricula'],
                'grado_id' => $gid, 'd' => $d, 'ext' => ['inferido' => $inferido, 'edad' => $nac !== '' ? self::edadAl($nac, $corte) : null],
                'errores' => [], 'advertencias' => [],
            ];
            self::validar($rec, $col, $ajustes);
            $salida[] = $rec;
        }

        // Documentos repetidos entre estudiantes del colegio
        $conteo = [];
        foreach ($salida as $r) {
            if ($r['d']['numero_documento'] !== '') {
                $conteo[$r['d']['numero_documento']] = ($conteo[$r['d']['numero_documento']] ?? 0) + 1;
            }
        }
        foreach ($salida as &$r) {
            $n = $r['d']['numero_documento'];
            if ($n !== '' && ($conteo[$n] ?? 0) > 1) {
                $r['errores'][] = ['c' => 'doc_repetido', 'campo' => 'numero_documento', 'm' => 'El número de documento está repetido en otro estudiante.'];
            }
            $d = &$r['d'];
            // formato de salida
            if ($d['fecha_nacimiento'] !== '') {
                $t = strtotime($d['fecha_nacimiento']);
                $d['fecha_nacimiento_fmt'] = $t ? date($fmtFecha, $t) : $d['fecha_nacimiento'];
            } else {
                $d['fecha_nacimiento_fmt'] = '';
            }
            unset($d);
        }
        unset($r);
        return $salida;
    }

    /** «Pérez Gómez» => ['Pérez', 'Gómez']. */
    private static function partir(string $t): array
    {
        $p = preg_split('/\s+/', trim($t), 2) ?: [''];
        return [$p[0] ?? '', $p[1] ?? ''];
    }

    /** Separa «Juan Camilo Pérez Gómez» (nombres primero) cuando no hay datos separados. Es una suposición: se avisa. */
    private static function separarNombreCompleto(string $nombre): array
    {
        $p = preg_split('/\s+/', trim($nombre)) ?: [];
        $n = count($p);
        if ($n >= 4) {
            return [$p[0], implode(' ', array_slice($p, 1, $n - 3)), $p[$n - 2], $p[$n - 1]];
        }
        if ($n === 3) {
            return [$p[0], '', $p[1], $p[2]];
        }
        if ($n === 2) {
            return [$p[0], '', $p[1], ''];
        }
        return [$p[0] ?? '', '', '', ''];
    }

    private static function validar(array &$rec, array $col, array $ajustes): void
    {
        $d = $rec['d'];
        $e = static function (string $c, string $campo, string $m) use (&$rec): void {
            $rec['errores'][] = ['c' => $c, 'campo' => $campo, 'm' => $m];
        };
        $a = static function (string $c, string $campo, string $m) use (&$rec): void {
            $rec['advertencias'][] = ['c' => $c, 'campo' => $campo, 'm' => $m];
        };

        // Documento
        $tipo = $d['tipo_documento'];
        $num = $d['numero_documento'];
        if ($tipo === '') {
            $e('sin_tipo_doc', 'tipo_documento', 'Falta el tipo de documento.');
        } elseif (!isset(self::DOCUMENTOS[$tipo])) {
            $e('tipo_doc_invalido', 'tipo_documento', "Tipo de documento «{$tipo}» no reconocido.");
        }
        if ($num === '') {
            $e('sin_num_doc', 'numero_documento', 'Falta el número de documento.');
        } else {
            $soloDigitos = !in_array($tipo, ['CE', 'PPT', 'PEP', 'NES'], true);
            if ($soloDigitos && preg_match('/^\d+$/', $num) !== 1) {
                $e('num_doc_invalido', 'numero_documento', 'El número de documento debe tener solo dígitos.');
            } elseif (strlen($num) < 4 || strlen($num) > 15) {
                $e('num_doc_invalido', 'numero_documento', 'El número de documento tiene una longitud no válida.');
            } elseif (preg_match('/^(\d)\1+$/', $num) === 1 || in_array($num, ['123456', '1234567', '12345678', '123456789', '1234567890'], true)) {
                $e('num_doc_invalido', 'numero_documento', 'El número de documento parece un valor de relleno.');
            }
        }

        // Nombres
        if ($d['apellido1'] === '') {
            $e('sin_apellido', 'apellido1', 'Falta el primer apellido.');
        }
        if ($d['nombre1'] === '') {
            $e('sin_nombre', 'nombre1', 'Falta el primer nombre.');
        }
        if ($rec['ext']['inferido']) {
            $a('nombre_inferido', 'nombre1', 'Nombres y apellidos separados por suposición: complétalos en la hoja de vida (Datos personales).');
        }

        // Nacimiento y coherencia con el documento
        $edad = $rec['ext']['edad'];
        if ($d['fecha_nacimiento'] === '') {
            $e('sin_nacimiento', 'fecha_nacimiento', 'Falta la fecha de nacimiento.');
        } elseif ($edad === null) {
            $e('nacimiento_invalido', 'fecha_nacimiento', 'La fecha de nacimiento no es válida.');
        } elseif ($edad < 0) {
            $e('nacimiento_futuro', 'fecha_nacimiento', 'La fecha de nacimiento es posterior al año lectivo.');
        } else {
            if ($edad < 2 || $edad > 25) {
                $a('edad_improbable', 'fecha_nacimiento', "Edad poco probable para un estudiante ({$edad} años): verifica la fecha de nacimiento.");
            }
            if ($tipo === 'CC' && $edad < 18) {
                $e('cc_menor', 'tipo_documento', 'Un menor de 18 años no puede tener cédula de ciudadanía.');
            } elseif ($tipo === 'TI' && $edad >= 19) {
                $a('ti_adulto', 'tipo_documento', 'Tiene 19 años o más con tarjeta de identidad: debería tener cédula.');
            } elseif ($tipo === 'RC' && $edad >= 8) {
                $a('rc_mayor', 'tipo_documento', 'Tiene 8 años o más con registro civil: debería tener tarjeta de identidad.');
            }
        }
        if ($d['genero'] === '') {
            $e('sin_genero', 'genero', 'Falta el género.');
        } elseif (!in_array($d['genero'], ['F', 'M'], true)) {
            $a('genero_otro', 'genero', 'El género «' . $d['genero'] . '» puede no tener código en el SIMAT: verifica cómo se reporta.');
        }

        // Ubicación académica
        if ($d['grado'] === '') {
            $e('sin_codigo_grado', 'grado', 'El grado «' . $d['grado_nombre'] . '» no tiene código SIMAT: asígnalo en Ajustes.');
        } elseif ($edad !== null && $edad >= 0) {
            $esperada = (int) $d['grado'] + 5;
            if ($edad - $esperada >= 3) {
                $a('extraedad', 'grado', "Extraedad: tiene {$edad} años y el grado corresponde a unos {$esperada}.");
            } elseif ($esperada - $edad >= 3) {
                $a('edad_baja', 'grado', "Edad baja para el grado ({$edad} años; lo esperado es ~{$esperada}).");
            }
        }
        if ($d['grupo'] === '') {
            $e('sin_grupo', 'grupo', 'La matrícula no tiene grupo asignado.');
        }

        // Datos complementarios
        if ($d['estrato'] === '') {
            $a('sin_estrato', 'estrato', 'Falta el estrato.');
        }
        if ($d['zona'] === '') {
            $a('sin_zona', 'zona', 'Falta la zona (urbana o rural).');
        }
        if ($d['direccion'] === '' || $d['municipio_residencia'] === '') {
            $a('sin_residencia', 'direccion', 'Falta la dirección o el municipio de residencia.');
        }
        if ($d['eps'] === '') {
            $a('sin_eps', 'eps', 'Falta la EPS.');
        }
        if ($d['estudiante_nuevo'] === 'S' && $d['proviene_de'] === '') {
            $a('sin_procedencia', 'proviene_de', 'Es estudiante nuevo y falta de dónde proviene.');
        }
        if ($d['repitente'] === '' ) {
            $a('sin_repitente', 'repitente', 'Falta indicar si es repitente.');
        }
    }

    /** Verificaciones del establecimiento (no de un estudiante). Cada ítem: nivel (error|aviso|ok), texto. */
    public static function verificacionesGenerales(PDO $pdo, int $colegioId, ?array $anio, array $estudiantes): array
    {
        $out = [];
        $col = self::colegio($pdo, $colegioId);
        $ajustes = self::ajustes($pdo, $colegioId);
        $dane = (string) $col['dane'];
        if (!Colegio::daneValido($dane) || $dane === self::DANE_MARCADOR) {
            $out[] = ['nivel' => 'error', 'texto' => 'El código DANE del colegio no es válido (o es el marcador de instalación). Lo corrige el superadmin en Colegios.'];
        } else {
            $out[] = ['nivel' => 'ok', 'texto' => 'Código DANE del establecimiento: ' . $dane . '.'];
        }
        if ($ajustes['sede'] === '' || $ajustes['jornada'] === '') {
            $out[] = ['nivel' => 'error', 'texto' => 'Falta configurar la sede y la jornada en «Ajustes».'];
        } else {
            $out[] = ['nivel' => 'ok', 'texto' => 'Sede «' . $ajustes['sede'] . '», jornada ' . $ajustes['jornada'] . '.'];
        }
        if ($anio === null) {
            $out[] = ['nivel' => 'error', 'texto' => 'No hay un año lectivo seleccionado.'];
            return $out;
        }
        $q = $pdo->prepare("SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                             WHERE r.nombre = 'estudiante' AND u.activo = 1 AND u.colegio_id = ?
                               AND NOT EXISTS (SELECT 1 FROM matriculas m WHERE m.estudiante_id = u.id AND m.anio_lectivo_id = ? AND m.estado = 'activa')");
        $q->execute([$colegioId, (int) $anio['id']]);
        $sinMatricula = (int) $q->fetchColumn();
        if ($sinMatricula > 0) {
            $out[] = ['nivel' => 'aviso', 'texto' => "{$sinMatricula} estudiante(s) activo(s) no tienen matrícula activa en {$anio['anio']}: no saldrán en el reporte. Revísalos en Matrículas."];
        }
        $q = $pdo->prepare("SELECT COUNT(*) FROM matriculas WHERE colegio_id = ? AND anio_lectivo_id = ? AND estado = 'pendiente'");
        $q->execute([$colegioId, (int) $anio['id']]);
        $pend = (int) $q->fetchColumn();
        if ($pend > 0) {
            $out[] = ['nivel' => 'aviso', 'texto' => "Hay {$pend} matrícula(s) pendiente(s) de aprobar; no se incluyen hasta que queden activas."];
        }
        if (!$estudiantes) {
            $out[] = ['nivel' => 'aviso', 'texto' => 'No hay matrículas activas en este año.'];
        }
        return $out;
    }

    /** Totales de la validación + los problemas más frecuentes. */
    public static function resumen(array $estudiantes): array
    {
        $r = ['total' => count($estudiantes), 'con_errores' => 0, 'con_avisos' => 0, 'listos' => 0, 'problemas' => []];
        foreach ($estudiantes as $s) {
            if ($s['errores']) {
                $r['con_errores']++;
            } else {
                $r['listos']++;
            }
            if ($s['advertencias']) {
                $r['con_avisos']++;
            }
            foreach (array_merge($s['errores'], $s['advertencias']) as $p) {
                $k = $p['c'];
                $r['problemas'][$k] ??= ['m' => $p['m'], 'n' => 0, 'nivel' => in_array($p, $s['errores'], true) ? 'error' : 'aviso'];
                $r['problemas'][$k]['n']++;
            }
        }
        uasort($r['problemas'], static fn (array $a, array $b): int => $b['n'] <=> $a['n']);
        return $r;
    }

    // ------------------------------------------------------------------ exportación

    public static function celdaSegura(string $v): string
    {
        // Evita que Excel interprete el texto como fórmula.
        if ($v !== '' && (in_array($v[0], ['=', '+', '@', "\t", "\r"], true) || ($v[0] === '-' && preg_match('/^-\d+$/', $v) !== 1))) {
            return "'" . $v;
        }
        return $v;
    }

    /**
     * Escribe el CSV de matrícula en $salida. Devuelve [filasEscritas, filasOmitidas].
     * $soloValidos = true omite a los estudiantes con errores.
     */
    public static function escribirCsv($salida, array $estudiantes, array $ajustes, bool $soloValidos): array
    {
        fwrite($salida, "\xEF\xBB\xBF");
        $sep = $ajustes['separador'];
        $cols = $ajustes['columnas'];
        fputcsv($salida, array_map(static fn (string $k): string => self::COLUMNAS[$k], $cols), $sep, '"', '\\');
        $n = 0;
        $omitidas = 0;
        foreach ($estudiantes as $s) {
            if ($soloValidos && $s['errores']) {
                $omitidas++;
                continue;
            }
            $fila = [];
            foreach ($cols as $k) {
                $v = $k === 'fecha_nacimiento' ? (string) $s['d']['fecha_nacimiento_fmt'] : (string) ($s['d'][$k] ?? '');
                $fila[] = self::celdaSegura($v);
            }
            fputcsv($salida, $fila, $sep, '"', '\\');
            $n++;
        }
        return [$n, $omitidas];
    }

    public static function registrarReporte(PDO $pdo, int $colegioId, ?int $anioId, string $tipo, int $filas, int $errores, int $avisos, string $detalle, int $usuarioId): void
    {
        if (!self::tablaExiste($pdo, 'simat_reportes')) {
            return;
        }
        $pdo->prepare('INSERT INTO simat_reportes (colegio_id, anio_lectivo_id, tipo, filas, errores, advertencias, detalle, generado_por) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, $anioId, $tipo, $filas, $errores, $avisos, mb_substr($detalle, 0, 300), $usuarioId ?: null]);
    }

    public static function bitacora(PDO $pdo, int $colegioId, int $limite = 15): array
    {
        if (!self::tablaExiste($pdo, 'simat_reportes')) {
            return [];
        }
        $q = $pdo->prepare('SELECT r.*, u.nombre AS autor FROM simat_reportes r LEFT JOIN usuarios u ON u.id = r.generado_por WHERE r.colegio_id = ? ORDER BY r.id DESC LIMIT ' . (int) $limite);
        $q->execute([$colegioId]);
        return $q->fetchAll();
    }

    // ------------------------------------------------------------------ datos SIMAT por estudiante

    /** Guarda los datos propios del SIMAT de un estudiante. Devuelve un error o ''. */
    public static function guardarDatos(PDO $pdo, int $colegioId, int $estudianteId, array $d, int $usuarioId): string
    {
        $q = $pdo->prepare('SELECT 1 FROM usuarios WHERE id = ? AND colegio_id = ?');
        $q->execute([$estudianteId, $colegioId]);
        if (!$q->fetchColumn()) {
            return 'El estudiante no pertenece a este colegio.';
        }
        $proviene = (string) ($d['proviene_de'] ?? '');
        if ($proviene !== '' && !isset(self::PROVIENE[$proviene])) {
            return 'Procedencia no válida.';
        }
        $bool = static function (mixed $v): ?int {
            $v = (string) $v;
            return $v === 'S' ? 1 : ($v === 'N' ? 0 : null);
        };
        $capacidad = mb_substr(trim((string) ($d['capacidad_excepcional'] ?? '')), 0, 40);
        $pdo->prepare('INSERT INTO simat_datos (estudiante_id, colegio_id, proviene_de, subsidio_condicionado, internado, matricula_contratada, capacidad_excepcional, actualizado_por)
                       VALUES (?,?,?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE proviene_de = VALUES(proviene_de), subsidio_condicionado = VALUES(subsidio_condicionado), internado = VALUES(internado),
                           matricula_contratada = VALUES(matricula_contratada), capacidad_excepcional = VALUES(capacidad_excepcional), actualizado_por = VALUES(actualizado_por)')
            ->execute([$estudianteId, $colegioId, $proviene !== '' ? $proviene : null, $bool($d['subsidio_condicionado'] ?? ''), $bool($d['internado'] ?? ''),
                $bool($d['matricula_contratada'] ?? ''), $capacidad !== '' ? $capacidad : null, $usuarioId ?: null]);
        return '';
    }

    // ------------------------------------------------------------------ novedades

    public static function novedades(PDO $pdo, int $colegioId, int $anioId, string $estado = ''): array
    {
        if (!self::tablaExiste($pdo, 'simat_novedades')) {
            return [];
        }
        $sql = "SELECT n.*, u.nombre AS estudiante, COALESCE(NULLIF(hv.numero_documento, ''), '') AS documento, COALESCE(NULLIF(hv.tipo_documento, ''), '') AS tipo_documento,
                       r.nombre AS reporto
                  FROM simat_novedades n
                  INNER JOIN usuarios u ON u.id = n.estudiante_id
                  LEFT JOIN hojas_vida hv ON hv.estudiante_id = u.id
                  LEFT JOIN usuarios r ON r.id = n.reportada_por
                 WHERE n.colegio_id = ? AND n.anio_lectivo_id = ?";
        $par = [$colegioId, $anioId];
        if (in_array($estado, ['pendiente', 'reportada'], true)) {
            $sql .= ' AND n.estado = ?';
            $par[] = $estado;
        }
        $q = $pdo->prepare($sql . ' ORDER BY n.estado = \'pendiente\' DESC, n.fecha DESC, n.id DESC');
        $q->execute($par);
        return $q->fetchAll();
    }

    public static function contarPendientes(PDO $pdo, int $colegioId, int $anioId): int
    {
        if (!self::tablaExiste($pdo, 'simat_novedades')) {
            return 0;
        }
        $q = $pdo->prepare("SELECT COUNT(*) FROM simat_novedades WHERE colegio_id = ? AND anio_lectivo_id = ? AND estado = 'pendiente'");
        $q->execute([$colegioId, $anioId]);
        return (int) $q->fetchColumn();
    }

    /** Crea una novedad. Devuelve un error o ''. Si ya hay una pendiente igual para ese estudiante, no la duplica. */
    public static function crearNovedad(PDO $pdo, int $colegioId, int $anioId, int $estudianteId, string $tipo, string $fecha, string $causa, string $destino, string $obs, int $usuarioId, string $origen = 'manual', ?int $matriculaId = null): string
    {
        if (!self::tablaExiste($pdo, 'simat_novedades')) {
            return 'Falta ejecutar la migración 041_simat_simpade.sql.';
        }
        if (!isset(self::NOVEDADES[$tipo])) {
            return 'Elige un tipo de novedad.';
        }
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        if ($d === false || $d->format('Y-m-d') !== $fecha) {
            return 'La fecha no es válida.';
        }
        // El SIMAT exige motivo para retirar y el establecimiento de destino para trasladar (instructivos de las secretarías).
        if ($origen === 'manual' && $tipo === 'retiro' && trim($causa) === '') {
            return 'El retiro necesita un motivo: el SIMAT no deja guardarlo sin él.';
        }
        if ($origen === 'manual' && $tipo === 'traslado' && trim($destino) === '') {
            return 'El traslado necesita la institución (y sede) de destino.';
        }
        $q = $pdo->prepare('SELECT 1 FROM usuarios WHERE id = ? AND colegio_id = ?');
        $q->execute([$estudianteId, $colegioId]);
        if (!$q->fetchColumn()) {
            return 'El estudiante no pertenece a este colegio.';
        }
        $q = $pdo->prepare("SELECT 1 FROM simat_novedades WHERE anio_lectivo_id = ? AND estudiante_id = ? AND tipo = ? AND estado = 'pendiente'");
        $q->execute([$anioId, $estudianteId, $tipo]);
        if ($q->fetchColumn()) {
            return $origen === 'automatico' ? '' : 'Ya hay una novedad pendiente de ese tipo para el estudiante.';
        }
        $pdo->prepare('INSERT INTO simat_novedades (colegio_id, anio_lectivo_id, estudiante_id, matricula_id, tipo, fecha, causa, destino, observacion, origen, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, $anioId, $estudianteId, $matriculaId, $tipo, $fecha, mb_substr(trim($causa), 0, 150) ?: null, mb_substr(trim($destino), 0, 150) ?: null,
                mb_substr(trim($obs), 0, 300) ?: null, $origen, $usuarioId ?: null]);
        return '';
    }

    public static function marcarReportada(PDO $pdo, int $id, int $colegioId, int $usuarioId): void
    {
        $pdo->prepare("UPDATE simat_novedades SET estado = 'reportada', reportada_en = NOW(), reportada_por = ? WHERE id = ? AND colegio_id = ? AND estado = 'pendiente'")
            ->execute([$usuarioId ?: null, $id, $colegioId]);
    }

    public static function reabrirNovedad(PDO $pdo, int $id, int $colegioId): void
    {
        $pdo->prepare("UPDATE simat_novedades SET estado = 'pendiente', reportada_en = NULL, reportada_por = NULL WHERE id = ? AND colegio_id = ?")->execute([$id, $colegioId]);
    }

    public static function eliminarNovedad(PDO $pdo, int $id, int $colegioId): void
    {
        $pdo->prepare("DELETE FROM simat_novedades WHERE id = ? AND colegio_id = ? AND estado = 'pendiente'")->execute([$id, $colegioId]);
    }

    /**
     * Gancho: cuando una matrícula pasa a «retirada» (Academico::resolverMatricula), deja la novedad de retiro pendiente
     * de reportar al SIMAT. Nunca lanza excepciones hacia afuera: la matrícula ya quedó guardada.
     */
    public static function retiroAutomatico(PDO $pdo, int $matriculaId, int $colegioId, int $usuarioId): void
    {
        try {
            if (!self::tablaExiste($pdo, 'simat_novedades')) {
                return;
            }
            $q = $pdo->prepare('SELECT estudiante_id, anio_lectivo_id FROM matriculas WHERE id = ? AND colegio_id = ?');
            $q->execute([$matriculaId, $colegioId]);
            $m = $q->fetch();
            if ($m) {
                self::crearNovedad($pdo, $colegioId, (int) $m['anio_lectivo_id'], (int) $m['estudiante_id'], 'retiro', date('Y-m-d'), 'Matrícula retirada en Serena', '', '', $usuarioId, 'automatico', $matriculaId);
            }
        } catch (Throwable $e) {
            error_log('Simat::retiroAutomatico: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ conciliación

    private const SINONIMOS = [
        'tipo_documento' => ['tipo_documento', 'tipo_doc', 'tipo_de_documento', 'tipodoc', 'tipo_identificacion', 'tipo_de_identificacion', 'td'],
        'numero_documento' => ['numero_documento', 'nro_documento', 'num_documento', 'documento', 'identificacion', 'numero_de_documento', 'no_documento', 'numero_identificacion', 'numero_de_identificacion', 'nro_doc', 'num_doc'],
        'grado' => ['grado', 'cod_grado', 'codigo_grado', 'grado_cod', 'nombre_grado'],
        'grupo' => ['grupo', 'nombre_grupo', 'cod_grupo'],
        'estado' => ['estado', 'estado_matricula', 'situacion', 'estado_estudiante', 'novedad'],
        'apellido1' => ['primer_apellido', 'apellido1', 'apellido_1'],
        'apellido2' => ['segundo_apellido', 'apellido2', 'apellido_2'],
        'nombre1' => ['primer_nombre', 'nombre1', 'nombre_1'],
        'nombre2' => ['segundo_nombre', 'nombre2', 'nombre_2'],
        'apellidos' => ['apellidos'],
        'nombres' => ['nombres'],
        'nombre_completo' => ['nombre_completo', 'estudiante', 'nombre_estudiante', 'nombre'],
    ];

    private static function claveEncabezado(string $h): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', self::norm($h)), '_');
    }

    /**
     * Compara el listado descargado del SIMAT (CSV) con la matrícula activa de Serena.
     * Devuelve ['error' => '...'] o el resultado con listas: solo_serena, solo_simat, diferencias y los totales.
     */
    public static function conciliar(PDO $pdo, int $colegioId, int $anioId, string $ruta): array
    {
        $txt = @file_get_contents($ruta);
        if ($txt === false || trim($txt) === '') {
            return ['error' => 'No se pudo leer el archivo o está vacío.'];
        }
        if (str_starts_with($txt, "\xEF\xBB\xBF")) {
            $txt = substr($txt, 3);
        }
        if (!mb_check_encoding($txt, 'UTF-8')) {
            $txt = mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1');
        }
        $pos = strpos($txt, "\n");
        $primera = $pos === false ? $txt : substr($txt, 0, $pos);
        $cuentas = [';' => substr_count($primera, ';'), ',' => substr_count($primera, ','), "\t" => substr_count($primera, "\t")];
        arsort($cuentas);
        $delim = (string) array_key_first($cuentas);
        $h = fopen('php://temp', 'r+');
        fwrite($h, $txt);
        rewind($h);

        // Buscar la fila de encabezados entre las primeras 15 filas (algunos reportes traen títulos arriba).
        $mapa = [];
        $encontrado = false;
        for ($i = 0; $i < 15 && ($fila = fgetcsv($h, 0, $delim, '"', '\\')) !== false; $i++) {
            $m = [];
            foreach ($fila as $idx => $celda) {
                $c = self::claveEncabezado((string) $celda);
                foreach (self::SINONIMOS as $campo => $lista) {
                    if (!isset($m[$campo]) && in_array($c, $lista, true)) {
                        $m[$campo] = $idx;
                    }
                }
            }
            if (isset($m['numero_documento'])) {
                $mapa = $m;
                $encontrado = true;
                break;
            }
        }
        if (!$encontrado) {
            fclose($h);
            return ['error' => 'No encontré la columna del número de documento. El archivo debe tener un encabezado como «Número de documento» o «Documento».'];
        }

        $simat = [];
        $leidas = 0;
        while (($fila = fgetcsv($h, 0, $delim, '"', '\\')) !== false && $leidas < 60000) {
            $num = self::limpiarDocumento((string) ($fila[$mapa['numero_documento']] ?? ''));
            if ($num === '') {
                continue;
            }
            $leidas++;
            $v = static fn (string $campo): string => isset($mapa[$campo]) ? trim((string) ($fila[$mapa[$campo]] ?? '')) : '';
            $nombre = $v('nombre_completo');
            if ($nombre === '') {
                $nombre = trim($v('apellido1') . ' ' . $v('apellido2') . ' ' . $v('nombre1') . ' ' . $v('nombre2'));
            }
            if ($nombre === '') {
                $nombre = trim($v('apellidos') . ' ' . $v('nombres'));
            }
            $simat[$num] = ['tipo' => strtoupper($v('tipo_documento')), 'grado' => $v('grado'), 'grupo' => $v('grupo'), 'estado' => $v('estado'), 'nombre' => $nombre];
        }
        fclose($h);
        if (!$simat) {
            return ['error' => 'El archivo no tiene filas con número de documento.'];
        }

        $serena = [];
        foreach (self::estudiantes($pdo, $colegioId, $anioId) as $s) {
            if ($s['d']['numero_documento'] !== '') {
                $serena[$s['d']['numero_documento']] = $s;
            }
        }

        $res = ['error' => '', 'coinciden' => 0, 'retirados_ok' => 0, 'solo_serena' => [], 'solo_simat' => [], 'diferencias' => [],
            'total_simat' => count($simat), 'total_serena' => count($serena), 'columnas' => array_keys($mapa)];
        $nombreSerena = static fn (array $s): string => trim($s['d']['apellido1'] . ' ' . $s['d']['apellido2'] . ' ' . $s['d']['nombre1'] . ' ' . $s['d']['nombre2']);
        $gradoDe = static fn (array $s): string => (string) $s['d']['grado_nombre'];

        foreach ($simat as $num => $r) {
            $estado = self::norm($r['estado']);
            $retirado = $estado !== '' && (str_contains($estado, 'retir') || str_contains($estado, 'desert') || str_contains($estado, 'trasl') || str_contains($estado, 'inactiv'));
            if (!isset($serena[$num])) {
                if ($retirado) {
                    $res['retirados_ok']++;
                } else {
                    $res['solo_simat'][] = ['tipo' => $r['tipo'], 'numero' => $num, 'nombre' => $r['nombre'], 'grado_simat' => $r['grado'], 'grupo_simat' => $r['grupo'],
                        'detalle' => 'Aparece en el SIMAT y no tiene matrícula activa en Serena: revisa si debe matricularse aquí o retirarse en el SIMAT.'];
                }
                continue;
            }
            $s = $serena[$num];
            $dif = [];
            if ($retirado) {
                $dif[] = 'Está retirado en el SIMAT pero activo en Serena.';
            }
            if ($r['tipo'] !== '' && $s['d']['tipo_documento'] !== '' && $r['tipo'] !== $s['d']['tipo_documento']) {
                $dif[] = "Tipo de documento: SIMAT {$r['tipo']} / Serena {$s['d']['tipo_documento']}.";
            }
            if ($r['grado'] !== '') {
                $cs = self::codigoGrado($r['grado']);
                if ($cs !== null && $s['d']['grado'] !== '' && $cs !== $s['d']['grado']) {
                    $dif[] = "Grado: SIMAT «{$r['grado']}» / Serena «{$gradoDe($s)}».";
                }
            }
            if ($r['grupo'] !== '' && $s['d']['grupo'] !== '' && self::norm($r['grupo']) !== self::norm($s['d']['grupo'])) {
                $dif[] = "Grupo: SIMAT «{$r['grupo']}» / Serena «{$s['d']['grupo']}».";
            }
            if ($r['nombre'] !== '') {
                $tokensSimat = array_filter(explode(' ', self::norm($r['nombre'])));
                $faltan = 0;
                foreach (array_filter([$s['d']['apellido1'], $s['d']['nombre1']]) as $t) {
                    if (!in_array(self::norm($t), $tokensSimat, true)) {
                        $faltan++;
                    }
                }
                if ($faltan > 0 && !$s['ext']['inferido']) {
                    $dif[] = 'El nombre no coincide: SIMAT «' . $r['nombre'] . '» / Serena «' . $nombreSerena($s) . '».';
                }
            }
            if ($dif) {
                $res['diferencias'][] = ['tipo' => $s['d']['tipo_documento'], 'numero' => $num, 'nombre' => $nombreSerena($s), 'grado_serena' => $gradoDe($s), 'grupo_serena' => $s['d']['grupo'],
                    'grado_simat' => $r['grado'], 'grupo_simat' => $r['grupo'], 'detalle' => implode(' ', $dif)];
            } else {
                $res['coinciden']++;
            }
        }
        foreach ($serena as $num => $s) {
            if (!isset($simat[$num])) {
                $res['solo_serena'][] = ['tipo' => $s['d']['tipo_documento'], 'numero' => $num, 'nombre' => $nombreSerena($s), 'grado_serena' => $gradoDe($s), 'grupo_serena' => $s['d']['grupo'],
                    'detalle' => 'Matriculado en Serena y no aparece en el SIMAT: falta cargarlo (o el documento difiere).'];
            }
        }
        return $res;
    }

    /** Escribe el CSV con las diferencias de una conciliación. */
    public static function escribirCsvConciliacion($salida, array $res, string $sep): void
    {
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['CATEGORIA', 'TIPO_DOCUMENTO', 'NUMERO_DOCUMENTO', 'NOMBRE', 'GRADO_SERENA', 'GRUPO_SERENA', 'GRADO_SIMAT', 'GRUPO_SIMAT', 'DETALLE'], $sep, '"', '\\');
        foreach (['solo_serena' => 'Solo en Serena', 'solo_simat' => 'Solo en SIMAT', 'diferencias' => 'Diferencia'] as $k => $etq) {
            foreach ($res[$k] ?? [] as $f) {
                fputcsv($salida, array_map([self::class, 'celdaSegura'], [$etq, (string) ($f['tipo'] ?? ''), (string) $f['numero'], (string) $f['nombre'], (string) ($f['grado_serena'] ?? ''),
                    (string) ($f['grupo_serena'] ?? ''), (string) ($f['grado_simat'] ?? ''), (string) ($f['grupo_simat'] ?? ''), (string) $f['detalle']]), $sep, '"', '\\');
            }
        }
    }
}
