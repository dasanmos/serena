<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/AlertaTemprana.php';
requiereRol('orientador','psicologo');
$uid = (int)$_SESSION['usuario_id'];
$esOrientador = $_SESSION['rol'] === 'orientador';
$gruposIds = $esOrientador ? ($_SESSION['grupos_ids'] ?? []) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int)($_POST['alerta_id'] ?? 0)) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    AlertaTemprana::atender($pdo, (int)$_POST['alerta_id'], $uid);
    header('Location: alertas.php'); exit;
}
$alertas = AlertaTemprana::listarNoAtendidas($pdo, $gruposIds);
$conteoPorNivel = ['alto' => 0, 'medio' => 0, 'bajo' => 0];
foreach ($alertas as $a) { $conteoPorNivel[$a['nivel_urgencia']]++; }
$tituloPagina = 'Alertas';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Alertas tempranas<?= $esOrientador ? ' — mis grupos' : '' ?></h1>

<div class="recordatorio-criterio-humano">
  <span aria-hidden="true">ℹ️</span>
  <span>Estas alertas son una guía para priorizar, no un diagnóstico. Cada caso se atiende con criterio profesional y, cuando haga falta, en conjunto con psicología.</span>
</div>

<div class="resumen-alertas">
  <div class="tarjeta-resumen-alerta resumen-severidad-alta">
    <span class="numero-alerta"><?= $conteoPorNivel['alto'] ?></span>
    <span class="etiqueta-alerta">Urgencia alta</span>
  </div>
  <div class="tarjeta-resumen-alerta resumen-severidad-media">
    <span class="numero-alerta"><?= $conteoPorNivel['medio'] ?></span>
    <span class="etiqueta-alerta">Urgencia media</span>
  </div>
  <div class="tarjeta-resumen-alerta resumen-severidad-baja">
    <span class="numero-alerta"><?= $conteoPorNivel['bajo'] ?></span>
    <span class="etiqueta-alerta">Urgencia baja</span>
  </div>
</div>

<?php if (empty($alertas)): ?>
  <div class="estado-vacio">
    <p>No hay alertas pendientes en tu grupo. ✅</p>
  </div>
<?php else: foreach ($alertas as $a): ?>
<div class="tarjeta-alerta severidad-<?= htmlspecialchars($a['nivel_urgencia']) ?>">
  <div class="info-alerta">
    <span class="estudiante-alerta"><?= htmlspecialchars($a['estudiante']) ?></span>
    <span class="pill pill-severidad-<?= htmlspecialchars($a['nivel_urgencia']) ?>"><?= strtoupper($a['nivel_urgencia']) ?></span>
    <?php if ($a['tipo'] === 'ayuda_urgente'): ?>
    <span class="pill pill-pedida-por-estudiante">🆘 Pedida por el estudiante</span>
    <?php endif; ?>
    <p class="motivo-alerta"><?= htmlspecialchars($a['descripcion']) ?></p>
    <p class="fecha-alerta"><?= date('d/m/Y H:i', strtotime($a['fecha_generada'])) ?></p>
  </div>
  <div class="acciones-solicitud">
    <a href="<?= BASE_URL ?>/orientador/estudiante_detalle.php?id=<?= $a['usuario_id'] ?>" class="boton-acceso">Ver estudiante</a>
    <form action="alertas.php" method="POST" style="display:inline">
      <?php campoCSRF(); ?>
      <input type="hidden" name="alerta_id" value="<?= $a['id'] ?>">
      <button type="submit" class="boton-secundario">Atendida</button>
    </form>
  </div>
</div>
<?php endforeach; endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
