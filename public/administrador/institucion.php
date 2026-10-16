<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Certificados.php';

// Configurador de la institución: nombre oficial, códigos (DANE, NIT, ICFES), resoluciones, firmantes, logo y firmas.
// Todo esto se imprime en los certificados y en su página de verificación.
requiereRol('administrador', 'rector');
$colegioId = colegioParaCrear();
$usuarioId = (int) $_SESSION['usuario_id'];

// Vista previa: un PDF de ejemplo con lo que hay guardado (sin validez)
if ((string) ($_GET['vista_previa'] ?? '') === '1') {
    $ins = Certificados::institucion($pdo, $colegioId);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="vista-previa-certificado.pdf"');
    header('X-Content-Type-Options: nosniff');
    echo Certificados::pdfPrueba($ins);
    exit;
}

$flash = $_SESSION['flash_institucion'] ?? null;
unset($_SESSION['flash_institucion']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    $err = Certificados::guardarInstitucion($pdo, $colegioId, $_POST, $usuarioId);
    if ($err !== '') {
        $f['error'] = $err;
    } else {
        $avisos = [];
        foreach (['logo' => 600, 'firma_rector' => 500, 'firma_secretaria' => 500] as $campo => $maxPx) {
            if (isset($_POST['quitar_' . $campo])) {
                Certificados::guardarImagen($pdo, $colegioId, $campo, null);
                continue;
            }
            try {
                Certificados::guardarImagen($pdo, $colegioId, $campo, Certificados::procesarImagen($_FILES[$campo] ?? [], $maxPx));
            } catch (RuntimeException $ex) {
                if ($ex->getMessage() !== '__vacio__') {
                    $avisos[] = ucfirst(str_replace('_', ' ', $campo)) . ': ' . $ex->getMessage();
                }
            }
        }
        $f['exito'] = 'Configuración de la institución guardada.' . ($avisos ? ' Pero ' . implode(' ', $avisos) : '');
        auditarAccion($pdo, 'configurar_institucion', 'institucion_perfil', $colegioId, [], ['usuario' => $usuarioId]);
    }
    $_SESSION['flash_institucion'] = $f;
    header('Location: institucion.php');
    exit;
}

$ins = Certificados::institucion($pdo, $colegioId);
$faltan = Certificados::faltantes($ins);
$e = static fn ($v): string => htmlspecialchars((string) $v);
$campo = static fn (string $k): string => htmlspecialchars((string) ($ins[$k] ?? ''));
$imgUri = static fn (string $b64): string => $b64 !== '' ? 'data:image/jpeg;base64,' . $b64 : '';

$tituloPagina = 'Datos de la institución';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Datos de la institución</h1>
<p class="texto-suave">Lo que escribas aquí se imprime en el encabezado y las firmas de los certificados, y aparece en su página pública de verificación. Cada certificado emitido guarda una copia: si cambias algo, los ya emitidos no se modifican.</p>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if ($faltan): ?><p class="mensaje mensaje-aviso">Para poder emitir certificados falta: <?= $e(implode(', ', $faltan)) ?>.</p>
<?php else: ?><p class="mensaje mensaje-exito">La institución está lista para emitir certificados.</p><?php endif; ?>

<form action="institucion.php" method="POST" enctype="multipart/form-data" novalidate>
    <?php campoCSRF(); ?>

    <section class="panel-seccion">
        <h2>1. Identificación</h2>
        <div class="insc-grid">
            <div class="ancho"><label>Nombre oficial de la institución <input type="text" name="nombre_oficial" maxlength="200" required value="<?= $campo('nombre_oficial') ?>"></label></div>
            <div><label>Naturaleza
                <select name="naturaleza"><option value="">—</option><?php foreach (Certificados::NATURALEZAS as $n): ?><option <?= ($ins['naturaleza'] ?? '') === $n ? 'selected' : '' ?>><?= $e($n) ?></option><?php endforeach; ?></select></label></div>
            <div><label>Carácter <input type="text" name="caracter" maxlength="80" placeholder="Académico, técnico..." value="<?= $campo('caracter') ?>"></label></div>
            <div class="ancho"><label>Niveles autorizados <input type="text" name="niveles" maxlength="200" placeholder="Preescolar, básica primaria, básica secundaria y media" value="<?= $campo('niveles') ?>"></label></div>
            <div><label>Jornadas <input type="text" name="jornadas" maxlength="100" placeholder="Mañana y tarde" value="<?= $campo('jornadas') ?>"></label></div>
            <div><label>Calendario <input type="text" name="calendario" maxlength="10" placeholder="A" value="<?= $campo('calendario') ?>"></label></div>
        </div>
    </section>

    <section class="panel-seccion">
        <h2>2. Códigos</h2>
        <div class="insc-grid">
            <div><label>Código DANE (10 a 12 dígitos) <input type="text" name="codigo_dane" maxlength="12" inputmode="numeric" value="<?= $campo('codigo_dane') ?>"></label></div>
            <div><label>NIT <input type="text" name="nit" maxlength="20" placeholder="890.900.123-4" value="<?= $campo('nit') ?>"></label></div>
            <div><label>Código ICFES (opcional) <input type="text" name="codigo_icfes" maxlength="12" value="<?= $campo('codigo_icfes') ?>"></label></div>
        </div>
    </section>

    <section class="panel-seccion">
        <h2>3. Resoluciones</h2>
        <p class="texto-suave">Se imprime como: «Reconocimiento oficial: Resolución N.º … del … , entidad.» seguido del texto adicional.</p>
        <div class="insc-grid">
            <div><label>Resolución de reconocimiento (número) <input type="text" name="resolucion_numero" maxlength="60" placeholder="16322" value="<?= $campo('resolucion_numero') ?>"></label></div>
            <div><label>Fecha de la resolución <input type="date" name="resolucion_fecha" value="<?= $campo('resolucion_fecha') ?>"></label></div>
            <div class="ancho"><label>Entidad que la expidió <input type="text" name="resolucion_entidad" maxlength="150" placeholder="Secretaría de Educación de Itagüí" value="<?= $campo('resolucion_entidad') ?>"></label></div>
            <div class="ancho"><label>Otras resoluciones o licencias (opcional) <textarea name="resoluciones_adicionales" rows="3" maxlength="600" placeholder="Resolución 9876 de 2015 (ampliación de niveles)..."><?= $campo('resoluciones_adicionales') ?></textarea></label></div>
        </div>
    </section>

    <section class="panel-seccion">
        <h2>4. Ubicación y contacto</h2>
        <div class="insc-grid">
            <div class="ancho"><label>Dirección <input type="text" name="direccion" maxlength="200" value="<?= $campo('direccion') ?>"></label></div>
            <div><label>Municipio <input type="text" name="municipio" maxlength="80" value="<?= $campo('municipio') ?>"></label></div>
            <div><label>Departamento <input type="text" name="departamento" maxlength="60" value="<?= $campo('departamento') ?>"></label></div>
            <div><label>Teléfono <input type="text" name="telefono" maxlength="40" value="<?= $campo('telefono') ?>"></label></div>
            <div><label>Correo institucional <input type="email" name="correo" maxlength="150" value="<?= $campo('correo') ?>"></label></div>
            <div><label>Sitio web (opcional) <input type="text" name="sitio_web" maxlength="150" value="<?= $campo('sitio_web') ?>"></label></div>
            <div><label>Ciudad de expedición <input type="text" name="ciudad_expedicion" maxlength="80" value="<?= $campo('ciudad_expedicion') ?>"></label></div>
        </div>
    </section>

    <section class="panel-seccion">
        <h2>5. Firmantes</h2>
        <div class="insc-grid">
            <div><label>Nombre del rector(a) <input type="text" name="rector_nombre" maxlength="120" value="<?= $campo('rector_nombre') ?>"></label></div>
            <div><label>Cargo <input type="text" name="rector_cargo" maxlength="60" value="<?= $campo('rector_cargo') ?>"></label></div>
            <div><label>Segundo firmante (opcional) <input type="text" name="secretaria_nombre" maxlength="120" placeholder="Secretario(a) académico(a)" value="<?= $campo('secretaria_nombre') ?>"></label></div>
            <div><label>Cargo <input type="text" name="secretaria_cargo" maxlength="60" placeholder="Secretario(a) académico(a)" value="<?= $campo('secretaria_cargo') ?>"></label></div>
        </div>
    </section>

    <section class="panel-seccion">
        <h2>6. Logo y firmas</h2>
        <p class="texto-suave">Sube imágenes JPG, PNG o WEBP (hasta 3 MB). Se reducen y convierten automáticamente. La firma se ve mejor sobre fondo blanco.</p>
        <div class="insc-grid">
            <?php foreach (['logo' => 'Logo de la institución', 'firma_rector' => 'Firma del rector(a)', 'firma_secretaria' => 'Firma del segundo firmante'] as $k => $titulo): $uri = $imgUri((string) ($ins[$k . '_b64'] ?? '')); ?>
                <div>
                    <label><?= $e($titulo) ?> <input type="file" name="<?= $e($k) ?>" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                    <?php if ($uri !== ''): ?>
                        <img src="<?= $e($uri) ?>" alt="<?= $e($titulo) ?> actual" style="max-width:160px;max-height:90px;border:1px solid var(--color-borde);border-radius:8px;background:#fff;padding:4px">
                        <label><input type="checkbox" name="quitar_<?= $e($k) ?>"> Quitar esta imagen</label>
                    <?php else: ?><span class="texto-suave">Sin imagen.</span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel-seccion">
        <h2>7. Apariencia y validez</h2>
        <div class="insc-grid">
            <div><label>Color de los títulos <input type="color" name="color_primario" value="<?= $campo('color_primario') ?>" style="height:2.6em"></label></div>
            <div><label>Vigencia de los certificados (días; vacío = sin vencimiento) <input type="number" name="dias_vigencia" min="0" max="3650" value="<?= $campo('dias_vigencia') ?>"></label></div>
            <div class="ancho"><label>Dirección pública del sitio (la que se imprime en el QR)
                <input type="text" name="url_publica" maxlength="200" placeholder="https://colegio.edu.co/serena/public" value="<?= $campo('url_publica') ?>"></label>
                <span class="texto-suave">Importante: debe ser la dirección con la que las familias y entidades abren Serena desde internet. Si la dejas vacía se usa la de este momento, que en pruebas locales (localhost) NO sirve para quien escanee el QR.</span></div>
        </div>
    </section>

    <div class="acad-acciones">
        <button type="submit">Guardar configuración</button>
        <a class="boton-secundario" href="institucion.php?vista_previa=1" target="_blank" rel="noopener">Ver certificado de ejemplo (PDF)</a>
        <a class="boton-secundario" href="certificados_plantillas.php">Editar los textos de los certificados</a>
    </div>
</form>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
