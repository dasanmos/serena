<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
requiereRol('superadmin', 'secretaria');

$ejemplos = [
    'secretarias' => ['filas' => [
        ['departamento', 'tipo', 'nombre', 'municipio'],
        ['Antioquia', 'departamental', 'Secretaría de Educación Departamental de Antioquia', ''],
        ['Antioquia', 'municipal', 'Secretaría de Educación de Itagüí', 'Itagüí'],
    ]],
    'municipios' => ['filas' => [
        ['departamento', 'municipio', 'codigo_dane'],
        ['Antioquia', 'Medellín', '05001'],
        ['Antioquia', 'Itagüí', '05360'],
        ['Antioquia', 'Jardín', '05364'],
    ]],
    'nucleos' => ['filas' => [
        ['secretaria', 'departamento', 'municipio', 'codigo', 'nombre'],
        ['Medellín', 'Antioquia', 'Medellín', 'N-01', 'Núcleo de ejemplo Norte'],
        ['Antioquia', 'Antioquia', '', 'N-02', 'Núcleo rural de ejemplo'],
    ]],
    'zonas' => ['filas' => [
        ['departamento', 'municipio', 'tipo', 'nombre'],
        ['Antioquia', 'Medellín', 'comuna', 'Comuna 10 - La Candelaria'],
        ['Antioquia', 'Medellín', 'corregimiento', 'San Cristóbal'],
        ['Antioquia', 'Itagüí', 'comuna', 'Comuna 1'],
    ]],
];
$tipo = (string) ($_GET['tipo'] ?? '');
if (!isset($ejemplos[$tipo])) {
    http_response_code(404);
    exit('Tipo de ejemplo no válido.');
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-ejemplo-' . $tipo . '.csv"');
$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
foreach ($ejemplos[$tipo]['filas'] as $fila) {
    fputcsv($salida, $fila, ',', '"', '');
}
fclose($salida);
