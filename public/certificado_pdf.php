<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/modelos/Certificados.php';

// Descarga PÚBLICA del PDF de un certificado VÁLIDO (se llega desde verificar.php, a la que apunta el QR).
// Se genera siempre desde la copia guardada al emitir, así que es idéntico al original.
$ahora = time();
$intentos = array_filter($_SESSION['pdf_descargas'] ?? [], static fn (int $t): bool => $t > $ahora - 600);
if (count($intentos) >= 20) {
    http_response_code(429);
    exit('Demasiadas descargas seguidas. Espera unos minutos.');
}
$intentos[] = $ahora;
$_SESSION['pdf_descargas'] = $intentos;

try {
    $c = Certificados::buscar($pdo, (string) ($_GET['c'] ?? ''));
} catch (PDOException $e) {
    $c = null;
}
if ($c === null || Certificados::estado($c) !== 'valido') {
    http_response_code(404);
    exit('Certificado no disponible: no existe, está anulado o venció.');
}
// Versiones: (por defecto) certificado con QR · qr=0 certificado sin QR (solo código y dirección escritos) · hoja=1 hoja de verificación de una página.
$modo = (string) ($_GET['hoja'] ?? '') === '1' ? 'hoja' : ((string) ($_GET['qr'] ?? '1') === '0' ? 'sinqr' : 'qr');
$pdf = $modo === 'hoja' ? Certificados::pdfHojaVerificacion($c) : Certificados::pdf($c, $modo === 'qr');
Certificados::registrarDescarga($pdo, (int) $c['id']);

$inline = (string) ($_GET['ver'] ?? '') === '1';
$prefijo = $modo === 'hoja' ? 'verificacion-' : ($modo === 'sinqr' ? 'certificado-sin-qr-' : 'certificado-');
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $prefijo . $c['tipo'] . '-' . $c['codigo'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $pdf;
