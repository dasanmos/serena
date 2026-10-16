<?php
declare(strict_types=1);
// Variables esperadas, definidas por quien incluye este parcial antes del require:
// $textoAnalisis (string, obligatorio) y opcionalmente $tituloAnalisis (string).
?>
<?php if (!empty($textoAnalisis)): ?>
<section class="tarjeta-analisis">
    <h2><?= htmlspecialchars($tituloAnalisis ?? 'Análisis de datos') ?></h2>
    <?php /* El personal lee informes de varias líneas: sin efecto de escritura (a 45 ms por letra tardaba más de 10 s). */ ?>
    <p<?= ($_SESSION['rol'] ?? '') === 'estudiante' ? ' class="efecto-escritura"' : '' ?>><?= htmlspecialchars($textoAnalisis) ?></p>
</section>
<?php endif; ?>
