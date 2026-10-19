<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/Habito.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        jsonError('Cuerpo JSON inválido.', 400);
    }
    verificarTokenCSRF_json(isset($datos['csrf_token']) ? (string) $datos['csrf_token'] : null);
    $accion = $datos['accion'] ?? '';
    if ($accion === 'marcar') {
        $habitoId = (int)($datos['habito_id'] ?? 0);
        // Aunque este endpoint no está conectado a ningún frontend todavía, sigue siendo
        // código alcanzable por URL — se verifica propiedad igual que en la página normal.
        if (!Habito::buscarPorId($pdo, $habitoId, $uid)) {
            jsonError('No autorizado', 403);
        }
        $fecha = (string) ($datos['fecha'] ?? date('Y-m-d'));
        $f = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$f || $f->format('Y-m-d') !== $fecha || $fecha > date('Y-m-d')) {
            jsonError('Fecha no válida.', 422);
        }
        Habito::marcarCompletado($pdo, $habitoId, $fecha, (bool)($datos['completado'] ?? false));
        jsonExito(null, 'Actualizado');
    }
    jsonError('Acción no válida.', 400);
}
jsonExito(Habito::listarDeUsuario($pdo, $uid));
