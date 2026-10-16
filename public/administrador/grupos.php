<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Grado.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';

requiereRol('administrador');

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    if (($_POST['accion'] ?? '') === 'crear_grado') {
        $nombreGrado = trim($_POST['nombre_grado'] ?? '');
        if ($nombreGrado === '') {
            $error = 'El nombre del grado no puede estar vacío.';
        } else {
            Grado::crear($pdo, $nombreGrado);
            $exito = 'Grado creado.';
        }
    }

    if (($_POST['accion'] ?? '') === 'crear_grupo') {
        $gradoId = (int) ($_POST['grado_id'] ?? 0);
        $nombreGrupo = trim($_POST['nombre_grupo'] ?? '');
        if ($gradoId === 0 || $nombreGrupo === '') {
            $error = 'Selecciona el grado y escribe el nombre del grupo.';
        } else {
            Grupo::crear($pdo, $gradoId, $nombreGrupo);
            $exito = 'Grupo creado.';
        }
    }

    if (($_POST['accion'] ?? '') === 'asignar_docente') {
        $grupoId = (int) ($_POST['grupo_id'] ?? 0);
        $docenteId = (int) ($_POST['docente_id'] ?? 0);
        if ($grupoId === 0) {
            $error = 'Grupo inválido.';
        } else {
            Grupo::asignarDocente($pdo, $grupoId, $docenteId > 0 ? $docenteId : null);
            $exito = 'Docente actualizado.';
        }
    }
}

$grados = Grado::listarTodos($pdo);
$grupos = Grupo::listarConGrado($pdo);
$docentes = Usuario::listarPorRol($pdo, 'docente');

$tituloPagina = 'Grupos';
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Grupos</h1>

<?php if ($error): ?>
    <p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if ($exito): ?>
    <p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p>
<?php endif; ?>

<div class="columnas">

    <section class="panel-seccion">
        <h2>Crear grado</h2>
        <form action="grupos.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="crear_grado">
            <label for="nombre_grado">Nombre</label>
            <input type="text" id="nombre_grado" name="nombre_grado" placeholder="Ej. Sexto" required>
            <button type="submit">Crear grado</button>
        </form>
    </section>

    <section class="panel-seccion">
        <h2>Crear grupo</h2>
        <form action="grupos.php" method="POST" novalidate>
    <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="crear_grupo">
            <label for="grado_id">Grado</label>
            <select id="grado_id" name="grado_id" required>
                <option value="">Selecciona un grado</option>
                <?php foreach ($grados as $grado): ?>
                    <option value="<?= $grado['id'] ?>"><?= htmlspecialchars($grado['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="nombre_grupo">Nombre del grupo</label>
            <input type="text" id="nombre_grupo" name="nombre_grupo" placeholder="Ej. A" required>
            <button type="submit">Crear grupo</button>
        </form>
    </section>

</div>

<section class="panel-seccion">
    <h2>Grupos existentes</h2>
    <?php if (empty($grupos)): ?>
        <div class="estado-vacio">
            <p>Aún no hay grupos creados. Crea un grado y un grupo arriba.</p>
        </div>
    <?php else: ?>
    <div class="tabla-wrapper">
    <table class="tabla-datos tabla-adaptable">
        <thead>
            <tr>
                <th>Grado</th>
                <th>Grupo</th>
                <th>Docente</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $g): ?>
                <tr>
                    <td data-etiqueta="Grado"><?= htmlspecialchars($g['grado']) ?></td>
                    <td data-etiqueta="Grupo"><span class="chip-grupo activo"><?= htmlspecialchars($g['nombre']) ?></span></td>
                    <td data-etiqueta="Docente">
                        <form action="grupos.php" method="POST" style="display:flex;gap:6px;align-items:center">
                            <?php campoCSRF(); ?>
                            <input type="hidden" name="accion" value="asignar_docente">
                            <input type="hidden" name="grupo_id" value="<?= $g['id'] ?>">
                            <select name="docente_id" style="width:auto">
                                <option value="0">Sin asignar</option>
                                <?php foreach ($docentes as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= (int) $g['docente_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="boton-secundario">Guardar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
