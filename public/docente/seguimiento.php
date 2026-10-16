<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/helpers/fotosEstudiantes.php';
require_once __DIR__ . '/../../src/modelos/Evaluacion.php';

// Seguimiento (reemplaza a la antigua «Planilla de notas», ya eliminada): Período > Grupo > Asignatura > Evaluación,
// y la tabla de estudiantes con su escala valorativa y nota. A la derecha: foto, nota parcial y copiar nota.
// Las actividades y logros de la asignatura se crean y editan aquí mismo, en la sección «Actividades y logros».
requiereRol('docente', 'administrador', 'rector', 'secretaria_academica');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$esDocente = $rol === 'docente';

$anios = Academico::anios($pdo, $colegioId);
$anioSel = (int) ($_GET['anio'] ?? $_POST['anio'] ?? 0);
$anio = $anioSel > 0 ? Academico::anio($pdo, $anioSel, $colegioId) : Academico::anioActual($pdo, $colegioId);
if ($anio === null && $anios) {
    $anio = $anios[0];
}
$config = Evaluacion::configuracion($pdo, $colegioId);
$estructura = $config['estructura'];
$escala = Evaluacion::escalaActiva($pdo, $colegioId);
$usaProc = in_array($estructura, ['procesos', 'procesos_logros', 'logros_procesos'], true);
$usaLogr = in_array($estructura, ['logros', 'procesos_logros', 'logros_procesos'], true);

$asigs = $anio ? Evaluacion::asignaciones($pdo, $colegioId, (int) $anio['id'], $esDocente ? $usuarioId : null) : [];
$periodos = $anio ? Academico::periodos($pdo, (int) $anio['id']) : [];

// --- Período (por defecto el que está en curso) ---
$periodoId = (int) ($_GET['periodo'] ?? $_POST['periodo'] ?? 0);
$periodo = null;
foreach ($periodos as $p) {
    if ((int) $p['id'] === $periodoId) {
        $periodo = $p;
    }
}
if ($periodo === null && $periodos) {
    $hoy = date('Y-m-d');
    $periodo = $periodos[0];
    foreach ($periodos as $p) {
        if ($p['fecha_inicio'] <= $hoy && $hoy <= $p['fecha_fin']) {
            $periodo = $p;
        }
    }
    $periodoId = (int) $periodo['id'];
}

// --- Grupo y asignatura (solo del propio alcance) ---
$grupoId = (int) ($_GET['grupo'] ?? $_POST['grupo'] ?? 0);
$asigId = (int) ($_GET['asig'] ?? $_POST['asig'] ?? 0);
$grupos = [];
foreach ($asigs as $a) {
    $grupos[(int) $a['grupo_id']] = $a['grado'] . ' - ' . $a['grupo'];
}
$asig = null;
foreach ($asigs as $a) {
    if ((int) $a['id'] === $asigId) {
        $asig = $a;
    }
}
if ($asig !== null) {
    if ($grupoId === 0) {
        $grupoId = (int) $asig['grupo_id'];       // llegó con la asignatura (p. ej. desde Actividades y logros)
    } elseif ($grupoId !== (int) $asig['grupo_id']) {
        $asig = null;                              // la asignatura no es del grupo elegido
        $asigId = 0;
    }
}
if (!isset($grupos[$grupoId])) {
    $grupoId = 0;
    $asig = null;
    $asigId = 0;
}
$asigsGrupo = array_values(array_filter($asigs, static fn (array $a): bool => (int) $a['grupo_id'] === $grupoId));

// --- Evaluación a mostrar: «p» = nota del período, o el id de una actividad ---
$actividades = ($asig && $periodo) ? Evaluacion::actividades($pdo, (int) $asig['id'], $periodoId) : [];
$evalSel = (string) ($_GET['eval'] ?? $_POST['eval'] ?? 'p');
$actividadSel = null;
if ($evalSel !== 'p') {
    foreach ($actividades as $a) {
        if ((string) $a['id'] === $evalSel) {
            $actividadSel = $a;
        }
    }
    if ($actividadSel === null) {
        $evalSel = 'p';
    }
}

$estudiantes = ($asig && $periodo && $anio) ? Evaluacion::estudiantesDeGrupo($pdo, $colegioId, $anio, (int) $asig['grupo_id']) : [];
$selId = (int) ($_GET['sel'] ?? $_POST['sel'] ?? 0);

$parametros = static fn (int $sel = 0): array => array_filter([
    'anio' => $anio ? (int) $anio['id'] : 0,
    'periodo' => $periodoId,
    'grupo' => $grupoId,
    'asig' => $asig ? (int) $asig['id'] : 0,
    'eval' => $asig ? $evalSel : '',
    'sel' => $sel,
], static fn ($v): bool => $v !== 0 && $v !== '');

$flash = $_SESSION['flash_seguimiento'] ?? null;
unset($_SESSION['flash_seguimiento']);

// ---------------------------------------------------------------- POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => '', 'detalle' => []];
    $selDestino = (int) ($_POST['sel'] ?? 0);

    if ($asig === null || $periodo === null || !$anio) {
        $f['error'] = 'Elige un período, un grupo y una asignatura válidos.';
    } elseif ($accion === 'confirmar') {
        if ($escala === null) {
            $f['error'] = 'El colegio todavía no tiene una escala de evaluación en uso.';
        } else {
            $entrada = [];
            foreach ((array) ($_POST['nota'] ?? []) as $sid => $porActividad) {
                // Solo se aceptan notas de la actividad que se está viendo; el resto de la planilla no se toca.
                if ($actividadSel !== null && is_array($porActividad) && array_key_exists((string) $actividadSel['id'], $porActividad)) {
                    $entrada[(int) $sid] = [(int) $actividadSel['id'] => $porActividad[(string) $actividadSel['id']]];
                }
            }
            // Siempre se pasan TODAS las actividades del período: así la nota del período se recalcula completa.
            $r = Evaluacion::guardarPlanilla($pdo, $asig, $periodoId, $estructura, $escala, $estudiantes, $actividades, $entrada, (array) ($_POST['obs'] ?? []));
            $f['exito'] = "Seguimiento guardado ({$r['guardadas']} nota(s)). La nota del período se recalculó.";
            $f['detalle'] = array_slice($r['errores'], 0, 30);
            auditarAccion($pdo, 'guardar_planilla_notas', 'asignaciones_academicas', (int) $asig['id'], [], ['periodo_id' => $periodoId, 'notas' => $r['guardadas'], 'rechazadas' => count($r['errores']), 'origen' => 'seguimiento', 'publicado' => (int) ($periodo['boletin_publicado'] ?? 0)]);
        }
    } elseif (in_array($accion, ['logro_guardar', 'logro_eliminar', 'actividad_guardar', 'actividad_eliminar'], true)) {
        // Actividades y logros (antes en planilla.php). Siempre se recalcula la nota del período al cambiar actividades.
        if ($escala === null) {
            $f['error'] = 'El colegio todavía no tiene una escala de evaluación en uso.';
        } else {
            $recalcular = static function () use ($pdo, $asig, $periodoId, $estructura, $escala, $colegioId, $anio): void {
                Evaluacion::recalcularPeriodo($pdo, $asig, $periodoId, $estructura, $escala,
                    Evaluacion::estudiantesDeGrupo($pdo, $colegioId, $anio, (int) $asig['grupo_id']),
                    Evaluacion::actividades($pdo, (int) $asig['id'], $periodoId));
            };
            if ($accion === 'logro_guardar') {
                $err = Evaluacion::guardarLogro($pdo, (int) $asig['id'], $periodoId, (int) ($_POST['logro_id'] ?? 0) ?: null, (string) ($_POST['descripcion'] ?? ''), (float) str_replace(',', '.', (string) ($_POST['peso'] ?? '0')));
                $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Logro guardado.' : $err;
                if ($err === '') {
                    $recalcular();
                }
            } elseif ($accion === 'logro_eliminar') {
                Evaluacion::eliminarLogro($pdo, (int) ($_POST['logro_id'] ?? 0), (int) $asig['id']);
                $recalcular();
                $f['exito'] = 'Logro eliminado.';
            } elseif ($accion === 'actividad_guardar') {
                $err = Evaluacion::guardarActividad($pdo, $colegioId, (int) $asig['id'], $periodoId, (int) ($_POST['actividad_id'] ?? 0) ?: null, $_POST, $estructura);
                $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Actividad guardada.' : $err;
                if ($err === '') {
                    $recalcular();
                }
            } else {
                Evaluacion::eliminarActividad($pdo, (int) ($_POST['actividad_id'] ?? 0), (int) $asig['id']);
                $recalcular();
                $f['exito'] = 'Actividad eliminada y notas recalculadas.';
            }
            if ($f['error'] === '') {
                auditarAccion($pdo, $accion, 'asignaciones_academicas', (int) $asig['id'], [], ['periodo_id' => $periodoId, 'origen' => 'seguimiento']);
            }
        }
    } elseif ($accion === 'foto_subir' || $accion === 'foto_quitar') {
        $eid = (int) ($_POST['estudiante_id'] ?? 0);
        $pertenece = false;
        foreach ($estudiantes as $s) {
            if ((int) $s['id'] === $eid) {
                $pertenece = true;
            }
        }
        if (!$pertenece) {
            $f['error'] = 'Ese estudiante no está en este grupo.';
        } elseif ($accion === 'foto_subir') {
            try {
                guardarFotoEstudiante($pdo, $eid, $_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
                $f['exito'] = 'Foto guardada.';
                auditarAccion($pdo, 'foto_estudiante_subir', 'usuarios', $eid, [], ['origen' => 'seguimiento']);
            } catch (RuntimeException $ex) {
                $f['error'] = $ex->getMessage();
            }
        } else {
            eliminarFotoEstudiante($pdo, $eid);
            $f['exito'] = 'Foto eliminada.';
            auditarAccion($pdo, 'foto_estudiante_quitar', 'usuarios', $eid, [], ['origen' => 'seguimiento']);
        }
        $selDestino = $eid;
    }
    if (($_POST['ajax'] ?? '') === '1') {   // dictado por voz: se guarda sin recargar la página
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $f['error'] === '', 'mensaje' => $f['error'] !== '' ? $f['error'] : $f['exito'], 'errores' => $f['detalle']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['flash_seguimiento'] = $f;
    header('Location: seguimiento.php?' . http_build_query($parametros($selDestino)));
    exit;
}

// ---------------------------------------------------------------- Datos para pintar
$puedeConfigurar = in_array($rol, ['administrador', 'rector', 'secretaria_academica'], true);
$componentes = $usaProc ? Evaluacion::componentes($pdo, $colegioId) : [];
$logros = ($asig && $periodo) ? Evaluacion::logros($pdo, (int) $asig['id'], $periodoId) : [];
$sumaPesos = array_sum(array_map(static fn (array $a): float => (float) $a['peso'], $actividades));
$califs = Evaluacion::calificaciones($pdo, array_map(static fn (array $a): int => (int) $a['id'], $actividades));
$notasPeriodo = ($asig && $periodo) ? Evaluacion::notasPeriodo($pdo, (int) $asig['id'], $periodoId) : [];
$niveles = [];
$nivelesPorId = [];
foreach (($escala['niveles'] ?? []) as $n) {
    $nivelesPorId[(int) $n['id']] = $n;
    $niveles[] = ['id' => (int) $n['id'], 'nombre' => (string) $n['nombre'], 'abrev' => (string) $n['abreviatura'],
                  'desde' => (float) $n['desde'], 'hasta' => (float) $n['hasta'], 'equiv' => (float) $n['valor_equivalente']];
}
$esDesc = $escala !== null && $escala['tipo'] === 'descriptiva';
$dec = $escala ? (int) $escala['decimales'] : 1;
$paso = $dec > 0 ? '0.' . str_repeat('0', $dec - 1) . '1' : '1';
$fmt = static fn ($v): string => $v === null ? '' : number_format((float) $v, $dec, '.', '');

$fotos = [];
$documentos = [];
$codigos = [];
if ($estudiantes) {
    $in = implode(',', array_map(static fn (array $s): int => (int) $s['id'], $estudiantes));
    foreach ($pdo->query("SELECT id, foto FROM usuarios WHERE id IN ({$in})") as $r) {
        $fotos[(int) $r['id']] = (string) ($r['foto'] ?? '');
    }
    // Documento de identidad: de la hoja de vida; si no está, de la última inscripción.
    foreach ($pdo->query("SELECT u.id,
                                 COALESCE(NULLIF(h.tipo_documento, ''), (SELECT i.tipo_documento FROM inscripciones i WHERE i.estudiante_id = u.id ORDER BY i.id DESC LIMIT 1)) AS tipo,
                                 COALESCE(NULLIF(h.numero_documento, ''), (SELECT i.numero_documento FROM inscripciones i WHERE i.estudiante_id = u.id ORDER BY i.id DESC LIMIT 1)) AS numero
                            FROM usuarios u LEFT JOIN hojas_vida h ON h.estudiante_id = u.id WHERE u.id IN ({$in})") as $r) {
        $documentos[(int) $r['id']] = trim((string) ($r['tipo'] ?? '') . ' ' . (string) ($r['numero'] ?? ''));
    }
    // Código = número de matrícula (consecutivo fijo por colegio). Se asigna solo a quien aún no lo tenga.
    if (method_exists('Academico', 'asegurarCodigosMatricula')) {
        Academico::asegurarCodigosMatricula($pdo, $colegioId);
    }
    try {
        foreach ($pdo->query("SELECT id, codigo_matricula FROM usuarios WHERE id IN ({$in})") as $r) {
            $codigos[(int) $r['id']] = $r['codigo_matricula'] !== null ? (int) $r['codigo_matricula'] : null;
        }
    } catch (Throwable $ex) {
        // migración 038 pendiente: se muestra el id como respaldo
    }
}

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$modoPeriodo = $actividadSel === null;
$puedeEditar = !$modoPeriodo && $escala !== null;

$cfgJs = [
    'tipo' => $escala['tipo'] ?? 'numerica', 'min' => (float) ($escala['valor_min'] ?? 1), 'max' => (float) ($escala['valor_max'] ?? 5),
    'dec' => $dec, 'niveles' => $niveles, 'editable' => $puedeEditar, 'selInicial' => $selId,
];

$tituloPagina = 'Seguimiento';
$cssExtra = ['/assets/css/academico.css', '/assets/css/seguimiento.css'];
$jsExtra = ['/assets/js/seguimiento.js'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Seguimiento</h1>
<?php if ($anio): ?><p class="texto-suave">Año lectivo <?= (int) $anio['anio'] ?><?php if ($asig && $periodo): ?> · <?= $e($asig['grado'] . ' ' . $asig['grupo']) ?> · <?= $e($asig['asignatura']) ?> · <?= $e($periodo['nombre']) ?><?php endif; ?></p><?php endif; ?>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if ($flash && !empty($flash['detalle'])): ?>
    <div class="mensaje mensaje-aviso"><strong>Algunas notas no se guardaron:</strong>
        <ul class="lista-simple"><?php foreach ($flash['detalle'] as $d): ?><li><?= $e($d) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($escala === null): ?>
    <p class="mensaje mensaje-aviso">El colegio todavía no tiene una escala de evaluación en uso.
        <?php if (!$esDocente): ?><a href="<?= $e(BASE_URL) ?>/administrador/evaluacion_config.php">Configurarla ahora</a>.<?php else: ?>Pídele al administrador que la configure.<?php endif; ?></p>
<?php elseif (!$anio): ?>
    <p class="mensaje mensaje-aviso">No hay un año lectivo configurado.</p>
<?php elseif (!$asigs): ?>
    <div class="estado-vacio"><p><?= $esDocente ? 'Todavía no tienes asignaturas asignadas en este año. Pídele al administrador que te asigne tus grupos en Plan académico.' : 'No hay asignaciones académicas en este año: créalas en Plan académico.' ?></p></div>
<?php else: ?>

<!-- Barra de acciones: Actualizar · Imprimir · Confirmar -->
<div class="seg-barra">
    <a class="seg-accion" href="seguimiento.php?<?= $e(http_build_query($parametros())) ?>" title="Volver a cargar los datos guardados">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.65 6.35A7.96 7.96 0 0 0 12 4a8 8 0 1 0 7.73 10h-2.08A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4z"/></svg>
        <span>Actualizar</span>
    </a>
    <button type="button" class="seg-accion" onclick="window.print()" title="Imprimir la tabla">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 8H5a3 3 0 0 0-3 3v6h4v4h12v-4h4v-6a3 3 0 0 0-3-3zm-3 11H8v-5h8v5zm3-7a1 1 0 1 1 0-2 1 1 0 0 1 0 2zM18 3H6v4h12V3z"/></svg>
        <span>Imprimir</span>
    </button>
    <button type="submit" form="seg-form" class="seg-accion" id="seg-confirmar" <?= $asig && $periodo ? '' : 'disabled' ?> title="Guardar los cambios">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
        <span>Confirmar</span>
    </button>
</div>

<!-- Selectores: Período > Grupo > Asignatura (> Evaluación). Al cambiar uno, la tabla se recarga sola. -->
<form action="seguimiento.php" method="GET" class="seg-filtros" id="seg-filtros">
    <?php if (count($anios) > 1): ?>
        <label>Año
            <select name="anio" data-auto data-limpiar="periodo:0,grupo:0,asig:0,eval:p">
                <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $anio['id'] === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?></option><?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>
    <label>Período
        <select name="periodo" data-auto>
            <?php foreach ($periodos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $periodoId === (int) $p['id'] ? 'selected' : '' ?>><?= $e($p['nombre']) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Grupo
        <select name="grupo" data-auto data-limpiar="asig:0,eval:p">
            <option value="0">Elige…</option>
            <?php foreach ($grupos as $gid => $gn): ?><option value="<?= $gid ?>" <?= $grupoId === $gid ? 'selected' : '' ?>><?= $e($gn) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Asignatura
        <select name="asig" data-auto data-limpiar="eval:p" <?= $grupoId ? '' : 'disabled' ?>>
            <option value="0">Elige…</option>
            <?php foreach ($asigsGrupo as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $asigId === (int) $a['id'] ? 'selected' : '' ?>><?= $e($a['asignatura']) ?></option><?php endforeach; ?>
        </select>
    </label>
    <?php if ($asig && $periodo): ?>
    <label>Evaluación
        <select name="eval" data-auto>
            <option value="p" <?= $modoPeriodo ? 'selected' : '' ?>>Evaluación de período</option>
            <?php foreach ($actividades as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $actividadSel && (int) $actividadSel['id'] === (int) $a['id'] ? 'selected' : '' ?>><?= $e($a['nombre']) ?><?= (float) $a['peso'] > 0 ? ' (' . $e(rtrim(rtrim(number_format((float) $a['peso'], 2, '.', ''), '0'), '.')) . ' %)' : '' ?></option><?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>
    <noscript><button type="submit">Cargar</button></noscript>
    <?php if ($asig && $periodo): ?>
        <span class="seg-enlaces">
            <a href="#seg-actividades">Actividades y logros</a>
        </span>
    <?php endif; ?>
</form>
<?php if (!$periodos): ?><p class="mensaje mensaje-aviso">Este año no tiene períodos. Créalos en Años lectivos.</p><?php endif; ?>

<?php if (!$asig || !$periodo): ?>
    <p class="texto-suave"><?= $grupoId ? 'Elige la asignatura para cargar los estudiantes.' : 'Elige el período, el grupo y la asignatura para cargar los estudiantes.' ?></p>
<?php else: ?>

    <?php if ((int) ($periodo['boletin_publicado'] ?? 0) === 1): ?>
        <p class="texto-suave"><span class="estado-pill estado-activa">Boletín publicado</span> los cambios se reflejan de inmediato para estudiantes y familias.</p>
    <?php endif; ?>
    <?php if (!$actividades): ?>
        <p class="mensaje mensaje-aviso">Esta asignatura aún no tiene actividades en este período. Créalas abajo, en <a href="#seg-actividades">Actividades y logros</a>, para poder calificar.</p>
    <?php endif; ?>

    <script type="application/json" id="seg-config"><?= json_encode($cfgJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

    <!-- Dictado de notas: «Juan Pérez 4.5» -->
    <section class="seg-voz" id="seg-voz" aria-label="Dictado de notas" <?= $puedeEditar ? '' : 'data-bloqueado="1"' ?>>
        <div class="seg-voz-fila">
            <button type="button" id="seg-voz-btn" class="seg-voz-btn" <?= $puedeEditar ? '' : 'disabled' ?>>🎤 Dictar notas</button>
            <input type="text" id="seg-voz-texto" class="seg-voz-texto" placeholder="Ej.: Juan Pérez 4.5" maxlength="200" autocomplete="off" data-sin-voz <?= $puedeEditar ? '' : 'disabled' ?> aria-label="Escribir una nota con el nombre del estudiante">
            <button type="button" id="seg-voz-aplicar" class="boton-secundario" <?= $puedeEditar ? '' : 'disabled' ?>>Aplicar</button>
            <label class="seg-check"><input type="checkbox" id="seg-voz-auto" checked <?= $puedeEditar ? '' : 'disabled' ?>> Guardar cada nota al reconocerla</label>
        </div>
        <p class="seg-ayuda"><?= $puedeEditar
            ? ($esDesc ? 'Di el nombre y el desempeño, por ejemplo: «Juan Pérez alto». Puedes dictar varios seguidos.' : 'Di el nombre y la nota, por ejemplo: «Juan Pérez 4.5» o «María López cuatro punto dos». Puedes dictar varios seguidos.')
            : 'Para dictar notas, elige una actividad en «Evaluación».' ?></p>
        <ul class="seg-voz-log" id="seg-voz-log" aria-live="polite"></ul>
    </section>

    <div class="seg-cuerpo">
        <!-- Tabla de estudiantes -->
        <div class="seg-izquierda">
            <form action="seguimiento.php" method="POST" id="seg-form" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="confirmar">
                <input type="hidden" name="anio" value="<?= (int) $anio['id'] ?>"><input type="hidden" name="periodo" value="<?= $periodoId ?>">
                <input type="hidden" name="grupo" value="<?= $grupoId ?>"><input type="hidden" name="asig" value="<?= (int) $asig['id'] ?>">
                <input type="hidden" name="eval" value="<?= $e($evalSel) ?>"><input type="hidden" name="sel" value="<?= $selId ?>" class="seg-sel-actual">
                <div class="seg-tabla-caja">
                    <table class="seg-tabla" id="seg-tabla">
                        <thead>
                            <tr><th class="seg-c-codigo">Código</th><th class="seg-c-doc">Documento</th><th>Estudiante</th><th class="seg-c-escala">Escala valorativa</th><th class="seg-c-nota">Nota</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($estudiantes as $s):
                            $sid = (int) $s['id'];
                            $np = $notasPeriodo[$sid] ?? null;
                            if ($modoPeriodo) {
                                $valor = $np && $np['valor'] !== null ? (float) $np['valor'] : null;
                                $nivelId = $np && $np['nivel_id'] !== null ? (int) $np['nivel_id'] : null;
                                $fechaEval = $np && !empty($np['calculado_en']) ? date('d/m/Y', strtotime((string) $np['calculado_en'])) : '';
                            } else {
                                $c = $califs[$sid][(int) $actividadSel['id']] ?? null;
                                $valor = $c && $c['valor'] !== null ? (float) $c['valor'] : null;
                                $nivelId = $c && $c['nivel_id'] !== null ? (int) $c['nivel_id'] : null;
                                $fechaEval = !empty($actividadSel['fecha']) ? date('d/m/Y', strtotime((string) $actividadSel['fecha'])) : '';
                            }
                            $nivel = $nivelId !== null ? ($nivelesPorId[$nivelId] ?? null) : null;
                            $textoNota = $valor === null ? '' : ($esDesc ? ($nivel['abreviatura'] ?? $fmt($valor)) : $fmt($valor));
                            $fotoNombre = $fotos[$sid] ?? '';
                        ?>
                            <tr data-sid="<?= $sid ?>" data-nombre="<?= $e(mb_strtoupper((string) $s['nombre'])) ?>"
                                data-foto="<?= $e('foto_estudiante.php?id=' . $sid . '&v=' . rawurlencode($fotoNombre)) ?>" data-tiene-foto="<?= $fotoNombre !== '' ? '1' : '0' ?>"
                                data-fecha="<?= $e($fechaEval) ?>" tabindex="0">
                                <td class="seg-c-codigo"><?= $e(str_pad((string) ($codigos[$sid] ?? $sid), 6, '0', STR_PAD_LEFT)) ?></td>
                                <td class="seg-c-doc"><?= $e($documentos[$sid] ?? '') ?></td>
                                <td><?= $e(mb_strtoupper((string) $s['nombre'])) ?></td>
                                <td class="seg-c-escala" data-celda="escala"><?= $e($nivel['nombre'] ?? '') ?></td>
                                <td class="seg-c-nota" data-celda="nota"><?= $e($textoNota) ?></td>
                                <?php if ($puedeEditar): ?>
                                    <td class="seg-oculto"><input type="hidden" data-nota name="nota[<?= $sid ?>][<?= (int) $actividadSel['id'] ?>]" value="<?= $e($esDesc ? ($nivelId ?? '') : $fmt($valor)) ?>"></td>
                                <?php endif; ?>
                                <td class="seg-oculto"><input type="hidden" data-obs name="obs[<?= $sid ?>]" value="<?= $e($np['observacion'] ?? '') ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <p class="seg-total">Total registros: <strong><?= count($estudiantes) ?></strong></p>
            <?php if (!$estudiantes): ?><p class="texto-suave">Este grupo no tiene estudiantes con matrícula activa en <?= (int) $anio['anio'] ?>.</p><?php endif; ?>
        </div>

        <!-- Panel derecho: evaluación del estudiante elegido -->
        <aside class="seg-panel" id="seg-panel" aria-live="polite">
            <h2 class="seg-panel-titulo">Evaluación</h2>
            <div class="seg-panel-eval">
                <div class="seg-foto-caja">
                    <img id="seg-foto" src="" alt="Foto del estudiante" width="120" height="120">
                </div>
                <div class="seg-panel-datos">
                    <p class="seg-nombre" id="seg-nombre">Elige un estudiante</p>
                    <label class="seg-dato">Nota parcial
                        <?php if ($puedeEditar && $esDesc): ?>
                            <select id="seg-nota" disabled>
                                <option value="">—</option>
                                <?php foreach ($niveles as $n): ?><option value="<?= (int) $n['id'] ?>"><?= $e($n['abrev'] . ' · ' . $n['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="<?= $puedeEditar ? 'number' : 'text' ?>" id="seg-nota" <?= $puedeEditar ? 'min="' . $e($escala['valor_min']) . '" max="' . $e($escala['valor_max']) . '" step="' . $e($paso) . '" inputmode="decimal"' : 'readonly' ?> disabled>
                        <?php endif; ?>
                    </label>
                    <p class="seg-dato">Fecha de evaluación <strong id="seg-fecha">—</strong></p>
                    <?php if ($modoPeriodo): ?><p class="seg-ayuda">La nota del período se calcula sola con las actividades. Para calificar, elige una actividad en «Evaluación».</p><?php endif; ?>
                </div>
            </div>

            <h2 class="seg-panel-titulo">Foto del estudiante</h2>
            <form action="seguimiento.php" method="POST" enctype="multipart/form-data" class="seg-foto-form" id="seg-foto-form">
                <?php campoCSRF(); ?>
                <input type="hidden" name="anio" value="<?= (int) $anio['id'] ?>"><input type="hidden" name="periodo" value="<?= $periodoId ?>">
                <input type="hidden" name="grupo" value="<?= $grupoId ?>"><input type="hidden" name="asig" value="<?= (int) $asig['id'] ?>">
                <input type="hidden" name="eval" value="<?= $e($evalSel) ?>">
                <input type="hidden" name="estudiante_id" value="0" class="seg-estudiante-id"><input type="hidden" name="sel" value="0" class="seg-estudiante-id">
                <input type="file" name="foto" id="seg-foto-archivo" accept="image/jpeg,image/png,image/webp,image/gif" disabled>
                <div class="seg-foto-botones">
                    <button type="submit" name="accion" value="foto_subir" id="seg-foto-subir" disabled>Subir foto</button>
                    <button type="submit" name="accion" value="foto_quitar" id="seg-foto-quitar" class="boton-secundario" disabled onclick="return confirm('¿Quitar la foto de este estudiante?');">Quitar</button>
                </div>
                <p class="seg-ayuda">Se reduce automáticamente (máx. 480 px) para ahorrar espacio en el hosting.</p>
            </form>

            <h2 class="seg-panel-titulo">Copiar nota</h2>
            <div class="seg-copiar">
                <label class="seg-dato">Nota
                    <?php if ($esDesc): ?>
                        <select id="seg-copia" <?= $puedeEditar ? '' : 'disabled' ?>><option value="">—</option><?php foreach ($niveles as $n): ?><option value="<?= (int) $n['id'] ?>"><?= $e($n['abrev'] . ' · ' . $n['nombre']) ?></option><?php endforeach; ?></select>
                    <?php else: ?>
                        <input type="number" id="seg-copia" inputmode="decimal" <?= $puedeEditar ? 'min="' . $e($escala['valor_min']) . '" max="' . $e($escala['valor_max']) . '" step="' . $e($paso) . '"' : 'disabled' ?>>
                    <?php endif; ?>
                </label>
                <label class="seg-check"><input type="checkbox" id="seg-copia-vacios" checked <?= $puedeEditar ? '' : 'disabled' ?>> Solo a quienes no tienen nota</label>
                <button type="button" id="seg-copiar" class="boton-secundario" <?= $puedeEditar ? '' : 'disabled' ?>>Copiar a todos</button>
                <p class="seg-ayuda">Rellena la tabla; luego pulsa «Confirmar» para guardar.</p>
            </div>

            <h2 class="seg-panel-titulo">Observación del período</h2>
            <label class="seg-dato"><span class="seg-solo-lector">Observación</span>
                <textarea id="seg-obs" rows="3" maxlength="500" disabled placeholder="Opcional"></textarea>
            </label>
        </aside>
    </div>

    <!-- Actividades y logros de la asignatura (antes en la página «Planilla»): cada actividad es una opción de «Evaluación». -->
    <?php
    $ocultosSeg = static function (string $accion, array $extra = []) use ($anio, $periodoId, $grupoId, $asig, $evalSel, $e): void {
        campoCSRF();
        $base = ['accion' => $accion, 'anio' => (int) $anio['id'], 'periodo' => $periodoId, 'grupo' => $grupoId, 'asig' => (int) $asig['id'], 'eval' => $evalSel] + $extra;
        foreach ($base as $k => $v) {
            echo '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">';
        }
    };
    $pesoTxt = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    ?>
    <section class="panel-seccion" id="seg-actividades">
        <h2>Actividades y logros</h2>
        <p class="texto-suave">Estructura del colegio: <strong><?= $e(Evaluacion::ESTRUCTURAS[$estructura][0] ?? $estructura) ?></strong>. Cada actividad aparece como opción en «Evaluación» para calificarla. Peso 0 en todas = todas valen igual.</p>

        <?php if ($usaProc && !$componentes): ?>
            <p class="mensaje mensaje-aviso">Esta estructura usa procesos, pero el colegio no ha definido ninguno. <?php if ($puedeConfigurar): ?><a href="<?= $e(BASE_URL) ?>/administrador/evaluacion_config.php">Definirlos</a>.<?php endif; ?></p>
        <?php endif; ?>

        <?php if ($usaLogr): ?>
            <h3>Logros del período</h3>
            <?php foreach ($logros as $l): ?>
                <div class="acad-acciones">
                    <form action="seguimiento.php" method="POST" novalidate>
                        <?php $ocultosSeg('logro_guardar', ['logro_id' => (int) $l['id']]); ?>
                        <label style="flex:1 1 320px">Logro <textarea name="descripcion" rows="2" maxlength="500" style="width:100%"><?= $e($l['descripcion']) ?></textarea></label>
                        <label>Peso % <input type="number" name="peso" min="0" max="100" step="0.01" value="<?= $e($l['peso']) ?>" style="width:6em"></label>
                        <button type="submit">Guardar</button>
                    </form>
                    <form action="seguimiento.php" method="POST" onsubmit="return confirm('¿Eliminar este logro? Sus actividades quedarán sin logro.');">
                        <?php $ocultosSeg('logro_eliminar', ['logro_id' => (int) $l['id']]); ?>
                        <button type="submit" class="boton-peligro">Quitar</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <details <?= $logros ? '' : 'open' ?>>
                <summary>Agregar un logro</summary>
                <form action="seguimiento.php" method="POST" class="acad-filtros" novalidate>
                    <?php $ocultosSeg('logro_guardar'); ?>
                    <label style="flex:1 1 320px">Descripción del logro <textarea name="descripcion" rows="2" maxlength="500" required style="width:100%"></textarea></label>
                    <label>Peso % (0 = todos iguales) <input type="number" name="peso" min="0" max="100" step="0.01" value="0" style="width:6em"></label>
                    <button type="submit">Agregar logro</button>
                </form>
            </details>
        <?php endif; ?>

        <h3>Actividades del período</h3>
        <?php foreach ($actividades as $a): ?>
            <div class="acad-acciones">
                <form action="seguimiento.php" method="POST" novalidate>
                    <?php $ocultosSeg('actividad_guardar', ['actividad_id' => (int) $a['id']]); ?>
                    <label>Nombre <input type="text" name="nombre" maxlength="100" value="<?= $e($a['nombre']) ?>"></label>
                    <label>Peso % <input type="number" name="peso" min="0" max="100" step="0.01" value="<?= $e($a['peso']) ?>" style="width:6em"></label>
                    <?php if ($usaProc): ?>
                        <label>Proceso <select name="componente_id"><option value="0">—</option><?php foreach ($componentes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $a['componente_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= $e($c['nombre']) ?></option><?php endforeach; ?></select></label>
                    <?php endif; ?>
                    <?php if ($usaLogr): ?>
                        <label>Logro <select name="logro_id"><option value="0">—</option><?php foreach ($logros as $i => $l): ?><option value="<?= (int) $l['id'] ?>" <?= (int) $a['logro_id'] === (int) $l['id'] ? 'selected' : '' ?>>Logro <?= $i + 1 ?></option><?php endforeach; ?></select></label>
                    <?php endif; ?>
                    <label>Fecha <input type="date" name="fecha" value="<?= $e($a['fecha']) ?>"></label>
                    <button type="submit">Guardar</button>
                </form>
                <form action="seguimiento.php" method="POST" onsubmit="return confirm('¿Eliminar esta actividad y sus notas?');">
                    <?php $ocultosSeg('actividad_eliminar', ['actividad_id' => (int) $a['id']]); ?>
                    <button type="submit" class="boton-peligro">Quitar</button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if ($actividades && $sumaPesos > 0 && abs($sumaPesos - 100.0) > 0.01): ?>
            <p class="mensaje mensaje-aviso">Los pesos de las actividades suman <?= $e($pesoTxt($sumaPesos)) ?> %. No es obligatorio que sumen 100: se promedian según su peso relativo.</p>
        <?php endif; ?>
        <details <?= $actividades ? '' : 'open' ?>>
            <summary>Agregar una actividad</summary>
            <form action="seguimiento.php" method="POST" class="acad-filtros" novalidate>
                <?php $ocultosSeg('actividad_guardar'); ?>
                <label>Nombre <input type="text" name="nombre" maxlength="100" required placeholder="Ej.: Taller 1, Evaluación escrita"></label>
                <label>Peso % <input type="number" name="peso" min="0" max="100" step="0.01" value="0" style="width:6em"></label>
                <?php if ($usaProc): ?>
                    <label>Proceso <select name="componente_id" required><option value="">Elige…</option><?php foreach ($componentes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= $e($c['nombre']) ?></option><?php endforeach; ?></select></label>
                <?php endif; ?>
                <?php if ($usaLogr): ?>
                    <label>Logro <select name="logro_id" required><option value="">Elige…</option><?php foreach ($logros as $i => $l): ?><option value="<?= (int) $l['id'] ?>">Logro <?= $i + 1 ?></option><?php endforeach; ?></select></label>
                <?php endif; ?>
                <label>Fecha <input type="date" name="fecha"></label>
                <button type="submit">Agregar actividad</button>
            </form>
        </details>
    </section>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
