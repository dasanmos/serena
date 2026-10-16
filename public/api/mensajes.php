<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/Mensaje.php';
requiereRol('estudiante', 'psicologo', 'orientador', 'docente', 'coordinador', 'rector', 'administrador');
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $convId = (int)($_GET['conversacion_id'] ?? 0);

    if ($convId) {
        if (!Mensaje::perteneceAUsuario($pdo, $convId, $uid)) jsonError('No tienes acceso a esta conversación.', 403);
        Mensaje::marcarLeidos($pdo, $convId, $uid);
        jsonExito(Mensaje::listarMensajes($pdo, $convId));
    }

    jsonExito(Mensaje::conversacionesDeUsuario($pdo, $uid));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    verificarTokenCSRF_json($datos['csrf_token'] ?? null);
    $accion = $datos['accion'] ?? '';

    if ($accion === 'iniciar') {
        $otroId = (int)($datos['otro_usuario_id'] ?? 0);
        $convId = Mensaje::iniciarConversacion($pdo, $uid, $otroId);
        if (!$convId) jsonError('No puedes iniciar una conversación con ese usuario.', 403);
        jsonExito(['conversacion_id' => $convId], 'Conversación lista.');
    }

    if ($accion === 'enviar') {
        $convId = (int)($datos['conversacion_id'] ?? 0);
        $mensaje = trim($datos['mensaje'] ?? '');
        if (!Mensaje::perteneceAUsuario($pdo, $convId, $uid)) jsonError('No tienes acceso a esta conversación.', 403);
        if ($mensaje === '') jsonError('El mensaje no puede estar vacío.');
        $id = Mensaje::enviar($pdo, $convId, $uid, $mensaje);
        jsonExito(['id' => $id], 'Mensaje enviado.');
    }

    jsonError('Acción no reconocida.');
}

jsonError('Método no permitido', 405);
