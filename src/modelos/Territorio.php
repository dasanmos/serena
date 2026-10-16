<?php
declare(strict_types=1);

/**
 * Organización territorial: Secretaría (ETC) → Municipio → Núcleo educativo → Colegio,
 * y Zonas del municipio (comunas, corregimientos, localidades, veredas).
 * Requiere la migración 033_territorio.sql.
 */
class Territorio
{
    public const TIPOS_ZONA = [
        'comuna' => 'Comuna', 'corregimiento' => 'Corregimiento', 'localidad' => 'Localidad', 'vereda' => 'Vereda',
    ];
    public const TIPOS_SECRETARIA = [
        'departamental' => 'Departamental', 'municipal' => 'Municipal', 'distrital' => 'Distrital',
    ];
    public const MAX_FILAS = 5000;

    /** Los 32 departamentos + Bogotá D.C. (nombre canónico con el que se guardan). */
    private const DEPARTAMENTOS = [
        'Amazonas', 'Antioquia', 'Arauca', 'Atlántico', 'Bogotá D.C.', 'Bolívar', 'Boyacá', 'Caldas', 'Caquetá', 'Casanare',
        'Cauca', 'Cesar', 'Chocó', 'Córdoba', 'Cundinamarca', 'Guainía', 'Guaviare', 'Huila', 'La Guajira', 'Magdalena',
        'Meta', 'Nariño', 'Norte de Santander', 'Putumayo', 'Quindío', 'Risaralda', 'San Andrés y Providencia', 'Santander',
        'Sucre', 'Tolima', 'Valle del Cauca', 'Vaupés', 'Vichada',
    ];

    // ------------------------------------------------------------------
    // Normalización de textos
    // ------------------------------------------------------------------

    /** Minúsculas, sin tildes ni signos: sirve para comparar nombres escritos de formas distintas. */
    public static function normalizar(string $t): string
    {
        $t = mb_strtolower(trim($t), 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? '';
        return trim($t);
    }

    /** Clave de comparación de un departamento (maneja «Bogotá, D.C.» y el nombre largo de San Andrés). */
    public static function claveDepartamento(string $d): string
    {
        $n = self::normalizar($d);
        if (strpos($n, 'san andres') !== false) {
            return 'san andres y providencia';
        }
        if (strpos($n, 'bogota') === 0) {
            return 'bogota d c';
        }
        return $n;
    }

    /** Nombre canónico del departamento (si no se reconoce, devuelve el texto ordenado). */
    public static function departamentoCanonico(string $d): string
    {
        static $mapa = null;
        if ($mapa === null) {
            $mapa = [];
            foreach (self::DEPARTAMENTOS as $nombre) {
                $mapa[self::claveDepartamento($nombre)] = $nombre;
            }
        }
        $clave = self::claveDepartamento($d);
        return $mapa[$clave] ?? self::tituloPropio($d);
    }

    /** «MEDELLÍN» → «Medellín», «SAN JOSÉ DEL GUAVIARE» → «San José del Guaviare». Solo cambia textos TODO EN MAYÚSCULAS. */
    public static function tituloPropio(string $t): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? '');
        if ($t === '' || mb_strtoupper($t, 'UTF-8') !== $t) {
            return $t;
        }
        $r = mb_convert_case(mb_strtolower($t, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $r = preg_replace_callback('/\b(De|Del|La|Las|Los|El|Y)\b/u', static fn (array $m): string => mb_strtolower($m[1], 'UTF-8'), $r) ?? $r;
        $r = str_replace('D.c.', 'D.C.', $r);
        return mb_strtoupper(mb_substr($r, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($r, 1, null, 'UTF-8');
    }

    // ------------------------------------------------------------------
    // Lectura de CSV (acepta , o ; y UTF-8 o Windows-1252, como exporta Excel en español)
    // ------------------------------------------------------------------

    /**
     * @return array{0: array<int, array<string, string>>, 1: string} filas (encabezados normalizados con _) y mensaje de error.
     *         Cada fila trae además '_n' = número de fila en el archivo (el encabezado es la 1).
     */
    public static function leerCsv(string $ruta): array
    {
        $contenido = @file_get_contents($ruta);
        if ($contenido === false || trim($contenido) === '') {
            return [[], 'El archivo está vacío o no se pudo leer.'];
        }
        if (strncmp($contenido, "\xEF\xBB\xBF", 3) === 0) {
            $contenido = substr($contenido, 3);
        }
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $primera = (string) strtok($contenido, "\r\n");
        $sep = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

        $h = fopen('php://temp', 'r+');
        fwrite($h, $contenido);
        rewind($h);
        $enc = fgetcsv($h, 0, $sep, '"', '');
        if (!$enc) {
            fclose($h);
            return [[], 'No se encontró la fila de encabezados.'];
        }
        // Alias: el archivo oficial DIVIPOLA del DANE usa estos encabezados.
        $alias = [
            'nombre_departamento' => 'departamento', 'dpto' => 'departamento', 'nombre_dpto' => 'departamento',
            'nombre_municipio' => 'municipio', 'mpio' => 'municipio', 'nombre_mpio' => 'municipio',
            'codigo_municipio' => 'codigo_dane', 'codigo_dane_del_municipio' => 'codigo_dane', 'cod_mpio' => 'codigo_dane',
        ];
        $cols = array_map(static function ($c) use ($alias): string {
            $k = str_replace(' ', '_', self::normalizar((string) $c));
            return $alias[$k] ?? $k;
        }, $enc);

        $filas = [];
        $n = 0;
        while (($r = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            $n++;
            if (!array_filter($r, static fn ($v): bool => trim((string) $v) !== '')) {
                continue; // fila vacía
            }
            if (count($filas) >= self::MAX_FILAS) {
                fclose($h);
                return [$filas, 'El archivo tiene más de ' . self::MAX_FILAS . ' filas: divídelo en partes.'];
            }
            $fila = [];
            foreach ($cols as $i => $c) {
                $fila[$c] = trim((string) ($r[$i] ?? ''));
            }
            $fila['_n'] = (string) ($n + 1);
            $filas[] = $fila;
        }
        fclose($h);
        return [$filas, ''];
    }

    /** Devuelve las columnas obligatorias que no están en el archivo. */
    public static function columnasFaltantes(array $filas, array $requeridas): array
    {
        if (!$filas) {
            return [];
        }
        return array_values(array_filter($requeridas, static fn (string $c): bool => !array_key_exists($c, $filas[0])));
    }

    // ------------------------------------------------------------------
    // Búsquedas auxiliares
    // ------------------------------------------------------------------

    /** @return array<string,int> nombre normalizado => id */
    private static function mapaSecretarias(PDO $pdo): array
    {
        $m = [];
        foreach ($pdo->query('SELECT id, nombre FROM secretarias') as $r) {
            $m[self::normalizar((string) $r['nombre'])] = (int) $r['id'];
        }
        return $m;
    }

    /** Busca una secretaría por nombre completo o por el territorio («Medellín», «Antioquia»). */
    private static function idSecretariaPorTexto(array $mapa, string $texto): ?int
    {
        $n = self::normalizar($texto);
        if ($n === '') {
            return null;
        }
        foreach ([$n, 'secretaria de educacion de ' . $n, 'secretaria de educacion departamental de ' . $n] as $clave) {
            if (isset($mapa[$clave])) {
                return $mapa[$clave];
            }
        }
        return null;
    }

    /**
     * Busca un municipio por nombre (y departamento si se da). Devuelve el id, null si no existe
     * o false si el nombre es ambiguo (hay varios y no se pudo desempatar).
     */
    public static function buscarMunicipioId(PDO $pdo, string $nombre, string $departamento = '', ?int $secretariaId = null)
    {
        $q = $pdo->prepare('SELECT id, departamento, secretaria_id FROM municipios WHERE nombre = ?');
        $q->execute([self::tituloPropio($nombre)]);
        $cand = $q->fetchAll();
        if ($departamento !== '') {
            $clave = self::claveDepartamento($departamento);
            $cand = array_values(array_filter($cand, static fn (array $m): bool => self::claveDepartamento((string) $m['departamento']) === $clave));
        }
        if (count($cand) > 1 && $secretariaId !== null) {
            $cand = array_values(array_filter($cand, static fn (array $m): bool => (int) $m['secretaria_id'] === $secretariaId));
        }
        if (count($cand) === 1) {
            return (int) $cand[0]['id'];
        }
        return count($cand) === 0 ? null : false;
    }

    /** Crea o actualiza un municipio. Devuelve [id, creado]. */
    private static function guardarMunicipio(PDO $pdo, string $departamento, string $nombre, ?string $codigo, ?int $secretariaId, bool $sobrescribirSecretaria): array
    {
        $q = $pdo->prepare('SELECT id, secretaria_id FROM municipios WHERE departamento = ? AND nombre = ? LIMIT 1');
        $q->execute([$departamento, $nombre]);
        $m = $q->fetch();
        if (!$m) {
            $pdo->prepare('INSERT INTO municipios (departamento, nombre, codigo_dane, secretaria_id) VALUES (?,?,?,?)')
                ->execute([$departamento, $nombre, $codigo, $secretariaId]);
            return [(int) $pdo->lastInsertId(), true];
        }
        $nueva = ($sobrescribirSecretaria || $m['secretaria_id'] === null) ? ($secretariaId ?? $m['secretaria_id']) : $m['secretaria_id'];
        $pdo->prepare('UPDATE municipios SET codigo_dane = COALESCE(?, codigo_dane), secretaria_id = ? WHERE id = ?')
            ->execute([$codigo, $nueva, $m['id']]);
        return [(int) $m['id'], false];
    }

    /** Compara dos columnas de texto aunque sus tablas tengan distinta collation (evita «Illegal mix of collations»). */
    private static function igual(string $a, string $b): string
    {
        return "CONVERT({$a} USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT({$b} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    /** Enlaza colegios existentes con el catálogo (solo si el nombre del municipio es único) y les hereda la secretaría. */
    public static function sincronizarColegios(PDO $pdo): int
    {
        $pdo->exec('UPDATE colegios c INNER JOIN municipios m ON ' . self::igual('m.nombre', 'c.municipio') . '
                       SET c.municipio_id = m.id
                     WHERE c.municipio_id IS NULL
                       AND (SELECT COUNT(*) FROM municipios x WHERE ' . self::igual('x.nombre', 'c.municipio') . ') = 1');
        $n = $pdo->exec('UPDATE colegios c INNER JOIN municipios m ON m.id = c.municipio_id
                            SET c.secretaria_id = m.secretaria_id
                          WHERE c.secretaria_id IS NULL AND m.secretaria_id IS NOT NULL');
        return (int) $n;
    }

    // ------------------------------------------------------------------
    // Importadores. Cada uno devuelve ['ok' => n, 'nuevos' => n, 'omitidas' => [mensajes]]
    // ------------------------------------------------------------------

    /** Columnas: departamento, tipo, nombre, municipio (esta última solo para municipales/distritales). */
    public static function importarSecretarias(PDO $pdo, array $filas): array
    {
        $res = ['ok' => 0, 'nuevos' => 0, 'municipios' => 0, 'omitidas' => []];
        $mapa = self::mapaSecretarias($pdo);
        foreach ($filas as $f) {
            $nombre = trim($f['nombre'] ?? '');
            $dep = self::departamentoCanonico($f['departamento'] ?? '');
            $mun = self::tituloPropio($f['municipio'] ?? '');
            $tipo = self::normalizar($f['tipo'] ?? '');
            if ($tipo === '') {
                $tipo = $mun !== '' ? 'municipal' : 'departamental';
            }
            if ($nombre === '' || mb_strlen($nombre) > 150) {
                $res['omitidas'][] = "Fila {$f['_n']}: nombre vacío o de más de 150 caracteres.";
                continue;
            }
            if (!isset(self::TIPOS_SECRETARIA[$tipo])) {
                $res['omitidas'][] = "Fila {$f['_n']} ({$nombre}): tipo «{$f['tipo']}» no válido (usa departamental, municipal o distrital).";
                continue;
            }
            if ($dep === '') {
                $res['omitidas'][] = "Fila {$f['_n']} ({$nombre}): falta el departamento.";
                continue;
            }
            $clave = self::normalizar($nombre);
            if (isset($mapa[$clave])) {
                $sid = $mapa[$clave];
                // Conserva lo que ya estaba: solo completa departamento y tipo si la secretaría aún no los tenía.
                $pdo->prepare("UPDATE secretarias SET tipo = ?, departamento = ? WHERE id = ? AND (departamento IS NULL OR departamento = '')")
                    ->execute([$tipo, $dep, $sid]);
            } else {
                $pdo->prepare('INSERT INTO secretarias (nombre, departamento, tipo) VALUES (?,?,?)')->execute([$nombre, $dep, $tipo]);
                $sid = (int) $pdo->lastInsertId();
                $mapa[$clave] = $sid;
                $res['nuevos']++;
            }
            $res['ok']++;

            if ($tipo === 'departamental') {
                // Los municipios del departamento que aún no tienen secretaría (los no certificados) pasan a esta.
                $pdo->prepare('UPDATE municipios SET secretaria_id = ? WHERE secretaria_id IS NULL AND departamento = ?')->execute([$sid, $dep]);
            }
            if ($tipo !== 'departamental' && $mun !== '') {
                self::guardarMunicipio($pdo, $dep, $mun, null, $sid, true);
                $pdo->prepare('INSERT IGNORE INTO secretaria_municipios (municipio, secretaria_id) VALUES (?, ?)')->execute([$mun, $sid]);
                $res['municipios']++;
            }
        }
        self::sincronizarColegios($pdo);
        return $res;
    }

    /**
     * Columnas: departamento, municipio, codigo_dane (opcional). Admite directamente el listado DIVIPOLA del DANE.
     * Cada municipio queda con su secretaría: la propia si es una ETC municipal; si no, la departamental.
     */
    public static function importarMunicipios(PDO $pdo, array $filas): array
    {
        $res = ['ok' => 0, 'nuevos' => 0, 'omitidas' => []];
        $dptales = [];
        foreach ($pdo->query("SELECT id, departamento FROM secretarias WHERE tipo = 'departamental' AND departamento IS NOT NULL") as $r) {
            $dptales[self::claveDepartamento((string) $r['departamento'])] = (int) $r['id'];
        }
        foreach ($filas as $f) {
            $dep = self::departamentoCanonico($f['departamento'] ?? '');
            $mun = self::tituloPropio($f['municipio'] ?? '');
            $cod = preg_replace('/\D+/', '', (string) ($f['codigo_dane'] ?? ($f['codigo'] ?? ''))) ?? '';
            $cod = $cod !== '' ? substr($cod, 0, 5) : null;
            if ($dep === '' || $mun === '' || mb_strlen($mun) > 80) {
                $res['omitidas'][] = "Fila {$f['_n']}: departamento o municipio vacío (o municipio de más de 80 caracteres).";
                continue;
            }
            $sid = $dptales[self::claveDepartamento($dep)] ?? null;
            [, $nuevo] = self::guardarMunicipio($pdo, $dep, $mun, $cod, $sid, false);
            $res['ok']++;
            if ($nuevo) {
                $res['nuevos']++;
            }
        }
        $res['colegios'] = self::sincronizarColegios($pdo);
        return $res;
    }

    /** Columnas: secretaria (ignorada si $secretariaForzada), municipio (opcional), departamento (opcional), codigo (opcional), nombre. */
    public static function importarNucleos(PDO $pdo, array $filas, ?int $secretariaForzada = null): array
    {
        $res = ['ok' => 0, 'nuevos' => 0, 'omitidas' => []];
        $mapa = self::mapaSecretarias($pdo);
        foreach ($filas as $f) {
            $nombre = trim($f['nombre'] ?? '');
            if ($nombre === '' || mb_strlen($nombre) > 120) {
                $res['omitidas'][] = "Fila {$f['_n']}: nombre del núcleo vacío o de más de 120 caracteres.";
                continue;
            }
            $sid = $secretariaForzada ?? self::idSecretariaPorTexto($mapa, $f['secretaria'] ?? '');
            if ($sid === null) {
                $res['omitidas'][] = "Fila {$f['_n']} ({$nombre}): no encontré la secretaría «" . ($f['secretaria'] ?? '') . '». Impórtala primero.';
                continue;
            }
            $munId = null;
            if (($f['municipio'] ?? '') !== '') {
                $munId = self::buscarMunicipioId($pdo, $f['municipio'], $f['departamento'] ?? '', $sid);
                if ($munId === null) {
                    $res['omitidas'][] = "Fila {$f['_n']} ({$nombre}): el municipio «{$f['municipio']}» no existe. Impórtalo primero (o deja la columna vacía).";
                    continue;
                }
                if ($munId === false) {
                    $res['omitidas'][] = "Fila {$f['_n']} ({$nombre}): «{$f['municipio']}» existe en varios departamentos; agrega la columna departamento.";
                    continue;
                }
            }
            $codigo = ($f['codigo'] ?? '') !== '' ? mb_substr($f['codigo'], 0, 20) : null;
            $q = $pdo->prepare('SELECT id FROM nucleos_educativos WHERE secretaria_id = ? AND nombre = ? LIMIT 1');
            $q->execute([$sid, $nombre]);
            $id = $q->fetchColumn();
            if ($id) {
                $pdo->prepare('UPDATE nucleos_educativos SET municipio_id = COALESCE(?, municipio_id), codigo = COALESCE(?, codigo) WHERE id = ?')
                    ->execute([$munId, $codigo, $id]);
            } else {
                $pdo->prepare('INSERT INTO nucleos_educativos (secretaria_id, municipio_id, codigo, nombre) VALUES (?,?,?,?)')
                    ->execute([$sid, $munId, $codigo, $nombre]);
                $res['nuevos']++;
            }
            $res['ok']++;
        }
        return $res;
    }

    /** Columnas: municipio, tipo (comuna|corregimiento|localidad|vereda), nombre, departamento (opcional). */
    public static function importarZonas(PDO $pdo, array $filas, ?int $secretariaForzada = null): array
    {
        $res = ['ok' => 0, 'nuevos' => 0, 'omitidas' => []];
        foreach ($filas as $f) {
            $nombre = trim($f['nombre'] ?? '');
            $tipo = self::normalizar($f['tipo'] ?? '');
            if ($tipo === '') {
                $tipo = 'comuna';
            }
            if ($nombre === '' || mb_strlen($nombre) > 100) {
                $res['omitidas'][] = "Fila {$f['_n']}: nombre de la zona vacío o de más de 100 caracteres.";
                continue;
            }
            if (!isset(self::TIPOS_ZONA[$tipo])) {
                $res['omitidas'][] = "Fila {$f['_n']} ({$nombre}): tipo «{$f['tipo']}» no válido (comuna, corregimiento, localidad o vereda).";
                continue;
            }
            $munId = self::buscarMunicipioId($pdo, $f['municipio'] ?? '', $f['departamento'] ?? '', $secretariaForzada);
            if ($munId === null) {
                $res['omitidas'][] = "Fila {$f['_n']} ({$nombre}): el municipio «" . ($f['municipio'] ?? '') . '» no existe. Impórtalo primero.';
                continue;
            }
            if ($munId === false) {
                $res['omitidas'][] = "Fila {$f['_n']} ({$nombre}): «{$f['municipio']}» existe en varios departamentos; agrega la columna departamento.";
                continue;
            }
            if ($secretariaForzada !== null) {
                $chk = $pdo->prepare('SELECT 1 FROM municipios WHERE id = ? AND secretaria_id = ?');
                $chk->execute([$munId, $secretariaForzada]);
                if (!$chk->fetchColumn()) {
                    $res['omitidas'][] = "Fila {$f['_n']} ({$nombre}): el municipio «{$f['municipio']}» no pertenece a tu secretaría.";
                    continue;
                }
            }
            $ins = $pdo->prepare('INSERT IGNORE INTO zonas_municipio (municipio_id, tipo, nombre) VALUES (?,?,?)');
            $ins->execute([$munId, $tipo, $nombre]);
            $res['ok']++;
            if ($ins->rowCount() > 0) {
                $res['nuevos']++;
            }
        }
        return $res;
    }

    // ------------------------------------------------------------------
    // Consultas para formularios
    // ------------------------------------------------------------------

    /** Núcleos disponibles (de una secretaría, o todos). */
    public static function nucleos(PDO $pdo, ?int $secretariaId = null): array
    {
        $sql = 'SELECT n.id, n.nombre, n.codigo, n.secretaria_id, s.nombre AS secretaria_nombre, m.nombre AS municipio_nombre
                  FROM nucleos_educativos n
                  INNER JOIN secretarias s ON s.id = n.secretaria_id
                  LEFT JOIN municipios m ON m.id = n.municipio_id'
             . ($secretariaId !== null ? ' WHERE n.secretaria_id = ?' : '') . ' ORDER BY s.nombre, n.nombre';
        $q = $pdo->prepare($sql);
        $q->execute($secretariaId !== null ? [$secretariaId] : []);
        return $q->fetchAll();
    }

    /** Zonas disponibles (de los municipios de una secretaría, o todas), con el municipio. */
    public static function zonas(PDO $pdo, ?int $secretariaId = null): array
    {
        $sql = 'SELECT z.id, z.tipo, z.nombre, m.id AS municipio_id, m.nombre AS municipio_nombre
                  FROM zonas_municipio z INNER JOIN municipios m ON m.id = z.municipio_id'
             . ($secretariaId !== null ? ' WHERE m.secretaria_id = ?' : '') . ' ORDER BY m.nombre, z.tipo, z.nombre';
        $q = $pdo->prepare($sql);
        $q->execute($secretariaId !== null ? [$secretariaId] : []);
        return $q->fetchAll();
    }

    public static function nucleoPermitido(PDO $pdo, int $id, ?int $secretariaId): bool
    {
        $q = $pdo->prepare('SELECT 1 FROM nucleos_educativos WHERE id = ?' . ($secretariaId !== null ? ' AND secretaria_id = ?' : ''));
        $q->execute($secretariaId !== null ? [$id, $secretariaId] : [$id]);
        return (bool) $q->fetchColumn();
    }

    public static function zonaPermitida(PDO $pdo, int $id, ?int $secretariaId): bool
    {
        $q = $pdo->prepare('SELECT 1 FROM zonas_municipio z INNER JOIN municipios m ON m.id = z.municipio_id WHERE z.id = ?'
            . ($secretariaId !== null ? ' AND m.secretaria_id = ?' : ''));
        $q->execute($secretariaId !== null ? [$id, $secretariaId] : [$id]);
        return (bool) $q->fetchColumn();
    }

    /** Asigna núcleo y zona al colegio y deduce su municipio_id (por la zona, el núcleo o el texto del municipio). */
    public static function asignarAColegio(PDO $pdo, int $colegioId, ?int $nucleoId, ?int $zonaId): void
    {
        $municipioId = null;
        if ($zonaId !== null) {
            $q = $pdo->prepare('SELECT municipio_id FROM zonas_municipio WHERE id = ?');
            $q->execute([$zonaId]);
            $municipioId = $q->fetchColumn() ?: null;
        }
        if ($municipioId === null && $nucleoId !== null) {
            $q = $pdo->prepare('SELECT municipio_id FROM nucleos_educativos WHERE id = ?');
            $q->execute([$nucleoId]);
            $municipioId = $q->fetchColumn() ?: null;
        }
        if ($municipioId === null) {
            $q = $pdo->prepare('SELECT m.id FROM municipios m INNER JOIN colegios c ON ' . self::igual('c.municipio', 'm.nombre') . '
                                 WHERE c.id = ? AND (SELECT COUNT(*) FROM municipios x WHERE ' . self::igual('x.nombre', 'c.municipio') . ') = 1 LIMIT 1');
            $q->execute([$colegioId]);
            $municipioId = $q->fetchColumn() ?: null;
        }
        $pdo->prepare('UPDATE colegios SET nucleo_id = ?, zona_id = ?, municipio_id = COALESCE(?, municipio_id) WHERE id = ?')
            ->execute([$nucleoId, $zonaId, $municipioId, $colegioId]);
    }

    /** Resuelve por NOMBRE (importación de colegios) un núcleo dentro de una secretaría (o único en toda la base). */
    public static function resolverNucleoId(PDO $pdo, string $nombre, ?int $secretariaId): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return null;
        }
        $q = $pdo->prepare('SELECT id FROM nucleos_educativos WHERE nombre = ?' . ($secretariaId !== null ? ' AND secretaria_id = ?' : ''));
        $q->execute($secretariaId !== null ? [$nombre, $secretariaId] : [$nombre]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        return count($ids) === 1 ? (int) $ids[0] : null;
    }

    /** Resuelve por NOMBRE una zona del municipio del colegio. Acepta «Comuna 10» o solo «10 - La Candelaria». */
    public static function resolverZonaId(PDO $pdo, string $nombre, string $municipio, ?int $secretariaId): ?int
    {
        $objetivo = self::normalizar($nombre);
        if ($objetivo === '' || $municipio === '') {
            return null;
        }
        $q = $pdo->prepare('SELECT z.id, z.tipo, z.nombre FROM zonas_municipio z INNER JOIN municipios m ON m.id = z.municipio_id WHERE m.nombre = ?'
            . ($secretariaId !== null ? ' AND m.secretaria_id = ?' : ''));
        $q->execute($secretariaId !== null ? [$municipio, $secretariaId] : [$municipio]);
        $hallados = [];
        foreach ($q as $z) {
            $solo = self::normalizar((string) $z['nombre']);
            $conTipo = self::normalizar($z['tipo'] . ' ' . $z['nombre']);
            if ($objetivo === $solo || $objetivo === $conTipo) {
                $hallados[] = (int) $z['id'];
            }
        }
        return count($hallados) === 1 ? $hallados[0] : null;
    }

    // ------------------------------------------------------------------
    // Resumen y árbol para la pantalla de Territorio
    // ------------------------------------------------------------------

    public static function resumen(PDO $pdo, ?int $secretariaId = null): array
    {
        $w = $secretariaId !== null ? ' WHERE secretaria_id = ' . (int) $secretariaId : '';
        $wc = $w;
        return [
            'secretarias' => $secretariaId !== null ? 1 : (int) $pdo->query('SELECT COUNT(*) FROM secretarias')->fetchColumn(),
            'municipios' => (int) $pdo->query('SELECT COUNT(*) FROM municipios' . $w)->fetchColumn(),
            'nucleos' => (int) $pdo->query('SELECT COUNT(*) FROM nucleos_educativos' . $w)->fetchColumn(),
            'zonas' => (int) $pdo->query('SELECT COUNT(*) FROM zonas_municipio z INNER JOIN municipios m ON m.id = z.municipio_id'
                . ($secretariaId !== null ? ' WHERE m.secretaria_id = ' . (int) $secretariaId : ''))->fetchColumn(),
            'colegios' => (int) $pdo->query('SELECT COUNT(*) FROM colegios' . $wc)->fetchColumn(),
            'sin_secretaria' => $secretariaId !== null ? 0 : (int) $pdo->query('SELECT COUNT(*) FROM colegios WHERE secretaria_id IS NULL')->fetchColumn(),
            'sin_nucleo' => (int) $pdo->query('SELECT COUNT(*) FROM colegios WHERE nucleo_id IS NULL' . ($secretariaId !== null ? ' AND secretaria_id = ' . (int) $secretariaId : ''))->fetchColumn(),
            'sin_zona' => (int) $pdo->query('SELECT COUNT(*) FROM colegios WHERE zona_id IS NULL' . ($secretariaId !== null ? ' AND secretaria_id = ' . (int) $secretariaId : ''))->fetchColumn(),
        ];
    }

    /**
     * Árbol Secretaría → {núcleos, municipios → zonas} con conteo de colegios en cada nodo.
     * @return array<int, array<string,mixed>>
     */
    public static function arbol(PDO $pdo, ?int $secretariaId = null): array
    {
        $q = $pdo->prepare('SELECT s.id, s.nombre, s.departamento, s.tipo, s.activa,
                                   (SELECT COUNT(*) FROM colegios c WHERE c.secretaria_id = s.id) AS colegios
                              FROM secretarias s' . ($secretariaId !== null ? ' WHERE s.id = ?' : '')
            . ' ORDER BY (s.departamento IS NULL), s.departamento, s.tipo, s.nombre');
        $q->execute($secretariaId !== null ? [$secretariaId] : []);
        $arbol = [];
        foreach ($q as $s) {
            $s['nucleos'] = [];
            $s['municipios'] = [];
            $arbol[(int) $s['id']] = $s;
        }
        if (!$arbol) {
            return [];
        }
        foreach ($pdo->query('SELECT n.id, n.secretaria_id, n.nombre, m.nombre AS municipio,
                                     (SELECT COUNT(*) FROM colegios c WHERE c.nucleo_id = n.id) AS colegios
                                FROM nucleos_educativos n LEFT JOIN municipios m ON m.id = n.municipio_id ORDER BY n.nombre') as $n) {
            if (isset($arbol[(int) $n['secretaria_id']])) {
                $arbol[(int) $n['secretaria_id']]['nucleos'][] = $n;
            }
        }
        $munIdx = [];
        foreach ($pdo->query('SELECT m.id, m.secretaria_id, m.nombre, m.departamento,
                                     (SELECT COUNT(*) FROM colegios c WHERE c.municipio_id = m.id) AS colegios
                                FROM municipios m WHERE m.secretaria_id IS NOT NULL ORDER BY m.nombre') as $m) {
            if (isset($arbol[(int) $m['secretaria_id']])) {
                $m['zonas'] = [];
                $arbol[(int) $m['secretaria_id']]['municipios'][(int) $m['id']] = $m;
                $munIdx[(int) $m['id']] = (int) $m['secretaria_id'];
            }
        }
        foreach ($pdo->query('SELECT z.id, z.municipio_id, z.tipo, z.nombre,
                                     (SELECT COUNT(*) FROM colegios c WHERE c.zona_id = z.id) AS colegios
                                FROM zonas_municipio z ORDER BY z.tipo, z.nombre') as $z) {
            $mid = (int) $z['municipio_id'];
            if (isset($munIdx[$mid])) {
                $arbol[$munIdx[$mid]]['municipios'][$mid]['zonas'][] = $z;
            }
        }
        return $arbol;
    }

    /** Elimina un núcleo o una zona (los colegios quedan sin ese dato; no se borra ningún colegio). */
    public static function eliminar(PDO $pdo, string $que, int $id, ?int $secretariaId): bool
    {
        if ($que === 'nucleo') {
            if (!self::nucleoPermitido($pdo, $id, $secretariaId)) {
                return false;
            }
            $pdo->prepare('DELETE FROM nucleos_educativos WHERE id = ?')->execute([$id]);
            return true;
        }
        if ($que === 'zona') {
            if (!self::zonaPermitida($pdo, $id, $secretariaId)) {
                return false;
            }
            $pdo->prepare('DELETE FROM zonas_municipio WHERE id = ?')->execute([$id]);
            return true;
        }
        return false;
    }
}
