<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/analisis.php';
require_once __DIR__ . '/../../src/helpers/analisis_cache.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';

requiereRol('administrador');

$textoAnalisis = obtenerAnalisisCacheado($pdo, (int) $_SESSION['usuario_id'], fn() => analisisAdministrador($pdo));

$totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE 1=1' . sqlColegio('colegio_id'))->fetchColumn();
$totalEstudiantes = Usuario::contarEstudiantes($pdo);
$totalGrupos = (int) $pdo->query('SELECT COUNT(*) FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE 1=1' . sqlColegio('gr.colegio_id'))->fetchColumn();
$solicitudesPendientes = (int) $pdo->query(
    "SELECT COUNT(*) FROM solicitudes_cambio WHERE estado = 'pendiente'" . sqlUsuarioDeColegio('usuario_id')
)->fetchColumn();

$estudiantesPorGrado = $pdo->query(
    "SELECT gr.nombre AS grado, COUNT(u.id) AS total
     FROM grados gr
     LEFT JOIN grupos g ON g.grado_id = gr.id
     LEFT JOIN usuarios u ON u.grupo_id = g.id
     WHERE 1=1" . sqlColegio('gr.colegio_id') . "
     GROUP BY gr.id, gr.nombre
     ORDER BY gr.nombre"
)->fetchAll();
$estudiantesPorGradoJson = json_encode($estudiantesPorGrado);

$tituloPagina = 'Panel de administrador';
require __DIR__ . '/../parciales/encabezado.php';
?>

<div class="cabecera-saludo">
  <h1><span class="efecto-escritura efecto-escritura-dorado">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span></h1>
  <p class="texto-suave">Panel de administrador</p>
</div>

<?php $tituloAnalisis = 'Análisis de la plataforma'; require __DIR__ . '/../parciales/tarjeta_analisis.php'; ?>

<div class="tarjetas-resumen">
    <div class="tarjeta">
        <span class="tarjeta-icono" aria-hidden="true">🧑‍💼</span>
        <span class="tarjeta-numero"><?= $totalUsuarios ?></span>
        <span class="tarjeta-etiqueta">Usuarios totales</span>
    </div>
    <div class="tarjeta">
        <span class="tarjeta-icono" aria-hidden="true">🎓</span>
        <span class="tarjeta-numero"><?= $totalEstudiantes ?></span>
        <span class="tarjeta-etiqueta">Estudiantes</span>
    </div>
    <div class="tarjeta">
        <span class="tarjeta-icono" aria-hidden="true">👥</span>
        <span class="tarjeta-numero"><?= $totalGrupos ?></span>
        <span class="tarjeta-etiqueta">Grupos</span>
    </div>
    <div class="tarjeta">
        <span class="tarjeta-icono" aria-hidden="true">📝</span>
        <span class="tarjeta-numero"><?= $solicitudesPendientes ?></span>
        <span class="tarjeta-etiqueta">Solicitudes pendientes</span>
    </div>
</div>

<section class="panel-seccion">
  <h2>Estudiantes por grado</h2>
  <?php if (empty($estudiantesPorGrado)): ?>
    <div class="estado-vacio"><p>Aún no hay grados ni estudiantes registrados.</p></div>
  <?php else: ?>
    <canvas id="grafico-estudiantes-grado" height="180"></canvas>
  <?php endif; ?>
</section>

<div class="accesos-rapidos">
    <a href="<?= BASE_URL ?>/administrador/usuarios.php" class="boton-acceso">Gestionar usuarios</a>
    <a href="<?= BASE_URL ?>/administrador/grupos.php" class="boton-acceso">Gestionar grupos</a>
</div>

<?php if (!empty($estudiantesPorGrado)):
    $chartJsLocal = __DIR__ . '/../assets/js/vendor/chart.min.js';
    $chartJsSrc = is_file($chartJsLocal) ? BASE_URL . '/assets/js/vendor/chart.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    ?>
    <script src="<?= $chartJsSrc ?>"></script>
    <script>
    const estudiantesGradoData = <?= $estudiantesPorGradoJson ?>;
    new Chart(document.getElementById('grafico-estudiantes-grado'), {
        type: 'bar',
        data: {
            labels: estudiantesGradoData.map(d => d.grado),
            datasets: [{ label: 'Estudiantes', data: estudiantesGradoData.map(d => d.total),
                backgroundColor: '#E8B923', borderRadius: 6 }]
        },
        options: { plugins: { legend: { display: false } } }
    });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
