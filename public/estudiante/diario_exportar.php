<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/DocumentoPdf.php';
require_once __DIR__ . '/../../src/modelos/Diario.php';
requiereRol('estudiante');

// Misma protección que diario.php: si no desbloqueó el PIN en esta sesión, no
// se genera el PDF — el diario es privado incluso para exportarlo.
if (!isset($_SESSION['diario_desbloqueado'])) {
    header('Location: ' . BASE_URL . '/estudiante/diario_pin.php');
    exit;
}

$uid = (int) $_SESSION['usuario_id'];
$entradas = Diario::listarDeUsuario($pdo, $uid);

$pdf = DocumentoPdf::crear($pdo, 'Diario personal', 'Mi diario — ' . $_SESSION['nombre'], DocumentoPdf::pideQr());
$pdf->titulo('Mi diario — ' . $_SESSION['nombre']);
$pdf->textoSuave('Documento privado. Generado el ' . date('d/m/Y H:i') . '. ' . count($entradas) . ' entrada(s).');
$pdf->espaciador(6);
$pdf->lineaSeparadora();

if (empty($entradas)) {
    $pdf->texto('Todavía no hay entradas en el diario.');
} else {
    foreach ($entradas as $entrada) {
        $encabezadoEntrada = date('d/m/Y H:i', strtotime($entrada['fecha']));
        if (!empty($entrada['titulo'])) {
            $encabezadoEntrada .= ' — ' . $entrada['titulo'];
        }
        $pdf->subtitulo($encabezadoEntrada);
        $pdf->texto($entrada['contenido']);
        $pdf->espaciador(8);
    }
}

$pdf->salidaDescarga('Mi-diario-' . date('Y-m-d'));
