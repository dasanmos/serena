<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/helpers/captura.php';
require_once __DIR__ . '/../src/modelos/Informes.php';

// Refuerzos y planes de mejoramiento: se alimentan de las notas del período y alimentan los informes de la categoría 6.
requiereRol('docente', 'coordinador', 'administrador', 'rector', 'secretaria_academica');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$e = static fn ($v): string => htmlspecialchars((string) $v);
$ctx = Informes::contexto($pdo, $colegioId, $rol, $usuarioId);
$anio = Academico::anioActual($pdo, $colegioId);
$listo = Informes::tablaExiste($pdo, 'refuerzos') && Informes::tablaExiste($pdo, 'planes_mejoramiento');
$asigs = $anio ? Informes::asignaciones($pdo, $ctx, (int) $anio['id']) : [];
$asigPorId = [];
foreach ($asigs as $a) {
    $asigPorId[(int) $a['id']] = $a;
}
$periodos = $anio ? Academico::periodos($pdo, (int) $anio['id']) : [];
$url = 'refuerzos.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $listo && $anio) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    if ($accion === 'generar') {
        $a = $asigPorId[(int) ($_POST['asignacion'] ?? 0)] ?? null;
        $p = Evaluacion::periodo($pdo, (int) ($_POST['periodo'] ?? 0), $colegioId);
        $lim = capFecha($_POST['fecha_limite'] ?? '');
        if ($a === null || $p === null || (int) $p['anio_lectivo_id'] !== (int) $anio['id'] || $ctx['escala'] === null) {
            capVolver('flash_refuerzos', $url, '', 'Elige una asignatura y un período del año actual (y configura la escala de evaluación).');
        }
        $ests = Evaluacion::estudiantesDeGrupo($pdo, $colegioId, $anio, (int) $a['grupo_id']);
        $q = $pdo->prepare('SELECT estudiante_id, valor FROM notas_periodo WHERE asignacion_id = ? AND periodo_id = ? AND valor IS NOT NULL');
        $q->execute([(int) $a['id'], (int) $p['id']]);
        $notas = [];
        foreach ($q->fetchAll() as $r) {
            $notas[(int) $r['estudiante_id']] = (float) $r['valor'];
        }
        $n = 0;
        $ins = $pdo->prepare('INSERT IGNORE INTO refuerzos (colegio_id, anio_lectivo_id, periodo_id, asignacion_id, estudiante_id, nota_original, fecha_limite, creado_por) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($ests as $es) {
            $v = $notas[(int) $es['id']] ?? null;
            if ($v !== null && Informes::aprueba($ctx['escala'], $v) === false) {
                $ins->execute([$colegioId, (int) $anio['id'], (int) $p['id'], (int) $a['id'], (int) $es['id'], $v, $lim, $usuarioId]);
                $n += $ins->rowCount();
            }
        }
        auditarAccion($pdo, 'generar_refuerzos', 'refuerzos', (int) $a['id'], [], ['periodo' => (int) $p['id'], 'creados' => $n]);
        capVolver('flash_refuerzos', $url, "Refuerzos generados: {$n} (los que ya existían no se duplican).");
    }
    if ($accion === 'resultado') {
        $q = $pdo->prepare('SELECT r.* FROM refuerzos r WHERE r.id = ? AND r.colegio_id = ?');
        $q->execute([(int) ($_POST['id'] ?? 0), $colegioId]);
        $r = $q->fetch();
        if (!$r || !isset($asigPorId[(int) $r['asignacion_id']])) {
            capVolver('flash_refuerzos', $url, '', 'Refuerzo no encontrado o sin acceso.');
        }
        $nota = capNumero($_POST['nota'] ?? '');
        $min = (float) $ctx['escala']['valor_min'];
        $max = (float) $ctx['escala']['valor_max'];
        if ($nota === null || $nota < $min || $nota > $max) {
            capVolver('flash_refuerzos', $url, '', "La nota debe estar entre {$min} y {$max}.");
        }
        $estado = Informes::aprueba($ctx['escala'], $nota) ? 'aprobado' : 'reprobado';
        $pdo->prepare('UPDATE refuerzos SET nota_refuerzo = ?, estado = ?, fecha_presentacion = CURDATE(), observacion = ? WHERE id = ?')
            ->execute([$nota, $estado, capTexto($_POST['observacion'] ?? '', 300), (int) $r['id']]);
        auditarAccion($pdo, 'calificar_refuerzo', 'refuerzos', (int) $r['id'], ['nota' => $r['nota_refuerzo'], 'estado' => $r['estado']], ['nota' => $nota, 'estado' => $estado]);
        capVolver('flash_refuerzos', $url . '?vista=refuerzos', 'Resultado del refuerzo guardado.');
    }
    if ($accion === 'plan_crear') {
        $est = (int) ($_POST['estudiante'] ?? 0);
        $ini = capFecha($_POST['inicio'] ?? '');
        $a = $asigPorId[(int) ($_POST['asignacion'] ?? 0)] ?? null;
        if (!Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, $est, $colegioId) || $ini === null) {
            capVolver('flash_refuerzos', $url . '?vista=planes', '', 'Elige un estudiante de tu alcance y la fecha de inicio.');
        }
        $pdo->prepare('INSERT INTO planes_mejoramiento (colegio_id, anio_lectivo_id, periodo_id, estudiante_id, asignacion_id, fecha_inicio, fecha_fin, nota_inicial, compromisos, responsable_id) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, (int) $anio['id'], (int) ($_POST['periodo'] ?? 0) ?: null, $est, $a ? (int) $a['id'] : null, $ini, capFecha($_POST['fin'] ?? ''), capNumero($_POST['nota_inicial'] ?? ''), capTexto($_POST['compromisos'] ?? '', 4000), $usuarioId]);
        capVolver('flash_refuerzos', $url . '?vista=planes', 'Plan de mejoramiento creado.');
    }
    if ($accion === 'plan_estado') {
        $estado = (string) ($_POST['estado'] ?? '');
        if (!in_array($estado, ['activo', 'cumplido', 'incumplido'], true)) {
            capVolver('flash_refuerzos', $url . '?vista=planes', '', 'Estado no válido.');
        }
        $q = $pdo->prepare('SELECT * FROM planes_mejoramiento WHERE id = ? AND colegio_id = ?');
        $q->execute([(int) ($_POST['id'] ?? 0), $colegioId]);
        $pl = $q->fetch();
        if (!$pl || !Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, (int) $pl['estudiante_id'], $colegioId)) {
            capVolver('flash_refuerzos', $url . '?vista=planes', '', 'Plan no encontrado o sin acceso.');
        }
        $pdo->prepare('UPDATE planes_mejoramiento SET estado = ?, nota_final = ? WHERE id = ?')->execute([$estado, capNumero($_POST['nota_final'] ?? ''), (int) $pl['id']]);
        auditarAccion($pdo, 'estado_plan_mejoramiento', 'planes_mejoramiento', (int) $pl['id'], ['estado' => $pl['estado'], 'nota_final' => $pl['nota_final']], ['estado' => $estado]);
        capVolver('flash_refuerzos', $url . '?vista=planes', 'Plan actualizado.');
    }
    capVolver('flash_refuerzos', $url, '', 'Acción no válida.');
}

$flash = capFlash('flash_refuerzos');
$vista = (string) ($_GET['vista'] ?? 'refuerzos') === 'planes' ? 'planes' : 'refuerzos';
$refuerzos = [];
$planes = [];
$estudiantes = [];
if ($listo && $anio) {
    $ids = Informes::in(array_keys($asigPorId));
    $q = $pdo->prepare("SELECT r.*, u.nombre, s.nombre AS asignatura, p.nombre AS periodo, g.nombre AS grupo, gr.nombre AS grado
                          FROM refuerzos r INNER JOIN usuarios u ON u.id = r.estudiante_id INNER JOIN asignaciones_academicas a ON a.id = r.asignacion_id INNER JOIN asignaturas s ON s.id = a.asignatura_id
                          INNER JOIN periodos p ON p.id = r.periodo_id INNER JOIN grupos g ON g.id = a.grupo_id INNER JOIN grados gr ON gr.id = g.grado_id
                         WHERE r.colegio_id = ? AND r.anio_lectivo_id = ? AND r.asignacion_id IN ({$ids}) ORDER BY FIELD(r.estado, 'pendiente', 'presentado', 'reprobado', 'aprobado'), r.fecha_limite, u.nombre LIMIT 500");
    $q->execute([$colegioId, (int) $anio['id']]);
    $refuerzos = $q->fetchAll();
    $q = $pdo->prepare('SELECT pl.*, u.nombre, s.nombre AS asignatura FROM planes_mejoramiento pl INNER JOIN usuarios u ON u.id = pl.estudiante_id
                          LEFT JOIN asignaciones_academicas a ON a.id = pl.asignacion_id LEFT JOIN asignaturas s ON s.id = a.asignatura_id
                         WHERE pl.colegio_id = ? AND pl.anio_lectivo_id = ?' . ($rol === 'docente' ? ' AND pl.responsable_id = ' . $usuarioId : '') . ' ORDER BY FIELD(pl.estado, \'activo\', \'incumplido\', \'cumplido\'), pl.fecha_inicio DESC LIMIT 500');
    $q->execute([$colegioId, (int) $anio['id']]);
    $planes = $q->fetchAll();
    $estudiantes = Expediente::estudiantes($pdo, $rol, $usuarioId, $colegioId, 0, '');
}

$tituloPagina = 'Refuerzos y planes';
$cssExtra = ['/assets/css/academico.css', '/assets/css/informes.css'];
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Refuerzos y planes de mejoramiento</h1>
<p class="texto-suave">Genera los refuerzos de quienes perdieron una asignatura en el período, registra su resultado y lleva los planes de mejoramiento. Los informes están en <a href="informes.php">Informes</a> (categoría 6).</p>
<?php if (!$listo): ?><p class="mensaje mensaje-aviso">Falta aplicar <code>sql/migraciones/040_informes.sql</code>.</p><?php endif; ?>
<?php if (!$anio): ?><p class="mensaje mensaje-aviso">No hay año lectivo actual.</p><?php endif; ?>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<?php if ($listo && $anio): ?>
<p><a href="?vista=refuerzos"><?= $vista === 'refuerzos' ? '<strong>Refuerzos</strong>' : 'Refuerzos' ?></a> · <a href="?vista=planes"><?= $vista === 'planes' ? '<strong>Planes de mejoramiento</strong>' : 'Planes de mejoramiento' ?></a></p>

<?php if ($vista === 'refuerzos'): ?>
<section class="inf-panel">
    <h2>Generar refuerzos del período</h2>
    <form method="POST" class="inf-filtros"><?php campoCSRF(); ?><input type="hidden" name="accion" value="generar">
        <label class="inf-ancho">Asignatura y grupo<select name="asignacion" required><option value="">— elige —</option>
            <?php foreach ($asigs as $a): ?><option value="<?= (int) $a['id'] ?>"><?= $e($a['asignatura']) ?> · <?= $e(trim($a['grado'] . ' ' . $a['grupo'])) ?></option><?php endforeach; ?></select></label>
        <label>Período<select name="periodo" required><option value="">— elige —</option><?php foreach ($periodos as $p): ?><option value="<?= (int) $p['id'] ?>"><?= $e($p['nombre']) ?></option><?php endforeach; ?></select></label>
        <label>Fecha límite<input type="date" name="fecha_limite"></label>
        <div class="inf-acciones"><button type="submit">Generar para quienes perdieron</button></div>
    </form>
    <p class="inf-nota">Crea un refuerzo por cada estudiante con nota del período que no aprueba según la escala. No duplica los existentes.</p>
</section>
<div class="inf-scroll"><table class="inf-tabla">
    <thead><tr><th>Estudiante</th><th>Asignatura</th><th>Período</th><th>Original</th><th>Límite</th><th>Estado</th><th>Registrar resultado</th></tr></thead><tbody>
    <?php foreach ($refuerzos as $r): ?><tr>
        <td class="a-i"><?= $e($r['nombre']) ?> <small><?= $e(trim($r['grado'] . ' ' . $r['grupo'])) ?></small></td><td class="a-i"><?= $e($r['asignatura']) ?></td><td class="a-c"><?= $e($r['periodo']) ?></td>
        <td class="a-c"><?= $e($r['nota_original']) ?></td><td class="a-c"><?= $e(Informes::fecha((string) $r['fecha_limite'])) ?></td>
        <td class="a-c"><?= $e(ucfirst((string) $r['estado'])) ?><?= $r['nota_refuerzo'] !== null ? ' (' . $e($r['nota_refuerzo']) . ')' : '' ?></td>
        <td><form method="POST" style="display:flex;gap:.3rem"><?php campoCSRF(); ?><input type="hidden" name="accion" value="resultado"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="text" inputmode="decimal" name="nota" size="4" placeholder="nota" required><button type="submit">Guardar</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$refuerzos): ?><tr><td colspan="7" class="inf-vacio">Aún no hay refuerzos.</td></tr><?php endif; ?>
    </tbody></table></div>

<?php else: ?>
<section class="inf-panel">
    <h2>Nuevo plan de mejoramiento</h2>
    <form method="POST" class="inf-filtros"><?php campoCSRF(); ?><input type="hidden" name="accion" value="plan_crear">
        <label class="inf-ancho">Estudiante<select name="estudiante" required><option value="">— elige —</option>
            <?php foreach ($estudiantes as $es): ?><option value="<?= (int) $es['id'] ?>"><?= $e($es['nombre']) ?> · <?= $e(trim($es['grado'] . ' ' . $es['grupo'])) ?></option><?php endforeach; ?></select></label>
        <label>Asignatura (opcional)<select name="asignacion"><option value="0">General</option><?php foreach ($asigs as $a): ?><option value="<?= (int) $a['id'] ?>"><?= $e($a['asignatura']) ?> · <?= $e(trim($a['grado'] . ' ' . $a['grupo'])) ?></option><?php endforeach; ?></select></label>
        <label>Período<select name="periodo"><option value="0">—</option><?php foreach ($periodos as $p): ?><option value="<?= (int) $p['id'] ?>"><?= $e($p['nombre']) ?></option><?php endforeach; ?></select></label>
        <label>Inicio<input type="date" name="inicio" required value="<?= $e(date('Y-m-d')) ?>"></label><label>Fin previsto<input type="date" name="fin"></label>
        <label>Nota inicial<input type="text" inputmode="decimal" name="nota_inicial" size="4"></label>
        <label class="inf-ancho">Compromisos y actividades<textarea name="compromisos" rows="3" maxlength="4000"></textarea></label>
        <div class="inf-acciones"><button type="submit">Crear plan</button></div>
    </form>
</section>
<div class="inf-scroll"><table class="inf-tabla">
    <thead><tr><th>Estudiante</th><th>Asignatura</th><th>Inicio</th><th>Inicial</th><th>Estado</th><th>Actualizar</th></tr></thead><tbody>
    <?php foreach ($planes as $p): ?><tr>
        <td class="a-i"><?= $e($p['nombre']) ?></td><td class="a-i"><?= $e($p['asignatura'] ?: 'General') ?></td><td class="a-c"><?= $e(Informes::fecha((string) $p['fecha_inicio'])) ?></td><td class="a-c"><?= $e($p['nota_inicial']) ?></td>
        <td class="a-c"><?= $e(ucfirst((string) $p['estado'])) ?><?= $p['nota_final'] !== null ? ' (' . $e($p['nota_final']) . ')' : '' ?></td>
        <td><form method="POST" style="display:flex;gap:.3rem"><?php campoCSRF(); ?><input type="hidden" name="accion" value="plan_estado"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <select name="estado"><?php foreach (['activo', 'cumplido', 'incumplido'] as $s): ?><option value="<?= $s ?>" <?= $p['estado'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
            <input type="text" inputmode="decimal" name="nota_final" size="4" placeholder="nota final"><button type="submit">Guardar</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$planes): ?><tr><td colspan="6" class="inf-vacio">Aún no hay planes.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
