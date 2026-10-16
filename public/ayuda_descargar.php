<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/ayuda.php';

// Descarga de la ayuda en PDF o HTML.
//   ?formato=pdf|html   &ambito=general|rol   [&rol=... solo administrador (roles de colegio) y superadmin (todos)]
// La guía general es pública; la del rol exige sesión y usa el rol de la sesión.
$formato = (string) ($_GET['formato'] ?? 'pdf');
$ambito  = (string) ($_GET['ambito'] ?? 'general');
if (!in_array($formato, ['pdf', 'html'], true)) {
    http_response_code(400);
    exit('Formato no válido.');
}
if (!in_array($ambito, ['general', 'rol'], true)) {
    $ambito = 'general';
}

$rol = null;
if ($ambito === 'rol') {
    requiereSesion(); // sin sesión lleva al login
    $rol = (string) ($_SESSION['rol'] ?? '');
    if (ayudaPuedeVerOtrosRoles($rol) && isset($_GET['rol']) && ayudaPuedeVerRol($rol, (string) $_GET['rol'])) {
        $rol = (string) $_GET['rol'];
    }
    if (!ayudaRolValido($rol)) {
        $ambito = 'general';
        $rol = null;
    }
}

$libro = ayudaArmarLibro($ambito, $rol);
$nombreBase = 'Serena-Manual-' . ($libro['rolNombre'] !== '' ? ayudaNombreArchivo($libro['rolNombre']) : 'General');

if ($formato === 'html') {
    ayudaEnviarHtml($libro, $nombreBase);
}
require_once __DIR__ . '/../src/helpers/ayuda_pdf.php';
ayudaEnviarPdf($libro, $nombreBase);
