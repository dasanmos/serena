<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/respaldo.php';
// Multi-colegio: las copias contienen los datos de TODOS los colegios -> solo superadmin.
requiereRol('superadmin');

// Sin ?archivo= se descarga la más reciente; con él, una de las copias guardadas
// (solo si existe de verdad en la carpeta — ver respaldoPorNombre()).
$respaldo = isset($_GET['archivo']) ? respaldoPorNombre((string) $_GET['archivo']) : respaldoActual();
if (!$respaldo) {
    http_response_code(404);
    exit('Esa copia de seguridad no existe.');
}

auditarAccion($pdo, 'descargar_respaldo_guardado', 'sistema', 0);

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $respaldo['nombre'] . '"');
header('Content-Length: ' . $respaldo['tamano']);
readfile($respaldo['ruta']);
