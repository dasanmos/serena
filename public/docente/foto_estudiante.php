<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/helpers/fotosEstudiantes.php';

// Sirve la foto de un estudiante (o una silueta si no tiene). Mismos roles que Seguimiento.
// El docente solo ve fotos de estudiantes de los grupos donde tiene asignaciones.
requiereRol('docente', 'administrador', 'rector', 'secretaria_academica');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$id = (int) ($_GET['id'] ?? 0);

$q = $pdo->prepare('SELECT foto, colegio_id FROM usuarios WHERE id = ?');
$q->execute([$id]);
$est = $q->fetch();
if (!$est || (int) $est['colegio_id'] !== $colegioId) {
    http_response_code(404);
    exit('No encontrada.');
}
if ($rol === 'docente') {
    $q = $pdo->prepare("SELECT 1 FROM asignaciones_academicas a
                         WHERE a.docente_id = ? AND a.colegio_id = ?
                           AND (a.grupo_id = (SELECT grupo_id FROM usuarios WHERE id = ?)
                                OR a.grupo_id IN (SELECT grupo_id FROM matriculas WHERE estudiante_id = ? AND estado = 'activa'))
                         LIMIT 1");
    $q->execute([$usuarioId, $colegioId, $id, $id]);
    if (!$q->fetchColumn()) {
        http_response_code(403);
        exit('Sin permiso.');
    }
}

header('X-Content-Type-Options: nosniff');
$ruta = rutaFotoEstudiante(is_string($est['foto']) ? $est['foto'] : null);
if ($ruta !== null) {
    $mimes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    header('Content-Type: ' . ($mimes[strtolower(pathinfo($ruta, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: private, max-age=86400'); // la URL lleva ?v=<archivo>: al cambiar la foto cambia la URL
    readfile($ruta);
    exit;
}

// Sin foto: silueta azul (igual que el marcador de la referencia).
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: private, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="120" height="120" role="img" aria-label="Sin foto">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4f7bd9"/><stop offset="1" stop-color="#16327a"/></linearGradient>
  </defs>
  <circle cx="60" cy="40" r="22" fill="url(#g)"/>
  <path d="M14 112c0-26 20-42 46-42s46 16 46 42z" fill="url(#g)"/>
</svg>
