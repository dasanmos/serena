<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/validaciones.php';
require_once __DIR__ . '/../src/modelos/Usuario.php';
require_once __DIR__ . '/../src/modelos/Colegio.php';
require __DIR__ . '/../src/controladores/AuthControlador.php';

if (estaAutenticado()) {
    header('Location: ' . rutaDashboardPorRol($_SESSION['rol']));
    exit;
}

$error = '';

// Paso 1: el estudiante ingresa el código que le dio su colegio. Sin código válido
// no se muestra el formulario ni la lista de grupos (así no se expone la
// estructura de ningún colegio ni se puede registrar alguien en uno ajeno).
$codigoColegio = Colegio::normalizarDane((string) ($_POST['codigo_colegio'] ?? $_GET['codigo'] ?? ''));
$colegio = $codigoColegio !== '' ? Colegio::buscarPorDane($pdo, $codigoColegio) : null;
if ($codigoColegio !== '' && $colegio === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = 'No encontramos un colegio activo con ese código DANE. Revisa que esté completo (12 dígitos).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $colegio === null) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $error = 'No encontramos un colegio activo con ese código DANE. Revisa que esté completo (12 dígitos).';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $datos = [
        'colegio_id'         => (int) $colegio['id'],
        'nombre'             => trim($_POST['nombre'] ?? ''),
        'correo'             => trim($_POST['correo'] ?? ''),
        'password'           => $_POST['password'] ?? '',
        'confirmar_password' => $_POST['confirmar_password'] ?? '',
        'fecha_nacimiento'   => $_POST['fecha_nacimiento'] ?? '',
        'grupo_id'           => ($_POST['grupo_id'] ?? '') !== '' ? (int) $_POST['grupo_id'] : null,
        'consentimiento'     => isset($_POST['consentimiento']),
    ];

    $resultado = AuthControlador::registrarEstudiante($pdo, $datos);

    if ($resultado['exito']) {
        header('Location: login.php?registrado=1');
        exit;
    }

    $error = $resultado['mensaje'];
}

$grupos = [];
if ($colegio !== null) {
    $consultaGrupos = $pdo->prepare(
        "SELECT g.id, g.nombre AS grupo, gr.nombre AS grado
         FROM grupos g
         INNER JOIN grados gr ON gr.id = g.grado_id
         WHERE gr.colegio_id = :colegio
         ORDER BY gr.nombre, g.nombre"
    );
    $consultaGrupos->execute(['colegio' => (int) $colegio['id']]);
    $grupos = $consultaGrupos->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Crear cuenta</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="assets/css/login_pro.css?v=<?= assetVersion('/assets/css/login_pro.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <link rel="apple-touch-icon" href="assets/img/logo/icon-192.png">
    <script>window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro">

    <div class="contenedor-login">
        <div class="card-login">
<?php $marcaTitulo = "Crea tu cuenta y empieza a cuidarte"; $marcaTexto = "Regístrate con el código DANE de tu colegio y comienza tu seguimiento de bienestar."; require __DIR__ . '/parciales/auth_marca.php'; ?>
<div class="login-form-lado">

            <h2>Crear cuenta</h2>

            <?php if ($error): ?>
                <p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <?php if ($colegio === null): ?>
            <form action="registro.php" method="POST" novalidate>
                <?php campoCSRF(); ?>
                <p>Escribe el código DANE de tu colegio (12 dígitos) para crear tu cuenta.</p>
                <label for="codigo_colegio">Código DANE del colegio</label>
                <input type="text" id="codigo_colegio" name="codigo_colegio" required maxlength="14"
                       inputmode="numeric" autocomplete="off"
                       value="<?= htmlspecialchars($codigoColegio) ?>">
                <button type="submit">Continuar</button>
            </form>
            <?php else: ?>
            <p class="mensaje mensaje-exito">Colegio: <strong><?= htmlspecialchars($colegio['nombre']) ?></strong></p>
            <form action="registro.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
                <input type="hidden" name="codigo_colegio" value="<?= htmlspecialchars($colegio['dane']) ?>">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" required
                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">

                <label for="correo">Correo</label>
                <input type="email" id="correo" name="correo" required
                       value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">

                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required
                       value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">

                <label for="grupo_id">Grupo</label>
                <select id="grupo_id" name="grupo_id" required>
                    <option value="">Selecciona tu grupo</option>
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= $grupo['id'] ?>">
                            <?= htmlspecialchars($grupo['grado'] . ' ' . $grupo['grupo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required
                       minlength="8" maxlength="64" data-requisitos="1"
                       pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}"
                       title="Entre 8 y 64 caracteres, con mayúscula, minúscula, número y carácter especial.">
                <div class="medidor-fortaleza" id="medidor-fortaleza" data-nivel="0" aria-hidden="true">
                    <div class="barras-fortaleza">
                        <span class="segmento-fortaleza"></span><span class="segmento-fortaleza"></span>
                        <span class="segmento-fortaleza"></span><span class="segmento-fortaleza"></span>
                    </div>
                    <p class="texto-medidor-fortaleza" id="texto-fortaleza">Entre 8 y 64 caracteres</p>
                </div>

                <label for="confirmar_password">Confirmar contraseña</label>
                <input type="password" id="confirmar_password" name="confirmar_password" autocomplete="new-password" required
                       minlength="8" maxlength="64">

                <label class="opcion-consentimiento">
                    <input type="checkbox" name="consentimiento" required>
                    Acepto el <a href="consentimiento_datos.php" target="_blank">tratamiento de mis datos personales</a>
                </label>

                <button type="submit">Crear cuenta</button>
            </form>
            <?php endif; ?>

            <div class="enlaces-login">
                <a href="login.php">Ya tengo una cuenta</a>
            </div>

        </div><!-- /login-form-lado -->
        </div>
    </div>

    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/requisitosPassword.js?v=<?= assetVersion('/assets/js/requisitosPassword.js') ?>"></script>
    <script>
        // Medidor de fortaleza puramente visual/orientativo — la validación real de
        // formato (8-12 caracteres, mayúscula, minúscula, número y especial) la hace
        // el atributo pattern y, sobre todo, el backend; esto no bloquea el envío.
        (function () {
            var campo = document.getElementById('password');
            var medidor = document.getElementById('medidor-fortaleza');
            var texto = document.getElementById('texto-fortaleza');
            if (!campo || !medidor) return;
            var etiquetas = ['Muy corta', 'Débil', 'Aceptable', 'Buena', 'Fuerte'];
            campo.addEventListener('input', function () {
                var v = campo.value;
                var puntos = 0;
                if (v.length >= 8) puntos++;
                if (/[A-Z]/.test(v) && /[a-z]/.test(v)) puntos++;
                if (/[0-9]/.test(v)) puntos++;
                if (/[^A-Za-z0-9]/.test(v) && v.length >= 10) puntos++;
                var nivel = v.length === 0 ? 0 : Math.max(1, puntos);
                medidor.setAttribute('data-nivel', String(nivel));
                texto.textContent = v.length === 0 ? 'Entre 8 y 64 caracteres' : etiquetas[nivel];
            });
        })();
    </script>
    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
