<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Informes.php';

// Módulo de informes: catálogo por categorías, filtros del informe elegido y emisión de certificados verificables.
requiereRol(...Informes::rolesDelModulo());
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$e = static fn ($v): string => htmlspecialchars((string) $v);

$clave = (string) ($_GET['r'] ?? '');
$def = $clave !== '' ? Informes::definicion($clave) : null;
if ($def !== null && !Informes::permitido($def, $rol)) {
    $def = null;
}

$flash = $_SESSION['flash_informes'] ?? null;
unset($_SESSION['flash_informes']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $claveP = (string) ($_POST['r'] ?? '');
    $dp = Informes::definicion($claveP);
    $f = ['exito' => '', 'error' => '', 'codigo' => ''];
    if ($dp !== null && !empty($dp['cert']) && Informes::permitido($dp, $rol) && ($_POST['accion'] ?? '') === 'emitir') {
        $r = Certificados::emitir($pdo, $colegioId, (string) $dp['cert'], (int) ($_POST['estudiante'] ?? 0), (int) ($_POST['anio'] ?? 0), $usuarioId);
        if ($r['ok']) {
            $f['exito'] = $dp['nombre'] . ' emitido.';
            $f['codigo'] = $r['codigo'];
            auditarAccion($pdo, 'emitir_certificado', 'certificados', 0, [], ['tipo' => (string) $dp['cert'], 'estudiante_id' => (int) ($_POST['estudiante'] ?? 0), 'codigo' => $r['codigo'], 'desde' => 'informes']);
        } else {
            $f['error'] = $r['error'];
        }
    } else {
        $f['error'] = 'Acción no permitida.';
    }
    $_SESSION['flash_informes'] = $f;
    header('Location: informes.php?r=' . urlencode($claveP));
    exit;
}

$ctx = Informes::contexto($pdo, $colegioId, $rol, $usuarioId);
$catalogo = Informes::visibles($rol);
$total = array_sum(array_map(static fn (array $c): int => count($c['informes']), $catalogo));

// Listas para los filtros del informe elegido
$L = [];
if ($def !== null) {
    $usa = $def['filtros'];
    $anios = Academico::anios($pdo, $colegioId);
    $actual = Academico::anioActual($pdo, $colegioId);
    $L['anios'] = $anios;
    $anioSel = (int) ($_GET['anio'] ?? ($actual['id'] ?? ($anios[0]['id'] ?? 0)));
    if (in_array('periodo', $usa, true)) {
        $L['periodos'] = [];
        foreach ($anios as $a) {
            foreach (Academico::periodos($pdo, (int) $a['id']) as $p) {
                $p['anio'] = $a['anio'];
                $L['periodos'][] = $p;
            }
        }
    }
    if (in_array('grado', $usa, true)) {
        $L['grados'] = Academico::grados($pdo, $colegioId);
    }
    if (in_array('grupo', $usa, true)) {
        $L['grupos'] = Informes::grupos($pdo, $ctx);
    }
    if (in_array('estudiante', $usa, true)) {
        $L['estudiantes'] = Expediente::estudiantes($pdo, $rol, $usuarioId, $colegioId, 0, '');
    }
    if (in_array('asignacion', $usa, true)) {
        $L['asignaciones'] = [];
        foreach (array_slice($anios, 0, 4) as $a) {
            foreach (Informes::asignaciones($pdo, $ctx, (int) $a['id']) as $x) {
                $x['anio'] = $a['anio'];
                $x['anio_id'] = $a['id'];
                $L['asignaciones'][] = $x;
            }
        }
    }
    if (in_array('docente', $usa, true) && $rol !== 'docente') {
        $L['docentes'] = Informes::docentes($pdo, $colegioId);
    }
}
$val = static fn (string $k): string => (string) ($_GET[$k] ?? '');
$faltaInst = $def !== null && !empty($def['cert']) ? Certificados::faltantes($ctx['ins']) : [];

$tituloPagina = 'Informes';
$cssExtra = ['/assets/css/academico.css', '/assets/css/informes.css'];
$jsExtra = ['/assets/js/informes.js'];
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Informes</h1>
<p class="texto-suave">Elige un informe, ajusta los filtros y míralo, imprímelo o descárgalo en PDF o CSV con el formato institucional de tu colegio.</p>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?>
    <p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?>
        <?php if ($flash['codigo']): ?>
            <a href="<?= BASE_URL ?>/certificado_pdf.php?c=<?= urlencode($flash['codigo']) ?>&amp;ver=1" target="_blank" rel="noopener">Abrir el PDF</a> · código <strong><?= $e(Certificados::formatearCodigo($flash['codigo'])) ?></strong>
        <?php endif; ?>
    </p>
<?php endif; ?>
<?php if (!Informes::tablaExiste($pdo, 'informes_generados')): ?><p class="mensaje mensaje-aviso">Falta aplicar <code>sql/migraciones/038_informes.sql</code>: sin ella no hay bitácora de informes ni los informes de refuerzos, restaurante, cartera y caracterización.</p><?php endif; ?>

<?php if ($def !== null): ?>
<section class="inf-panel">
    <p><a href="informes.php">← Todos los informes</a></p>
    <h2><?= $e($def['nombre']) ?></h2>
    <p class="texto-suave"><?= $e($def['desc']) ?></p>

    <?php if (!empty($def['cert'])): ?>
        <?php if ($faltaInst): ?><p class="mensaje mensaje-aviso">Antes de emitir configura la institución: falta <?= $e(implode(', ', $faltaInst)) ?>.</p><?php endif; ?>
        <form method="POST" class="inf-filtros">
            <?php campoCSRF(); ?>
            <input type="hidden" name="r" value="<?= $e($clave) ?>"><input type="hidden" name="accion" value="emitir">
            <label>Año lectivo
                <select name="anio"><?php foreach ($L['anios'] as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === $anioSel ? 'selected' : '' ?>><?= $e($a['anio']) ?></option><?php endforeach; ?></select></label>
            <label class="inf-ancho">Estudiante
                <select name="estudiante" required><option value="">— elige —</option>
                    <?php foreach ($L['estudiantes'] as $es): ?><option value="<?= (int) $es['id'] ?>"><?= $e($es['nombre']) ?> · <?= $e(trim($es['grado'] . ' ' . $es['grupo'])) ?></option><?php endforeach; ?>
                </select></label>
            <div class="inf-acciones"><button type="submit">Emitir certificado</button></div>
        </form>
        <p class="inf-nota">El certificado queda guardado con un código QR de verificación. Para anularlo o ver los emitidos usa <a href="<?= BASE_URL ?>/secretaria_academica/certificados.php">Certificados</a>. El texto se edita en la plantilla del colegio.</p>
    <?php else: ?>
        <form method="GET" action="informe_ver.php" class="inf-filtros">
            <input type="hidden" name="r" value="<?= $e($clave) ?>">
            <?php $req = $def['req'] ?? []; foreach ($def['filtros'] as $k): $obl = in_array($k, $req, true) ? ' <em>(obligatorio)</em>' : ''; ?>
                <?php if ($k === 'anio'): ?>
                    <label>Año lectivo<select name="anio" id="f-anio"><?php foreach ($L['anios'] as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === $anioSel ? 'selected' : '' ?>><?= $e($a['anio']) ?><?= (int) $a['actual'] === 1 ? ' (actual)' : '' ?></option><?php endforeach; ?></select></label>
                <?php elseif ($k === 'periodo'): ?>
                    <label>Período<?= $obl ?><select name="periodo" id="f-periodo"><option value="0"><?= in_array('periodo', $req, true) ? '— elige —' : 'Todo el año' ?></option>
                        <?php foreach ($L['periodos'] as $p): ?><option value="<?= (int) $p['id'] ?>" data-anio="<?= (int) $p['anio_lectivo_id'] ?>"><?= $e($p['nombre']) ?> (<?= $e($p['anio']) ?>)</option><?php endforeach; ?></select></label>
                <?php elseif ($k === 'grado'): ?>
                    <label>Grado<select name="grado"><option value="0">Todos</option><?php foreach ($L['grados'] as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $e($g['nombre']) ?></option><?php endforeach; ?></select></label>
                <?php elseif ($k === 'grupo'): ?>
                    <label>Grupo<?= $obl ?><select name="grupo"><option value="0"><?= in_array('grupo', $req, true) ? '— elige —' : 'Todos' ?></option><?php foreach ($L['grupos'] as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?></select></label>
                <?php elseif ($k === 'estudiante'): ?>
                    <label class="inf-ancho">Estudiante<?= $obl ?><select name="estudiante"><option value="0">— ninguno (usa el grupo) —</option>
                        <?php foreach ($L['estudiantes'] as $es): ?><option value="<?= (int) $es['id'] ?>"><?= $e($es['nombre']) ?> · <?= $e(trim($es['grado'] . ' ' . $es['grupo'])) ?></option><?php endforeach; ?></select></label>
                <?php elseif ($k === 'asignacion'): ?>
                    <label class="inf-ancho">Asignatura y grupo<?= $obl ?><select name="asignacion" id="f-asig"><option value="0">— elige —</option>
                        <?php foreach ($L['asignaciones'] as $x): ?><option value="<?= (int) $x['id'] ?>" data-anio="<?= (int) $x['anio_id'] ?>"><?= $e($x['asignatura']) ?> · <?= $e(trim($x['grado'] . ' ' . $x['grupo'])) ?> (<?= $e($x['anio']) ?>)</option><?php endforeach; ?></select></label>
                <?php elseif ($k === 'docente' && $rol !== 'docente'): ?>
                    <label>Docente<select name="docente"><option value="0">Todos</option><?php foreach ($L['docentes'] as $d): ?><option value="<?= (int) $d['id'] ?>"><?= $e($d['nombre']) ?></option><?php endforeach; ?></select></label>
                <?php elseif ($k === 'desde' || $k === 'hasta'): ?>
                    <label><?= $k === 'desde' ? 'Desde' : 'Hasta' ?><input type="date" name="<?= $e($k) ?>"></label>
                <?php elseif ($k === 'umbral'): ?>
                    <label>Umbral <small>(opcional)</small><input type="text" inputmode="decimal" name="umbral" placeholder="automático"></label>
                <?php elseif ($k === 'estado'): ?>
                    <label>Estado<select name="estado"><option value="">Todos</option><?php foreach ($def['estados'] as $v => $t): ?><option value="<?= $e($v) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
                <?php elseif ($k === 'solo_pub'): ?>
                    <label class="inf-check"><input type="checkbox" name="solo_pub" value="1"> Solo períodos con boletín publicado</label>
                <?php elseif ($k === 'tipo_cert'): ?>
                    <label>Tipo de certificado<select name="tipo_cert"><?php foreach (Certificados::TIPOS as $v => $t): ?><option value="<?= $e($v) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
                <?php elseif ($k === 'mes'): ?>
                    <label>Mes<select name="mes"><option value="0">Todos</option><?php foreach (Informes::MESES as $v => $t): ?><option value="<?= $v ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="inf-acciones">
                <button type="submit" name="formato" value="html" formtarget="_blank">Ver e imprimir</button>
                <button type="submit" name="formato" value="pdf" formtarget="_blank" class="boton-sec">PDF</button>
                <button type="submit" name="formato" value="csv" class="boton-sec">CSV</button>
                <label class="texto-suave" style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="qr" value="1"> PDF con código QR de verificación</label>
            </div>
        </form>
        <?php if (!empty($def['tablas'])): ?><p class="inf-nota">Usa datos de: <?= $e(implode(', ', $def['tablas'])) ?> (migración 038 si no existen).</p><?php endif; ?>
        <?php if (!empty($def['sensible'])): ?><p class="inf-nota">Informe con datos sensibles: cada consulta queda registrada en la auditoría.</p><?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="inf-buscar no-print">
    <input type="search" id="inf-buscar" placeholder="Buscar entre <?= (int) $total ?> informes…" aria-label="Buscar informe" autocomplete="off">
</div>

<?php foreach ($catalogo as $n => $cat): ?>
<details class="inf-cat" <?= ($def !== null && (int) $def['cat'] === $n) || $def === null ? 'open' : '' ?>>
    <summary><strong><?= (int) $n ?>. <?= $e($cat['nombre']) ?></strong> <span class="inf-cuenta"><?= count($cat['informes']) ?></span><small><?= $e($cat['desc']) ?></small></summary>
    <div class="inf-grid">
        <?php foreach ($cat['informes'] as $k => $d): ?>
            <a class="inf-card<?= $k === $clave ? ' inf-card-act' : '' ?>" href="informes.php?r=<?= urlencode($k) ?>" data-nombre="<?= $e(mb_strtolower($d['nombre'] . ' ' . $d['desc'], 'UTF-8')) ?>">
                <strong><?= $e($d['nombre']) ?></strong>
                <span><?= $e($d['desc']) ?></span>
                <?php if (!empty($d['cert'])): ?><em>Emite certificado con QR</em><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</details>
<?php endforeach; ?>

<?php $rec = Informes::recientes($pdo, $ctx); if ($rec): ?>
<section class="inf-recientes">
    <h2>Generados recientemente</h2>
    <ul>
        <?php foreach ($rec as $r): $d = Informes::definicion((string) $r['clave']); ?>
            <li><?= $e($d['nombre'] ?? $r['clave']) ?> · <?= $e(strtoupper((string) $r['formato'])) ?> · <?= $e(substr((string) $r['creado_en'], 0, 16)) ?><?= $rol === 'administrador' || $rol === 'rector' ? ' · ' . $e($r['usuario'] ?? '') : '' ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
