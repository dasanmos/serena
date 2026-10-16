<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Auditoria.php';
requiereRol('administrador');

$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$usuarioBusqueda = trim($_GET['usuario'] ?? '');
$registros = ($desde !== '' || $hasta !== '' || $usuarioBusqueda !== '')
    ? Auditoria::listarFiltrado($pdo, $desde ?: null, $hasta ?: null, $usuarioBusqueda ?: null, 5000)
    : Auditoria::listar($pdo, 500);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-auditoria-' . date('Y-m-d_His') . '.csv"');

$salida = fopen('php://output', 'w');
fputs($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['Fecha', 'Usuario', 'Acción', 'Tabla', 'Registro']);
foreach ($registros as $r) {
    fputcsv($salida, [
        date('Y-m-d H:i:s', strtotime($r['fecha'])),
        $r['usuario_nombre'] ?? 'Sistema',
        $r['accion'],
        $r['tabla_afectada'],
        $r['registro_id'],
    ]);
}
fclose($salida);
