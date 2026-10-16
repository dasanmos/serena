<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
requiereRol('administrador');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-ejemplo-importar-estudiantes.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['nombre', 'correo', 'contraseña', 'grupo', 'fecha_nacimiento']);
fputcsv($salida, ['Juan Torres', 'juan.torres@serena.edu', '', 'Sexto A', '2013-04-12']);
fputcsv($salida, ['María Londoño', 'maria.londono@serena.edu', '', 'Sexto B', '2013-07-30']);
fputcsv($salida, ['Andrés Zapata', 'andres.zapata@serena.edu', '', 'Séptimo A', '']);
fclose($salida);
