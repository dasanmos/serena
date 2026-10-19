<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/helpers/analisis.php';
require_once __DIR__ . '/../../src/helpers/analisis_cache.php';
requiereRol('docente');
$grupoId = $_SESSION['grupo_id'] ?? null;
$textoAnalisis = obtenerAnalisisCacheado($pdo, (int) $_SESSION['usuario_id'], fn() => analisisDocente($pdo, $grupoId !== null ? (int) $grupoId : null));
$totalEst = $grupoId ? Usuario::contarEstudiantes($pdo, (int)$grupoId) : 0;

$activosInactivos = ['activos' => 0, 'inactivos' => 0];
if ($grupoId) {
    $c = $pdo->prepare(
        "SELECT SUM(ultimo_acceso >= NOW() - INTERVAL 7 DAY) AS activos, SUM(ultimo_acceso IS NULL OR ultimo_acceso < NOW() - INTERVAL 7 DAY) AS inactivos
         FROM usuarios u INNER JOIN roles r ON r.id=u.rol_id WHERE r.nombre='estudiante' AND u.grupo_id=:gid"
    );
    $c->execute(['gid' => $grupoId]);
    $fila = $c->fetch();
    $activosInactivos = ['activos' => (int) ($fila['activos'] ?? 0), 'inactivos' => (int) ($fila['inactivos'] ?? 0)];
}
$activosInactivosJson = json_encode($activosInactivos);

$tituloPagina = 'Panel Docente';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
  <h1><span class="efecto-escritura efecto-escritura-dorado">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span></h1>
  <p class="texto-suave">Panel de docente</p>
</div>
<?php $tituloAnalisis = 'Análisis de mi grupo'; require __DIR__ . '/../parciales/tarjeta_analisis.php'; ?>
<div class="tarjetas-resumen">
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">🎓</span><span class="tarjeta-numero"><?= $totalEst ?></span><span class="tarjeta-etiqueta">Estudiantes en mi grupo</span></div>
</div>

<?php if ($totalEst > 0): ?>
<section class="panel-seccion">
  <h2>Actividad en mi grupo (últimos 7 días)</h2>
  <canvas id="grafico-actividad-grupo" height="180"></canvas>
</section>
<?php endif; ?>

<div class="accesos-rapidos">
  <a href="<?= BASE_URL ?>/docente/grupo.php" class="boton-acceso">Ver mi grupo</a>
  <a href="<?= BASE_URL ?>/docente/pulso.php" class="boton-acceso">Pulso de grupo</a>
</div>

<?php if ($totalEst > 0):
    $chartJsLocal = __DIR__ . '/../assets/js/vendor/chart.min.js';
    $chartJsSrc = is_file($chartJsLocal) ? BASE_URL . '/assets/js/vendor/chart.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    ?>
    <script src="<?= $chartJsSrc ?>"></script>
    <script>
    const actividadData = <?= $activosInactivosJson ?>;
    new Chart(document.getElementById('grafico-actividad-grupo'), {
        type: 'doughnut',
        data: {
            labels: ['Activos esta semana', 'Sin actividad reciente'],
            datasets: [{ data: [actividadData.activos, actividadData.inactivos], backgroundColor: ['#3A7D44', '#D9B75A'] }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
    </script>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
