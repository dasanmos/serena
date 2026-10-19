<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/modelos/Academico.php';

// Consulta pública del estado de una inscripción: exige el código de seguimiento Y el documento del aspirante.
$resultado = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    // Freno por sesión: máximo 15 consultas cada 10 minutos (evita adivinar códigos).
    $ahora = time();
    $intentos = array_filter($_SESSION['insc_consultas'] ?? [], static fn (int $t): bool => $t > $ahora - 600);
    if (count($intentos) >= 15) {
        $error = 'Hiciste muchas consultas seguidas. Espera unos minutos e inténtalo de nuevo.';
    } else {
        $intentos[] = $ahora;
        $_SESSION['insc_consultas'] = $intentos;
        $resultado = Academico::inscripcionPorCodigo($pdo, (string) ($_POST['codigo'] ?? ''), (string) ($_POST['documento'] ?? ''));
        if ($resultado === null) {
            $error = 'No encontramos una inscripción con ese código y documento. Revisa que estén bien escritos.';
        }
    }
}
$mensajesEstado = [
    'pendiente' => 'Tu solicitud fue recibida y está en cola para revisión.',
    'en_revision' => 'El colegio está revisando tu solicitud.',
    'admitida' => '¡Fue admitida! El colegio se comunicará contigo para completar la matrícula.',
    'matriculada' => 'La matrícula ya fue realizada. Revisa tu correo y el contacto del colegio para recibir el acceso a Serena.',
    'rechazada' => 'La solicitud no fue admitida. Comunícate con el colegio si tienes dudas.',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Estado de la inscripción</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="assets/css/academico.css?v=<?= assetVersion('/assets/css/academico.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <script>window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro">
    <div class="contenedor-login">
        <div class="card-login">
<?php $marcaTitulo = 'Estado de tu inscripción'; $marcaTexto = 'Escribe el código que recibiste al inscribirte y el documento del estudiante.'; require __DIR__ . '/parciales/auth_marca.php'; ?>
<div class="login-form-lado">
            <h2>Consultar inscripción</h2>
            <?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

            <?php if ($resultado): ?>
                <p><strong><?= htmlspecialchars($resultado['nombres'] . ' ' . $resultado['apellidos']) ?></strong><br>
                   <?= htmlspecialchars($resultado['colegio']) ?> · <?= htmlspecialchars($resultado['grado']) ?> · <?= (int) $resultado['anio'] ?></p>
                <p><span class="estado-pill estado-<?= htmlspecialchars($resultado['estado']) ?>"><?= htmlspecialchars(Academico::ESTADOS_INSCRIPCION[$resultado['estado']] ?? $resultado['estado']) ?></span></p>
                <p><?= htmlspecialchars($mensajesEstado[$resultado['estado']] ?? '') ?></p>
                <?php if (!empty($resultado['nota_resolucion'])): ?><p class="texto-suave">Nota del colegio: <?= htmlspecialchars($resultado['nota_resolucion']) ?></p><?php endif; ?>
            <?php endif; ?>

            <form action="inscripcion_estado.php" method="POST" novalidate>
                <?php campoCSRF(); ?>
                <label for="codigo">Código de seguimiento</label>
                <input type="text" id="codigo" name="codigo" required maxlength="12" autocomplete="off" style="text-transform:uppercase" value="<?= htmlspecialchars((string) ($_POST['codigo'] ?? '')) ?>">
                <label for="documento">Documento del estudiante</label>
                <input type="text" id="documento" name="documento" required maxlength="20" inputmode="numeric" autocomplete="off" value="<?= htmlspecialchars((string) ($_POST['documento'] ?? '')) ?>">
                <button type="submit">Consultar</button>
            </form>
            <div class="enlaces-login">
                <a href="inscripcion.php">Inscribir a un estudiante</a>
                <a href="index.php">Volver al inicio</a>
            </div>
</div>
        </div>
    </div>
    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
