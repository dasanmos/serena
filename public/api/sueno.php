<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/Sueno.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limite = min(90, max(1, (int)($_GET['limite'] ?? 14)));
    jsonExito([
        'historial' => Sueno::listarDeUsuario($pdo, $uid, $limite),
        'hoy'       => Sueno::deHoy($pdo, $uid),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    verificarTokenCSRF_json($datos['csrf_token'] ?? null);

    $horas = (float)($datos['horas'] ?? 0);
    $calidad = $datos['calidad'] ?? '';
    if ($horas < 0 || $horas > 24) jsonError('Horas inválidas.');
    if (!in_array($calidad, ['mala', 'regular', 'buena'], true)) jsonError('Selecciona una calidad válida.');

    $id = Sueno::registrar($pdo, $uid, [
        'fecha'           => $datos['fecha'] ?? date('Y-m-d'),
        'horas'           => $horas,
        'calidad'         => $calidad,
        'hora_dormir'     => $datos['hora_dormir'] ?? '',
        'hora_despertar'  => $datos['hora_despertar'] ?? '',
    ]);
    jsonExito(['id' => $id], 'Registro de sueño guardado.');
}

jsonError('Método no permitido', 405);
