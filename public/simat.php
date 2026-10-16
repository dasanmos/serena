<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/helpers/captura.php';
require_once __DIR__ . '/../src/modelos/Simat.php';

// SIMAT: prepara la matrícula para reportarla (validación, exportación CSV, novedades y conciliación con el listado del SIMAT).
// Serena NO se conecta al SIMAT: deja la información lista y verificada para el cargue que hace el colegio o la secretaría.
requiereRol('secretaria_academica', 'administrador', 'rector');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

require_once __DIR__ . '/parciales/sm_error.php';
// Todo lo que lee la base va protegido: si algo falla, la página se dibuja igual (con el error a la vista) y el menú sigue vivo.
$falla = null;
$listo = false;
$anios = [];
$anio = null;
$anioSel = (int) ($_GET['anio'] ?? $_POST['anio'] ?? 0);
try {
$listo = Simat::tablaExiste($pdo, 'simat_novedades') && Simat::tablaExiste($pdo, 'simat_datos') && Simat::tablaExiste($pdo, 'simat_reportes');
$anios = Academico::anios($pdo, $colegioId);
// Sin año elegido: el actual si tiene matrículas activas; si no, el más reciente que sí las tenga.
$anio = $anioSel > 0 ? Academico::anio($pdo, $anioSel, $colegioId) : null;
if ($anio === null) {
    $anio = Simat::anioPredeterminado($pdo, $colegioId);
}
if ($anio === null && $anios) {
    $anio = $anios[0];
}
} catch (Throwable $ex) {
    $falla = $ex;
}
$anioId = $anio ? (int) $anio['id'] : 0;

$vistas = ['resumen' => 'Resumen', 'revision' => 'Revisión', 'datos' => 'Datos SIMAT', 'conciliar' => 'Conciliar', 'novedades' => 'Novedades', 'ajustes' => 'Ajustes'];
$vista = (string) ($_GET['vista'] ?? $_POST['vista'] ?? 'resumen');
if (!isset($vistas[$vista])) {
    $vista = 'resumen';
}
$smUrl = static fn (string $v, array $extra = []): string => 'simat.php?' . http_build_query(array_merge(['anio' => $anioId, 'vista' => $v], $extra));

// ---------------------------------------------------------------- POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $volver = static fn (string $v, string $ok, string $err = '', array $extra = []) => capVolver('flash_simat', $smUrl($v, $extra), $ok, $err);
    if (!$listo) {
        $volver($vista, '', 'Falta ejecutar la migración sql/migraciones/041_simat_simpade.sql.');
    }

    if ($accion === 'ajustes_guardar') {
        $err = Simat::guardarAjustes($pdo, $colegioId, $_POST);
        if ($err === '') {
            auditarAccion($pdo, 'simat_ajustes', 'colegios', $colegioId, [], ['sede' => (string) ($_POST['sede'] ?? ''), 'jornada' => (string) ($_POST['jornada'] ?? '')]);
        }
        $volver('ajustes', 'Ajustes guardados.', $err);
    }

    if ($accion === 'datos_guardar') {
        $n = 0;
        $err = '';
        // Todo o nada: si un estudiante falla, no queda la tabla guardada a medias.
        $pdo->beginTransaction();
        try {
            foreach (array_slice((array) ($_POST['d'] ?? []), 0, 400, true) as $sid => $campos) {
                if (!is_array($campos)) {
                    continue;
                }
                $r = Simat::guardarDatos($pdo, $colegioId, (int) $sid, $campos, $usuarioId);
                if ($r !== '') {
                    $err = $r;
                    break;
                }
                $n++;
            }
            if ($err === '') {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('simat.php datos_guardar: ' . $ex->getMessage());
            $err = 'No se pudieron guardar los datos. Intenta de nuevo.';
        }
        if ($err === '') {
            auditarAccion($pdo, 'simat_datos_guardar', 'simat_datos', 0, [], ['estudiantes' => $n]);
        }
        $volver('datos', "Datos guardados ({$n} estudiante(s)).", $err, ['grupo' => (int) ($_POST['grupo'] ?? 0), 'faltan' => ($_POST['faltan'] ?? '1') === '0' ? '0' : '1']);
    }

    if ($accion === 'novedad_crear') {
        $err = Simat::crearNovedad($pdo, $colegioId, $anioId, (int) ($_POST['estudiante_id'] ?? 0), (string) ($_POST['tipo'] ?? ''), (string) ($_POST['fecha'] ?? ''),
            (string) ($_POST['causa'] ?? ''), (string) ($_POST['destino'] ?? ''), (string) ($_POST['observacion'] ?? ''), $usuarioId);
        if ($err === '') {
            auditarAccion($pdo, 'simat_novedad_crear', 'simat_novedades', 0, [], ['estudiante_id' => (int) ($_POST['estudiante_id'] ?? 0), 'tipo' => (string) ($_POST['tipo'] ?? '')]);
        }
        $volver('novedades', 'Novedad registrada.', $err);
    }
    if (in_array($accion, ['novedad_reportar', 'novedad_reabrir', 'novedad_eliminar'], true)) {
        $nid = (int) ($_POST['novedad_id'] ?? 0);
        if ($accion === 'novedad_reportar') {
            Simat::marcarReportada($pdo, $nid, $colegioId, $usuarioId);
        } elseif ($accion === 'novedad_reabrir') {
            Simat::reabrirNovedad($pdo, $nid, $colegioId);
        } else {
            Simat::eliminarNovedad($pdo, $nid, $colegioId);
        }
        auditarAccion($pdo, $accion, 'simat_novedades', $nid, [], []);
        $volver('novedades', 'Listo.');
    }

    if ($accion === 'conciliar') {
        $f = $_FILES['archivo'] ?? null;
        if (!$anio) {
            $volver('conciliar', '', 'No hay un año lectivo configurado.');
        }
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            $volver('conciliar', '', 'Sube el archivo CSV que descargaste del SIMAT.');
        }
        if (strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $volver('conciliar', '', 'El archivo debe ser .csv. Si lo tienes en Excel, usa «Guardar como → CSV».');
        }
        if ((int) $f['size'] > 6 * 1024 * 1024) {
            $volver('conciliar', '', 'El archivo pesa más de 6 MB.');
        }
        $res = Simat::conciliar($pdo, $colegioId, $anioId, (string) $f['tmp_name']);
        if ($res['error'] !== '') {
            $volver('conciliar', '', (string) $res['error']);
        }
        foreach (['solo_serena', 'solo_simat', 'diferencias'] as $k) {
            $res[$k] = array_slice($res[$k], 0, 3000);
        }
        $res['anio'] = $anioId;
        $res['archivo'] = mb_substr((string) $f['name'], 0, 80);
        $res['fecha'] = date('d/m/Y H:i');
        $_SESSION['simat_conciliacion'] = $res;
        $dif = count($res['solo_serena']) + count($res['solo_simat']) + count($res['diferencias']);
        Simat::registrarReporte($pdo, $colegioId, $anioId, 'conciliacion', (int) $res['total_simat'], $dif, 0, 'Archivo: ' . $res['archivo'], $usuarioId);
        auditarAccion($pdo, 'simat_conciliar', 'colegios', $colegioId, [], ['filas_simat' => $res['total_simat'], 'diferencias' => $dif]);
        $volver('conciliar', 'Conciliación terminada: ' . $res['coinciden'] . ' coinciden y ' . $dif . ' requieren revisión.');
    }
    $volver($vista, '');
}

$flash = capFlash('flash_simat');

// ---------------------------------------------------------------- datos de cada vista
$ajustes = ['sede' => '', 'dane_sede' => '', 'jornada' => '', 'metodologia' => '', 'formato_fecha' => 'd/m/Y', 'separador' => ';', 'columnas' => []];
$estudiantes = [];
$pendientes = 0;
try {
    $ajustes = Simat::ajustes($pdo, $colegioId);
    if ($anio && in_array($vista, ['resumen', 'revision', 'datos'], true)) {
        $estudiantes = Simat::estudiantes($pdo, $colegioId, $anioId);
    }
    $pendientes = $anio && $listo ? Simat::contarPendientes($pdo, $colegioId, $anioId) : 0;
} catch (Throwable $ex) {
    $falla = $falla ?? $ex;
}
$resumen = Simat::resumen($estudiantes);

$tituloPagina = 'SIMAT';
$cssExtra = ['/assets/css/academico.css', '/assets/css/simat.css'];
$jsExtra = ['/assets/js/simat.js'];
require __DIR__ . '/parciales/encabezado.php';
?>
<div class="sm-modulo">
<h1>SIMAT · Matrícula</h1>
<p class="texto-suave">Prepara y verifica la matrícula del colegio para reportarla al Sistema Integrado de Matrícula. Serena <strong>no se conecta</strong> al SIMAT: te deja la información lista, validada y en el formato de cargue.</p>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if (!$listo): ?>
    <p class="mensaje mensaje-aviso">Falta ejecutar la migración <code>sql/migraciones/041_simat_simpade.sql</code>. Mientras tanto puedes revisar y exportar, pero no registrar novedades ni datos SIMAT.</p>
<?php endif; ?>

<?php if ($falla !== null) { echo smPanelError($falla, 'simat'); } ?>

<?php if ($falla === null) { ob_start(); try { ?>
<?php if (!$anio): ?>
    <p class="mensaje mensaje-aviso">No hay un año lectivo configurado. Créalo en Años lectivos.</p>
<?php else: ?>

<form action="simat.php" method="GET" class="acad-filtros">
    <input type="hidden" name="vista" value="<?= $e($vista) ?>">
    <label>Año lectivo
        <select name="anio" onchange="this.form.submit()">
            <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $anioId === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?></option><?php endforeach; ?>
        </select>
    </label>
    <noscript><button type="submit">Ver</button></noscript>
</form>

<?php require __DIR__ . '/parciales/sm_diagnostico.php'; ?>

<nav class="sm-tabs" aria-label="Secciones de SIMAT">
    <?php foreach ($vistas as $k => $n): ?>
        <a href="<?= $e($smUrl($k)) ?>" class="<?= $vista === $k ? 'activa' : '' ?>" <?= $vista === $k ? 'aria-current="page"' : '' ?>><?= $e($n) ?><?php if ($k === 'novedades' && $pendientes > 0): ?> <span class="sm-badge"><?= $pendientes ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($vista === 'resumen'): ?>
    <?php $generales = Simat::verificacionesGenerales($pdo, $colegioId, $anio, $estudiantes); ?>
    <div class="sm-tarjetas">
        <div class="sm-tarjeta"><strong><?= $resumen['total'] ?></strong><span>Matriculados activos</span></div>
        <div class="sm-tarjeta sm-ok"><strong><?= $resumen['listos'] ?></strong><span>Listos para reportar</span></div>
        <div class="sm-tarjeta sm-error"><strong><?= $resumen['con_errores'] ?></strong><span>Con errores</span></div>
        <div class="sm-tarjeta sm-aviso"><strong><?= $resumen['con_avisos'] ?></strong><span>Con avisos</span></div>
        <div class="sm-tarjeta"><strong><?= $pendientes ?></strong><span>Novedades por reportar</span></div>
    </div>
    <?php $pctListos = $resumen['total'] > 0 ? (int) round($resumen['listos'] * 100 / $resumen['total']) : 0; ?>
    <div class="sm-progreso" role="img" aria-label="<?= $pctListos ?> % de la matrícula lista para reportar">
        <div class="sm-progreso-pista"><div class="sm-progreso-relleno" style="width:<?= $pctListos ?>%"></div></div>
        <small><?= $pctListos ?> % de la matrícula está lista para reportar.</small>
    </div>

    <section class="panel-seccion">
        <h2>Antes de reportar</h2>
        <ul class="sm-chequeo">
            <?php foreach ($generales as $g): ?>
                <li class="sm-chk-<?= $e($g['nivel']) ?>"><span aria-hidden="true"><?= $g['nivel'] === 'ok' ? '✔' : ($g['nivel'] === 'error' ? '✖' : '⚠') ?></span> <?= $e($g['texto']) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="panel-seccion">
        <h2>Cómo se reporta en el SIMAT</h2>
        <p class="texto-suave">Estados que maneja el SIMAT para cada estudiante, según los instructivos de las secretarías de educación:</p>
        <ul class="sm-estados">
            <li><span class="estado-pill">Nuevo</span><span class="estado-pill">Inscrito</span><span class="estado-pill">Asignado</span><span class="estado-pill estado-activa">Matriculado</span></li>
            <li><span class="estado-pill estado-en_revision">Promocionado</span><span class="estado-pill estado-en_revision">Sin continuidad</span><span class="estado-pill estado-rechazada">Reprobado</span> <small class="texto-suave">(al cerrar el año: se matriculan de nuevo o se retiran)</small></li>
            <li><span class="estado-pill estado-en_revision">Trasladado</span><span class="estado-pill estado-rechazada">Retirado</span><span class="estado-pill estado-activa">Graduado</span><span class="estado-pill">Cancelado</span></li>
        </ul>
        <ol class="sm-pasos">
            <li>Corrige los <strong>errores</strong> de la Revisión (documento, nombres, nacimiento, grado y grupo).</li>
            <li>Descarga el CSV y cárgalo (o digita) en el SIMAT; el retiro exige <strong>motivo</strong> y el traslado, institución, sede, jornada, grado y grupo de destino.</li>
            <li>Después de cada acción consulta el estado en <em>Consulta de Alumnos</em> del SIMAT y concilia aquí con el listado descargado.</li>
            <li>Antes de crear un estudiante en el SIMAT búscalo por documento: si ya existe, no lo dupliques (revisa «estado actual» y, si hace falta, pide la fusión).</li>
        </ol>
    </section>

    <section class="panel-seccion">
        <h2>Exportar matrícula (CSV)</h2>
        <p class="texto-suave">Columnas, orden, separador y formato de fecha se ajustan en <a href="<?= $e($smUrl('ajustes')) ?>">Ajustes</a>. Compáralos con la plantilla vigente de tu Secretaría de Educación antes del cargue. La exportación queda registrada en la auditoría.</p>
        <div class="acad-acciones">
            <a class="boton" href="simat_exportar.php?tipo=matricula&amp;anio=<?= $anioId ?>">Descargar todos (<?= $resumen['total'] ?>)</a>
            <a class="boton-secundario" href="simat_exportar.php?tipo=matricula&amp;anio=<?= $anioId ?>&amp;solo_validos=1">Descargar solo los que no tienen errores (<?= $resumen['listos'] ?>)</a>
            <a class="boton-secundario" href="simat_exportar.php?tipo=novedades&amp;anio=<?= $anioId ?>">Novedades pendientes (<?= $pendientes ?>)</a>
        </div>
    </section>

    <?php if ($resumen['problemas']): ?>
    <section class="panel-seccion">
        <h2>Lo que más hay que corregir</h2>
        <div class="acad-tabla-envoltura">
            <table class="acad-tabla">
                <thead><tr><th>Problema</th><th>Tipo</th><th>Estudiantes</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($resumen['problemas'], 0, 12, true) as $p): ?>
                    <tr><td><?= $e($p['m']) ?></td><td><span class="estado-pill <?= $p['nivel'] === 'error' ? 'estado-rechazada' : 'estado-en_revision' ?>"><?= $p['nivel'] === 'error' ? 'Error' : 'Aviso' ?></span></td><td><?= (int) $p['n'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="texto-suave">Los errores bloquean el reporte; los avisos conviene revisarlos. Corrige en la <a href="<?= $e($smUrl('revision', ['f' => 'errores'])) ?>">Revisión</a> (cada fila abre la hoja de vida del estudiante).</p>
    </section>
    <?php endif; ?>

    <?php $bit = Simat::bitacora($pdo, $colegioId); if ($bit): ?>
    <section class="panel-seccion">
        <h2>Historial</h2>
        <ul class="lista-simple">
            <?php foreach ($bit as $b): ?><li><?= $e(date('d/m/Y H:i', strtotime((string) $b['creado_en']))) ?> · <strong><?= $e(['matricula' => 'Exportación', 'novedades' => 'Novedades', 'conciliacion' => 'Conciliación', 'validacion' => 'Validación'][$b['tipo']] ?? $b['tipo']) ?></strong> · <?= (int) $b['filas'] ?> fila(s)<?= (int) $b['errores'] > 0 ? ' · ' . (int) $b['errores'] . ' con observaciones' : '' ?> · <?= $e($b['autor'] ?? '') ?><?= !empty($b['detalle']) ? ' · ' . $e($b['detalle']) : '' ?></li><?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

<?php elseif ($vista === 'revision'): ?>
    <?php
    $filtro = (string) ($_GET['f'] ?? 'errores');
    if (!in_array($filtro, ['errores', 'avisos', 'todos'], true)) {
        $filtro = 'errores';
    }
    $q = Simat::norm((string) ($_GET['q'] ?? ''));
    $gSel = trim((string) ($_GET['g'] ?? ''));
    $opcionesGrupo = [];
    foreach ($estudiantes as $s) {
        $opcionesGrupo[trim($s['d']['grado_nombre'] . ' ' . $s['d']['grupo'])] = true;
    }
    uksort($opcionesGrupo, static fn ($a, $b): int => strnatcasecmp((string) $a, (string) $b));
    $lista = array_values(array_filter($estudiantes, static function (array $s) use ($filtro, $q, $gSel): bool {
        if ($gSel !== '' && trim($s['d']['grado_nombre'] . ' ' . $s['d']['grupo']) !== $gSel) {
            return false;
        }
        if ($filtro === 'errores' && !$s['errores']) {
            return false;
        }
        if ($filtro === 'avisos' && !$s['advertencias']) {
            return false;
        }
        return $q === '' || str_contains(Simat::norm($s['nombre'] . ' ' . $s['d']['numero_documento']), $q);
    }));
    $total = count($lista);
    $lista = array_slice($lista, 0, 400);
    ?>
    <form action="simat.php" method="GET" class="acad-filtros">
        <input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="revision">
        <label>Mostrar
            <select name="f" onchange="this.form.submit()">
                <option value="errores" <?= $filtro === 'errores' ? 'selected' : '' ?>>Solo con errores</option>
                <option value="avisos" <?= $filtro === 'avisos' ? 'selected' : '' ?>>Solo con avisos</option>
                <option value="todos" <?= $filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
        </label>
        <label>Grupo
            <select name="g" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach (array_keys($opcionesGrupo) as $og): ?><option value="<?= $e((string) $og) ?>" <?= $gSel === (string) $og ? 'selected' : '' ?>><?= $e((string) $og) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Buscar <input type="search" name="q" value="<?= $e($_GET['q'] ?? '') ?>" placeholder="Nombre o documento"></label>
        <button type="submit">Buscar</button>
    </form>
    <p class="texto-suave"><?= $total ?> estudiante(s)<?= $total > 400 ? ' (se muestran los primeros 400; usa la búsqueda)' : '' ?>. Para corregir un dato abre la hoja de vida (pestaña Matrícula).</p>
    <?php if (!$lista): ?>
        <div class="estado-vacio"><p><?= $filtro === 'errores' ? '¡Sin errores! Ningún estudiante tiene problemas que bloqueen el reporte.' : 'No hay resultados.' ?></p></div>
    <?php else: ?>
    <div class="acad-tabla-envoltura">
        <table class="acad-tabla">
            <thead><tr><th>Estudiante</th><th>Grado</th><th>Documento</th><th>Errores</th><th>Avisos</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lista as $s): ?>
                <tr>
                    <td><?= $e($s['nombre']) ?></td>
                    <td><?= $e($s['d']['grado_nombre'] . ' ' . $s['d']['grupo']) ?></td>
                    <td><?= $e(trim($s['d']['tipo_documento'] . ' ' . $s['d']['numero_documento'])) ?></td>
                    <td><?php if ($s['errores']): ?><ul class="sm-problemas sm-p-error"><?php foreach ($s['errores'] as $p): ?><li><?= $e($p['m']) ?></li><?php endforeach; ?></ul><?php else: ?>—<?php endif; ?></td>
                    <td><?php if ($s['advertencias']): ?><ul class="sm-problemas sm-p-aviso"><?php foreach ($s['advertencias'] as $p): ?><li><?= $e($p['m']) ?></li><?php endforeach; ?></ul><?php else: ?>—<?php endif; ?></td>
                    <td><a class="boton-secundario" href="<?= $e(BASE_URL) ?>/estudiante_ficha.php?est=<?= (int) $s['id'] ?>">Hoja de vida</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

<?php elseif ($vista === 'datos'): ?>
    <?php
    $grupoSel = (int) ($_GET['grupo'] ?? 0);
    $soloFaltan = ($_GET['faltan'] ?? '1') === '1';
    $grupos = Academico::grupos($pdo, $colegioId);
    $q = $pdo->prepare("SELECT m.estudiante_id, m.grupo_id FROM matriculas m WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'activa'");
    $q->execute([$colegioId, $anioId]);
    $grupoDe = [];
    foreach ($q as $r) {
        $grupoDe[(int) $r['estudiante_id']] = (int) $r['grupo_id'];
    }
    $lista = array_values(array_filter($estudiantes, static function (array $s) use ($grupoSel, $soloFaltan, $grupoDe): bool {
        if ($grupoSel > 0 && ($grupoDe[$s['id']] ?? 0) !== $grupoSel) {
            return false;
        }
        if ($soloFaltan) {
            $d = $s['d'];
            return $d['proviene_de'] === '' || $d['subsidio_condicionado'] === '' || $d['internado'] === '' || $d['matricula_contratada'] === '';
        }
        return true;
    }));
    $total = count($lista);
    $lista = array_slice($lista, 0, 100);
    $sn = static function (string $nombre, string $actual) use ($e): string {
        $h = '<select name="' . $e($nombre) . '"><option value="">—</option>';
        foreach (['S' => 'Sí', 'N' => 'No'] as $v => $t) {
            $h .= '<option value="' . $v . '"' . ($actual === $v ? ' selected' : '') . '>' . $t . '</option>';
        }
        return $h . '</select>';
    };
    ?>
    <p class="texto-suave">Datos que el SIMAT pide y no están en la hoja de vida: procedencia, subsidio, internado y matrícula contratada. Elige un grupo para trabajar por partes (máximo 100 estudiantes por pantalla).</p>
    <form action="simat.php" method="GET" class="acad-filtros">
        <input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="datos">
        <label>Grupo
            <select name="grupo" onchange="this.form.submit()">
                <option value="0">Todos</option>
                <?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $grupoSel === (int) $g['id'] ? 'selected' : '' ?>><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Mostrar
            <select name="faltan" onchange="this.form.submit()">
                <option value="1" <?= $soloFaltan ? 'selected' : '' ?>>Solo a quienes les falta algo</option>
                <option value="0" <?= !$soloFaltan ? 'selected' : '' ?>>Todos</option>
            </select>
        </label>
    </form>
    <?php if (!$lista): ?>
        <div class="estado-vacio"><p>No hay estudiantes por completar con este filtro.</p></div>
    <?php else: ?>
    <p class="texto-suave"><?= $total ?> estudiante(s)<?= $total > 100 ? ' (se muestran 100)' : '' ?>.</p>
    <form action="simat.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="datos_guardar"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="datos"><input type="hidden" name="grupo" value="<?= $grupoSel ?>"><input type="hidden" name="faltan" value="<?= $soloFaltan ? '1' : '0' ?>">
        <div class="acad-filtros">
            <label>Buscar en esta página <input type="search" data-sm-filtro="#sm-tabla-datos" placeholder="Nombre del estudiante"></label>
            <span class="texto-suave" data-sm-contador="#sm-tabla-datos" aria-live="polite"></span>
        </div>
        <div class="acad-tabla-envoltura">
            <table class="acad-tabla sm-datos-tabla" id="sm-tabla-datos">
                <thead><tr><th>Estudiante</th><th>Proviene de</th><th>Subsidio condicionado</th><th>Internado</th><th>Matrícula contratada</th><th>Capacidad excepcional</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $s): $id = (int) $s['id']; $d = $s['d']; ?>
                    <tr>
                        <td><?= $e($s['nombre']) ?><br><small class="texto-suave"><?= $e($d['grado_nombre'] . ' ' . $d['grupo']) ?></small></td>
                        <td>
                            <select name="d[<?= $id ?>][proviene_de]"><option value="">—</option>
                                <?php foreach (Simat::PROVIENE as $v => $t): ?><option value="<?= $e($v) ?>" <?= $d['proviene_de'] === $v ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td><?= $sn("d[{$id}][subsidio_condicionado]", $d['subsidio_condicionado']) ?></td>
                        <td><?= $sn("d[{$id}][internado]", $d['internado']) ?></td>
                        <td><?= $sn("d[{$id}][matricula_contratada]", $d['matricula_contratada']) ?></td>
                        <td><input type="text" name="d[<?= $id ?>][capacidad_excepcional]" maxlength="40" value="<?= $e($d['capacidad_excepcional']) ?>" placeholder="Ninguna"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="acad-acciones sm-sticky-guardar"><button type="submit" <?= $listo ? '' : 'disabled' ?>>Guardar</button></div>
    </form>
    <?php endif; ?>

<?php elseif ($vista === 'conciliar'): ?>
    <?php $c = $_SESSION['simat_conciliacion'] ?? null; if ($c !== null && (int) ($c['anio'] ?? 0) !== $anioId) { $c = null; } ?>
    <section class="panel-seccion">
        <h2>Comparar con el listado del SIMAT</h2>
        <p class="texto-suave">Descarga del SIMAT el listado de estudiantes matriculados (formato Excel o CSV) y súbelo aquí como <strong>.csv</strong>. Serena lo compara con la matrícula activa y te dice quién falta, quién sobra y qué no coincide (documento, grado, grupo, nombre). El archivo no se guarda: solo se usa para esta comparación.</p>
        <form action="simat.php" method="POST" enctype="multipart/form-data" class="acad-filtros">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="conciliar"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="conciliar">
            <label>Archivo CSV del SIMAT <input type="file" name="archivo" accept=".csv,text/csv" required></label>
            <button type="submit" <?= $listo ? '' : 'disabled' ?>>Conciliar</button>
        </form>
        <p class="texto-suave">En el SIMAT el listado se descarga desde <em>Administración → Procesos por lotes</em>; si llega en CSV, ábrelo en Excel como archivo plano y guárdalo de nuevo como .csv.</p>
        <p class="texto-suave">Reconoce el separador y los encabezados más comunes («Número de documento», «Grado», «Grupo», «Estado», apellidos y nombres). Si tu archivo trae otros nombres de columna y no lo reconoce, cámbiales el encabezado.</p>
    </section>

    <?php if ($c !== null): ?>
    <section class="panel-seccion">
        <h2>Resultado · <?= $e($c['archivo']) ?> <small class="texto-suave">(<?= $e($c['fecha']) ?>)</small></h2>
        <div class="sm-tarjetas">
            <div class="sm-tarjeta"><strong><?= (int) $c['total_simat'] ?></strong><span>Filas en el SIMAT</span></div>
            <div class="sm-tarjeta"><strong><?= (int) $c['total_serena'] ?></strong><span>Activos en Serena</span></div>
            <div class="sm-tarjeta sm-ok"><strong><?= (int) $c['coinciden'] ?></strong><span>Coinciden</span></div>
            <?php if (!empty($c['retirados_ok'])): ?><div class="sm-tarjeta sm-info"><strong><?= (int) $c['retirados_ok'] ?></strong><span>Retirados en el SIMAT (correcto)</span></div><?php endif; ?>
            <div class="sm-tarjeta sm-aviso"><strong><?= count($c['solo_serena']) ?></strong><span>Solo en Serena</span></div>
            <div class="sm-tarjeta sm-aviso"><strong><?= count($c['solo_simat']) ?></strong><span>Solo en SIMAT</span></div>
            <div class="sm-tarjeta sm-error"><strong><?= count($c['diferencias']) ?></strong><span>Con diferencias</span></div>
        </div>
        <div class="acad-acciones"><a class="boton-secundario" href="simat_exportar.php?tipo=conciliacion&amp;anio=<?= $anioId ?>">Descargar diferencias (CSV)</a></div>

        <?php foreach (['solo_serena' => ['Matriculados en Serena que no aparecen en el SIMAT', 'grado_serena'], 'solo_simat' => ['En el SIMAT sin matrícula activa en Serena', 'grado_simat'], 'diferencias' => ['Datos que no coinciden', 'grado_serena']] as $k => [$titulo]): ?>
            <?php if ($c[$k]): ?>
                <h3><?= $e($titulo) ?> (<?= count($c[$k]) ?>)</h3>
                <div class="acad-tabla-envoltura">
                    <table class="acad-tabla">
                        <thead><tr><th>Documento</th><th>Nombre</th><th>Detalle</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($c[$k], 0, 200) as $f): ?>
                            <tr><td><?= $e(trim(($f['tipo'] ?? '') . ' ' . $f['numero'])) ?></td><td><?= $e($f['nombre']) ?></td><td><?= $e($f['detalle']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($c[$k]) > 200): ?><p class="texto-suave">Se muestran 200; el CSV trae todos.</p><?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$c['solo_serena'] && !$c['solo_simat'] && !$c['diferencias']): ?><p class="mensaje mensaje-exito">Todo coincide con el SIMAT.</p><?php endif; ?>
    </section>
    <?php endif; ?>

<?php elseif ($vista === 'novedades'): ?>
    <?php
    $estadoNov = (string) ($_GET['estado'] ?? 'pendiente');
    if (!in_array($estadoNov, ['pendiente', 'reportada', ''], true)) {
        $estadoNov = 'pendiente';
    }
    $novedades = Simat::novedades($pdo, $colegioId, $anioId, $estadoNov);
    $q = $pdo->prepare('SELECT DISTINCT u.id, u.nombre FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? ORDER BY u.nombre');
    $q->execute([$colegioId, $anioId]);
    $elegibles = $q->fetchAll();
    ?>
    <p class="texto-suave">Retiros, traslados, ingresos y cambios de grupo o grado que hay que registrar en el SIMAT. Los retiros de matrícula hechos en Serena aparecen aquí solos. Cuando los reportes en el SIMAT, márcalos como reportados.</p>
    <form action="simat.php" method="GET" class="acad-filtros">
        <input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="novedades">
        <label>Estado
            <select name="estado" onchange="this.form.submit()">
                <option value="pendiente" <?= $estadoNov === 'pendiente' ? 'selected' : '' ?>>Por reportar</option>
                <option value="reportada" <?= $estadoNov === 'reportada' ? 'selected' : '' ?>>Ya reportadas</option>
                <option value="" <?= $estadoNov === '' ? 'selected' : '' ?>>Todas</option>
            </select>
        </label>
    </form>
    <?php if (!$novedades): ?>
        <div class="estado-vacio"><p>No hay novedades en esta lista.</p></div>
    <?php else: ?>
    <div class="acad-filtros">
        <label>Buscar en la lista <input type="search" data-sm-filtro="#sm-tabla-nov" placeholder="Nombre, documento o causa"></label>
        <span class="texto-suave" data-sm-contador="#sm-tabla-nov" aria-live="polite"></span>
    </div>
    <div class="acad-tabla-envoltura">
        <table class="acad-tabla" id="sm-tabla-nov">
            <thead><tr><th>Fecha</th><th>Estudiante</th><th>Novedad</th><th>Causa / destino</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($novedades as $n): ?>
                <tr>
                    <td><?= $e(date('d/m/Y', strtotime((string) $n['fecha']))) ?></td>
                    <td><?= $e($n['estudiante']) ?><br><small class="texto-suave"><?= $e(trim($n['tipo_documento'] . ' ' . $n['documento'])) ?></small></td>
                    <td><?= $e(Simat::NOVEDADES[$n['tipo']] ?? $n['tipo']) ?><?= $n['origen'] === 'automatico' ? ' <small class="texto-suave">(automática)</small>' : '' ?></td>
                    <td><?= $e(trim(($n['causa'] ?? '') . ($n['destino'] ? ' → ' . $n['destino'] : ''))) ?><?= !empty($n['observacion']) ? '<br><small class="texto-suave">' . $e($n['observacion']) . '</small>' : '' ?></td>
                    <td><span class="estado-pill <?= $n['estado'] === 'reportada' ? 'estado-activa' : 'estado-pendiente' ?>"><?= $n['estado'] === 'reportada' ? 'Reportada' : 'Por reportar' ?></span>
                        <?php if ($n['estado'] === 'reportada' && $n['reportada_en']): ?><br><small class="texto-suave"><?= $e(date('d/m/Y', strtotime((string) $n['reportada_en']))) ?></small><?php endif; ?></td>
                    <td class="acad-acciones">
                        <form action="simat.php" method="POST">
                            <?php campoCSRF(); ?>
                            <input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="novedades"><input type="hidden" name="novedad_id" value="<?= (int) $n['id'] ?>">
                            <?php if ($n['estado'] === 'pendiente'): ?>
                                <button type="submit" name="accion" value="novedad_reportar">Marcar reportada</button>
                                <button type="submit" name="accion" value="novedad_eliminar" class="boton-peligro" data-sm-confirmar="¿Eliminar esta novedad?">Quitar</button>
                            <?php else: ?>
                                <button type="submit" name="accion" value="novedad_reabrir" class="boton-secundario">Reabrir</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <section class="panel-seccion">
        <h2>Registrar una novedad</h2>
        <form action="simat.php" method="POST" class="acad-filtros" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="novedad_crear"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="novedades">
            <label>Estudiante
                <select name="estudiante_id" required><option value="">Elige…</option>
                    <?php foreach ($elegibles as $s): ?><option value="<?= (int) $s['id'] ?>"><?= $e($s['nombre']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Tipo
                <select name="tipo" required><?php foreach (Simat::NOVEDADES as $k => $t): ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select>
            </label>
            <label>Fecha <input type="date" name="fecha" value="<?= $e(date('Y-m-d')) ?>" required></label>
            <label>Motivo (obligatorio en retiros) <input type="text" name="causa" maxlength="150"></label>
            <label>Destino: institución y sede (obligatorio en traslados) <input type="text" name="destino" maxlength="150"></label>
            <label>Observación <input type="text" name="observacion" maxlength="300"></label>
            <button type="submit" <?= $listo ? '' : 'disabled' ?>>Registrar</button>
        </form>
    </section>

<?php elseif ($vista === 'ajustes'): ?>
    <?php
    $grados = Academico::grados($pdo, $colegioId);
    $col = Simat::colegio($pdo, $colegioId);
    ?>
    <section class="panel-seccion">
        <h2>Ajustes del reporte</h2>
        <p class="texto-suave">Establecimiento: <strong><?= $e($col['nombre']) ?></strong> · DANE <strong><?= $e($col['dane']) ?></strong> (lo cambia el superadmin). Ajusta aquí lo demás para que el CSV coincida con la plantilla que te entregó la Secretaría de Educación.</p>
        <form action="simat.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="ajustes_guardar"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="ajustes">
            <div class="acad-filtros">
                <label>Sede <input type="text" name="sede" maxlength="120" value="<?= $e($ajustes['sede']) ?>" required></label>
                <label>Código DANE de la sede <input type="text" name="dane_sede" maxlength="15" value="<?= $e($ajustes['dane_sede']) ?>" inputmode="numeric"></label>
                <label>Jornada
                    <select name="jornada" required><option value="">Elige…</option>
                        <?php foreach (Simat::JORNADAS as $j): ?><option value="<?= $e($j) ?>" <?= $ajustes['jornada'] === $j ? 'selected' : '' ?>><?= $e($j) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Metodología / modelo <input type="text" name="metodologia" maxlength="80" value="<?= $e($ajustes['metodologia']) ?>"></label>
            </div>
            <div class="acad-filtros">
                <label>Formato de fecha
                    <select name="formato_fecha"><?php foreach (Simat::FORMATOS_FECHA as $k => $t): ?><option value="<?= $e($k) ?>" <?= $ajustes['formato_fecha'] === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select>
                </label>
                <label>Separador del CSV
                    <select name="separador"><?php foreach (Simat::SEPARADORES as $k => $t): $v = $k === "\t" ? 'tab' : $k; ?><option value="<?= $e($v) ?>" <?= $ajustes['separador'] === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select>
                </label>
            </div>

            <h3>Columnas del CSV</h3>
            <p class="texto-suave">Marca las que debe llevar. El orden es el de esta lista.</p>
            <div class="sm-columnas">
                <?php foreach (Simat::COLUMNAS as $k => $h): ?>
                    <label class="sm-check"><input type="checkbox" name="columnas[]" value="<?= $e($k) ?>" <?= in_array($k, $ajustes['columnas'], true) ? 'checked' : '' ?>> <?= $e($h) ?></label>
                <?php endforeach; ?>
            </div>

            <h3>Códigos de grado</h3>
            <p class="texto-suave">Serena reconoce Prejardín (-2), Jardín (-1), Transición (0) y 1 a 11 por el nombre. Si un grado no se reconoce (por ejemplo ciclos o aceleración), escribe su código; lo que pongas aquí manda sobre el detectado.</p>
            <div class="acad-tabla-envoltura">
                <table class="acad-tabla">
                    <thead><tr><th>Grado</th><th>Código detectado</th><th>Código a usar (opcional)</th></tr></thead>
                    <tbody>
                    <?php foreach ($grados as $g):
                        $manual = trim(configuracionObtener($pdo, 'simat_codigo_grado_' . (int) $g['id'], '', $colegioId));
                        $detectado = Simat::codigoGrado((string) $g['nombre']); ?>
                        <tr>
                            <td><?= $e($g['nombre']) ?></td>
                            <td><?= $detectado !== null ? $e($detectado) : '<span class="texto-suave">No reconocido</span>' ?></td>
                            <td><input type="text" name="codigo_grado[<?= (int) $g['id'] ?>]" value="<?= $e($manual) ?>" maxlength="3" size="4" inputmode="numeric"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="acad-acciones"><button type="submit" <?= $listo ? '' : 'disabled' ?>>Guardar ajustes</button></div>
        </form>
    </section>
<?php endif; ?>

<?php endif; ?>
<?php ob_end_flush(); } catch (Throwable $exv) { ob_end_clean(); echo smPanelError($exv, 'simat:' . $vista); } } ?>
</div>

<?php require __DIR__ . '/parciales/pie.php'; ?>
