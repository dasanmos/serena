<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
requiereRol('psicologo', 'orientador');

$profesionalId = (int) $_SESSION['usuario_id'];

$c = $pdo->prepare(
    "SELECT c.fecha, c.hora_inicio, c.hora_fin, c.motivo, u.nombre AS estudiante_nombre
     FROM citas c
     INNER JOIN usuarios u ON u.id = c.estudiante_id
     WHERE c.profesional_id = :pid AND c.estado = 'confirmada' AND c.fecha >= CURDATE()
     ORDER BY c.fecha, c.hora_inicio"
);
$c->execute(['pid' => $profesionalId]);
$citas = $c->fetchAll();

// Formato iCalendar estándar (RFC 5545) — texto plano, sin dependencias externas.
// Compatible con Google Calendar, Outlook, Apple Calendar: cualquiera que soporte
// "Importar calendario" desde archivo .ics.
function formatoFechaICS(string $fecha, string $hora): string
{
    return str_replace('-', '', $fecha) . 'T' . str_replace(':', '', $hora) . '00';
}

function escaparTextoICS(string $texto): string
{
    return str_replace([',', ';', "\n"], ['\,', '\;', '\n'], $texto);
}

$lineas = [];
$lineas[] = 'BEGIN:VCALENDAR';
$lineas[] = 'VERSION:2.0';
$lineas[] = 'PRODID:-//Serena//Citas//ES';
$lineas[] = 'CALSCALE:GREGORIAN';

foreach ($citas as $cita) {
    $lineas[] = 'BEGIN:VEVENT';
    $lineas[] = 'UID:' . md5($cita['fecha'] . $cita['hora_inicio'] . $profesionalId) . '@serena';
    $lineas[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
    $lineas[] = 'DTSTART:' . formatoFechaICS($cita['fecha'], $cita['hora_inicio']);
    $lineas[] = 'DTEND:' . formatoFechaICS($cita['fecha'], $cita['hora_fin']);
    $lineas[] = 'SUMMARY:' . escaparTextoICS('Cita — ' . $cita['estudiante_nombre']);
    if (!empty($cita['motivo'])) {
        $lineas[] = 'DESCRIPTION:' . escaparTextoICS($cita['motivo']);
    }
    $lineas[] = 'END:VEVENT';
}

$lineas[] = 'END:VCALENDAR';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-citas.ics"');
echo implode("\r\n", $lineas);
