<?php
// Vista del método "Reconocimiento facial" de seguridad.php (se incluye desde allí;
// comparte sus variables). La cámara y el análisis viven en assets/js/rostro.js.
if (!isset($verRostro)) { http_response_code(403); exit; }
?>
<p class="volver-metodos"><a href="<?= BASE_URL ?>/seguridad.php">← Volver a Seguridad</a></p>

<?php if ($viendoComo): ?>
<section class="panel-seccion seccion-2fa">
    <h2>No disponible en "Ver como usuario"</h2>
    <p class="texto-suave">
        La seguridad de una cuenta solo la puede cambiar su dueño desde su propia sesión.
        Vuelve a tu cuenta de administrador para gestionar la tuya.
    </p>
</section>

<?php elseif (!$activoRostro && $rostroActivacionPendiente): ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-registrar-rostro">
    <h2 id="titulo-registrar-rostro">Registra tu rostro</h2>
    <p class="texto-suave">
        Vamos a tomar <?= VerificacionDosPasosRostro::MUESTRAS ?> lecturas seguidas. Busca un lugar con luz suave
        de frente, sin gorra ni lentes oscuros, y mira a la cámara.
    </p>
    <form action="seguridad.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="rostro_confirmar_activacion">
        <?php $rostroModo = 'registro'; require __DIR__ . '/visor_rostro.php'; ?>
    </form>
    <form action="seguridad.php" method="POST" class="cancelar-2fa" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="rostro_cancelar_activacion">
        <button type="submit" class="boton-secundario">Cancelar</button>
    </form>
</section>

<?php elseif (!$activoRostro): ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-intro-rostro">
    <div class="encabezado-2fa">
        <h2 id="titulo-intro-rostro">Reconocimiento facial</h2>
        <span class="estado-2fa estado-2fa-off">Desactivado</span>
    </div>
    <p>
        Además de tu contraseña, al iniciar sesión te pediremos mirar a la cámara para confirmar que eres tú.
    </p>
    <ul class="beneficios-2fa">
        <li>La imagen se analiza en tu dispositivo: no se envía ni se guarda ninguna foto ni video.</li>
        <li>Guardamos solo una representación numérica de tu rostro, cifrada.</li>
        <li>Puedes desactivarlo cuando quieras y esos datos se borran al instante.</li>
        <li>Necesitas un dispositivo con cámara y conexión a internet al iniciar sesión.</li>
    </ul>
    <?php if ($activo || $activoCorreo || $activoWhatsapp): ?>
        <p class="mensaje mensaje-aviso">
            Ya tienes activo otro método de verificación. Activar este lo reemplazará
            (solo puedes tener un método de verificación en 2 pasos activo a la vez).
        </p>
    <?php endif; ?>
    <p class="mensaje mensaje-aviso">
        Importante: si algún día la cámara no funciona o no te reconoce, no podrás iniciar sesión hasta que
        el administrador de tu institución desactive este método desde tu cuenta.
    </p>
    <form action="seguridad.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="rostro_iniciar_activacion">
        <label class="consentimiento-rostro">
            <input type="checkbox" name="consentimiento" value="1" required>
            <span>
                Autorizo a Serena a guardar, de forma cifrada, una representación numérica de mi rostro
                (no una foto) para verificar mi identidad al iniciar sesión. Entiendo que son datos
                biométricos, que puedo retirar esta autorización desactivando el método y que, si soy
                menor de edad, cuento con la autorización de mi acudiente.
            </span>
        </label>
        <button type="submit">Continuar</button>
    </form>
</section>

<?php else: ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-estado-rostro">
    <div class="encabezado-2fa">
        <h2 id="titulo-estado-rostro">Reconocimiento facial</h2>
        <span class="estado-2fa estado-2fa-on">Activado</span>
    </div>
    <p class="texto-suave">
        Activo desde el <?= fechaCorta($usuario['rostro_2fa_activado_en']) ?>.
        Al iniciar sesión te pediremos mirar a la cámara. Tus datos faciales están cifrados y nunca guardamos fotos.
    </p>
</section>

<?php require __DIR__ . '/estado_codigos_recuperacion.php'; ?>

<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-desactivar-rostro">
    <h2 id="titulo-desactivar-rostro">Desactivar</h2>
    <details class="bloque-plegable">
        <summary>Desactivar reconocimiento facial</summary>
        <p class="texto-suave">
            Tu cuenta volverá a protegerse solo con la contraseña y se borrarán tus datos faciales.
            Para confirmar, escribe tu contraseña.
        </p>
        <form action="seguridad.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="rostro_desactivar">
            <label for="password-desactivar-rostro">Contraseña</label>
            <input type="password" id="password-desactivar-rostro" name="password" autocomplete="current-password" required>
            <button type="submit" class="boton-peligro">Desactivar y borrar mis datos faciales</button>
        </form>
    </details>
</section>
<?php endif; ?>
