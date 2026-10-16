<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/ReporteColegios.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

$mes = (string) ($_GET['mes'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) { $mes = date('Y-m'); }
$agrupar = ($_GET['agrupar'] ?? 'colegio') === 'municipio' ? 'municipio' : 'colegio';
$desde = $mes . '-01 00:00:00';
$hasta = date('Y-m-d H:i:s', strtotime($desde . ' +1 month'));

$filas = ReporteColegios::metricas($pdo, $desde, $hasta);
if ($agrupar === 'municipio') {
    $grupos = [];
    foreach ($filas as $f) {
        $k = trim((string) ($f['municipio'] ?? '')) ?: '(sin municipio)';
        if (!isset($grupos[$k])) {
            $grupos[$k] = ['nombre' => $k, 'colegios' => 0, 'activo' => 1, 'estudiantes' => 0, 'con_registro' => 0, 'registros' => 0,
                'suma_nivel' => 0, 'alertas' => 0, 'alertas_altas' => 0, 'alertas_atendidas' => 0];
        }
        $grupos[$k]['colegios']++;
        foreach (['estudiantes', 'con_registro', 'registros', 'suma_nivel', 'alertas', 'alertas_altas', 'alertas_atendidas'] as $c) {
            $grupos[$k][$c] += (int) $f[$c];
        }
    }
    ksort($grupos);
    $filas = array_values($grupos);
} else {
    foreach ($filas as &$f) { $f['colegios'] = 1; }
    unset($f);
}

if (($_GET['formato'] ?? '') === 'csv') {
    auditarAccion($pdo, 'exportar_reporte', 'colegios', 0, [], ['mes' => $mes, 'agrupar' => $agrupar]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="serena-reporte-' . $agrupar . '-' . $mes . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [$agrupar === 'colegio' ? 'colegio' : 'municipio', 'colegios', 'estudiantes_activos', 'estudiantes_con_registro',
        'participacion_pct', 'registros_emocionales', 'promedio_emocional', 'alertas_generadas', 'alertas_altas', 'alertas_atendidas', 'atendidas_pct']);
    foreach ($filas as $f) {
        $uso = ReporteColegios::participacion($f); $prom = ReporteColegios::promedio($f);
        fputcsv($out, [ReporteColegios::csvSeguro($f['nombre']), $f['colegios'], $f['estudiantes'], $f['con_registro'],
            $uso ?? '', $f['registros'], $prom ?? '', $f['alertas'], $f['alertas_altas'], $f['alertas_atendidas'],
            (int) $f['alertas'] > 0 ? round(100 * (int) $f['alertas_atendidas'] / (int) $f['alertas'], 1) : '']);
    }
    fclose($out);
    exit;
}

$tituloPagina = 'Reportes';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Reportes para la Secretaría</h1>
<p class="texto-suave">Cifras agregadas del mes, sin datos de estudiantes individuales.</p>

<section class="panel-seccion">
    <form action="reportes.php" method="GET">
        <label>Mes <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>" max="<?= date('Y-m') ?>"></label>
        <label>Agrupar por
            <select name="agrupar">
                <option value="colegio" <?= $agrupar === 'colegio' ? 'selected' : '' ?>>Colegio</option>
                <option value="municipio" <?= $agrupar === 'municipio' ? 'selected' : '' ?>>Municipio</option>
            </select>
        </label>
        <button type="submit">Ver reporte</button>
        <a class="boton-secundario" href="reportes.php?<?= htmlspecialchars(http_build_query(['mes' => $mes, 'agrupar' => $agrupar, 'formato' => 'csv'])) ?>">Descargar CSV</a>
        <button type="button" class="boton-secundario" onclick="window.print()">Imprimir / guardar PDF</button>
    </form>
</section>

<section class="panel-seccion">
    <h2>Reporte de <?= htmlspecialchars($mes) ?> por <?= $agrupar ?></h2>
    <table class="tabla-datos">
        <thead><tr>
            <th><?= $agrupar === 'colegio' ? 'Colegio' : 'Municipio' ?></th>
            <?php if ($agrupar === 'municipio'): ?><th>Colegios</th><?php endif; ?>
            <th>Estudiantes</th><th>Con registro</th><th>Participación</th><th>Promedio emocional</th>
            <th>Alertas</th><th>Altas</th><th>Atendidas</th>
        </tr></thead>
        <tbody>
        <?php foreach ($filas as $f):
            $uso = ReporteColegios::participacion($f); $prom = ReporteColegios::promedio($f); ?>
            <tr>
                <td><?= htmlspecialchars($f['nombre']) ?></td>
                <?php if ($agrupar === 'municipio'): ?><td><?= (int) $f['colegios'] ?></td><?php endif; ?>
                <td><?= (int) $f['estudiantes'] ?></td>
                <td><?= (int) $f['con_registro'] ?></td>
                <td><?= $uso === null ? '—' : $uso . ' %' ?></td>
                <td><?= $prom === null ? '—' : $prom . ' / 5' ?></td>
                <td><?= (int) $f['alertas'] ?></td>
                <td><?= (int) $f['alertas_altas'] ?></td>
                <td><?= (int) $f['alertas_atendidas'] ?><?= (int) $f['alertas'] > 0 ? ' (' . round(100 * (int) $f['alertas_atendidas'] / (int) $f['alertas']) . ' %)' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="texto-suave">Participación = estudiantes con al menos un registro emocional en el mes. "—" en promedio = menos de <?= ReporteColegios::MIN_PARA_PROMEDIO ?> estudiantes con registro.</p>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
