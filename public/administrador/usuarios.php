<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';

requiereRol('administrador');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $idUsuario = (int) ($_POST['usuario_id'] ?? 0);
    $nuevoEstado = (int) ($_POST['nuevo_estado'] ?? 1);

    // No se permite que un administrador se desactive a sí mismo por accidente
    if ($idUsuario !== (int) $_SESSION['usuario_id']) {
        $paramsEstado = ['activo' => $nuevoEstado, 'id' => $idUsuario];
        $consulta = $pdo->prepare('UPDATE usuarios SET activo = :activo WHERE id = :id' . condicionColegio('colegio_id', $paramsEstado));
        $consulta->execute($paramsEstado);
        auditarAccion($pdo, $nuevoEstado ? 'activar_usuario' : 'desactivar_usuario', 'usuarios', $idUsuario);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $idUsuario = (int) ($_POST['usuario_id'] ?? 0);
    // No se permite que un administrador se borre a sí mismo desde esta pantalla.
    if ($idUsuario !== (int) $_SESSION['usuario_id']) {
        auditarAccion($pdo, 'eliminar_usuario', 'usuarios', $idUsuario);
        Usuario::eliminar($pdo, $idUsuario);
        header('Location: usuarios.php?eliminado=1' . (isset($_GET['rol']) ? '&rol=' . urlencode($_GET['rol']) : ''));
        exit;
    }
}

const USUARIOS_POR_PAGINA = 25;

$filtroRol = $_GET['rol'] ?? '';
$busqueda = trim($_GET['q'] ?? '');
$totalUsuarios = Usuario::contarConFiltro($pdo, $filtroRol, $busqueda);
$totalPaginas = max(1, (int) ceil($totalUsuarios / USUARIOS_POR_PAGINA));
$pagina = max(1, min($totalPaginas, (int) ($_GET['pagina'] ?? 1)));
$usuarios = Usuario::buscarConFiltro($pdo, $filtroRol, $busqueda, $pagina, USUARIOS_POR_PAGINA);

$roles = $pdo->query("SELECT nombre FROM roles WHERE nombre != 'superadmin' ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

$tituloPagina = 'Usuarios';
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Usuarios</h1>

<?php if (isset($_GET['creado'])): ?>
    <p class="mensaje mensaje-exito">Usuario creado correctamente.</p>
<?php endif; ?>
<?php if (isset($_GET['actualizado'])): ?>
    <p class="mensaje mensaje-exito">Usuario actualizado.</p>
<?php endif; ?>
<?php if (isset($_GET['eliminado'])): ?>
    <p class="mensaje mensaje-exito">Usuario eliminado. Sus registros (emociones, hábitos, metas, etc.) también se borraron.</p>
<?php endif; ?>

<div class="barra-acciones" style="flex-wrap:wrap;gap:10px">
    <a href="<?= BASE_URL ?>/administrador/usuario_crear.php" class="boton-acceso">Crear usuario</a>
    <a href="<?= BASE_URL ?>/administrador/usuarios_exportar.php?rol=<?= urlencode($filtroRol) ?>&q=<?= urlencode($busqueda) ?>" class="boton-secundario">⬇ Exportar CSV</a>
    <a href="<?= BASE_URL ?>/administrador/usuarios_importar.php" class="boton-secundario">⬆ Importar personal</a>
</div>

<form action="usuarios.php" method="GET" class="filtro-rol" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:16px">
    <div>
        <label for="q">Buscar por nombre o correo</label>
        <input type="search" id="q" name="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Ej. Ana, ana@serena.edu">
    </div>
    <div>
        <label for="rol">Filtrar por rol</label>
        <select id="rol" name="rol">
            <option value="">Todos</option>
            <?php foreach ($roles as $rol): ?>
                <option value="<?= htmlspecialchars($rol) ?>" <?= $filtroRol === $rol ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($rol)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit">Buscar</button>
    <?php if ($filtroRol !== '' || $busqueda !== ''): ?>
        <a href="usuarios.php" class="boton-secundario">Quitar filtros</a>
    <?php endif; ?>
</form>

<p class="texto-suave" style="margin-top:12px">
    <?= $totalUsuarios ?> usuario(s) encontrado(s)<?= $totalPaginas > 1 ? " — página {$pagina} de {$totalPaginas}" : '' ?>.
</p>

<table class="tabla-datos tabla-adaptable">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Rol</th>
            <th>Estado</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($usuarios)): ?>
            <tr><td colspan="5"><div class="estado-vacio"><p>No se encontró ningún usuario con esos filtros.</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td data-etiqueta="Nombre"><?= htmlspecialchars($u['nombre']) ?></td>
                <td data-etiqueta="Correo"><?= htmlspecialchars($u['correo']) ?></td>
                <td data-etiqueta="Rol"><span class="badge-rol badge-rol-<?= htmlspecialchars($u['rol']) ?>"><?= htmlspecialchars(ucfirst($u['rol'])) ?></span></td>
                <td data-etiqueta="Estado"><span class="pill <?= $u['activo'] ? 'pill-exito' : 'pill-neutro' ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                <td data-etiqueta="" style="display:flex;gap:6px;flex-wrap:wrap">
                    <a href="<?= BASE_URL ?>/administrador/usuario_editar.php?id=<?= $u['id'] ?>" class="boton-secundario">Editar</a>
                    <?php if ((int) $u['id'] !== (int) $_SESSION['usuario_id']): ?>
                        <form action="usuarios.php" method="POST" style="display:inline">
                            <?php campoCSRF(); ?>
                            <input type="hidden" name="accion" value="cambiar_estado">
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="nuevo_estado" value="<?= $u['activo'] ? 0 : 1 ?>">
                            <button type="submit" class="<?= $u['activo'] ? 'boton-peligro' : 'boton-secundario' ?>">
                                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form action="usuarios.php" method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars(addslashes($u['nombre'])) ?> para siempre? Esto borra también sus registros (emociones, diario, hábitos, metas). No se puede deshacer.')">
                            <?php campoCSRF(); ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="boton-peligro">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($totalPaginas > 1): ?>
<nav class="paginacion" aria-label="Paginación de usuarios" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:16px">
    <?php
    $parametrosBase = ['rol' => $filtroRol, 'q' => $busqueda];
    $urlPagina = fn(int $p) => 'usuarios.php?' . http_build_query($parametrosBase + ['pagina' => $p]);
    ?>
    <?php if ($pagina > 1): ?>
        <a href="<?= htmlspecialchars($urlPagina($pagina - 1)) ?>" class="boton-secundario">&larr; Anterior</a>
    <?php endif; ?>
    <span class="texto-suave">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
    <?php if ($pagina < $totalPaginas): ?>
        <a href="<?= htmlspecialchars($urlPagina($pagina + 1)) ?>" class="boton-secundario">Siguiente &rarr;</a>
    <?php endif; ?>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
