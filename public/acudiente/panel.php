<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/Academico.php';

// Panel del acudiente (padre, madre o cuidador): ve a sus hijos, su matrícula y puede solicitar la renovación.
requiereRol('acudiente');
$acudienteId = (int) $_SESSION['usuario_id'];

$flash = $_SESSION['flash_acudiente'] ?? null;
unset($_SESSION['flash_acudiente']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    if ((string) ($_POST['accion'] ?? '') === 'solicitar_renovacion') {
        $err = Academico::solicitarRenovacion($pdo, $acudienteId, (int) ($_POST['estudiante_id'] ?? 0), (int) ($_POST['grado_id'] ?? 0));
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Solicitud enviada. El colegio la confirmará y asignará el grupo.' : $err;
        if ($err === '') {
            auditarAccion($pdo, 'solicitar_renovacion', 'matriculas', 0, [], ['estudiante_id' => (int) ($_POST['estudiante_id'] ?? 0)]);
        }
    }
    $_SESSION['flash_acudiente'] = $f;
    header('Location: panel.php');
    exit;
}

$hijos = Academico::hijos($pdo, $acudienteId);
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Panel del acudiente';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
  <h1><span class="efecto-escritura efecto-escritura-dorado">Hola, <?= $e($_SESSION['nombre']) ?></span></h1>
  <p class="texto-suave">Seguimiento de la matrícula de tus hijos</p>
</div>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<?php if (!$hijos): ?>
  <div class="estado-vacio"><p>Todavía no tienes estudiantes vinculados. Si ya inscribiste a alguien, el colegio los vinculará al matricularlo.</p></div>
<?php endif; ?>

<?php foreach ($hijos as $h): ?>
  <?php
    $matriculas = Academico::matriculasDeEstudiante($pdo, (int) $h['id']);
    $anioRenov = Academico::anioRenovacionAbierta($pdo, (int) $h['colegio_id']);
    $yaTiene = false;
    if ($anioRenov !== null) {
        foreach ($matriculas as $m) {
            if ((int) $m['anio'] === (int) $anioRenov['anio']) {
                $yaTiene = true;
            }
        }
    }
    $grados = ($anioRenov !== null && !$yaTiene) ? Academico::grados($pdo, (int) $h['colegio_id']) : [];
  ?>
  <article class="hijo-tarjeta">
    <h3><?= $e($h['nombre']) ?></h3>
    <p class="hijo-meta"><?= $e($h['colegio']) ?> · <?= $h['grado'] ? $e($h['grado'] . ' ' . $h['grupo']) : 'sin grupo asignado' ?> · tú: <?= $e($h['parentesco']) ?></p>
    <p class="acad-acciones"><a href="<?= $e(BASE_URL) ?>/boletin.php?est=<?= (int) $h['id'] ?>">Boletín de notas</a> · <a href="<?= $e(BASE_URL) ?>/estudiante_ficha.php?est=<?= (int) $h['id'] ?>">Hoja de vida</a></p>

    <?php if ($matriculas): ?>
      <ul class="hijo-matriculas">
        <?php foreach ($matriculas as $m): ?>
          <li>
            <strong><?= (int) $m['anio'] ?></strong>
            <span class="estado-pill estado-<?= $e($m['estado']) ?>"><?= $e(Academico::ESTADOS_MATRICULA[$m['estado']] ?? $m['estado']) ?></span>
            <span class="texto-suave"><?= $e($m['grado']) ?><?= $m['grupo'] ? ' · Grupo ' . $e($m['grupo']) : '' ?> · <?= $m['tipo'] === 'renovacion' ? 'Renovación' : 'Matrícula nueva' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="texto-suave">Aún no hay matrículas registradas.</p>
    <?php endif; ?>

    <?php if ($anioRenov !== null && !$yaTiene && $grados): ?>
      <form action="panel.php" method="POST" class="acad-acciones" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="solicitar_renovacion"><input type="hidden" name="estudiante_id" value="<?= (int) $h['id'] ?>">
        <label>Renovar matrícula <?= (int) $anioRenov['anio'] ?> en el grado
          <select name="grado_id" required>
            <?php foreach ($grados as $g): ?><option value="<?= (int) $g['id'] ?>" <?= (int) $h['grado_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= $e($g['nombre']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <button type="submit">Solicitar renovación</button>
      </form>
    <?php elseif ($anioRenov !== null && $yaTiene): ?>
      <p class="texto-suave">La renovación <?= (int) $anioRenov['anio'] ?> ya fue solicitada o registrada.</p>
    <?php endif; ?>
  </article>
<?php endforeach; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
