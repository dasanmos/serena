<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/helpers/censura.php';
requiereRol('docente');
$grupoId = $_SESSION['grupo_id'] ?? null;
$id = (int)($_GET['id'] ?? 0);
exigirUsuarioDeMiColegio($pdo, $id); // multi-colegio

$estudiante = Usuario::buscarPorId($pdo, $id);
// Control de acceso: el docente solo puede ver estudiantes de su propio grupo, y nunca datos emocionales
// (esos son exclusivos de psicólogo, orientador y administrador según la política de privacidad del proyecto).
if (!$estudiante || $estudiante['rol'] !== 'estudiante' || !$grupoId || (int)$estudiante['grupo_id'] !== (int)$grupoId) {
    require_once __DIR__ . '/../../src/helpers/acceso_denegado.php';
    mostrarAccesoDenegado('Solo puedes ver a los estudiantes de tu propio grupo.', 'No tienes acceso a este estudiante');
}

$metas = $pdo->prepare("SELECT titulo, estado, porcentaje_avance FROM metas WHERE usuario_id=:uid ORDER BY fecha_creacion DESC LIMIT 10");
$metas->execute(['uid' => $id]);
$metas = $metas->fetchAll();

$habitos = $pdo->prepare("SELECT nombre, frecuencia FROM habitos WHERE usuario_id=:uid AND activo=1 ORDER BY nombre");
$habitos->execute(['uid' => $id]);
$habitos = $habitos->fetchAll();

$tituloPagina = 'Detalle estudiante';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1><?= htmlspecialchars($estudiante['nombre']) ?></h1>
<p class="texto-suave nota-vista-docente">Vista de docente — no incluye registros emocionales ni de diario, que son confidenciales.</p>
<div class="columnas">
<section class="panel-seccion">
  <h2>Metas recientes</h2>
  <?php if (empty($metas)): ?><p>Sin metas registradas.</p>
  <?php else: ?>
  <ul class="lista-simple">
    <?php foreach ($metas as $m): ?>
      <li><?= htmlspecialchars(censurarTexto($m['titulo'])) ?> — <?= htmlspecialchars(ucfirst($m['estado'])) ?> (<?= (int)$m['porcentaje_avance'] ?>%)</li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<section class="panel-seccion">
  <h2>Hábitos activos</h2>
  <?php if (empty($habitos)): ?><p>Sin hábitos registrados.</p>
  <?php else: ?>
  <ul class="lista-simple">
    <?php foreach ($habitos as $h): ?>
      <li><?= htmlspecialchars(censurarTexto($h['nombre'])) ?> — <?= htmlspecialchars(ucfirst($h['frecuencia'])) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
</div>
<p class="u-mt-md"><small>¿Notas señales de alerta en este estudiante? Repórtalo a psicología u orientación — ellos tienen el contexto emocional completo para actuar.</small></p>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
