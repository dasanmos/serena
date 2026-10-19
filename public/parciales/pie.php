</main>
</div><!-- /.area-principal -->

<script src="<?= BASE_URL ?>/assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/sonidos.js?v=<?= assetVersion('/assets/js/sonidos.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/experiencia.js?v=<?= assetVersion('/assets/js/experiencia.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/menu_barra.js?v=<?= assetVersion('/assets/js/menu_barra.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/requisitosPassword.js?v=<?= assetVersion('/assets/js/requisitosPassword.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/voz.js?v=<?= assetVersion('/assets/js/voz.js') ?>" defer></script>
<?php foreach (($jsExtra ?? []) as $jsPagina): ?>
<script src="<?= BASE_URL . htmlspecialchars($jsPagina) ?>?v=<?= assetVersion($jsPagina) ?>"></script>
<?php endforeach; ?>
</body>
</html>
