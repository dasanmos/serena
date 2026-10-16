<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/Sueno.php';
require_once __DIR__ . '/../../src/modelos/Habito.php';
// Igual que la página estudiante/estadisticas.php de la que sale esta lógica: estas son
// SIEMPRE las estadísticas propias de quien pide el reporte, nunca las de otro usuario —
// para ver datos de un estudiante distinto existen las vistas de psicólogo/orientador,
// que sí llevan su propio control de acceso por grupo.
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Método no permitido', 405);

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$anioActual = (int)date('Y'); $mesActual = (int)date('m');
$fechaMesAnterior = strtotime(date('Y-m-01') . ' -1 month');
$anioAnterior = (int)date('Y', $fechaMesAnterior); $mesAnterior = (int)date('m', $fechaMesAnterior);

jsonExito([
    'emociones'              => EstadoEmocional::resumenPorPeriodo($pdo, $uid, $desde, $hasta),
    'sueno'                  => Sueno::resumenPorPeriodo($pdo, $uid, $desde, $hasta),
    'distribucion_emociones' => EstadoEmocional::distribucionPorEmocion($pdo, $uid, $desde, $hasta),
    'comparativa'            => [
        'emocion' => ['actual' => EstadoEmocional::promedioPorMes($pdo, $uid, $anioActual, $mesActual),
                       'anterior' => EstadoEmocional::promedioPorMes($pdo, $uid, $anioAnterior, $mesAnterior)],
        'sueno'   => ['actual' => Sueno::promedioPorMes($pdo, $uid, $anioActual, $mesActual),
                       'anterior' => Sueno::promedioPorMes($pdo, $uid, $anioAnterior, $mesAnterior)],
        'habitos' => ['actual' => Habito::completadosPorMes($pdo, $uid, $anioActual, $mesActual),
                       'anterior' => Habito::completadosPorMes($pdo, $uid, $anioAnterior, $mesAnterior)],
    ],
]);
