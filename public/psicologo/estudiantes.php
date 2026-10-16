<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Grado.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
requiereRol('psicologo');
$busqueda = trim($_GET['busqueda'] ?? '');
$gradoId = (int) ($_GET['grado_id'] ?? 0);
$grupoId = (int) ($_GET['grupo_id'] ?? 0);

$sql = "SELECT u.id, u.nombre, u.correo, u.ultimo_acceso, gr.nombre AS grado, g.nombre AS grupo
        FROM usuarios u INNER JOIN roles r ON r.id=u.rol_id
        LEFT JOIN grupos g ON g.id=u.grupo_id LEFT JOIN grados gr ON gr.id=g.grado_id
        WHERE r.nombre='estudiante'" . sqlColegio('u.colegio_id');
$params = [];
if ($busqueda) { $sql .= ' AND (u.nombre LIKE :b OR u.correo LIKE :b)'; $params['b'] = "%$busqueda%"; }
if ($grupoId) { $sql .= ' AND u.grupo_id = :grupo_id'; $params['grupo_id'] = $grupoId; }
elseif ($gradoId) { $sql .= ' AND g.grado_id = :grado_id'; $params['grado_id'] = $gradoId; }
$sql .= ' ORDER BY u.nombre';
$c = $pdo->prepare($sql); $c->execute($params);
$estudiantes = $c->fetchAll();

$grados = Grado::listarTodos($pdo);
$todosLosGrupos = Grupo::listarConGrado($pdo);

$tituloPagina = 'Estudiantes';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Estudiantes</h1>
<div class="buscador-barra u-mb-lg">
  <form action="estudiantes.php" method="GET" style="display:flex;gap:8px;width:100%;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:1;min-width:200px">
      <label for="busqueda">Buscar</label>
      <input type="text" id="busqueda" name="busqueda" placeholder="Nombre o correo..." value="<?= htmlspecialchars($busqueda) ?>">
    </div>
    <div>
      <label for="grado_id">Grado</label>
      <select id="grado_id" name="grado_id">
        <option value="">Todos</option>
        <?php foreach ($grados as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $gradoId === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="grupo_id">Grupo</label>
      <select id="grupo_id" name="grupo_id">
        <option value="">Todos</option>
        <?php foreach ($todosLosGrupos as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $grupoId === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['grado'] . ' ' . $g['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit">Filtrar</button>
    <?php if ($busqueda || $gradoId || $grupoId): ?><a href="estudiantes.php" class="boton-secundario">Quitar filtros</a><?php endif; ?>
  </form>
</div>
<p class="texto-suave u-flex u-gap-sm" style="align-items:center;justify-content:space-between;flex-wrap:wrap;">
  <span><?= count($estudiantes) ?> estudiante(s).</span>
  <?php if ($grupoId): ?>
    <a href="grupo_pdf.php?grupo_id=<?= $grupoId ?>" class="boton-secundario" data-sonido="clic">📄 Exportar grupo a PDF</a>
    <a href="grupo_pdf.php?grupo_id=<?= $grupoId ?>&qr=1" class="boton-secundario" data-sonido="clic" title="PDF con código QR para verificar su autenticidad">PDF con QR</a>
  <?php endif; ?>
</p>
<?php if (empty($estudiantes)): ?>
  <div class="estado-vacio"><p>No se encontraron estudiantes.</p></div>
<?php else: ?>
<div class="tabla-wrapper">
<table class="tabla-datos tabla-adaptable">
  <thead><tr><th>Nombre</th><th>Grado/Grupo</th><th>Último acceso</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($estudiantes as $e): ?>
    <tr>
      <td data-etiqueta="Nombre"><?= htmlspecialchars($e['nombre']) ?></td>
      <td data-etiqueta="Grado/Grupo"><?= htmlspecialchars(($e['grado'] ?? '—').' '.($e['grupo'] ?? '')) ?></td>
      <td data-etiqueta="Último acceso"><?= $e['ultimo_acceso'] ? date('d/m/Y', strtotime($e['ultimo_acceso'])) : '—' ?></td>
      <td data-etiqueta=""><a href="estudiante_detalle.php?id=<?= $e['id'] ?>" class="boton-acceso">Ver detalle</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
