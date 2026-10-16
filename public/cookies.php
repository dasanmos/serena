<?php require __DIR__ . '/../src/config/config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Política de cookies</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="assets/css/login_pro.css?v=<?= assetVersion('/assets/css/login_pro.css') ?>">
    <script>
        window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;
        (function () {
            try {
                var tema = localStorage.getItem('serena_tema');
                if (tema === 'oscuro' || (!tema && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.setAttribute('data-tema', 'oscuro');
                }
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro" data-cookies-pagina>

    <main class="pagina-legal">
        <div class="contenedor-legal">

            <img src="assets/img/logo/logo.png" alt="Serena" class="logo-legal">

            <h1>Política de cookies</h1>

            <p>
                Las cookies son pequeños archivos que un sitio guarda en tu navegador. Serena también usa el
                almacenamiento local del navegador (<em>localStorage</em>), que cumple una función parecida.
                Aquí te contamos qué guardamos y para qué.
            </p>

            <h2>Lo que usamos siempre (necesario)</h2>
            <ul>
                <li><strong>Cookie de sesión (<code>PHPSESSID</code>).</strong> Te mantiene con la sesión iniciada y protege tu cuenta (por ejemplo, contra formularios falsos). Es solo para esta visita: se borra al cerrar el navegador o al cerrar sesión. No se usa para publicidad.</li>
                <li><strong>Google reCAPTCHA.</strong> En la pantalla de inicio de sesión, si tu colegio lo tiene activado, comprueba que eres una persona y no un programa. Lo carga Google y puede usar sus propias cookies para ese fin de seguridad.</li>
            </ul>

            <h2>Tus preferencias (se guardan en tu navegador)</h2>
            <ul>
                <li><code>serena_tema</code>, <code>serena_contraste</code>, <code>serena_tamano</code> y <code>serena_forzar_animaciones</code>: recuerdan el modo oscuro, el contraste, el tamaño del texto y las animaciones que elegiste. No salen de tu dispositivo.</li>
                <li><code>serena_cookies</code>: guarda lo que decidas en esta página.</li>
            </ul>

            <h2>Servicios de terceros</h2>
            <ul>
                <li><strong>Mapas (opcional).</strong> Los mapas de Serena usan OpenStreetMap y la librería Leaflet. Si no aceptaste todas las cookies, el mapa no se carga hasta que pulses «Cargar mapa». Esos servicios reciben tu dirección IP. Los administradores, al usar «Ubicar la dirección en el mapa», envían esa dirección al buscador de OpenStreetMap (Nominatim).</li>
                <li><strong>Ubicación de tu dispositivo (opcional).</strong> En Recursos de apoyo puedes pulsar «Ver los más cercanos a mí». Tu navegador te pedirá permiso; la ubicación se usa solo en tu dispositivo para ordenar los lugares y <strong>no se guarda ni se envía a Serena</strong>.</li>
                <li><strong>Dictado por voz (opcional).</strong> En las páginas con sesión, el botón 🎤 junto a los campos de texto usa el reconocimiento de voz de tu navegador. En Chrome y Edge, el audio se envía a Google o Microsoft para convertirlo en texto. Serena no graba ni guarda tu voz, y antes de la primera vez te pide aceptar un aviso (se recuerda en <code>serena_voz</code>). No aparece en contraseñas, correos ni códigos.</li>
                <li><strong>Tipografías de Google (Google Fonts).</strong> Las páginas cargan las letras desde servidores de Google, que reciben tu dirección IP. No guardan cookies de seguimiento de Serena.</li>
                <li><strong>Menú «Cómo llegar».</strong> Abre la app o el sitio de mapas que elijas (Google Maps, Waze, Apple Maps, tu app de mapas o OpenStreetMap) solo si tú lo pulsas. Serena solo les pasa las coordenadas del lugar, nunca tu ubicación.</li>
            </ul>

            <p><strong>No usamos</strong> cookies de publicidad ni herramientas de análisis o seguimiento.</p>

            <h2>Tu decisión</h2>
            <p data-cookies-estado role="status">Todavía no has elegido.</p>
            <p>
                <button type="button" data-cookies-elegir="todas">Aceptar todas</button>
                <button type="button" class="boton-secundario" data-cookies-elegir="necesarias">Solo necesarias</button>
            </p>
            <p>Puedes cambiar tu decisión cuando quieras volviendo a esta página, o borrando los datos del sitio en tu navegador.</p>

            <p>
                El tratamiento de tus datos personales se rige por la Ley 1581 de 2012. Lee más en
                <a href="consentimiento_datos.php">Tratamiento de tus datos personales</a>.
            </p>

            <div class="enlaces-login">
                <a href="index.php">Volver al inicio</a>
            </div>

        </div>
    </main>

    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
