<?php
// Vista del método "WhatsApp" de seguridad.php (se incluye desde allí; comparte
// sus variables: $usuario, $uid, $pdo, $activoWhatsapp, $whatsappActivacionPendiente...).
if (!isset($verWhatsapp)) { http_response_code(403); exit; }

$tieneCredenciales = VerificacionDosPasosWhatsapp::tieneCredencialesGuardadas($usuario);
// Muestra un fragmento identificable sin revelar el dato completo: los últimos
// 4 dígitos si es un número normal, o el identificador @lid completo (no es un
// dato tan sensible como un número de teléfono, y cortarlo a 4 caracteres no
// ayudaría a reconocerlo).
if (!function_exists('whatsappFragmentoOculto')) {
    function whatsappFragmentoOculto(string $guardado): string
    {
        if (whatsappEsIdentificadorLid($guardado)) {
            return $guardado;
        }
        return '••• ' . substr($guardado, -4);
    }
}
$numeroOculto = $tieneCredenciales ? whatsappFragmentoOculto((string) $usuario['whatsapp_2fa_numero']) : '';
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

<?php elseif (!$activoWhatsapp && $whatsappActivacionPendiente): ?>
<?php $esperaReenvioWa = VerificacionDosPasosWhatsapp::segundosParaReenvio($pdo, $uid); ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-activar-wa">
    <h2 id="titulo-activar-wa">Activar verificación por WhatsApp</h2>
    <p class="texto-suave">
        Te enviamos un código de 6 dígitos por WhatsApp a <strong><?= htmlspecialchars(whatsappFragmentoOculto((string) $usuario['whatsapp_2fa_numero'])) ?></strong>.
        Puede tardar hasta un minuto en llegar y vence en <?= VerificacionDosPasosWhatsapp::VIGENCIA_MINUTOS ?> minutos.
    </p>
    <form action="seguridad.php" method="POST" class="formulario-codigo" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="whatsapp_confirmar_activacion">
        <label for="codigo-activar-wa" class="sr-only">Código de 6 dígitos</label>
        <input type="text" id="codigo-activar-wa" name="codigo" class="campo-codigo"
               inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code"
               placeholder="000000" data-codigo-2fa autofocus required>
        <button type="submit">Verificar y activar</button>
    </form>
    <form action="seguridad.php" method="POST" class="reenviar-2fa-correo" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="whatsapp_reenviar">
        <button type="submit" class="boton-secundario" data-reenviar-2fa
                data-espera="<?= $esperaReenvioWa ?>" <?= $esperaReenvioWa > 0 ? 'disabled' : '' ?>>
            <?= $esperaReenvioWa > 0 ? "Reenviar código ({$esperaReenvioWa}s)" : 'Reenviar código' ?>
        </button>
    </form>
    <form action="seguridad.php" method="POST" class="cancelar-2fa" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="whatsapp_cancelar_activacion">
        <button type="submit" class="boton-secundario">Cancelar</button>
    </form>
</section>

<?php elseif (!$activoWhatsapp && !$tieneCredenciales): ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-conectar-wa">
    <div class="encabezado-2fa">
        <h2 id="titulo-conectar-wa">Verificación por WhatsApp</h2>
        <span class="estado-2fa estado-2fa-off">Desactivada</span>
    </div>
    <p>
        Además de tu contraseña, te enviaremos un código de 6 dígitos a tu WhatsApp al iniciar sesión.
        Usamos el servicio gratuito <strong>CallMeBot</strong>, que necesita un paso único de tu parte:
        autorizarlo desde tu propio teléfono.
    </p>
    <ol class="pasos-2fa">
        <li>
            <strong>Agrega el contacto de CallMeBot</strong>
            <span class="texto-suave">
                Encuentras el número actual en
                <a href="https://www.callmebot.com/blog/free-api-whatsapp-messages/" target="_blank" rel="noopener">callmebot.com</a>.
            </span>
        </li>
        <li>
            <strong>Envíale este mensaje por WhatsApp</strong>
            <span class="texto-suave"><code>I allow callmebot to send me messages</code></span>
        </li>
        <li>
            <strong>Espera su respuesta con tu apikey</strong>
            <span class="texto-suave">Puede tardar hasta 2 minutos.</span>
        </li>
        <li>
            <strong>Escribe aquí tu número y esa apikey</strong>
            <span class="texto-suave">
                Si tu WhatsApp usa nombre de usuario en vez de número, CallMeBot te habrá dado
                un identificador terminado en <code>@lid</code> — pégalo tal cual en vez del número.
            </span>
            <form action="seguridad.php" method="POST" class="formulario-credenciales" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="whatsapp_guardar_credenciales">
                <label for="wa-numero">Tu número de WhatsApp (o tu identificador @lid), con indicativo del país si es número</label>
                <input type="text" id="wa-numero" name="whatsapp_numero" placeholder="+57 300 1234567 o 273950831550569@lid"
                       inputmode="text" autocomplete="off" required>
                <label for="wa-apikey">Apikey de CallMeBot</label>
                <input type="text" id="wa-apikey" name="whatsapp_apikey" placeholder="123456"
                       autocomplete="off" spellcheck="false" required>
                <button type="submit">Guardar y continuar</button>
            </form>
        </li>
    </ol>
    <p class="texto-suave">
        Tu apikey se guarda cifrada y solo se usa para enviarte tus códigos. CallMeBot es un servicio
        gratuito sin garantía de entrega: si algún día el mensaje no llega, podrás pedir otro.
    </p>
</section>

<?php elseif (!$activoWhatsapp): ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-intro-wa">
    <div class="encabezado-2fa">
        <h2 id="titulo-intro-wa">Verificación por WhatsApp</h2>
        <span class="estado-2fa estado-2fa-off">Desactivada</span>
    </div>
    <p>
        Además de tu contraseña, te enviaremos un código de 6 dígitos a tu WhatsApp al iniciar sesión.
        Así, aunque alguien conozca tu contraseña, no podrá entrar a tu cuenta.
    </p>
    <p class="dato-guardado">
        <span class="texto-suave">CallMeBot conectado a</span> <code><?= htmlspecialchars($numeroOculto) ?></code>
    </p>
    <?php if ($activo || $activoCorreo || $activoRostro): ?>
        <p class="mensaje mensaje-aviso">
            Ya tienes activo otro método de verificación. Activar este lo reemplazará
            (solo puedes tener un método de verificación en 2 pasos activo a la vez).
        </p>
    <?php endif; ?>
    <form action="seguridad.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="whatsapp_iniciar_activacion">
        <button type="submit">Enviarme un código para activar</button>
    </form>

    <details class="bloque-plegable">
        <summary>Cambiar o borrar mi número y apikey</summary>
        <form action="seguridad.php" method="POST" class="formulario-credenciales" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="whatsapp_guardar_credenciales">
            <label for="wa-numero-cambio">Número de WhatsApp (o identificador @lid), con indicativo del país si es número</label>
            <input type="text" id="wa-numero-cambio" name="whatsapp_numero" placeholder="+57 300 1234567 o 273950831550569@lid"
                   inputmode="text" autocomplete="off" required>
            <label for="wa-apikey-cambio">Apikey de CallMeBot</label>
            <input type="text" id="wa-apikey-cambio" name="whatsapp_apikey" placeholder="123456"
                   autocomplete="off" spellcheck="false" required>
            <button type="submit">Guardar cambios</button>
        </form>
        <form action="seguridad.php" method="POST" novalidate
              onsubmit="return confirm('¿Borrar tu número y tu apikey de CallMeBot?');">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="whatsapp_borrar_credenciales">
            <button type="submit" class="boton-peligro">Borrar número y apikey</button>
        </form>
    </details>
</section>

<?php else: ?>
<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-estado-wa">
    <div class="encabezado-2fa">
        <h2 id="titulo-estado-wa">Verificación por WhatsApp</h2>
        <span class="estado-2fa estado-2fa-on">Activada</span>
    </div>
    <p class="texto-suave">
        Activa desde el <?= fechaCorta($usuario['whatsapp_2fa_activado_en']) ?>.
        Al iniciar sesión te enviaremos un código por WhatsApp a <?= htmlspecialchars(whatsappFragmentoOculto((string) $usuario['whatsapp_2fa_numero'])) ?>.
        Para cambiar tu número o tu apikey, primero desactiva este método.
    </p>
</section>

<?php require __DIR__ . '/estado_codigos_recuperacion.php'; ?>

<section class="panel-seccion seccion-2fa" aria-labelledby="titulo-desactivar-wa">
    <h2 id="titulo-desactivar-wa">Desactivar</h2>
    <details class="bloque-plegable">
        <summary>Desactivar verificación por WhatsApp</summary>
        <p class="texto-suave">
            Tu cuenta volverá a protegerse solo con la contraseña.
            Para confirmar, escribe tu contraseña.
        </p>
        <form action="seguridad.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="whatsapp_desactivar">
            <label for="password-desactivar-wa">Contraseña</label>
            <input type="password" id="password-desactivar-wa" name="password" autocomplete="current-password" required>
            <button type="submit" class="boton-peligro">Desactivar verificación</button>
        </form>
    </details>
</section>
<?php endif; ?>
