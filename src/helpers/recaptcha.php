<?php
declare(strict_types=1);

// Verificación de reCAPTCHA v2 contra la API de Google. Si no hay claves
// configuradas en .env, deja pasar sin exigir captcha — así el desarrollo
// local no se rompe si todavía no tienes las claves puestas.

function recaptchaConfigurado(): bool
{
    return entornoServer('RECAPTCHA_SITE_KEY') !== '' && entornoServer('RECAPTCHA_SECRET_KEY') !== '';
}

function recaptchaSiteKey(): string
{
    return entornoServer('RECAPTCHA_SITE_KEY');
}

// $tokenRespuesta es el valor de g-recaptcha-response que manda el formulario.
function verificarRecaptcha(string $tokenRespuesta): bool
{
    if (!recaptchaConfigurado()) return true; // sin claves = no se exige (modo desarrollo)
    if ($tokenRespuesta === '') return false;

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret'   => entornoServer('RECAPTCHA_SECRET_KEY'),
            'response' => $tokenRespuesta,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $respuesta = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || !$respuesta) {
        error_log('verificarRecaptcha: ' . ($error ?: 'sin respuesta de Google'));
        return false; // si Google no responde, se rechaza por seguridad, no se deja pasar
    }

    $datos = json_decode($respuesta, true);
    return (bool) ($datos['success'] ?? false);
}
