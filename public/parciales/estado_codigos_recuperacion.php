<?php
// Sección "Códigos de recuperación", compartida por los 4 métodos de verificación
// en 2 pasos (TOTP, correo, WhatsApp, rostro): solo hay UN lote de códigos por
// usuario, sin importar cuál método esté activo, así que la vista es la misma
// para los cuatro. Requiere que quien la incluya ya tenga cargadas $estadoCodigos
// (VerificacionDosPasos::estadoCodigos) y $pdo/$uid — la acción del formulario
// (regenerar_codigos) es la misma que usa seguridad.php para los 4 métodos.
if (!isset($estadoCodigos)) { http_response_code(403); exit; }
$ec = $estadoCodigos;
$pocos = $ec['disponibles'] <= 2 || ($ec['hay_lote'] && $ec['dias_restantes'] <= 7);
?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-codigos-estado">
    <h2 id="titulo-codigos-estado">Códigos de recuperación</h2>
    <?php if ($ec['hay_lote']): ?>
        <p class="resumen-codigos">
            <strong><?= $ec['disponibles'] ?> de <?= $ec['total'] ?></strong> disponibles
            <?php if ($ec['vencido']): ?>
                · <span class="texto-alerta">vencieron el <?= fechaCorta($ec['expira_en']) ?></span>
            <?php else: ?>
                · vencen el <?= fechaCorta($ec['expira_en']) ?> (<?= $ec['dias_restantes'] ?> <?= $ec['dias_restantes'] === 1 ? 'día' : 'días' ?>)
            <?php endif; ?>
        </p>
        <div class="puntos-codigos" role="img"
             aria-label="<?= $ec['disponibles'] ?> códigos disponibles y <?= $ec['usados'] ?> usados de <?= $ec['total'] ?>">
            <?php
            $vencidosN = $ec['total'] - $ec['usados'] - $ec['disponibles'];
            $clases = array_merge(
                array_fill(0, $ec['disponibles'], 'punto-disponible'),
                array_fill(0, $ec['usados'], 'punto-usado'),
                array_fill(0, max(0, $vencidosN), 'punto-vencido')
            );
            foreach ($clases as $clase): ?>
                <span class="punto-codigo <?= $clase ?>"></span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="mensaje mensaje-aviso">No tienes códigos de recuperación guardados. Genera un juego nuevo.</p>
    <?php endif; ?>
    <p class="texto-suave">
        Sirven si tu método principal falla (perdiste el teléfono, no te llega el código, la cámara no te
        reconoce). Cada código sirve una sola vez. Cuando se gasten todos o venzan, te damos un juego nuevo
        automáticamente la próxima vez que inicies sesión.
        <?php if ($pocos && $ec['hay_lote']): ?>
            Si quieres uno ya, puedes generarlo aquí abajo.
        <?php endif; ?>
    </p>

    <details class="bloque-plegable" <?= $pocos ? 'open' : '' ?>>
        <summary>Generar códigos nuevos</summary>
        <p class="texto-suave">Los códigos actuales dejarán de funcionar. Escribe tu contraseña para confirmar.</p>
        <form action="seguridad.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="regenerar_codigos">
            <label for="password-regenerar">Contraseña</label>
            <input type="password" id="password-regenerar" name="password" autocomplete="current-password" required>
            <button type="submit">Generar códigos nuevos</button>
        </form>
    </details>
</section>
