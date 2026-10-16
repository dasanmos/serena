<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';

if (estaAutenticado()) {
    header('Location: ' . rutaDashboardPorRol($_SESSION['rol']));
    exit;
}
// Cifras públicas AGREGADAS (nunca datos de personas). Si las tablas no existen aún, la franja no se muestra.
$cifras = ['colegios' => 0, 'municipios' => 0];
try {
    $fila = $pdo->query("SELECT COUNT(*) AS c, COUNT(DISTINCT NULLIF(municipio, '')) AS m FROM colegios WHERE activo = 1")->fetch();
    $cifras = ['colegios' => (int) ($fila['c'] ?? 0), 'municipios' => (int) ($fila['m'] ?? 0)];
} catch (Throwable $e) {
    error_log('index.php: no se pudieron leer las cifras — ' . $e->getMessage());
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
    <link rel="stylesheet" href="assets/css/landing_pro.css?v=<?= assetVersion('/assets/css/landing_pro.css') ?>">
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

    <nav class="nav-publica">
        <a href="index.php" class="marca-nav-publica">
            <img src="assets/img/logo/logo.png" alt="">
            <span>Serena</span>
        </a>
        <div class="anclas-nav-publica" aria-label="Secciones">
            <a href="#como-funciona">Cómo funciona</a>
            <a href="#roles">Roles</a>
            <a href="#secretarias">Secretarías</a>
            <a href="#privacidad">Privacidad</a>
            <a href="#preguntas">Preguntas</a>
        </div>
        <div class="enlaces-nav-publica">
            <button type="button" class="boton-tema" id="boton-tema" aria-pressed="false" aria-label="Modo oscuro" title="Cambiar a modo oscuro">🌙</button>
            <a href="ayuda.php" class="boton-secundario">Ayuda</a>
            <a href="login.php" class="boton-secundario">Iniciar sesión</a>
            <a href="registro.php" class="boton-acceso">Crear cuenta</a>
        </div>
    </nav>

    <header class="hero-landing">
        <div class="columna-hero-texto">
            <span class="eslogan-hero">Cuerpo · Mente · Bienestar</span>
            <h1>Un espacio del colegio para que nadie tenga que cargar solo lo que siente</h1>
            <p class="intro-hero">
                Serena es la plataforma de bienestar emocional de la institución. Estudiantes desde
                sexto grado registran cómo se sienten, cómo duermen y qué hábitos construyen — y
                psicología, orientación y docentes acompañan a tiempo, con la información justa y
                necesaria para cada rol.
            </p>
            <div class="acciones-hero">
                <a href="registro.php" class="boton-acceso">Crear una cuenta</a>
                <a href="login.php" class="boton-secundario">Ya tengo cuenta</a>
                <a href="inscripcion.php" class="boton-secundario">Inscribir a un estudiante</a>
            </div>
            <ul class="chips-confianza">
                <li>🔒 Ley 1581 de 2012</li>
                <li>🛡️ Verificación en 2 pasos</li>
                <li>💛 Sin costo para familias</li>
            </ul>
        </div>
        <div class="escena-hero">
            <div class="resplandor-hero" aria-hidden="true"></div>
            <div class="tarjeta-escena" aria-hidden="true">
                <div class="tarjeta-escena-cabecera">
                    <span>¿Cómo te sientes hoy?</span>
                    <span>7:42 p.m.</span>
                </div>
                <div class="fila-emociones-escena">
                    <div class="emocion-escena"><div class="circulo-emocion-escena">😊</div>Feliz</div>
                    <div class="emocion-escena"><div class="circulo-emocion-escena">😌</div>Tranquilo</div>
                    <div class="emocion-escena activa"><div class="circulo-emocion-escena">😰</div>Ansioso</div>
                    <div class="emocion-escena"><div class="circulo-emocion-escena">😔</div>Triste</div>
                    <div class="emocion-escena"><div class="circulo-emocion-escena">😴</div>Cansado</div>
                </div>
                <p class="etiqueta-escena">Intensidad</p>
                <div class="fila-intensidad-escena">
                    <span class="punto-intensidad-escena activo"></span>
                    <span class="punto-intensidad-escena activo"></span>
                    <span class="punto-intensidad-escena activo"></span>
                    <span class="punto-intensidad-escena"></span>
                    <span class="punto-intensidad-escena"></span>
                </div>
                <p class="nota-escena">"Examen de mañana, me cuesta concentrarme."</p>
            </div>
        </div>
    </header>

    <?php if ($cifras['colegios'] > 0): ?>
    <section class="franja-cifras" aria-label="Serena en cifras">
        <div class="cifra-landing"><strong data-contar="<?= $cifras['colegios'] ?>"><?= $cifras['colegios'] ?></strong><span>colegio<?= $cifras['colegios'] === 1 ? '' : 's' ?> acompañado<?= $cifras['colegios'] === 1 ? '' : 's' ?></span></div>
        <?php if ($cifras['municipios'] > 0): ?>
        <div class="cifra-landing"><strong data-contar="<?= $cifras['municipios'] ?>"><?= $cifras['municipios'] ?></strong><span>municipio<?= $cifras['municipios'] === 1 ? '' : 's' ?></span></div>
        <?php endif; ?>
        <div class="cifra-landing"><strong>24/7</strong><span>Línea 106 de salud mental</span></div>
    </section>
    <?php endif; ?>

    <div class="franja-ayuda-inmediata">
        <div class="contenido-franja-ayuda">
            <span>📞 <strong>Línea 106</strong> — salud mental, gratuita, 24/7</span>
            <span>Serena acompaña; no reemplaza una emergencia. Si es urgente, llama al <strong>123</strong>.</span>
        </div>
    </div>

    <section class="seccion-landing" id="como-funciona">
        <div class="cabecera-seccion-landing">
            <h2>Cómo funciona</h2>
            <p>De crear tu cuenta a sentir que alguien te acompaña, en tres pasos.</p>
        </div>
        <div class="camino-pasos">
            <div class="paso-camino">
                <div class="numero-paso">1</div>
                <h3>Creas tu cuenta</h3>
                <p>Con el código DANE de tu colegio, tu nombre, tu correo y tu grupo. Toma un par de minutos.</p>
            </div>
            <div class="paso-camino">
                <div class="numero-paso">2</div>
                <h3>Le cuentas a Serena cómo vas</h3>
                <p>Tu ánimo, tu sueño, tus hábitos — a tu ritmo, cuando quieras.</p>
            </div>
            <div class="paso-camino">
                <div class="numero-paso">3</div>
                <h3>Alguien de tu colegio te acompaña</h3>
                <p>Psicología y orientación ven lo justo y necesario para apoyarte a tiempo.</p>
            </div>
        </div>
    </section>

    <section class="seccion-landing">
        <div class="cabecera-seccion-landing">
            <h2>Un lugar para cada parte de tu día</h2>
            <p>Herramientas sencillas para conocerte mejor, y una red de apoyo real detrás cuando hace falta.</p>
        </div>
        <div class="bento-funciones">
            <div class="bloque-bento bloque-bento--destacado">
                <h3>Tu diario y tus emociones, en un espacio solo tuyo</h3>
                <p>Registra cómo te sientes cuando quieras, y escribe en un diario protegido con tu propio PIN. Nadie más puede leerlo — ni siquiera un administrador.</p>
            </div>
            <div class="bloque-bento bloque-bento--chico">
                <h3>Metas y hábitos</h3>
                <p>Propónte metas pequeñas, construye hábitos y ve tu racha crecer, con insignias que celebran el camino.</p>
            </div>
            <div class="bloque-bento bloque-bento--chico">
                <h3>Sueño</h3>
                <p>Lleva el ritmo de tu descanso y entiende cómo se relaciona con tus días.</p>
            </div>
            <div class="bloque-bento bloque-bento--chico bloque-bento--acento">
                <h3>Psicología y orientación</h3>
                <p>Pide una cita cuando quieras hablar con alguien, según la disponibilidad real que publiquen.</p>
            </div>
            <div class="bloque-bento bloque-bento--chico">
                <h3>Tu información, protegida</h3>
                <p>Tus datos emocionales solo los ve quien puede acompañarte — psicología, orientación y administración.</p>
            </div>
        </div>
    </section>

    <section class="seccion-landing explorador-roles" id="roles">
        <div class="cabecera-seccion-landing">
            <h2>Pensado para todo el colegio y su Secretaría</h2>
            <p>Ocho roles, cada uno con la información justa que necesita para cumplir su función.</p>
        </div>

        <input type="radio" name="rol" id="rol-estudiante" class="entradas-rol" checked>
        <input type="radio" name="rol" id="rol-psicologo" class="entradas-rol">
        <input type="radio" name="rol" id="rol-orientador" class="entradas-rol">
        <input type="radio" name="rol" id="rol-docente" class="entradas-rol">
        <input type="radio" name="rol" id="rol-coordinador" class="entradas-rol">
        <input type="radio" name="rol" id="rol-rector" class="entradas-rol">
        <input type="radio" name="rol" id="rol-admin" class="entradas-rol">
        <input type="radio" name="rol" id="rol-secretaria" class="entradas-rol">

        <div class="pestanas-rol">
            <label for="rol-estudiante" class="pestana-rol">🎓 Estudiantes</label>
            <label for="rol-psicologo" class="pestana-rol">💬 Psicología</label>
            <label for="rol-orientador" class="pestana-rol">🧭 Orientación</label>
            <label for="rol-docente" class="pestana-rol">📚 Docentes</label>
            <label for="rol-coordinador" class="pestana-rol">🛡️ Coordinación</label>
            <label for="rol-rector" class="pestana-rol">🏛️ Rectoría</label>
            <label for="rol-admin" class="pestana-rol">⚙️ Administración</label>
            <label for="rol-secretaria" class="pestana-rol">🏛️ Secretaría de Educación</label>
        </div>

        <div class="paneles-rol">
            <div class="panel-rol" data-rol="estudiante">
                <h3>Estudiantes</h3>
                <p>Registran su ánimo, su sueño, sus hábitos y metas, escriben en su diario privado, y piden ayuda —agendada o urgente— cuando la necesitan.</p>
            </div>
            <div class="panel-rol" data-rol="psicologo">
                <h3>Psicología</h3>
                <p>Acompaña a los estudiantes de toda la institución: ve sus registros emocionales, atiende alertas tempranas, agenda citas y crea encuestas de bienestar.</p>
            </div>
            <div class="panel-rol" data-rol="orientador">
                <h3>Orientación</h3>
                <p>El mismo acompañamiento que psicología, pero enfocado en los grupos o grados que tiene a cargo.</p>
            </div>
            <div class="panel-rol" data-rol="docente">
                <h3>Docentes</h3>
                <p>Ven la actividad general de su grupo —metas y hábitos, nunca emociones individuales— y pueden marcar a un estudiante para seguimiento si algo les preocupa.</p>
            </div>
            <div class="panel-rol" data-rol="coordinador">
                <h3>Coordinación</h3>
                <p>Consulta reportes agrupados por grado o grupo: cifras y tendencias, nunca el dato de un estudiante en particular.</p>
            </div>
            <div class="panel-rol" data-rol="rector">
                <h3>Rectoría</h3>
                <p>Una visión general de la institución —cifras y análisis— para tomar decisiones informadas sin exponer datos individuales.</p>
            </div>
            <div class="panel-rol" data-rol="secretaria">
                <h3>Secretaría de Educación</h3>
                <p>Acompaña a los colegios de sus municipios con cifras agregadas: semáforo de participación, reportes mensuales descargables y comunicados a rectores y administradores. Nunca ve el dato de un estudiante.</p>
            </div>
            <div class="panel-rol" data-rol="admin">
                <h3>Administración</h3>
                <p>Gestiona usuarios, grupos y la configuración de la plataforma, con una bitácora de auditoría para cada acción sensible.</p>
            </div>
        </div>
    </section>

    <section class="seccion-landing seccion-secretarias" id="secretarias">
        <div class="cabecera-seccion-landing">
            <span class="etiqueta-seccion">Para Secretarías de Educación</span>
            <h2>Una mirada a todos los colegios, sin exponer a ningún estudiante</h2>
            <p>Cada secretaría ve únicamente los colegios y municipios que tiene asignados.</p>
        </div>
        <div class="grid-secretarias">
            <article class="tarjeta-secretaria"><span class="icono-secretaria">🚦</span><h3>Semáforo de colegios</h3><p>Participación y alertas altas pendientes por colegio, para saber dónde actuar primero.</p></article>
            <article class="tarjeta-secretaria"><span class="icono-secretaria">📊</span><h3>Reportes mensuales</h3><p>Por colegio o por municipio, descargables en CSV o listos para imprimir.</p></article>
            <article class="tarjeta-secretaria"><span class="icono-secretaria">📣</span><h3>Comunicados</h3><p>Avisos por correo a los rectores y administradores de sus colegios, con historial de envío.</p></article>
            <article class="tarjeta-secretaria"><span class="icono-secretaria">🔐</span><h3>Privacidad por diseño</h3><p>Solo cifras agregadas: los promedios se ocultan cuando hay muy pocos estudiantes para proteger su identidad.</p></article>
        </div>
    </section>

    <section class="seccion-landing franja-privacidad" id="privacidad">
        <div class="cabecera-seccion-landing">
            <h2>Tu privacidad, en serio</h2>
            <p>Serena cumple la Ley 1581 de 2012 de protección de datos personales de Colombia.</p>
        </div>
        <div class="grid-privacidad">
            <div class="columna-privacidad columna-privacidad--si">
                <h3>Tus derechos</h3>
                <ul>
                    <li><span>✓</span> Ver, corregir o eliminar tu información cuando quieras.</li>
                    <li><span>✓</span> Pedir el borrado completo de tu cuenta.</li>
                    <li><span>✓</span> Saber exactamente quién consultó tu ficha, y cuándo.</li>
                    <li><span>✓</span> Dar tu consentimiento antes de registrarte, con el detalle explicado.</li>
                </ul>
            </div>
            <div class="columna-privacidad columna-privacidad--no">
                <h3>Lo que no hacemos</h3>
                <ul>
                    <li><span>✕</span> No vendemos ni compartimos tus datos con nadie externo.</li>
                    <li><span>✕</span> Tu diario no lo lee nadie más, bajo ninguna circunstancia.</li>
                    <li><span>✕</span> Tus docentes nunca ven tus emociones ni tu sueño.</li>
                    <li><span>✕</span> Coordinación y rectoría nunca ven el dato de un estudiante en particular.</li>
                </ul>
            </div>
        </div>
        <div class="pie-privacidad">
            <a href="consentimiento_datos.php" class="boton-secundario">Ver cómo tratamos tus datos</a>
        </div>
    </section>

    <section class="seccion-landing" id="preguntas">
        <div class="cabecera-seccion-landing">
            <h2>Preguntas frecuentes</h2>
        </div>
        <div class="lista-faq">
            <details class="item-faq">
                <summary>¿Serena reemplaza ir al psicólogo?</summary>
                <p>No. Serena es una herramienta de acompañamiento: ayuda a conocerte mejor y a pedir ayuda más fácil, pero no diagnostica ni reemplaza a un profesional de la salud mental.</p>
            </details>
            <details class="item-faq">
                <summary>¿Mis papás pueden leer lo que escribo?</summary>
                <p>No. Tu diario y tus registros emocionales son privados. Solo psicología, orientación y administración —quienes pueden acompañarte— tienen acceso, y cada consulta queda registrada.</p>
            </details>
            <details class="item-faq">
                <summary>¿Qué pasa si escribo que me siento muy mal?</summary>
                <p>Si tus registros muestran varios días seguidos difíciles, Serena avisa a psicología y orientación para que puedan buscarte a tiempo. También puedes usar el botón de ayuda urgente cuando quieras hablar ya.</p>
            </details>
            <details class="item-faq">
                <summary>¿Tiene algún costo para mi familia?</summary>
                <p>No. Serena es un proyecto de la institución educativa, sin costo para estudiantes ni familias.</p>
            </details>
            <details class="item-faq">
                <summary>¿Quién puede crear una cuenta?</summary>
                <p>Cualquier estudiante de sexto grado en adelante, con al menos 10 años, usando el código DANE de su colegio. Las cuentas del personal las crea el administrador de cada colegio, y las de las Secretarías de Educación, el equipo de Serena.</p>
            </details>
            <details class="item-faq">
                <summary>¿Cómo se une mi colegio o mi Secretaría de Educación?</summary>
                <p>El equipo de Serena registra el colegio con su código DANE y crea la cuenta de su administrador. Las Secretarías de Educación se asignan por municipio o por colegio y reciben su acceso por correo.</p>
            </details>
            <details class="item-faq">
                <summary>¿La Secretaría puede ver lo que escribe un estudiante?</summary>
                <p>No. La Secretaría solo ve cifras agregadas por colegio o municipio: participación, alertas y tendencias. Ningún dato individual.</p>
            </details>
        </div>
    </section>

    <section class="cta-final">
        <h2>¿Empezamos?</h2>
        <p>Crear tu cuenta toma un par de minutos.</p>
        <a href="registro.php" class="boton-acceso">Crear una cuenta</a>
    </section>

    <footer class="pie-publico">
        <div class="enlaces-pie-publico">
            <a href="login.php">Iniciar sesión</a>
            <a href="registro.php">Crear cuenta</a>
            <a href="inscripcion.php">Inscripciones</a>
            <a href="verificar.php">Verificar certificado</a>
            <a href="ayuda.php">Ayuda</a>
            <a href="consentimiento_datos.php">Privacidad de tus datos</a>
            <a href="cookies.php">Cookies</a>
        </div>
        <p class="pie-emergencia">¿Es urgente? Llama a la <strong>Línea 106</strong> (salud mental, 24/7) o al <strong>123</strong>.</p>
        <p>Serena — Proyecto Educativo · © <?= date('Y') ?></p>
    </footer>

    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/landing_pro.js?v=<?= assetVersion('/assets/js/landing_pro.js') ?>"></script>
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
