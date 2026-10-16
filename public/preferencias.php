<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/impersonacion.php';
require_once __DIR__ . '/../src/modelos/Usuario.php';

requiereSesion();

$uid = (int) $_SESSION['usuario_id'];
$viendoComo = estaViendoComoOtro(); // igual que en seguridad.php y sesiones.php: no se cambian preferencias de otra persona desde "Ver como"
$error = '';
$exito = '';

if (!$viendoComo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_tema') {
        $tema = $_POST['tema'] ?? '';
        if (!in_array($tema, ['claro', 'oscuro', 'sistema'], true)) {
            $error = 'Tema inválido.';
        } else {
            Usuario::actualizarTemaPreferido($pdo, $uid, $tema);
            $_SESSION['tema_preferido'] = $tema;
            header('Location: ' . BASE_URL . '/preferencias.php?ok=tema');
            exit;
        }
    } elseif ($accion === 'guardar_resumen') {
        $activo = isset($_POST['resumen_semanal_activo']);
        Usuario::actualizarResumenSemanalActivo($pdo, $uid, $activo);
        header('Location: ' . BASE_URL . '/preferencias.php?ok=resumen');
        exit;
    } elseif ($accion === 'guardar_accesibilidad') {
        $tamano = $_POST['tamano_texto'] ?? '';
        if (!in_array($tamano, ['normal', 'grande', 'muy_grande'], true)) {
            $error = 'Tamaño de texto inválido.';
        } else {
            $altoContraste = isset($_POST['alto_contraste']);
            Usuario::actualizarAccesibilidad($pdo, $uid, $tamano, $altoContraste);
            $_SESSION['tamano_texto'] = $tamano;
            $_SESSION['alto_contraste'] = $altoContraste;
            header('Location: ' . BASE_URL . '/preferencias.php?ok=accesibilidad');
            exit;
        }
    }
}

$mensajesOk = [
    'tema'          => 'Tema actualizado.',
    'resumen'       => 'Preferencia de correo actualizada.',
    'accesibilidad' => 'Preferencias de accesibilidad actualizadas.',
];
if (isset($_GET['ok'], $mensajesOk[$_GET['ok']])) {
    $exito = $mensajesOk[$_GET['ok']];
}

$usuario = Usuario::buscarPorId($pdo, $uid);
$temaActual = $viendoComo ? 'sistema' : ($_SESSION['tema_preferido'] ?? ($usuario['tema_preferido'] ?? 'sistema'));
$resumenActivo = $viendoComo ? true : ((int) ($usuario['resumen_semanal_activo'] ?? 1) === 1);
$tamanoActual = $viendoComo ? 'normal' : ($_SESSION['tamano_texto'] ?? ($usuario['tamano_texto'] ?? 'normal'));
$altoContrasteActual = $viendoComo ? false : ($_SESSION['alto_contraste'] ?? ((int) ($usuario['alto_contraste'] ?? 0) === 1));

$tituloPagina = 'Preferencias';
$cssExtra = ['/assets/css/preferencias.css'];
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Preferencias</h1>
<?php if ($error): ?><p class="mensaje mensaje-error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito" role="status"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<?php if ($viendoComo): ?>
<section class="panel-seccion">
    <h2>No disponible en "Ver como usuario"</h2>
    <p class="texto-suave">
        Las preferencias de una cuenta solo las puede cambiar su dueño desde su propia sesión.
        Vuelve a tu cuenta de administrador para ajustar las tuyas.
    </p>
</section>
<?php else: ?>

<div class="columnas">
<section class="panel-seccion">
    <h2>Tema</h2>
    <p class="texto-suave">
        Elige cómo se ve Serena en este y en tus otros dispositivos. "Según el sistema"
        sigue el modo claro/oscuro configurado en tu computador o teléfono.
    </p>
    <form action="preferencias.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="guardar_tema">
        <label for="tema-sistema" class="opcion-radio">
            <input type="radio" id="tema-sistema" name="tema" value="sistema" <?= $temaActual === 'sistema' ? 'checked' : '' ?>>
            Según el sistema
        </label>
        <label for="tema-claro" class="opcion-radio">
            <input type="radio" id="tema-claro" name="tema" value="claro" <?= $temaActual === 'claro' ? 'checked' : '' ?>>
            Claro
        </label>
        <label for="tema-oscuro" class="opcion-radio">
            <input type="radio" id="tema-oscuro" name="tema" value="oscuro" <?= $temaActual === 'oscuro' ? 'checked' : '' ?>>
            Oscuro
        </label>
        <button type="submit">Guardar tema</button>
    </form>
</section>

<section class="panel-seccion">
    <h2>Accesibilidad</h2>
    <p class="texto-suave">
        Ajusta el tamaño del texto y el contraste de colores en toda la plataforma.
    </p>
    <form action="preferencias.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="guardar_accesibilidad">
        <fieldset class="grupo-radios">
            <legend>Tamaño del texto</legend>
            <label for="tamano-normal" class="opcion-radio">
                <input type="radio" id="tamano-normal" name="tamano_texto" value="normal" <?= $tamanoActual === 'normal' ? 'checked' : '' ?>>
                Normal
            </label>
            <label for="tamano-grande" class="opcion-radio">
                <input type="radio" id="tamano-grande" name="tamano_texto" value="grande" <?= $tamanoActual === 'grande' ? 'checked' : '' ?>>
                Grande
            </label>
            <label for="tamano-muy-grande" class="opcion-radio">
                <input type="radio" id="tamano-muy-grande" name="tamano_texto" value="muy_grande" <?= $tamanoActual === 'muy_grande' ? 'checked' : '' ?>>
                Muy grande
            </label>
        </fieldset>
        <label for="alto-contraste" class="opcion-consentimiento">
            <input type="checkbox" id="alto-contraste" name="alto_contraste" value="1" <?= $altoContrasteActual ? 'checked' : '' ?>>
            Alto contraste (texto y bordes más marcados)
        </label>
        <button type="submit">Guardar accesibilidad</button>
    </form>
</section>

<section class="panel-seccion">
    <h2>Correo</h2>
    <p class="texto-suave">
        El resumen semanal te muestra cómo te fue en la semana según lo que hayas
        registrado en Serena (emociones, hábitos, sueño). Llega por correo a tu
        dirección registrada.
    </p>
    <form action="preferencias.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="guardar_resumen">
        <label for="resumen-activo" class="opcion-consentimiento">
            <input type="checkbox" id="resumen-activo" name="resumen_semanal_activo" value="1" <?= $resumenActivo ? 'checked' : '' ?>>
            Recibir mi resumen semanal por correo
        </label>
        <button type="submit">Guardar</button>
    </form>
</section>
</div>

<?php endif; ?>

<section class="panel-seccion">
    <h2>Cookies y privacidad</h2>
    <p class="texto-suave">Elige si Serena puede cargar servicios de terceros opcionales (hoy, los mapas de OpenStreetMap). La cookie de sesión y tus preferencias de apariencia siempre se usan porque son necesarias.</p>
    <p data-cookies-estado role="status">Todavía no has elegido.</p>
    <p>
        <button type="button" data-cookies-elegir="todas">Aceptar todas</button>
        <button type="button" class="boton-secundario" data-cookies-elegir="necesarias">Solo necesarias</button>
    </p>
    <p><a href="<?= BASE_URL ?>/cookies.php">Leer la política de cookies</a></p>
</section>
<?php require __DIR__ . '/parciales/pie.php'; ?>
