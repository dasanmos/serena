<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/helpers/DocumentoPdf.php';
require_once __DIR__ . '/../../src/modelos/Certificados.php';

// PDF de certificados para la secretaría académica:
//  · modo=registro (GET)  → «Registro de certificados emitidos» con formato institucional (filtros tipo/estado/q; qr=1 agrega código QR de verificación).
//  · modo=lote (POST)     → varios certificados VIGENTES en un solo PDF (codigos[] marcados, o todos=1 con los filtros). qr=0 → versión sin QR.
requiereRol('secretaria_academica', 'administrador', 'rector');
$colegioId = colegioParaCrear();
$modo = (string) ($_REQUEST['modo'] ?? 'registro');

if ($modo === 'lote') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Método no permitido.');
    }
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $codigos = array_map('strval', (array) ($_POST['codigos'] ?? []));
    if ((string) ($_POST['todos'] ?? '') === '1') {
        $codigos = array_column(Certificados::listar($pdo, $colegioId, (string) ($_POST['tipo'] ?? ''), 'vigente', trim((string) ($_POST['q'] ?? ''))), 'codigo');
    }
    $certificados = Certificados::buscarVarios($pdo, $colegioId, $codigos);
    if (!$certificados) {
        $_SESSION['flash_certificados'] = ['exito' => '', 'error' => 'No hay certificados vigentes para descargar con esa selección.', 'codigo' => ''];
        header('Location: certificados.php');
        exit;
    }
    $pdf = Certificados::pdfLote($certificados, (string) ($_POST['qr'] ?? '1') !== '0');
    foreach ($certificados as $c) {
        Certificados::registrarDescarga($pdo, (int) $c['id']);
    }
    auditarAccion($pdo, 'descargar_certificados_lote', 'certificados', 0, [], ['cantidad' => count($certificados)]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="certificados-lote-' . date('Y-m-d') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}

// ---- Registro de certificados emitidos ----
$filtroTipo = (string) ($_GET['tipo'] ?? '');
$filtroEstado = (string) ($_GET['estado'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$lista = Certificados::listar($pdo, $colegioId, $filtroTipo, $filtroEstado, $q);
$hoy = date('Y-m-d');
$vigentes = $anulados = $vencidos = 0;
$porTipo = [];
$filas = [];
foreach ($lista as $c) {
    $anulado = $c['estado'] === 'anulado';
    $vencido = !$anulado && $c['vence_en'] !== null && $c['vence_en'] < $hoy;
    $anulado ? $anulados++ : ($vencido ? $vencidos++ : $vigentes++);
    $nombreTipo = Certificados::TIPOS[$c['tipo']] ?? (string) $c['tipo'];
    $porTipo[$nombreTipo] = ($porTipo[$nombreTipo] ?? 0) + 1;
    $filas[] = [
        $c['estudiante'] ?: 'Estudiante eliminado', $nombreTipo, (string) (int) $c['anio'], Certificados::formatearCodigo((string) $c['codigo']),
        date('d/m/Y', strtotime((string) $c['emitido_en'])), $c['emisor'] ?: '—', $anulado ? 'Anulado' : ($vencido ? 'Vencido' : 'Vigente'),
        (string) (int) $c['descargas'], (string) (int) $c['verificaciones'],
    ];
}
$alcance = array_filter([
    $filtroTipo !== '' ? 'Tipo: ' . (Certificados::TIPOS[$filtroTipo] ?? $filtroTipo) : '',
    $filtroEstado !== '' ? 'Estado: ' . $filtroEstado : '',
    $q !== '' ? 'Búsqueda: ' . $q : '',
]);

$pdf = DocumentoPdf::crear($pdo, 'Registro de certificados', 'Registro de certificados emitidos', DocumentoPdf::pideQr(), $colegioId);
$pdf->titulo('Registro de certificados emitidos');
$pdf->textoSuave(($alcance ? implode('  ·  ', $alcance) : 'Sin filtros') . '  ·  ' . count($lista) . ' certificado(s) (máx. 300)');
$pdf->indicadores([
    ['Certificados', (string) count($lista)], ['Vigentes', (string) $vigentes], ['Vencidos', (string) $vencidos], ['Anulados', (string) $anulados],
]);
if ($porTipo) {
    arsort($porTipo);
    $pdf->graficoBarras('Certificados por tipo', array_keys($porTipo), array_values($porTipo));
}
$pdf->subtitulo('Detalle');
if (!$filas) {
    $pdf->texto('No hay certificados emitidos con esos filtros.');
} else {
    $pdf->tablaAjustada(['Estudiante', 'Tipo', 'Año', 'Código', 'Emitido', 'Emitido por', 'Estado', 'Desc.', 'Verif.'], $filas, [26, 20, 7, 19, 12, 20, 10, 6, 6]);
}
$pdf->espaciador(10);
$pdf->textoSuave('Cada certificado se comprueba con su código en la página de verificación pública. Este registro es de uso interno.');
$pdf->salidaDescarga('Registro-certificados-' . date('Y-m-d'));
