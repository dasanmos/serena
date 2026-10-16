<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Academico.php';

requiereRol('secretaria_academica');
$colegioId = colegioParaCrear();
$r = Academico::resumen($pdo, $colegioId);
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Panel de secretaría académica';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
  <h1><span class="efecto-escritura efecto-escritura-dorado">Hola, <?= $e($_SESSION['nombre']) ?></span></h1>
  <p class="texto-suave">Secretaría académica<?= $r['anio'] ? ' · año lectivo ' . (int) $r['anio']['anio'] : '' ?></p>
</div>

<?php if (!$r['anio']): ?>
  <p class="mensaje mensaje-aviso">Todavía no hay un año lectivo en curso. Créalo y márcalo como actual en <a href="<?= $e(BASE_URL) ?>/administrador/anios_lectivos.php">Años lectivos</a>.</p>
<?php endif; ?>

<div class="tarjetas-resumen">
  <a class="tarjeta" href="<?= $e(BASE_URL) ?>/secretaria_academica/inscripciones.php?estado=pendiente" style="text-decoration:none;color:inherit">
    <span class="tarjeta-icono" aria-hidden="true">📝</span><span class="tarjeta-numero"><?= $r['inscripciones_por_revisar'] ?></span><span class="tarjeta-etiqueta">Inscripciones por revisar</span></a>
  <a class="tarjeta" href="<?= $e(BASE_URL) ?>/secretaria_academica/inscripciones.php?estado=admitida" style="text-decoration:none;color:inherit">
    <span class="tarjeta-icono" aria-hidden="true">✅</span><span class="tarjeta-numero"><?= $r['inscripciones_admitidas'] ?></span><span class="tarjeta-etiqueta">Admitidas por matricular</span></a>
  <a class="tarjeta" href="<?= $e(BASE_URL) ?>/secretaria_academica/matriculas.php" style="text-decoration:none;color:inherit">
    <span class="tarjeta-icono" aria-hidden="true">🎓</span><span class="tarjeta-numero"><?= $r['matriculas_activas'] ?></span><span class="tarjeta-etiqueta">Matrículas activas</span></a>
  <a class="tarjeta" href="<?= $e(BASE_URL) ?>/secretaria_academica/matriculas.php?estado=pendiente" style="text-decoration:none;color:inherit">
    <span class="tarjeta-icono" aria-hidden="true">🔁</span><span class="tarjeta-numero"><?= $r['renovaciones_pendientes'] ?></span><span class="tarjeta-etiqueta">Renovaciones por confirmar</span></a>
</div>

<section class="panel-seccion">
  <h2>Accesos rápidos</h2>
  <ul class="lista-simple">
    <li><a href="<?= $e(BASE_URL) ?>/secretaria_academica/inscripciones.php">Bandeja de inscripciones</a></li>
    <li><a href="<?= $e(BASE_URL) ?>/secretaria_academica/matriculas.php">Matrículas y renovación</a></li>
    <li><a href="<?= $e(BASE_URL) ?>/administrador/anios_lectivos.php">Años lectivos y períodos</a></li>
    <li><a href="<?= $e(BASE_URL) ?>/inscripcion.php" target="_blank" rel="noopener">Formulario público de inscripción</a> (compártelo con las familias)</li>
  </ul>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
