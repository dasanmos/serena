<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/AlertaTemprana.php';
requiereRol('estudiante');

// Botón "Necesito ayuda ahora": crea una alerta de urgencia alta que ven de inmediato
// psicología (institución completa) y los orientadores del grupo del estudiante, y
// dispara el correo a ambos (ver AlertaTemprana::crear/avisarPorCorreoSiCritica). No
// hay throttle de varios días como en las alertas automáticas (existeReciente) a
// propósito: en una situación de crisis nunca se debe ignorar un segundo pedido de
// ayuda. Sí hay un debounce corto (30s) solo para no duplicar por doble clic o F5.
$uid = (int) $_SESSION['usuario_id'];
$enviado = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');

    $ahora = time();
    $ultimoEnvio = (int) ($_SESSION['ultimo_envio_ayuda_urgente'] ?? 0);
    if ($ahora - $ultimoEnvio < 30) {
        // Doble clic o recarga del formulario: no duplicar la alerta, pero sí
        // mostrar la confirmación (para quien recargó por accidente no vea un error).
        $enviado = true;
    } else {
        $nota = trim((string) ($_POST['nota'] ?? ''));
        $descripcion = 'El estudiante pidió ayuda urgente desde la plataforma.';
        if ($nota !== '') {
            $descripcion .= ' Nota del estudiante: ' . $nota;
        }
        $descripcion = mb_substr($descripcion, 0, 255);

        AlertaTemprana::crear($pdo, $uid, AlertaTemprana::TIPO_AYUDA_URGENTE, $descripcion, 'alto');
        $_SESSION['ultimo_envio_ayuda_urgente'] = $ahora;
        $enviado = true;
    }
}

$tituloPagina = 'Ayuda urgente';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Ayuda urgente</h1>

<?php if ($enviado): ?>
<section class="panel-seccion panel-ayuda-urgente-confirmacion">
    <h2>Ya avisamos a psicología y orientación</h2>
    <p>
        Saben que necesitas hablar y van a buscarte lo antes posible. Mientras tanto,
        no estás solo: aquí tienes formas de conseguir ayuda ahora mismo.
    </p>
</section>
<?php else: ?>
<p class="texto-suave">
    Si necesitas hablar con alguien ya, usa este botón: avisa de inmediato a psicología
    y a los orientadores de tu grupo, con más urgencia que un mensaje normal por el chat.
</p>
<section class="panel-seccion">
    <form action="ayuda_urgente.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <label for="nota-ayuda-urgente">Si quieres, cuenta brevemente qué pasa (opcional)</label>
        <textarea id="nota-ayuda-urgente" name="nota" maxlength="200" rows="3"
            placeholder="Puedes dejarlo en blanco y contarlo cuando te contacten."></textarea>
        <button type="submit" class="boton-peligro">🆘 Avisar a psicología y orientación ahora</button>
    </form>
</section>
<?php endif; ?>

<div class="banner-ayuda-inmediata" style="position:static">
    <div>
        <strong>¿Necesitas hablar con alguien ya mismo?</strong>
        <p class="texto-suave u-mb-0">Línea 106 (salud mental, gratuita, 24/7) — también puedes llamar al 123 en una emergencia.</p>
    </div>
    <a href="tel:106" class="boton-linea-ayuda boton-acceso">📞 Llamar al 106</a>
</div>

<section class="panel-seccion" style="margin-top:20px">
    <h2>Otras líneas de ayuda en Colombia</h2>
    <ul class="lista-simple lista-lineas-ayuda">
        <li>📞 <strong>Línea 106</strong> — Salud mental y bienestar (gratuita, 24/7)</li>
        <li>📞 <strong>Línea 155</strong> — Violencia de género</li>
        <li>📞 <strong>Línea 123</strong> — Emergencias</li>
        <li>📞 <strong>Línea 018000-113113</strong> — ICBF (menores de edad)</li>
    </ul>
</section>

<p class="texto-suave">
    ¿No es una urgencia? Puedes escribir con calma por el
    <a href="<?= BASE_URL ?>/estudiante/mensajes.php">chat</a> o agendar una
    <a href="<?= BASE_URL ?>/estudiante/citas.php">cita</a> en su lugar.
</p>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
