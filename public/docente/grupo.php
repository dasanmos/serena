<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
requiereRol('docente');
$grupoId = $_SESSION['grupo_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_seguimiento_id'])) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $estudianteId = (int) $_POST['marcar_seguimiento_id'];
    // Verifica que el estudiante sea del propio grupo del docente antes de crear la
    // alerta — evita que alguien manipule el id por POST y marque a cualquiera.
    $pertenece = $pdo->prepare(
        "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id=u.rol_id
         WHERE u.id=:id AND r.nombre='estudiante' AND u.grupo_id=:gid"
    );
    $pertenece->execute(['id' => $estudianteId, 'gid' => $grupoId]);
    if ($grupoId && (int) $pertenece->fetchColumn() > 0) {
        $ins = $pdo->prepare(
            "INSERT INTO alertas_tempranas (usuario_id, tipo, descripcion, nivel_urgencia)
             VALUES (:uid, 'seguimiento_solicitado', :desc, 'medio')"
        );
        $ins->execute(['uid' => $estudianteId, 'desc' => $_SESSION['nombre'] . ' (docente) marcó este estudiante para seguimiento.']);
    }
    header('Location: grupo.php?seguimiento=1');
    exit;
}

$tituloPagina = 'Mi grupo';
require __DIR__ . '/../parciales/encabezado.php';

if (!$grupoId) {
    echo '<h1>Mi grupo</h1><p>No tienes un grupo asignado. Contacta al administrador.</p>';
    require __DIR__ . '/../parciales/pie.php';
    exit;
}

// El docente NO ve datos emocionales individuales (reservados a psicólogo, orientador y administrador
// según la política de privacidad del proyecto). Solo ve actividad general del grupo.
$estudiantes = $pdo->prepare(
    "SELECT u.id, u.nombre, u.ultimo_acceso,
            (SELECT COUNT(*) FROM metas m WHERE m.usuario_id=u.id AND m.estado='activa') AS metas_activas,
            (SELECT COUNT(*) FROM habitos h WHERE h.usuario_id=u.id AND h.activo=1) AS habitos_activos
     FROM usuarios u INNER JOIN roles r ON r.id=u.rol_id
     WHERE r.nombre='estudiante' AND u.grupo_id=:gid ORDER BY u.nombre"
);
$estudiantes->execute(['gid' => $grupoId]);
$estudiantes = $estudiantes->fetchAll();

$coloresIniciales = ['#2E6E8E', '#3A7D44', '#9C6B0A', '#8A2E4E', '#6B4E9C'];
?>
<div class="cabecera-grupo">
  <div class="info-grupo">
    <h1>Mi grupo</h1>
    <span class="conteo-grupo"><?= count($estudiantes) ?> estudiantes</span>
  </div>
</div>
<div class="recordatorio-criterio-humano">
  <span aria-hidden="true">ℹ️</span>
  <span>Vista general de actividad. Los registros emocionales individuales son confidenciales y solo los ve psicología/orientación.</span>
</div>
<?php if (isset($_GET['seguimiento'])): ?>
  <p class="mensaje mensaje-exito">Se marcó el seguimiento — psicología/orientación lo verá en sus alertas.</p>
<?php endif; ?>
<?php if (empty($estudiantes)): ?>
  <div class="estado-vacio">
    <p>Sin estudiantes asignados a este grupo todavía.</p>
  </div>
<?php else: ?>
<div class="roster-grupo">
  <?php foreach ($estudiantes as $indice => $e):
      $iniciales = mb_strtoupper(mb_substr($e['nombre'], 0, 1) . mb_substr(strrchr($e['nombre'], ' ') ?: '', 1, 1));
      $color = $coloresIniciales[$indice % count($coloresIniciales)];
  ?>
    <div class="tarjeta-estudiante-roster">
      <a href="<?= BASE_URL ?>/docente/estudiante_detalle.php?id=<?= $e['id'] ?>" class="u-flex u-items-center u-gap-md">
        <span class="avatar avatar-md" style="--avatar-color:<?= $color ?>"><?= htmlspecialchars($iniciales) ?></span>
        <span>
          <span class="nombre-estudiante-roster u-flex"><?= htmlspecialchars($e['nombre']) ?></span>
          <span class="detalle-roster">
            <?= (int)$e['metas_activas'] ?> metas · <?= (int)$e['habitos_activos'] ?> hábitos ·
            <?= $e['ultimo_acceso'] ? 'activo ' . date('d/m', strtotime($e['ultimo_acceso'])) : 'sin acceso aún' ?>
          </span>
        </span>
      </a>
      <form action="grupo.php" method="POST" onsubmit="return confirm('¿Marcar a <?= htmlspecialchars(addslashes($e['nombre'])) ?> para seguimiento de psicología/orientación?');">
        <?php campoCSRF(); ?>
        <input type="hidden" name="marcar_seguimiento_id" value="<?= $e['id'] ?>">
        <button type="submit" class="boton-secundario" data-sonido="clic">🚩 Marcar seguimiento</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
