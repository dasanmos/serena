<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/helpers/analisis.php';
require_once __DIR__ . '/../../src/helpers/analisis_cache.php';
requiereRol('rector');
$textoAnalisis = obtenerAnalisisCacheado($pdo, (int) $_SESSION['usuario_id'], fn() => analisisRector($pdo));
$totalEst = Usuario::contarEstudiantes($pdo);
$totalUsuarios = (int)$pdo->query('SELECT COUNT(*) FROM usuarios WHERE 1=1' . sqlColegio('colegio_id'))->fetchColumn();
$promedioEmo = $pdo->query("SELECT AVG(nivel) FROM estado_emocional WHERE fecha_hora >= NOW()-INTERVAL 30 DAY" . sqlUsuarioDeColegio('usuario_id'))->fetchColumn();

$usuariosPorRol = $pdo->query(
    "SELECT r.nombre AS rol, COUNT(*) AS total FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE 1=1" . sqlColegio('u.colegio_id') . "
     GROUP BY r.nombre ORDER BY total DESC"
)->fetchAll();
$usuariosPorRolJson = json_encode($usuariosPorRol);

$tituloPagina = 'Panel Rector';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
  <h1><span class="efecto-escritura efecto-escritura-dorado">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span></h1>
  <p class="texto-suave">Panel institucional</p>
</div>
<?php $tituloAnalisis = 'Análisis institucional'; require __DIR__ . '/../parciales/tarjeta_analisis.php'; ?>
<div class="tarjetas-resumen">
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">🎓</span><span class="tarjeta-numero"><?= $totalEst ?></span><span class="tarjeta-etiqueta">Estudiantes activos</span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">🧑‍💼</span><span class="tarjeta-numero"><?= $totalUsuarios ?></span><span class="tarjeta-etiqueta">Usuarios totales</span></div>
  <div class="tarjeta"><span class="tarjeta-icono" aria-hidden="true">💛</span><span class="tarjeta-numero"><?= $promedioEmo ? round((float)$promedioEmo,1) : '—' ?></span><span class="tarjeta-etiqueta">Nivel emocional promedio (30 días)</span></div>
</div>

<section class="panel-seccion">
  <h2>Usuarios por rol</h2>
  <?php if (empty($usuariosPorRol)): ?>
    <div class="estado-vacio"><p>Sin usuarios registrados todavía.</p></div>
  <?php else: ?>
    <canvas id="grafico-usuarios-rol" height="180"></canvas>
  <?php endif; ?>
</section>

<div class="aviso-confidencialidad">
  Vista agregada — los datos individuales son confidenciales y solo visibles para psicólogos y orientadores.
</div>

<?php if (!empty($usuariosPorRol)):
    $chartJsLocal = __DIR__ . '/../assets/js/vendor/chart.min.js';
    $chartJsSrc = is_file($chartJsLocal) ? BASE_URL . '/assets/js/vendor/chart.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
    ?>
    <script src="<?= $chartJsSrc ?>"></script>
    <script>
    const usuariosRolData = <?= $usuariosPorRolJson ?>;
    const PALETA_ROL = ['#E8B923', '#F0C63E', '#F9D976', '#C9A227', '#8A6A00', '#D9B75A', '#B08D1E'];
    new Chart(document.getElementById('grafico-usuarios-rol'), {
        type: 'bar',
        data: {
            labels: usuariosRolData.map(d => d.rol.charAt(0).toUpperCase() + d.rol.slice(1)),
            datasets: [{ label: 'Usuarios', data: usuariosRolData.map(d => d.total),
                backgroundColor: PALETA_ROL, borderRadius: 6 }]
        },
        options: { indexAxis: 'y', plugins: { legend: { display: false } } }
    });
    </script>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
