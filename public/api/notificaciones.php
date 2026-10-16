<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/Notificacion.php';
// Notificacion::crear() solo se invoca hoy para el rol estudiante (alertas emocionales,
// insignias) — ver notificador.php y gamificacion.php. El resto de roles no tiene
// notificaciones propias en el esquema actual, así que la API refleja esa misma regla.
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $soloNoLeidas = isset($_GET['solo_no_leidas']);
    jsonExito([
        'notificaciones' => Notificacion::listarDeUsuario($pdo, $uid, $soloNoLeidas),
        'no_leidas'      => Notificacion::contarNoLeidas($pdo, $uid),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    verificarTokenCSRF_json($datos['csrf_token'] ?? null);
    $accion = $datos['accion'] ?? '';

    if ($accion === 'marcar') {
        $id = (int)($datos['id'] ?? 0);
        if (!$id) jsonError('Id inválido.');
        Notificacion::marcarLeida($pdo, $id, $uid);
        jsonExito(['no_leidas' => Notificacion::contarNoLeidas($pdo, $uid)], 'Marcada como leída.');
    }

    if ($accion === 'marcar_todas') {
        Notificacion::marcarTodasLeidas($pdo, $uid);
        jsonExito(['no_leidas' => 0], 'Todas marcadas como leídas.');
    }

    jsonError('Acción no reconocida.');
}

jsonError('Método no permitido', 405);
