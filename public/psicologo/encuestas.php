<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Encuesta.php';
requiereRol('psicologo');
$encuestas = Encuesta::listarParaRol($pdo, 'psicologo');
$tituloPagina = 'Encuestas';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-estadisticas">
  <h1>Encuestas</h1>
  <a href="encuesta_crear.php" class="boton-acceso">+ Nueva encuesta</a>
</div>
<?php if (empty($encuestas)): ?>
  <div class="estado-vacio">
    <p>No hay encuestas aún. Crea la primera.</p>
  </div>
<?php else: foreach ($encuestas as $e): ?>
  <div class="tarjeta-alerta">
    <div class="info-alerta">
      <span class="estudiante-alerta"><?= htmlspecialchars($e['titulo']) ?></span>
      <p class="fecha-alerta">Creada <?= date('d/m/Y', strtotime($e['fecha_creacion'])) ?>
        <?= $e['fecha_cierre'] ? ' · Cierra '.date('d/m/Y', strtotime($e['fecha_cierre'])) : '' ?></p>
    </div>
    <div class="acciones-solicitud">
      <a href="encuesta_resultados.php?id=<?= $e['id'] ?>" class="boton-acceso">Ver resultados</a>
    </div>
  </div>
<?php endforeach; endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
