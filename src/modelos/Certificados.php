<?php
declare(strict_types=1);

require_once __DIR__ . '/Expediente.php';
require_once __DIR__ . '/../helpers/configuracion.php';
require_once __DIR__ . '/../helpers/QrCode.php';
require_once __DIR__ . '/../helpers/CertificadoPDF.php';

/**
 * Certificados verificables con QR. Requiere la migración 037_certificados.sql.
 *
 * Cada certificado emitido guarda una COPIA INMUTABLE de lo que dice (incluida la institución, el rector y las
 * imágenes de ese momento) y una huella HMAC. La página pública de verificación y el PDF se generan SIEMPRE
 * desde esa copia, así que un certificado nunca cambia aunque después se edite la configuración.
 */
class Certificados
{
    public const TIPOS = [
        'matricula' => 'Constancia de matrícula',
        'estudio' => 'Certificado de estudio',
        'notas' => 'Certificado de calificaciones',
        'horas' => 'Certificado de servicio social y Constitución Política',
        'traslado' => 'Certificado de traslado',
    ];
    public const NATURALEZAS = ['Oficial', 'Privada', 'Concesión', 'Cobertura contratada'];

    /** tipo => [título, cuerpo]. Variables: ver self::VARIABLES. */
    private const PLANTILLAS = [
        'matricula' => ['Constancia de matrícula',
            "El suscrito {{rector_cargo}} de la {{institucion}}, con código DANE {{dane}},\n\nHACE CONSTAR:\n\nQue el(la) estudiante {{estudiante}}, identificado(a) con {{documento_tipo}} N.º {{documento}}, se encuentra matriculado(a) en esta institución en el grado {{grado}}{{grupo_texto}} para el año lectivo {{anio}}.\n\nLa presente constancia se expide a solicitud del interesado para los fines que estime convenientes."],
        'estudio' => ['Certificado de estudio',
            "El suscrito {{rector_cargo}} de la {{institucion}}, con código DANE {{dane}},\n\nCERTIFICA:\n\nQue el(la) estudiante {{estudiante}}, identificado(a) con {{documento_tipo}} N.º {{documento}}, {{verbo_estudio}} el grado {{grado}}{{grupo_texto}} en esta institución durante el año lectivo {{anio}}.\n\nSe expide a solicitud del interesado."],
        'notas' => ['Certificado de calificaciones',
            "El suscrito {{rector_cargo}} de la {{institucion}}, con código DANE {{dane}}, HACE CONSTAR que el(la) estudiante {{estudiante}}, identificado(a) con {{documento_tipo}} N.º {{documento}}, {{verbo_estudio}} el grado {{grado}} en el año lectivo {{anio}} con las siguientes valoraciones, en la escala {{escala}}:"],
        'horas' => ['Certificado de servicio social y Constitución Política',
            "El suscrito {{rector_cargo}} de la {{institucion}}, con código DANE {{dane}}, CERTIFICA que el(la) estudiante {{estudiante}}, identificado(a) con {{documento_tipo}} N.º {{documento}}, del grado {{grado}}, registra en esta institución las siguientes horas de formación obligatoria, conforme a la Resolución 4210 de 1996 (servicio social estudiantil obligatorio) y a la Ley 107 de 1994 (estudio de la Constitución Política):"],
        'traslado' => ['Certificado de traslado',
            "El suscrito {{rector_cargo}} de la {{institucion}}, con código DANE {{dane}},\n\nCERTIFICA:\n\nQue el(la) estudiante {{estudiante}}, identificado(a) con {{documento_tipo}} N.º {{documento}}, {{verbo_estudio}} el grado {{grado}}{{grupo_texto}} en esta institución durante el año lectivo {{anio}}.\n\nSe expide a solicitud del interesado para efectos de traslado a otra institución educativa."],
    ];
    public const VARIABLES = [
        'institucion' => 'Nombre de la institución (en mayúsculas)', 'dane' => 'Código DANE', 'nit' => 'NIT', 'rector_cargo' => 'Cargo de quien firma (Rector(a))',
        'niveles' => 'Niveles autorizados', 'jornadas' => 'Jornadas', 'calendario' => 'Calendario (A/B)', 'ciudad' => 'Ciudad de expedición',
        'estudiante' => 'Nombre del estudiante (en mayúsculas)', 'documento_tipo' => 'Tipo de documento (tarjeta de identidad...)', 'documento' => 'Número de documento',
        'grado' => 'Grado', 'grupo_texto' => 'Grupo (", grupo 10-02") o vacío', 'anio' => 'Año lectivo', 'verbo_estudio' => '«se encuentra cursando» o «cursó»', 'escala' => 'Escala de valoración',
    ];

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    private static function txt(mixed $v, int $max): ?string
    {
        $t = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v) ?? ''), 0, $max);
        return $t === '' ? null : $t;
    }

    private static function fechaOk(string $f): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $f);
        return $d !== false && $d->format('Y-m-d') === $f;
    }

    public static function formatearCodigo(string $c): string
    {
        return implode('-', str_split($c, 4));
    }

    public static function normalizarCodigo(string $c): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c) ?? '');
    }

    private static function codigoAleatorio(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $c = '';
        for ($i = 0; $i < 12; $i++) {
            $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        return $c;
    }

    /** Clave secreta para la huella: se genera una vez y se guarda en la configuración (no depende de las credenciales de la base). */
    private static function clave(PDO $pdo): string
    {
        $k = configuracionObtener($pdo, 'certificados_clave', '');
        if ($k === '') {
            $k = bin2hex(random_bytes(32));
            configuracionGuardar($pdo, 'certificados_clave', $k);
        }
        return $k;
    }

    private static function huella(PDO $pdo, string $json): string
    {
        return hash_hmac('sha256', $json, self::clave($pdo));
    }

    /** Dirección pública del sitio para el QR: la configurada, o la de esta petición. */
    public static function urlBase(array $ins): string
    {
        $u = trim((string) ($ins['url_publica'] ?? ''));
        if ($u !== '' && preg_match('#^https?://#i', $u)) {
            return rtrim($u, '/');
        }
        return urlAbsoluta();
    }

    // ------------------------------------------------------------------
    // Perfil de la institución (configurador)
    // ------------------------------------------------------------------

    private const CAMPOS_TEXTO = [
        'nombre_oficial' => 200, 'naturaleza' => 60, 'caracter' => 80, 'niveles' => 200, 'jornadas' => 100, 'calendario' => 10, 'codigo_dane' => 12, 'nit' => 20,
        'codigo_icfes' => 12, 'resolucion_numero' => 60, 'resolucion_entidad' => 150, 'resoluciones_adicionales' => 600, 'direccion' => 200, 'municipio' => 80,
        'departamento' => 60, 'telefono' => 40, 'correo' => 150, 'sitio_web' => 150, 'ciudad_expedicion' => 80, 'rector_nombre' => 120, 'rector_cargo' => 60,
        'secretaria_nombre' => 120, 'secretaria_cargo' => 60, 'url_publica' => 200,
    ];

    /** Perfil de la institución; si aún no se configuró, se pre-llena con los datos del colegio. */
    public static function institucion(PDO $pdo, int $colegioId): array
    {
        $q = $pdo->prepare('SELECT * FROM institucion_perfil WHERE colegio_id = ?');
        $q->execute([$colegioId]);
        $row = $q->fetch();
        $r = array_fill_keys(array_merge(array_keys(self::CAMPOS_TEXTO), ['resolucion_fecha', 'dias_vigencia', 'logo_b64', 'firma_rector_b64', 'firma_secretaria_b64']), '');
        $r['color_primario'] = '#AD6B05';
        $r['rector_cargo'] = 'Rector(a)';
        $r['configurada'] = (bool) $row;
        if ($row) {
            foreach ($row as $k => $v) {
                if ($v !== null) {
                    $r[$k] = $v;
                }
            }
        } else {
            $c = $pdo->prepare('SELECT nombre, dane, municipio, direccion FROM colegios WHERE id = ?');
            $c->execute([$colegioId]);
            if ($col = $c->fetch()) {
                $r['nombre_oficial'] = (string) $col['nombre'];
                $r['codigo_dane'] = (string) $col['dane'];
                $r['municipio'] = (string) ($col['municipio'] ?? '');
                $r['direccion'] = (string) ($col['direccion'] ?? '');
                $r['ciudad_expedicion'] = (string) ($col['municipio'] ?? '');
            }
        }
        return $r;
    }

    /** Qué falta para poder emitir certificados. @return string[] */
    public static function faltantes(array $ins): array
    {
        $f = [];
        foreach (['nombre_oficial' => 'el nombre oficial', 'codigo_dane' => 'el código DANE', 'rector_nombre' => 'el nombre de quien firma (rector)'] as $k => $t) {
            if (trim((string) ($ins[$k] ?? '')) === '') {
                $f[] = $t;
            }
        }
        if (trim((string) ($ins['resolucion_numero'] ?? '')) === '' && trim((string) ($ins['resoluciones_adicionales'] ?? '')) === '') {
            $f[] = 'la resolución de reconocimiento oficial';
        }
        return $f;
    }

    public static function guardarInstitucion(PDO $pdo, int $colegioId, array $d, int $porId): string
    {
        $vals = [];
        foreach (self::CAMPOS_TEXTO as $campo => $max) {
            $vals[$campo] = self::txt($d[$campo] ?? '', $max);
        }
        if ($vals['nombre_oficial'] === null) {
            return 'Escribe el nombre oficial de la institución.';
        }
        if ($vals['codigo_dane'] !== null && !preg_match('/^\d{10,12}$/', $vals['codigo_dane'])) {
            return 'El código DANE debe tener entre 10 y 12 dígitos, sin espacios.';
        }
        if ($vals['nit'] !== null && !preg_match('/^[0-9][0-9.\-]{4,19}$/', $vals['nit'])) {
            return 'El NIT solo puede tener números, puntos y guion (ej. 890.900.123-4).';
        }
        if ($vals['naturaleza'] !== null && !in_array($vals['naturaleza'], self::NATURALEZAS, true)) {
            return 'Elige una naturaleza válida.';
        }
        if ($vals['correo'] !== null && !filter_var($vals['correo'], FILTER_VALIDATE_EMAIL)) {
            return 'El correo institucional no es válido.';
        }
        if ($vals['url_publica'] !== null && !preg_match('#^https?://[^\s/]+#i', $vals['url_publica'])) {
            return 'La dirección pública debe empezar por http:// o https:// (ej. https://colegio.edu.co/serena/public).';
        }
        $fecha = trim((string) ($d['resolucion_fecha'] ?? ''));
        if ($fecha !== '' && !self::fechaOk($fecha)) {
            return 'La fecha de la resolución no es válida.';
        }
        $color = trim((string) ($d['color_primario'] ?? '#AD6B05'));
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return 'El color debe tener el formato #RRGGBB.';
        }
        $dias = trim((string) ($d['dias_vigencia'] ?? ''));
        if ($dias !== '' && (!ctype_digit($dias) || (int) $dias > 3650)) {
            return 'Los días de vigencia deben ser un número entre 0 y 3650 (vacío = sin vencimiento).';
        }
        $vals['resolucion_fecha'] = $fecha !== '' ? $fecha : null;
        $vals['color_primario'] = strtoupper($color);
        $vals['dias_vigencia'] = ($dias !== '' && (int) $dias > 0) ? (int) $dias : null;
        $cols = array_keys($vals);
        $sql = 'INSERT INTO institucion_perfil (colegio_id, actualizado_por, ' . implode(', ', $cols) . ') VALUES (?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')
                ON DUPLICATE KEY UPDATE actualizado_por = VALUES(actualizado_por), ' . implode(', ', array_map(static fn (string $c): string => "{$c} = VALUES({$c})", $cols));
        $pdo->prepare($sql)->execute(array_merge([$colegioId, $porId], array_values($vals)));
        return '';
    }

    /**
     * Procesa una imagen subida (logo o firma): valida que sea una imagen real, la reduce y la guarda como JPEG
     * (así se descarta cualquier contenido oculto y el PDF puede incrustarla). Devuelve el JPEG en base64.
     * @throws RuntimeException con un mensaje apto para mostrar
     */
    public static function procesarImagen(array $archivo, int $maxPx): string
    {
        $err = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('__vacio__');
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($archivo['tmp_name'] ?? ''))) {
            throw new RuntimeException('No se pudo subir la imagen.');
        }
        if (($archivo['size'] ?? 0) > 3 * 1024 * 1024) {
            throw new RuntimeException('La imagen pesa más de 3 MB.');
        }
        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('El archivo no es una imagen válida (JPG, PNG, GIF o WEBP).');
        }
        $bytes = (string) file_get_contents($archivo['tmp_name']);
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            // Sin la extensión GD solo se aceptan JPEG pequeños tal cual
            if ($info[2] === IMAGETYPE_JPEG && strlen($bytes) <= 500 * 1024 && CertificadoPDF::imagenJpeg($bytes) !== null) {
                return base64_encode($bytes);
            }
            throw new RuntimeException('El servidor no tiene la extensión GD para procesar imágenes. Sube un JPG de menos de 500 KB.');
        }
        if ($info[0] * $info[1] > 25000000) {
            throw new RuntimeException('La imagen es demasiado grande (más de 25 megapíxeles).');
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw new RuntimeException('No se pudo leer la imagen.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $esc = min(1.0, $maxPx / max($w, $h));
        $nw = max(1, (int) round($w * $esc));
        $nh = max(1, (int) round($h * $esc));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // las transparencias quedan sobre blanco
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($dst, null, 88);
        $jpeg = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        if ($jpeg === '' || CertificadoPDF::imagenJpeg($jpeg) === null) {
            throw new RuntimeException('No se pudo convertir la imagen.');
        }
        return base64_encode($jpeg);
    }

    /** Guarda o quita una imagen del perfil. $campo: logo | firma_rector | firma_secretaria. */
    public static function guardarImagen(PDO $pdo, int $colegioId, string $campo, ?string $base64): void
    {
        if (!in_array($campo, ['logo', 'firma_rector', 'firma_secretaria'], true)) {
            return;
        }
        $pdo->prepare('INSERT IGNORE INTO institucion_perfil (colegio_id) VALUES (?)')->execute([$colegioId]);
        $pdo->prepare("UPDATE institucion_perfil SET {$campo}_b64 = ? WHERE colegio_id = ?")->execute([$base64, $colegioId]);
    }

    // ------------------------------------------------------------------
    // Plantillas editables
    // ------------------------------------------------------------------

    /** @return array{titulo: string, cuerpo: string, personalizada: bool} */
    public static function plantilla(PDO $pdo, int $colegioId, string $tipo): array
    {
        $q = $pdo->prepare('SELECT titulo, cuerpo FROM plantillas_certificado WHERE colegio_id = ? AND tipo = ?');
        $q->execute([$colegioId, $tipo]);
        $r = $q->fetch();
        if ($r) {
            return ['titulo' => (string) $r['titulo'], 'cuerpo' => (string) $r['cuerpo'], 'personalizada' => true];
        }
        return ['titulo' => self::PLANTILLAS[$tipo][0], 'cuerpo' => self::PLANTILLAS[$tipo][1], 'personalizada' => false];
    }

    public static function guardarPlantilla(PDO $pdo, int $colegioId, string $tipo, string $titulo, string $cuerpo): string
    {
        if (!isset(self::TIPOS[$tipo])) {
            return 'Tipo de certificado no válido.';
        }
        $titulo = mb_substr(trim($titulo), 0, 120);
        $cuerpo = trim(str_replace("\r", '', $cuerpo));
        if ($titulo === '' || $cuerpo === '') {
            return 'El título y el texto no pueden estar vacíos.';
        }
        if (mb_strlen($cuerpo) > 4000) {
            return 'El texto es demasiado largo (máximo 4000 caracteres).';
        }
        $pdo->prepare('INSERT INTO plantillas_certificado (colegio_id, tipo, titulo, cuerpo) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), cuerpo = VALUES(cuerpo)')->execute([$colegioId, $tipo, $titulo, $cuerpo]);
        return '';
    }

    public static function restablecerPlantilla(PDO $pdo, int $colegioId, string $tipo): void
    {
        $pdo->prepare('DELETE FROM plantillas_certificado WHERE colegio_id = ? AND tipo = ?')->execute([$colegioId, $tipo]);
    }

    public static function renderizar(string $plantilla, array $vars): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn (array $m): string => (string) ($vars[$m[1]] ?? ''), $plantilla);
    }

    // ------------------------------------------------------------------
    // Emisión
    // ------------------------------------------------------------------

    private static function resolucionTexto(array $i): string
    {
        $partes = [];
        if (trim((string) $i['resolucion_numero']) !== '') {
            $t = 'Reconocimiento oficial: Resolución ' . trim((string) $i['resolucion_numero']);
            if (trim((string) $i['resolucion_fecha']) !== '') {
                $t .= ' del ' . CertificadoPDF::fechaLarga((string) $i['resolucion_fecha']);
            }
            if (trim((string) $i['resolucion_entidad']) !== '') {
                $t .= ', ' . trim((string) $i['resolucion_entidad']);
            }
            $partes[] = $t . '.';
        }
        if (trim((string) $i['resoluciones_adicionales']) !== '') {
            $partes[] = trim((string) $i['resoluciones_adicionales']);
        }
        return implode(' ', $partes);
    }

    private static function documentoEstudiante(PDO $pdo, int $estId): ?array
    {
        $h = Expediente::hojaVida($pdo, $estId);
        if ($h && $h['numero_documento'] && $h['tipo_documento']) {
            return [(string) $h['tipo_documento'], (string) $h['numero_documento']];
        }
        $q = $pdo->prepare('SELECT tipo_documento, numero_documento FROM inscripciones WHERE estudiante_id = ? ORDER BY id DESC LIMIT 1');
        $q->execute([$estId]);
        $r = $q->fetch();
        return $r ? [(string) $r['tipo_documento'], (string) $r['numero_documento']] : null;
    }

    private static function formatoDocumento(string $n): string
    {
        return ctype_digit($n) ? number_format((int) $n, 0, ',', '.') : $n;
    }

    private static function enmascarar(string $n): string
    {
        $n = (string) preg_replace('/[^A-Za-z0-9]/', '', $n);
        return strlen($n) <= 3 ? $n : str_repeat('•', strlen($n) - 3) . substr($n, -3);
    }

    /** Grado y grupo del estudiante en el año: por su matrícula o, si el año es el actual, por su grupo actual. */
    private static function gradoDelAnio(PDO $pdo, int $colegioId, int $estId, array $anio, bool $exigirActiva): ?array
    {
        $q = $pdo->prepare("SELECT m.estado, g.nombre AS grado, gp.nombre AS grupo FROM matriculas m
                              INNER JOIN grados g ON g.id = m.grado_id LEFT JOIN grupos gp ON gp.id = m.grupo_id
                             WHERE m.estudiante_id = ? AND m.anio_lectivo_id = ? AND m.colegio_id = ?
                          ORDER BY FIELD(m.estado, 'activa', 'retirada', 'pendiente', 'rechazada') LIMIT 1");
        $q->execute([$estId, (int) $anio['id'], $colegioId]);
        $m = $q->fetch();
        if ($m && (!$exigirActiva || $m['estado'] === 'activa') && in_array($m['estado'], ['activa', 'retirada'], true)) {
            return ['grado' => (string) $m['grado'], 'grupo' => (string) ($m['grupo'] ?? '')];
        }
        if (!$m && (int) $anio['actual'] === 1) {
            $q = $pdo->prepare('SELECT g.nombre AS grado, gp.nombre AS grupo FROM usuarios u INNER JOIN grupos gp ON gp.id = u.grupo_id INNER JOIN grados g ON g.id = gp.grado_id WHERE u.id = ? AND u.colegio_id = ?');
            $q->execute([$estId, $colegioId]);
            $r = $q->fetch();
            if ($r) {
                return ['grado' => (string) $r['grado'], 'grupo' => (string) ($r['grupo'] ?? '')];
            }
        }
        return null;
    }

    /** @return array{ok: bool, codigo: string, error: string} */
    public static function emitir(PDO $pdo, int $colegioId, string $tipo, int $estudianteId, int $anioId, int $porId): array
    {
        $fallo = static fn (string $m): array => ['ok' => false, 'codigo' => '', 'error' => $m];
        if (!isset(self::TIPOS[$tipo])) {
            return $fallo('Tipo de certificado no válido.');
        }
        $ins = self::institucion($pdo, $colegioId);
        $falta = self::faltantes($ins);
        if ($falta) {
            return $fallo('Antes de emitir configura la institución: falta ' . implode(', ', $falta) . '.');
        }
        $anio = Academico::anio($pdo, $anioId, $colegioId);
        if ($anio === null) {
            return $fallo('Elige un año lectivo.');
        }
        $q = $pdo->prepare("SELECT u.id, u.nombre FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante' WHERE u.id = ? AND u.colegio_id = ?");
        $q->execute([$estudianteId, $colegioId]);
        $est = $q->fetch();
        if (!$est) {
            return $fallo('El estudiante no existe en este colegio.');
        }
        $doc = self::documentoEstudiante($pdo, $estudianteId);
        if ($doc === null) {
            return $fallo('Falta el documento de identidad del estudiante. Regístralo en su hoja de vida (Expedientes).');
        }
        $g = self::gradoDelAnio($pdo, $colegioId, $estudianteId, $anio, $tipo === 'matricula');
        if ($g === null) {
            return $fallo($tipo === 'matricula'
                ? "El estudiante no tiene una matrícula ACTIVA en {$anio['anio']}."
                : "El estudiante no tiene matrícula en {$anio['anio']}.");
        }

        $plantilla = self::plantilla($pdo, $colegioId, $tipo);
        $esActual = (int) $anio['actual'] === 1;
        $tabla = null;
        $escalaTxt = '';
        $extraVars = [];
        $resumen = [];
        $resumenAnio = (string) $anio['anio'];

        if ($tipo === 'notas') {
            $b = Evaluacion::boletin($pdo, $colegioId, $estudianteId, $anio, false);
            $esc = $b['escala'];
            if ($esc === null) {
                return $fallo('El colegio aún no tiene un sistema de evaluación configurado.');
            }
            $periodos = array_values(array_filter($b['periodos'], static function (array $p) use ($b): bool {
                foreach ($b['filas'] as $f) {
                    if (($f['periodos'][(int) $p['id']]['valor'] ?? null) !== null) {
                        return true;
                    }
                }
                return false;
            }));
            if (!$b['filas'] || !$periodos) {
                return $fallo('El estudiante aún no tiene valoraciones registradas en ' . $anio['anio'] . '.');
            }
            $enc = ['Asignatura'];
            foreach ($periodos as $p) {
                $enc[] = (string) $p['nombre'];
            }
            $enc[] = 'Definitiva';
            $enc[] = 'Desempeño';
            $filas = [];
            $areaPrevia = null;
            $hayAreas = (bool) array_filter(array_column($b['filas'], 'area'));
            foreach ($b['filas'] as $f) {
                $areaFila = $f['area'] ?: ($hayAreas ? 'Otras asignaturas' : null);
                if ($areaFila !== null && $areaFila !== $areaPrevia) {
                    $filas[] = ['area' => (string) $areaFila];   // banda con el nombre del área; sus asignaturas van debajo
                    $areaPrevia = $areaFila;
                }
                $fila = [(string) $f['asignatura']];
                foreach ($periodos as $p) {
                    $c = $f['periodos'][(int) $p['id']];
                    $fila[] = Evaluacion::textoNota($c['valor'], $c['nivel'], $esc);
                }
                $fila[] = Evaluacion::textoNota($f['definitiva'], $f['nivel_definitiva'], $esc);
                $fila[] = $f['nivel_definitiva'] ? (string) $f['nivel_definitiva']['nombre'] : '—';
                $filas[] = $fila;
            }
            if ($esc['tipo'] !== 'descriptiva' && $b['promedio_final'] !== null) {
                $pr = ['Promedio general'];
                foreach ($periodos as $p) {
                    $pr[] = isset($b['promedios'][(int) $p['id']]) ? number_format($b['promedios'][(int) $p['id']], (int) $esc['decimales'], '.', '') : '—';
                }
                $pr[] = number_format($b['promedio_final'], (int) $esc['decimales'], '.', '');
                $pr[] = '';
                $filas[] = $pr;
            }
            $anchos = array_merge([30], array_fill(0, count($periodos), 11), [12, 17]);
            $tabla = ['encabezados' => $enc, 'anchos' => $anchos, 'filas' => $filas];
            $escalaTxt = $esc['tipo'] === 'descriptiva'
                ? 'descriptiva (' . implode(', ', array_map(static fn (array $n): string => (string) $n['nombre'], $esc['niveles'])) . ')'
                : number_format((float) $esc['valor_min'], 1, '.', '') . ' a ' . number_format((float) $esc['valor_max'], 1, '.', '');
            $resumen[] = ['Asignaturas valoradas', (string) count($b['filas'])];
            if ($b['promedio_final'] !== null && $esc['tipo'] !== 'descriptiva') {
                $resumen[] = ['Promedio general', number_format($b['promedio_final'], (int) $esc['decimales'], '.', '')];
            }
        } elseif ($tipo === 'horas') {
            $tot = Expediente::totalesHoras($pdo, $estudianteId);
            $filas = [];
            $completas = 0;
            foreach ($tot as $t) {
                $ok = $t['cumplidas'] >= $t['requeridas'];
                $completas += $ok ? 1 : 0;
                $filas[] = [$t['nombre'], rtrim(rtrim(number_format($t['cumplidas'], 1, '.', ''), '0'), '.') ?: '0', (string) $t['requeridas'], $ok ? 'Cumplido' : 'En proceso'];
                $resumen[] = [$t['nombre'], (rtrim(rtrim(number_format($t['cumplidas'], 1, '.', ''), '0'), '.') ?: '0') . ' de ' . $t['requeridas'] . ' horas'];
            }
            $tabla = ['encabezados' => ['Concepto', 'Horas cumplidas', 'Horas requeridas', 'Estado'], 'anchos' => [40, 20, 20, 20], 'filas' => $filas];
            $extraVars['conclusion'] = $completas === count($tot)
                ? 'El estudiante cumplió la totalidad de las horas exigidas.'
                : 'El estudiante aún no completa la totalidad de las horas exigidas.';
        }

        $cuerpo = self::renderizar($plantilla['cuerpo'], [
            'institucion' => mb_strtoupper((string) $ins['nombre_oficial'], 'UTF-8'), 'dane' => (string) $ins['codigo_dane'], 'nit' => (string) $ins['nit'],
            'rector_cargo' => (string) ($ins['rector_cargo'] ?: 'Rector(a)'), 'niveles' => (string) $ins['niveles'], 'jornadas' => (string) $ins['jornadas'],
            'calendario' => (string) $ins['calendario'], 'ciudad' => (string) ($ins['ciudad_expedicion'] ?: $ins['municipio']),
            'estudiante' => mb_strtoupper((string) $est['nombre'], 'UTF-8'),
            'documento_tipo' => mb_strtolower(Academico::TIPOS_DOCUMENTO[$doc[0]] ?? $doc[0], 'UTF-8'), 'documento' => self::formatoDocumento($doc[1]),
            'grado' => mb_strtoupper($g['grado'], 'UTF-8'), 'grupo_texto' => $g['grupo'] !== '' ? ', grupo ' . $g['grupo'] : '', 'anio' => $resumenAnio,
            'verbo_estudio' => $esActual ? 'se encuentra cursando' : 'cursó', 'escala' => $escalaTxt,
        ]);
        if (!empty($extraVars['conclusion'])) {
            $cuerpo .= "\n\n" . $extraVars['conclusion'];
        }

        $ciudad = (string) ($ins['ciudad_expedicion'] ?: $ins['municipio'] ?: '');
        $hoy = new DateTimeImmutable('today');
        [$dia, $mesNombre, $anioHoy] = CertificadoPDF::partesFecha($hoy->format('Y-m-d'));
        $fechaTexto = 'Dado en ' . ($ciudad !== '' ? $ciudad . ', ' : '') . ($dia === 1 ? 'al primer día' : "a los {$dia} días") . " del mes de {$mesNombre} de {$anioHoy}.";

        $resumenBase = [['Estudiante', mb_strtoupper((string) $est['nombre'], 'UTF-8')], ['Documento', (Academico::TIPOS_DOCUMENTO[$doc[0]] ?? $doc[0]) . ' ' . self::enmascarar($doc[1])],
            ['Grado', $g['grado'] . ($g['grupo'] !== '' ? ' · grupo ' . $g['grupo'] : '')], ['Año lectivo', $resumenAnio]];
        $dias = (int) ($ins['dias_vigencia'] ?? 0);
        $vence = $dias > 0 ? $hoy->modify("+{$dias} days")->format('Y-m-d') : null;

        $datos = [
            'v' => 1, 'tipo' => $tipo, 'titulo' => $plantilla['titulo'], 'cuerpo' => $cuerpo, 'tabla' => $tabla, 'fecha_texto' => $fechaTexto,
            'resumen' => array_merge($resumenBase, $resumen), 'url_base' => self::urlBase($ins),
            'institucion' => self::snapshotInstitucion($ins),
        ];
        $json = (string) json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $huella = self::huella($pdo, $json);

        for ($intento = 0; $intento < 4; $intento++) {
            $codigo = self::codigoAleatorio();
            try {
                $pdo->prepare('INSERT INTO certificados (colegio_id, codigo, tipo, estudiante_id, anio_lectivo_id, datos, huella, emitido_por, vence_en) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$colegioId, $codigo, $tipo, $estudianteId, $anioId, $json, $huella, $porId, $vence]);
                return ['ok' => true, 'codigo' => $codigo, 'error' => ''];
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    return $fallo('No se pudo guardar el certificado: ' . $e->getMessage());
                }
            }
        }
        return $fallo('No se pudo generar un código único. Intenta de nuevo.');
    }

    /** Copia de la institución tal como queda impresa en el certificado (incluye imágenes en base64). */
    private static function snapshotInstitucion(array $ins): array
    {
        return [
            'nombre' => (string) $ins['nombre_oficial'], 'naturaleza' => implode(' · ', array_filter([(string) $ins['naturaleza'] !== '' ? 'Carácter ' . mb_strtolower((string) $ins['naturaleza'], 'UTF-8') : '', (string) $ins['caracter']])),
            'dane' => (string) $ins['codigo_dane'], 'nit' => (string) $ins['nit'], 'icfes' => (string) $ins['codigo_icfes'], 'resolucion' => self::resolucionTexto($ins),
            'direccion' => (string) $ins['direccion'], 'municipio' => (string) $ins['municipio'], 'departamento' => (string) $ins['departamento'],
            'telefono' => (string) $ins['telefono'], 'correo' => (string) $ins['correo'], 'sitio_web' => (string) $ins['sitio_web'],
            'rector_nombre' => (string) $ins['rector_nombre'], 'rector_cargo' => (string) ($ins['rector_cargo'] ?: 'Rector(a)'),
            'secretaria_nombre' => (string) $ins['secretaria_nombre'], 'secretaria_cargo' => (string) $ins['secretaria_cargo'],
            'color' => (string) $ins['color_primario'], 'logo_b64' => (string) $ins['logo_b64'], 'firma_rector_b64' => (string) $ins['firma_rector_b64'],
            'firma_secretaria_b64' => (string) $ins['firma_secretaria_b64'],
        ];
    }

    /** PDF de EJEMPLO con los datos actuales de la institución (no se guarda ni tiene validez): para revisar logo, firmas y resoluciones. */
    public static function pdfPrueba(array $ins): string
    {
        $hoy = date('Y-m-d');
        [$dia, $mes, $anio] = CertificadoPDF::partesFecha($hoy);
        $ciudad = (string) ($ins['ciudad_expedicion'] ?: $ins['municipio'] ?: '');
        $c = [
            'id' => 0, 'codigo' => 'EJEMPLOEJEMP', 'emitido_en' => $hoy . ' 00:00:00',
            'd' => [
                'titulo' => 'Constancia de matrícula (EJEMPLO)',
                'cuerpo' => 'El suscrito ' . ($ins['rector_cargo'] ?: 'Rector(a)') . ' de la ' . mb_strtoupper((string) $ins['nombre_oficial'], 'UTF-8') . ', con código DANE ' . $ins['codigo_dane'] . ",\n\nHACE CONSTAR:\n\nQue el(la) estudiante NOMBRE DE EJEMPLO APELLIDOS, identificado(a) con tarjeta de identidad N.º 1.000.000.000, se encuentra matriculado(a) en esta institución en el grado DÉCIMO para el año lectivo {$anio}.\n\nEste documento es una VISTA PREVIA sin validez: sirve para revisar el logo, las firmas y las resoluciones.",
                'tabla' => null,
                'fecha_texto' => 'Dado en ' . ($ciudad !== '' ? $ciudad . ', ' : '') . "a los {$dia} días del mes de {$mes} de {$anio}.",
                'institucion' => self::snapshotInstitucion($ins), 'url_base' => self::urlBase($ins),
            ],
        ];
        return self::pdf($c);
    }

    // ------------------------------------------------------------------
    // Consulta, verificación, anulación
    // ------------------------------------------------------------------

    /** Certificado por su código público, con los datos decodificados y la comprobación de integridad. */
    public static function buscar(PDO $pdo, string $codigo): ?array
    {
        $codigo = self::normalizarCodigo($codigo);
        if (strlen($codigo) !== 12) {
            return null;
        }
        $q = $pdo->prepare('SELECT * FROM certificados WHERE codigo = ?');
        $q->execute([$codigo]);
        $c = $q->fetch();
        if (!$c) {
            return null;
        }
        $c['d'] = json_decode((string) $c['datos'], true) ?: [];
        $c['integro'] = hash_equals((string) $c['huella'], self::huella($pdo, (string) $c['datos']));
        return $c;
    }

    /** valido | vencido | anulado | alterado */
    public static function estado(array $c): string
    {
        if (!$c['integro']) {
            return 'alterado';
        }
        if ($c['estado'] === 'anulado') {
            return 'anulado';
        }
        if ($c['vence_en'] !== null && $c['vence_en'] < date('Y-m-d')) {
            return 'vencido';
        }
        return 'valido';
    }

    public static function registrarVerificacion(PDO $pdo, int $id): void
    {
        $pdo->prepare('UPDATE certificados SET verificaciones = verificaciones + 1, ultima_verificacion = NOW() WHERE id = ?')->execute([$id]);
    }

    public static function registrarDescarga(PDO $pdo, int $id): void
    {
        $pdo->prepare('UPDATE certificados SET descargas = descargas + 1 WHERE id = ?')->execute([$id]);
    }

    public static function listar(PDO $pdo, int $colegioId, string $tipo, string $estado, string $busqueda): array
    {
        $sql = 'SELECT c.id, c.codigo, c.tipo, c.estado, c.emitido_en, c.vence_en, c.descargas, c.verificaciones, c.anulado_motivo, c.estudiante_id,
                       u.nombre AS estudiante, e.nombre AS emisor, a.anio
                  FROM certificados c LEFT JOIN usuarios u ON u.id = c.estudiante_id LEFT JOIN usuarios e ON e.id = c.emitido_por
                  LEFT JOIN anios_lectivos a ON a.id = c.anio_lectivo_id WHERE c.colegio_id = ?';
        $p = [$colegioId];
        if (isset(self::TIPOS[$tipo])) {
            $sql .= ' AND c.tipo = ?';
            $p[] = $tipo;
        }
        if (in_array($estado, ['vigente', 'anulado'], true)) {
            $sql .= ' AND c.estado = ?';
            $p[] = $estado;
        }
        if (trim($busqueda) !== '') {
            $sql .= ' AND (u.nombre LIKE ? OR c.codigo LIKE ?)';
            $p[] = '%' . trim($busqueda) . '%';
            $p[] = '%' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $busqueda) ?: trim($busqueda)) . '%';
        }
        $q = $pdo->prepare($sql . ' ORDER BY c.emitido_en DESC, c.id DESC LIMIT 300');
        $q->execute($p);
        return $q->fetchAll();
    }

    /** Certificados vigentes de un estudiante (para que él o su acudiente los abra). */
    public static function deEstudiante(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare("SELECT codigo, tipo, emitido_en, vence_en FROM certificados WHERE estudiante_id = ? AND estado = 'vigente' ORDER BY emitido_en DESC LIMIT 50");
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    public static function anular(PDO $pdo, int $id, int $colegioId, string $motivo, int $porId): string
    {
        $motivo = self::txt($motivo, 255);
        if ($motivo === null) {
            return 'Escribe el motivo de la anulación.';
        }
        $u = $pdo->prepare("UPDATE certificados SET estado = 'anulado', anulado_por = ?, anulado_en = NOW(), anulado_motivo = ? WHERE id = ? AND colegio_id = ? AND estado = 'vigente'");
        $u->execute([$porId, $motivo, $id, $colegioId]);
        return $u->rowCount() > 0 ? '' : 'El certificado no existe o ya estaba anulado.';
    }

    // ------------------------------------------------------------------
    // PDF
    // ------------------------------------------------------------------

    public static function urlVerificacion(array $c): string
    {
        $base = (string) ($c['d']['url_base'] ?? '');
        return $base . '/verificar.php?c=' . $c['codigo'];
    }

    /**
     * Datos listos para CertificadoPDF::dibujar() a partir de la copia guardada del certificado.
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:string} [institución, certificado, url de verificación]
     */
    private static function datosPdf(array $c): array
    {
        $d = $c['d'];
        $i = $d['institucion'];
        $img = static fn (string $b64): ?array => $b64 !== '' ? CertificadoPDF::imagenJpeg((string) base64_decode($b64, true)) : null;
        $insPdf = [
            'nombre' => $i['nombre'], 'naturaleza' => $i['naturaleza'], 'dane' => $i['dane'], 'nit' => $i['nit'], 'icfes' => $i['icfes'], 'resolucion' => $i['resolucion'],
            'direccion' => $i['direccion'], 'municipio' => $i['municipio'], 'departamento' => $i['departamento'], 'telefono' => $i['telefono'], 'correo' => $i['correo'],
            'rector_nombre' => $i['rector_nombre'], 'rector_cargo' => $i['rector_cargo'], 'secretaria_nombre' => $i['secretaria_nombre'], 'secretaria_cargo' => $i['secretaria_cargo'],
            'color' => $i['color'], 'logo' => $img((string) $i['logo_b64']), 'firma_rector' => $img((string) $i['firma_rector_b64']), 'firma_secretaria' => $img((string) $i['firma_secretaria_b64']),
        ];
        $url = self::urlVerificacion($c);
        $cert = [
            'titulo' => $d['titulo'], 'cuerpo' => $d['cuerpo'], 'tabla' => $d['tabla'] ?? null, 'fecha_texto' => $d['fecha_texto'],
            'url_corta' => (string) preg_replace('#^https?://#i', '', $url), 'codigo_fmt' => self::formatearCodigo((string) $c['codigo']),
            'emitido' => CertificadoPDF::fechaLarga(substr((string) $c['emitido_en'], 0, 10)), 'folio' => str_pad((string) $c['id'], 6, '0', STR_PAD_LEFT),
        ];
        return [$insPdf, $cert, $url];
    }

    /**
     * Genera el PDF del certificado a partir de su copia guardada.
     * $conQr = false produce la versión SIN código QR (solo código y dirección de verificación escritos), útil si el QR no se imprime bien.
     */
    public static function pdf(array $c, bool $conQr = true): string
    {
        [$insPdf, $cert, $url] = self::datosPdf($c);
        $pdf = new CertificadoPDF();
        CertificadoPDF::dibujar($pdf, $insPdf, $cert, $conQr ? QrCode::matriz($url) : null);
        return $pdf->contenido();
    }

    /**
     * Varios certificados en UN solo PDF (cada uno empieza en hoja nueva y conserva su numeración, borde, firmas y QR).
     * @param array<int, array<string,mixed>> $certificados resultados de buscar()
     */
    public static function pdfLote(array $certificados, bool $conQr = true): string
    {
        $pdf = new CertificadoPDF();
        $primero = true;
        foreach ($certificados as $c) {
            [$insPdf, $cert, $url] = self::datosPdf($c);
            if (!$primero) {
                $pdf->nuevaPagina();
            }
            $primero = false;
            CertificadoPDF::dibujar($pdf, $insPdf, $cert, $conQr ? QrCode::matriz($url) : null);
        }
        return $primero ? '' : $pdf->contenido();
    }

    /** Hoja de verificación de UNA página: QR grande, código y datos públicos del certificado (para pegar en un expediente o carpeta). */
    public static function pdfHojaVerificacion(array $c): string
    {
        [$insPdf, $cert, $url] = self::datosPdf($c);
        $color = CertificadoPDF::hexARgb((string) ($insPdf['color'] ?? '#AD6B05'));
        $pdf = new CertificadoPDF();
        $pdf->titulo = 'Hoja de verificación - ' . $insPdf['nombre'];
        $pdf->autor = (string) $insPdf['nombre'];
        $X = 60.0;
        $W = 492.0;
        $pdf->rect(24, 24, 564, 744, null, $color, 2.2);
        $pdf->rect(30, 30, 552, 732, null, $color, 0.6);
        $nombre = mb_strtoupper((string) $insPdf['nombre'], 'UTF-8');
        $pdf->texto($X, 90, $nombre, $pdf->tamanoQueCabe($nombre, $W, 16, 10, true), true, [0.1, 0.1, 0.1], 'c', $W);
        $pdf->linea($X, 104, $X + $W, 104, 1.2, $color);
        $pdf->texto($X, 150, 'HOJA DE VERIFICACIÓN', 18, true, $color, 'c', $W);
        $y = $pdf->parrafo($X, 190, $W, (string) $cert['titulo'], 13, 18, true, 'c', [0.1, 0.1, 0.1]);
        $pdf->rect(206, $y + 20, 200, 200, [1, 1, 1], [0.8, 0.8, 0.8], 0.6);
        $pdf->qr(QrCode::matriz($url), 216, $y + 30, 180);
        $y += 250;
        $pdf->texto($X, $y, 'Código de verificación', 10, false, [0.35, 0.35, 0.35], 'c', $W);
        $pdf->texto($X, $y + 26, (string) $cert['codigo_fmt'], 24, true, [0.1, 0.1, 0.1], 'c', $W);
        $y = $pdf->parrafo($X, $y + 56, $W, 'Escanee el código QR con la cámara del celular o ingrese a:', 10.5, 14, false, 'c', [0.35, 0.35, 0.35]);
        $y = $pdf->parrafo($X + 20, $y + 2, $W - 40, (string) $cert['url_corta'], 11, 15, true, 'c', [0.1, 0.25, 0.55]);
        $y = $pdf->parrafo($X, $y + 14, $W, sprintf('Certificado emitido el %s · Folio N.º %s.', $cert['emitido'], $cert['folio']), 10, 14, false, 'c', [0.35, 0.35, 0.35]);
        $pdf->parrafo($X + 20, $y + 8, $W - 40, 'La página de verificación confirma si el certificado existe, está vigente y no fue alterado. Este documento por sí solo no reemplaza el certificado.', 9, 12.5, false, 'c', [0.45, 0.45, 0.45]);
        $pdf->texto($X, 782, 'Generado con Serena · Gestión escolar', 7, false, [0.6, 0.6, 0.6], 'c', $W);
        return $pdf->contenido();
    }

    /** Certificados (con sus datos decodificados) de una lista de códigos del colegio, en el orden pedido. Omite los que no existan, no sean del colegio o no estén vigentes. @return array<int, array<string,mixed>> */
    public static function buscarVarios(PDO $pdo, int $colegioId, array $codigos): array
    {
        $res = [];
        foreach (array_slice(array_unique(array_map(static fn ($c) => self::normalizarCodigo((string) $c), $codigos)), 0, 100) as $codigo) {
            $c = self::buscar($pdo, $codigo);
            if ($c !== null && (int) $c['colegio_id'] === $colegioId && self::estado($c) === 'valido') {
                $res[] = $c;
            }
        }
        return $res;
    }
}
