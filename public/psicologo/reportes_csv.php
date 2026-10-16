<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
requiereRol('psicologo', 'coordinador');

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$gradoId = (int) ($_GET['grado_id'] ?? 0);
$grupoId = (int) ($_GET['grupo_id'] ?? 0);

$condicionGrupo = '';
$paramsGrupo = [];
// Multi-colegio: todos los reportes quedan limitados al colegio del usuario (u = usuarios del estudiante).
$condicionColegio = sqlColegio('u.colegio_id');
if ($grupoId) { $condicionGrupo = ' AND u.grupo_id = :grupo_id'; $paramsGrupo['grupo_id'] = $grupoId; }
elseif ($gradoId) { $condicionGrupo = ' AND g.grado_id = :grado_id'; $paramsGrupo['grado_id'] = $gradoId; }
$necesitaJoinGrupo = $condicionGrupo !== '' ? 'INNER JOIN usuarios u ON u.id = e.usuario_id INNER JOIN grupos g ON g.id = u.grupo_id' : 'INNER JOIN usuarios u ON u.id = e.usuario_id';

$emocionesFrec = $pdo->prepare(
    "SELECT e.emocion, COUNT(*) AS veces FROM estado_emocional e
     {$necesitaJoinGrupo} WHERE DATE(e.fecha_hora) BETWEEN :d AND :h {$condicionGrupo}{$condicionColegio}
     GROUP BY e.emocion ORDER BY veces DESC"
);
$emocionesFrec->execute(['d' => $desde, 'h' => $hasta] + $paramsGrupo);
$frecc = $emocionesFrec->fetchAll();

// Un CSV real (no .xlsx) — Excel, Google Sheets y LibreOffice lo abren nativamente
// sin necesitar ninguna librería adicional en el servidor.
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-reporte-' . $desde . '-a-' . $hasta . '.csv"');

$salida = fopen('php://output', 'w');
fputs($salida, "\xEF\xBB\xBF"); // BOM UTF-8, para que Excel no muestre acentos rotos
fputcsv($salida, ['Serena — Reporte institucional']);
fputcsv($salida, ['Período', $desde . ' a ' . $hasta]);
fputcsv($salida, ['Generado', date('d/m/Y H:i')]);
fputcsv($salida, []);
fputcsv($salida, ['Emoción', 'Veces registrada']);
foreach ($frecc as $f) {
    fputcsv($salida, [ucfirst($f['emocion']), $f['veces']]);
}
fclose($salida);
