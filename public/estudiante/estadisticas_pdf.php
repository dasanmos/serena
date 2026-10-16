<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/DocumentoPdf.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/Sueno.php';
require_once __DIR__ . '/../../src/modelos/Habito.php';
requiereRol('estudiante');

$uid = (int) $_SESSION['usuario_id'];
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$emociones = EstadoEmocional::resumenPorPeriodo($pdo, $uid, $desde, $hasta);
$distribucionEmociones = EstadoEmocional::distribucionPorEmocion($pdo, $uid, $desde, $hasta);
$sueno = Sueno::resumenPorPeriodo($pdo, $uid, $desde, $hasta);
$habitos = Habito::listarDeUsuario($pdo, $uid);
foreach ($habitos as &$h) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM seguimiento_habitos WHERE habito_id=:hid AND fecha BETWEEN :d AND :h AND completado=1");
    $c->execute(['hid' => $h['id'], 'd' => $desde, 'h' => $hasta]);
    $h['completados'] = (int) $c->fetchColumn();
}
unset($h);

// Notas de texto que el estudiante escribió junto a sus registros emocionales en el período.
$notasConsulta = $pdo->prepare(
    "SELECT fecha_hora, emocion, nivel, nota FROM estado_emocional
     WHERE usuario_id=:uid AND nota IS NOT NULL AND nota <> ''
       AND DATE(fecha_hora) BETWEEN :d AND :h
     ORDER BY fecha_hora DESC"
);
$notasConsulta->execute(['uid' => $uid, 'd' => $desde, 'h' => $hasta]);
$notas = $notasConsulta->fetchAll();

$promedioEmocion = null;
if (!empty($emociones)) {
    $suma = array_sum(array_map(fn($e) => (float) $e['promedio'], $emociones));
    $promedioEmocion = round($suma / count($emociones), 1);
}
$promedioSueno = null;
if (!empty($sueno)) {
    $suma = array_sum(array_map(fn($s) => (float) $s['horas'], $sueno));
    $promedioSueno = round($suma / count($sueno), 1);
}

$pdf = DocumentoPdf::crear($pdo, 'Reporte de bienestar', 'Reporte de bienestar — ' . $_SESSION['nombre'], DocumentoPdf::pideQr());
$pdf->titulo('Reporte de bienestar');
$pdf->textoSuave($_SESSION['nombre'] . '  ·  Período: ' . $desde . ' a ' . $hasta . '  ·  Generado el ' . date('d/m/Y H:i'));
$pdf->espaciador(6);
$pdf->lineaSeparadora();

$pdf->subtitulo('Resumen del período');
$pdf->indicadores([
    ['Ánimo promedio', $promedioEmocion !== null ? $promedioEmocion . ' / 5' : 'sin datos'],
    ['Sueño promedio', $promedioSueno !== null ? $promedioSueno . ' h' : 'sin datos'],
    ['Días con registro emocional', (string) count($emociones)],
    ['Noches con sueño registrado', (string) count($sueno)],
]);
if (!empty($emociones)) {
    $pdf->graficoLinea('Nivel emocional por día (1 a 5)', array_map(fn($e) => date('d/m', strtotime((string) $e['dia'])), $emociones), array_map(fn($e) => round((float) $e['promedio'], 1), $emociones), 1.0, 5.0);
}
if (!empty($sueno)) {
    $pdf->graficoLinea('Horas de sueño por noche', array_map(fn($x) => date('d/m', strtotime((string) $x['fecha'])), $sueno), array_map(fn($x) => round((float) $x['horas'], 1), $sueno), 0.0, 12.0);
}
$pdf->espaciador(6);

$pdf->subtitulo('Distribución de emociones');
if (empty($distribucionEmociones)) {
    $pdf->texto('Sin registros emocionales en este período.');
} else {
    $pdf->graficoBarras('Emociones registradas en el período', array_map(fn($d) => ucfirst((string) $d['emocion']), $distribucionEmociones), array_map(fn($d) => (int) $d['total'], $distribucionEmociones));
    $pdf->tabla(['Emoción', 'Veces registrada'], array_map(
        fn($d) => [ucfirst($d['emocion']), (string) $d['total']],
        $distribucionEmociones
    ));
}
$pdf->espaciador(6);

$pdf->subtitulo('Cumplimiento de hábitos en el período');
if (empty($habitos)) {
    $pdf->texto('Aún no hay hábitos para mostrar en este período.');
} else {
    $pdf->graficoBarras('Días completados por hábito', array_map(fn($h) => (string) $h['nombre'], $habitos), array_map(fn($h) => (int) $h['completados'], $habitos));
    $pdf->tabla(['Hábito', 'Días completados'], array_map(
        fn($h) => [$h['nombre'], (string) $h['completados']],
        $habitos
    ));
}
$pdf->espaciador(6);

$pdf->subtitulo('Mis notas');
if (empty($notas)) {
    $pdf->texto('No escribiste notas en tus registros emocionales durante este período.');
} else {
    foreach ($notas as $n) {
        $pdf->texto(date('d/m/Y', strtotime($n['fecha_hora'])) . ' — ' . ucfirst($n['emocion']) . ' (nivel ' . $n['nivel'] . '): ' . $n['nota']);
    }
}

$pdf->espaciador(14);
$pdf->textoSuave('Este reporte es un resumen de tu propio bienestar. Si algo de esto te preocupa, hablar con el psicólogo o el orientador siempre es una buena opción.');

$pdf->salidaDescarga('Serena-Reporte-' . str_replace(' ', '-', $_SESSION['nombre']) . '-' . date('Y-m-d'));
