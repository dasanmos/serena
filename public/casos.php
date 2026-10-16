<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Expediente.php';

// Casos de convivencia y de aula de apoyo del colegio (coordinación, rectoría, administración y orientación).
requiereRol('coordinador', 'rector', 'administrador', 'psicologo', 'orientador');
$rol = (string) $_SESSION['rol'];
$cap = Expediente::capacidades($rol);
$colegioId = colegioParaCrear();
$vista = (string) ($_GET['vista'] ?? 'convivencia') === 'apoyo' ? 'apoyo' : 'convivencia';
$estado = (string) ($_GET['estado'] ?? '');
$situacion = (string) ($_GET['situacion'] ?? '');
$procesos = ($vista === 'convivencia' && $cap['ver_convivencia']) ? Expediente::listarProcesos($pdo, $colegioId, $estado, $situacion) : [];
$apoyos = ($vista === 'apoyo' && $cap['ver_apoyo_completo']) ? Expediente::listarApoyos($pdo, $colegioId, $estado) : [];
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Casos';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Casos</h1>
<p class="texto-suave">Información reservada: úsala solo para la atención del estudiante. Cada consulta queda en auditoría.</p>
<div class="acad-acciones">
    <a class="<?= $vista === 'convivencia' ? '' : 'boton-secundario' ?>" href="casos.php?vista=convivencia">Convivencia</a>
    <a class="<?= $vista === 'apoyo' ? '' : 'boton-secundario' ?>" href="casos.php?vista=apoyo">Aula de apoyo</a>
</div>

<?php if ($vista === 'convivencia'): ?>
<form action="casos.php" method="GET" class="acad-filtros">
    <input type="hidden" name="vista" value="convivencia">
    <label>Estado
        <select name="estado" onchange="this.form.submit()"><option value="">Todos</option>
            <?php foreach (Expediente::ESTADOS_PROCESO as $k => $t): ?><option value="<?= $e($k) ?>" <?= $estado === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
    <label>Situación
        <select name="situacion" onchange="this.form.submit()"><option value="">Todas</option>
            <?php foreach (Expediente::SITUACIONES as $k => $t): ?><option value="<?= $e($k) ?>" <?= $situacion === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
</form>
<?php if (!$procesos): ?><div class="estado-vacio"><p>No hay procesos con esos filtros.</p></div><?php else: ?>
<div class="acad-lista">
    <?php foreach ($procesos as $p): ?>
        <div class="acad-fila">
            <div class="acad-fila-info">
                <strong><?= $e($p['estudiante']) ?></strong> <small><?= $p['grupo'] ? $e($p['grado'] . ' ' . $p['grupo']) : 'sin grupo' ?></small>
                <small><?= $e($p['fecha']) ?> · <?= $e(Expediente::SITUACIONES[$p['situacion_tipo']] ?? $p['situacion_tipo']) ?></small>
            </div>
            <span class="estado-pill estado-<?= $p['estado'] === 'cerrado' ? 'matriculada' : ($p['estado'] === 'reportado' ? 'pendiente' : 'en_revision') ?>"><?= $e(Expediente::ESTADOS_PROCESO[$p['estado']] ?? $p['estado']) ?></span>
            <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/estudiante_ficha.php?est=<?= (int) $p['estudiante_id'] ?>&seccion=procesos#contenido">Abrir</a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<form action="casos.php" method="GET" class="acad-filtros">
    <input type="hidden" name="vista" value="apoyo">
    <label>Estado
        <select name="estado" onchange="this.form.submit()"><option value="">Todos</option>
            <option value="activo" <?= $estado === 'activo' ? 'selected' : '' ?>>Activos</option><option value="cerrado" <?= $estado === 'cerrado' ? 'selected' : '' ?>>Cerrados</option></select></label>
</form>
<?php if (!$apoyos): ?><div class="estado-vacio"><p>No hay planes de apoyo. Créalos desde la hoja de vida de cada estudiante.</p></div><?php else: ?>
<div class="acad-lista">
    <?php foreach ($apoyos as $a): ?>
        <div class="acad-fila">
            <div class="acad-fila-info">
                <strong><?= $e($a['estudiante']) ?></strong> <small><?= $a['grupo'] ? $e($a['grado'] . ' ' . $a['grupo']) : 'sin grupo' ?></small>
                <small>PIAR: <?= $e($a['piar_fecha'] ?: 'sin fecha') ?> · último seguimiento: <?= $e($a['ultimo_seguimiento'] ?: 'ninguno') ?></small>
            </div>
            <span class="estado-pill <?= $a['estado'] === 'activo' ? 'estado-activa' : '' ?>"><?= $e($a['estado']) ?></span>
            <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/estudiante_ficha.php?est=<?= (int) $a['estudiante_id'] ?>&seccion=apoyo#contenido">Abrir</a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
