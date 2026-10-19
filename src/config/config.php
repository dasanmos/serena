<?php
declare(strict_types=1);

// Colombia no tiene horario de verano, así que un offset fijo es correcto todo el año.
// Sin esto, PHP corre en la zona horaria por defecto del servidor (normalmente UTC en
// InfinityFree), y cada date()/fecha_hora quedaba desfasada varias horas respecto a la
// hora real de Bogotá/Medellín.
date_default_timezone_set('America/Bogota');

// No depender solo de putenv()/getenv(): algunos hostings compartidos (InfinityFree
// entre ellos) deshabilitan putenv() por seguridad. Si eso pasa, el .env se lee pero
// nunca "pega" a las variables de entorno, y el sistema cae de vuelta a los valores
// por defecto (localhost) sin avisar — eso da exactamente "no se pudo conectar con la
// base de datos" en un hosting donde localhost no existe. Se guarda todo también en
// un array propio como respaldo, independiente de si putenv() funciona o no.
$GLOBALS['_serena_env'] = [];

$rutaEnv = __DIR__ . '/.env';
if (file_exists($rutaEnv)) {
    foreach (file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#') continue;
        if (strpos($linea, '=') === false) continue;
        [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
        if ($clave === '') continue;
        $GLOBALS['_serena_env'][$clave] = $valor;
        @putenv("$clave=$valor"); // se intenta igual, por si acaso sí funciona y algo más lo usa
    }
}

// Lee primero del array propio (siempre confiable), y solo si no está ahí intenta
// getenv() (por si viene de una variable de entorno real del servidor, no del .env).
function entornoServer(string $clave, string $porDefecto = ''): string
{
    if (isset($GLOBALS['_serena_env'][$clave]) && $GLOBALS['_serena_env'][$clave] !== '') {
        return $GLOBALS['_serena_env'][$clave];
    }
    $valor = getenv($clave);
    return $valor !== false && $valor !== '' ? $valor : $porDefecto;
}

if (!defined('BASE_URL')) {
    // Calcula automáticamente la carpeta base (ej: "/serena/public") comparando
    // la ruta real de la carpeta public/ contra la raíz del servidor (DOCUMENT_ROOT).
    // Así funciona igual con Virtual Host (BASE_URL = "") o accediendo directo
    // por http://localhost/serena/public/.
    $carpetaPublicReal = str_replace('\\', '/', realpath(__DIR__ . '/../../public'));
    $documentRoot       = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));

    if ($documentRoot !== '' && strpos($carpetaPublicReal, $documentRoot) === 0) {
        define('BASE_URL', substr($carpetaPublicReal, strlen($documentRoot)));
    } else {
        define('BASE_URL', '');
    }
}

/**
 * URL absoluta del sitio para enlaces que salen por correo (recuperación, credenciales,
 * alertas, QR de certificados). Usa APP_URL del .env (solo el dominio, p. ej.
 * https://serena.example.com, SIN /public) y NO la cabecera Host de la petición:
 * esa cabecera la controla quien hace la petición y permitía "envenenar" los enlaces
 * de recuperación para que apuntaran a un dominio ajeno y robar el token.
 * Sin APP_URL (desarrollo local) cae al Host, igual que antes.
 */
if (!function_exists('origenSitio')) {
function origenSitio(): string
{
    $base = rtrim(entornoServer('APP_URL'), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $base = ($https ? 'https' : 'http') . '://' . ($host !== '' ? $host : 'localhost');
    }
    return $base;
}

function urlAbsoluta(string $ruta = ''): string
{
    return origenSitio() . (defined('BASE_URL') ? BASE_URL : '') . $ruta;
}
}

// Hash de integridad (SRI) del face-api.js que carga public/assets/js/rostro.js
// desde jsdelivr. Vacío por defecto = sin verificación (funciona igual, pero sin
// esta defensa extra). Para activarla, corre esto una vez y pega el resultado:
//   curl -s https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js \
//     | openssl dgst -sha384 -binary | openssl base64 -A
// y pon el resultado como: define('FACEAPI_SRI_HASH', 'sha384-XXXXXXXX...');
if (!defined('FACEAPI_SRI_HASH')) {
    define('FACEAPI_SRI_HASH', '');
}

// Única cuenta de rol "estudiante" que un administrador puede usar con "Ver como
// usuario" (ver src/helpers/impersonacion.php). Fuente única de verdad: cualquier
// otro lugar que necesite esta excepción debe leer esta constante, nunca repetir
// el correo como texto literal.
if (!defined('CORREO_ESTUDIANTE_PRUEBA')) {
    define('CORREO_ESTUDIANTE_PRUEBA', 'estudiante@serena.edu');
}

// Cache-busting real para CSS/JS: sin esto, cada vez que se sube una versión nueva
// de un archivo con el MISMO nombre (base.css, base.js...), el navegador de quien
// ya visitó el sitio antes puede seguir usando su copia vieja en caché durante días,
// aunque el archivo en el servidor ya haya cambiado — así se ve "no funciona nada
// nuevo" cuando en realidad sí se subió, solo que el navegador no fue a buscarlo.
// filemtime() cambia automáticamente cada vez que se sube el archivo, así que no
// hace falta acordarse de subir un número de versión a mano en cada cambio.
function assetVersion(string $rutaRelativaDesdePublic): string
{
    $rutaAbsoluta = __DIR__ . '/../../public' . $rutaRelativaDesdePublic;
    $mtime = @filemtime($rutaAbsoluta);
    return $mtime !== false ? (string) $mtime : (string) time();
}

return [
    'db_host'     => entornoServer('DB_HOST', 'localhost'),
    'db_nombre'   => entornoServer('DB_NOMBRE', 'serena'),
    'db_usuario'  => entornoServer('DB_USUARIO', 'root'),
    'db_password' => entornoServer('DB_PASSWORD', ''),
];