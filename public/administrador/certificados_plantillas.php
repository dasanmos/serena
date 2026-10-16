<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Certificados.php';

// Textos editables de cada certificado. Se pueden usar {{variables}}; las tablas (notas, horas) las arma Serena.
requiereRol('administrador', 'rector');
$colegioId = colegioParaCrear();
$tipo = (string) ($_GET['tipo'] ?? $_POST['tipo'] ?? 'matricula');
if (!isset(Certificados::TIPOS[$tipo])) {
    $tipo = 'matricula';
}

$flash = $_SESSION['flash_plantillas'] ?? null;
unset($_SESSION['flash_plantillas']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    if ((string) ($_POST['accion'] ?? '') === 'restablecer') {
        Certificados::restablecerPlantilla($pdo, $colegioId, $tipo);
        $f['exito'] = 'Texto restablecido al original de Serena.';
    } else {
        $err = Certificados::guardarPlantilla($pdo, $colegioId, $tipo, (string) ($_POST['titulo'] ?? ''), (string) ($_POST['cuerpo'] ?? ''));
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Texto guardado. Se usará en los certificados que emitas desde ahora.' : $err;
        if ($err === '') {
            auditarAccion($pdo, 'editar_plantilla_certificado', 'plantillas_certificado', 0, [], ['tipo' => $tipo]);
        }
    }
    $_SESSION['flash_plantillas'] = $f;
    header('Location: certificados_plantillas.php?tipo=' . urlencode($tipo));
    exit;
}

$pl = Certificados::plantilla($pdo, $colegioId, $tipo);
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Textos de los certificados';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Textos de los certificados</h1>
<p class="texto-suave">Edita el título y el texto. Donde quieras que Serena ponga un dato escribe su {{variable}}. Los certificados ya emitidos no cambian.</p>
<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<form action="certificados_plantillas.php" method="GET" class="acad-filtros">
    <label>Certificado
        <select name="tipo" onchange="this.form.submit()">
            <?php foreach (Certificados::TIPOS as $k => $t): ?><option value="<?= $e($k) ?>" <?= $tipo === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?>
        </select>
    </label>
</form>

<section class="panel-seccion">
    <?php if ($pl['personalizada']): ?><span class="estado-pill estado-en_revision">Texto personalizado</span><?php else: ?><span class="estado-pill">Texto original de Serena</span><?php endif; ?>
    <form action="certificados_plantillas.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="tipo" value="<?= $e($tipo) ?>">
        <label>Título <input type="text" name="titulo" maxlength="120" required value="<?= $e($pl['titulo']) ?>"></label>
        <label>Texto <textarea name="cuerpo" rows="12" maxlength="4000" required><?= $e($pl['cuerpo']) ?></textarea></label>
        <div class="acad-acciones">
            <button type="submit" name="accion" value="guardar">Guardar texto</button>
            <?php if ($pl['personalizada']): ?><button type="submit" name="accion" value="restablecer" class="boton-secundario" onclick="return confirm('¿Volver al texto original?');">Restablecer el original</button><?php endif; ?>
        </div>
    </form>
</section>

<section class="panel-seccion">
    <h2>Variables disponibles</h2>
    <dl class="acad-detalle">
        <?php foreach (Certificados::VARIABLES as $k => $d): ?><div><dt>{{<?= $e($k) ?>}}</dt><dd><?= $e($d) ?></dd></div><?php endforeach; ?>
    </dl>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
