<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Evaluacion.php';
require_once __DIR__ . '/../src/modelos/Horario.php';

// Horario semanal. Estudiante: el de su grupo. Acudiente: el de sus hijos. Docente: el suyo.
// Personal directivo/académico: el de cualquier grupo o docente de su colegio.
requiereRol('estudiante', 'acudiente', 'docente', 'coordinador', 'administrador', 'rector', 'secretaria_academica');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaConsulta();
if ($colegioId === null) {
    http_response_code(403);
    exit('No se pudo determinar el colegio.');
}
$e = static fn ($v): string => htmlspecialchars((string) $v);
$puedeEditar = in_array($rol, ['administrador', 'rector', 'coordinador', 'secretaria_academica'], true);
$esPersonal = !in_array($rol, ['estudiante', 'acudiente', 'docente'], true);

$anio = Academico::anioActual($pdo, $colegioId);
if ($anio === null) {
    $anios = Academico::anios($pdo, $colegioId);
    $anio = $anios[0] ?? null;
}
$franjas = $anio ? Horario::franjas($pdo, $colegioId) : [];

$tipo = null;          // 'grupo' | 'docente' | null (elegir)
$titulo = '';
$bloques = [];
$permitido = true;
$hijos = [];
$grupoIdVer = 0;

if ($anio !== null && $franjas) {
    if ($rol === 'estudiante' || $rol === 'acudiente') {
        $estId = $usuarioId;
        if ($rol === 'acudiente') {
            $hijos = Academico::hijos($pdo, $usuarioId);
            $estId = (int) ($_GET['est'] ?? ($hijos[0]['id'] ?? 0));
        }
        $permitido = $estId > 0 && Evaluacion::puedeVerBoletin($pdo, $rol, $usuarioId, $estId, $colegioId);
        if ($permitido) {
            $g = Horario::grupoDeEstudiante($pdo, $estId, $anio);
            if ($g !== null) {
                $tipo = 'grupo';
                $titulo = 'Grupo ' . $g['nombre'];
                $grupoIdVer = $g['id'];
                $bloques = Horario::bloquesDeGrupo($pdo, $colegioId, (int) $anio['id'], $g['id']);
            }
        }
    } elseif ($rol === 'docente') {
        $tipo = 'docente';
        $titulo = 'Mi horario de clases';
        $bloques = Horario::bloquesDeDocente($pdo, $colegioId, (int) $anio['id'], $usuarioId);
    } else {
        $gid = (int) ($_GET['grupo'] ?? 0);
        $did = (int) ($_GET['docente'] ?? 0);
        foreach (Academico::grupos($pdo, $colegioId) as $g) {
            if ($gid > 0 && (int) $g['id'] === $gid) {
                $tipo = 'grupo';
                $grupoIdVer = $gid;
                $titulo = 'Grupo ' . $g['grado'] . ' ' . $g['nombre'];
                $bloques = Horario::bloquesDeGrupo($pdo, $colegioId, (int) $anio['id'], $gid);
            }
        }
        if ($tipo === null && $did > 0) {
            $q = $pdo->prepare('SELECT nombre FROM usuarios WHERE id = ? AND colegio_id = ?');
            $q->execute([$did, $colegioId]);
            $nombreDoc = $q->fetchColumn();
            if ($nombreDoc !== false) {
                $tipo = 'docente';
                $titulo = 'Horario de ' . $nombreDoc;
                $bloques = Horario::bloquesDeDocente($pdo, $colegioId, (int) $anio['id'], $did);
            }
        }
    }
}

$gruposLista = [];
$docentesLista = [];
if ($esPersonal && $tipo === null && $anio !== null && $franjas) {
    $gruposLista = Academico::grupos($pdo, $colegioId);
    $q = $pdo->prepare('SELECT DISTINCT u.id, u.nombre FROM asignaciones_academicas a INNER JOIN usuarios u ON u.id = a.docente_id WHERE a.colegio_id = ? AND a.anio_lectivo_id = ? ORDER BY u.nombre');
    $q->execute([$colegioId, (int) $anio['id']]);
    $docentesLista = $q->fetchAll();
}

$hoy = (int) date('N');
$totalClases = 0;
foreach ($bloques as $porFranja) {
    $totalClases += count($porFranja);
}
$dias = range(1, Horario::DIAS_VISIBLES);
if (isset($bloques[6])) {
    $dias[] = 6;
}

$tituloPagina = 'Horario';
$cssExtra = ['/assets/css/academico.css', '/assets/css/horarios.css'];
require __DIR__ . '/parciales/encabezado.php';
?>

<h1>Horario</h1>

<?php if ($rol === 'acudiente' && count($hijos) > 1): ?>
    <form method="GET" class="acad-filtros">
        <label>Estudiante
            <select name="est" onchange="this.form.submit()">
                <?php foreach ($hijos as $h): ?><option value="<?= (int) $h['id'] ?>" <?= (int) $h['id'] === ($estId ?? 0) ? 'selected' : '' ?>><?= $e($h['nombre']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <noscript><button type="submit">Ver</button></noscript>
    </form>
<?php endif; ?>

<?php if ($anio === null): ?>
    <p class="mensaje mensaje-aviso">No hay un año lectivo configurado.</p>
<?php elseif (!$franjas): ?>
    <div class="estado-vacio">
        <p>El colegio todavía no ha publicado los horarios.</p>
        <?php if ($puedeEditar): ?><p><a class="boton-acceso" href="<?= $e(BASE_URL) ?>/horarios_admin.php?vista=franjas">Configurar franjas horarias</a></p><?php endif; ?>
    </div>
<?php elseif (!$permitido): ?>
    <p class="mensaje mensaje-error" role="alert">No tienes permiso para ver este horario.</p>
<?php elseif ($tipo === null && $esPersonal): ?>
    <section class="panel-seccion">
        <h2>Elige un grupo</h2>
        <ul class="horas-asignatura">
            <?php foreach ($gruposLista as $g): ?><li><a href="?grupo=<?= (int) $g['id'] ?>"><?= $e($g['grado'] . ' ' . $g['nombre']) ?></a></li><?php endforeach; ?>
        </ul>
        <?php if ($docentesLista): ?>
            <h2>O un docente</h2>
            <ul class="horas-asignatura">
                <?php foreach ($docentesLista as $d): ?><li><a href="?docente=<?= (int) $d['id'] ?>"><?= $e($d['nombre']) ?></a></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php elseif ($tipo === null): ?>
    <div class="estado-vacio"><p>Todavía no tienes un grupo asignado para este año.</p></div>
<?php else: ?>
    <section class="panel-seccion">
        <h2><?= $e($titulo) ?> — <?= (int) $anio['anio'] ?></h2>
        <?php if ($totalClases === 0): ?>
            <div class="estado-vacio">
                <p>Todavía no hay clases en este horario.</p>
                <?php if ($puedeEditar && $grupoIdVer > 0): ?><p><a class="boton-acceso" href="<?= $e(BASE_URL) ?>/horarios_admin.php?grupo=<?= $grupoIdVer ?>">Armar el horario de este grupo</a></p><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="acad-acciones">
                <?php if ($puedeEditar && $grupoIdVer > 0): ?><a class="boton-secundario" href="<?= $e(BASE_URL) ?>/horarios_admin.php?grupo=<?= $grupoIdVer ?>">Editar horario</a><?php endif; ?>
                <?php if ($esPersonal): ?><a class="boton-secundario" href="<?= $e(BASE_URL) ?>/horario.php">Otro grupo o docente</a><?php endif; ?>
                <button type="button" class="boton-secundario" onclick="window.print()">Imprimir</button>
            </div>
            <div class="horario-envoltura">
                <table class="horario-tabla">
                    <caption class="sr-only"><?= $e($titulo) ?>: clases por día y hora</caption>
                    <thead>
                        <tr>
                            <th scope="col">Hora</th>
                            <?php foreach ($dias as $d): ?>
                                <th scope="col"<?= $d === $hoy ? ' class="hoy" aria-current="date"' : '' ?>><?= $e(Horario::DIAS[$d]) ?><?= $d === $hoy ? '<span class="marca-hoy">Hoy</span>' : '' ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($franjas as $fr): ?>
                        <?php if ($fr['descanso']): ?>
                            <tr class="franja-descanso">
                                <th scope="row"><?= $e($fr['nombre']) ?><small><?= $e($fr['inicio'] . '–' . $fr['fin']) ?></small></th>
                                <td colspan="<?= count($dias) ?>">Descanso</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th scope="row"><?= $e($fr['nombre']) ?><small><?= $e($fr['inicio'] . '–' . $fr['fin']) ?></small></th>
                                <?php foreach ($dias as $d): $b = $bloques[$d][$fr['id']] ?? null; ?>
                                    <td<?= $d === $hoy ? ' class="hoy"' : '' ?>>
                                        <?php if ($b === null): ?>
                                            <span class="horario-libre" aria-label="Libre">—</span>
                                        <?php elseif ($tipo === 'grupo'): ?>
                                            <div class="horario-celda"><strong><?= $e($b['asignatura']) ?></strong>
                                                <?php if (!empty($b['docente'])): ?><span><?= $e($b['docente']) ?></span><?php endif; ?>
                                                <?php if (!empty($b['salon'])): ?><span>Salón <?= $e($b['salon']) ?></span><?php endif; ?></div>
                                        <?php else: ?>
                                            <div class="horario-celda"><strong><?= $e($b['grupo']) ?></strong><span><?= $e($b['asignatura']) ?></span>
                                                <?php if (!empty($b['salon'])): ?><span>Salón <?= $e($b['salon']) ?></span><?php endif; ?></div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="horario-nota"><?= (int) $totalClases ?> clase(s) por semana.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/parciales/pie.php'; ?>
