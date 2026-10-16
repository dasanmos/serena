<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/SolicitudCambio.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
requiereRol('administrador');
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';
    $stSol = $pdo->prepare('SELECT * FROM solicitudes_cambio WHERE id=:id LIMIT 1');
    $stSol->execute(['id' => $id]);
    $solicitud = $stSol->fetch();
    // Multi-colegio: solo se gestionan solicitudes de usuarios del propio colegio.
    if ($solicitud && !usuarioEsDeMiColegio($pdo, (int) $solicitud['usuario_id'])) {
        $solicitud = false;
    }
    if ($solicitud) {
        if ($accion === 'aprobar') {
            SolicitudCambio::aprobar($pdo, $id, $uid);

            if ($solicitud['campo'] === 'eliminar_cuenta') {
                // Nunca se borra al propio administrador que está aprobando (no debería
                // poder pasar, pero se valida igual antes de una operación irreversible),
                // ni una segunda vez si por alguna razón ya no existe.
                $usuarioABorrar = Usuario::buscarPorId($pdo, (int) $solicitud['usuario_id']);
                if ($usuarioABorrar && (int) $usuarioABorrar['id'] !== $uid) {
                    auditarAccion($pdo, 'eliminar_cuenta_por_solicitud', 'usuarios', (int) $solicitud['usuario_id'],
                        ['correo' => $usuarioABorrar['correo']], []);
                    Usuario::eliminar($pdo, (int) $solicitud['usuario_id']);
                }
            } else {
                // Solo se permiten estos campos como whitelist explícita, nunca el nombre de columna del usuario.
                $col = in_array($solicitud['campo'], ['nombre','correo','telefono'], true) ? $solicitud['campo'] : null;
                if ($col) {
                    $pdo->prepare("UPDATE usuarios SET $col=:v WHERE id=:id")->execute(['v'=>$solicitud['valor_nuevo'],'id'=>$solicitud['usuario_id']]);
                    auditarAccion($pdo, 'aprobar_cambio_dato', 'usuarios', (int)$solicitud['usuario_id'],
                        [$col => $solicitud['valor_anterior']], [$col => $solicitud['valor_nuevo']]);
                }
            }
        } else {
            SolicitudCambio::rechazar($pdo, $id, $uid);
            auditarAccion($pdo, 'rechazar_cambio_dato', 'solicitudes_cambio', $id);
        }
    }
    header('Location: aprobaciones.php'); exit;
}

$pendientes = SolicitudCambio::listarPendientes($pdo);
$tituloPagina = 'Aprobaciones';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Solicitudes de cambio</h1>
<?php if (empty($pendientes)): ?>
  <div class="estado-vacio">
    <p>No hay solicitudes pendientes. ✅</p>
  </div>
<?php else: foreach ($pendientes as $s): ?>
<div class="solicitud-cambio">
  <div class="detalle-cambio">
    <strong><?= htmlspecialchars($s['usuario_nombre']) ?></strong> <span class="texto-suave">(<?= htmlspecialchars($s['usuario_correo']) ?>)</span>
    <?php if ($s['campo'] === 'eliminar_cuenta'): ?>
      <p>🗑️ Solicita <strong>eliminar su cuenta y todos sus datos por completo</strong>. Esta acción no se puede deshacer.</p>
    <?php else: ?>
      <p>Solicita cambiar <strong><?= htmlspecialchars($s['campo']) ?></strong>:
        <span class="valor-anterior"><?= htmlspecialchars($s['valor_anterior'] ?? '—') ?></span> →
        <span class="valor-nuevo"><?= htmlspecialchars($s['valor_nuevo']) ?></span></p>
    <?php endif; ?>
    <p class="fecha-alerta"><?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?></p>
  </div>
  <div class="acciones-solicitud">
    <form action="aprobaciones.php" method="POST" style="display:inline">
      <?php campoCSRF(); ?><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="accion" value="aprobar">
      <button type="submit" class="boton-acceso">Aprobar</button>
    </form>
    <form action="aprobaciones.php" method="POST" style="display:inline">
      <?php campoCSRF(); ?><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="accion" value="rechazar">
      <button type="submit" class="boton-secundario">Rechazar</button>
    </form>
  </div>
</div>
<?php endforeach; endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
