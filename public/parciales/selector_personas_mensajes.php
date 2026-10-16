<?php
declare(strict_types=1);
// Se incluye desde cada mensajes.php de personal, con $personalDisponible ya cargado
// por la página que lo incluye (Mensaje::personalDisponibleParaChat).
?>
<section class="panel-seccion selector-personas">
  <h2>Iniciar conversación nueva</h2>
  <?php if (empty($personalDisponible)): ?>
    <p class="texto-ayuda-vencidas">Ya tienes conversación abierta con todo el personal disponible.</p>
  <?php else: ?>
  <div class="lista-selector-personas">
    <?php foreach ($personalDisponible as $p): ?>
      <form action="mensajes.php" method="POST" class="item-selector-persona">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="iniciar_chat">
        <input type="hidden" name="otro_usuario_id" value="<?= $p['id'] ?>">
        <button type="submit" data-sonido="clic">
          <span class="nombre-persona"><?= htmlspecialchars($p['nombre']) ?></span>
          <span class="pill pill-neutro"><?= ucfirst($p['rol']) ?></span>
        </button>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
