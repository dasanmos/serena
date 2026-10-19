<?php require __DIR__ . '/../src/config/config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Tratamiento de datos personales</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro">

    <div class="pagina-legal">
        <div class="contenedor-legal">

            <img src="assets/img/logo/logo.png" alt="Serena" class="logo-legal">

            <h1>Tratamiento de tus datos personales</h1>

            <p>
                En Serena guardamos información que tú nos das para poder acompañarte:
                tu nombre, tu correo, tu grupo, y los registros que hagas de tus
                emociones, tu sueño, tus hábitos, tus metas y tu diario.
            </p>

            <p>Esto es lo que necesitas saber:</p>

            <ul>
                <li>Tu diario es privado. Solo tú puedes leerlo, y puedes protegerlo además con un PIN propio.</li>
                <li>Tus registros emocionales sí pueden ser vistos por el psicólogo, tu profesor orientador y el administrador del sistema, porque son quienes pueden acompañarte si algo anda mal.</li>
                <li>Nadie va a usar tu información para otra cosa distinta a este acompañamiento.</li>
                <li>Puedes pedir en cualquier momento ver, corregir o eliminar tu información, incluyendo borrar tu cuenta por completo.</li>
                <li>Este tratamiento de datos se rige por la Ley 1581 de 2012 de Colombia (protección de datos personales).</li>
            </ul>

            <p>
                Si tienes dudas sobre esto, puedes hablarlo con el psicólogo o el
                profesor orientador de tu institución.
            </p>

            <div class="enlaces-login">
                <a href="registro.php">Volver al registro</a>
            </div>

        </div>
    </div>

    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
