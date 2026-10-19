<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';

if (estaAutenticado()) {
    header('Location: ' . rutaDashboardPorRol($_SESSION['rol']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Bienestar emocional escolar</title>
    <meta name="description" content="Serena acompaña el bienestar emocional de todo el colegio: registro emocional, sueño, hábitos, metas, diario privado y acompañamiento de psicología y orientación.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Serena — Bienestar emocional escolar">
    <meta property="og:description" content="Acompañamiento emocional para estudiantes, con alertas tempranas para psicología y orientación, y reportes agregados para rectorías y Secretarías de Educación.">
    <meta property="og:image" content="assets/img/logo/icon-512.png">
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?= assetVersion('/assets/css/landing.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <link rel="apple-touch-icon" href="assets/img/logo/icon-192.png">
    <script>
        window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;
        (function () {
            var tema = localStorage.getItem('serena_tema');
            if (tema === 'oscuro' || (!tema && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-tema', 'oscuro');
            }
        })();
    </script>
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="landing-body">
<?php
$funciones = [
    ['icono_emociones.png', 'Emociones', 'Registra cómo te sientes y con qué intensidad, cuantas veces quieras al día.'],
    ['icono_sueno.png', 'Sueño', 'Anota cuánto y cómo duermes, y mira cómo se relaciona con tus días.'],
    ['icono_diario.png', 'Diario con PIN', 'Escribe con imágenes y etiquetas. Solo tú puedes leerlo.'],
    ['icono_habitos.png', 'Hábitos y metas', 'Propón metas pequeñas, sigue tus rachas y gana insignias.'],
    ['icono_citas.png', 'Citas', 'Agenda con psicología u orientación según su disponibilidad real.'],
    ['icono_mensajes.png', 'Chat', 'Escribe a psicología u orientación, o a tu docente de grupo para temas de clase.'],
    ['icono_respiracion.png', 'Respiración', 'Ejercicios guiados para bajar el ritmo antes de un examen o al final del día.'],
    ['icono_urgente.png', 'Ayuda urgente', 'Un botón que avisa de inmediato a psicología y orientación de tu colegio.'],
];
// Quién ve qué. Refleja los permisos reales del código (src/modelos/Expediente.php y HojaVida.php):
// si cambian los permisos, actualizar también esta tabla y la ayuda.
$columnasPrivacidad = ['Tú', 'Tu familia', 'Docentes', 'Psicología y orientación', 'Coordinación y rectoría', 'Administración', 'Secretaría de Educación'];
$filasPrivacidad = [
    ['Emociones y sueño', ['si', 'Sí'], ['no', 'No'], ['no', 'No'], ['si', 'Sí *'], ['parcial', 'Solo cifras del colegio'], ['no', 'No'], ['parcial', 'Solo cifras']],
    ['Diario', ['si', 'Sí'], ['no', 'No'], ['no', 'No'], ['no', 'No'], ['no', 'No'], ['no', 'No'], ['no', 'No']],
    ['Notas, asistencia y horario', ['si', 'Sí'], ['si', 'Sí'], ['parcial', 'De sus grupos'], ['si', 'Sí *'], ['si', 'Sí'], ['si', 'Sí'], ['no', 'No']],
    ['Salud, convivencia y aula de apoyo', ['no', 'No'], ['no', 'No'], ['parcial', 'Alergias y contactos'], ['si', 'Sí *'], ['si', 'Sí'], ['no', 'No'], ['no', 'No']],
];
?>
    <a class="saltar-contenido" href="#contenido">Saltar al contenido</a>

    <nav class="nav-publica" aria-label="Principal">
        <a href="index.php" class="marca-nav-publica">
            <img src="assets/img/logo/logo.png" alt="">
            <span>Serena</span>
        </a>
        <div class="anclas-nav-publica">
            <a href="#como-funciona">Cómo funciona</a>
            <a href="#quien-ve-que">Quién ve qué</a>
            <a href="#secretarias">Secretarías</a>
            <a href="#preguntas">Preguntas</a>
        </div>
        <div class="enlaces-nav-publica">
            <button type="button" class="boton-tema" id="boton-tema" aria-pressed="false" aria-label="Modo oscuro" title="Cambiar a modo oscuro">🌙</button>
            <a href="ayuda.php" class="enlace-nav">Ayuda</a>
            <a href="login.php" class="boton-secundario">Iniciar sesión</a>
        </div>
    </nav>

    <header class="hero-landing" id="contenido">
        <div class="columna-hero-texto">
            <h1>Un espacio del colegio para que nadie tenga que cargar solo lo que siente</h1>
            <p class="intro-hero">
                Los estudiantes cuentan cómo se sienten, cómo duermen y qué quieren lograr.
                Psicología y orientación ven a tiempo cuándo alguien necesita apoyo.
            </p>
            <div class="acciones-hero">
                <a href="registro.php" class="boton-acceso">Crear cuenta de estudiante</a>
                <a href="login.php" class="boton-secundario">Iniciar sesión</a>
            </div>
            <p class="nota-hero">Sin costo para las familias. Cumple la Ley 1581 de 2012 de protección de datos.</p>
        </div>

        <div class="escena-hero">
            <form class="tarjeta-escena" id="demo-emocion" aria-labelledby="demo-titulo" onsubmit="return false">
                <div class="tarjeta-escena-cabecera">
                    <h2 id="demo-titulo">¿Cómo te sientes hoy?</h2>
                    <span class="demo-etiqueta">Pruébalo</span>
                </div>
                <fieldset class="fila-emociones-escena">
                    <legend class="sr-only">Emoción</legend>
                    <?php foreach ([['feliz', '😊', 'Feliz'], ['tranquilo', '😌', 'Tranquilo'], ['ansioso', '😰', 'Ansioso'], ['triste', '😔', 'Triste'], ['cansado', '😴', 'Cansado']] as $i => [$v, $emoji, $txt]): ?>
                    <label class="emocion-escena">
                        <input type="radio" name="demo_emocion" value="<?= $v ?>" <?= $v === 'ansioso' ? 'checked' : '' ?>>
                        <span class="circulo-emocion-escena" aria-hidden="true"><?= $emoji ?></span>
                        <span><?= $txt ?></span>
                    </label>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset class="fila-intensidad-escena">
                    <legend>Intensidad</legend>
                    <?php for ($n = 1; $n <= 5; $n++): ?>
                    <label class="punto-intensidad-escena">
                        <input type="radio" name="demo_nivel" value="<?= $n ?>" <?= $n === 3 ? 'checked' : '' ?>>
                        <span class="sr-only"><?= $n ?> de 5</span>
                    </label>
                    <?php endfor; ?>
                </fieldset>
                <p class="respuesta-escena" id="demo-respuesta" aria-live="polite">
                    Gracias por contarlo. Si los días difíciles se repiten, psicología y orientación reciben un aviso para buscarte.
                </p>
                <p class="pie-escena">Es una muestra: no se guarda nada.</p>
            </form>
        </div>
    </header>

    <aside class="franja-ayuda-inmediata" aria-label="Ayuda inmediata">
        <p><strong>¿Es urgente?</strong> Llama gratis a la <a href="tel:106"><strong>Línea 106</strong></a> (salud mental, 24 horas) o al <a href="tel:123"><strong>123</strong></a>. Serena acompaña, pero no reemplaza una emergencia.</p>
    </aside>

    <section class="seccion-landing" aria-labelledby="titulo-caminos">
        <h2 id="titulo-caminos">¿Qué te trae por aquí?</h2>
        <div class="caminos">
            <div class="camino">
                <h3>Soy estudiante</h3>
                <p>Crea tu cuenta con el código DANE de tu colegio. Necesitas tener al menos 10 años y estar en sexto grado o más.</p>
                <div class="acciones-camino">
                    <a href="registro.php" class="boton-acceso">Crear mi cuenta</a>
                    <a href="login.php" class="enlace-flecha">Ya tengo cuenta</a>
                </div>
            </div>
            <div class="camino">
                <h3>Soy familia</h3>
                <p>Inscribe a tu hijo o hija y sigue el estado de la solicitud. Con tu cuenta de acudiente ves boletines, asistencia y horario.</p>
                <div class="acciones-camino">
                    <a href="inscripcion.php" class="boton-secundario">Inscribir a un estudiante</a>
                    <a href="inscripcion_estado.php" class="enlace-flecha">Consultar una inscripción</a>
                </div>
            </div>
            <div class="camino">
                <h3>Trabajo en un colegio o en una Secretaría</h3>
                <p>Tu cuenta la crea el administrador de tu colegio o el equipo de Serena, y te llega por correo.</p>
                <div class="acciones-camino">
                    <a href="login.php" class="boton-secundario">Iniciar sesión</a>
                    <a href="ayuda.php" class="enlace-flecha">Leer la guía</a>
                </div>
            </div>
        </div>
    </section>

    <section class="seccion-landing" id="como-funciona" aria-labelledby="titulo-pasos">
        <h2 id="titulo-pasos">Cómo funciona</h2>
        <ol class="camino-pasos">
            <li class="paso-camino">
                <h3>Creas tu cuenta</h3>
                <p>Con el código DANE de tu colegio, tu nombre, tu correo y tu grupo. Toma un par de minutos.</p>
            </li>
            <li class="paso-camino">
                <h3>Cuentas cómo vas</h3>
                <p>Tu ánimo, tu sueño y tus hábitos, a tu ritmo y cuando quieras.</p>
            </li>
            <li class="paso-camino">
                <h3>Alguien te acompaña a tiempo</h3>
                <p>Si algo no va bien, psicología u orientación de tu colegio lo ven y pueden buscarte.</p>
            </li>
        </ol>
    </section>

    <section class="seccion-landing" aria-labelledby="titulo-funciones">
        <h2 id="titulo-funciones">Lo que encuentras adentro</h2>
        <ul class="lista-funciones">
            <?php foreach ($funciones as [$icono, $titulo, $texto]): ?>
            <li>
                <img src="assets/img/recursos/<?= $icono ?>" alt="" width="40" height="40">
                <div><h3><?= $titulo ?></h3><p><?= $texto ?></p></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="nota-seccion">El colegio también lleva en Serena la matrícula, las notas, la asistencia, los horarios, los boletines y los certificados con código QR.</p>
    </section>

    <section class="seccion-landing seccion-privacidad" id="quien-ve-que" aria-labelledby="titulo-privacidad">
        <h2 id="titulo-privacidad">Quién ve qué</h2>
        <p class="intro-seccion">Cada persona ve solo lo que necesita para acompañarte, y cada vez que alguien abre tu ficha queda registrado quién y cuándo.</p>
        <div class="tabla-privacidad-scroll" tabindex="0" role="region" aria-labelledby="titulo-privacidad">
            <table class="tabla-privacidad">
                <thead>
                    <tr>
                        <th scope="col"><span class="sr-only">Información</span></th>
                        <?php foreach ($columnasPrivacidad as $c): ?><th scope="col"><?= $c ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filasPrivacidad as $fila): ?>
                    <tr>
                        <th scope="row"><?= array_shift($fila) ?></th>
                        <?php foreach ($fila as [$tipo, $texto]): ?><td class="celda-<?= $tipo ?>"><?= $texto ?></td><?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="nota-seccion">* Psicología ve a todo el colegio; orientación, solo a los grupos que tiene a cargo. La Secretaría de Educación solo ve cifras agrupadas por colegio. En los comparativos, los promedios se ocultan cuando hay muy pocos registros, para que nadie pueda ser identificado.</p>
        <div class="derechos">
            <p>Desde tu perfil puedes pedir que se corrijan tus datos o que se elimine tu cuenta. Nadie vende ni comparte tus datos con terceros.</p>
            <a href="consentimiento_datos.php" class="boton-secundario">Cómo tratamos tus datos</a>
        </div>
    </section>

    <section class="seccion-landing seccion-secretarias" id="secretarias" aria-labelledby="titulo-secretarias">
        <h2 id="titulo-secretarias">Para Secretarías de Educación</h2>
        <p class="intro-seccion">Cada secretaría ve solo los colegios y municipios que tiene asignados, y el bienestar siempre en cifras agrupadas.</p>
        <dl class="lista-secretarias">
            <div><dt>Semáforo de colegios</dt><dd>Participación y alertas altas pendientes por colegio, para saber dónde actuar primero.</dd></div>
            <div><dt>Reportes mensuales</dt><dd>Por colegio o por municipio, en CSV o listos para imprimir.</dd></div>
            <div><dt>Comunicados</dt><dd>Avisos por correo a rectores y administradores, con historial de envío.</dd></div>
            <div><dt>Traslados</dt><dd>Mover a un estudiante de un colegio a otro de la secretaría sin perder su historial.</dd></div>
        </dl>
    </section>

    <section class="seccion-landing" id="preguntas" aria-labelledby="titulo-preguntas">
        <h2 id="titulo-preguntas">Preguntas frecuentes</h2>
        <div class="lista-faq">
            <details class="item-faq">
                <summary>¿Serena reemplaza ir al psicólogo?</summary>
                <p>No. Serena ayuda a conocerte mejor y a pedir ayuda más fácil, pero no diagnostica ni reemplaza a un profesional de la salud mental.</p>
            </details>
            <details class="item-faq">
                <summary>¿Mi familia puede leer lo que escribo?</summary>
                <p>No. Tu diario es solo tuyo y tus emociones solo las ven psicología y orientación. Tu familia ve tus boletines, tu asistencia y tu horario.</p>
            </details>
            <details class="item-faq">
                <summary>¿Qué pasa si registro que me siento muy mal?</summary>
                <p>Si tus registros muestran varios días difíciles seguidos, Serena avisa a psicología y orientación para que puedan buscarte. También puedes usar el botón de ayuda urgente cuando quieras hablar ya.</p>
            </details>
            <details class="item-faq">
                <summary>¿Tiene algún costo?</summary>
                <p>No. Serena no tiene costo para estudiantes ni familias.</p>
            </details>
            <details class="item-faq">
                <summary>¿Quién puede crear una cuenta?</summary>
                <p>Estudiantes de sexto grado en adelante, con al menos 10 años, usando el código DANE de su colegio. Las cuentas del personal y de las familias las crea el colegio; las de las Secretarías, el equipo de Serena.</p>
            </details>
            <details class="item-faq">
                <summary>¿Cómo se une mi colegio o mi Secretaría?</summary>
                <p>El equipo de Serena registra el colegio con su código DANE y crea la cuenta de su administrador. Las Secretarías se asignan por municipio o por colegio y reciben su acceso por correo.</p>
            </details>
            <details class="item-faq">
                <summary>¿Cómo verifico un certificado?</summary>
                <p>Escanea el código QR del certificado o escribe su código en <a href="verificar.php">Verificar certificado</a>.</p>
            </details>
        </div>
    </section>

    <section class="cta-final" aria-labelledby="titulo-cta">
        <h2 id="titulo-cta">Empieza hoy</h2>
        <p>Crear tu cuenta toma un par de minutos. Necesitas el código DANE de tu colegio.</p>
        <a href="registro.php" class="boton-acceso">Crear cuenta de estudiante</a>
    </section>

    <footer class="pie-publico">
        <nav class="enlaces-pie-publico" aria-label="Enlaces del sitio">
            <a href="login.php">Iniciar sesión</a>
            <a href="registro.php">Crear cuenta</a>
            <a href="inscripcion.php">Inscripciones</a>
            <a href="verificar.php">Verificar certificado</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="consentimiento_datos.php">Privacidad de tus datos</a>
            <a href="cookies.php">Cookies</a>
        </nav>
        <p>Serena, proyecto educativo, © <?= date('Y') ?>. Desarrollado por <a href="https://github.com/dasanmos" target="_blank" rel="noopener noreferrer">@dasanmos en GitHub</a>.</p>
    </footer>

    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/landing.js?v=<?= assetVersion('/assets/js/landing.js') ?>"></script>
    <script>
// Serena ya no usa Service Worker/PWA (InfinityFree no soporta bien esa combinación
// de caché + hosting compartido). Este bloque desregistra cualquier Service Worker
// que haya quedado activo de una visita anterior a esta misma app, y borra su caché,
// para que a nadie le quede la versión vieja sirviendo contenido offline obsoleto.
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(function (registros) {
        registros.forEach(function (registro) { registro.unregister(); });
    });
}
if ('caches' in window) {
    caches.keys().then(function (nombres) {
        nombres.filter(function (n) { return n.startsWith('serena-'); })
            .forEach(function (n) { caches.delete(n); });
    });
}
</script>

    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
