<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Encuesta.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
$encuestaId = (int)($_GET['id'] ?? 0);
$error = ''; $exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $encuestaId) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $enc = Encuesta::buscarPorId($pdo, $encuestaId);
    if ($enc && Encuesta::yaConcluida($pdo, $encuestaId)) {
        $error = 'Esta encuesta ya cerró y no acepta más respuestas.';
    } elseif ($enc && !Encuesta::usuarioYaRespondio($pdo, $encuestaId, $uid)) {
        $respuestas = [];
        foreach ($_POST['respuesta'] ?? [] as $pid => $resp) {
            $respuestas[(int)$pid] = is_array($resp) ? ['opcion_id' => (int)$resp] : ['texto' => $resp];
        }
        foreach ($_POST['opcion'] ?? [] as $pid => $oid) {
            $respuestas[(int)$pid] = ['opcion_id' => (int)$oid, 'texto' => null];
        }
        Encuesta::guardarRespuestas($pdo, $uid, $respuestas);
        $exito = 'Respuestas enviadas. ¡Gracias!';
    }
}

if ($encuestaId && !$exito) {
    $encuesta = Encuesta::buscarPorId($pdo, $encuestaId);
    $preguntas = Encuesta::preguntas($pdo, $encuestaId);
    $yaRespondio = Encuesta::usuarioYaRespondio($pdo, $encuestaId, $uid);
    $cerrada = Encuesta::yaConcluida($pdo, $encuestaId);
    $tituloPagina = $encuesta['titulo'] ?? 'Encuesta';
    require __DIR__ . '/../parciales/encabezado.php';
    echo $exito ? '<p class="mensaje mensaje-exito">'.htmlspecialchars($exito).'</p>' : '';
    echo $error ? '<p class="mensaje mensaje-error" role="alert">'.htmlspecialchars($error).'</p>' : '';
    if ($yaRespondio): ?><p class="mensaje mensaje-exito">Ya respondiste esta encuesta.</p><?php
    elseif ($cerrada): ?><p class="mensaje mensaje-error" role="alert">Esta encuesta ya cerró y no acepta más respuestas.</p><?php
    else: ?>
    <div class="cabecera-bloque-encuesta">
      <h1><?= htmlspecialchars($encuesta['titulo']) ?></h1>
      <span class="contador-bloque"><?= count($preguntas) ?> preguntas</span>
    </div>
    <?php if (!empty($encuesta['descripcion'])): ?>
      <p class="texto-suave u-mb-lg"><?= htmlspecialchars($encuesta['descripcion']) ?></p>
    <?php endif; ?>
    <form action="encuestas.php?id=<?= $encuestaId ?>" method="POST">
    <?php campoCSRF(); ?>
      <?php foreach ($preguntas as $indice => $p): ?>
      <div class="tarjeta-pregunta">
        <p class="numero-pregunta">Pregunta <?= $indice + 1 ?> de <?= count($preguntas) ?></p>
        <p class="texto-pregunta"><?= htmlspecialchars($p['texto']) ?></p>
        <?php if ($p['tipo'] === 'texto_libre'): ?>
          <textarea name="respuesta[<?= $p['id'] ?>]" rows="3"></textarea>
        <?php elseif ($p['tipo'] === 'escala'): ?>
          <div class="u-flex u-items-center u-gap-md">
            <input type="range" id="escala-<?= $p['id'] ?>" name="respuesta[<?= $p['id'] ?>]" min="1" max="10" value="5" class="slider-nivel u-flex" oninput="document.getElementById('valor-escala-<?= $p['id'] ?>').textContent = this.value">
            <span class="valor-slider" id="valor-escala-<?= $p['id'] ?>">5</span>
          </div>
        <?php else: foreach ($p['opciones'] as $o): ?>
          <label class="opcion-casilla">
            <input type="radio" name="opcion[<?= $p['id'] ?>]" value="<?= $o['id'] ?>" required>
            <?= htmlspecialchars($o['texto']) ?>
          </label>
        <?php endforeach; endif; ?>
      </div>
      <?php endforeach; ?>
      <button type="submit">Enviar respuestas</button>
    </form>
    <?php endif;
} else {
    $encuestas = Encuesta::listarParaRol($pdo, 'estudiante', $_SESSION['grupo_id'] ?? null);
    $tituloPagina = 'Encuestas';
    require __DIR__ . '/../parciales/encabezado.php';
    if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>
    <h1>Encuestas</h1>
    <?php if (empty($encuestas)): ?>
      <div class="estado-vacio">
        <p>No hay encuestas disponibles por el momento.</p>
      </div>
    <?php else: foreach ($encuestas as $e):
        $respondida = Encuesta::usuarioYaRespondio($pdo, $e['id'], $uid);
        $cerrada = !$respondida && Encuesta::yaConcluida($pdo, $e['id']); ?>
        <div class="tarjeta-alerta <?= $respondida ? 'atendida' : '' ?>">
          <div class="info-alerta">
            <span class="estudiante-alerta"><?= htmlspecialchars($e['titulo']) ?></span>
          </div>
          <div class="acciones-solicitud">
            <?php if ($respondida): ?>
              <span class="pill pill-exito">Respondida ✓</span>
            <?php elseif ($cerrada): ?>
              <span class="pill">Cerrada</span>
            <?php else: ?>
              <a href="encuestas.php?id=<?= $e['id'] ?>" class="boton-acceso">Responder</a>
            <?php endif; ?>
          </div>
        </div>
    <?php endforeach; endif;
}
require __DIR__ . '/../parciales/pie.php'; ?>
