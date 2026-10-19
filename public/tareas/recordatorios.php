<?php
declare(strict_types=1);

// Endpoint de cron para public/tareas/recordatorios.php.
//
// Un servicio externo (ej. cron-job.org, gratuito) debe llamar a esta URL cada
// 15 minutos:
//   https://serena.infinityfreeapp.com/public/tareas/recordatorios.php?secreto=TU_SECRETO
//
// El secreto se compara contra RECORDATORIOS_CRON_SECRETO en src/config/.env
// (ver .env.example). Sin secreto configurado en el servidor, o si no coincide,
// el endpoint responde 403 y no hace nada — así cualquiera no puede llamarlo para
// forzar el envío de mensajes a los estudiantes.
//
// No requiere sesión (lo llama un servicio externo, no un navegador logueado):
// por eso NO se incluye auth.php aquí, solo conexion.php + config.php.
//
// Canal de envío por usuario:
//   - Correo: siempre, vía la única cuenta Brevo de la plataforma (src/helpers/correo.php).
//   - WhatsApp: además, SOLO si el usuario ya guardó su número + apikey personal
//     de CallMeBot en Preferencias/Seguridad (mismas credenciales que usa el 2FA
//     por WhatsApp — ver VerificacionDosPasosWhatsapp). Si no las tiene, se
//     omite sin error: el correo ya se envió igual.
//
// Idempotencia: cada recordatorio se marca con ultimo_envio = hoy apenas se
// intenta enviar (columna agregada en sql/migraciones/019_recordatorios_envio.sql),
// así que si cron-job.org llama de nuevo dentro de la misma hora no se duplica.

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/modelos/Recordatorio.php';
require_once __DIR__ . '/../../src/modelos/Cita.php';
require_once __DIR__ . '/../../src/helpers/resumen_mensual.php';
require_once __DIR__ . '/../../src/helpers/correo.php';
require_once __DIR__ . '/../../src/helpers/whatsapp.php';
require_once __DIR__ . '/../../src/helpers/totp.php'; // totpDescifrarSecreto() para la apikey de WhatsApp

header('Content-Type: application/json; charset=utf-8');

$secretoEsperado = entornoServer('RECORDATORIOS_CRON_SECRETO');
$secretoRecibido = $_GET['secreto'] ?? '';

if ($secretoEsperado === '' || !hash_equals($secretoEsperado, (string) $secretoRecibido)) {
    http_response_code(403);
    echo json_encode(['error' => 'Secreto inválido o no configurado.']);
    exit;
}

$etiquetasTipo = [
    'habito'        => ['emoji' => '🔥', 'texto' => 'Es hora de registrar tu hábito de hoy.'],
    'sueno'         => ['emoji' => '🌙', 'texto' => 'Es hora de registrar tu sueño de anoche.'],
    'diario'        => ['emoji' => '📓', 'texto' => 'Es hora de escribir en tu diario.'],
    'meta'          => ['emoji' => '🎯', 'texto' => 'Revisa el avance de tu meta.'],
    'personalizado' => ['emoji' => '✏️', 'texto' => 'Tienes un recordatorio pendiente.'],
];

$ahora = date('H:i:s');
$hoy = date('Y-m-d');

$pendientes = Recordatorio::pendientesDeEnvio($pdo, $ahora, $hoy);

$resultado = ['procesados' => 0, 'correo_ok' => 0, 'whatsapp_ok' => 0, 'errores' => []];

foreach ($pendientes as $r) {
    $resultado['procesados']++;
    $tipo = $etiquetasTipo[$r['tipo']] ?? $etiquetasTipo['personalizado'];
    $nombre = (string) $r['nombre'];

    // Correo (canal principal, llega a todos)
    $asunto = $tipo['emoji'] . ' Recordatorio de Serena';
    $textoPlano = "Hola {$nombre},\n\n{$tipo['texto']}\n\nEntra a Serena para registrarlo.";
    $cuerpoHtml = "<p>Hola {$nombre},</p><p>{$tipo['texto']}</p>";
    $htmlCompleto = plantillaCorreoHtml('Tienes un recordatorio', $cuerpoHtml);

    if (!empty($r['correo']) && smtpConfigurado()) {
        if (enviarCorreo((string) $r['correo'], $asunto, $textoPlano, $htmlCompleto)) {
            $resultado['correo_ok']++;
        } else {
            $resultado['errores'][] = "correo #{$r['id']}";
        }
    }

    // WhatsApp (bonus, solo si el usuario ya activó sus credenciales de CallMeBot)
    if (!empty($r['whatsapp_2fa_numero']) && !empty($r['whatsapp_2fa_apikey'])) {
        $apikey = totpDescifrarSecreto((string) $r['whatsapp_2fa_apikey']);
        if ($apikey !== null) {
            $textoWa = "*Serena* {$tipo['emoji']}\n{$tipo['texto']}";
            if (enviarWhatsappCallMeBot((string) $r['whatsapp_2fa_numero'], $apikey, $textoWa)) {
                $resultado['whatsapp_ok']++;
            } else {
                $resultado['errores'][] = "whatsapp #{$r['id']}";
            }
        }
    }

    // Se marca como enviado hoy aunque un canal haya fallado: evita reintentos
    // infinitos por un recordatorio con correo/apikey rotos, igual que el resto
    // de la plataforma (WhatsApp/correo son "mejor esfuerzo", nunca bloquean nada).
    Recordatorio::marcarEnviadoHoy($pdo, (int) $r['id'], $hoy);
}

// --- Recordatorio de citas: un día antes, por correo, a las dos partes ---
// (mismo endpoint/cron que arriba — no hace falta configurar una segunda tarea
// programada en cron-job.org). Solo correo, sin WhatsApp: no tiene el mismo peso
// de urgencia que un hábito o una emoción, y así se mantiene simple.
$resultado['citas_procesadas'] = 0;
$resultado['citas_correo_ok'] = 0;

foreach (Cita::pendientesDeRecordatorio($pdo) as $c) {
    $resultado['citas_procesadas']++;
    $fechaTexto = date('d/m/Y', strtotime($c['fecha']));
    $horaTexto = substr($c['hora_inicio'], 0, 5);
    $rolTexto = $c['profesional_rol'] === 'psicologo' ? 'psicólogo' : 'orientador';

    if (smtpConfigurado()) {
        if (!empty($c['estudiante_correo'])) {
            $ok = enviarCorreo(
                (string) $c['estudiante_correo'],
                '📅 Recordatorio: cita mañana en Serena',
                "Hola {$c['estudiante_nombre']},\n\nMañana {$fechaTexto} a las {$horaTexto} tienes una cita con {$c['profesional_nombre']} ({$rolTexto}).",
                plantillaCorreoHtml('Tienes una cita mañana', "<p>Hola {$c['estudiante_nombre']},</p><p>Mañana <strong>{$fechaTexto}</strong> a las <strong>{$horaTexto}</strong> tienes una cita con {$c['profesional_nombre']} ({$rolTexto}).</p>")
            );
            if ($ok) $resultado['citas_correo_ok']++;
        }
        if (!empty($c['profesional_correo'])) {
            $ok = enviarCorreo(
                (string) $c['profesional_correo'],
                '📅 Recordatorio: cita mañana en Serena',
                "Hola {$c['profesional_nombre']},\n\nMañana {$fechaTexto} a las {$horaTexto} tienes una cita con {$c['estudiante_nombre']}.",
                plantillaCorreoHtml('Tienes una cita mañana', "<p>Hola {$c['profesional_nombre']},</p><p>Mañana <strong>{$fechaTexto}</strong> a las <strong>{$horaTexto}</strong> tienes una cita con {$c['estudiante_nombre']}.</p>")
            );
            if ($ok) $resultado['citas_correo_ok']++;
        }
    }

    // Se marca enviado aunque el correo falle — mismo criterio de "mejor esfuerzo"
    // que el resto del cron, para no reintentar sin fin una dirección rota.
    Cita::marcarRecordatorioEnviado($pdo, (int) $c['id']);
}

// --- Resumen mensual institucional: solo hace algo el día 1 de cada mes, y como
// mucho una vez (ver resumen_mensual.php) — reutiliza este mismo cron de siempre.
enviarResumenMensualSiToca($pdo);

echo json_encode($resultado);
