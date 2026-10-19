<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Grado.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
require_once __DIR__ . '/../../src/helpers/ia.php';
require_once __DIR__ . '/../../src/helpers/texto_ia_cache.php';
requiereRol('psicologo','coordinador');
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$gradoId = (int) ($_GET['grado_id'] ?? 0);
$grupoId = (int) ($_GET['grupo_id'] ?? 0);

$condicionGrupo = '';
$paramsGrupo = [];
// Multi-colegio: todos los reportes quedan limitados al colegio del usuario (u = usuarios del estudiante).
$condicionColegio = sqlColegio('u.colegio_id');
if ($grupoId) { $condicionGrupo = ' AND u.grupo_id = :grupo_id'; $paramsGrupo['grupo_id'] = $grupoId; }
elseif ($gradoId) { $condicionGrupo = ' AND g.grado_id = :grado_id'; $paramsGrupo['grado_id'] = $gradoId; }
$necesitaJoinGrupo = $condicionGrupo !== '' ? 'INNER JOIN usuarios u ON u.id = e.usuario_id INNER JOIN grupos g ON g.id = u.grupo_id' : 'INNER JOIN usuarios u ON u.id = e.usuario_id';

$promedioEmociones = $pdo->prepare(
    "SELECT AVG(e.nivel) AS prom, COUNT(*) AS total FROM estado_emocional e
     {$necesitaJoinGrupo} WHERE DATE(e.fecha_hora) BETWEEN :d AND :h {$condicionGrupo}{$condicionColegio}"
);
$promedioEmociones->execute(['d'=>$desde,'h'=>$hasta] + $paramsGrupo);
$resEmo = $promedioEmociones->fetch();

$necesitaJoinGrupoSueno = $condicionGrupo !== '' ? 'INNER JOIN usuarios u ON u.id = s.usuario_id INNER JOIN grupos g ON g.id = u.grupo_id' : 'INNER JOIN usuarios u ON u.id = s.usuario_id';
$promedioSueno = $pdo->prepare(
    "SELECT AVG(s.horas) AS prom FROM sueno s {$necesitaJoinGrupoSueno} WHERE s.fecha BETWEEN :d AND :h {$condicionGrupo}{$condicionColegio}"
);
$promedioSueno->execute(['d'=>$desde,'h'=>$hasta] + $paramsGrupo);
$resSueno = $promedioSueno->fetch();

$emocionesFrec = $pdo->prepare(
    "SELECT e.emocion, COUNT(*) AS veces FROM estado_emocional e
     {$necesitaJoinGrupo} WHERE DATE(e.fecha_hora) BETWEEN :d AND :h {$condicionGrupo}{$condicionColegio}
     GROUP BY e.emocion ORDER BY veces DESC LIMIT 8"
);
$emocionesFrec->execute(['d'=>$desde,'h'=>$hasta] + $paramsGrupo);
$frecc = $emocionesFrec->fetchAll();

$comparativaGrados = $pdo->prepare(
    "SELECT gr.nombre AS grado, AVG(e.nivel) AS promedio, COUNT(*) AS total
     FROM estado_emocional e
     INNER JOIN usuarios u ON u.id = e.usuario_id
     INNER JOIN grupos g ON g.id = u.grupo_id
     INNER JOIN grados gr ON gr.id = g.grado_id
     WHERE DATE(e.fecha_hora) BETWEEN :d AND :h{$condicionColegio}
     GROUP BY gr.id ORDER BY gr.nombre"
);
$comparativaGrados->execute(['d' => $desde, 'h' => $hasta]);
$comparativaGrados = $comparativaGrados->fetchAll();
$datosComparativaGrados = json_encode(array_map(
    fn($g) => ['grado' => $g['grado'], 'promedio' => round((float) $g['promedio'], 1)],
    $comparativaGrados
));

// Tendencia de los últimos 12 meses (respeta el filtro de grado/grupo, pero NO el rango
// Desde/Hasta: su objetivo es ver la evolución del año, no un período corto).
$necesitaJoinGrupoSueno2 = $condicionGrupo !== '' ? 'INNER JOIN usuarios u ON u.id = s.usuario_id INNER JOIN grupos g ON g.id = u.grupo_id' : 'INNER JOIN usuarios u ON u.id = s.usuario_id';
$tendEmo = $pdo->prepare(
    "SELECT DATE_FORMAT(e.fecha_hora, '%Y-%m') AS mes, AVG(e.nivel) AS prom, COUNT(*) AS total
     FROM estado_emocional e {$necesitaJoinGrupo}
     WHERE e.fecha_hora >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01') {$condicionGrupo}{$condicionColegio}
     GROUP BY mes ORDER BY mes"
);
$tendEmo->execute($paramsGrupo);
$tendSueno = $pdo->prepare(
    "SELECT DATE_FORMAT(s.fecha, '%Y-%m') AS mes, AVG(s.horas) AS prom
     FROM sueno s {$necesitaJoinGrupoSueno2}
     WHERE s.fecha >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01') {$condicionGrupo}{$condicionColegio}
     GROUP BY mes"
);
$tendSueno->execute($paramsGrupo);
$suenoPorMes = [];
foreach ($tendSueno->fetchAll() as $f) { $suenoPorMes[$f['mes']] = (float) $f['prom']; }
$tendenciaMensual = $tendEmo->fetchAll();
$nombresMes = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];

$grados = Grado::listarTodos($pdo);
$todosLosGrupos = Grupo::listarConGrado($pdo);

// Multi-colegio: el colegio va en la clave; sin él, el informe institucional (grado 0, grupo 0) se compartiría entre colegios.
$claveInforme = "informe_c" . (int) (colegioParaConsulta() ?? 0) . "_{$desde}_{$hasta}_{$gradoId}_{$grupoId}";
$textoInforme = obtenerTextoIACacheado(
    $pdo,
    $claveInforme,
    function () use ($resEmo, $resSueno, $frecc, $desde, $hasta) {
        $total = (int) $resEmo['total'];
        if ($total === 0) {
            return "No se registraron datos emocionales entre el {$desde} y el {$hasta} para el filtro seleccionado.";
        }
        $promedio = round((float) $resEmo['prom'], 1);
        $partes = ["Entre el {$desde} y el {$hasta} se registraron {$total} entradas emocionales, con un nivel promedio de {$promedio}/5."];
        if ($resSueno['prom'] !== null) {
            $partes[] = 'El promedio de sueño reportado en este período fue de ' . round((float) $resSueno['prom'], 1) . ' horas por noche.';
        }
        if (!empty($frecc)) {
            $top3 = array_slice($frecc, 0, 3);
            $nombres = implode(', ', array_map(fn($f) => ucfirst($f['emocion']), $top3));
            $partes[] = "Las emociones más frecuentes en este período fueron: {$nombres}.";
        }
        return implode(' ', $partes);
    },
    'reescribirConIA'
);

$tituloPagina = 'Reportes';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Reportes institucionales</h1>
<div class="encabezado-impresion solo-impresion">
  <img src="<?= BASE_URL ?>/assets/img/logo/logo_negro.png" alt="" class="logo-impresion">
  <span>Serena — Reporte institucional — <?= date('d/m/Y') ?> — Período: <?= htmlspecialchars($desde) ?> a <?= htmlspecialchars($hasta) ?></span>
</div>

<section class="tarjeta-analisis">
    <h2>Informe del período</h2>
    <p><?= htmlspecialchars($textoInforme) ?></p>
</section>
<form action="reportes.php" method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:24px">
  <div><label>Desde</label><input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>"></div>
  <div><label>Hasta</label><input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>"></div>
  <div>
    <label>Grado</label>
    <select name="grado_id">
      <option value="">Toda la institución</option>
      <?php foreach ($grados as $g): ?>
        <option value="<?= $g['id'] ?>" <?= $gradoId === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Grupo</label>
    <select name="grupo_id">
      <option value="">Todos</option>
      <?php foreach ($todosLosGrupos as $g): ?>
        <option value="<?= $g['id'] ?>" <?= $grupoId === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['grado'] . ' ' . $g['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit">Ver</button>
  <button type="button" class="boton-secundario" onclick="window.print()" data-sonido="clic">🖨 Imprimir</button>
  <a href="reportes_pdf.php?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&grado_id=<?= $gradoId ?>&grupo_id=<?= $grupoId ?>" class="boton-acceso">📄 Exportar a PDF</a>
  <a href="reportes_pdf.php?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&grado_id=<?= $gradoId ?>&grupo_id=<?= $grupoId ?>&qr=1" class="boton-secundario" data-sonido="clic" title="PDF con código QR para verificar su autenticidad">PDF con QR</a>
  <a href="reportes_csv.php?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&grado_id=<?= $gradoId ?>&grupo_id=<?= $grupoId ?>" class="boton-secundario">📊 Exportar a Excel (CSV)</a>
</form>
<div class="tarjetas-resumen">
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">💬</span><span class="tarjeta-numero"><?= $resEmo['total'] ?></span><span class="tarjeta-etiqueta">Registros emocionales</span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">💛</span><span class="tarjeta-numero"><?= $resEmo['prom'] ? round((float)$resEmo['prom'],1) : '—' ?></span><span class="tarjeta-etiqueta">Nivel emocional promedio</span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">🌙</span><span class="tarjeta-numero"><?= $resSueno['prom'] ? round((float)$resSueno['prom'],1).'h' : '—' ?></span><span class="tarjeta-etiqueta">Horas de sueño promedio</span></div>
</div>
<section class="panel-seccion" style="margin-top:20px">
  <h2>Tendencia de los últimos 12 meses<?= $gradoId || $grupoId ? ' (filtrado)' : '' ?></h2>
  <?php if (empty($tendenciaMensual)): ?>
    <div class="estado-vacio"><p>Todavía no hay registros en los últimos 12 meses.</p></div>
  <?php else: ?>
    <div class="tabla-wrapper"><table class="tabla-datos tabla-adaptable">
      <thead><tr><th>Mes</th><th>Ánimo promedio (1-5)</th><th>Registros</th><th>Sueño promedio</th></tr></thead>
      <tbody>
      <?php foreach ($tendenciaMensual as $m): [$anio, $nm] = explode('-', $m['mes']); ?>
        <tr>
          <td><?= $nombresMes[$nm] ?> <?= $anio ?></td>
          <td>
            <div class="u-flex u-items-center u-gap-md">
              <div class="barra-progreso u-flex u-mt-0" style="flex:1;min-width:80px"><div class="barra-progreso-relleno" style="width:<?= min(100, round((float) $m['prom'] / 5 * 100)) ?>%"></div></div>
              <span><?= number_format((float) $m['prom'], 1) ?></span>
            </div>
          </td>
          <td><?= (int) $m['total'] ?></td>
          <td><?= isset($suenoPorMes[$m['mes']]) ? number_format($suenoPorMes[$m['mes']], 1) . ' h' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<section class="panel-seccion" style="margin-top:20px">
  <h2>Emociones más frecuentes<?= $gradoId || $grupoId ? ' (filtrado)' : '' ?></h2>
  <?php if (empty($frecc)): ?>
    <div class="estado-vacio"><p>Sin datos para el rango seleccionado.</p></div>
  <?php else: foreach ($frecc as $f): ?>
    <div class="u-flex u-items-center u-gap-md u-mb-sm">
      <span style="min-width:100px"><?= htmlspecialchars(ucfirst($f['emocion'])) ?></span>
      <div class="barra-progreso u-flex u-mt-0" style="flex:1">
        <div class="barra-progreso-relleno" style="width:<?= min(100, round($f['veces']/$frecc[0]['veces']*100)) ?>%"></div>
      </div>
      <span class="texto-suave"><?= $f['veces'] ?></span>
    </div>
  <?php endforeach; endif; ?>
</section>

<section class="panel-seccion" style="margin-top:20px">
  <h2>Comparativa de ánimo entre grados</h2>
  <?php if (empty($comparativaGrados)): ?>
    <div class="estado-vacio"><p>Sin datos suficientes para comparar grados en este período.</p></div>
  <?php else: ?>
    <canvas id="grafico-comparativa-grados" height="180"></canvas>
  <?php endif; ?>
</section>

<?php
$chartJsLocal2 = __DIR__ . '/../assets/js/vendor/chart.min.js';
$chartJsSrc2 = is_file($chartJsLocal2) ? BASE_URL . '/assets/js/vendor/chart.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
?>
<?php if (!empty($comparativaGrados)): ?>
<script src="<?= $chartJsSrc2 ?>"></script>
<script>
const datosGrados = <?= $datosComparativaGrados ?>;
Chart.defaults.color = getComputedStyle(document.body).color;
Chart.defaults.borderColor = getComputedStyle(document.documentElement).getPropertyValue('--color-borde').trim() || '#E0DCCB';
new Chart(document.getElementById('grafico-comparativa-grados'), {
  type: 'bar',
  data: {
    labels: datosGrados.map(g => g.grado),
    datasets: [{ label: 'Ánimo promedio', data: datosGrados.map(g => g.promedio),
      backgroundColor: 'rgba(232,185,35,0.7)', borderRadius: 6 }]
  },
  options: { scales: { y: { min: 1, max: 5 } }, plugins: { legend: { display: false } } }
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
