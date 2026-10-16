<?php
declare(strict_types=1);

// Envío de WhatsApp vía la API gratuita de CallMeBot (https://www.callmebot.com).
//
// A diferencia del correo (una sola cuenta Brevo para TODA la plataforma), acá
// CADA usuario debe activar su PROPIA apikey desde su propio teléfono antes de
// poder usar este método (ver public/seguridad.php → WhatsApp):
//   1. Agregar el número de CallMeBot a sus contactos.
//   2. Enviarle por WhatsApp el mensaje "I allow callmebot to send me messages".
//   3. CallMeBot responde con su apikey personal.
//   4. Esa apikey (+ su número) se guarda en whatsapp_2fa_numero / whatsapp_2fa_apikey.
//
// Es un servicio gratuito de un solo desarrollador, pensado para uso personal:
// no hay SLA ni garantía de entrega, y puede tardar hasta 1 minuto. Por eso el
// código de este 2FA vive más tiempo que el de correo (ver
// VerificacionDosPasosWhatsapp::VIGENCIA_MINUTOS).

const CALLMEBOT_URL_ENVIO = 'https://api.callmebot.com/whatsapp.php';

// Limpia lo que la gente escribe de forma natural ("+57 300 123-4567",
// "(300) 1234567", "0057 300...") y lo deja como +573001234567. No inventa el
// indicativo: si falta el "+" o el "00", se devuelve tal cual y la validación
// lo rechaza para que la persona lo corrija.
//
// CallMeBot ahora también identifica cuentas por un "lid" (identificador de
// usuario de WhatsApp, formato "NUMEROLARGO@lid") en vez de solo por número de
// teléfono — pasa con cuentas que usan nombre de usuario de WhatsApp en lugar
// de mostrar su número. Un identificador así se deja tal cual, sin tocarlo:
// normalizarlo como si fuera un teléfono (quitarle todo lo que no sea dígito)
// destruiría el "@lid" y lo dejaría inválido.
function whatsappNormalizarNumero(string $numero): string
{
    $numero = trim($numero);
    if (whatsappEsIdentificadorLid($numero)) {
        return $numero;
    }
    $tieneMas = strncmp($numero, '+', 1) === 0;
    $digitos = preg_replace('/\D+/', '', $numero);
    if (!$tieneMas && strncmp($digitos, '00', 2) === 0) {
        return '+' . substr($digitos, 2);
    }
    return $tieneMas ? '+' . $digitos : $digitos;
}

// La apikey de CallMeBot es corta y alfanumérica (por ejemplo "123456").
function whatsappApikeyValida(string $apikey): bool
{
    return (bool) preg_match('/^[A-Za-z0-9]{4,30}$/', $apikey);
}

// Identificador "lid" de WhatsApp: una cadena larga de dígitos seguida de
// "@lid" (ej. "273950831550569@lid"), que CallMeBot acepta como alternativa
// al número de teléfono para cuentas que muestran nombre de usuario en vez
// de número.
function whatsappEsIdentificadorLid(string $numero): bool
{
    return (bool) preg_match('/^\d{5,20}@lid$/', trim($numero));
}

// Valida el formato de un número en E.164 (+ seguido de 8 a 15 dígitos), que es
// lo que CallMeBot espera como "phone" cuando la cuenta usa número normal, O
// un identificador "lid" cuando la cuenta usa nombre de usuario de WhatsApp
// (ver whatsappEsIdentificadorLid).
function whatsappNumeroValido(string $numero): bool
{
    $numero = trim($numero);
    return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $numero) || whatsappEsIdentificadorLid($numero);
}

/**
 * Envía un mensaje de WhatsApp a través de CallMeBot usando la apikey personal
 * del usuario. Devuelve true si CallMeBot aceptó la solicitud (código HTTP 200
 * con cuerpo que empieza por "Message queued" o similar). No hay forma de
 * confirmar la entrega real desde el lado del servidor — es una limitación del
 * servicio gratuito, documentada arriba.
 */
function enviarWhatsappCallMeBot(string $numero, string $apikey, string $texto): bool
{
    if (!whatsappNumeroValido($numero) || trim($apikey) === '') {
        error_log('enviarWhatsappCallMeBot: número o apikey inválidos.');
        return false;
    }

    $url = CALLMEBOT_URL_ENVIO . '?' . http_build_query([
        'phone'  => $numero,
        'text'   => $texto,
        'apikey' => $apikey,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('enviarWhatsappCallMeBot: error de conexión — ' . $error);
        return false;
    }
    // CallMeBot responde 200 y un texto tipo "Message queued. You will receive
    // it in a few seconds." tanto en éxito como en algunos errores de negocio
    // (apikey vencida, número no activado, etc.) — no hay un código de error
    // estructurado, así que se revisan palabras clave conocidas de fallo.
    $fallo = $respuesta !== false && (
        stripos($respuesta, 'error') !== false
        || stripos($respuesta, 'invalid') !== false
        || stripos($respuesta, 'apikey is invalid') !== false
    );
    if ($codigoHttp !== 200 || $fallo) {
        error_log("enviarWhatsappCallMeBot: CallMeBot respondió HTTP {$codigoHttp} — {$respuesta}");
        return false;
    }
    return true;
}
