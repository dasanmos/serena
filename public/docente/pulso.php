<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Pulso.php';
requiereRol('docente');

$grupoId = $_SESSION['grupo_id'] ?? null;
$mensaje = '';

if ($grupoId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $pregunta = trim((string) ($_POST['pregunta'] ?? ''));
        if ($pregunta === '' && !empty($_POST['pregunta_sugerida'])) {
            $pregunta = (string) $_POST['pregunta_sugerida'];
        }
        $pregunta = mb_substr(trim($pregunta), 0, 255);
        if ($pregunta !== '') {
            Pulso::crear($pdo, (int) $_SESSION['usuario_id'], (int) $grupoId, $pregunta);
            $mensaje = 'creado';
        }
    } elseif ($accion === 'cerrar') {
        Pulso::cerrarManual($pdo, (int) ($_POST['pulso_id'] ?? 0), (int) $_SESSION['usuario_id']);
        $mensaje = 'cerrado';
    }
    header('Location: pulso.php?ok=' . $mensaje);
    exit;
}

$activo = $grupoId ? Pulso::activoDeGrupo($pdo, (int) $grupoId) : null;
$historial = $grupoId ? Pulso::historialDeGrupo($pdo, (int) $grupoId) : [];

$tituloPagina = 'Pulso de grupo';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Pulso de grupo</h1>
<p class="texto-suave">
    Una pregunta rápida para todo tu curso — responderla toma unos 10 segundos.
    Solo ves el promedio y cuántos respondieron cada opción, nunca quién respondió
    qué: esto es para sentir el ambiente del grupo, no para reemplazar las alertas
    de psicología/orientación, que trabajan con lo individual.
</p>

<?php if (!$grupoId): ?>
    <div class="estado-vacio"><p>Todavía no tienes un grupo asignado. Contacta al administrador.</p></div>
<?php else: ?>

<?php if ($activo): ?>
<section class="panel-seccion">
  <h2>Pulso activo</h2>
  <p><strong><?= htmlspecialchars($activo['pregunta']) ?></strong></p>
  <p class="texto-suave">
    Abierto desde el <?= date('d/m/Y', strtotime($activo['fecha_creacion'])) ?>,
    se cierra solo el <?= date('d/m/Y', strtotime($activo['fecha_cierre'])) ?>.
  </p>
  <?php $resultadosActivo = Pulso::resultados($pdo, (int) $activo['id']); ?>
  <?php if ($resultadosActivo['insuficiente']): ?>
    <p class="texto-suave">
      <?= $resultadosActivo['total'] ?> de <?= Pulso::RESPUESTAS_MINIMAS_PARA_VER ?> respuestas necesarias para
      mostrar un resultado sin arriesgar que se identifique a alguien.
    </p>
  <?php else: ?>
    <?php require __DIR__ . '/../parciales/resultado_pulso.php'; ?>
  <?php endif; ?>
  <form action="pulso.php" method="POST" onsubmit="return confirm('¿Cerrar este pulso antes de tiempo?')" class="u-mt-sm">
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="cerrar">
    <input type="hidden" name="pulso_id" value="<?= $activo['id'] ?>">
    <button type="submit" class="boton-secundario">Cerrar ahora</button>
  </form>
</section>
<?php else: ?>
<section class="panel-seccion">
  <h2>Lanzar un pulso nuevo</h2>
  <form action="pulso.php" method="POST">
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="crear">
    <label for="pregunta-sugerida">Pregunta</label>
    <select id="pregunta-sugerida" name="pregunta_sugerida" onchange="document.getElementById('pregunta-personalizada').value = this.value !== '__custom__' ? this.value : '';">
      <?php foreach (Pulso::PREGUNTAS_SUGERIDAS as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
      <?php endforeach; ?>
      <option value="__custom__">Otra (escríbela abajo)</option>
    </select>
    <label for="pregunta-personalizada">O escribe la tuya</label>
    <input type="text" id="pregunta-personalizada" name="pregunta" maxlength="255" placeholder="Deja en blanco para usar la de arriba">
    <p class="texto-suave">Se responde en una escala de 1 (nada) a 5 (mucho). Queda abierto <?= Pulso::DURACION_DIAS ?> días, o hasta que lo cierres a mano.</p>
    <button type="submit" class="boton-acceso u-mt-sm" data-sonido="clic">Lanzar pulso</button>
  </form>
</section>
<?php endif; ?>

<?php if (!empty($historial)): ?>
<section class="panel-seccion">
  <h2>Historial</h2>
  <ul class="lista-historial-pulso">
    <?php foreach ($historial as $h): if ((int) $h['id'] === (int) ($activo['id'] ?? 0)) continue; ?>
      <?php $r = Pulso::resultados($pdo, (int) $h['id']); ?>
      <li>
        <strong><?= htmlspecialchars($h['pregunta']) ?></strong>
        <span class="texto-suave"><?= date('d/m/Y', strtotime($h['fecha_creacion'])) ?></span>
        <?php if ($r['insuficiente']): ?>
          <p class="texto-suave">Sin suficientes respuestas (<?= $r['total'] ?>) para mostrar un resultado.</p>
        <?php else: ?>
          <p>Promedio <?= number_format($r['promedio'], 1) ?> / 5 — <?= $r['total'] ?> respuestas</p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
