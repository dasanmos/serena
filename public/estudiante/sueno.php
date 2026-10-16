<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Sueno.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
$error = ''; $exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $datos = ['fecha' => $_POST['fecha'] ?? date('Y-m-d'), 'horas' => (float)($_POST['horas'] ?? 0),
              'calidad' => $_POST['calidad'] ?? '', 'hora_dormir' => $_POST['hora_dormir'] ?? '',
              'hora_despertar' => $_POST['hora_despertar'] ?? ''];
    if ($datos['horas'] < 0 || $datos['horas'] > 24) $error = 'Horas inválidas.';
    elseif (!in_array($datos['calidad'], ['mala','regular','buena'])) $error = 'Selecciona la calidad.';
    else { Sueno::registrar($pdo, $uid, $datos); $exito = 'Registro de sueño guardado.'; }
}

$historial = Sueno::listarDeUsuario($pdo, $uid, 14);
$hoy = Sueno::deHoy($pdo, $uid);

// Últimos 7 días para el gráfico, en orden cronológico (el modelo los trae del más reciente al más antiguo)
$ultimaSemana = array_slice($historial, 0, 7);
$ultimaSemana = array_reverse($ultimaSemana);
$promedioSemana = count($ultimaSemana) ? round(array_sum(array_column($ultimaSemana, 'horas')) / count($ultimaSemana), 1) : null;
$diasSemanaAbrev = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

$tituloPagina = 'Sueño';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Registro de sueño</h1>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<?php if ($hoy): ?>
<div class="tarjeta tarjeta-sueno-hoy">
  <div class="horas-grandes"><?= rtrim(rtrim(number_format((float)$hoy['horas'], 1), '0'), '.') ?><small>horas hoy</small></div>
  <div>
    <strong><?= ucfirst($hoy['calidad']) ?></strong>
    <p class="texto-suave">Calidad de tu último registro</p>
  </div>
</div>
<?php endif; ?>

<?php if ($promedioSemana !== null && $promedioSemana < 8): ?>
<div class="aviso-sueno-bajo">
  <span aria-hidden="true">🌙</span>
  <span>Esta semana dormiste en promedio <?= $promedioSemana ?> horas por noche. A tu edad se recomiendan entre 8 y 10. Si te cuesta dormir seguido, cuéntaselo a un adulto de confianza o al psicólogo/orientador.</span>
</div>
<?php endif; ?>

<div class="columnas">
<section class="panel-seccion">
  <h2>¿Cómo dormiste?</h2>
  <form action="sueno.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
    <label for="fecha">Fecha</label>
    <input type="date" id="fecha" name="fecha" value="<?= $hoy ? $hoy['fecha'] : date('Y-m-d') ?>">

    <label>Horas dormidas</label>
    <div class="selector-horas-sueno">
      <?php for ($h = 3; $h <= 14; $h++): $marcada = (int)($hoy['horas'] ?? 0) === $h; ?>
        <label>
          <input type="radio" name="horas" value="<?= $h ?>" <?= $marcada ? 'checked' : '' ?> required>
          <span class="opcion-horas-sueno <?= $marcada ? 'activa' : '' ?>"><?= $h ?>h</span>
        </label>
      <?php endfor; ?>
    </div>

    <label>Calidad del sueño</label>
    <div class="grilla-calidad-sueno">
      <?php foreach (['mala' => '😣 Mala', 'regular' => '😐 Regular', 'buena' => '😊 Buena'] as $c => $etq): ?>
        <label class="btn-calidad-sueno <?= ($hoy['calidad'] ?? '') === $c ? 'activa' : '' ?>">
          <input type="radio" name="calidad" value="<?= $c ?>" <?= ($hoy['calidad'] ?? '') === $c ? 'checked' : '' ?> required>
          <?= $etq ?>
        </label>
      <?php endforeach; ?>
    </div>

    <label for="hora_dormir">Hora en que te dormiste (opcional)</label>
    <input type="time" id="hora_dormir" name="hora_dormir" value="<?= $hoy['hora_dormir'] ?? '' ?>">
    <label for="hora_despertar">Hora en que te despertaste (opcional)</label>
    <input type="time" id="hora_despertar" name="hora_despertar" value="<?= $hoy['hora_despertar'] ?? '' ?>">
    <button type="submit">Guardar</button>
  </form>
</section>
<section class="panel-seccion">
  <h2>Últimos 7 días</h2>
  <?php if (empty($ultimaSemana)): ?>
    <div class="estado-vacio">
      <p>Aún no hay registros. Cuéntanos cómo dormiste anoche.</p>
    </div>
  <?php else: ?>
  <div class="grafico-sueno-semana">
    <?php foreach ($ultimaSemana as $s):
      $diaLetra = $diasSemanaAbrev[(int)date('N', strtotime($s['fecha'])) - 1];
      $alturaPct = max(6, min(100, ((float)$s['horas'] / 12) * 100));
      $insuficiente = (float)$s['horas'] < 8;
    ?>
      <div class="barra-sueno-dia <?= $insuficiente ? 'sueno-insuficiente' : '' ?>">
        <span class="valor-horas-sueno"><?= rtrim(rtrim(number_format((float)$s['horas'], 1), '0'), '.') ?>h</span>
        <div class="barra-vertical" style="height: <?= $alturaPct ?>%"></div>
        <span class="etiqueta-dia-sueno"><?= $diaLetra ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <details class="historial-mensual u-mt-lg">
    <summary>Ver tabla completa (últimas 2 semanas)</summary>
    <div class="tabla-wrapper u-mt-sm">
      <table class="tabla-datos tabla-adaptable">
        <thead><tr><th>Fecha</th><th>Horas</th><th>Calidad</th></tr></thead>
        <tbody>
        <?php foreach ($historial as $s): ?>
          <tr>
            <td data-etiqueta="Fecha"><?= date('d/m', strtotime($s['fecha'])) ?></td>
            <td data-etiqueta="Horas"><?= $s['horas'] ?>h</td>
            <td data-etiqueta="Calidad"><?= ucfirst($s['calidad']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
  <?php endif; ?>
</section>
</div>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
