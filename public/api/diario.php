<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/Diario.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $busqueda = trim($_GET['busqueda'] ?? '');
    $etiqueta = trim($_GET['etiqueta'] ?? '');
    $entradas = Diario::listarDeUsuario($pdo, $uid, $busqueda, $etiqueta);
    // La API nunca devuelve la ruta de archivo en disco de las imágenes, solo el id —
    // el archivo real se sirve siempre a través de diario_imagen.php, que sí verifica
    // propiedad antes de entregar el binario.
    foreach ($entradas as &$ent) {
        $ent['imagenes'] = array_map(fn($i) => ['id' => $i['id']], Diario::imagenesDeEntrada($pdo, $ent['id']));
    }
    unset($ent);
    jsonExito($entradas);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No hay subida de archivos por esta vía (JSON, no multipart) — las imágenes del
    // diario siguen subiéndose solo desde diario.php. Este endpoint cubre texto/etiquetas.
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    verificarTokenCSRF_json($datos['csrf_token'] ?? null);
    $accion = $datos['accion'] ?? '';

    if ($accion === 'crear') {
        $contenido = trim($datos['contenido'] ?? '');
        if ($contenido === '') jsonError('El contenido no puede estar vacío.');
        $id = Diario::crear($pdo, $uid, [
            'titulo'     => trim($datos['titulo'] ?? ''),
            'contenido'  => $contenido,
            'etiquetas'  => array_filter(array_map('trim', $datos['etiquetas'] ?? [])),
        ]);
        jsonExito(['id' => $id], 'Entrada guardada.');
    }

    if ($accion === 'actualizar') {
        $id = (int)($datos['id'] ?? 0);
        $entrada = Diario::buscarPorId($pdo, $id, $uid);
        if (!$entrada) jsonError('Esa entrada no existe o no te pertenece.', 404);
        $contenido = trim($datos['contenido'] ?? '');
        if ($contenido === '') jsonError('El contenido no puede estar vacío.');
        Diario::actualizar($pdo, $id, ['titulo' => trim($datos['titulo'] ?? ''), 'contenido' => $contenido]);
        jsonExito(null, 'Entrada actualizada.');
    }

    if ($accion === 'eliminar') {
        $id = (int)($datos['id'] ?? 0);
        $entrada = Diario::buscarPorId($pdo, $id, $uid);
        if (!$entrada) jsonError('Esa entrada no existe o no te pertenece.', 404);
        // Las imágenes en disco de esta entrada se quedan huérfanas si se borra solo por
        // esta vía (a diferencia de diario.php, que sí las borra de disco antes) — por eso
        // este endpoint rechaza eliminar entradas que todavía tengan imágenes adjuntas, y
        // pide hacerlo desde diario.php, que sí limpia el archivo físico correctamente.
        if (Diario::contarImagenes($pdo, $id) > 0) {
            jsonError('Esta entrada tiene imágenes adjuntas — elimínala desde diario.php para borrar también los archivos.', 409);
        }
        Diario::eliminar($pdo, $id, $uid);
        jsonExito(null, 'Entrada eliminada.');
    }

    jsonError('Acción no reconocida.');
}

jsonError('Método no permitido', 405);
