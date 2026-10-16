<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
requiereRol('estudiante');
// Recursos globales (colegio_id NULL, ej. líneas nacionales) + los propios del colegio del estudiante.
$colegioRecursos = colegioParaConsulta();
$recursos = $pdo->query("SELECT * FROM recursos_apoyo WHERE colegio_id IS NULL" . ($colegioRecursos ? " OR colegio_id = " . (int) $colegioRecursos : '') . " ORDER BY tipo, categoria")->fetchAll();
require_once __DIR__ . '/../../src/helpers/mapas.php';

// Lugares de ayuda con ubicación: globales (colegio_id NULL) + los de su colegio. Solo los visibles.
$lugares = [];
try {
    $q = $pdo->prepare('SELECT * FROM lugares_apoyo WHERE activo = 1 AND (colegio_id IS NULL OR colegio_id <=> ?) ORDER BY nombre');
    $q->execute([$colegioRecursos]);
    $lugares = $q->fetchAll();
} catch (PDOException $e) {
    $lugares = []; // la migración 032 aún no se aplicó: la página sigue funcionando sin el mapa
}
$tiposLugar = lugaresApoyoTipos();
$datosLugares = [];
foreach ($lugares as $l) {
    $datosLugares[] = [
        'id' => (int) $l['id'], 'nombre' => (string) $l['nombre'], 'tipo' => $tiposLugar[$l['tipo']] ?? 'Otro',
        'direccion' => (string) ($l['direccion'] ?? ''), 'telefono' => (string) ($l['telefono'] ?? ''),
        'lat' => (float) $l['latitud'], 'lng' => (float) $l['longitud'],
    ];
}

$tituloPagina = 'Recursos de apoyo';
$cssExtra = ['/assets/css/mapas.css'];
$jsExtra  = ['/assets/js/mapas.js'];
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Recursos de apoyo</h1>
<p class="subtitulo-apoyo">Aquí encontrarás artículos, ejercicios y contactos de ayuda para tu bienestar emocional.</p>

<div class="banner-ayuda-inmediata">
  <div>
    <strong>¿Necesitas hablar con alguien ya?</strong>
    <p class="texto-suave u-mb-0">Línea 106 (salud mental, gratuita, 24/7) o avisa ahora mismo a psicología/orientación.</p>
  </div>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <a href="ayuda_urgente.php" class="boton-linea-ayuda boton-peligro">🆘 Avisar ahora</a>
    <a href="tel:106" class="boton-linea-ayuda boton-acceso">📞 Llamar al 106</a>
  </div>
</div>

<section class="panel-seccion">
  <h2>Líneas de ayuda en Colombia</h2>
  <ul class="lista-simple lista-lineas-ayuda">
    <li>📞 <strong>Línea 106</strong> — Salud mental y bienestar (gratuita, 24/7)</li>
    <li>📞 <strong>Línea 155</strong> — Violencia de género</li>
    <li>📞 <strong>Línea 123</strong> — Emergencias</li>
    <li>📞 <strong>Línea 018000-113113</strong> — ICBF (menores de edad)</li>
  </ul>
</section>
<section class="panel-seccion" style="margin-top:20px">
  <h2>Dónde pedir ayuda cerca de ti</h2>
  <?php if (!$lugares): ?>
    <p class="texto-suave">Tu colegio todavía no ha cargado lugares de ayuda en el mapa. Mientras tanto, puedes llamar a las líneas de arriba o hablar con psicología u orientación.</p>
  <?php else: ?>
    <p class="texto-suave">Hospitales, centros de salud mental y entidades de protección. Puedes ordenarlos por cercanía: tu ubicación se calcula solo en tu dispositivo y no se guarda ni se envía a Serena.</p>
    <div class="mapa-bloque" data-mapa="apoyo">
      <div class="lugares-barra">
        <button type="button" class="boton-secundario" data-accion="cerca">📍 Ver los más cercanos a mí</button>
        <p class="mapa-estado" role="status" aria-live="polite"></p>
      </div>
      <div class="mapa-lienzo" aria-label="Mapa de lugares de ayuda"></div>
      <script type="application/json" class="mapa-datos"><?= jsonParaHtml($datosLugares) ?></script>
      <ul class="lugares-lista">
        <?php foreach ($lugares as $l): ?>
          <?php $tel = preg_replace('/[^0-9+]/', '', (string) ($l['telefono'] ?? '')); ?>
          <li class="lugar-tarjeta" data-lugar data-lat="<?= htmlspecialchars((string) $l['latitud']) ?>" data-lng="<?= htmlspecialchars((string) $l['longitud']) ?>">
            <span class="lugar-tipo"><?= htmlspecialchars($tiposLugar[$l['tipo']] ?? 'Otro') ?></span>
            <h3><?= htmlspecialchars((string) $l['nombre']) ?></h3>
            <?php if (!empty($l['direccion'])): ?><p class="lugar-dato">📍 <?= htmlspecialchars((string) $l['direccion']) ?><?= !empty($l['municipio']) ? ', ' . htmlspecialchars((string) $l['municipio']) : '' ?></p><?php endif; ?>
            <?php if (!empty($l['horario'])): ?><p class="lugar-dato">🕒 <?= htmlspecialchars((string) $l['horario']) ?></p><?php endif; ?>
            <p class="lugar-dato lugar-distancia" hidden></p>
            <div class="lugar-acciones">
              <?php if ($tel !== ''): ?><a href="tel:<?= htmlspecialchars($tel) ?>">📞 Llamar</a><?php endif; ?>
              <?= mapaComoLlegarHtml((float) $l['latitud'], (float) $l['longitud'], (string) $l['nombre']) ?>
              <button type="button" class="enlace-boton" data-ver-lugar="<?= (int) $l['id'] ?>">Ver en el mapa</button>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</section>
<section class="panel-seccion" style="margin-top:20px">
  <h2>Ejercicios de respiración</h2>
  <div class="columnas">
    <div class="ejercicio-respiracion">
      <h3>Respiración 4-7-8</h3>
      <div class="circulo-respiracion" data-ciclo="4-7-8" aria-hidden="true"><span>Inhala</span></div>
      <ol class="lista-simple"><li>Inhala 4 segundos</li><li>Mantén 7 segundos</li><li>Exhala 8 segundos</li></ol>
      <p>Repite 4 veces. Ideal para ansiedad y antes de dormir.</p>
    </div>
    <div class="ejercicio-respiracion">
      <h3>Respiración diafragmática</h3>
      <div class="circulo-respiracion circulo-lento" aria-hidden="true"><span>Respira</span></div>
      <ol class="lista-simple"><li>Pon una mano en el pecho, otra en el abdomen</li><li>Inhala por la nariz, hincha el abdomen (no el pecho)</li><li>Exhala lento por la boca</li></ol>
    </div>
  </div>
</section>
<?php if (!empty($recursos)): ?>
<section class="panel-seccion" style="margin-top:20px">
  <h2>Recursos adicionales</h2>
  <?php foreach ($recursos as $r): ?>
  <div class="fila-recurso-apoyo">
    <strong><?= htmlspecialchars($r['titulo']) ?></strong>
    <span class="pill pill-neutro"><?= ucfirst($r['tipo']) ?></span>
    <p class="descripcion-recurso"><?= htmlspecialchars($r['descripcion']) ?></p>
    <?php if ($r['url']): ?><a href="<?= htmlspecialchars($r['url']) ?>" target="_blank">Ver recurso →</a><?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
