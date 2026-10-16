<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Habito.php';
require_once __DIR__ . '/../../src/modelos/Insignia.php';
require_once __DIR__ . '/../../src/modelos/Meta.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Notificacion.php';
require_once __DIR__ . '/../../src/helpers/gamificacion.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
$error = ''; $exito = '';
$categorias = ['salud','estudio','mental','social','deporte','alimentación','otro'];
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    if ($accion === 'crear') {
        $datos = ['nombre' => trim($_POST['nombre'] ?? ''), 'categoria' => $_POST['categoria'] ?? '',
                  'frecuencia' => $_POST['frecuencia'] ?? 'diaria', 'dias_semana' => implode(',', $_POST['dias_semana'] ?? [])];
        if (empty($datos['nombre'])) $error = 'El nombre es obligatorio.';
        else { Habito::crear($pdo, $uid, $datos); $exito = 'Hábito creado.'; }
    } elseif ($accion === 'marcar') {
        $hid = (int)($_POST['habito_id'] ?? 0);
        $completado = isset($_POST['completado']);
        $h = Habito::buscarPorId($pdo, $hid, $uid);
        if ($h) {
            Habito::marcarCompletado($pdo, $hid, date('Y-m-d'), $completado);
            $exito = 'Hábito actualizado.';
            if ($completado) evaluarInsignias($pdo, $uid);
        }
    } elseif ($accion === 'archivar') {
        $hid = (int)($_POST['habito_id'] ?? 0);
        $h = Habito::buscarPorId($pdo, $hid, $uid);
        if ($h) { Habito::archivar($pdo, $hid); $exito = 'Hábito archivado.'; }
    }
}

$diasAbrev = ['lun','mar','mie','jue','vie','sab','dom'];
$diaHoy = $diasAbrev[(int)date('N') - 1];
$habitos = Habito::listarDeUsuario($pdo, $uid);
foreach ($habitos as &$h) {
    $c = $pdo->prepare("SELECT completado FROM seguimiento_habitos WHERE habito_id=:hid AND fecha=CURDATE() LIMIT 1");
    $c->execute(['hid' => $h['id']]);
    $h['completado_hoy'] = (int)($c->fetchColumn() ?? 0);
    $h['racha'] = Habito::rachaActual($pdo, $h['id']);
    $registrosMes = Habito::historialMensual($pdo, $h['id'], (int)date('Y'), (int)date('m'));
    $h['historial_mes'] = [];
    foreach ($registrosMes as $reg) { $h['historial_mes'][(int)date('j', strtotime($reg['fecha']))] = (int)$reg['completado']; }
}
unset($h);

$tituloPagina = 'Hábitos';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Mis hábitos</h1>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>
<div class="columnas">
<section class="panel-seccion">
  <h2>Nuevo hábito</h2>
  <form action="habitos.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="crear">
    <label>Nombre</label>
    <input type="text" name="nombre" required>
    <label>Categoría</label>
    <select name="categoria">
      <?php foreach ($categorias as $cat): ?><option value="<?= $cat ?>"><?= ucfirst($cat) ?></option><?php endforeach; ?>
    </select>
    <label>Frecuencia</label>
    <select name="frecuencia" id="frecuencia" onchange="document.getElementById('dias-custom').style.display=this.value==='personalizada'?'block':'none'">
      <option value="diaria">Diaria</option>
      <option value="semanal">Semanal (todos los días habilitados)</option>
      <option value="personalizada">Personalizada</option>
    </select>
    <div id="dias-custom" class="selector-dias-semana" style="display:none">
      <?php foreach (['lun'=>'Lun','mar'=>'Mar','mie'=>'Mié','jue'=>'Jue','vie'=>'Vie','sab'=>'Sáb','dom'=>'Dom'] as $v=>$l): ?>
        <label>
          <input type="checkbox" name="dias_semana[]" value="<?= $v ?>">
          <span class="dia-semana-boton"><?= $l ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <button type="submit">Crear hábito</button>
  </form>
</section>
<section class="panel-seccion">
  <h2>Hoy — <?= date('d/m/Y') ?></h2>
  <?php if (empty($habitos)): ?>
    <div class="estado-vacio">
      <img src="<?= BASE_URL ?>/assets/img/recursos/estado_vacio_habitos.png" alt="">
      <p>Aún no tienes hábitos. ¡Crea el primero!</p>
    </div>
  <?php else: ?>
    <?php foreach ($habitos as $h): ?>
    <?php $activo = $h['frecuencia'] === 'diaria' || $h['frecuencia'] === 'semanal' || ($h['frecuencia'] === 'personalizada' && in_array($diaHoy, explode(',', $h['dias_semana'] ?? ''))); ?>
    <div class="fila-habito">
    <div class="tarjeta-habito">
      <div>
        <strong><?= htmlspecialchars($h['nombre']) ?></strong>
        <small style="color:var(--color-texto-suave)"> · <?= ucfirst($h['categoria']) ?> ·
          <span class="racha" data-dias="<?= (int)$h['racha'] ?>"><span class="racha-fuego">🔥</span> <?= (int)$h['racha'] ?> días</span>
        </small>
      </div>
      <?php if ($activo): ?>
      <form action="habitos.php" method="POST">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="marcar">
        <input type="hidden" name="habito_id" value="<?= $h['id'] ?>">
        <?php if (!$h['completado_hoy']): ?>
          <input type="hidden" name="completado" value="1">
          <button type="submit" class="boton-acceso">✓ Completar</button>
        <?php else: ?>
          <button type="submit" class="boton-secundario">↩ Desmarcar</button>
        <?php endif; ?>
      </form>
      <?php else: ?><small style="color:var(--color-texto-suave)">No aplica hoy</small><?php endif; ?>
    </div>
    <details class="historial-mensual">
      <summary>Historial de este mes</summary>
      <div class="grilla-historial">
        <?php
        $diasEnMes = (int)date('t');
        for ($dia = 1; $dia <= $diasEnMes; $dia++):
          $estado = $h['historial_mes'][$dia] ?? null;
          $clase = $estado === 1 ? 'dia-cumplido' : ($estado === 0 ? 'dia-fallido' : 'dia-sin-registro');
        ?>
          <span class="dia-historial <?= $clase ?>" title="<?= $dia ?>/<?= date('m') ?>"><?= $dia ?></span>
        <?php endfor; ?>
      </div>
    </details>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
</div>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
