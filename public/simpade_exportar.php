<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Simpade.php';

// Descargas CSV de SIMPADE: casos de riesgo y retiros con su causa.
requiereRol('administrador', 'rector', 'coordinador', 'orientador', 'psicologo', 'secretaria_academica');
$colegioId = colegioParaCrear();
$anio = Academico::anio($pdo, (int) ($_GET['anio'] ?? 0), $colegioId);
if ($anio === null) {
    http_response_code(404);
    exit('Año lectivo no encontrado.');
}
$tipo = (string) ($_GET['tipo'] ?? 'casos');
if (!in_array($tipo, ['casos', 'retiros'], true)) {
    http_response_code(400);
    exit('Tipo de descarga no válido.');
}
$sep = Simat::ajustes($pdo, $colegioId)['separador'];
header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: no-store');
header('Content-Disposition: attachment; filename="serena-simpade-' . $tipo . '-' . (int) $anio['anio'] . '-' . date('Ymd') . '.csv"');
$salida = fopen('php://output', 'w');
if ($tipo === 'casos') {
    $datos = Simpade::casos($pdo, $colegioId, (int) $anio['id']);
    Simpade::escribirCsvCasos($salida, $datos, $sep);
} else {
    $datos = Simpade::retiros($pdo, $colegioId, (int) $anio['id']);
    Simpade::escribirCsvRetiros($salida, $datos, $sep);
}
auditarAccion($pdo, 'simpade_exportar_' . $tipo, 'colegios', $colegioId, [], ['filas' => count($datos)]);
fclose($salida);
