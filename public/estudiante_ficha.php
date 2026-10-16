<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Expediente.php';
require_once __DIR__ . '/../src/modelos/HojaVida.php';
require_once __DIR__ . '/../src/modelos/Certificados.php';

// Hoja de vida del estudiante (estructura del Sistema Académico Master2000). DATOS SENSIBLES DE MENORES:
// cada pestaña y cada acción se validan con HojaVida::permisos() y el acceso al estudiante con
// Expediente::puedeAccederEstudiante(). Los permisos por rol están documentados en LEEME_HOJA_VIDA.txt.
requiereRol('estudiante', 'acudiente', 'docente', 'coordinador', 'rector', 'administrador', 'secretaria_academica', 'psicologo', 'orientador', 'secretaria', 'superadmin');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaConsulta();
if ($colegioId === null) {
    http_response_code(403);
    exit('No se pudo determinar el colegio.');
}
$cap = Expediente::capacidades($rol);
$e = static fn ($v): string => htmlspecialchars((string) $v);

$hijos = [];
if ($rol === 'estudiante') {
    $estId = $usuarioId;
} elseif ($rol === 'acudiente') {
    $hijos = Academico::hijos($pdo, $usuarioId);
    $estId = (int) ($_GET['est'] ?? $_POST['est'] ?? ($hijos[0]['id'] ?? 0));
} else {
    $estId = (int) ($_GET['est'] ?? $_POST['est'] ?? 0);
}
$permitido = $estId > 0 && Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, $estId, $colegioId);
$perm = $permitido ? HojaVida::permisos($pdo, $rol, $usuarioId, $estId, $colegioId) : null;

// Pestaña y subpestaña pedidas (si no tiene permiso para la pedida, se abre la primera que sí puede ver)
$seccion = (string) ($_GET['seccion'] ?? $_POST['seccion'] ?? '');
if ($perm !== null && !in_array($seccion, $perm['tabs'], true)) {
    $seccion = $perm['tabs'][0];
}
$sub = (string) ($_GET['sub'] ?? $_POST['sub'] ?? 'datos');
if (!isset(HojaVida::SUBTABS[$sub])) {
    $sub = 'datos';
}

// Filtro Año / Período (anecdotario, hoja de vida y procesos)
$anios = $permitido ? Academico::anios($pdo, $colegioId) : [];
$anioSel = null;
$anioPedido = (int) ($_GET['anio'] ?? $_POST['anio'] ?? 0);
foreach ($anios as $a) {
    if ((int) $a['id'] === $anioPedido) { $anioSel = $a; }
}
if ($anioSel === null) {
    foreach ($anios as $a) {
        if ((int) $a['actual'] === 1) { $anioSel = $a; }
    }
}
$anioSel ??= $anios[0] ?? null;
$periodos = $anioSel ? Academico::periodos($pdo, (int) $anioSel['id']) : [];
$periodoSel = (int) ($_GET['periodo'] ?? $_POST['periodo'] ?? 0);
if (!in_array($periodoSel, array_map(static fn (array $p): int => (int) $p['id'], $periodos), true)) {
    $periodoSel = 0;
}
$rango = $anioSel ? HojaVida::rango($anioSel, $periodos, $periodoSel) : ['0000-01-01', '9999-12-31'];

$flash = $_SESSION['flash_ficha'] ?? null;
unset($_SESSION['flash_ficha']);

// ---------------------------------------------------------------- Acciones (POST)
if ($permitido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    $denegado = 'No tienes permiso para esa acción.';
    $hoy = date('Y-m-d');
    $res = static function (string $err, string $ok) use (&$f): void {
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? $ok : $err;
    };

    switch ($accion) {
        case 'hv_guardar':
            $seccion = 'matricula';
            $res(HojaVida::guardarSub($pdo, $colegioId, $estId, $sub, $_POST, $perm, $usuarioId), 'Datos guardados.');
            break;
        case 'hv_ventana':
            $seccion = 'matricula'; $sub = 'datos';
            if (!$perm['ventana_admin']) { $f['error'] = $denegado; break; }
            $res(HojaVida::guardarVentana($pdo, $colegioId, trim((string) ($_POST['desde'] ?? '')), trim((string) ($_POST['hasta'] ?? ''))), 'Ventana de modificación actualizada.');
            break;
        case 'hv_familiar':
            $seccion = 'matricula'; $sub = 'familia';
            if (!$perm['edita_datos']) { $f['error'] = $denegado; break; }
            $res(HojaVida::guardarFamiliar($pdo, $estId, (string) ($_POST['parentesco'] ?? ''), $_POST, $usuarioId), 'Datos del familiar guardados.');
            break;
        case 'hv_previo_agregar':
        case 'hv_previo_eliminar':
            $seccion = 'matricula'; $sub = 'caracteristicas';
            if (!$perm['edita_datos']) { $f['error'] = $denegado; break; }
            if ($accion === 'hv_previo_agregar') {
                $res(HojaVida::agregarPrevio($pdo, $estId, $_POST), 'Establecimiento agregado.');
            } else {
                HojaVida::eliminarPrevio($pdo, (int) ($_POST['previo_id'] ?? 0), $estId);
                $f['exito'] = 'Registro eliminado.';
            }
            break;
        case 'contacto_agregar':
        case 'contacto_eliminar':
            $seccion = 'matricula'; $sub = 'salud';
            if (!$perm['edita_datos']) { $f['error'] = $denegado; break; }
            if ($accion === 'contacto_agregar') {
                $res(Expediente::agregarContacto($pdo, $estId, (string) ($_POST['c_nombre'] ?? ''), (string) ($_POST['c_parentesco'] ?? ''), (string) ($_POST['c_telefono'] ?? ''), isset($_POST['c_recoger'])), 'Contacto agregado.');
            } else {
                Expediente::eliminarContacto($pdo, (int) ($_POST['contacto_id'] ?? 0), $estId);
                $f['exito'] = 'Contacto eliminado.';
            }
            break;
        case 'dev_guardar':
            $seccion = 'desarrollo';
            if (!$perm['escribir_desarrollo']) { $f['error'] = $denegado; break; }
            $anioDev = Academico::anio($pdo, (int) ($_POST['anio_id'] ?? 0), $colegioId);
            if (!$anioDev) { $f['error'] = 'El año lectivo no existe.'; break; }
            $res(HojaVida::guardarDesarrollo($pdo, $estId, (int) $anioDev['id'], (int) ($_POST['semestre'] ?? 0), (array) ($_POST['d'] ?? []), $usuarioId), 'Desarrollo guardado.');
            break;
        case 'aprob_agregar':
        case 'aprob_eliminar':
            $seccion = 'aprobacion';
            if (!$perm['escribir_aprobacion']) { $f['error'] = $denegado; break; }
            if ($accion === 'aprob_agregar') {
                $res(HojaVida::agregarAprobacion($pdo, $colegioId, $estId, $_POST, $usuarioId), 'Aprobación anticipada registrada.');
            } else {
                HojaVida::eliminarAprobacion($pdo, (int) ($_POST['aprob_id'] ?? 0), $estId, $colegioId);
                $f['exito'] = 'Registro eliminado.';
            }
            break;
        case 'obsg_guardar':
            $seccion = 'observaciones';
            $campo = (string) ($_POST['campo'] ?? '');
            $okEscribe = $campo === 'temporales' ? $perm['escribir_obs_temporales'] : ($campo === 'certificados' ? $perm['escribir_obs_certificados'] : false);
            if (!$okEscribe) { $f['error'] = $denegado; break; }
            $res(HojaVida::guardarObservacionesGenerales($pdo, $estId, $campo, (string) ($_POST['texto'] ?? ''), $usuarioId), 'Observaciones guardadas.');
            break;
        case 'anot_agregar':
            $grupo = (string) ($_POST['grupo'] ?? '') === 'observador' ? 'observador' : 'anecdotario';
            $seccion = $grupo;
            if (!$cap['escribir_observador']) { $f['error'] = $denegado; break; }
            [$err, $nuevoId] = HojaVida::agregarAnotacion($pdo, $colegioId, $estId, $usuarioId, $grupo, $_POST, $cap['ver_obs_reservado']);
            $res($err, 'Anotación registrada.');
            if ($err === '' && $nuevoId > 0 && isset($_FILES['evidencia'])) {
                $errEv = HojaVida::subirEvidencia($pdo, $colegioId, $estId, 'observador', $nuevoId, $_FILES['evidencia'], $usuarioId);
                if ($errEv !== '') { $f['error'] = 'La anotación se guardó, pero la evidencia no: ' . $errEv; }
            }
            break;
        case 'anot_eliminar':
            $seccion = (string) ($_POST['grupo'] ?? '') === 'observador' ? 'observador' : 'anecdotario';
            if (!$cap['gestionar_convivencia']) { $f['error'] = $denegado; break; }
            HojaVida::eliminarAnotacion($pdo, (int) ($_POST['obs_id'] ?? 0), $estId, $colegioId);
            $f['exito'] = 'Anotación eliminada.';
            break;
        case 'anot_pasar':
            $seccion = (string) ($_POST['grupo'] ?? '') === 'observador' ? 'observador' : 'anecdotario';
            $destino = (string) ($_POST['destino'] ?? '');
            $obsId = (int) ($_POST['obs_id'] ?? 0);
            if ($destino === 'proceso') {
                if (!$perm['pasar_proceso']) { $f['error'] = $denegado; break; }
                $res(HojaVida::pasarAProceso($pdo, $colegioId, $estId, $obsId, $usuarioId, !$cap['gestionar_convivencia']), $cap['gestionar_convivencia'] ? 'Se abrió el proceso disciplinario.' : 'Reporte enviado a coordinación.');
            } elseif (in_array($destino, ['academica', 'convivencia'], true)) {
                if (!$perm['pasar_observador']) { $f['error'] = $denegado; break; }
                $res(HojaVida::pasarAObservador($pdo, $colegioId, $estId, $obsId, $destino), 'La anotación pasó a la hoja de vida (observador).');
            } else {
                $f['error'] = 'Destino no válido.';
            }
            break;
        case 'evid_agregar':
            $origen = (string) ($_POST['origen'] ?? '') === 'proceso' ? 'proceso' : 'observador';
            $seccion = $origen === 'proceso' ? 'procesos' : ((string) ($_POST['grupo'] ?? '') === 'observador' ? 'observador' : 'anecdotario');
            if (!($origen === 'proceso' ? $cap['gestionar_convivencia'] : $cap['escribir_observador'])) { $f['error'] = $denegado; break; }
            if (!isset($_FILES['evidencia']) || ($_FILES['evidencia']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { $f['error'] = 'Elige una imagen.'; break; }
            $res(HojaVida::subirEvidencia($pdo, $colegioId, $estId, $origen, (int) ($_POST['origen_id'] ?? 0), $_FILES['evidencia'], $usuarioId), 'Evidencia adjuntada.');
            break;
        case 'evid_eliminar':
            $origen = (string) ($_POST['origen'] ?? '') === 'proceso' ? 'proceso' : 'observador';
            $seccion = $origen === 'proceso' ? 'procesos' : ((string) ($_POST['grupo'] ?? '') === 'observador' ? 'observador' : 'anecdotario');
            if (!$cap['gestionar_convivencia']) { $f['error'] = $denegado; break; }
            HojaVida::eliminarEvidencia($pdo, (int) ($_POST['evid_id'] ?? 0), $estId, $colegioId);
            $f['exito'] = 'Evidencia eliminada.';
            break;
        case 'rev_agregar':
        case 'rev_eliminar':
            $seccion = 'observador';
            if (!$perm['revisar']) { $f['error'] = $denegado; break; }
            if ($accion === 'rev_agregar') {
                $res(HojaVida::agregarRevision($pdo, $colegioId, $estId, $usuarioId, (string) ($_POST['fecha'] ?? $hoy), (string) ($_POST['observaciones'] ?? '')), 'Revisión registrada.');
            } else {
                HojaVida::eliminarRevision($pdo, (int) ($_POST['rev_id'] ?? 0), $estId, $colegioId);
                $f['exito'] = 'Revisión eliminada.';
            }
            break;
        case 'proceso_abrir':
            $seccion = 'procesos';
            if (!$cap['gestionar_convivencia'] && !$cap['reportar_convivencia']) { $f['error'] = $denegado; break; }
            [$err, $nuevoId] = HojaVida::abrirProceso($pdo, $colegioId, $estId, $usuarioId, (string) ($_POST['fecha'] ?? $hoy), (string) ($_POST['situacion'] ?? ''), (string) ($_POST['descripcion'] ?? ''), (string) ($_POST['tratamiento'] ?? ''), !$cap['gestionar_convivencia']);
            $res($err, $cap['gestionar_convivencia'] ? 'Proceso abierto.' : 'Reporte enviado a coordinación.');
            if ($err === '' && $nuevoId > 0 && isset($_FILES['evidencia'])) {
                $errEv = HojaVida::subirEvidencia($pdo, $colegioId, $estId, 'proceso', $nuevoId, $_FILES['evidencia'], $usuarioId);
                if ($errEv !== '') { $f['error'] = 'El registro se guardó, pero la evidencia no: ' . $errEv; }
            }
            break;
        case 'actuacion_agregar':
        case 'proceso_estado':
        case 'proceso_pasar':
            $seccion = 'procesos';
            if (!$cap['gestionar_convivencia']) { $f['error'] = $denegado; break; }
            $p = Expediente::proceso($pdo, (int) ($_POST['proceso_id'] ?? 0), $colegioId);
            if (!$p || (int) $p['estudiante_id'] !== $estId) { $f['error'] = 'El proceso no existe.'; break; }
            if ($accion === 'actuacion_agregar') {
                $res(Expediente::agregarActuacion($pdo, (int) $p['id'], $usuarioId, (string) ($_POST['fecha'] ?? $hoy), (string) ($_POST['tipo'] ?? ''), (string) ($_POST['detalle'] ?? '')), 'Actuación registrada.');
            } elseif ($accion === 'proceso_pasar') {
                $res(HojaVida::pasarProceso($pdo, $colegioId, (int) $p['id'], (string) ($_POST['destino'] ?? ''), $usuarioId), 'Se registró el paso del proceso.');
            } else {
                $res(Expediente::cambiarEstadoProceso($pdo, (int) $p['id'], $colegioId, (string) ($_POST['estado'] ?? ''), (string) ($_POST['decision'] ?? ''), $usuarioId), 'Estado del proceso actualizado.');
            }
            break;
        case 'horas_agregar':
            $seccion = 'horas';
            if (!$cap['escribir_horas']) { $f['error'] = $denegado; break; }
            $res(Expediente::agregarHoras($pdo, $colegioId, $estId, $usuarioId, (string) ($_POST['tipo'] ?? ''), (string) ($_POST['fecha'] ?? $hoy), (float) str_replace(',', '.', (string) ($_POST['horas'] ?? '0')), (string) ($_POST['actividad'] ?? ''), (string) ($_POST['entidad'] ?? '')), 'Horas registradas.');
            break;
        case 'horas_eliminar':
            $seccion = 'horas';
            if (!$cap['escribir_horas'] || $rol === 'docente') { $f['error'] = $denegado; break; }
            Expediente::eliminarHoras($pdo, (int) ($_POST['hora_id'] ?? 0), $estId, $colegioId);
            $f['exito'] = 'Registro eliminado.';
            break;
        case 'apoyo_guardar':
            $seccion = 'apoyo';
            if (!$cap['escribir_apoyo']) { $f['error'] = $denegado; break; }
            $res(Expediente::guardarApoyo($pdo, $colegioId, $estId, $_POST, $usuarioId), 'Plan de apoyo guardado.');
            break;
        case 'apoyo_seguimiento':
            $seccion = 'apoyo';
            if (!$cap['escribir_apoyo']) { $f['error'] = $denegado; break; }
            $ap = Expediente::apoyo($pdo, $estId);
            if (!$ap) { $f['error'] = 'Primero crea el plan de apoyo.'; break; }
            $res(HojaVida::agregarSeguimiento($pdo, (int) $ap['id'], $usuarioId, (string) ($_POST['fecha'] ?? $hoy), (string) ($_POST['nota'] ?? ''), (string) ($_POST['observacion'] ?? '')), 'Seguimiento registrado.');
            break;
        default:
            $f['error'] = 'Acción no válida.';
    }
    if ($f['exito'] !== '' && $f['error'] === '') {
        // Se audita QUÉ se hizo y sobre quién, nunca el contenido.
        auditarAccion($pdo, 'expediente_' . $accion, 'usuarios', $estId, [], ['rol' => $rol, 'seccion' => $seccion]);
    }
    $_SESSION['flash_ficha'] = $f;
    $volver = 'estudiante_ficha.php?est=' . $estId . '&seccion=' . urlencode($seccion);
    if ($seccion === 'matricula') { $volver .= '&sub=' . urlencode($sub); }
    if (!empty($_POST['anio'])) { $volver .= '&anio=' . (int) $_POST['anio']; }
    if (!empty($_POST['periodo'])) { $volver .= '&periodo=' . (int) $_POST['periodo']; }
    header('Location: ' . $volver . '#contenido');
    exit;
}

// ---------------------------------------------------------------- Datos a mostrar
$est = $permitido ? Expediente::estudianteBasico($pdo, $estId, $colegioId) : null;
$esPersonal = $cap['personal'];
$ctx = $est !== null ? HojaVida::contexto($pdo, $estId, $colegioId) : [];
$vals = $est !== null ? HojaVida::valores($pdo, $estId) : [];
$verSensibleAlgo = $cap['salud_sensible'] || $cap['ver_convivencia'] || $cap['ver_apoyo_completo'];
if ($est !== null && $esPersonal && $verSensibleAlgo && $_SERVER['REQUEST_METHOD'] === 'GET') {
    auditarAccion($pdo, 'ver_expediente', 'usuarios', $estId, [], ['rol' => $rol, 'seccion' => $seccion]); // quién consultó a quién
}
$ultima = $est !== null ? HojaVida::ultimaModificacion($pdo, $estId) : null;
$hoyIso = date('Y-m-d');
try {
    $certificados = $est !== null ? Certificados::deEstudiante($pdo, $estId) : [];
} catch (PDOException $ex) {
    $certificados = []; // la migración 037 aún no se aplicó
}

$url = static fn (string $sec, array $extra = []): string => 'estudiante_ficha.php?' . http_build_query(['est' => $estId, 'seccion' => $sec] + $extra) . '#contenido';
/** Campos ocultos comunes de los formularios POST de esta página. */
$ocultos = static function (string $accion, array $extra = []) use ($estId, $seccion, $sub, $anioSel, $periodoSel): void {
    campoCSRF();
    $campos = ['accion' => $accion, 'est' => $estId, 'seccion' => $seccion, 'sub' => $sub, 'anio' => $anioSel ? (int) $anioSel['id'] : 0, 'periodo' => $periodoSel] + $extra;
    foreach ($campos as $k => $v) {
        echo '<input type="hidden" name="' . htmlspecialchars((string) $k) . '" value="' . htmlspecialchars((string) $v) . '">';
    }
};

$tituloPagina = 'Hoja de vida del estudiante';
$cssExtra = ['/assets/css/academico.css', '/assets/css/hoja_vida.css'];
require __DIR__ . '/parciales/encabezado.php';
define('HV_CARGADO', true);
?>

<h1>Hoja de vida del estudiante</h1>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error" role="alert"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito" role="status"><?= $e($flash['exito']) ?></p><?php endif; ?>

<?php if ($est === null): ?>
    <div class="estado-vacio"><p><?= $rol === 'acudiente' && !$hijos ? 'Todavía no tienes estudiantes vinculados.' : ($esPersonal ? 'Elige un estudiante desde <a href="' . $e(BASE_URL) . '/estudiantes.php">Estudiantes</a>. (Si ya lo hiciste, no tienes permiso para abrir a ese estudiante.)' : 'No tienes permiso para ver esta hoja de vida.') ?></p></div>
<?php else: ?>

<?php if ($rol === 'acudiente' && count($hijos) > 1): ?>
    <form action="estudiante_ficha.php" method="GET" class="acad-filtros no-print">
        <label>Estudiante
            <select name="est" onchange="this.form.submit()">
                <?php foreach ($hijos as $h): ?><option value="<?= (int) $h['id'] ?>" <?= $estId === (int) $h['id'] ? 'selected' : '' ?>><?= $e($h['nombre']) ?></option><?php endforeach; ?>
            </select>
        </label>
    </form>
<?php endif; ?>

<section class="hv-cabecera" aria-label="Datos del estudiante">
    <dl>
        <div><dt>Estudiante</dt><dd><?= $e($est['nombre']) ?></dd></div>
        <div><dt>Matrícula</dt><dd><?= $e($vals['numero_matricula'] ?? '') ?: '—' ?></dd></div>
        <?php if (!empty($ctx['codigo_matricula'])): ?><div><dt>Código</dt><dd><?= $e($ctx['codigo_matricula']) ?></dd></div><?php endif; ?>
        <div><dt>Grupo</dt><dd><?= $e($ctx['grupo']) ?></dd></div>
        <?php if ($perm['ver_datos_completos']): ?>
        <div><dt>Tipo y número de documento</dt><dd><?= $e(trim(($vals['tipo_documento'] ?? '') . ' ' . ($vals['numero_documento'] ?? ''))) ?: '—' ?></dd></div>
        <?php endif; ?>
    </dl>
    <?php if ($ultima && $esPersonal): ?>
        <p class="hv-ultima">Última modificación el <?= $e(date('d/m/Y H:i', strtotime((string) $ultima['en']))) ?><?= $ultima['nombre'] ? ' por ' . $e($ultima['nombre']) : '' ?></p>
    <?php endif; ?>
    <div class="acad-acciones no-print">
        <?php if ($esPersonal): ?><a class="boton-secundario" href="<?= $e(BASE_URL) ?>/estudiantes.php">← Estudiantes</a><?php endif; ?>
        <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/boletin.php?est=<?= $estId ?>">Boletín de notas</a>
    </div>
</section>

<nav class="hv-tabs no-print" aria-label="Secciones de la hoja de vida">
    <?php foreach ($perm['tabs'] as $t): ?>
        <a href="<?= $e($url($t)) ?>" <?= $t === $seccion ? 'class="activa" aria-current="page"' : '' ?>><?= $e(HojaVida::TABS[$t]) ?></a>
    <?php endforeach; ?>
</nav>

<div class="hv-contenido" id="contenido">
<?php
$parcial = __DIR__ . '/parciales/hv_' . $seccion . '.php';
if (preg_match('/^[a-z]+$/', $seccion) === 1 && is_file($parcial)) {
    require $parcial;
}
?>
</div>

<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
