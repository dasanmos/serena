<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/helpers/captura.php';
require_once __DIR__ . '/../src/modelos/Informes.php';

// Caracterización poblacional del estudiante (víctima del conflicto, etnia, SISBÉN, estrato). Dato sensible: cada cambio se audita.
requiereRol('secretaria_academica', 'administrador', 'rector', 'coordinador', 'psicologo', 'orientador');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$e = static fn ($v): string => htmlspecialchars((string) $v);
$listo = Informes::tablaExiste($pdo, 'caracterizacion_estudiante');
$url = 'caracterizacion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $listo) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $est = (int) ($_POST['estudiante'] ?? 0);
    if (!Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, $est, $colegioId)) {
        capVolver('flash_caract', $url, '', 'Estudiante no válido.');
    }
    $q = $pdo->prepare('SELECT * FROM caracterizacion_estudiante WHERE estudiante_id = ? AND colegio_id = ?');
    $q->execute([$est, $colegioId]);
    $antes = $q->fetch() ?: [];
    $estrato = (int) ($_POST['estrato'] ?? 0);
    $d = [
        'victima_conflicto' => !empty($_POST['victima']) ? 1 : 0,
        'hecho_victimizante' => capTexto($_POST['hecho'] ?? '', 80),
        'poblacion_etnica' => capTexto($_POST['etnia'] ?? '', 40),
        'sisben' => capTexto($_POST['sisben'] ?? '', 10),
        'estrato' => ($estrato >= 1 && $estrato <= 6) ? $estrato : null,
        'observacion' => capTexto($_POST['observacion'] ?? '', 300),
    ];
    $pdo->prepare('INSERT INTO caracterizacion_estudiante (estudiante_id, colegio_id, victima_conflicto, hecho_victimizante, poblacion_etnica, sisben, estrato, observacion, actualizado_por) VALUES (?,?,?,?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE victima_conflicto = VALUES(victima_conflicto), hecho_victimizante = VALUES(hecho_victimizante), poblacion_etnica = VALUES(poblacion_etnica), sisben = VALUES(sisben), estrato = VALUES(estrato), observacion = VALUES(observacion), actualizado_por = VALUES(actualizado_por)')
        ->execute([$est, $colegioId, $d['victima_conflicto'], $d['hecho_victimizante'], $d['poblacion_etnica'], $d['sisben'], $d['estrato'], $d['observacion'], $usuarioId]);
    auditarAccion($pdo, 'caracterizacion_estudiante', 'caracterizacion_estudiante', $est, $antes ? ['victima_conflicto' => $antes['victima_conflicto'], 'etnia' => $antes['poblacion_etnica']] : [], ['victima_conflicto' => $d['victima_conflicto'], 'etnia' => $d['poblacion_etnica']]);
    capVolver('flash_caract', $url . '?estudiante=' . $est, 'Caracterización guardada.');
}

$flash = capFlash('flash_caract');
$estudiantes = Expediente::estudiantes($pdo, $rol, $usuarioId, $colegioId, 0, '');
$sel = (int) ($_GET['estudiante'] ?? 0);
$c = [];
$selNombre = '';
if ($listo && $sel > 0 && Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, $sel, $colegioId)) {
    $q = $pdo->prepare('SELECT * FROM caracterizacion_estudiante WHERE estudiante_id = ? AND colegio_id = ?');
    $q->execute([$sel, $colegioId]);
    $c = $q->fetch() ?: [];
    foreach ($estudiantes as $es) {
        if ((int) $es['id'] === $sel) {
            $selNombre = (string) $es['nombre'];
        }
    }
} else {
    $sel = 0;
}
$tituloPagina = 'Caracterización';
$cssExtra = ['/assets/css/academico.css', '/assets/css/informes.css'];
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Caracterización poblacional</h1>
<p class="texto-suave">Información sensible (Ley 1581 de 2012): solo para quienes la necesitan. Cada cambio queda en la auditoría. Alimenta el informe «Estudiantes Víctimas del Conflicto».</p>
<?php if (!$listo): ?><p class="mensaje mensaje-aviso">Falta aplicar <code>sql/migraciones/038_informes.sql</code>.</p><?php endif; ?>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if ($listo): ?>
<form method="GET" class="inf-filtros"><label class="inf-ancho">Estudiante<select name="estudiante" onchange="this.form.submit()"><option value="0">— elige —</option>
    <?php foreach ($estudiantes as $es): ?><option value="<?= (int) $es['id'] ?>" <?= (int) $es['id'] === $sel ? 'selected' : '' ?>><?= $e($es['nombre']) ?> · <?= $e(trim($es['grado'] . ' ' . $es['grupo'])) ?></option><?php endforeach; ?></select></label>
    <noscript><div class="inf-acciones"><button type="submit">Abrir</button></div></noscript></form>
<?php if ($sel > 0): ?>
<section class="inf-panel"><h2><?= $e($selNombre) ?></h2>
    <form method="POST" class="inf-filtros"><?php campoCSRF(); ?><input type="hidden" name="estudiante" value="<?= $sel ?>">
        <label class="inf-check"><input type="checkbox" name="victima" value="1" <?= !empty($c['victima_conflicto']) ? 'checked' : '' ?>> Víctima del conflicto armado</label>
        <label>Hecho victimizante<input type="text" name="hecho" maxlength="80" value="<?= $e($c['hecho_victimizante'] ?? '') ?>"></label>
        <label>Población étnica<input type="text" name="etnia" maxlength="40" value="<?= $e($c['poblacion_etnica'] ?? '') ?>" placeholder="Indígena, afro, Rrom, ninguna…"></label>
        <label>SISBÉN<input type="text" name="sisben" maxlength="10" value="<?= $e($c['sisben'] ?? '') ?>" placeholder="A1, B2…"></label>
        <label>Estrato<input type="number" name="estrato" min="1" max="6" value="<?= $e($c['estrato'] ?? '') ?>"></label>
        <label class="inf-ancho">Observación<input type="text" name="observacion" maxlength="300" value="<?= $e($c['observacion'] ?? '') ?>"></label>
        <div class="inf-acciones"><button type="submit">Guardar</button></div>
    </form></section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
