<?php
declare(strict_types=1);

// ============================================================================
// ECOSISTEMA IA DE SERENA — OpenRouter (modelos gratuitos)
// Reemplaza a src/helpers/gemini.php. Todo lo que use IA pasa por aquí.
//
// Qué hace por ti:
//  1. Descubre solo los modelos GRATIS vigentes (los ":free" rotan casi cada
//     semana; una lista fija se rompe sola). Se cachea 6 h. Si el descubrimiento
//     falla, usa "openrouter/free" (el enrutador gratuito de OpenRouter).
//  2. Prueba varios modelos en orden: si uno da 429 (límite), 404 (sin
//     proveedor), 5xx o texto cortado, pasa al siguiente.
//  3. Interruptor de pausa: si TODO falla, no vuelve a intentar durante unos
//     minutos (los modelos :free tienen tope de ~20 peticiones/min).
//  4. Privacidad: por defecto pide SOLO proveedores que no guardan ni entrenan
//     con los prompts, y anonimiza correos/teléfonos/enlaces antes de enviar.
//  5. Bitácora (tabla ia_registro, sin guardar prompts ni respuestas).
//
// Quien llama SIEMPRE debe tener un texto de respaldo: toda función devuelve
// null ante cualquier problema (sin clave, sin conexión, cuota, texto cortado).
//
// Variables en src/config/.env (todas opcionales salvo la primera):
//   OPENROUTER_API_KEY=sk-or-v1-...
//   OPENROUTER_MODELOS=            lista fija separada por comas (anula el descubrimiento)
//   OPENROUTER_DATA_COLLECTION=deny   deny (recomendado) | allow
//   OPENROUTER_MAX_INTENTOS=4      cuántos modelos probar por consulta
//   APP_URL=https://tu-dominio     (cabecera HTTP-Referer; opcional)
// ============================================================================

const IA_BASE_URL        = 'https://openrouter.ai/api/v1';
const IA_MODELO_RESPALDO = 'openrouter/free';
const IA_CACHE_MODELOS_S = 21600; // 6 horas
const IA_PAUSA_NORMAL_S  = 300;   // 5 min tras fallar todo (límite/caída)
const IA_PAUSA_CLAVE_S   = 1800;  // 30 min si la clave es inválida (401/403)
const IA_PRESUPUESTO_S   = 26;    // tiempo máximo total por consulta (hostings limitan a ~30 s)

// ---------------------------------------------------------------- configuración

function iaClave(): string
{
    return trim(entornoServer('OPENROUTER_API_KEY'));
}

/** ¿Hay IA configurada? Sin clave, Serena usa solo las plantillas fijas. */
function iaDisponible(): bool
{
    return iaClave() !== '';
}

/**
 * deny = solo proveedores que no guardan ni entrenan con los datos.
 * Se puede afinar por tarea: OPENROUTER_DATA_COLLECTION_ANALISIS=allow aplica solo
 * a los resúmenes de cifras agregadas (sin nombres); las encuestas siguen en el valor global.
 */
function iaPrivacidad(string $tarea = ''): string
{
    $valor = $tarea !== '' ? entornoServer('OPENROUTER_DATA_COLLECTION_' . strtoupper($tarea)) : '';
    if ($valor === '') {
        $valor = entornoServer('OPENROUTER_DATA_COLLECTION', 'deny');
    }
    return strtolower($valor) === 'allow' ? 'allow' : 'deny';
}

// ---------------------------------------------------------------- caché en disco

function iaRutaTemporal(string $nombre): string
{
    $dirs = [sys_get_temp_dir(), __DIR__ . '/../uploads'];
    foreach ($dirs as $d) {
        if (is_dir($d) && is_writable($d)) {
            return rtrim($d, '/\\') . DIRECTORY_SEPARATOR . 'serena_ia_' . $nombre;
        }
    }
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'serena_ia_' . $nombre;
}

function iaPausaActiva(): bool
{
    $ruta = iaRutaTemporal('pausa.txt');
    if (!is_file($ruta)) {
        return false;
    }
    $hasta = (int) @file_get_contents($ruta);
    return $hasta > time();
}

function iaActivarPausa(int $segundos): void
{
    @file_put_contents(iaRutaTemporal('pausa.txt'), (string) (time() + $segundos));
}

function iaQuitarPausa(): void
{
    @unlink(iaRutaTemporal('pausa.txt'));
}

// ---------------------------------------------------------------- lista negra temporal

/** @return array<string,int> modelo => timestamp hasta el que se salta */
function iaModelosMalos(): array
{
    $datos = json_decode((string) @file_get_contents(iaRutaTemporal('malos.json')), true);
    if (!is_array($datos)) {
        return [];
    }
    return array_filter($datos, fn($hasta) => (int) $hasta > time());
}

function iaMarcarMalo(string $modelo, int $segundos): void
{
    $malos = iaModelosMalos();
    $malos[$modelo] = time() + $segundos;
    @file_put_contents(iaRutaTemporal('malos.json'), json_encode($malos));
}

function iaLimpiarMalos(): void
{
    @unlink(iaRutaTemporal('malos.json'));
}

// ---------------------------------------------------------------- modelos gratis

/**
 * Lista ordenada de modelos a probar. Prioridad:
 *  1) OPENROUTER_MODELOS del .env (si lo defines, manda).
 *  2) Descubrimiento automático de modelos con precio 0 (cache 6 h).
 *  3) "openrouter/free" siempre al final como último recurso.
 *
 * @return string[]
 */
function iaModelosGratis(bool $forzarRefresco = false): array
{
    $fijos = array_values(array_filter(array_map('trim', explode(',', entornoServer('OPENROUTER_MODELOS')))));
    if (!empty($fijos)) {
        return array_values(array_unique(array_merge($fijos, [IA_MODELO_RESPALDO])));
    }

    $ruta = iaRutaTemporal('modelos.json');
    if (!$forzarRefresco && is_file($ruta) && (time() - (int) @filemtime($ruta)) < IA_CACHE_MODELOS_S) {
        $guardado = json_decode((string) @file_get_contents($ruta), true);
        if (is_array($guardado) && !empty($guardado)) {
            // Por si la caché es de una versión anterior que dejó pasar modelos de audio/música.
            $guardado = array_values(array_filter($guardado, fn($id) => !preg_match('/(lyria|music|veo|imagen|audio|tts)/i', (string) $id)));
            if (!empty($guardado)) {
                return $guardado;
            }
        }
    }

    $descubiertos = iaDescubrirModelosGratis();
    $lista = array_values(array_unique(array_merge($descubiertos, [IA_MODELO_RESPALDO])));

    if (!empty($descubiertos)) {
        @file_put_contents($ruta, json_encode($lista));
    }
    return $lista;
}

/** Consulta el catálogo público de OpenRouter y filtra los de precio 0. @return string[] */
function iaDescubrirModelosGratis(): array
{
    $ch = curl_init(IA_BASE_URL . '/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $http !== 200) {
        error_log("IA: no se pudo leer el catálogo de OpenRouter (HTTP {$http}).");
        return [];
    }

    $datos = json_decode($resp, true);
    $modelos = $datos['data'] ?? [];
    if (!is_array($modelos)) {
        return [];
    }

    $candidatos = [];
    foreach ($modelos as $m) {
        $id = (string) ($m['id'] ?? '');
        if ($id === '' || $id === IA_MODELO_RESPALDO) {
            continue;
        }
        $precioEntrada = (string) ($m['pricing']['prompt'] ?? '1');
        $precioSalida  = (string) ($m['pricing']['completion'] ?? '1');
        if ((float) $precioEntrada !== 0.0 || (float) $precioSalida !== 0.0) {
            continue;
        }
        // Los modelos "stealth" son anónimos y registran todo lo que reciben:
        // jamás para datos de estudiantes.
        if (stripos($id, 'stealth/') === 0) {
            continue;
        }
        // Solo texto → texto, y descartar los que no sirven para redactar.
        // Salida EXACTAMENTE texto: los modelos de música/audio/imagen (p. ej. Lyria)
        // también cuestan 0 y declaran "text", pero no sirven para redactar.
        $salidas = $m['architecture']['output_modalities'] ?? ['text'];
        if (!is_array($salidas) || $salidas !== ['text']) {
            continue;
        }
        $entradas = $m['architecture']['input_modalities'] ?? ['text'];
        if (is_array($entradas) && !in_array('text', $entradas, true)) {
            continue;
        }
        if (preg_match('/(embed|moderation|guard|safeguard|thinking|reason|r1\b|coder|vision|-vl\b|audio|image|tts|lyria|music|veo|imagen)/i', $id)) {
            continue;
        }
        $contexto = (int) ($m['context_length'] ?? 0);
        if ($contexto < 8000) {
            continue;
        }
        $candidatos[$id] = $contexto;
    }

    // Más contexto primero (suele coincidir con modelos más grandes y capaces).
    arsort($candidatos);
    return array_slice(array_keys($candidatos), 0, 8);
}

// ---------------------------------------------------------------- utilidades de texto

/** Quita lo que no debe salir del servidor: correos, enlaces, teléfonos/documentos. */
function iaAnonimizar(string $texto): string
{
    $texto = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[correo]', $texto) ?? $texto;
    $texto = preg_replace('~https?://\S+|www\.\S+~i', '[enlace]', $texto) ?? $texto;
    $texto = preg_replace('/\+?\d[\d\s().\-]{6,}\d/', '[número]', $texto) ?? $texto;
    return $texto;
}

/** Quita bloques de razonamiento (<think>) y cercas de código que algunos modelos agregan. */
function iaLimpiarRespuesta(string $texto): string
{
    $texto = preg_replace('~<think>.*?</think>~is', '', $texto) ?? $texto;
    $texto = preg_replace('~^```[a-z]*\s*|\s*```$~i', '', trim($texto)) ?? $texto;
    return trim($texto);
}

/** ¿La última oración realmente terminó? (., !, ?, … con cierre opcional de comilla/paréntesis) */
function iaTerminaBien(string $texto): bool
{
    return (bool) preg_match('/[.!?…](["\'”’\)])?\s*$/u', $texto);
}

// ---------------------------------------------------------------- bitácora

function iaRegistrar(string $tarea, string $modelo, bool $ok, int $http, int $ms, string $error = ''): void
{
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return;
    }
    try {
        $pdo->prepare(
            'INSERT INTO ia_registro (tarea, modelo, ok, http_codigo, ms, error)
             VALUES (:t, :m, :ok, :h, :ms, :e)'
        )->execute([
            't'  => mb_substr($tarea, 0, 40),
            'm'  => mb_substr($modelo, 0, 120),
            'ok' => $ok ? 1 : 0,
            'h'  => $http,
            'ms' => $ms,
            'e'  => mb_substr($error, 0, 255),
        ]);
    } catch (Throwable $e) {
        // La tabla puede no existir todavía (migración 042): la bitácora nunca rompe la página.
    }
}

// ---------------------------------------------------------------- llamada de bajo nivel

/**
 * Una sola petición a UN modelo.
 * @return array{texto:?string, http:int, error:string, modelo_usado:string, ms:int}
 */
function iaPeticion(string $modelo, string $prompt, float $temperatura, int $maxTokens, int $timeout, string $privacidad): array
{
    $cuerpo = [
        'model'       => $modelo,
        // Todo va en UN mensaje de usuario a propósito: algunos modelos gratis
        // (p. ej. familia Gemma) rechazan el rol "system".
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'temperature' => $temperatura,
        'max_tokens'  => $maxTokens,
        'provider'    => ['data_collection' => $privacidad],
    ];

    $referer = entornoServer('APP_URL');
    if ($referer === '' && !empty($_SERVER['HTTP_HOST'])) {
        $referer = 'https://' . $_SERVER['HTTP_HOST'];
    }

    $ch = curl_init(IA_BASE_URL . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . iaClave(),
            'HTTP-Referer: ' . ($referer !== '' ? $referer : 'https://serena.local'),
            'X-Title: Serena',
        ],
        CURLOPT_POSTFIELDS     => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => max(5, $timeout),
    ]);

    $inicio = microtime(true);
    $resp   = curl_exec($ch);
    $http   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errCurl = curl_error($ch);
    curl_close($ch);
    $ms = (int) round((microtime(true) - $inicio) * 1000);

    $salida = ['texto' => null, 'http' => $http, 'error' => '', 'modelo_usado' => $modelo, 'ms' => $ms];

    if ($resp === false || $errCurl !== '') {
        $salida['error'] = 'conexión: ' . $errCurl;
        return $salida;
    }

    $datos = json_decode($resp, true);

    if ($http !== 200) {
        $detalle = is_array($datos) ? (string) ($datos['error']['message'] ?? '') : '';
        $salida['error'] = 'HTTP ' . $http . ($detalle !== '' ? ': ' . $detalle : '');
        return $salida;
    }

    // OpenRouter a veces responde 200 con un error dentro del JSON.
    if (isset($datos['error'])) {
        $salida['http']  = (int) ($datos['error']['code'] ?? 500);
        $salida['error'] = 'proveedor: ' . (string) ($datos['error']['message'] ?? 'error');
        return $salida;
    }

    $salida['modelo_usado'] = (string) ($datos['model'] ?? $modelo);
    $contenido    = $datos['choices'][0]['message']['content'] ?? null;
    $finalizacion = $datos['choices'][0]['finish_reason'] ?? null;

    if (!is_string($contenido) || trim($contenido) === '') {
        $salida['error'] = 'respuesta vacía' . ($finalizacion === 'length' ? ' (todo el presupuesto se fue en razonamiento interno)' : '');
        return $salida;
    }
    if ($finalizacion === 'length') {
        $salida['error'] = 'respuesta cortada por límite de tokens';
        return $salida;
    }

    $salida['texto'] = iaLimpiarRespuesta($contenido);
    if ($salida['texto'] === '') {
        $salida['texto'] = null;
        $salida['error'] = 'respuesta vacía tras limpiar';
    }
    return $salida;
}

// ---------------------------------------------------------------- API pública del ecosistema

/**
 * Llamada genérica con cadena de modelos gratis y respaldo. Devuelve null si nada funcionó.
 *
 * Opciones:
 *   tarea            etiqueta para la bitácora (p. ej. 'analisis', 'encuesta')
 *   temperatura      0.6
 *   max_tokens       900
 *   exigir_cierre    true  → descarta textos que no terminan en signo de cierre
 *   max_intentos     cuántos modelos probar (por defecto OPENROUTER_MAX_INTENTOS o 4)
 *
 * @param array<string,mixed> $opc
 * @param array<int,array<string,mixed>>|null $traza  (opcional) detalle de cada intento, para diagnóstico
 */
function iaLlamar(string $prompt, array $opc = [], ?array &$traza = null): ?string
{
    $traza = [];
    if (!iaDisponible()) {
        return null;
    }
    if (iaPausaActiva()) {
        $traza[] = ['modelo' => '-', 'http' => 0, 'ms' => 0, 'error' => 'en pausa tras fallos recientes (se reintenta solo)'];
        return null;
    }

    $tarea       = (string) ($opc['tarea'] ?? 'general');
    $temperatura = (float) ($opc['temperatura'] ?? 0.6);
    $maxTokens   = (int) ($opc['max_tokens'] ?? 900);
    $exigirCierre = (bool) ($opc['exigir_cierre'] ?? true);
    $maxIntentos = (int) ($opc['max_intentos'] ?? (int) entornoServer('OPENROUTER_MAX_INTENTOS', '4'));
    $maxIntentos = max(1, min(8, $maxIntentos));

    $privacidad  = (string) ($opc['privacidad'] ?? iaPrivacidad($tarea));
    $malos       = iaModelosMalos();

    $inicioTotal = microtime(true);
    $claveInvalida = false;
    $intentos = 0;

    foreach (iaModelosGratis() as $modelo) {
        if (isset($malos[$modelo])) {
            continue; // falló hace poco (sin proveedor, caído o con límite)
        }
        if ($intentos >= $maxIntentos) {
            break;
        }
        $restante = IA_PRESUPUESTO_S - (int) (microtime(true) - $inicioTotal);
        if ($restante < 5) {
            break;
        }

        $r = iaPeticion($modelo, $prompt, $temperatura, $maxTokens, min(14, $restante), $privacidad);

        // Un 404 (sin proveedor que cumpla la política) responde en milisegundos:
        // no consume uno de los intentos reales.
        if ($r['http'] !== 404) {
            $intentos++;
        }

        if ($r['texto'] !== null && $exigirCierre && !iaTerminaBien($r['texto'])) {
            $r['error'] = 'texto sin cierre de oración, descartado';
            $r['texto'] = null;
        }

        $traza[] = ['modelo' => $r['modelo_usado'], 'http' => $r['http'], 'ms' => $r['ms'], 'error' => $r['error']];
        iaRegistrar($tarea, $r['modelo_usado'], $r['texto'] !== null, $r['http'], $r['ms'], $r['error']);

        if ($r['texto'] !== null) {
            iaQuitarPausa();
            return $r['texto'];
        }

        error_log("IA [{$tarea}] {$modelo}: {$r['error']}");

        // Recordar el fallo para no repetirlo en cada carga de página.
        if ($r['http'] === 404) {
            iaMarcarMalo($modelo, 21600);       // sin proveedor para esta política: 6 h
        } elseif ($r['http'] === 429) {
            iaMarcarMalo($modelo, 120);         // límite: 2 min
        } elseif ($r['http'] === 0 || $r['http'] >= 500) {
            iaMarcarMalo($modelo, 600);         // caído / 502: 10 min
        }

        // Clave mala o sin permiso: probar más modelos no sirve de nada.
        if (in_array($r['http'], [401, 403], true)) {
            $claveInvalida = true;
            break;
        }
    }

    iaActivarPausa($claveInvalida ? IA_PAUSA_CLAVE_S : IA_PAUSA_NORMAL_S);
    return null;
}

// ---------------------------------------------------------------- tareas concretas de Serena

// Reescribe un texto YA CALCULADO con datos reales (ver src/helpers/analisis.php)
// en un tono más cálido y natural. A propósito NO se le pide a la IA que calcule
// nada — solo que redacte mejor cifras que ya vienen fijas en $textoBase, para no
// arriesgarnos a que "invente" un número.
function reescribirConIA(string $textoBase): ?string
{
    $prompt = 'Reescribe el siguiente resumen en un párrafo cálido, cercano y sin '
        . 'lenguaje clínico ni diagnóstico, en español neutro, para el dashboard de '
        . 'una plataforma de bienestar emocional escolar. No inventes cifras nuevas, '
        . 'no cambies ni redondees los números, y no agregues datos que no estén ya '
        . 'en el texto original. Máximo 4 oraciones, sin encabezados ni viñetas, solo '
        . 'el párrafo, sin saludos ni comentarios tuyos. Es muy importante que termines '
        . "la última oración por completo, con su punto final — nunca la dejes a la mitad.\n\n"
        . "Texto original:\n" . iaAnonimizar($textoBase);

    $resultado = iaLlamar($prompt, ['tarea' => 'analisis', 'temperatura' => 0.6]);
    return $resultado !== null ? iaValidarCifras($textoBase, $resultado) : null;
}

// Agrupa respuestas de texto libre de una encuesta en temas comunes. Nunca cita
// una respuesta textualmente ni identifica a quién la escribió — solo agrupa
// temas, sin sacar conclusiones clínicas. Las respuestas se anonimizan antes de salir.
function agruparTemasConIA(array $textos): ?string
{
    $textos = array_values(array_filter(array_map('trim', $textos), fn($t) => $t !== ''));
    if (empty($textos)) {
        return null;
    }

    $muestra = array_slice($textos, 0, 200);
    $lista = '- ' . implode("\n- ", array_map(fn($t) => iaAnonimizar(mb_substr($t, 0, 300)), $muestra));

    $prompt = 'Estas son respuestas de estudiantes a una pregunta abierta de una '
        . 'encuesta escolar de bienestar emocional. Agrúpalas por temas comunes y '
        . 'escribe un resumen breve (máximo 4 oraciones) en español, mencionando los '
        . 'temas más frecuentes. No cites ninguna respuesta textualmente, no '
        . 'identifiques a nadie, y no saques conclusiones clínicas ni diagnósticos — '
        . 'solo agrupa los temas que aparecen. Responde solo con el párrafo. Es muy '
        . 'importante que termines la última oración por completo, con su punto final '
        . "— nunca la dejes a la mitad.\n\nRespuestas:\n" . $lista;

    return iaLlamar($prompt, ['tarea' => 'encuesta', 'temperatura' => 0.5]);
}

// Red de seguridad anti-invención: los modelos gratis son más propensos a
// "mejorar" cifras. Si el texto reescrito contiene un número que NO estaba en el
// original, se descarta y se muestra el texto base (cifras reales, sin IA).
function iaValidarCifras(string $textoBase, string $textoIA): ?string
{
    preg_match_all('/\d+(?:[.,]\d+)?/u', $textoBase, $base);
    preg_match_all('/\d+(?:[.,]\d+)?/u', $textoIA, $nuevo);
    $aNumero = fn(string $n): float => (float) str_replace(',', '.', $n);
    $permitidos = array_map($aNumero, $base[0]);

    foreach ($nuevo[0] as $n) {
        if (!in_array($aNumero($n), $permitidos, true)) {
            error_log("IA: texto descartado, contiene una cifra que no estaba en el original ({$n}).");
            return null;
        }
    }
    return $textoIA;
}
