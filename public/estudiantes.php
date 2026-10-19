<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Expediente.php';

// Directorio de estudiantes para abrir su expediente. El docente solo ve a los de sus grupos.
requiereRol('docente', 'coordinador', 'rector', 'administrador', 'secretaria_academica', 'psicologo', 'orientador', 'superadmin');
$rol = (string) $_SESSION['rol'];
$colegioId = colegioParaCrear();
$grupoId = (int) ($_GET['grupo'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$lista = Expediente::estudiantes($pdo, $rol, (int) $_SESSION['usuario_id'], $colegioId, $grupoId, $q);
$grupos = Academico::grupos($pdo, $colegioId);
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Estudiantes';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Estudiantes</h1>
<p class="texto-suave">Abre la hoja de vida (matrícula, desarrollo, observador, procesos, horas, apoyo y asistencia). <?= $rol === 'docente' ? 'Ves a los estudiantes de los grupos donde das clase o que diriges.' : '' ?></p>

<form action="estudiantes.php" method="GET" class="acad-filtros">
    <label>Grupo
        <select name="grupo" onchange="this.form.submit()">
            <option value="0">Todos</option>
            <?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $grupoId === (int) $g['id'] ? 'selected' : '' ?>><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Buscar <input type="search" name="q" value="<?= $e($q) ?>" placeholder="Nombre o correo"></label>
    <button type="submit">Buscar</button>
</form>

<?php if (!$lista): ?>
    <div class="estado-vacio"><p>No hay estudiantes con esos filtros.</p></div>
<?php else: ?>
<div class="acad-lista">
    <?php foreach ($lista as $s): ?>
        <div class="acad-fila">
            <div class="acad-fila-info"><strong><?= $e($s['nombre']) ?></strong><small><?= $s['grupo'] ? $e($s['grado'] . ' ' . $s['grupo']) : 'sin grupo' ?></small></div>
            <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/estudiante_ficha.php?est=<?= (int) $s['id'] ?>">Abrir hoja de vida</a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
