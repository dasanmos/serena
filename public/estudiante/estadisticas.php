<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/Sueno.php';
require_once __DIR__ . '/../../src/modelos/Habito.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$emociones = EstadoEmocional::resumenPorPeriodo($pdo, $uid, $desde, $hasta);
$sueno = Sueno::resumenPorPeriodo($pdo, $uid, $desde, $hasta);
$distribucionEmociones = EstadoEmocional::distribucionPorEmocion($pdo, $uid, $desde, $hasta);
$habitos = Habito::listarDeUsuario($pdo, $uid);
foreach ($habitos as &$h) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM seguimiento_habitos WHERE habito_id=:hid AND fecha BETWEEN :d AND :h AND completado=1");
    $c->execute(['hid' => $h['id'], 'd' => $desde, 'h' => $hasta]);
    $h['completados'] = (int)$c->fetchColumn();
}
unset($h);

// --- Comparativa mensual: mes actual vs mes anterior ---
$anioActual = (int)date('Y'); $mesActual = (int)date('m');
$fechaMesAnterior = strtotime(date('Y-m-01') . ' -1 month');
$anioAnterior = (int)date('Y', $fechaMesAnterior); $mesAnterior = (int)date('m', $fechaMesAnterior);

$comparativa = [
    'emocion' => ['actual' => EstadoEmocional::promedioPorMes($pdo, $uid, $anioActual, $mesActual),
                  'anterior' => EstadoEmocional::promedioPorMes($pdo, $uid, $anioAnterior, $mesAnterior)],
    'sueno'   => ['actual' => Sueno::promedioPorMes($pdo, $uid, $anioActual, $mesActual),
                  'anterior' => Sueno::promedioPorMes($pdo, $uid, $anioAnterior, $mesAnterior)],
    'habitos' => ['actual' => Habito::completadosPorMes($pdo, $uid, $anioActual, $mesActual),
                  'anterior' => Habito::completadosPorMes($pdo, $uid, $anioAnterior, $mesAnterior)],
];

function tendencia(?float $actual, ?float $anterior): array
{
    if ($actual === null || $anterior === null || $anterior == 0) return ['icono' => '—', 'clase' => 'tendencia-neutra', 'texto' => 'Sin datos suficientes'];
    $delta = $actual - $anterior;
    $pct = round(($delta / $anterior) * 100);
    if (abs($pct) < 3) return ['icono' => '→', 'clase' => 'tendencia-neutra', 'texto' => 'Estable respecto al mes pasado'];
    return $delta > 0
        ? ['icono' => '▲', 'clase' => 'tendencia-positiva', 'texto' => "+{$pct}% respecto al mes pasado"]
        : ['icono' => '▼', 'clase' => 'tendencia-negativa', 'texto' => "{$pct}% respecto al mes pasado"];
}

$emocionData = json_encode(array_map(fn($e) => ['dia' => $e['dia'], 'promedio' => round((float)$e['promedio'],1)], $emociones));
$suenoData = json_encode(array_map(fn($s) => ['dia' => $s['fecha'], 'horas' => (float)$s['horas']], $sueno));
$distribucionData = json_encode(array_map(fn($d) => ['emocion' => ucfirst($d['emocion']), 'total' => (int)$d['total']], $distribucionEmociones));

$nombresMeses = ['', 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

// --- Mapa de calor del mes actual: un color por día según el ánimo promedio ---
$primerDiaMes = date('Y-m-01');
$ultimoDiaMes = date('Y-m-t');
$emocionesMes = EstadoEmocional::resumenPorPeriodo($pdo, $uid, $primerDiaMes, $ultimoDiaMes);
$mapaAnimoPorDia = [];
foreach ($emocionesMes as $e) {
    $mapaAnimoPorDia[$e['dia']] = round((float) $e['promedio'], 1);
}
$diasEnMes = (int) date('t');
$primerDiaSemana = (int) date('N', strtotime($primerDiaMes)); // 1 = lunes ... 7 = domingo

$tituloPagina = 'Estadísticas';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-estadisticas">
  <h1>Mis estadísticas</h1>
  <div class="u-flex u-gap-sm">
    <button type="button" class="boton-secundario" onclick="window.print()" data-sonido="clic">🖨 Imprimir</button>
    <a href="estadisticas_pdf.php?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>" class="boton-acceso" data-sonido="clic">📄 Exportar a PDF</a>
    <a href="estadisticas_pdf.php?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&qr=1" class="boton-secundario" data-sonido="clic" title="PDF con código QR para verificar su autenticidad">PDF con QR</a>
  </div>
</div>
<div class="encabezado-impresion solo-impresion">
  <img src="<?= BASE_URL ?>/assets/img/logo/logo_negro.png" alt="" class="logo-impresion">
  <span>Serena — Reporte de bienestar de <?= htmlspecialchars($_SESSION['nombre']) ?> — <?= date('d/m/Y') ?> — Período: <?= htmlspecialchars($desde) ?> a <?= htmlspecialchars($hasta) ?></span>
</div>


<form action="estadisticas.php" method="GET" class="form-filtro-fechas no-imprimir">
  <div><label>Desde</label><input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>"></div>
  <div><label>Hasta</label><input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>"></div>
  <button type="submit" data-sonido="clic">Ver</button>
</form>

<section class="panel-seccion">
  <h2>Mapa de ánimo — <?= $nombresMeses[(int) date('m')] ?></h2>
  <div class="mapa-calor-leyenda">
    <span>Bajo</span>
    <span class="muestra-calor nivel-1"></span>
    <span class="muestra-calor nivel-2"></span>
    <span class="muestra-calor nivel-3"></span>
    <span class="muestra-calor nivel-4"></span>
    <span class="muestra-calor nivel-5"></span>
    <span>Alto</span>
    <span class="muestra-calor sin-dato"></span>
    <span>Sin registro</span>
  </div>
  <div class="mapa-calor-grilla">
    <span class="mapa-calor-encabezado">L</span>
    <span class="mapa-calor-encabezado">M</span>
    <span class="mapa-calor-encabezado">M</span>
    <span class="mapa-calor-encabezado">J</span>
    <span class="mapa-calor-encabezado">V</span>
    <span class="mapa-calor-encabezado">S</span>
    <span class="mapa-calor-encabezado">D</span>
    <?php for ($i = 1; $i < $primerDiaSemana; $i++): ?>
      <span class="mapa-calor-vacio"></span>
    <?php endfor; ?>
    <?php for ($dia = 1; $dia <= $diasEnMes; $dia++): ?>
      <?php
        $fechaDia = date('Y-m-') . str_pad((string) $dia, 2, '0', STR_PAD_LEFT);
        $promedio = $mapaAnimoPorDia[$fechaDia] ?? null;
        $clase = $promedio === null ? 'sin-dato' : 'nivel-' . max(1, min(5, (int) round($promedio)));
      ?>
      <span class="mapa-calor-dia <?= $clase ?>" title="<?= htmlspecialchars($fechaDia) ?><?= $promedio !== null ? " — ánimo {$promedio}/5" : ' — sin registro' ?>">
        <?= $dia ?>
      </span>
    <?php endfor; ?>
  </div>
</section>

<section class="panel-seccion">
  <h2>Comparativa mensual — <?= $nombresMeses[$mesActual] ?> vs <?= $nombresMeses[$mesAnterior] ?></h2>
  <div class="grilla-comparativa">
    <div class="tarjeta-comparativa">
      <span class="etiqueta-comparativa">Estado emocional</span>
      <span class="valor-comparativa"><?= $comparativa['emocion']['actual'] !== null ? $comparativa['emocion']['actual'] . '/5' : '—' ?></span>
      <?php $t = tendencia($comparativa['emocion']['actual'], $comparativa['emocion']['anterior']); ?>
      <span class="<?= $t['clase'] ?>"><?= $t['icono'] ?> <?= $t['texto'] ?></span>
    </div>
    <div class="tarjeta-comparativa">
      <span class="etiqueta-comparativa">Horas de sueño (prom.)</span>
      <span class="valor-comparativa"><?= $comparativa['sueno']['actual'] !== null ? $comparativa['sueno']['actual'] . 'h' : '—' ?></span>
      <?php $t = tendencia($comparativa['sueno']['actual'], $comparativa['sueno']['anterior']); ?>
      <span class="<?= $t['clase'] ?>"><?= $t['icono'] ?> <?= $t['texto'] ?></span>
    </div>
    <div class="tarjeta-comparativa">
      <span class="etiqueta-comparativa">Hábitos completados</span>
      <span class="valor-comparativa"><?= $comparativa['habitos']['actual'] ?></span>
      <?php $t = tendencia((float)$comparativa['habitos']['actual'], (float)$comparativa['habitos']['anterior']); ?>
      <span class="<?= $t['clase'] ?>"><?= $t['icono'] ?> <?= $t['texto'] ?></span>
    </div>
  </div>
</section>

<div class="columnas">
<section class="panel-seccion">
  <h2>Estado emocional en el período</h2>
  <canvas id="grafico-emocion" height="200"></canvas>
</section>
<section class="panel-seccion">
  <h2>Horas de sueño en el período</h2>
  <canvas id="grafico-sueno" height="200"></canvas>
</section>
</div>

<div class="columnas">
<section class="panel-seccion">
  <h2>Distribución de emociones</h2>
  <?php if (empty($distribucionEmociones)): ?>
    <div class="estado-vacio"><p>Sin registros emocionales en este período.</p></div>
  <?php else: ?>
    <canvas id="grafico-torta" height="220"></canvas>
  <?php endif; ?>
</section>
<section class="panel-seccion">
  <h2>Cumplimiento de hábitos en el período</h2>
  <?php if (empty($habitos)): ?>
    <div class="estado-vacio"><p>Aún no tienes hábitos para mostrar aquí.</p></div>
  <?php else: ?>
  <ul class="lista-simple">
    <?php foreach ($habitos as $h): ?>
      <li><strong><?= htmlspecialchars($h['nombre']) ?></strong> — <?= $h['completados'] ?> días completados</li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
</div>

<?php
// Usa la copia local si ya la descargaste a assets/js/vendor/chart.min.js (recomendado
// para InfinityFree o redes escolares que bloqueen CDNs externos). Si no existe, cae
// al CDN — así nunca se rompe la página por falta del archivo local.
$chartJsLocal = __DIR__ . '/../assets/js/vendor/chart.min.js';
$chartJsSrc = is_file($chartJsLocal) ? BASE_URL . '/assets/js/vendor/chart.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
?>
<script src="<?= $chartJsSrc ?>"></script>
<script>
const emoData = <?= $emocionData ?>;
const suenoData = <?= $suenoData ?>;
const distribucionData = <?= $distribucionData ?>;
const PALETA = ['#E8B923', '#F0C63E', '#F9D976', '#C9A227', '#8A6A00', '#D9B75A', '#B08D1E', '#DEC873', '#A67C00', '#EFDBA0'];

new Chart(document.getElementById('grafico-emocion'), {
  type: 'line',
  data: {
    labels: emoData.map(d => d.dia),
    datasets: [{ label: 'Nivel promedio', data: emoData.map(d => d.promedio),
      borderColor: '#E8B923', backgroundColor: 'rgba(232,185,35,0.15)', tension: 0.3, fill: true }]
  },
  options: { scales: { y: { min: 1, max: 5 } }, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('grafico-sueno'), {
  type: 'bar',
  data: {
    labels: suenoData.map(d => d.dia),
    datasets: [{ label: 'Horas', data: suenoData.map(d => d.horas),
      backgroundColor: 'rgba(232,185,35,0.7)', borderRadius: 6 }]
  },
  options: { scales: { y: { min: 0, max: 12 } }, plugins: { legend: { display: false } } }
});
if (distribucionData.length) {
  new Chart(document.getElementById('grafico-torta'), {
    type: 'pie',
    data: {
      labels: distribucionData.map(d => d.emocion),
      datasets: [{ data: distribucionData.map(d => d.total), backgroundColor: PALETA }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
  });
}
</script>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
