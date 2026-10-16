<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/helpers/captura.php';
require_once __DIR__ . '/../src/modelos/Informes.php';

// Cartera: cargos (pensión, matrícula, otros) y pagos por estudiante. Alimenta la categoría 12 de Informes. Todo se audita.
requiereRol('secretaria_academica', 'administrador', 'rector');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$e = static fn ($v): string => htmlspecialchars((string) $v);
$anio = Academico::anioActual($pdo, $colegioId);
$listo = Informes::tablaExiste($pdo, 'cartera_cargos') && Informes::tablaExiste($pdo, 'cartera_pagos');
$sel = (int) ($_GET['estudiante'] ?? 0);
$url = 'cartera.php' . ($sel > 0 ? '?estudiante=' . $sel : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $listo && $anio) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $anioId = (int) $anio['id'];
    $concepto = capTexto($_POST['concepto'] ?? '', 80);
    $mes = (int) ($_POST['mes'] ?? 0);
    $mes = ($mes >= 1 && $mes <= 12) ? $mes : null;
    $valor = capNumero($_POST['valor'] ?? '');
    $vence = capFecha($_POST['vence'] ?? '');
    if ($accion === 'cargo_masivo') {
        if ($concepto === null || $valor === null || $valor <= 0) {
            capVolver('flash_cartera', $url, '', 'Escribe el concepto y un valor mayor que cero.');
        }
        $grupo = (int) ($_POST['grupo'] ?? 0);
        $grado = (int) ($_POST['grado'] ?? 0);
        if ($grupo === 0 && $grado === 0) {
            capVolver('flash_cartera', $url, '', 'Elige un grado o un grupo para crear los cargos.');
        }
        $f = ['anio' => $anioId, 'anio_row' => $anio, 'grupo' => $grupo, 'grado' => $grado, 'estudiante' => 0];
        $ctx = Informes::contexto($pdo, $colegioId, $rol, $usuarioId);
        $roster = Informes::roster($pdo, $ctx, $f);
        $ins = $pdo->prepare('INSERT INTO cartera_cargos (colegio_id, anio_lectivo_id, estudiante_id, concepto, mes, valor, vence, creado_por) VALUES (?,?,?,?,?,?,?,?)');
        $dup = $pdo->prepare('SELECT 1 FROM cartera_cargos WHERE colegio_id = ? AND anio_lectivo_id = ? AND estudiante_id = ? AND concepto = ? AND mes <=> ?');
        $n = 0;
        foreach ($roster as $r) {
            $dup->execute([$colegioId, $anioId, (int) $r['id'], $concepto, $mes]);
            if (!$dup->fetchColumn()) {
                $ins->execute([$colegioId, $anioId, (int) $r['id'], $concepto, $mes, $valor, $vence, $usuarioId]);
                $n++;
            }
        }
        auditarAccion($pdo, 'cargos_masivos', 'cartera_cargos', 0, [], ['concepto' => $concepto, 'mes' => $mes, 'valor' => $valor, 'cargos' => $n]);
        capVolver('flash_cartera', $url, "Cargos creados: {$n} (no se duplican los que ya existían con el mismo concepto y mes).");
    }
    if ($accion === 'cargo') {
        $est = (int) ($_POST['estudiante'] ?? 0);
        if (!Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, $est, $colegioId) || $concepto === null || $valor === null || $valor <= 0) {
            capVolver('flash_cartera', $url, '', 'Revisa estudiante, concepto y valor.');
        }
        $pdo->prepare('INSERT INTO cartera_cargos (colegio_id, anio_lectivo_id, estudiante_id, concepto, mes, valor, vence, creado_por) VALUES (?,?,?,?,?,?,?,?)')->execute([$colegioId, $anioId, $est, $concepto, $mes, $valor, $vence, $usuarioId]);
        auditarAccion($pdo, 'cargo_cartera', 'cartera_cargos', $est, [], ['concepto' => $concepto, 'valor' => $valor]);
        capVolver('flash_cartera', 'cartera.php?estudiante=' . $est, 'Cargo creado.');
    }
    if ($accion === 'pago') {
        $q = $pdo->prepare('SELECT c.*, COALESCE((SELECT SUM(p.valor) FROM cartera_pagos p WHERE p.cargo_id = c.id), 0) AS pagado FROM cartera_cargos c WHERE c.id = ? AND c.colegio_id = ?');
        $q->execute([(int) ($_POST['cargo'] ?? 0), $colegioId]);
        $c = $q->fetch();
        if (!$c || $valor === null || $valor <= 0) {
            capVolver('flash_cartera', $url, '', 'Cargo o valor no válido.');
        }
        if ($valor > (float) $c['valor'] - (float) $c['pagado'] + 0.009) {
            capVolver('flash_cartera', $url, '', 'El pago supera el saldo del cargo.');
        }
        $pdo->prepare('INSERT INTO cartera_pagos (colegio_id, cargo_id, estudiante_id, valor, fecha, metodo, recibo, registrado_por) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, (int) $c['id'], (int) $c['estudiante_id'], $valor, capFecha($_POST['fecha'] ?? '') ?? date('Y-m-d'), capTexto($_POST['metodo'] ?? '', 20), capTexto($_POST['recibo'] ?? '', 30), $usuarioId]);
        auditarAccion($pdo, 'pago_cartera', 'cartera_pagos', (int) $c['id'], [], ['valor' => $valor, 'estudiante_id' => (int) $c['estudiante_id']]);
        capVolver('flash_cartera', 'cartera.php?estudiante=' . (int) $c['estudiante_id'], 'Pago registrado.');
    }
    capVolver('flash_cartera', $url, '', 'Acción no válida.');
}

$flash = capFlash('flash_cartera');
$estudiantes = Expediente::estudiantes($pdo, $rol, $usuarioId, $colegioId, 0, '');
$grados = Academico::grados($pdo, $colegioId);
$grupos = Academico::grupos($pdo, $colegioId);
$cargos = [];
if ($listo && $anio && $sel > 0 && Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, $sel, $colegioId)) {
    $q = $pdo->prepare('SELECT c.*, COALESCE((SELECT SUM(p.valor) FROM cartera_pagos p WHERE p.cargo_id = c.id), 0) AS pagado FROM cartera_cargos c WHERE c.colegio_id = ? AND c.anio_lectivo_id = ? AND c.estudiante_id = ? ORDER BY c.mes IS NULL, c.mes, c.id');
    $q->execute([$colegioId, (int) $anio['id'], $sel]);
    $cargos = $q->fetchAll();
} else {
    $sel = 0;
}
$tituloPagina = 'Cartera';
$cssExtra = ['/assets/css/academico.css', '/assets/css/informes.css'];
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Cartera</h1>
<p class="texto-suave">Cargos y pagos de los estudiantes del año actual. Los informes (estados de cuenta, saldos y pensión mensual) están en <a href="informes.php">Informes</a> (categoría 12).</p>
<?php if (!$listo): ?><p class="mensaje mensaje-aviso">Falta aplicar <code>sql/migraciones/038_informes.sql</code>.</p><?php endif; ?>
<?php if (!$anio): ?><p class="mensaje mensaje-aviso">No hay año lectivo actual.</p><?php endif; ?>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if ($listo && $anio): ?>
<section class="inf-panel"><h2>Crear cargo para todo un grado o grupo</h2>
    <form method="POST" class="inf-filtros"><?php campoCSRF(); ?><input type="hidden" name="accion" value="cargo_masivo">
        <label>Grado<select name="grado"><option value="0">—</option><?php foreach ($grados as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $e($g['nombre']) ?></option><?php endforeach; ?></select></label>
        <label>o Grupo<select name="grupo"><option value="0">—</option><?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?></select></label>
        <label>Concepto<input type="text" name="concepto" maxlength="80" value="Pensión" required></label>
        <label>Mes<select name="mes"><option value="0">Sin mes</option><?php foreach (Informes::MESES as $n => $t): ?><option value="<?= $n ?>" <?= $n === (int) date('n') ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
        <label>Valor<input type="text" inputmode="decimal" name="valor" required></label><label>Vence<input type="date" name="vence"></label>
        <div class="inf-acciones"><button type="submit">Crear cargos</button></div></form>
    <p class="inf-nota">Para la «Pensión Mensual» usa el concepto «Pensión» y el mes correspondiente.</p></section>

<form method="GET" class="inf-filtros"><label class="inf-ancho">Estudiante<select name="estudiante" onchange="this.form.submit()"><option value="0">— elige para ver su cuenta —</option>
    <?php foreach ($estudiantes as $es): ?><option value="<?= (int) $es['id'] ?>" <?= (int) $es['id'] === $sel ? 'selected' : '' ?>><?= $e($es['nombre']) ?> · <?= $e(trim($es['grado'] . ' ' . $es['grupo'])) ?></option><?php endforeach; ?></select></label>
    <noscript><div class="inf-acciones"><button type="submit">Abrir</button></div></noscript></form>
<?php if ($sel > 0): ?>
<div class="inf-scroll"><table class="inf-tabla"><thead><tr><th>Concepto</th><th>Mes</th><th>Vence</th><th>Valor</th><th>Pagado</th><th>Saldo</th><th>Registrar pago</th></tr></thead><tbody>
    <?php foreach ($cargos as $c): $saldo = (float) $c['valor'] - (float) $c['pagado']; ?>
        <tr><td class="a-i"><?= $e($c['concepto']) ?></td><td class="a-c"><?= $c['mes'] ? $e(Informes::MESES[(int) $c['mes']]) : '' ?></td><td class="a-c"><?= $e(Informes::fecha((string) $c['vence'])) ?></td>
            <td class="a-d"><?= $e(Informes::moneda((float) $c['valor'])) ?></td><td class="a-d"><?= $e(Informes::moneda((float) $c['pagado'])) ?></td><td class="a-d"><?= $e(Informes::moneda($saldo)) ?></td>
            <td><?php if ($saldo > 0.009): ?><form method="POST" style="display:flex;gap:.3rem;flex-wrap:wrap"><?php campoCSRF(); ?><input type="hidden" name="accion" value="pago"><input type="hidden" name="cargo" value="<?= (int) $c['id'] ?>">
                <input type="text" inputmode="decimal" name="valor" size="9" placeholder="valor" required><input type="text" name="recibo" size="8" maxlength="30" placeholder="recibo"><button type="submit">Pagar</button></form><?php else: ?>Pagado<?php endif; ?></td></tr>
    <?php endforeach; ?><?php if (!$cargos): ?><tr><td colspan="7" class="inf-vacio">Sin cargos este año.</td></tr><?php endif; ?></tbody></table></div>
<section class="inf-panel"><h2>Cargo individual</h2>
    <form method="POST" class="inf-filtros"><?php campoCSRF(); ?><input type="hidden" name="accion" value="cargo"><input type="hidden" name="estudiante" value="<?= $sel ?>">
        <label>Concepto<input type="text" name="concepto" maxlength="80" required></label>
        <label>Mes<select name="mes"><option value="0">Sin mes</option><?php foreach (Informes::MESES as $n => $t): ?><option value="<?= $n ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
        <label>Valor<input type="text" inputmode="decimal" name="valor" required></label><label>Vence<input type="date" name="vence"></label>
        <div class="inf-acciones"><button type="submit">Crear cargo</button></div></form></section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
