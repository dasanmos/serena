<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/demo.php';
require_once __DIR__ . '/../src/helpers/demo_guion.php';

// MODO DEMOSTRACIÓN: centro de control. Solo la cuenta MAESTRA (se valida en la BD, también durante la demo).
requiereSesion();
if (demoMaestroId($pdo) === null) {
    require_once __DIR__ . '/../src/helpers/acceso_denegado.php';
    mostrarAccesoDenegado('Esta pantalla es solo para la cuenta maestra.');
}
$roles = demoRoles();
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$mensaje = $_SESSION['flash_demo'] ?? null;
unset($_SESSION['flash_demo']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'entrar') {
        $rol = (string) ($_POST['rol'] ?? '');
        $error = demoEntrar($pdo, $rol);
        if ($error === null) {
            auditarAccion($pdo, 'demo_entrar', 'usuarios', (int) $_SESSION['usuario_id'], [], ['rol' => $rol]);
            // «Entrar y abrir»: solo se acepta una ruta que esté en el guion de ese rol (nunca una dirección cualquiera).
            $destino = (string) ($_POST['destino'] ?? '');
            $rutasRol = array_column(demoItemsPlanos($rol), 1);
            if ($destino !== '' && in_array($destino, $rutasRol, true)) {
                header('Location: ' . BASE_URL . $destino);
            } else {
                header('Location: ' . (!empty($_POST['guion']) ? BASE_URL . '/demo.php?guion=' . urlencode($rol) : rutaDashboardPorRol($rol)));
            }
            exit;
        }
        $_SESSION['flash_demo'] = ['error' => $error];
    } elseif ($accion === 'salir') {
        demoSalir();
        header('Location: ' . BASE_URL . '/elegir_rol.php');
        exit;
    } elseif ($accion === 'sembrar') {
        try {
            $r = demoSembrar($pdo);
            auditarAccion($pdo, 'demo_sembrar', 'colegios', (int) $r['colegio_id'], [], $r);
            $_SESSION['flash_demo'] = ['exito' => "Datos de demostración listos: {$r['usuarios']} usuarios, {$r['estudiantes']} estudiantes, {$r['notas']} notas y {$r['registros_emocionales']} registros emocionales." . ($r['extra'] ?? '')];
        } catch (Throwable $ex) {
            error_log('demoSembrar: ' . $ex->getMessage());
            $_SESSION['flash_demo'] = ['error' => 'No se pudieron preparar los datos: ' . $ex->getMessage()];
        }
    }
    header('Location: ' . BASE_URL . '/demo.php' . (!empty($_POST['guion']) ? '?guion=' . urlencode((string) $_POST['guion']) : ''));
    exit;
}

$listo = demoUsuariosCreados($pdo) > 0;
$catalogo = demoCatalogo();
$conteos = demoConteos();
$guion = (string) ($_GET['guion'] ?? '');
$guion = isset($roles[$guion]) ? $guion : '';
$verMapa = isset($_GET['mapa']) && $guion === '';
$rolActual = (string) ($_SESSION['demo_rol'] ?? '');
$volverA = demoActiva() && $rolActual !== '' ? rutaDashboardPorRol($rolActual) : BASE_URL . '/elegir_rol.php';
$esGlobal = static fn (string $clave): bool => in_array($clave, ['superadmin', 'secretaria'], true);
$familiasRoles = [
    'Estudiantes y familias' => ['estudiante', 'acudiente'],
    'Aula' => ['docente'],
    'Bienestar y apoyo' => ['orientador', 'psicologo'],
    'Dirección' => ['coordinador', 'rector'],
    'Administración del colegio' => ['secretaria_academica', 'administrador'],
    'Territorio y plataforma' => ['secretaria', 'superadmin'],
];
$clavesRoles = array_keys($roles);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Modo demostración</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/demo.css?v=<?= assetVersion('/assets/css/demo.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="demo-body">
<main class="demo-contenedor">
    <header class="demo-cabecera">
        <a href="<?= $e($volverA) ?>"><img src="assets/img/logo/logo.png" alt="Serena" class="demo-logo"></a>
        <div>
            <h1>🎬 Modo demostración</h1>
            <p>Presenta cada rol y cada módulo con esta única cuenta. Los datos son ficticios y viven en un colegio aparte.</p>
        </div>
    </header>

    <nav class="demo-pestanas" aria-label="Secciones de la demostración">
        <a href="demo.php" <?= ($guion === '' && !$verMapa) ? 'aria-current="page"' : '' ?>>Roles</a>
        <a href="demo.php?mapa=1" <?= $verMapa ? 'aria-current="page"' : '' ?>>Mapa de módulos</a>
        <a href="<?= $e($volverA) ?>"><?= demoActiva() ? 'Volver a la plataforma' : 'Selector de rol' ?></a>
    </nav>

    <?php if ($mensaje && !empty($mensaje['error'])): ?><p class="mensaje mensaje-error" role="alert"><?= $e($mensaje['error']) ?></p><?php endif; ?>
    <?php if ($mensaje && !empty($mensaje['exito'])): ?><p class="mensaje mensaje-exito"><?= $e($mensaje['exito']) ?></p><?php endif; ?>

<?php if ($guion !== ''): // ======================================================= GUION DE UN ROL
    $r = $catalogo[$guion];
    $activo = demoActiva() && $rolActual === $guion;
    $totalRol = $conteos['por_rol'][$guion];
    $pos = array_search($guion, $clavesRoles, true);
    $anterior = $clavesRoles[($pos - 1 + count($clavesRoles)) % count($clavesRoles)];
    $siguiente = $clavesRoles[($pos + 1) % count($clavesRoles)];
    $porId = [];
    foreach ($r['grupos'] as $mods) { foreach ($mods as $m) { $porId[$m['id']] = $m; } }
    $puedeEntrar = $listo || $esGlobal($guion);
?>
    <section class="demo-guion" data-rol="<?= $e($guion) ?>">
        <div class="demo-guion-cab">
            <h2><?= $e($r['icono'] . ' Guion: ' . $r['titulo']) ?></h2>
            <p class="demo-sub"><?= $e($r['detalle']) ?> · <strong><?= (int) $totalRol ?> módulos</strong></p>
            <?php if ($esGlobal($guion)): ?><p class="demo-aviso">⚠️ Este rol usa tu propia cuenta y muestra los colegios <strong>reales</strong>. Evítalo en presentaciones públicas.</p><?php endif; ?>
            <?php if (!$activo): ?>
                <form method="POST" action="demo.php" class="demo-linea">
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="entrar"><input type="hidden" name="rol" value="<?= $e($guion) ?>"><input type="hidden" name="guion" value="1">
                    <button type="submit" <?= $puedeEntrar ? '' : 'disabled' ?>>Entrar como <?= $e($r['titulo']) ?></button>
                    <span class="demo-sub"><?= $puedeEntrar ? 'Cada módulo también tiene su propio botón «Entrar y abrir».' : 'Primero prepara los datos de demostración en la pantalla de roles.' ?></span>
                </form>
            <?php else: ?>
                <p class="demo-sub">✅ Estás actuando como <strong><?= $e($r['titulo']) ?></strong>: los enlaces abren cada módulo directamente.</p>
            <?php endif; ?>
        </div>

        <div class="demo-herramientas">
            <label class="demo-buscar"><span class="demo-solo-lectores">Buscar módulo</span>
                <input type="search" id="demo-filtro" placeholder="Buscar módulo (ej.: SIMAT, horario, certificados)…" autocomplete="off"></label>
            <div class="demo-progreso" aria-live="polite"><span id="demo-progreso-texto">0 de <?= (int) $totalRol ?> presentados</span>
                <div class="demo-barra" aria-hidden="true"><span id="demo-progreso-barra"></span></div></div>
            <div class="demo-linea">
                <button type="button" class="demo-boton-sec" id="demo-limpiar">Reiniciar marcas</button>
                <button type="button" class="demo-boton-sec" id="demo-imprimir">🖨️ Imprimir guion</button>
            </div>
        </div>

        <div class="demo-recorrido">
            <h3>⭐ Recorrido sugerido (≈ 8 minutos)</h3>
            <ol>
                <?php foreach ($r['recorrido'] as $idMod): if (!isset($porId[$idMod])) { continue; } $m = $porId[$idMod]; ?>
                    <li><a href="#mod-<?= $e($m['id']) ?>"><?= $e($m['nombre']) ?></a> — <?= $e($m['decir']) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>

        <?php foreach ($r['grupos'] as $nombreGrupo => $mods): ?>
            <section class="demo-grupo">
                <h3><?= $e($nombreGrupo) ?> <small>(<?= count($mods) ?>)</small></h3>
                <div class="demo-modulos">
                <?php foreach ($mods as $m): $clave = $guion . ':' . $m['id']; ?>
                    <article class="demo-modulo" id="mod-<?= $e($m['id']) ?>" data-clave="<?= $e($clave) ?>">
                        <header>
                            <h4><?= $e($m['nombre']) ?></h4>
                            <label class="demo-visto"><input type="checkbox" class="demo-marca" data-clave="<?= $e($clave) ?>"> Presentado</label>
                        </header>
                        <p class="demo-que-es"><?= $e($m['que_es']) ?></p>
                        <p class="demo-decir"><strong>Qué decir:</strong> <?= $e($m['decir']) ?></p>
                        <p class="demo-tip"><strong>Con los datos demo:</strong> <?= $e($m['tip']) ?></p>
                        <?php if ($activo): ?>
                            <a class="demo-abrir" href="<?= $e(BASE_URL . $m['ruta']) ?>">Abrir módulo →</a>
                        <?php else: ?>
                            <form method="POST" action="demo.php">
                                <?php campoCSRF(); ?>
                                <input type="hidden" name="accion" value="entrar"><input type="hidden" name="rol" value="<?= $e($guion) ?>">
                                <input type="hidden" name="destino" value="<?= $e($m['ruta']) ?>">
                                <button type="submit" class="demo-abrir" <?= $puedeEntrar ? '' : 'disabled' ?>>Entrar y abrir →</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <p class="demo-navroles">
            <a href="demo.php?guion=<?= $e($anterior) ?>">&larr; <?= $e($roles[$anterior][1]) ?></a>
            <a href="demo.php">Todos los roles</a>
            <a href="demo.php?guion=<?= $e($siguiente) ?>"><?= $e($roles[$siguiente][1]) ?> &rarr;</a>
        </p>
    </section>

<?php elseif ($verMapa): // ======================================================= MAPA DE MÓDULOS
    $mapa = demoMapaModulos();
?>
    <section class="demo-guion">
        <h2>🗺️ Mapa de módulos</h2>
        <p class="demo-sub"><strong><?= count($mapa) ?> módulos distintos</strong> explicados en el guion. Cada marca indica qué roles lo ven. Pulsa un módulo para ver qué es.</p>
        <label class="demo-buscar"><span class="demo-solo-lectores">Buscar módulo</span>
            <input type="search" id="demo-filtro" placeholder="Buscar módulo…" autocomplete="off"></label>
        <div class="demo-tabla-caja">
        <table class="demo-mapa">
            <thead><tr><th scope="col">Módulo</th>
                <?php foreach ($roles as $clave => $rr): ?><th scope="col" title="<?= $e($rr[1]) ?>"><a href="demo.php?guion=<?= $e($clave) ?>"><?= $e($rr[0]) ?></a></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($mapa as $m): ?>
                <tr class="demo-mapa-fila">
                    <th scope="row"><strong><?= $e($m['nombre']) ?></strong><small><?= $e($m['que_es']) ?></small></th>
                    <?php foreach ($roles as $clave => $rr): ?>
                        <td><?= isset($m['roles'][$clave]) ? '<span class="demo-si" aria-label="Sí">●</span>' : '<span class="demo-no" aria-label="No">·</span>' ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p><a href="demo.php">&larr; Todos los roles</a></p>
    </section>

<?php else: // ======================================================= ELEGIR ROL ?>

    <section class="demo-estado">
        <?php if (!$listo): ?>
            <p><strong>Falta preparar los datos.</strong> Se crea el «<?= $e(DEMO_NOMBRE_COLEGIO) ?>» con un usuario por rol, 24 estudiantes en 4 grupos y datos en <strong>todos los módulos</strong>: notas, horarios, asistencia, observador y convivencia, refuerzos, SIMAT, SIMPADE, restaurante, cartera, inscripciones, encuestas, bienestar emocional, alertas, citas y chat.</p>
        <?php else: ?>
            <p>✅ Datos de demostración listos. Puedes restablecerlos cuando quieras: vuelven a su estado inicial (se borra lo que hayas cambiado durante la presentación).</p>
        <?php endif; ?>
        <form method="POST" action="demo.php" onsubmit="return confirm('<?= $listo ? 'Se borrarán y recrearán los datos de demostración (solo los del colegio demo). ¿Continuar?' : 'Se crearán los datos de demostración. ¿Continuar?' ?>');">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="sembrar">
            <button type="submit"><?= $listo ? '🔄 Restablecer datos de demostración' : '✨ Preparar datos de demostración' ?></button>
        </form>
    </section>

    <p class="demo-resumen"><strong><?= count($roles) ?> roles</strong> · <strong><?= (int) $conteos['distintos'] ?> módulos distintos</strong> explicados. Cada rol tiene un recorrido sugerido y, por módulo, qué es y qué decir. <a href="demo.php?mapa=1">Ver el mapa completo →</a></p>

    <?php foreach ($familiasRoles as $familia => $claves): ?>
        <h2 class="demo-titulo"><?= $e($familia) ?></h2>
        <div class="demo-rejilla">
            <?php foreach ($claves as $clave): if (!isset($roles[$clave])) { continue; } [$icono, $titulo, $detalle] = $roles[$clave]; $r = $catalogo[$clave]; $global = $esGlobal($clave); ?>
                <article class="demo-tarjeta <?= $rolActual === $clave && demoActiva() ? 'demo-actual' : '' ?>">
                    <div class="demo-tarjeta-cab"><span aria-hidden="true"><?= $icono ?></span><div><strong><?= $e($titulo) ?></strong><small><?= $e($detalle) ?></small></div></div>
                    <p class="demo-chip"><?= (int) $conteos['por_rol'][$clave] ?> módulos · <?= count($r['grupos']) ?> secciones</p>
                    <ul><?php foreach (array_slice($r['recorrido'], 0, 4) as $idMod) { foreach ($r['grupos'] as $mods) { foreach ($mods as $m) { if ($m['id'] === $idMod) { echo '<li>' . $e($m['nombre']) . '</li>'; } } } } ?></ul>
                    <?php if ($global): ?><p class="demo-aviso">Usa colegios reales.</p><?php endif; ?>
                    <div class="demo-tarjeta-acciones">
                        <form method="POST" action="demo.php">
                            <?php campoCSRF(); ?>
                            <input type="hidden" name="accion" value="entrar"><input type="hidden" name="rol" value="<?= $e($clave) ?>">
                            <button type="submit" <?= ($listo || $global) ? '' : 'disabled' ?>>Entrar</button>
                        </form>
                        <a href="demo.php?guion=<?= $e($clave) ?>">Ver guion</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <p class="demo-nota">Superadministrador y Secretaría de Educación usan tu propia cuenta y muestran los colegios reales. Para una presentación pública, usa los demás roles.
        Dentro de la demo, la barra superior permite cambiar de rol al instante, abrir el guion y salir.</p>
    <p><a href="<?= $e($volverA) ?>">&larr; Volver</a> · <a href="logout.php">Cerrar sesión</a></p>
<?php endif; ?>
</main>
<script src="assets/js/demo.js?v=<?= assetVersion('/assets/js/demo.js') ?>"></script>
</body>
</html>
