<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Certificados.php';

// Emisión y control de certificados verificables con QR (constancias, estudio, calificaciones y horas formativas).
requiereRol('secretaria_academica', 'administrador', 'rector');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();

$flash = $_SESSION['flash_certificados'] ?? null;
unset($_SESSION['flash_certificados']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => '', 'codigo' => ''];
    if ($accion === 'emitir') {
        $r = Certificados::emitir($pdo, $colegioId, (string) ($_POST['tipo'] ?? ''), (int) ($_POST['estudiante_id'] ?? 0), (int) ($_POST['anio_id'] ?? 0), $usuarioId);
        if ($r['ok']) {
            $f['exito'] = 'Certificado emitido.';
            $f['codigo'] = $r['codigo'];
            auditarAccion($pdo, 'emitir_certificado', 'certificados', 0, [], ['tipo' => (string) ($_POST['tipo'] ?? ''), 'estudiante_id' => (int) ($_POST['estudiante_id'] ?? 0), 'codigo' => $r['codigo']]);
        } else {
            $f['error'] = $r['error'];
        }
    } elseif ($accion === 'anular') {
        $err = Certificados::anular($pdo, (int) ($_POST['id'] ?? 0), $colegioId, (string) ($_POST['motivo'] ?? ''), $usuarioId);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Certificado anulado: su página de verificación ya muestra que no es válido.' : $err;
        if ($err === '') {
            auditarAccion($pdo, 'anular_certificado', 'certificados', (int) ($_POST['id'] ?? 0), [], []);
        }
    }
    $_SESSION['flash_certificados'] = $f;
    header('Location: certificados.php');
    exit;
}

$ins = Certificados::institucion($pdo, $colegioId);
$faltan = Certificados::faltantes($ins);
$anios = Academico::anios($pdo, $colegioId);
$actual = Academico::anioActual($pdo, $colegioId);
$estudiantes = Expediente::estudiantes($pdo, 'secretaria_academica', $usuarioId, $colegioId, 0, '');
$filtroTipo = (string) ($_GET['tipo'] ?? '');
$filtroEstado = (string) ($_GET['estado'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
try {
    $lista = Certificados::listar($pdo, $colegioId, $filtroTipo, $filtroEstado, $q);
} catch (PDOException $ex) {
    $lista = [];
    $sinMigrar = true;
}
$e = static fn ($v): string => htmlspecialchars((string) $v);
$puedeConfigurar = in_array($rol, ['administrador', 'rector'], true);

$tituloPagina = 'Certificados';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Certificados</h1>
<p class="texto-suave">Cada certificado lleva un código QR. Quien lo escanee llega a una página pública que confirma si es válido y permite descargarlo en PDF.</p>

<?php if (!empty($sinMigrar)): ?><p class="mensaje mensaje-aviso">Falta aplicar <code>sql/migraciones/037_certificados.sql</code>.</p><?php endif; ?>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?>
    <p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?>
        <?php if ($flash['codigo'] !== ''): ?>
            Código <strong><?= $e(Certificados::formatearCodigo($flash['codigo'])) ?></strong> ·
            <a href="<?= $e(BASE_URL) ?>/certificado_pdf.php?c=<?= $e($flash['codigo']) ?>&amp;ver=1" target="_blank" rel="noopener">Abrir PDF</a> ·
            <a href="<?= $e(BASE_URL) ?>/verificar.php?c=<?= $e($flash['codigo']) ?>" target="_blank" rel="noopener">Ver página de verificación</a>
        <?php endif; ?></p>
<?php endif; ?>

<?php if ($faltan): ?>
    <p class="mensaje mensaje-aviso">Antes de emitir configura la institución: falta <?= $e(implode(', ', $faltan)) ?>.
        <?php if ($puedeConfigurar): ?><a href="<?= $e(BASE_URL) ?>/administrador/institucion.php">Configurarla ahora</a>.<?php else: ?>Pídeselo al administrador o al rector.<?php endif; ?></p>
<?php elseif (trim((string) ($ins['url_publica'] ?? '')) === ''): ?>
    <p class="mensaje mensaje-aviso">No hay una «dirección pública del sitio» configurada: el QR usará la dirección con la que estás entrando ahora (<?= $e(Certificados::urlBase($ins)) ?>). Si estás en pruebas locales, quien escanee el QR no podrá abrirla.
        <?php if ($puedeConfigurar): ?><a href="<?= $e(BASE_URL) ?>/administrador/institucion.php">Configurarla</a>.<?php endif; ?></p>
<?php endif; ?>

<section class="panel-seccion">
    <h2>Emitir un certificado</h2>
    <form action="certificados.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="emitir">
        <div class="insc-grid">
            <div><label>Tipo
                <select name="tipo" required><?php foreach (Certificados::TIPOS as $k => $t): ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label></div>
            <div><label>Año lectivo
                <select name="anio_id" required>
                    <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $actual && (int) $actual['id'] === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?><?= (int) $a['actual'] === 1 ? ' (actual)' : '' ?></option><?php endforeach; ?>
                </select></label></div>
            <div class="ancho"><label>Estudiante
                <select name="estudiante_id" required><option value="">Elige…</option>
                    <?php foreach ($estudiantes as $s): ?><option value="<?= (int) $s['id'] ?>"><?= $e($s['nombre']) ?> — <?= $s['grupo'] ? $e($s['grado'] . ' ' . $s['grupo']) : 'sin grupo' ?></option><?php endforeach; ?>
                </select></label></div>
        </div>
        <button type="submit" <?= $faltan ? 'disabled' : '' ?>>Emitir certificado</button>
    </form>
    <p class="texto-suave">Para <strong>calificaciones</strong> el estudiante debe tener notas en el año; para <strong>horas</strong>, la hoja de vida con sus horas registradas; todos exigen el documento del estudiante en su hoja de vida.</p>
</section>

<form action="certificados.php" method="GET" class="acad-filtros">
    <label>Tipo <select name="tipo" onchange="this.form.submit()"><option value="">Todos</option><?php foreach (Certificados::TIPOS as $k => $t): ?><option value="<?= $e($k) ?>" <?= $filtroTipo === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
    <label>Estado <select name="estado" onchange="this.form.submit()"><option value="">Todos</option><option value="vigente" <?= $filtroEstado === 'vigente' ? 'selected' : '' ?>>Vigentes</option><option value="anulado" <?= $filtroEstado === 'anulado' ? 'selected' : '' ?>>Anulados</option></select></label>
    <label>Buscar <input type="search" name="q" value="<?= $e($q) ?>" placeholder="Estudiante o código"></label>
    <button type="submit">Filtrar</button>
</form>

<?php $qsFiltros = http_build_query(['tipo' => $filtroTipo, 'estado' => $filtroEstado, 'q' => $q]); ?>
<form id="form-lote" action="certificados_pdf.php" method="POST" class="acad-acciones" style="margin:12px 0">
    <?php campoCSRF(); ?>
    <input type="hidden" name="modo" value="lote"><input type="hidden" name="tipo" value="<?= $e($filtroTipo) ?>"><input type="hidden" name="q" value="<?= $e($q) ?>">
    <button type="submit" class="boton-secundario">PDF de los marcados</button>
    <button type="submit" name="todos" value="1" class="boton-secundario">PDF de todos los vigentes de esta lista</button>
    <label><input type="checkbox" name="qr" value="0"> Sin código QR</label>
    <a class="boton-secundario" href="certificados_pdf.php?modo=registro&amp;<?= $e($qsFiltros) ?>">Registro en PDF</a>
    <a class="boton-secundario" href="certificados_pdf.php?modo=registro&amp;qr=1&amp;<?= $e($qsFiltros) ?>">Registro en PDF con QR</a>
</form>

<?php if (!$lista): ?><div class="estado-vacio"><p>No hay certificados emitidos con esos filtros.</p></div><?php else: ?>
<div class="acad-lista">
    <?php foreach ($lista as $c): $anulado = $c['estado'] === 'anulado'; $vencido = !$anulado && $c['vence_en'] !== null && $c['vence_en'] < date('Y-m-d'); ?>
        <div class="acad-fila">
            <div class="acad-fila-info">
                <?php if (!$anulado && !$vencido): ?><label style="float:right"><input type="checkbox" name="codigos[]" value="<?= $e($c['codigo']) ?>" form="form-lote" aria-label="Marcar para el PDF en lote"> Marcar</label><?php endif; ?>
                <strong><?= $e($c['estudiante'] ?: 'Estudiante eliminado') ?></strong>
                <span class="estado-pill <?= $anulado ? 'estado-rechazada' : ($vencido ? 'estado-pendiente' : 'estado-activa') ?>"><?= $anulado ? 'Anulado' : ($vencido ? 'Vencido' : 'Vigente') ?></span>
                <small><?= $e(Certificados::TIPOS[$c['tipo']] ?? $c['tipo']) ?> · <?= (int) $c['anio'] ?> · <?= $e(Certificados::formatearCodigo((string) $c['codigo'])) ?></small>
                <small>Emitido el <?= $e(substr((string) $c['emitido_en'], 0, 10)) ?> por <?= $e($c['emisor'] ?: '—') ?> · <?= (int) $c['descargas'] ?> descarga(s) · <?= (int) $c['verificaciones'] ?> verificación(es)<?= $anulado && $c['anulado_motivo'] ? ' · motivo: ' . $e($c['anulado_motivo']) : '' ?></small>
            </div>
            <div class="acad-acciones">
                <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/verificar.php?c=<?= $e($c['codigo']) ?>" target="_blank" rel="noopener">Verificación</a>
                <?php if (!$anulado && !$vencido): ?><a class="boton-secundario" href="<?= $e(BASE_URL) ?>/certificado_pdf.php?c=<?= $e($c['codigo']) ?>&amp;ver=1" target="_blank" rel="noopener">PDF</a>
                    <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/certificado_pdf.php?c=<?= $e($c['codigo']) ?>&amp;qr=0&amp;ver=1" target="_blank" rel="noopener">PDF sin QR</a>
                    <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/certificado_pdf.php?c=<?= $e($c['codigo']) ?>&amp;hoja=1&amp;ver=1" target="_blank" rel="noopener">Hoja de verificación</a><?php endif; ?>
                <?php if (!$anulado): ?>
                <form action="certificados.php" method="POST" onsubmit="return confirm('¿Anular este certificado? Dejará de ser válido para quien lo verifique.');">
                    <?php campoCSRF(); ?><input type="hidden" name="accion" value="anular"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <input type="text" name="motivo" maxlength="255" required placeholder="Motivo de la anulación" aria-label="Motivo de la anulación" style="min-width:12em">
                    <button type="submit" class="boton-peligro">Anular</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
