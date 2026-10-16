<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Evaluacion.php';

// Consolidado de notas por grupo y período, publicación de boletines y exportación a CSV (Excel).
requiereRol('secretaria_academica', 'administrador', 'rector', 'coordinador');
$rol = (string) $_SESSION['rol'];
$puedePublicar = in_array($rol, ['secretaria_academica', 'administrador', 'rector'], true);
$colegioId = colegioParaCrear();

$anios = Academico::anios($pdo, $colegioId);
$anioSel = (int) ($_GET['anio'] ?? $_POST['anio'] ?? 0);
$anio = $anioSel > 0 ? Academico::anio($pdo, $anioSel, $colegioId) : Academico::anioActual($pdo, $colegioId);
if ($anio === null && $anios) {
    $anio = $anios[0];
}
$periodos = $anio ? Academico::periodos($pdo, (int) $anio['id']) : [];

$flash = $_SESSION['flash_boletines'] ?? null;
unset($_SESSION['flash_boletines']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    if (!$puedePublicar) {
        $f['error'] = 'No tienes permiso para publicar boletines.';
    } elseif ((string) ($_POST['accion'] ?? '') === 'publicar') {
        $publicar = (string) ($_POST['publicar'] ?? '') === '1';
        $ok = Evaluacion::publicarPeriodo($pdo, (int) ($_POST['periodo_id'] ?? 0), $colegioId, $publicar);
        $f[$ok ? 'exito' : 'error'] = $ok ? ($publicar ? 'Boletín publicado: estudiantes y acudientes ya pueden verlo.' : 'Boletín oculto para estudiantes y acudientes.') : 'El período no existe.';
        if ($ok) {
            auditarAccion($pdo, $publicar ? 'publicar_boletin' : 'ocultar_boletin', 'periodos', (int) ($_POST['periodo_id'] ?? 0), [], []);
        }
    }
    $_SESSION['flash_boletines'] = $f;
    header('Location: boletines.php?' . http_build_query(['anio' => $anio ? (int) $anio['id'] : 0, 'grupo' => (int) ($_POST['grupo'] ?? 0), 'periodo' => (int) ($_POST['periodo'] ?? 0)]));
    exit;
}

$grupos = Academico::grupos($pdo, $colegioId);
$grupoId = (int) ($_GET['grupo'] ?? 0);
$periodoId = (int) ($_GET['periodo'] ?? 0);
if ($periodoId === 0 && $periodos) {
    $periodoId = (int) $periodos[0]['id'];
}
$periodo = null;
foreach ($periodos as $p) {
    if ((int) $p['id'] === $periodoId) {
        $periodo = $p;
    }
}
$grupoValido = false;
foreach ($grupos as $g) {
    if ((int) $g['id'] === $grupoId) {
        $grupoValido = true;
    }
}
$consol = ($anio && $periodo && $grupoValido) ? Evaluacion::consolidadoGrupo($pdo, $colegioId, $anio, $grupoId, $periodoId) : null;
$escala = Evaluacion::escalaActiva($pdo, $colegioId);

// Exportar a CSV (se abre en Excel)
if ($consol !== null && $escala !== null && (string) ($_GET['exportar'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="consolidado-' . (int) $anio['anio'] . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $periodo['nombre']) . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, array_merge(['Estudiante'], array_map(static fn (array $a): string => (string) $a['asignatura'], $consol['asignaciones']), ['Promedio']), ',', '"', '');
    foreach ($consol['estudiantes'] as $s) {
        $fila = [$s['nombre']];
        $vals = [];
        foreach ($consol['asignaciones'] as $a) {
            $n = $consol['notas'][(int) $s['id']][(int) $a['id']] ?? null;
            $v = ($n && $n['valor'] !== null) ? (float) $n['valor'] : null;
            $fila[] = $v !== null ? number_format($v, (int) $escala['decimales'], '.', '') : '';
            if ($v !== null) {
                $vals[] = $v;
            }
        }
        $fila[] = $vals ? number_format(array_sum($vals) / count($vals), (int) $escala['decimales'], '.', '') : '';
        fputcsv($o, $fila, ',', '"', '');
    }
    fclose($o);
    exit;
}

$e = static fn ($v): string => htmlspecialchars((string) $v);
$tituloPagina = 'Boletines';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Boletines y consolidado</h1>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<?php if (!$anio): ?>
    <p class="mensaje mensaje-aviso">No hay años lectivos configurados.</p>
<?php else: ?>

<?php if ($puedePublicar && $periodos): ?>
<section class="panel-seccion">
    <h2>Publicación de boletines (<?= (int) $anio['anio'] ?>)</h2>
    <p class="texto-suave">Estudiantes y acudientes solo ven los períodos publicados. Revisa las planillas antes de publicar.</p>
    <?php foreach ($periodos as $p): ?>
        <div class="acad-acciones">
            <strong><?= $e($p['nombre']) ?></strong>
            <span class="estado-pill <?= (int) ($p['boletin_publicado'] ?? 0) === 1 ? 'estado-activa' : 'estado-pendiente' ?>"><?= (int) ($p['boletin_publicado'] ?? 0) === 1 ? 'Publicado' : 'Sin publicar' ?></span>
            <form action="boletines.php" method="POST" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="publicar"><input type="hidden" name="periodo_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="anio" value="<?= (int) $anio['id'] ?>">
                <input type="hidden" name="grupo" value="<?= $grupoId ?>"><input type="hidden" name="periodo" value="<?= $periodoId ?>">
                <?php if ((int) ($p['boletin_publicado'] ?? 0) === 1): ?>
                    <button type="submit" name="publicar" value="0" class="boton-secundario">Ocultar</button>
                <?php else: ?>
                    <button type="submit" name="publicar" value="1" onclick="return confirm('¿Publicar este período? Lo verán estudiantes y acudientes.');">Publicar</button>
                <?php endif; ?>
            </form>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<form action="boletines.php" method="GET" class="acad-filtros">
    <label>Año
        <select name="anio" onchange="this.form.submit()">
            <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $anio['id'] === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Grupo
        <select name="grupo" onchange="this.form.submit()">
            <option value="0">Elige…</option>
            <?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $grupoId === (int) $g['id'] ? 'selected' : '' ?>><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Período
        <select name="periodo" onchange="this.form.submit()">
            <?php foreach ($periodos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $periodoId === (int) $p['id'] ? 'selected' : '' ?>><?= $e($p['nombre']) ?></option><?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Ver</button>
</form>

<?php if ($escala === null): ?>
    <p class="mensaje mensaje-aviso">Configura primero el <a href="<?= $e(BASE_URL) ?>/administrador/evaluacion_config.php">sistema de evaluación</a>.</p>
<?php elseif ($consol === null): ?>
    <div class="estado-vacio"><p>Elige un grupo y un período para ver el consolidado.</p></div>
<?php elseif (!$consol['estudiantes']): ?>
    <div class="estado-vacio"><p>Ese grupo no tiene estudiantes con matrícula activa en <?= (int) $anio['anio'] ?>.</p></div>
<?php else: ?>
<section class="panel-seccion">
    <h2>Consolidado · <?= $e($periodo['nombre']) ?></h2>
    <div class="acad-acciones no-print">
        <a class="boton-secundario" href="boletines.php?<?= $e(http_build_query(['anio' => (int) $anio['id'], 'grupo' => $grupoId, 'periodo' => $periodoId, 'exportar' => 1])) ?>">Descargar CSV (Excel)</a>
        <button type="button" class="boton-secundario" onclick="window.print()">Imprimir</button>
    </div>
    <div class="acad-tabla-envoltura">
        <table class="acad-tabla planilla-tabla">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <?php foreach ($consol['asignaciones'] as $a): ?><th class="nota"><?= $e($a['asignatura']) ?></th><?php endforeach; ?>
                    <th class="nota">Boletín</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($consol['estudiantes'] as $s): ?>
                <tr>
                    <td><?= $e($s['nombre']) ?></td>
                    <?php foreach ($consol['asignaciones'] as $a): $n = $consol['notas'][(int) $s['id']][(int) $a['id']] ?? null; ?>
                        <td class="nota"><?= $n && $n['valor'] !== null ? $e($escala['tipo'] === 'descriptiva' ? ($n['abreviatura'] ?? '') : number_format((float) $n['valor'], (int) $escala['decimales'], '.', '')) : '—' ?></td>
                    <?php endforeach; ?>
                    <td class="nota"><a href="<?= $e(BASE_URL) ?>/boletin.php?est=<?= (int) $s['id'] ?>&amp;anio=<?= (int) $anio['id'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
