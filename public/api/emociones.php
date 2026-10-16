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
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = EstadoEmocional::registrar($pdo, $uid, $datos);
    verificarAlertasEmocionales($pdo, $uid);
    jsonExito(['id' => $id], 'Registrado');
}
jsonError('Método no permitido', 405);
