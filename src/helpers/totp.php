<?php
declare(strict_types=1);

// TOTP (RFC 6238) sin librerías externas: compatible con Google Authenticator,
// Microsoft Authenticator, Authy, 2FAS, etc. (SHA-1, 6 dígitos, paso de 30 s).
// Requiere config.php cargado (usa entornoServer()).

const TOTP_DIGITOS = 6;
const TOTP_PERIODO = 30;
const TOTP_EMISOR = 'Serena';

const BASE32_ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32Codificar(string $binario): string
{
    $bits = '';
    foreach (str_split($binario) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $salida = '';
    foreach (str_split($bits, 5) as $trozo) {
        $salida .= BASE32_ALFABETO[bindec(str_pad($trozo, 5, '0', STR_PAD_RIGHT))];
    }
    return $salida; // sin relleno "=", como esperan las apps de autenticación
}

function base32Decodificar(string $texto): string
{
    $texto = strtoupper(preg_replace('/[\s=-]+/', '', $texto));
    $bits = '';
    foreach (str_split($texto) as $caracter) {
        $pos = strpos(BASE32_ALFABETO, $caracter);
        if ($pos === false) {
            throw new InvalidArgumentException('Base32 inválido.');
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $binario = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $binario .= chr(bindec($byte));
        }
    }
    return $binario;
}

// 20 bytes aleatorios (160 bits) = 32 caracteres base32.
function totpGenerarSecreto(): string
{
    return base32Codificar(random_bytes(20));
}

function totpPasoActual(?int $ahora = null): int
{
    return intdiv($ahora ?? time(), TOTP_PERIODO);
}

function totpCodigoParaPaso(string $secretoBase32, int $paso): string
{
    $clave = base32Decodificar($secretoBase32);
    $mensaje = pack('N2', $paso >> 32, $paso & 0xFFFFFFFF); // contador de 64 bits, big-endian
    $hash = hash_hmac('sha1', $mensaje, $clave, true);
    $desplazamiento = ord($hash[19]) & 0x0F;
    $entero = ((ord($hash[$desplazamiento]) & 0x7F) << 24)
        | ((ord($hash[$desplazamiento + 1]) & 0xFF) << 16)
        | ((ord($hash[$desplazamiento + 2]) & 0xFF) << 8)
        | (ord($hash[$desplazamiento + 3]) & 0xFF);
    return str_pad((string) ($entero % (10 ** TOTP_DIGITOS)), TOTP_DIGITOS, '0', STR_PAD_LEFT);
}

// Devuelve el paso (contador) que coincidió, o null si el código no es válido.
// Acepta ±1 paso (±30 s) por desfase de reloj del teléfono. $ultimoPasoUsado
// impide reutilizar un código ya aceptado (anti-repetición): solo pasos
// estrictamente mayores al último usado sirven.
function totpVerificar(string $secretoBase32, string $codigo, ?int $ultimoPasoUsado = null, int $ventana = 1, ?int $ahora = null): ?int
{
    $codigo = preg_replace('/\s+/', '', $codigo);
    if (!preg_match('/^\d{' . TOTP_DIGITOS . '}$/', $codigo)) {
        return null;
    }
    $actual = totpPasoActual($ahora);
    $coincidencia = null;
    for ($desfase = -$ventana; $desfase <= $ventana; $desfase++) {
        $paso = $actual + $desfase;
        // Se recorren todos los pasos sin cortar antes: el tiempo de respuesta no
        // delata en cuál coincidió.
        if (hash_equals(totpCodigoParaPaso($secretoBase32, $paso), $codigo)) {
            $coincidencia = $paso;
        }
    }
    if ($coincidencia === null) {
        return null;
    }
    if ($ultimoPasoUsado !== null && $coincidencia <= $ultimoPasoUsado) {
        return null;
    }
    return $coincidencia;
}

function totpUriAprovisionamiento(string $secretoBase32, string $cuenta): string
{
    return 'otpauth://totp/' . rawurlencode(TOTP_EMISOR) . ':' . rawurlencode($cuenta)
        . '?secret=' . $secretoBase32
        . '&issuer=' . rawurlencode(TOTP_EMISOR)
        . '&algorithm=SHA1&digits=' . TOTP_DIGITOS . '&period=' . TOTP_PERIODO;
}

// "ABCD EFGH IJKL ..." para dictar/copiar la clave a mano sin equivocarse.
function totpClaveAgrupada(string $secretoBase32): string
{
    return trim(chunk_split($secretoBase32, 4, ' '));
}

// --- Cifrado del secreto en la base de datos -------------------------------
// El secreto TOTP tiene que poder leerse de vuelta (no se puede guardar como
// hash), así que se guarda cifrado con AES-256-GCM. La clave sale de, en orden:
//   1. SERENA_2FA_KEY en src/config/.env (64 caracteres hexadecimales) — lo ideal.
//   2. Un archivo src/config/.clave_2fa que se crea solo la primera vez. Queda
//      fuera del alcance web por el .htaccess raíz (bloquea /src/). OJO: si ese
//      archivo se borra o se cambia de servidor sin copiarlo, los secretos
//      guardados dejan de poder leerse y cada persona debe volver a activar la
//      verificación (el administrador puede desactivarla desde "Editar usuario").
function clave2FA(): string
{
    $hex = entornoServer('SERENA_2FA_KEY');
    if (preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
        return hex2bin($hex);
    }

    $ruta = __DIR__ . '/../config/.clave_2fa';
    if (is_file($ruta)) {
        $hex = trim((string) file_get_contents($ruta));
        if (preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
            return hex2bin($hex);
        }
        throw new RuntimeException('El archivo src/config/.clave_2fa está dañado.');
    }

    $nueva = bin2hex(random_bytes(32));
    $manejador = @fopen($ruta, 'x'); // 'x': falla si otro proceso lo creó justo antes
    if ($manejador === false) {
        if (is_file($ruta)) {
            return clave2FA(); // lo creó otra petición en paralelo
        }
        throw new RuntimeException('No se pudo crear la clave de cifrado. Define SERENA_2FA_KEY en src/config/.env.');
    }
    fwrite($manejador, $nueva);
    fclose($manejador);
    @chmod($ruta, 0600);
    return hex2bin($nueva);
}

function totpCifrarSecreto(string $secretoBase32): string
{
    $iv = random_bytes(12);
    $etiqueta = '';
    $cifrado = openssl_encrypt($secretoBase32, 'aes-256-gcm', clave2FA(), OPENSSL_RAW_DATA, $iv, $etiqueta);
    if ($cifrado === false) {
        throw new RuntimeException('No se pudo cifrar el secreto.');
    }
    return 'v1:' . base64_encode($iv . $etiqueta . $cifrado);
}

function totpDescifrarSecreto(string $almacenado): ?string
{
    if (strncmp($almacenado, 'v1:', 3) !== 0) {
        return null;
    }
    $crudo = base64_decode(substr($almacenado, 3), true);
    if ($crudo === false || strlen($crudo) < 29) {
        return null;
    }
    $iv = substr($crudo, 0, 12);
    $etiqueta = substr($crudo, 12, 16);
    $cifrado = substr($crudo, 28);
    $claro = openssl_decrypt($cifrado, 'aes-256-gcm', clave2FA(), OPENSSL_RAW_DATA, $iv, $etiqueta);
    return $claro === false ? null : $claro;
}
