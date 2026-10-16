<?php
// Pestaña «Observaciones»: temporales (no se conservan en la hoja de vida) y para certificados.
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
$obsg = HojaVida::observacionesGenerales($pdo, $estId);
$bloques = [
    'temporales' => ['Observaciones temporales', 'Estas no son observaciones que se conservan en la hoja de vida.', $perm['ver_obs_temporales'], $perm['escribir_obs_temporales']],
    'certificados' => ['Observaciones para certificados', 'Texto que el colegio incluye al expedir certificados de este estudiante.', true, $perm['escribir_obs_certificados']],
];
?>
<h2 class="hv-titulo">Observaciones</h2>
<?php foreach ($bloques as $campo => [$titulo, $ayuda, $ve, $escribe]): if (!$ve) { continue; } ?>
<section class="hv-seccion hv-bloque">
    <h3><?= $e($titulo) ?></h3>
    <p class="texto-suave"><?= $e($ayuda) ?></p>
    <?php if ($escribe): ?>
    <form action="estudiante_ficha.php" method="POST" novalidate>
        <?php $ocultos('obsg_guardar', ['campo' => $campo]); ?>
        <label class="sr-only" for="obs-<?= $e($campo) ?>"><?= $e($titulo) ?></label>
        <textarea id="obs-<?= $e($campo) ?>" name="texto" rows="4" maxlength="5000"><?= $e($obsg[$campo]) ?></textarea>
        <p class="hv-acciones"><button type="submit">Guardar</button></p>
    </form>
    <?php else: ?>
        <p style="white-space:pre-line"><?= $e($obsg[$campo]) ?: '—' ?></p>
    <?php endif; ?>
</section>
<?php endforeach; ?>
