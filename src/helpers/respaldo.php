<?php
declare(strict_types=1);

// Genera un volcado SQL completo con PDO puro (SELECT + INSERT armados a mano), no
// con mysqldump/exec() — InfinityFree y la mayoría de hostings compartidos deshabilitan
// exec()/shell_exec() por seguridad, así que no se puede confiar en tener el binario
// de mysqldump disponible. Esto funciona en cualquier hosting.
//
// Se usa tanto para la descarga directa (administrador/respaldo.php) como para la
// copia guardada en disco (administrador/configuracion.php) — una sola fuente de
// verdad para la lista de tablas y el formato del volcado.
function tablasParaRespaldo(): array
{
    return [
        'roles', 'grados', 'grupos', 'usuarios', 'personal_grupos', 'disponibilidad_citas',
        'citas', 'tokens_recuperacion', 'solicitudes_cambio', 'estado_emocional', 'sueno',
        'diario', 'diario_imagenes', 'etiquetas', 'diario_etiquetas', 'habitos',
        'seguimiento_habitos', 'metas', 'subtareas', 'notificaciones', 'recordatorios',
        'conversaciones', 'mensajes', 'encuestas', 'preguntas_encuesta', 'opciones_pregunta',
        'respuestas_encuesta', 'alertas_tempranas', 'insignias', 'usuario_insignias',
        'recursos_apoyo', 'auditoria', 'analisis_cache', 'texto_ia_cache', 'notas_sesion',
        'planes_seguimiento', 'pulsos_grupo', 'pulso_respuestas', 'configuracion', 'accesos_ficha',
    ];
}

function generarVolcadoSQL(PDO $pdo, string $generadoPor): string
{
    $sql = "-- Respaldo de Serena — generado el " . date('Y-m-d H:i:s') . " por {$generadoPor}\n";
    $sql .= "-- Para restaurar: importa este archivo en phpMyAdmin sobre una base de datos vacía\n";
    $sql .= "-- con el esquema ya creado (sql/schema.sql), o directamente si la base está vacía.\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach (tablasParaRespaldo() as $tabla) {
        $filas = $pdo->query("SELECT * FROM `{$tabla}`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($filas)) {
            continue;
        }

        $sql .= "-- Tabla: {$tabla} (" . count($filas) . " filas)\n";
        $columnas = array_keys($filas[0]);
        $columnasSql = '`' . implode('`, `', $columnas) . '`';

        foreach ($filas as $fila) {
            $valores = array_map(function ($valor) use ($pdo) {
                if ($valor === null) return 'NULL';
                return $pdo->quote((string) $valor);
            }, array_values($fila));
            $sql .= "INSERT INTO `{$tabla}` ({$columnasSql}) VALUES (" . implode(', ', $valores) . ");\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $sql;
}

// Cuántas copias guardadas se conservan en el servidor (las más recientes). Antes era
// solo una; con varias, un problema descubierto tarde (borrado accidental, datos
// dañados) todavía se puede revertir a una copia de días atrás.
const RESPALDOS_A_CONSERVAR = 5;

// Carpeta donde viven las copias guardadas — fuera de public/, igual que .env, para que
// nadie pueda descargarlas adivinando la URL: solo se llega a ellas pasando por
// respaldo_descargar.php, que exige sesión de administrador.
function carpetaRespaldos(): string
{
    $ruta = __DIR__ . '/../respaldos';
    if (!is_dir($ruta)) {
        mkdir($ruta, 0755, true);
    }
    return $ruta;
}

// Todas las copias guardadas, de la más reciente a la más antigua.
function listarRespaldos(): array
{
    $archivos = glob(carpetaRespaldos() . '/serena-respaldo-*.sql') ?: [];
    usort($archivos, fn($a, $b) => filemtime($b) <=> filemtime($a));
    return array_map(fn($ruta) => [
        'ruta'   => $ruta,
        'nombre' => basename($ruta),
        'fecha'  => filemtime($ruta),
        'tamano' => filesize($ruta),
    ], $archivos);
}

// La copia más reciente (o null si nunca se ha creado una).
function respaldoActual(): ?array
{
    return listarRespaldos()[0] ?? null;
}

// Busca una copia por nombre de archivo SOLO entre las que existen de verdad en la
// carpeta — nunca se arma una ruta a partir del texto recibido, así no se puede
// pedir ../../.env ni ningún otro archivo por la URL.
function respaldoPorNombre(string $nombre): ?array
{
    foreach (listarRespaldos() as $r) {
        if ($r['nombre'] === $nombre) {
            return $r;
        }
    }
    return null;
}

// Genera la copia nueva PRIMERO, confirma que quedó bien escrita en disco, y SOLO
// ENTONCES borra las que sobran (más allá de RESPALDOS_A_CONSERVAR) — nunca al revés.
// Si algo falla a mitad de camino (disco lleno, timeout, etc.), las copias anteriores
// siguen intactas.
function crearRespaldoSeguro(PDO $pdo, string $generadoPor): array
{
    $carpeta = carpetaRespaldos();
    $nombreFinal = 'serena-respaldo-' . date('Y-m-d_His') . '.sql';
    $rutaTemporal = $carpeta . '/' . $nombreFinal . '.tmp';

    $contenido = generarVolcadoSQL($pdo, $generadoPor);
    $escritos = @file_put_contents($rutaTemporal, $contenido);

    // Confirmación explícita de que el archivo quedó completo en disco: se compara el
    // tamaño escrito contra el esperado, no solo que file_put_contents no devolviera false.
    if ($escritos === false || $escritos !== strlen($contenido)) {
        @unlink($rutaTemporal);
        return ['exito' => false, 'mensaje' => 'No se pudo escribir la copia de seguridad en el servidor (revisa el espacio disponible en disco).'];
    }

    $rutaFinal = $carpeta . '/' . $nombreFinal;
    if (!@rename($rutaTemporal, $rutaFinal)) {
        @unlink($rutaTemporal);
        return ['exito' => false, 'mensaje' => 'La copia se generó pero no se pudo guardar con su nombre final.'];
    }

    // La copia nueva ya está confirmada: ahora sí, se descartan las más viejas que sobran.
    foreach (array_slice(listarRespaldos(), RESPALDOS_A_CONSERVAR) as $sobrante) {
        @unlink($sobrante['ruta']);
    }

    return ['exito' => true, 'mensaje' => 'Copia de seguridad creada correctamente.', 'ruta' => $rutaFinal];
}
