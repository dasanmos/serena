<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/AlertaTemprana.php';
require_once __DIR__ . '/../../src/modelos/Notificacion.php';
require_once __DIR__ . '/../../src/helpers/notificador.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonExito(EstadoEmocional::listarDeUsuario($pdo, $uid, date('Y-m-d', strtotime('-7 days')), date('Y-m-d')));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        jsonError('Cuerpo JSON inválido.', 400);
    }
    verificarTokenCSRF_json(isset($datos['csrf_token']) ? (string) $datos['csrf_token'] : null);
    // Mismas reglas que estudiante/emociones.php: sin esto, un nivel fuera de rango o una
    // emoción inventada generaban errores 500 o alertas tempranas falsas.
    $emociones = ['feliz', 'tranquilo', 'motivado', 'ansioso', 'triste', 'cansado', 'estresado', 'enojado', 'confundido', 'agradecido'];
    $emocion = (string) ($datos['emocion'] ?? '');
    $nivel = filter_var($datos['nivel'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $momento = (string) ($datos['momento_dia'] ?? 'mañana');
    if (!in_array($emocion, $emociones, true)) {
        jsonError('Emoción no válida.', 422);
    }
    if ($nivel === false) {
        jsonError('El nivel debe estar entre 1 y 5.', 422);
    }
    if (!in_array($momento, ['mañana', 'tarde', 'noche'], true)) {
        jsonError('Momento del día no válido.', 422);
    }
    $nota = trim((string) ($datos['nota'] ?? ''));
    $id = EstadoEmocional::registrar($pdo, $uid, [
        'emocion' => $emocion,
        'nivel' => $nivel,
        'nota' => $nota !== '' ? mb_substr($nota, 0, 1000) : null,
        'momento_dia' => $momento,
    ]);
    verificarAlertasEmocionales($pdo, $uid);
    jsonExito(['id' => $id], 'Registrado');
}
jsonError('Método no permitido', 405);
