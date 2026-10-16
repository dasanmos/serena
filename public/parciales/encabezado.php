<?php
declare(strict_types=1);

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../src/config/config.php';
}
// ?pdf=1 / ?pdf=qr en las páginas de informes: captura la página y la entrega como PDF institucional (ver PdfDesdeHtml).
require_once __DIR__ . '/../../src/helpers/PdfDesdeHtml.php';
PdfDesdeHtml::iniciar();
require_once __DIR__ . '/../../src/helpers/navegacion.php';
require_once __DIR__ . '/../../src/helpers/impersonacion.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';

$enlaces = enlacesNavegacionPorRol($_SESSION['rol']);
if (estaViendoComoOtro()) {
    // No tiene sentido ofrecer "Ver como usuario" mientras ya se está viendo como otro:
    // ver_como.php redirige de todas formas, pero mejor no mostrar el enlace y evitar confusión.
    $enlaces = array_values(array_filter($enlaces, fn($e) => $e['url'] !== BASE_URL . '/administrador/ver_como.php'));
}
$rutaActual = basename($_SERVER['SCRIPT_NAME']);

// La campana de notificaciones es exclusiva del rol estudiante: es el único rol para el
// que Notificacion::crear() se invoca (alertas emocionales propias, insignias). El resto
// de roles no tiene notificaciones propias en el esquema actual.
$noLeidas = 0;
if (($_SESSION['rol'] ?? '') === 'estudiante' && isset($pdo)) {
    require_once __DIR__ . '/../../src/modelos/Notificacion.php';
    $noLeidas = Notificacion::contarNoLeidas($pdo, (int)$_SESSION['usuario_id']);
}

$porCategoria = enlacesPorCategoria($_SESSION['rol']);
$gruposMenu = gruposNavegacion((string) $_SESSION['rol'], $enlaces);

// Bandera de un solo uso (ver iniciarSesionUsuario/iniciarVistaComo/volverAAdmin):
// se consume aquí mismo, así que solo dispara el panel en esta carga de página.
$abrirPanelAlCargar = !empty($_SESSION['sesion_recien_iniciada']);
unset($_SESSION['sesion_recien_iniciada']);

$inicialesUsuario = '';
foreach (preg_split('/\s+/', trim((string) $_SESSION['nombre'])) as $parte) {
    if ($parte !== '') $inicialesUsuario .= mb_strtoupper(mb_substr($parte, 0, 1));
    if (mb_strlen($inicialesUsuario) >= 2) break;
}

// Colegio al que pertenece la sesión (se muestra en la barra superior). Se guarda en
// sesión para no consultar la BD en cada página; el superadmin no tiene colegio.
$nombreColegioBarra = '';
if (in_array(($_SESSION['rol'] ?? ''), ['superadmin', 'secretaria'], true)) {
    require_once __DIR__ . '/../../src/helpers/csrf.php';
    require_once __DIR__ . '/../../src/modelos/Colegio.php';
    require_once __DIR__ . '/../../src/helpers/secretaria.php';
    $ctxBarra = (int) ($_SESSION['colegio_contexto'] ?? 0);
    $colegiosBarra = isset($pdo) ? Colegio::listarTodos($pdo, true) : [];
    $etiquetaTodos = esSecretaria() ? 'Todos mis colegios' : 'Todos los colegios';
    $nombreColegioBarra = $etiquetaTodos;
    foreach ($colegiosBarra as $cb) {
        if ((int) $cb['id'] === $ctxBarra) { $nombreColegioBarra = (string) $cb['nombre']; }
    }
} else {
    $colegioIdBarra = (int) ($_SESSION['colegio_id'] ?? 0);
    if ($colegioIdBarra > 0 && (int) ($_SESSION['colegio_nombre_id'] ?? 0) !== $colegioIdBarra && isset($pdo)) {
        $q = $pdo->prepare('SELECT nombre FROM colegios WHERE id = :id');
        $q->execute(['id' => $colegioIdBarra]);
        $_SESSION['colegio_nombre'] = (string) ($q->fetchColumn() ?: '');
        $_SESSION['colegio_nombre_id'] = $colegioIdBarra;
    }
    $nombreColegioBarra = $colegioIdBarra > 0 ? (string) ($_SESSION['colegio_nombre'] ?? '') : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Serena — <?= htmlspecialchars($tituloPagina ?? '') ?></title>
    <link rel="icon" href="<?= BASE_URL ?>/assets/favicon/favicon.ico">
    <meta name="theme-color" content="#E8B923">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/logo/icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/panel.css?v=<?= assetVersion('/assets/css/panel.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estudiante.css?v=<?= assetVersion('/assets/css/estudiante.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/mensajes.css?v=<?= assetVersion('/assets/css/mensajes.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/administrador.css?v=<?= assetVersion('/assets/css/administrador.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/panel_apoyo.css?v=<?= assetVersion('/assets/css/panel_apoyo.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/alertas.css?v=<?= assetVersion('/assets/css/alertas.css') ?>">
    <?php if (in_array(($_SESSION['rol'] ?? ''), ['superadmin', 'secretaria'], true)): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/superadmin.css?v=<?= assetVersion('/assets/css/superadmin.css') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/encuestas.css?v=<?= assetVersion('/assets/css/encuestas.css') ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/grupos.css?v=<?= assetVersion('/assets/css/grupos.css') ?>">
    <?php if (in_array(($_SESSION['rol'] ?? ''), ['psicologo', 'orientador'], true)): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/psicologo.css?v=<?= assetVersion('/assets/css/psicologo.css') ?>">
    <?php endif; ?>
    <?php if (($_SESSION['rol'] ?? '') === 'docente'): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/docente.css?v=<?= assetVersion('/assets/css/docente.css') ?>">
    <?php endif; ?>
    <?php if (($_SESSION['rol'] ?? '') === 'coordinador'): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/coordinador.css?v=<?= assetVersion('/assets/css/coordinador.css') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/menu_barra.css?v=<?= assetVersion('/assets/css/menu_barra.css') ?>">
    <?php foreach (($cssExtra ?? []) as $cssPagina): ?>
    <link rel="stylesheet" href="<?= BASE_URL . htmlspecialchars($cssPagina) ?>?v=<?= assetVersion($cssPagina) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
    <script>
        window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;
        window.SERENA_PDF = <?= PdfDesdeHtml::disponible() ? 'true' : 'false' ?>;
        window.SERENA_ABRIR_PANEL_INICIO = <?= $abrirPanelAlCargar ? 'true' : 'false' ?>;
        // Aplicar el tema y la accesibilidad guardados ANTES de que se pinte la página,
        // para que no haya parpadeo al cargar (esto va inline a propósito, no en base.js,
        // porque necesita correr antes de que el navegador dibuje el <body>).
        // El servidor manda (preferencias.php guarda estos valores en BD): valores
        // explícitos sobrescriben lo que hubiera en localStorage — así la preferencia
        // viaja entre dispositivos. 'sistema'/ausente (o sin sesión, en páginas
        // públicas) deja que decida lo ya guardado localmente / el SO.
        (function () {
            var temaServidor = <?= json_encode($_SESSION['tema_preferido'] ?? 'sistema') ?>;
            var tema;
            if (temaServidor === 'claro' || temaServidor === 'oscuro') {
                tema = temaServidor;
                localStorage.setItem('serena_tema', tema);
            } else {
                tema = localStorage.getItem('serena_tema');
            }
            if (tema === 'oscuro' || (!tema && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-tema', 'oscuro');
            }

            var tamanoServidor = <?= json_encode($_SESSION['tamano_texto'] ?? 'normal') ?>;
            if (tamanoServidor === 'grande' || tamanoServidor === 'muy_grande') {
                localStorage.setItem('serena_tamano', tamanoServidor);
                document.documentElement.setAttribute('data-tamano', tamanoServidor);
            } else {
                localStorage.removeItem('serena_tamano');
            }

            var contrasteServidor = <?= json_encode(!empty($_SESSION['alto_contraste'])) ?>;
            if (contrasteServidor) {
                localStorage.setItem('serena_contraste', '1');
                document.documentElement.setAttribute('data-contraste', 'alto');
            } else {
                localStorage.removeItem('serena_contraste');
            }
        })();
    </script>
</head>
<?php
// Clase por rol en <body> (rol-estudiante, rol-psicologo...) para dar estilo propio a cada rol sin
// afectar a los demás. Lista blanca: nunca se imprime un valor de sesión que no sea un rol conocido.
$rolesConClase = ['estudiante', 'psicologo', 'orientador', 'docente', 'coordinador', 'rector', 'administrador', 'superadmin', 'secretaria', 'secretaria_academica', 'acudiente'];
$rolSesionClase = (string) ($_SESSION['rol'] ?? '');
$claseRol = in_array($rolSesionClase, $rolesConClase, true) ? ' rol-' . $rolSesionClase : '';
if (in_array($rolSesionClase, ['superadmin', 'secretaria'], true)) {
    $claseRol = ' rol-superadmin' . $claseRol; // las pantallas de plataforma comparten estilos
}
if (in_array($rolSesionClase, ['psicologo', 'orientador'], true)) {
    $claseRol .= ' rol-acompana'; // psicología y orientación comparten pantallas (alertas, agenda, fichas)
}
?>
<body class="panel-body<?= $claseRol ?>">

<button type="button" class="barra-acento-superior" id="boton-acento-superior" aria-label="Mostrar información de la cuenta y navegación" aria-expanded="false">
    <span class="barra-acento-visual" aria-hidden="true"></span>
</button>

<div class="panel-acento-fondo" id="panel-acento-fondo"></div>

<div class="panel-acento-superior" id="panel-acento-superior">
    <div class="panel-acento-contenido">
    <div class="panel-acento-marca">
        <span class="panel-acento-logo-insignia">
            <img src="<?= BASE_URL ?>/assets/img/logo/icon-192.png" alt="Serena">
        </span>
        <div>
            <h2>SERENA</h2>
            <p>Plataforma de Bienestar Emocional Institucional</p>
            <p class="panel-acento-caption">Acompañamiento por rol y grupo</p>
        </div>
    </div>

    <div class="panel-acento-sesion">
        <span class="indicador-vista panel-acento-vista">Vista: <strong><?= htmlspecialchars($tituloPagina ?? '') ?></strong></span>
        <?php if (in_array(($_SESSION['rol'] ?? ''), ['superadmin', 'secretaria'], true)): ?>
        <form action="<?= BASE_URL ?>/<?= carpetaPlataforma() ?>/contexto.php" method="POST" class="insignia-colegio selector-contexto<?= $ctxBarra > 0 ? ' en-contexto' : '' ?>">
            <?php campoCSRF(); ?>
            🏫 <select name="colegio_id" onchange="this.form.submit()" aria-label="Colegio en contexto">
                <option value="0"><?= htmlspecialchars($etiquetaTodos) ?></option>
                <?php foreach ($colegiosBarra as $cb): ?>
                    <option value="<?= (int) $cb['id'] ?>" <?= (int) $cb['id'] === $ctxBarra ? 'selected' : '' ?>><?= htmlspecialchars($cb['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit">Ir</button></noscript>
        </form>
        <?php elseif ($nombreColegioBarra !== ''): ?><span class="insignia-colegio" title="Colegio">🏫 <?= htmlspecialchars($nombreColegioBarra) ?></span><?php endif; ?>
        <span class="insignia-rol" aria-hidden="true"><?= htmlspecialchars($inicialesUsuario) ?></span>
        <span><?= htmlspecialchars($_SESSION['nombre']) ?></span>
        <a href="<?= BASE_URL ?>/logout.php" class="panel-acento-boton-salir">Cerrar sesión</a>
    </div>

    <?php if (!empty($porCategoria['vistas'])): ?>
    <div class="panel-acento-fila">
        <span class="etiqueta-categoria">Vistas</span>
        <div class="pestanas-categoria">
            <?php foreach ($porCategoria['vistas'] as $enlace): ?>
                <a href="<?= htmlspecialchars($enlace['url']) ?>" class="pestana <?= basename((string)$enlace['url']) === $rutaActual ? 'activa' : '' ?>">
                    <?php if (!empty($enlace['icono']) && existeIconoRecurso($enlace['icono'])): ?>
                        <img src="<?= BASE_URL ?>/assets/img/recursos/<?= htmlspecialchars($enlace['icono']) ?>" alt="">
                    <?php endif; ?>
                    <?= htmlspecialchars($enlace['texto']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($porCategoria['modulos'])): ?>
    <div class="panel-acento-fila">
        <span class="etiqueta-categoria">Módulos</span>
        <div class="pestanas-categoria">
            <?php foreach ($porCategoria['modulos'] as $enlace): ?>
                <a href="<?= htmlspecialchars($enlace['url']) ?>" class="pestana <?= basename((string)$enlace['url']) === $rutaActual ? 'activa' : '' ?>">
                    <?php if (!empty($enlace['icono']) && existeIconoRecurso($enlace['icono'])): ?>
                        <img src="<?= BASE_URL ?>/assets/img/recursos/<?= htmlspecialchars($enlace['icono']) ?>" alt="">
                    <?php endif; ?>
                    <?= htmlspecialchars($enlace['texto']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    </div>
</div>

<div class="area-principal">
<?php if (estaViendoComoOtro()): $original = identidadAdminOriginal(); ?>
<div class="barra-viendo-como" role="status">
    <span>
        👁️ Viendo como: <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong>
        (<?= htmlspecialchars(ucfirst($_SESSION['rol'])) ?>)
        — sesión real: <?= htmlspecialchars($original['nombre']) ?>
    </span>
    <form action="<?= BASE_URL ?>/administrador/volver_admin.php" method="POST">
        <?php campoCSRF(); ?>
        <button type="submit">Volver a mi cuenta de administrador</button>
    </form>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['demo_maestro'])): require_once __DIR__ . '/../../src/helpers/demo.php'; ?>
<div class="barra-viendo-como barra-demo" role="status">
    <span>🎬 <strong>Modo demostración</strong> · actuando como <strong><?= htmlspecialchars((string) (demoRoles()[$_SESSION['demo_rol'] ?? '']['1'] ?? ucfirst((string) $_SESSION['rol']))) ?></strong> · datos ficticios</span>
    <form action="<?= BASE_URL ?>/demo.php" method="POST">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="entrar">
        <select name="rol" onchange="this.form.submit()" aria-label="Cambiar de rol en la demostración">
            <?php foreach (demoRoles() as $claveDemo => $infoDemo): ?>
                <option value="<?= htmlspecialchars($claveDemo) ?>" <?= ($_SESSION['demo_rol'] ?? '') === $claveDemo ? 'selected' : '' ?>><?= htmlspecialchars($infoDemo[0] . ' ' . $infoDemo[1]) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="<?= BASE_URL ?>/demo.php?guion=<?= urlencode((string) ($_SESSION['demo_rol'] ?? '')) ?>">Guion</a>
    </form>
    <form action="<?= BASE_URL ?>/demo.php" method="POST">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="salir">
        <button type="submit">Salir de la demo</button>
    </form>
</div>
<?php endif; ?>
<?php if (!empty($_SESSION['rol_real']) && ($_SESSION['rol'] ?? '') !== 'superadmin' && !estaViendoComoOtro() && empty($_SESSION['demo_maestro'])): ?>
<div class="barra-viendo-como" role="status">
    <span>🔑 Modo maestro: actuando como <strong><?= htmlspecialchars(ucfirst((string) $_SESSION['rol'])) ?></strong></span>
    <form action="<?= BASE_URL ?>/elegir_rol.php" method="GET">
        <button type="submit">Cambiar de rol</button>
    </form>
</div>
<?php endif; ?>

<header class="barra-superior">
    <img src="<?= BASE_URL ?>/assets/img/logo/logo.png" alt="Serena" class="logo-barra">

    <button type="button" class="boton-menu-movil" id="boton-menu-movil" aria-controls="menubar" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        Menú
    </button>

    <span class="indicador-vista">Vista: <strong><?= htmlspecialchars($tituloPagina ?? '') ?></strong></span>

    <div class="usuario-barra">
        <?php if (($_SESSION['rol'] ?? '') === 'estudiante'): ?>
        <a href="<?= BASE_URL ?>/estudiante/notificaciones.php" class="boton-sonido boton-campana" aria-label="Notificaciones<?= $noLeidas ? " ($noLeidas sin leer)" : '' ?>" title="Notificaciones">
            🔔
            <?php if ($noLeidas > 0): ?><span class="contador-campana"><?= $noLeidas > 9 ? '9+' : $noLeidas ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <button type="button" class="boton-sonido" id="boton-sonido" aria-pressed="false" aria-label="Silenciar sonidos" title="Sonidos activados">🔊</button>
        <button type="button" class="boton-tema" id="boton-tema" aria-pressed="false" aria-label="Modo oscuro" title="Cambiar a modo oscuro">🌙</button>
        <button type="button" class="boton-tema boton-animacion-oculto" id="boton-animacion" aria-pressed="false" aria-label="Forzar animaciones">✨</button>
        <?php if (in_array(($_SESSION['rol'] ?? ''), ['superadmin', 'secretaria'], true)): ?>
        <form action="<?= BASE_URL ?>/<?= carpetaPlataforma() ?>/contexto.php" method="POST" class="insignia-colegio selector-contexto<?= $ctxBarra > 0 ? ' en-contexto' : '' ?>">
            <?php campoCSRF(); ?>
            🏫 <select name="colegio_id" onchange="this.form.submit()" aria-label="Colegio en contexto">
                <option value="0"><?= htmlspecialchars($etiquetaTodos) ?></option>
                <?php foreach ($colegiosBarra as $cb): ?>
                    <option value="<?= (int) $cb['id'] ?>" <?= (int) $cb['id'] === $ctxBarra ? 'selected' : '' ?>><?= htmlspecialchars($cb['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit">Ir</button></noscript>
        </form>
        <?php elseif ($nombreColegioBarra !== ''): ?><span class="insignia-colegio" title="Colegio">🏫 <?= htmlspecialchars($nombreColegioBarra) ?></span><?php endif; ?>
        <span class="insignia-rol" aria-hidden="true"><?= htmlspecialchars($inicialesUsuario) ?></span>
        <span><?= htmlspecialchars($_SESSION['nombre']) ?></span>
        <a href="<?= BASE_URL ?>/logout.php" id="enlace-cerrar-sesion">Cerrar sesión</a>
    </div>
</header>

<nav class="menubar" id="menubar" aria-label="Navegación principal">
    <div class="menubar-cabecera-movil">
        <span>SERENA</span>
        <button type="button" class="menubar-cerrar" id="menubar-cerrar" aria-label="Cerrar menú">&times;</button>
    </div>
    <ul class="menubar-lista">
        <?php foreach ($gruposMenu as $i => $grupo):
            $grupoActivo = false;
            foreach ($grupo['enlaces'] as $enlace) { if (enlaceNavegacionActivo($enlace, $rutaActual)) { $grupoActivo = true; } }
            $idSub = 'mb-' . $i; ?>
        <li class="menubar-grupo<?= $grupoActivo ? ' activo' : '' ?>" data-grupo="<?= htmlspecialchars($grupo['titulo']) ?>">
            <button type="button" class="menubar-boton" aria-haspopup="true" aria-expanded="false" aria-controls="<?= $idSub ?>">
                <span><?= htmlspecialchars($grupo['titulo']) ?></span>
                <span class="menubar-chevron" aria-hidden="true"></span>
            </button>
            <ul class="menubar-submenu" id="<?= $idSub ?>">
                <?php foreach ($grupo['enlaces'] as $enlace): $activo = enlaceNavegacionActivo($enlace, $rutaActual); ?>
                <li>
                    <a href="<?= htmlspecialchars($enlace['url']) ?>" class="menubar-enlace<?= $activo ? ' activo' : '' ?>"<?= $activo ? ' aria-current="page"' : '' ?>>
                        <span class="menubar-icono" aria-hidden="true">
                            <?php if (!empty($enlace['icono']) && existeIconoRecurso($enlace['icono'])): ?>
                                <img src="<?= BASE_URL ?>/assets/img/recursos/<?= htmlspecialchars($enlace['icono']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <?= htmlspecialchars(mb_strtoupper(mb_substr((string) $enlace['texto'], 0, 1))) ?>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars($enlace['texto']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="menubar-sesion">
        <span class="insignia-rol" aria-hidden="true"><?= htmlspecialchars($inicialesUsuario) ?></span>
        <span class="menubar-sesion-nombre"><?= htmlspecialchars($_SESSION['nombre']) ?></span>
        <a href="<?= BASE_URL ?>/logout.php">Cerrar sesión</a>
    </div>
</nav>
<div class="menubar-fondo" id="menubar-fondo"></div>

<main class="contenido-panel">
