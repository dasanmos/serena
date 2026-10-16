<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
requiereRol('orientador');
$gruposIds = $_SESSION['grupos_ids'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_seguimiento_id'])) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $estudianteId = (int) $_POST['marcar_seguimiento_id'];
    if (!empty($gruposIds)) {
        $marcadoresVerif = implode(',', array_fill(0, count($gruposIds), '?'));
        $pertenece = $pdo->prepare(
            "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id=u.rol_id
             WHERE u.id=? AND r.nombre='estudiante' AND u.grupo_id IN ({$marcadoresVerif})"
        );
        $pertenece->execute(array_merge([$estudianteId], $gruposIds));
        if ((int) $pertenece->fetchColumn() > 0) {
            $ins = $pdo->prepare(
                "INSERT INTO alertas_tempranas (usuario_id, tipo, descripcion, nivel_urgencia)
                 VALUES (:uid, 'seguimiento_solicitado', :desc, 'medio')"
            );
            $ins->execute(['uid' => $estudianteId, 'desc' => $_SESSION['nombre'] . ' (orientador) marcó este estudiante para seguimiento.']);
        }
    }
    header('Location: grupo.php?seguimiento=1');
    exit;
}

$estudiantes = [];
if (!empty($gruposIds)) {
    $marcadores = implode(',', array_fill(0, count($gruposIds), '?'));
    $c = $pdo->prepare(
        "SELECT u.id, u.nombre, u.ultimo_acceso, u.grupo_id, g.nombre AS grupo_nombre, gr.nombre AS grado_nombre
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         INNER JOIN grupos g ON g.id = u.grupo_id
         INNER JOIN grados gr ON gr.id = g.grado_id
         WHERE r.nombre = 'estudiante' AND u.grupo_id IN ({$marcadores})
         ORDER BY gr.nombre, g.nombre, u.nombre"
    );
    $c->execute($gruposIds);
    $estudiantes = $c->fetchAll();

    // Un solo query agregado para el nivel emocional de hoy de todo el roster,
    // en vez de una consulta por estudiante dentro del foreach (N+1).
    $nivelesHoy = [];
    if (!empty($estudiantes)) {
        $idsEstudiantes = array_column($estudiantes, 'id');
        $marcadoresIds = implode(',', array_fill(0, count($idsEstudiantes), '?'));
        $nq = $pdo->prepare(
            "SELECT usuario_id, AVG(nivel) AS nivel
             FROM estado_emocional
             WHERE usuario_id IN ({$marcadoresIds}) AND DATE(fecha_hora) = CURDATE()
             GROUP BY usuario_id"
        );
        $nq->execute($idsEstudiantes);
        foreach ($nq->fetchAll() as $fila) {
            $nivelesHoy[(int) $fila['usuario_id']] = $fila['nivel'];
        }
    }
}
$tituloPagina = count($gruposIds) === 1 ? 'Mi grupo' : 'Mis grupos';
require __DIR__ . '/../parciales/encabezado.php';

$coloresIniciales = ['#2E6E8E', '#3A7D44', '#9C6B0A', '#8A2E4E', '#6B4E9C'];
?>
<div class="cabecera-grupo">
  <div class="info-grupo">
    <h1><?= count($gruposIds) === 1 ? 'Mi grupo' : 'Mis grupos' ?></h1>
    <span class="conteo-grupo"><?= count($estudiantes) ?> estudiantes<?= count($gruposIds) > 1 ? ' · ' . count($gruposIds) . ' grupos' : '' ?></span>
  </div>
  <?php if (count($gruposIds) === 1): ?>
    <a href="<?= BASE_URL ?>/psicologo/grupo_pdf.php?grupo_id=<?= $gruposIds[0] ?>" class="boton-secundario" data-sonido="clic">📄 Exportar grupo a PDF</a>
    <a href="<?= BASE_URL ?>/psicologo/grupo_pdf.php?grupo_id=<?= $gruposIds[0] ?>&qr=1" class="boton-secundario" data-sonido="clic" title="PDF con código QR para verificar su autenticidad">PDF con QR</a>
  <?php endif; ?>
</div>
<?php if (count($gruposIds) > 1): ?>
  <div class="u-flex u-gap-sm u-flex-wrap" style="margin-bottom:12px;">
    <?php
      // Un botón de exportar por cada grupo asignado (no hay un solo grupo_id
      // que represente a todos a la vez en grupo_pdf.php).
      $gruposUnicos = [];
      foreach ($estudiantes as $e) {
          $gid = (int) $e['grupo_id'];
          if (!isset($gruposUnicos[$gid])) {
              $gruposUnicos[$gid] = $e['grado_nombre'] . ' ' . $e['grupo_nombre'];
          }
      }
    ?>
    <?php foreach ($gruposUnicos as $gid => $etiqueta): ?>
      <a href="<?= BASE_URL ?>/psicologo/grupo_pdf.php?grupo_id=<?= $gid ?>" class="boton-secundario" data-sonido="clic">📄 PDF <?= htmlspecialchars($etiqueta) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if (isset($_GET['seguimiento'])): ?>
  <p class="mensaje mensaje-exito">Se marcó el seguimiento.</p>
<?php endif; ?>
<?php if (empty($gruposIds)): ?>
  <div class="estado-vacio">
    <p>Todavía no tienes ningún grupo asignado. Contacta al administrador.</p>
  </div>
<?php elseif (empty($estudiantes)): ?>
  <div class="estado-vacio">
    <p>Sin estudiantes asignados a tus grupos todavía.</p>
  </div>
<?php else: ?>
<div class="roster-grupo">
  <?php foreach ($estudiantes as $indice => $e):
    $nivel = $nivelesHoy[(int) $e['id']] ?? null;
    $iniciales = mb_strtoupper(mb_substr($e['nombre'], 0, 1) . mb_substr(strrchr($e['nombre'], ' ') ?: '', 1, 1));
    $color = $coloresIniciales[$indice % count($coloresIniciales)];
  ?>
    <div class="tarjeta-estudiante-roster">
      <a href="<?= BASE_URL ?>/psicologo/estudiante_detalle.php?id=<?= $e['id'] ?>" class="u-flex u-items-center u-gap-md">
        <span class="avatar avatar-md" style="--avatar-color:<?= $color ?>"><?= htmlspecialchars($iniciales) ?></span>
        <span>
          <span class="nombre-estudiante-roster u-flex"><?= htmlspecialchars($e['nombre']) ?><?= count($gruposIds) > 1 ? ' <span class="chip-grupo chip-pequeno">' . htmlspecialchars($e['grado_nombre'] . ' ' . $e['grupo_nombre']) . '</span>' : '' ?></span>
          <span class="detalle-roster">
            Nivel hoy: <?= $nivel ? round((float)$nivel,1).'/5' : 'sin registro' ?> ·
            <?= $e['ultimo_acceso'] ? 'activo ' . date('d/m', strtotime($e['ultimo_acceso'])) : 'sin acceso aún' ?>
          </span>
        </span>
        <span class="indicador-estado-roster <?= $nivel ? 'registro-reciente' : 'sin-registro' ?>"></span>
      </a>
      <form action="grupo.php" method="POST" onsubmit="return confirm('¿Marcar a <?= htmlspecialchars(addslashes($e['nombre'])) ?> para seguimiento?');">
        <?php campoCSRF(); ?>
        <input type="hidden" name="marcar_seguimiento_id" value="<?= $e['id'] ?>">
        <button type="submit" class="boton-secundario" data-sonido="clic">🚩</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
