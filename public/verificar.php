<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/modelos/Certificados.php';
require_once __DIR__ . '/../src/helpers/DocumentoPdf.php';

// Verificación PÚBLICA de certificados: a esta página lleva el código QR. Sin sesión.
$codigo = (string) ($_GET['c'] ?? $_POST['c'] ?? '');
$busco = trim($codigo) !== '';
$c = null;
$doc = null; // informe/reporte PDF con QR (no es un certificado)
$estado = null;
$limitado = false;

if ($busco) {
    // Freno por sesión: 40 consultas cada 10 minutos (evita probar códigos al azar)
    $ahora = time();
    $intentos = array_filter($_SESSION['verif_consultas'] ?? [], static fn (int $t): bool => $t > $ahora - 600);
    if (count($intentos) >= 40) {
        $limitado = true;
    } else {
        $intentos[] = $ahora;
        $_SESSION['verif_consultas'] = $intentos;
        try {
            $c = Certificados::buscar($pdo, $codigo);
        } catch (PDOException $e) {
            $c = null; // la migración 037 aún no se aplicó
        }
        if ($c !== null) {
            $estado = Certificados::estado($c);
            Certificados::registrarVerificacion($pdo, (int) $c['id']);
        }
        if ($c === null) {
            $doc = DocumentoPdf::buscar($pdo, $codigo);
            if ($doc !== null) {
                DocumentoPdf::registrarVerificacion($pdo, (int) $doc['id']);
            }
        }
    }
}
$e = static fn ($v): string => htmlspecialchars((string) $v);
$i = $c['d']['institucion'] ?? [];
$logo = !empty($i['logo_b64']) ? 'data:image/jpeg;base64,' . $i['logo_b64'] : '';
$tipoTxt = $c ? (Certificados::TIPOS[$c['tipo']] ?? 'Certificado') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Serena — Verificación de certificados</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/academico.css?v=<?= assetVersion('/assets/css/academico.css') ?>">
    <script>window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;
        (function () { try { var t = localStorage.getItem('serena_tema'); if (t === 'oscuro' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) { document.documentElement.setAttribute('data-tema', 'oscuro'); } } catch (e) {} })();</script>
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body>
<main class="verif-pagina">
    <article class="verif-tarjeta">

    <?php if ($limitado): ?>
        <h1>Verificación de certificados</h1>
        <p class="mensaje mensaje-error">Hiciste muchas consultas seguidas. Espera unos minutos e inténtalo de nuevo.</p>

    <?php elseif (!$busco): ?>
        <h1>Verificación de certificados</h1>
        <p class="texto-suave">Escanea el código QR del certificado o escribe aquí el código de verificación que aparece en el documento.</p>
        <form action="verificar.php" method="GET" novalidate>
            <label for="c">Código de verificación</label>
            <input type="text" id="c" name="c" required maxlength="20" autocomplete="off" placeholder="ABCD-EFGH-IJKL" style="text-transform:uppercase">
            <button type="submit">Verificar</button>
        </form>

    <?php elseif ($doc !== null): ?>
        <h1>Verificación de documentos</h1>
        <?php if ($doc['integro'] && !(int) $doc['anulado']): ?>
            <span class="verif-sello verif-ok">✔ DOCUMENTO AUTÉNTICO</span>
            <p>Este informe fue generado desde Serena. Por privacidad, aquí <strong>no se muestra su contenido</strong>: compárelo con la copia que conserva la institución.</p>
        <?php elseif ((int) $doc['anulado']): ?>
            <span class="verif-sello verif-mal">✖ DOCUMENTO ANULADO</span>
            <p>La institución dejó sin efecto este documento.</p>
        <?php else: ?>
            <span class="verif-sello verif-mal">✖ NO SE PUDO VERIFICAR</span>
            <p>El registro de este documento no superó la comprobación de integridad. <strong>No lo acepte</strong> y comuníquese con la institución.</p>
        <?php endif; ?>
        <dl class="verif-datos">
            <div><dt>Documento</dt><dd><?= $e($doc['tipo']) ?></dd></div>
            <div><dt>Título</dt><dd><?= $e($doc['titulo']) ?></dd></div>
            <div><dt>Institución</dt><dd><?= $e($doc['colegio_nombre'] ?: 'Plataforma Serena') ?></dd></div>
            <div><dt>Generado el</dt><dd><?= $e(CertificadoPDF::fechaLarga(substr((string) $doc['creado_en'], 0, 10))) ?> a las <?= $e(substr((string) $doc['creado_en'], 11, 5)) ?></dd></div>
            <div><dt>Código de verificación</dt><dd class="verif-codigo"><?= $e(Certificados::formatearCodigo((string) $doc['codigo'])) ?></dd></div>
        </dl>

    <?php elseif ($c === null): ?>
        <h1>Verificación de certificados</h1>
        <span class="verif-sello verif-mal">✖ NO EXISTE UN DOCUMENTO CON ESE CÓDIGO</span>
        <p>Revisa que el código esté bien escrito. Si lo escaneaste desde un documento impreso y sigue sin aparecer, <strong>no lo acepte como válido</strong> y comuníquese con la institución que lo emitió.</p>
        <form action="verificar.php" method="GET" novalidate>
            <label for="c">Probar con otro código</label>
            <input type="text" id="c" name="c" required maxlength="20" autocomplete="off" style="text-transform:uppercase">
            <button type="submit">Verificar</button>
        </form>

    <?php else: ?>
        <div class="verif-inst">
            <?php if ($logo !== ''): ?><img src="<?= $e($logo) ?>" alt="Logo de la institución"><?php endif; ?>
            <div>
                <h1><?= $e($i['nombre'] ?? 'Institución educativa') ?></h1>
                <small><?= !empty($i['dane']) ? 'Código DANE ' . $e($i['dane']) : '' ?><?= !empty($i['municipio']) ? ' · ' . $e($i['municipio']) : '' ?></small>
            </div>
        </div>

        <?php if ($estado === 'valido'): ?>
            <span class="verif-sello verif-ok">✔ CERTIFICADO VÁLIDO</span>
            <p>Este documento fue emitido por la institución y su contenido coincide con el registrado en Serena.</p>
        <?php elseif ($estado === 'vencido'): ?>
            <span class="verif-sello verif-aviso">⚠ CERTIFICADO VENCIDO</span>
            <p>Es auténtico, pero su vigencia terminó el <?= $e(CertificadoPDF::fechaLarga((string) $c['vence_en'])) ?>. Solicite uno nuevo a la institución.</p>
        <?php elseif ($estado === 'anulado'): ?>
            <span class="verif-sello verif-mal">✖ CERTIFICADO ANULADO</span>
            <p>La institución anuló este certificado el <?= $e(CertificadoPDF::fechaLarga(substr((string) $c['anulado_en'], 0, 10))) ?>. <strong>No tiene validez.</strong></p>
        <?php else: ?>
            <span class="verif-sello verif-mal">✖ NO SE PUDO VERIFICAR</span>
            <p>El registro de este certificado no superó la comprobación de integridad. <strong>No lo acepte</strong> y comuníquese con la institución.</p>
        <?php endif; ?>

        <dl class="verif-datos">
            <div><dt>Documento</dt><dd><?= $e($tipoTxt) ?></dd></div>
            <?php if (in_array($estado, ['valido', 'vencido'], true)): foreach (($c['d']['resumen'] ?? []) as [$k, $v]): ?>
                <div><dt><?= $e($k) ?></dt><dd><?= $e($v) ?></dd></div>
            <?php endforeach; endif; ?>
            <div><dt>Fecha de emisión</dt><dd><?= $e(CertificadoPDF::fechaLarga(substr((string) $c['emitido_en'], 0, 10))) ?></dd></div>
            <?php if ($c['vence_en'] !== null): ?><div><dt>Vigente hasta</dt><dd><?= $e(CertificadoPDF::fechaLarga((string) $c['vence_en'])) ?></dd></div><?php endif; ?>
            <div><dt>Folio</dt><dd>N.º <?= $e(str_pad((string) $c['id'], 6, '0', STR_PAD_LEFT)) ?></dd></div>
            <div><dt>Código de verificación</dt><dd class="verif-codigo"><?= $e(Certificados::formatearCodigo((string) $c['codigo'])) ?></dd></div>
        </dl>

        <?php if ($estado === 'valido'): ?>
            <p class="verif-descarga"><a class="boton-acceso" href="certificado_pdf.php?c=<?= $e($c['codigo']) ?>">⬇ Descargar certificado en PDF</a></p>
            <p class="texto-suave" style="text-align:center"><a href="certificado_pdf.php?c=<?= $e($c['codigo']) ?>&amp;qr=0">Versión sin QR</a> · <a href="certificado_pdf.php?c=<?= $e($c['codigo']) ?>&amp;hoja=1">Hoja de verificación (1 página)</a></p>
            <p class="texto-suave" style="text-align:center">El PDF descargado desde aquí es copia fiel del original emitido.</p>
        <?php endif; ?>
    <?php endif; ?>

        <p class="texto-suave" style="text-align:center;margin-top:20px"><a href="verificar.php">Verificar otro código</a> · <a href="index.php">Ir a Serena</a></p>
    </article>
</main>
<script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
