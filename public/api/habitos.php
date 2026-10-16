<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/Habito.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = $datos['accion'] ?? '';
    if ($accion === 'marcar') {
        $habitoId = (int)($datos['habito_id'] ?? 0);
        // Aunque este endpoint no está conectado a ningún frontend todavía, sigue siendo
        // código alcanzable por URL — se verifica propiedad igual que en la página normal.
        if (!Habito::buscarPorId($pdo, $habitoId, $uid)) {
            jsonError('No autorizado', 403);
        }
        Habito::marcarCompletado($pdo, $habitoId, $datos['fecha'] ?? date('Y-m-d'), (bool)($datos['completado'] ?? false));
        jsonExito(null, 'Actualizado');
    }
}
jsonExito(Habito::listarDeUsuario($pdo, $uid));
