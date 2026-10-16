<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/ayuda.php';

// Ayuda de Serena. Sin sesión: guía general (pública). Con sesión: la guía del rol de
// quien entra (y la guía general en una pestaña). El superadmin puede ver la de cualquier rol con
// ?rol=... ; el administrador, solo las de los roles de colegio (no las de Secretaría ni superadmin).
$logueado = estaAutenticado();
if ($logueado) {
    requiereSesion();
}
$rolSesion = $logueado ? (string) ($_SESSION['rol'] ?? '') : '';
$rolVista = $rolSesion;
if (ayudaPuedeVerOtrosRoles($rolSesion) && isset($_GET['rol']) && ayudaPuedeVerRol($rolSesion, (string) $_GET['rol'])) {
    $rolVista = (string) $_GET['rol'];
}
$verGeneral = !$logueado || !ayudaRolValido($rolVista) || (($_GET['ambito'] ?? '') === 'general');

$libro = ayudaArmarLibro($verGeneral ? 'general' : 'rol', $verGeneral ? null : $rolVista);
$bloque = ayudaHtmlPagina($libro, [
    'logueado'   => $logueado,
    'rolSesion'  => $rolSesion,
    'rolVista'   => $rolVista,
    'verGeneral' => $verGeneral,
]);

if ($logueado) {
    $tituloPagina = 'Ayuda';
    $cssExtra = ['/assets/css/ayuda.css'];
    $jsExtra  = ['/assets/js/ayuda.js', '/assets/js/ayuda_ia.js'];
    require __DIR__ . '/parciales/encabezado.php';
    echo $bloque;
    require __DIR__ . '/parciales/pie.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Ayuda</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/ayuda.css?v=<?= assetVersion('/assets/css/ayuda.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <script>
        window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;
        (function () {
            var tema = localStorage.getItem('serena_tema');
            if (tema === 'oscuro' || (!tema && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-tema', 'oscuro');
            }
        })();
    </script>
</head>
<body class="ayuda-publica">
    <nav class="ayuda-publica-nav">
        <a href="index.php" class="ayuda-publica-marca"><img src="assets/img/logo/logo.png" alt=""><span>Serena</span></a>
        <div class="ayuda-publica-acciones">
            <button type="button" class="boton-tema" id="boton-tema" aria-pressed="false" aria-label="Modo oscuro" title="Cambiar a modo oscuro">🌙</button>
            <a href="login.php" class="ayuda-boton ayuda-boton-sec">Iniciar sesión</a>
            <a href="registro.php" class="ayuda-boton">Crear cuenta</a>
        </div>
    </nav>
    <main class="ayuda-publica-main">
        <?= $bloque ?>
    </main>
    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/ayuda.js?v=<?= assetVersion('/assets/js/ayuda.js') ?>"></script>
    <script src="assets/js/ayuda_ia.js?v=<?= assetVersion('/assets/js/ayuda_ia.js') ?>"></script>
    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
