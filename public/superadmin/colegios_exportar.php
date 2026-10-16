<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Colegio.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-colegios-' . date('Y-m-d') . '.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['nombre', 'dane', 'municipio', 'estado', 'estudiantes_activos', 'personal_activo', 'creado_en']);
foreach (Colegio::listarTodos($pdo) as $c) {
    // Evita inyección de fórmulas al abrir en Excel.
    $nombre = preg_match('/^[=+\-@]/', (string) $c['nombre']) ? "'" . $c['nombre'] : $c['nombre'];
    fputcsv($salida, [$nombre, $c['dane'], $c['municipio'], (int) $c['activo'] === 1 ? 'activo' : 'suspendido',
        $c['estudiantes_activos'], $c['personal_activo'], $c['creado_en']]);
}
fclose($salida);
