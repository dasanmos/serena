<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/ia.php';
require_once __DIR__ . '/../../src/helpers/texto_ia_cache.php';
require_once __DIR__ . '/../../src/modelos/Encuesta.php';
requiereRol('psicologo');
$encuestaId = (int)($_GET['id'] ?? 0);
$encuesta = Encuesta::buscarPorId($pdo, $encuestaId);
if (!$encuesta) { http_response_code(404); exit('Encuesta no encontrada.'); }
$preguntas = Encuesta::preguntas($pdo, $encuestaId);
$tituloPagina = 'Resultados';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Resultados: <?= htmlspecialchars($encuesta['titulo']) ?></h1>
<p><a href="encuesta_csv.php?id=<?= $encuestaId ?>" class="boton-secundario">📊 Exportar a Excel (CSV)</a></p>
<?php foreach ($preguntas as $p): ?>
<section class="panel-seccion" style="margin-bottom:16px">
  <h3><?= htmlspecialchars($p['texto']) ?></h3>
  <?php if ($p['tipo'] === 'opcion_multiple'): ?>
    <?php $c = $pdo->prepare("SELECT o.texto, COUNT(r.id) AS votos FROM opciones_pregunta o LEFT JOIN respuestas_encuesta r ON r.opcion_id=o.id WHERE o.pregunta_id=:pid GROUP BY o.id"); $c->execute(['pid'=>$p['id']]); $opciones = $c->fetchAll(); ?>
    <?php $totalVotos = array_sum(array_column($opciones,'votos')); ?>
    <?php foreach ($opciones as $o): $pct = $totalVotos ? round($o['votos']/$totalVotos*100) : 0; ?>
    <div class="u-mb-sm">
      <span><?= htmlspecialchars($o['texto']) ?> — <?= $pct ?>% (<?= $o['votos'] ?> votos)</span>
      <div class="barra-progreso u-mt-0"><div class="barra-progreso-relleno" style="width:<?= $pct ?>%"></div></div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <?php
    $c = $pdo->prepare("SELECT respuesta_texto FROM respuestas_encuesta WHERE pregunta_id=:pid AND respuesta_texto IS NOT NULL AND respuesta_texto != ''");
    $c->execute(['pid'=>$p['id']]);
    $textos = $c->fetchAll(PDO::FETCH_COLUMN);
    $totalRespuestas = count($textos);

    $resumenTemas = obtenerTextoIACacheado(
        $pdo,
        "resumen_encuesta_pregunta_{$p['id']}_{$totalRespuestas}",
        function () use ($totalRespuestas) {
            return $totalRespuestas > 0
                ? "Se recibieron {$totalRespuestas} respuesta" . ($totalRespuestas === 1 ? '' : 's') . " de texto libre para esta pregunta."
                : 'Todavía no hay respuestas de texto libre para esta pregunta.';
        },
        fn() => agruparTemasConIA($textos)
    );
    ?>
    <?php if ($totalRespuestas > 0): ?>
      <div class="tarjeta-analisis-mini">
        <strong>Temas frecuentes:</strong>
        <p class="efecto-escritura"><?= htmlspecialchars($resumenTemas) ?></p>
      </div>
    <?php endif; ?>
    <details class="u-mt-sm">
      <summary class="texto-suave">Ver las <?= $totalRespuestas ?> respuestas individuales</summary>
      <ul class="lista-simple"><?php foreach ($textos as $t): ?><li><?= htmlspecialchars($t) ?></li><?php endforeach; ?></ul>
    </details>
  <?php endif; ?>
</section>
<?php endforeach; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
