<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-ejemplo-importar-colegios.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['nombre', 'dane', 'municipio', 'admin_nombre', 'admin_correo', 'nucleo', 'zona', 'direccion']);
fputcsv($salida, ['I.E. Ejemplo Uno', '105001000001', 'Medellín', 'Ana Rector', 'rector.uno@ejemplo.edu', 'Núcleo de ejemplo Norte', 'Comuna 10 - La Candelaria', 'Carrera 50 # 24-15']);
fputcsv($salida, ['I.E. Ejemplo Dos', '105001000002', 'Bello', '', '', '', '', '']);
fclose($salida);
