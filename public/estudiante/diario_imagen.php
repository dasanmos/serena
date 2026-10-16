<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Diario.php';
requiereRol('estudiante');

// Las imágenes del diario respetan el mismo PIN que las entradas: si el diario está
// bloqueado, la imagen tampoco se sirve, aunque alguien tenga el enlace guardado.
if (!isset($_SESSION['diario_desbloqueado'])) {
    http_response_code(403);
    exit('Diario bloqueado.');
}

$uid = (int) $_SESSION['usuario_id'];
$imagenId = (int) ($_GET['id'] ?? 0);

$imagen = Diario::buscarImagenDeUsuario($pdo, $imagenId, $uid);
if (!$imagen) {
    http_response_code(404);
    exit('Imagen no encontrada.');
}

$rutaArchivo = __DIR__ . '/../../src/uploads/diario/' . $imagen['ruta_imagen'];
if (!is_file($rutaArchivo)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));
$tiposMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];

header('Content-Type: ' . ($tiposMime[$extension] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($rutaArchivo));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($rutaArchivo);
