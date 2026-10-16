<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/helpers/captura.php';
require_once __DIR__ . '/../src/modelos/Informes.php';

// Restaurante escolar: beneficiarios, asistencia diaria y pagos. Alimenta la categoría 11 de Informes.
requiereRol('secretaria_academica', 'administrador', 'rector', 'coordinador');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$e = static fn ($v): string => htmlspecialchars((string) $v);
$anio = Academico::anioActual($pdo, $colegioId);
$listo = Informes::tablaExiste($pdo, 'restaurante_beneficiarios') && Informes::tablaExiste($pdo, 'restaurante_asistencia') && Informes::tablaExiste($pdo, 'restaurante_pagos');
$vista = in_array((string) ($_GET['vista'] ?? ''), ['asistencia', 'pagos'], true) ? (string) $_GET['vista'] : 'beneficiarios';
$url = 'restaurante.php?vista=' . $vista;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $listo && $anio) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $anioId = (int) $anio['id'];
    if ($accion === 'agregar') {
        $est = (int) ($_POST['estudiante'] ?? 0);
        $jor = in_array((string) ($_POST['jornada'] ?? ''), ['desayuno', 'almuerzo', 'refrigerio'], true) ? (string) $_POST['jornada'] : 'almuerzo';
        $tipo = ($_POST['tipo'] ?? '') === 'pago' ? 'pago' : 'gratuito';
        if (!Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, $est, $colegioId)) {
            capVolver('flash_rest', $url, '', 'Estudiante no válido.');
        }
        $pdo->prepare('INSERT INTO restaurante_beneficiarios (colegio_id, anio_lectivo_id, estudiante_id, jornada, tipo, observacion) VALUES (?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE jornada = VALUES(jornada), tipo = VALUES(tipo), activo = 1, observacion = VALUES(observacion)')
            ->execute([$colegioId, $anioId, $est, $jor, $tipo, capTexto($_POST['observacion'] ?? '', 200)]);
        capVolver('flash_rest', $url, 'Beneficiario guardado.');
    }
    if ($accion === 'quitar') {
        $pdo->prepare('UPDATE restaurante_beneficiarios SET activo = 0 WHERE id = ? AND colegio_id = ?')->execute([(int) ($_POST['id'] ?? 0), $colegioId]);
        capVolver('flash_rest', $url, 'Beneficiario retirado del restaurante.');
    }
    if ($accion === 'asistencia') {
        $fecha = capFecha($_POST['fecha'] ?? '');
        if ($fecha === null) {
            capVolver('flash_rest', $url, '', 'Fecha no válida.');
        }
        $q = $pdo->prepare('SELECT estudiante_id FROM restaurante_beneficiarios WHERE colegio_id = ? AND anio_lectivo_id = ? AND activo = 1');
        $q->execute([$colegioId, $anioId]);
        $validos = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        $marcados = array_map('intval', (array) ($_POST['asistio'] ?? []));
        $s = $pdo->prepare('INSERT INTO restaurante_asistencia (colegio_id, fecha, estudiante_id, asistio, registrado_por) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE asistio = VALUES(asistio), registrado_por = VALUES(registrado_por)');
        $ids = array_map('intval', (array) ($_POST['lista'] ?? []));
        foreach ($ids as $id) {
            if (in_array($id, $validos, true)) {
                $s->execute([$colegioId, $fecha, $id, in_array($id, $marcados, true) ? 1 : 0, $usuarioId]);
            }
        }
        capVolver('flash_rest', 'restaurante.php?vista=asistencia&fecha=' . $fecha . '&grupo=' . (int) ($_POST['grupo'] ?? 0), 'Asistencia al restaurante guardada.');
    }
    if ($accion === 'pago') {
        $est = (int) ($_POST['estudiante'] ?? 0);
        $mes = (int) ($_POST['mes'] ?? 0);
        $valor = capNumero($_POST['valor'] ?? '');
        $fecha = capFecha($_POST['fecha'] ?? '') ?? date('Y-m-d');
        if (!Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, $est, $colegioId) || $mes < 1 || $mes > 12 || $valor === null || $valor <= 0) {
            capVolver('flash_rest', $url, '', 'Revisa estudiante, mes y valor (mayor que cero).');
        }
        $pdo->prepare('INSERT INTO restaurante_pagos (colegio_id, anio_lectivo_id, estudiante_id, mes, valor, fecha_pago, metodo, recibo, registrado_por) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, $anioId, $est, $mes, $valor, $fecha, capTexto($_POST['metodo'] ?? '', 20), capTexto($_POST['recibo'] ?? '', 30), $usuarioId]);
        auditarAccion($pdo, 'pago_restaurante', 'restaurante_pagos', $est, [], ['mes' => $mes, 'valor' => $valor]);
        capVolver('flash_rest', $url, 'Pago registrado.');
    }
    capVolver('flash_rest', $url, '', 'Acción no válida.');
}

$flash = capFlash('flash_rest');
$estudiantes = Expediente::estudiantes($pdo, $rol, $usuarioId, $colegioId, 0, '');
$grupos = Academico::grupos($pdo, $colegioId);
$ben = [];
if ($listo && $anio) {
    $q = $pdo->prepare('SELECT b.*, u.nombre, g.nombre AS grupo, gr.nombre AS grado, u.grupo_id FROM restaurante_beneficiarios b INNER JOIN usuarios u ON u.id = b.estudiante_id
                          LEFT JOIN grupos g ON g.id = u.grupo_id LEFT JOIN grados gr ON gr.id = g.grado_id WHERE b.colegio_id = ? AND b.anio_lectivo_id = ? AND b.activo = 1 ORDER BY gr.nombre, g.nombre, u.nombre');
    $q->execute([$colegioId, (int) $anio['id']]);
    $ben = $q->fetchAll();
}
$fecha = capFecha($_GET['fecha'] ?? '') ?? date('Y-m-d');
$grupoSel = (int) ($_GET['grupo'] ?? 0);
$marcas = [];
if ($vista === 'asistencia' && $ben) {
    $q = $pdo->prepare('SELECT estudiante_id, asistio FROM restaurante_asistencia WHERE colegio_id = ? AND fecha = ?');
    $q->execute([$colegioId, $fecha]);
    foreach ($q->fetchAll() as $r) {
        $marcas[(int) $r['estudiante_id']] = (int) $r['asistio'];
    }
}
$tituloPagina = 'Restaurante escolar';
$cssExtra = ['/assets/css/academico.css', '/assets/css/informes.css'];
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Restaurante escolar</h1>
<p class="texto-suave">Beneficiarios del año actual, asistencia diaria y pagos. Los informes están en <a href="informes.php">Informes</a> (categoría 11).</p>
<?php if (!$listo): ?><p class="mensaje mensaje-aviso">Falta aplicar <code>sql/migraciones/038_informes.sql</code>.</p><?php endif; ?>
<?php if (!$anio): ?><p class="mensaje mensaje-aviso">No hay año lectivo actual.</p><?php endif; ?>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if ($listo && $anio): ?>
<p><?php foreach (['beneficiarios' => 'Beneficiarios', 'asistencia' => 'Asistencia diaria', 'pagos' => 'Pagos'] as $k => $t): ?><a href="?vista=<?= $k ?>"><?= $vista === $k ? '<strong>' . $e($t) . '</strong>' : $e($t) ?></a> · <?php endforeach; ?></p>

<?php if ($vista === 'beneficiarios'): ?>
<section class="inf-panel"><h2>Agregar beneficiario</h2>
    <form method="POST" class="inf-filtros"><?php campoCSRF(); ?><input type="hidden" name="accion" value="agregar">
        <label class="inf-ancho">Estudiante<select name="estudiante" required><option value="">— elige —</option><?php foreach ($estudiantes as $es): ?><option value="<?= (int) $es['id'] ?>"><?= $e($es['nombre']) ?> · <?= $e(trim($es['grado'] . ' ' . $es['grupo'])) ?></option><?php endforeach; ?></select></label>
        <label>Jornada<select name="jornada"><option value="desayuno">Desayuno</option><option value="almuerzo" selected>Almuerzo</option><option value="refrigerio">Refrigerio</option></select></label>
        <label>Tipo<select name="tipo"><option value="gratuito">Gratuito</option><option value="pago">Pago</option></select></label>
        <label>Observación<input type="text" name="observacion" maxlength="200"></label>
        <div class="inf-acciones"><button type="submit">Guardar</button></div></form></section>
<div class="inf-scroll"><table class="inf-tabla"><thead><tr><th>Estudiante</th><th>Grupo</th><th>Jornada</th><th>Tipo</th><th></th></tr></thead><tbody>
    <?php foreach ($ben as $b): ?><tr><td class="a-i"><?= $e($b['nombre']) ?></td><td class="a-c"><?= $e(trim($b['grado'] . ' ' . $b['grupo'])) ?></td><td class="a-c"><?= $e(ucfirst($b['jornada'])) ?></td><td class="a-c"><?= $e(ucfirst($b['tipo'])) ?></td>
        <td><form method="POST"><?php campoCSRF(); ?><input type="hidden" name="accion" value="quitar"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button type="submit">Quitar</button></form></td></tr>
    <?php endforeach; ?><?php if (!$ben): ?><tr><td colspan="5" class="inf-vacio">Sin beneficiarios.</td></tr><?php endif; ?></tbody></table></div>

<?php elseif ($vista === 'asistencia'): ?>
<form method="GET" class="inf-filtros"><input type="hidden" name="vista" value="asistencia">
    <label>Fecha<input type="date" name="fecha" value="<?= $e($fecha) ?>"></label>
    <label>Grupo<select name="grupo"><option value="0">Todos</option><?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>" <?= (int) $g['id'] === $grupoSel ? 'selected' : '' ?>><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?></select></label>
    <div class="inf-acciones"><button type="submit">Ver lista</button></div></form>
<form method="POST"><?php campoCSRF(); ?><input type="hidden" name="accion" value="asistencia"><input type="hidden" name="fecha" value="<?= $e($fecha) ?>"><input type="hidden" name="grupo" value="<?= $grupoSel ?>">
<div class="inf-scroll"><table class="inf-tabla"><thead><tr><th>Estudiante</th><th>Grupo</th><th>Asistió</th></tr></thead><tbody>
    <?php foreach ($ben as $b): if ($grupoSel > 0 && (int) $b['grupo_id'] !== $grupoSel) { continue; } $id = (int) $b['estudiante_id']; ?>
        <tr><td class="a-i"><?= $e($b['nombre']) ?><input type="hidden" name="lista[]" value="<?= $id ?>"></td><td class="a-c"><?= $e(trim($b['grado'] . ' ' . $b['grupo'])) ?></td>
            <td class="a-c"><input type="checkbox" name="asistio[]" value="<?= $id ?>" <?= ($marcas[$id] ?? 1) === 1 ? 'checked' : '' ?>></td></tr>
    <?php endforeach; ?></tbody></table></div>
<p><button type="submit">Guardar asistencia del <?= $e(Informes::fecha($fecha)) ?></button></p></form>

<?php else: ?>
<section class="inf-panel"><h2>Registrar pago</h2>
    <form method="POST" class="inf-filtros"><?php campoCSRF(); ?><input type="hidden" name="accion" value="pago">
        <label class="inf-ancho">Estudiante<select name="estudiante" required><option value="">— elige —</option><?php foreach ($ben as $b): ?><option value="<?= (int) $b['estudiante_id'] ?>"><?= $e($b['nombre']) ?> · <?= $e(trim($b['grado'] . ' ' . $b['grupo'])) ?></option><?php endforeach; ?></select></label>
        <label>Mes<select name="mes"><?php foreach (Informes::MESES as $n => $t): ?><option value="<?= $n ?>" <?= $n === (int) date('n') ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
        <label>Valor<input type="text" inputmode="decimal" name="valor" required></label><label>Fecha<input type="date" name="fecha" value="<?= $e(date('Y-m-d')) ?>"></label>
        <label>Método<input type="text" name="metodo" maxlength="20" placeholder="efectivo, transferencia…"></label><label>Recibo N.º<input type="text" name="recibo" maxlength="30"></label>
        <div class="inf-acciones"><button type="submit">Registrar</button></div></form></section>
<p class="inf-nota">Los pagos registrados se consultan en el informe «Control de Pagos Restaurante».</p>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
