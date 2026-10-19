<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/AlertaTemprana.php';
require_once __DIR__ . '/../../src/helpers/analisis.php';
require_once __DIR__ . '/../../src/helpers/analisis_cache.php';
requiereRol('orientador');
$uid = (int)$_SESSION['usuario_id'];
$gruposIds = $_SESSION['grupos_ids'] ?? [];
$textoAnalisis = obtenerAnalisisCacheado($pdo, $uid, fn() => analisisOrientador($pdo, $gruposIds));
$misGrupos = [];
$totalEstudiantes = 0;
if (!empty($gruposIds)) {
    $marcadores = implode(',', array_fill(0, count($gruposIds), '?'));
    $c = $pdo->prepare(
        "SELECT g.id, g.nombre, gr.nombre AS grado, COUNT(u.id) AS total_estudiantes
         FROM grupos g
         INNER JOIN grados gr ON gr.id = g.grado_id
         LEFT JOIN usuarios u ON u.grupo_id = g.id
         WHERE g.id IN ({$marcadores})
         GROUP BY g.id ORDER BY gr.nombre, g.nombre"
    );
    $c->execute($gruposIds);
    $misGrupos = $c->fetchAll();
    $totalEstudiantes = array_sum(array_column($misGrupos, 'total_estudiantes'));
}
$alertas = AlertaTemprana::listarNoAtendidas($pdo, $gruposIds);
$conteoPorNivel = ['alto' => 0, 'medio' => 0, 'bajo' => 0];
foreach ($alertas as $a) { $conteoPorNivel[$a['nivel_urgencia']]++; }
$conteoPorNivelJson = json_encode($conteoPorNivel);
$tituloPagina = 'Panel Orientador';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
  <h1><span class="efecto-escritura efecto-escritura-dorado">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span></h1>
  <p class="texto-suave">Panel de orientador</p>
</div>
<?php $tituloAnalisis = 'Análisis de mis grupos'; require __DIR__ . '/../parciales/tarjeta_analisis.php'; ?>
<?php if (!empty($misGrupos)): ?>
<div class="tarjetas-resumen">
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">👥</span><span class="tarjeta-numero"><?= count($misGrupos) === 1 ? htmlspecialchars($misGrupos[0]['grado'] . ' ' . $misGrupos[0]['nombre']) : count($misGrupos) . ' grupos' ?></span><span class="tarjeta-etiqueta"><?= count($misGrupos) === 1 ? 'Mi grupo' : 'Mis grupos' ?></span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">🎓</span><span class="tarjeta-numero"><?= $totalEstudiantes ?></span><span class="tarjeta-etiqueta">Estudiantes</span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">⚠️</span><span class="tarjeta-numero"><?= count($alertas) ?></span><span class="tarjeta-etiqueta">Alertas pendientes</span></div>
</div>
<?php if (count($misGrupos) > 1): ?>
<p class="texto-suave" style="margin-top:-8px">Grupos a cargo: <?= htmlspecialchars(implode(', ', array_map(fn($g) => $g['grado'] . ' ' . $g['nombre'], $misGrupos))) ?></p>
<?php endif; ?>
<?php else: ?>
<div class="estado-vacio">
  <p>Todavía no tienes ningún grupo asignado. Contacta al administrador.</p>
</div>
<?php endif; ?>
<div class="accesos-rapidos">
  <a href="<?= BASE_URL ?>/orientador/grupo.php" class="boton-acceso">Ver <?= count($misGrupos) === 1 ? 'mi grupo' : 'mis grupos' ?></a>
  <a href="<?= BASE_URL ?>/orientador/alertas.php" class="boton-acceso">Alertas</a>
  <a href="<?= BASE_URL ?>/orientador/seguimientos.php" class="boton-acceso">Mis seguimientos</a>
  <a href="<?= BASE_URL ?>/orientador/mensajes.php" class="boton-acceso">Mensajes</a>
</div>

<?php if (!empty($alertas)): ?>
<section class="panel-seccion">
  <h2>Alertas pendientes por urgencia</h2>
  <canvas id="grafico-alertas-orientador" height="180"></canvas>
</section>
<?php
    $chartJsLocal = __DIR__ . '/../assets/js/vendor/chart.min.js';
    $chartJsSrc = is_file($chartJsLocal) ? BASE_URL . '/assets/js/vendor/chart.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
?>
<script src="<?= $chartJsSrc ?>"></script>
<script>
const nivelDataOrientador = <?= $conteoPorNivelJson ?>;
new Chart(document.getElementById('grafico-alertas-orientador'), {
    type: 'doughnut',
    data: {
        labels: ['Urgencia alta', 'Urgencia media', 'Urgencia baja'],
        datasets: [{ data: [nivelDataOrientador.alto, nivelDataOrientador.medio, nivelDataOrientador.bajo],
            backgroundColor: ['#C0392B', '#E8B923', '#3A7D44'] }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
