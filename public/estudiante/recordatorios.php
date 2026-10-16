<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Recordatorio.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
$error = ''; $exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        if (empty($_POST['hora'])) {
            $error = 'Elige una hora.';
        } else {
            Recordatorio::crear($pdo, $uid, [
                'tipo'        => $_POST['tipo'] ?? 'personalizado',
                'hora'        => $_POST['hora'],
                'dias_semana' => $_POST['dias_semana'] ?? [],
            ]);
            $exito = 'Recordatorio creado.';
        }
    } elseif ($accion === 'alternar') {
        $id = (int)($_POST['id'] ?? 0);
        if (Recordatorio::buscarPorId($pdo, $id, $uid)) {
            Recordatorio::alternarActivo($pdo, $id, $uid);
            $exito = 'Actualizado.';
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if (Recordatorio::buscarPorId($pdo, $id, $uid)) {
            Recordatorio::eliminar($pdo, $id, $uid);
            $exito = 'Recordatorio eliminado.';
        }
    }
}

$recordatorios = Recordatorio::listarDeUsuario($pdo, $uid);
$etiquetasTipo = ['habito' => '🔥 Hábito', 'sueno' => '🌙 Sueño', 'diario' => '📓 Diario', 'meta' => '🎯 Meta', 'personalizado' => '✏️ Personalizado'];
$etiquetasDia = ['lun' => 'Lun', 'mar' => 'Mar', 'mie' => 'Mié', 'jue' => 'Jue', 'vie' => 'Vie', 'sab' => 'Sáb', 'dom' => 'Dom'];
$tituloPagina = 'Recordatorios';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Recordatorios</h1>

<div class="recordatorio-criterio-humano">
  <span aria-hidden="true">ℹ️</span>
  <span>Estos recordatorios son una lista personal, no una alarma automática: te los mostramos aquí y en tu inicio mientras tengas Serena abierto ese día, pero todavía no enviamos notificaciones fuera de la página.</span>
</div>

<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<div class="columnas">
<section class="panel-seccion">
  <h2>Nuevo recordatorio</h2>
  <form action="recordatorios.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="crear">
    <label for="tipo">¿De qué es?</label>
    <select id="tipo" name="tipo">
      <?php foreach ($etiquetasTipo as $valor => $etiqueta): ?>
        <option value="<?= $valor ?>"><?= $etiqueta ?></option>
      <?php endforeach; ?>
    </select>
    <label for="hora">Hora</label>
    <input type="time" id="hora" name="hora" value="20:00" required>
    <label>Días</label>
    <div class="selector-dias-semana">
      <?php foreach ($etiquetasDia as $v => $l): ?>
        <label>
          <input type="checkbox" name="dias_semana[]" value="<?= $v ?>" checked>
          <span class="dia-semana-boton"><?= $l ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <button type="submit">Crear recordatorio</button>
  </form>
</section>

<section class="panel-seccion">
  <h2>Tus recordatorios</h2>
  <?php if (empty($recordatorios)): ?>
    <div class="estado-vacio">
      <p>Todavía no tienes recordatorios. Crea el primero.</p>
    </div>
  <?php else: ?>
    <ul class="lista-simple">
      <?php foreach ($recordatorios as $r): ?>
        <li class="<?= $r['activo'] ? '' : 'texto-suave' ?>">
          <div class="u-flex u-items-center u-justify-between u-gap-md">
            <span>
              <strong><?= $etiquetasTipo[$r['tipo']] ?? ucfirst($r['tipo']) ?></strong>
              — <?= substr($r['hora'], 0, 5) ?>
              <br>
              <small class="texto-suave">
                <?php
                $diasFila = explode(',', (string)$r['dias_semana']);
                echo implode(', ', array_map(fn($d) => $etiquetasDia[$d] ?? $d, $diasFila));
                ?>
              </small>
            </span>
            <span class="u-flex u-gap-sm">
              <form action="recordatorios.php" method="POST" style="display:inline">
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="alternar">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit" class="boton-secundario"><?= $r['activo'] ? 'Pausar' : 'Activar' ?></button>
              </form>
              <form action="recordatorios.php" method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este recordatorio?')">
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit" class="boton-peligro">Eliminar</button>
              </form>
            </span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
</div>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
