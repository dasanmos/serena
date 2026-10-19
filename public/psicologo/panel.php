<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/AlertaTemprana.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/helpers/analisis.php';
require_once __DIR__ . '/../../src/helpers/analisis_cache.php';
requiereRol('psicologo');
$textoAnalisis = obtenerAnalisisCacheado($pdo, (int) $_SESSION['usuario_id'], fn() => analisisPsicologo($pdo));
$totalEstudiantes = Usuario::contarEstudiantes($pdo);
$alertasPendientes = (int)$pdo->query("SELECT COUNT(*) FROM alertas_tempranas WHERE atendida=0" . sqlUsuarioDeColegio('usuario_id'))->fetchColumn();
$encuestasActivas = (int)$pdo->query("SELECT COUNT(*) FROM encuestas WHERE (fecha_cierre IS NULL OR fecha_cierre > NOW())" . sqlColegio('colegio_id'))->fetchColumn();

// Tendencia institucional agregada de los últimos 14 días — nunca datos de un
// estudiante puntual, solo el promedio de todos, que es justo lo que este panel
// puede mostrar sin comprometer la confidencialidad de nadie.
$tendenciaEmocional = $pdo->query(
    "SELECT DATE(fecha_hora) AS dia, ROUND(AVG(nivel), 2) AS promedio
     FROM estado_emocional
     WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)" . sqlUsuarioDeColegio('usuario_id') . "
     GROUP BY DATE(fecha_hora)
     ORDER BY dia"
)->fetchAll();
$tendenciaEmocionalJson = json_encode($tendenciaEmocional);

$tituloPagina = 'Panel Psicólogo';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
  <h1><span class="efecto-escritura efecto-escritura-dorado">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span></h1>
  <p class="texto-suave">Panel de psicólogo</p>
</div>
<?php $tituloAnalisis = 'Análisis de alertas'; require __DIR__ . '/../parciales/tarjeta_analisis.php'; ?>
<div class="tarjetas-resumen">
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">🎓</span><span class="tarjeta-numero"><?= $totalEstudiantes ?></span><span class="tarjeta-etiqueta">Estudiantes</span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">⚠️</span><span class="tarjeta-numero"><?= $alertasPendientes ?></span><span class="tarjeta-etiqueta">Alertas pendientes</span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">📋</span><span class="tarjeta-numero"><?= $encuestasActivas ?></span><span class="tarjeta-etiqueta">Encuestas activas</span></div>
</div>

<section class="panel-seccion">
  <h2>Tendencia emocional institucional (últimos 14 días)</h2>
  <?php if (empty($tendenciaEmocional)): ?>
    <div class="estado-vacio"><p>Todavía no hay registros suficientes para mostrar una tendencia.</p></div>
  <?php else: ?>
    <canvas id="grafico-tendencia-institucional" height="180"></canvas>
  <?php endif; ?>
</section>

<div class="accesos-rapidos">
  <a href="<?= BASE_URL ?>/psicologo/estudiantes.php" class="boton-acceso">Ver estudiantes</a>
  <a href="<?= BASE_URL ?>/psicologo/alertas.php" class="boton-acceso">Alertas tempranas</a>
  <a href="<?= BASE_URL ?>/psicologo/seguimientos.php" class="boton-acceso">Mis seguimientos</a>
  <a href="<?= BASE_URL ?>/psicologo/encuesta_crear.php" class="boton-acceso">Crear encuesta</a>
  <a href="<?= BASE_URL ?>/psicologo/mensajes.php" class="boton-acceso">Mensajes</a>
</div>

<?php if (!empty($tendenciaEmocional)):
    // Misma lógica que estudiante/estadisticas.php: usa la copia local de Chart.js si
    // existe (assets/js/vendor/chart.min.js), y si no, cae al CDN — así nunca se rompe
    // la página por falta del archivo local, y se puede vendorizar para redes que
    // bloqueen CDNs externos sin tocar código.
    $chartJsLocal = __DIR__ . '/../assets/js/vendor/chart.min.js';
    $chartJsSrc = is_file($chartJsLocal) ? BASE_URL . '/assets/js/vendor/chart.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    ?>
    <script src="<?= $chartJsSrc ?>"></script>
    <script>
    const tendenciaData = <?= $tendenciaEmocionalJson ?>;
    new Chart(document.getElementById('grafico-tendencia-institucional'), {
        type: 'line',
        data: {
            labels: tendenciaData.map(d => d.dia.slice(5)),
            datasets: [{ label: 'Promedio institucional', data: tendenciaData.map(d => d.promedio),
                borderColor: '#E8B923', backgroundColor: 'rgba(232,185,35,0.15)', tension: 0.3, fill: true }]
        },
        options: { scales: { y: { min: 1, max: 5 } }, plugins: { legend: { display: false } } }
    });
    </script>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
