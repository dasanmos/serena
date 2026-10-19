<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/AlertaTemprana.php';
require_once __DIR__ . '/../../src/helpers/analisis.php';
require_once __DIR__ . '/../../src/helpers/analisis_cache.php';
requiereRol('coordinador');
$textoAnalisis = obtenerAnalisisCacheado($pdo, (int) $_SESSION['usuario_id'], fn() => analisisCoordinador($pdo));
$totalEst = Usuario::contarEstudiantes($pdo);
$totalGrupos = (int)$pdo->query('SELECT COUNT(*) FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE 1=1' . sqlColegio('gr.colegio_id'))->fetchColumn();
$alertas = AlertaTemprana::listarNoAtendidas($pdo);
$totalAlertas = count($alertas);

$conteoPorNivel = ['alto' => 0, 'medio' => 0, 'bajo' => 0];
foreach ($alertas as $a) { $conteoPorNivel[$a['nivel_urgencia']]++; }
$conteoPorNivelJson = json_encode($conteoPorNivel);

$tituloPagina = 'Panel Coordinador';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
  <h1><span class="efecto-escritura efecto-escritura-dorado">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span></h1>
  <p class="texto-suave">Panel de coordinador</p>
</div>
<?php $tituloAnalisis = 'Análisis institucional'; require __DIR__ . '/../parciales/tarjeta_analisis.php'; ?>
<div class="tarjetas-resumen">
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">🎓</span><span class="tarjeta-numero"><?= $totalEst ?></span><span class="tarjeta-etiqueta">Estudiantes</span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">👥</span><span class="tarjeta-numero"><?= $totalGrupos ?></span><span class="tarjeta-etiqueta">Grupos</span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">⚠️</span><span class="tarjeta-numero"><?= $totalAlertas ?></span><span class="tarjeta-etiqueta">Alertas pendientes</span></div>
</div>

<section class="panel-seccion">
  <h2>Alertas pendientes por urgencia</h2>
  <?php if ($totalAlertas === 0): ?>
    <div class="estado-vacio"><p>No hay alertas pendientes ahora mismo. ✅</p></div>
  <?php else: ?>
    <canvas id="grafico-alertas-urgencia" height="180"></canvas>
  <?php endif; ?>
</section>

<div class="accesos-rapidos"><a href="<?= BASE_URL ?>/psicologo/reportes.php" class="boton-acceso">Ver reportes</a></div>

<?php if ($totalAlertas > 0):
    $chartJsLocal = __DIR__ . '/../assets/js/vendor/chart.min.js';
    $chartJsSrc = is_file($chartJsLocal) ? BASE_URL . '/assets/js/vendor/chart.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    ?>
    <script src="<?= $chartJsSrc ?>"></script>
    <script>
    const nivelData = <?= $conteoPorNivelJson ?>;
    const estilo = getComputedStyle(document.documentElement);
    Chart.defaults.color = getComputedStyle(document.body).color;
    new Chart(document.getElementById('grafico-alertas-urgencia'), {
        type: 'doughnut',
        data: {
            labels: ['Urgencia alta', 'Urgencia media', 'Urgencia baja'],
            datasets: [{ data: [nivelData.alto, nivelData.medio, nivelData.bajo],
                backgroundColor: [estilo.getPropertyValue('--color-severidad-alta').trim(), estilo.getPropertyValue('--color-severidad-media').trim(), estilo.getPropertyValue('--color-severidad-baja').trim()],
                borderColor: estilo.getPropertyValue('--color-superficie').trim(), borderWidth: 2 }]
        },
        options: { plugins: { legend: { position: 'bottom', labels: { padding: 16 } } } }
    });
    </script>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
