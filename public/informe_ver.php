<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Informes.php';
require_once __DIR__ . '/../src/helpers/InformePDF.php';
require_once __DIR__ . '/../src/helpers/DocumentoPdf.php';
require_once __DIR__ . '/../src/helpers/InformeRender.php';

// Genera UN informe del catálogo en HTML imprimible, PDF o CSV. Solo lectura (GET).
requiereRol(...Informes::rolesDelModulo());
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();

$clave = (string) ($_GET['r'] ?? '');
$def = Informes::definicion($clave);
if ($def === null || !Informes::permitido($def, $rol)) {
    http_response_code(404);
    exit('Informe no disponible.');
}
if (!empty($def['cert'])) {
    header('Location: informes.php?r=' . urlencode($clave));
    exit;
}
$formato = (string) ($_GET['formato'] ?? 'html');
if (!in_array($formato, ['html', 'pdf', 'csv'], true)) {
    $formato = 'html';
}

$ctx = Informes::contexto($pdo, $colegioId, $rol, $usuarioId);
$error = '';
$res = null;
$f = [];
try {
    $f = Informes::filtros($pdo, $ctx, $def, $_GET);
    $res = Informes::generar($pdo, $ctx, $def, $f);
} catch (InformeError $ex) {
    $error = $ex->getMessage();
} catch (Throwable $ex) {
    error_log('Informe ' . $clave . ': ' . $ex->getMessage());
    $error = 'No se pudo generar el informe. Revisa los filtros e inténtalo de nuevo.';
}

if ($res === null) {
    $tituloPagina = $def['nombre'];
    $cssExtra = ['/assets/css/academico.css', '/assets/css/informes.css'];
    require __DIR__ . '/parciales/encabezado.php';
    echo '<h1>' . htmlspecialchars($def['nombre']) . '</h1><p class="mensaje mensaje-error">' . htmlspecialchars($error) . '</p>';
    echo '<p><a class="boton" href="informes.php?' . htmlspecialchars(http_build_query(['r' => $clave] + array_diff_key($_GET, ['r' => 1, 'formato' => 1]))) . '">← Volver a los filtros</a></p>';
    require __DIR__ . '/parciales/pie.php';
    exit;
}

$logId = Informes::registrar($pdo, $ctx, $clave, $formato, $f, (int) $res['filas']);
if (!empty($res['sensible'])) {
    auditarAccion($pdo, 'generar_informe', 'informes_generados', $logId, [], ['informe' => $clave, 'formato' => $formato, 'filas' => (int) $res['filas']]);
}
$meta = ['fecha' => date('d/m/Y H:i'), 'usuario' => (string) $_SESSION['nombre'], 'ref' => $logId > 0 ? 'INF-' . $logId : ''];
$ins = $ctx['ins'];
$nombreArchivo = $clave . '-' . date('Ymd-His');

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if ($formato === 'pdf') {
    // &qr=1 → versión con código QR de verificación (se registra en documentos_emitidos).
    $bytes = InformePDF::generar($res, $pdo, $colegioId, $meta, DocumentoPdf::pideQr());
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nombreArchivo . '.pdf"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
}
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
    InformeRender::csv($res, $ins, $meta);
    exit;
}
$qs = http_build_query(array_diff_key($_GET, ['formato' => 1]));
header('Content-Type: text/html; charset=utf-8');
echo InformeRender::html($res, $ins, $meta, [
    'pdf' => 'informe_ver.php?' . $qs . '&formato=pdf',
    'pdfqr' => 'informe_ver.php?' . $qs . '&formato=pdf&qr=1',
    'csv' => 'informe_ver.php?' . $qs . '&formato=csv',
    'volver' => 'informes.php?' . http_build_query(['r' => $clave] + array_diff_key($_GET, ['r' => 1, 'formato' => 1])),
], BASE_URL);
