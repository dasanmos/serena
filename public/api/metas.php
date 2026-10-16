<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/Meta.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Meta::marcarVencidas($pdo, $uid);
    $estado = $_GET['estado'] ?? '';
    $metas = Meta::listarDeUsuario($pdo, $uid, $estado);
    foreach ($metas as &$m) { $m['subtareas'] = Meta::subtareas($pdo, $m['id']); }
    unset($m);
    jsonExito($metas);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    verificarTokenCSRF_json($datos['csrf_token'] ?? null);
    $accion = $datos['accion'] ?? '';

    if ($accion === 'crear') {
        $titulo = trim($datos['titulo'] ?? '');
        if ($titulo === '') jsonError('El título es obligatorio.');
        $id = Meta::crear($pdo, $uid, [
            'titulo'       => $titulo,
            'descripcion'  => trim($datos['descripcion'] ?? ''),
            'tipo'         => in_array($datos['tipo'] ?? '', ['corto_plazo', 'largo_plazo'], true) ? $datos['tipo'] : 'corto_plazo',
            'fecha_limite' => $datos['fecha_limite'] ?? '',
            'habito_id'    => $datos['habito_id'] ?? null,
            'subtareas'    => $datos['subtareas'] ?? [],
        ]);
        jsonExito(['id' => $id], 'Meta creada.');
    }

    if ($accion === 'toggle_subtarea') {
        $sid = (int)($datos['subtarea_id'] ?? 0);
        $midVerificado = Meta::metaIdDeSubtarea($pdo, $sid, $uid);
        if (!$midVerificado || !Meta::toggleSubtarea($pdo, $sid, $uid)) jsonError('Subtarea no encontrada.', 404);
        Meta::recalcularAvance($pdo, $midVerificado);
        jsonExito(null, 'Actualizado.');
    }

    if ($accion === 'completar') {
        $mid = (int)($datos['meta_id'] ?? 0);
        $meta = Meta::buscarPorId($pdo, $mid, $uid);
        if (!$meta) jsonError('Meta no encontrada.', 404);
        Meta::actualizar($pdo, $mid, array_merge($meta, ['porcentaje_avance' => 100, 'estado' => 'completada']));
        jsonExito(null, '¡Meta completada!');
    }

    if ($accion === 'eliminar') {
        $mid = (int)($datos['meta_id'] ?? 0);
        $meta = Meta::buscarPorId($pdo, $mid, $uid);
        if (!$meta) jsonError('Meta no encontrada.', 404);
        Meta::eliminar($pdo, $mid);
        jsonExito(null, 'Meta eliminada.');
    }

    jsonError('Acción no reconocida.');
}

jsonError('Método no permitido', 405);
