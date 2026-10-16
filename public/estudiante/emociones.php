<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/AlertaTemprana.php';
require_once __DIR__ . '/../../src/modelos/Notificacion.php';
require_once __DIR__ . '/../../src/helpers/notificador.php';

requiereRol('estudiante');
$usuarioId = (int)$_SESSION['usuario_id'];
$emociones = ['feliz','tranquilo','motivado','ansioso','triste','cansado','estresado','enojado','confundido','agradecido'];
$error = ''; $exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $datos = [
        'emocion'     => $_POST['emocion'] ?? '',
        'nivel'       => (int)($_POST['nivel'] ?? 3),
        'nota'        => trim($_POST['nota'] ?? ''),
        'momento_dia' => $_POST['momento_dia'] ?? 'mañana',
    ];
    if (!in_array($datos['emocion'], $emociones)) $error = 'Selecciona una emoción.';
    elseif ($datos['nivel'] < 1 || $datos['nivel'] > 5) $error = 'Nivel inválido.';
    else {
        EstadoEmocional::registrar($pdo, $usuarioId, $datos);
        verificarAlertasEmocionales($pdo, $usuarioId);
        $exito = 'Emoción registrada correctamente.';
    }
}

$registros = EstadoEmocional::listarDeUsuario($pdo, $usuarioId, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$tituloPagina = 'Emociones';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Registro emocional</h1>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<div class="columnas">
<section class="panel-seccion">
  <h2>¿Cómo te sientes ahora?</h2>
  <form action="emociones.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <label>Emoción</label>
    <div class="grilla-emociones">
      <?php foreach ($emociones as $e): ?>
        <label class="btn-emocion <?= ($_POST['emocion'] ?? '') === $e ? 'activa' : '' ?>">
          <input type="radio" name="emocion" value="<?= $e ?>" <?= ($_POST['emocion'] ?? '') === $e ? 'checked' : '' ?>> <?= ucfirst($e) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <label for="nivel">Intensidad (1 = leve, 5 = muy intenso) <span class="valor-slider" id="valor-nivel"><?= htmlspecialchars($_POST['nivel'] ?? '3') ?></span></label>
    <input type="range" id="nivel" name="nivel" min="1" max="5" value="<?= htmlspecialchars($_POST['nivel'] ?? '3') ?>" class="slider-nivel" oninput="document.getElementById('valor-nivel').textContent = this.value">
    <label for="momento_dia">Momento del día</label>
    <select id="momento_dia" name="momento_dia">
      <?php foreach (['mañana','tarde','noche'] as $m): ?>
        <option value="<?= $m ?>" <?= ($_POST['momento_dia'] ?? '') === $m ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="nota">Nota (opcional)</label>
    <textarea id="nota" name="nota" rows="3" placeholder="¿Qué lo provocó? ¿Cómo te afecta?"><?= htmlspecialchars($_POST['nota'] ?? '') ?></textarea>
    <button type="submit">Guardar</button>
  </form>
</section>

<section class="panel-seccion">
  <h2>Últimos 30 días</h2>
  <?php if (empty($registros)): ?>
    <div class="estado-vacio">
      <p>Aún no tienes registros. Cuéntanos cómo te sientes hoy.</p>
    </div>
  <?php else: ?>
    <ul class="lista-simple">
      <?php foreach (array_slice($registros, 0, 10) as $r): ?>
        <li>
          <strong><?= htmlspecialchars(ucfirst($r['emocion'])) ?></strong>
          — nivel <?= $r['nivel'] ?> — <?= htmlspecialchars($r['momento_dia']) ?>
          <small><?= date('d/m H:i', strtotime($r['fecha_hora'])) ?></small>
          <?php if ($r['nota']): ?><br><em><?= htmlspecialchars(mb_substr($r['nota'], 0, 80)) ?></em><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
</div>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
