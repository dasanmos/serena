<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
requiereRol('administrador');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-ejemplo-importar-personal.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['nombre', 'correo', 'contraseña', 'rol', 'grupo']);
fputcsv($salida, ['Laura Gómez', 'laura.gomez@serena.edu', '', 'docente', 'Sexto A']);
fputcsv($salida, ['Carlos Pérez', 'carlos.perez@serena.edu', '', 'orientador', '']);
fputcsv($salida, ['Marcela Ruiz', 'marcela.ruiz@serena.edu', '', 'psicologo', '']);
fclose($salida);
