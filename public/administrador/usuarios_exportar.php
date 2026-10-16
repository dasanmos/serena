<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
requiereRol('administrador');

$filtroRol = $_GET['rol'] ?? '';
$busqueda = trim($_GET['q'] ?? '');
$usuarios = Usuario::buscarConFiltro($pdo, $filtroRol, $busqueda);

$gruposPorId = [];
foreach ($pdo->query(
    "SELECT g.id, g.nombre, gr.nombre AS grado FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE 1=1" . sqlColegio('gr.colegio_id')
)->fetchAll() as $g) {
    $gruposPorId[(int) $g['id']] = $g['grado'] . ' ' . $g['nombre'];
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-usuarios-' . date('Y-m-d') . '.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel abra los acentos bien
fputcsv($salida, ['nombre', 'correo', 'rol', 'grupo', 'estado']);
foreach ($usuarios as $u) {
    fputcsv($salida, [
        $u['nombre'],
        $u['correo'],
        $u['rol'],
        $u['grupo_id'] ? ($gruposPorId[(int) $u['grupo_id']] ?? '') : '',
        $u['activo'] ? 'activo' : 'inactivo',
    ]);
}
fclose($salida);
