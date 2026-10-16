<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';

requiereRol('administrador');

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
    $grupoIds = array_map('intval', $_POST['grupos'] ?? []);
    if ($usuarioId === 0) {
        $error = 'Selecciona un docente u orientador.';
    } else {
        Grupo::asignarPersonal($pdo, $usuarioId, $grupoIds);
        $exito = 'Grupos actualizados.';
    }
}

$docentesYOrientadores = array_merge(
    array_map(fn($u) => $u + ['rol' => 'orientador'], Usuario::listarPorRol($pdo, 'orientador')),
    array_map(fn($u) => $u + ['rol' => 'docente'], Usuario::listarPorRol($pdo, 'docente'))
);
usort($docentesYOrientadores, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

$usuarioSeleccionadoId = (int) ($_GET['usuario_id'] ?? $_POST['usuario_id'] ?? 0);
$gruposAsignadosIds = $usuarioSeleccionadoId ? Grupo::idsGruposDePersonal($pdo, $usuarioSeleccionadoId) : [];

$gruposPorGrado = [];
foreach (Grupo::listarConGrado($pdo) as $g) {
    $gruposPorGrado[$g['grado']][] = $g;
}

$tituloPagina = 'Asignar grupos';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Asignar grupos a docentes y orientadores</h1>
<p class="texto-suave">Un docente director de grupo normalmente tiene un solo grupo a cargo (se asigna desde <a href="grupos.php">Grupos</a>). Un orientador suele cubrir varios grupos o un grado completo — desde aquí puedes marcarle todos los que le correspondan.</p>

<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<section class="panel-seccion">
    <form action="asignar_grupos_personal.php" method="GET" class="formulario-panel">
        <label for="usuario_id">Docente u orientador</label>
        <select id="usuario_id" name="usuario_id" onchange="this.form.submit()" required>
            <option value="">Selecciona una persona</option>
            <?php foreach ($docentesYOrientadores as $u): ?>
                <option value="<?= $u['id'] ?>" <?= (int) $u['id'] === $usuarioSeleccionadoId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['nombre']) ?> — <?= ucfirst($u['rol']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">Ver</button></noscript>
    </form>
</section>

<?php if ($usuarioSeleccionadoId): ?>
<section class="panel-seccion" style="margin-top:20px">
    <h2>Grupos asignados</h2>
    <form action="asignar_grupos_personal.php" method="POST" class="formulario-panel">
        <?php campoCSRF(); ?>
        <input type="hidden" name="usuario_id" value="<?= $usuarioSeleccionadoId ?>">
        <?php if (empty($gruposPorGrado)): ?>
            <div class="estado-vacio"><p>Aún no hay grupos creados. Crea uno primero en <a href="grupos.php">Grupos</a>.</p></div>
        <?php else: foreach ($gruposPorGrado as $grado => $grupos): ?>
            <fieldset style="border:1px solid var(--color-borde);border-radius:var(--radio-borde);padding:12px 16px;margin-bottom:12px">
                <legend><?= htmlspecialchars($grado) ?></legend>
                <?php foreach ($grupos as $g): ?>
                    <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;margin-right:16px">
                        <input type="checkbox" name="grupos[]" value="<?= $g['id'] ?>" style="width:auto"
                            <?= in_array((int) $g['id'], $gruposAsignadosIds, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($g['nombre']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; endif; ?>
        <button type="submit">Guardar grupos</button>
    </form>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
