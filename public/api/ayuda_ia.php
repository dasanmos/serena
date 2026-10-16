<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/helpers/ayuda_ia.php';

// Asistente de ayuda («Pregúntale a Serena»). Público (la ayuda general lo es), pero:
//  - exige token CSRF de la sesión,
//  - limita a 8 preguntas por sesión cada 10 minutos,
//  - tiene un cupo diario global de llamadas a la IA (OPENROUTER_AYUDA_MAX_DIA, 200 por defecto);
//    al agotarse sigue respondiendo con el texto del manual, sin IA.
// Solo recibe la pregunta: nunca datos de la cuenta ni de bienestar emocional.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$entrada = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    jsonError('Solicitud inválida.', 400);
}
verificarTokenCSRF_json((string) ($entrada['csrf_token'] ?? ''));

$pregunta = trim((string) ($entrada['pregunta'] ?? ''));
if (mb_strlen($pregunta, 'UTF-8') < 3) {
    jsonError('Escribe tu pregunta (al menos unas palabras).', 422);
}

// Con sesión: la guía del rol (el administrador/superadmin pueden consultar la de otro rol, igual que en ayuda.php).
$rol = estaAutenticado() ? (string) ($_SESSION['rol'] ?? '') : '';
$pedido = (string) ($entrada['rol'] ?? '');
if ($rol !== '' && ayudaPuedeVerOtrosRoles($rol) && $pedido !== '' && ayudaPuedeVerRol($rol, $pedido)) {
    $rol = $pedido;
}
if ($rol !== '' && !ayudaRolValido($rol)) {
    $rol = '';
}

if (ayudaIALimiteSesion()) {
    jsonError('Hiciste muchas preguntas seguidas. Espera unos minutos o usa el buscador de la ayuda.', 429);
}

try {
    jsonExito(ayudaIAResponder($pdo, $pregunta, $rol));
} catch (Throwable $e) {
    error_log('ayuda_ia: ' . $e->getMessage());
    jsonError('No pude responder ahora. Prueba con el buscador de la ayuda.', 500);
}
