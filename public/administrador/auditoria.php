<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Auditoria.php';
requiereRol('administrador', 'superadmin');

const AUDITORIA_POR_PAGINA = 50;

$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$usuarioBusqueda = trim($_GET['usuario'] ?? '');
$hayFiltro = $desde !== '' || $hasta !== '' || $usuarioBusqueda !== '';

$totalRegistros = $hayFiltro
    ? Auditoria::contarFiltrado($pdo, $desde ?: null, $hasta ?: null, $usuarioBusqueda ?: null)
    : Auditoria::contarTotal($pdo);
$totalPaginas = max(1, (int) ceil($totalRegistros / AUDITORIA_POR_PAGINA));
$pagina = max(1, min($totalPaginas, (int) ($_GET['pagina'] ?? 1)));
$offset = ($pagina - 1) * AUDITORIA_POR_PAGINA;

$registros = $hayFiltro
    ? Auditoria::listarFiltrado($pdo, $desde ?: null, $hasta ?: null, $usuarioBusqueda ?: null, AUDITORIA_POR_PAGINA, $offset)
    : Auditoria::listar($pdo, AUDITORIA_POR_PAGINA, $offset);

$tituloPagina = 'Auditoría';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Bitácora de auditoría</h1>

<form action="auditoria.php" method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px">
    <div><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>"></div>
    <div><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>"></div>
    <div><label for="usuario">Usuario (nombre o correo)</label><input type="text" id="usuario" name="usuario" placeholder="Ej. Ana o ana@serena.edu" value="<?= htmlspecialchars($usuarioBusqueda) ?>"></div>
    <button type="submit">Filtrar</button>
    <?php if ($hayFiltro): ?><a href="auditoria.php" class="boton-secundario">Quitar filtros</a><?php endif; ?>
    <a href="auditoria_csv.php?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&usuario=<?= urlencode($usuarioBusqueda) ?>" class="boton-secundario">📊 Exportar CSV</a>
</form>
<p class="texto-suave">
    <?= $totalRegistros ?> registro(s)<?= $hayFiltro ? ' con los filtros aplicados' : '' ?><?= $totalPaginas > 1 ? " — página {$pagina} de {$totalPaginas}" : '' ?>.
</p>

<?php if (empty($registros)): ?>
  <div class="estado-vacio">
    <p>No hay actividad registrada<?= ($desde || $hasta) ? ' en ese rango de fechas' : '' ?>.</p>
  </div>
<?php else: ?>
<div class="tabla-wrapper">
<table class="tabla-datos tabla-adaptable">
  <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Tabla</th><th>Registro</th></tr></thead>
  <tbody>
  <?php foreach ($registros as $r): ?>
    <tr class="fila-auditoria">
      <td data-etiqueta="Fecha"><?= date('d/m/Y H:i', strtotime($r['fecha'])) ?></td>
      <td data-etiqueta="Usuario"><?= htmlspecialchars($r['usuario_nombre'] ?? 'Sistema') ?></td>
      <td data-etiqueta="Acción"><span class="accion-auditoria"><?= htmlspecialchars($r['accion']) ?></span></td>
      <td data-etiqueta="Tabla"><?= htmlspecialchars($r['tabla_afectada']) ?></td>
      <td data-etiqueta="Registro"><?= $r['registro_id'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($totalPaginas > 1): ?>
<nav class="paginacion" aria-label="Paginación de auditoría" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:16px">
    <?php
    $parametrosBase = ['desde' => $desde, 'hasta' => $hasta, 'usuario' => $usuarioBusqueda];
    $urlPagina = fn(int $p) => 'auditoria.php?' . http_build_query($parametrosBase + ['pagina' => $p]);
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
