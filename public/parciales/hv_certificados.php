<?php
// Pestaña «Certificados»: los certificados emitidos a este estudiante (se abren en su página de verificación).
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
?>
<h2 class="hv-titulo">Certificados emitidos</h2>
<?php if (!$certificados): ?>
    <p class="texto-suave">Todavía no se ha emitido ningún certificado a este estudiante.</p>
<?php else: ?>
    <p class="texto-suave">Cada certificado se abre en su página de verificación, desde donde se puede descargar en PDF.</p>
    <?php foreach ($certificados as $ce): ?>
        <div class="hv-certificado">
            <div><strong><?= $e(Certificados::TIPOS[$ce['tipo']] ?? $ce['tipo']) ?></strong><br><small class="texto-suave">Emitido el <?= $e(substr((string) $ce['emitido_en'], 0, 10)) ?><?= $ce['vence_en'] ? ' · vigente hasta ' . $e($ce['vence_en']) : '' ?></small></div>
            <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/verificar.php?c=<?= $e($ce['codigo']) ?>" target="_blank" rel="noopener">Ver y descargar</a>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
