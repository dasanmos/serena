<?php
declare(strict_types=1);

// Envío de correo vía la API HTTP de Brevo (https://api.brevo.com), no SMTP crudo.
// Motivo: InfinityFree bloquea con frecuencia conexiones salientes al puerto 587
// (SMTP), incluso hacia proveedores legítimos como Brevo o Gmail — es un problema
// del hosting, no de las credenciales. La API funciona sobre HTTPS puerto 443,
// que nunca se bloquea, así que evita el problema de raíz en vez de pelear con él.
//
// Configuración esperada en src/config/.env:
//   BREVO_API_KEY=xkeysib-xxxxxxxxxxxxxxxx  (Brevo → SMTP & API → pestaña "API Keys")
//   SMTP_DESDE=correo_verificado_en_brevo@tudominio.com
//   SMTP_DESDE_NOMBRE=Serena
//
// Importante: la IP del servidor debe estar autorizada en Brevo → Security →
// Authorised IPs, si no todo falla con 401 "unauthorised IP" sin importar que
// la clave esté bien.

function smtpConfigurado(): bool
{
    return entornoServer('BREVO_API_KEY') !== '';
}

// Envía un correo. $htmlContent es opcional — si se manda, el correo sale con
// diseño (logo, colores de Serena, botones); $textoPlano siempre va también,
// como respaldo para clientes de correo que no muestran HTML.
function enviarCorreo(string $destinatario, string $asunto, string $textoPlano, string $htmlContent = ''): bool
{
    // Modo demostración: las cuentas @serena-demo.test son ficticias; no se les envía nada real.
    if (substr(strtolower(trim($destinatario)), -strlen('@serena-demo.test')) === '@serena-demo.test') {
        return true;
    }
    if (!smtpConfigurado()) {
        error_log('enviarCorreo: BREVO_API_KEY no configurado, correo no enviado.');
        return false;
    }

    $desde = entornoServer('SMTP_DESDE');
    $desdeNombre = entornoServer('SMTP_DESDE_NOMBRE') ?: 'Serena';

    if ($desde === '') {
        error_log('enviarCorreo: SMTP_DESDE no configurado, correo no enviado.');
        return false;
    }

    $cuerpo = [
        'sender'      => ['name' => $desdeNombre, 'email' => $desde],
        'to'          => [['email' => $destinatario]],
        'subject'     => $asunto,
        'textContent' => $textoPlano,
    ];
    if ($htmlContent !== '') {
        $cuerpo['htmlContent'] = $htmlContent;
    }

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($cuerpo),
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . entornoServer('BREVO_API_KEY'),
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('enviarCorreo: error de conexión — ' . $error);
        return false;
    }
    if ($codigoHttp !== 201) {
        error_log("enviarCorreo: Brevo respondió HTTP {$codigoHttp} — {$respuesta}");
        return false;
    }
    return true;
}

// Plantilla visual reutilizable — mismos colores/tipografía que el resto de Serena.
// $tituloPrincipal: aparece grande bajo el logo (ej. "Recupera tu contraseña").
// $cuerpoHtml: el contenido específico del correo, ya en HTML (párrafos, botón, etc.).
// Nada de CSS externo ni <style> en el <head>: los clientes de correo (Gmail,
// Outlook) ignoran o recortan eso — todo el estilo va inline, es la única forma
// confiable de que se vea igual en todos lados.
function plantillaCorreoHtml(string $tituloPrincipal, string $cuerpoHtml): string
{
    // Lema y pie editables por el administrador (Configuración → Correos). Sin conexión
    // disponible o sin ajuste guardado, salen los textos originales de siempre.
    require_once __DIR__ . '/configuracion.php';
    $pdoConfig = (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) ? $GLOBALS['pdo'] : null;
    $lema = htmlspecialchars(configuracionObtener($pdoConfig, 'correo_lema', 'Cuerpo • Mente • Bienestar'), ENT_QUOTES, 'UTF-8');
    $pie = nl2br(htmlspecialchars(configuracionObtener(
        $pdoConfig,
        'correo_pie',
        "Este correo fue enviado por Serena, plataforma de bienestar emocional escolar.\nSi no esperabas este mensaje, puedes ignorarlo con confianza."
    ), ENT_QUOTES, 'UTF-8'));
    $logoUrl = 'https://serena.infinityfreeapp.com/public/assets/img/logo/logo.png';
    $dorado = '#E8B923';
    $doradoClaro = '#F9D976';
    $carbon = '#2B2B2B';
    $grisTexto = '#6B6B6B';
    $fondo = '#F5F3EC';

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background-color:{$fondo}; font-family:Arial, Helvetica, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:{$fondo}; padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" style="max-width:480px; background-color:#FFFFFF; border-radius:16px; overflow:hidden; box-shadow:0 4px 14px rgba(43,43,43,0.08);">

          <tr>
            <td align="center" style="background:linear-gradient(135deg, {$doradoClaro}, {$dorado}); padding:32px 24px 24px;">
              <img src="{$logoUrl}" alt="Serena" width="72" height="72" style="display:block; margin:0 auto 12px; border-radius:50%; background:#FFFFFF;">
              <p style="margin:0; color:{$carbon}; font-size:12px; letter-spacing:2px; text-transform:uppercase; font-weight:bold;">
                {$lema}
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:32px 32px 8px;">
              <h1 style="margin:0; font-family:Georgia, 'Times New Roman', serif; font-size:24px; color:{$carbon};">
                {$tituloPrincipal}
              </h1>
            </td>
          </tr>

          <tr>
            <td style="padding:8px 32px 32px; font-size:15px; line-height:1.6; color:{$carbon};">
              {$cuerpoHtml}
            </td>
          </tr>

          <tr>
            <td style="padding:20px 32px; background-color:{$fondo}; text-align:center;">
              <p style="margin:0; font-size:12px; color:{$grisTexto};">
                {$pie}
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

// Botón dorado reutilizable dentro del cuerpo de un correo.
function botonCorreoHtml(string $texto, string $url): string
{
    return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
  <tr>
    <td style="background:linear-gradient(135deg, #F9D976, #E8B923); border-radius:10px;">
      <a href="{$url}" style="display:inline-block; padding:14px 28px; font-size:15px; font-weight:bold; color:#2B2B2B; text-decoration:none;">
        {$texto}
      </a>
    </td>
  </tr>
</table>
HTML;
}