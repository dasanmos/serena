<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Meta.php';
require_once __DIR__ . '/../../src/modelos/Habito.php';
require_once __DIR__ . '/../../src/modelos/Insignia.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Notificacion.php';
require_once __DIR__ . '/../../src/helpers/gamificacion.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
$error = ''; $exito = '';
$accion = $_POST['accion'] ?? '';

Meta::marcarVencidas($pdo, $uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    if ($accion === 'crear') {
        $datos = ['titulo' => trim($_POST['titulo'] ?? ''), 'descripcion' => trim($_POST['descripcion'] ?? ''),
                  'tipo' => $_POST['tipo'] ?? 'corto_plazo', 'fecha_limite' => $_POST['fecha_limite'] ?? '',
                  'habito_id' => $_POST['habito_id'] ?: null,
                  'subtareas' => array_filter(array_map('trim', explode("\n", $_POST['subtareas'] ?? '')))];
        if (empty($datos['titulo'])) $error = 'El título es obligatorio.';
        else { Meta::crear($pdo, $uid, $datos); $exito = 'Meta creada.'; }
    } elseif ($accion === 'toggle_subtarea') {
        $sid = (int)($_POST['subtarea_id'] ?? 0);
        // El meta_id real se obtiene siempre desde la BD verificando propiedad; nunca se confía en el meta_id del POST.
        $midVerificado = Meta::metaIdDeSubtarea($pdo, $sid, $uid);
        if ($midVerificado && Meta::toggleSubtarea($pdo, $sid, $uid)) {
            Meta::recalcularAvance($pdo, $midVerificado);
            $exito = 'Actualizado.';
        }
    } elseif ($accion === 'eliminar') {
        $mid = (int)($_POST['meta_id'] ?? 0);
        $meta = Meta::buscarPorId($pdo, $mid, $uid);
        if ($meta) { Meta::eliminar($pdo, $mid); $exito = 'Meta eliminada.'; }
    } elseif ($accion === 'continuar_vencida') {
        $mid = (int)($_POST['meta_id'] ?? 0);
        $nuevaFecha = $_POST['nueva_fecha_limite'] ?? null;
        if (Meta::continuar($pdo, $mid, $uid, $nuevaFecha)) $exito = 'Meta reactivada. ¡Sigue donde ibas!';
    } elseif ($accion === 'reiniciar_vencida') {
        $mid = (int)($_POST['meta_id'] ?? 0);
        $nuevaFecha = $_POST['nueva_fecha_limite'] ?? null;
        if (Meta::reiniciar($pdo, $mid, $uid, $nuevaFecha)) $exito = 'Meta reiniciada desde cero.';
    } elseif ($accion === 'completar') {
        $mid = (int)($_POST['meta_id'] ?? 0);
        $meta = Meta::buscarPorId($pdo, $mid, $uid);
        if ($meta) {
            $datos = array_merge($meta, ['porcentaje_avance' => 100, 'estado' => 'completada']);
            Meta::actualizar($pdo, $mid, $datos);
            $exito = '¡Meta completada! 🎉';
            evaluarInsignias($pdo, $uid);
        }
    }
}

$metas = Meta::listarDeUsuario($pdo, $uid);
foreach ($metas as &$m) { $m['subtareas'] = Meta::subtareas($pdo, $m['id']); }
unset($m);
$habitos = Habito::listarDeUsuario($pdo, $uid);
$tituloPagina = 'Metas';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Mis metas</h1>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>
<div class="columnas">
<section class="panel-seccion">
  <h2>Nueva meta</h2>
  <form action="metas.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="crear">
    <label>Título</label><input type="text" name="titulo" required>
    <label>Descripción</label><textarea name="descripcion" rows="2"></textarea>
    <label>Tipo</label>
    <select name="tipo">
      <option value="corto_plazo">Corto plazo</option>
      <option value="largo_plazo">Largo plazo</option>
    </select>
    <label>Fecha límite (opcional)</label><input type="date" name="fecha_limite">
    <label>Vincular a hábito (opcional)</label>
    <select name="habito_id"><option value="">Sin vínculo</option>
      <?php foreach ($habitos as $h): ?><option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['nombre']) ?></option><?php endforeach; ?>
    </select>
    <label>Subtareas (una por línea)</label>
    <textarea name="subtareas" rows="4" placeholder="Subtarea 1&#10;Subtarea 2"></textarea>
    <button type="submit">Crear meta</button>
  </form>
</section>
<?php $vencidas = array_filter($metas, fn($m) => $m['estado'] === 'vencida'); ?>
<?php if (!empty($vencidas)): ?>
<section class="panel-seccion panel-vencidas">
  <h2>Metas vencidas</h2>
  <p class="texto-ayuda-vencidas">Se pasó la fecha límite sin completarlas. ¿Qué quieres hacer con cada una?</p>
  <?php foreach ($vencidas as $m): ?>
  <div class="tarjeta-meta tarjeta-meta-vencida">
    <strong><?= htmlspecialchars($m['titulo']) ?></strong>
    <small style="color:var(--color-texto-suave)"> · Venció el <?= date('d/m/Y', strtotime($m['fecha_limite'])) ?> · <?= $m['porcentaje_avance'] ?>% completado</small>
    <div class="barra-progreso" style="margin:8px 0"><div class="barra-progreso-relleno" style="width:<?= $m['porcentaje_avance'] ?>%"></div></div>
    <form action="metas.php" method="POST" class="form-decision-vencida">
      <?php campoCSRF(); ?>
      <input type="hidden" name="meta_id" value="<?= $m['id'] ?>">
      <label>Nueva fecha límite (opcional)</label>
      <input type="date" name="nueva_fecha_limite">
      <div class="acciones-meta">
        <button type="submit" name="accion" value="continuar_vencida" class="boton-acceso">Continuar donde iba</button>
        <button type="submit" name="accion" value="reiniciar_vencida" class="boton-secundario" onclick="return confirm('Esto pone el progreso en 0%. ¿Seguro?')">Reiniciar desde cero</button>
      </div>
    </form>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<section class="panel-seccion">
  <h2>Metas activas</h2>
  <?php $activas = array_filter($metas, fn($m) => $m['estado'] === 'activa'); ?>
  <?php if (empty($activas)): ?>
    <div class="estado-vacio"><p>Sin metas activas todavía. Crea la primera abajo.</p></div>
  <?php else: foreach ($activas as $m): ?>
  <div class="tarjeta-meta">
    <strong><?= htmlspecialchars($m['titulo']) ?></strong>
    <small style="color:var(--color-texto-suave)"> · <?= $m['tipo'] === 'corto_plazo' ? 'Corto plazo' : 'Largo plazo' ?>
      <?= $m['fecha_limite'] ? ' · Límite: '.date('d/m/Y', strtotime($m['fecha_limite'])) : '' ?></small>
    <div class="barra-progreso" style="margin:8px 0">
      <div class="barra-progreso-relleno" style="width:<?= $m['porcentaje_avance'] ?>%"></div>
    </div>
    <small><?= $m['porcentaje_avance'] ?>% completado</small>
    <?php if (!empty($m['subtareas'])): ?>
    <ul class="lista-subtareas">
      <?php foreach ($m['subtareas'] as $st): ?>
      <li class="<?= $st['completada'] ? 'subtarea-completada' : '' ?>">
        <form action="metas.php" method="POST" style="display:inline">
          <?php campoCSRF(); ?>
          <input type="hidden" name="accion" value="toggle_subtarea">
          <input type="hidden" name="subtarea_id" value="<?= $st['id'] ?>">
          <input type="hidden" name="meta_id" value="<?= $m['id'] ?>">
          <button type="submit" class="boton-check-subtarea"><?= $st['completada'] ? '☑' : '☐' ?></button>
        </form>
        <?= htmlspecialchars($st['descripcion']) ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <div class="acciones-meta">
      <form action="metas.php" method="POST" style="display:inline">
        <?php campoCSRF(); ?><input type="hidden" name="accion" value="completar"><input type="hidden" name="meta_id" value="<?= $m['id'] ?>">
        <button type="submit" class="boton-acceso">Completar</button>
      </form>
      <form action="metas.php" method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta meta?')">
        <?php campoCSRF(); ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="meta_id" value="<?= $m['id'] ?>">
        <button type="submit" class="boton-secundario">Eliminar</button>
      </form>
    </div>
  </div>
  <?php endforeach; endif; ?>
</section>
</div>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
