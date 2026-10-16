<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/impersonacion.php';
require_once __DIR__ . '/../src/modelos/SesionActiva.php';

requiereSesion();

// Aquí se ven IPs y navegadores de la persona: nada de esto debe quedar en caché.
header('Cache-Control: no-store');

$uid = (int) $_SESSION['usuario_id'];
$viendoComo = estaViendoComoOtro(); // igual que en seguridad.php: no se gestiona la sesión de otra persona desde "Ver como"
$sidActual = session_id();
$error = '';
$exito = '';

if (!$viendoComo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cerrar_sesion') {
        $id = (int) ($_POST['id'] ?? 0);
        $eraLaActual = false;
        // Se comprueba ANTES de revocar si es la sesión con la que se está
        // navegando ahora mismo, para decidir a dónde mandar después.
        $fila = $pdo->prepare('SELECT session_id FROM sesiones_activas WHERE id=:id AND usuario_id=:u');
        $fila->execute(['id' => $id, 'u' => $uid]);
        $encontrada = $fila->fetch();
        if ($encontrada) {
            $eraLaActual = hash_equals($sidActual, (string) $encontrada['session_id']);
        }

        if (SesionActiva::revocarPorId($pdo, $uid, $id, $sidActual)) {
            if ($eraLaActual) {
                // Cerrar la sesión con la que se está navegando ahora: además de
                // revocar la fila, hay que destruir la sesión de PHP ya mismo
                // (si no, la propia página seguiría "adentro" hasta el próximo
                // request, porque requiereSesion() ya pasó al inicio de esta carga).
                cerrarSesionUsuario();
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            }
            header('Location: ' . BASE_URL . '/sesiones.php?ok=cerrada');
            exit;
        }
        $error = 'No se pudo cerrar esa sesión.';
    } elseif ($accion === 'cerrar_otras') {
        $n = SesionActiva::revocarTodasMenosActual($pdo, $uid, $sidActual);
        header('Location: ' . BASE_URL . '/sesiones.php?ok=otras&n=' . $n);
        exit;
    }
}

$mensajesOk = [
    'cerrada' => 'Sesión cerrada.',
];
if (isset($_GET['ok']) && $_GET['ok'] === 'otras') {
    $n = (int) ($_GET['n'] ?? 0);
    $exito = $n > 0 ? "Cerramos {$n} sesión(es) en otros dispositivos." : 'No había otras sesiones abiertas.';
} elseif (isset($_GET['ok'], $mensajesOk[$_GET['ok']])) {
    $exito = $mensajesOk[$_GET['ok']];
}

$sesiones = $viendoComo ? [] : SesionActiva::listarDeUsuario($pdo, $uid);

function tiempoRelativo(string $fecha): string
{
    $segundos = time() - strtotime($fecha);
    if ($segundos < 60) return 'justo ahora';
    if ($segundos < 3600) return (int) ($segundos / 60) . ' min';
    if ($segundos < 86400) return (int) ($segundos / 3600) . ' h';
    return (int) ($segundos / 86400) . ' d';
}

$tituloPagina = 'Mis sesiones';
$cssExtra = ['/assets/css/seguridad.css'];
require __DIR__ . '/parciales/encabezado.php';
?>
<h1>Mis sesiones</h1>
<?php if ($error): ?><p class="mensaje mensaje-error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito" role="status"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<?php if ($viendoComo): ?>
<section class="panel-seccion seccion-2fa">
    <h2>No disponible en "Ver como usuario"</h2>
    <p class="texto-suave">
        Las sesiones de una cuenta solo las puede gestionar su dueño desde su propia sesión.
        Vuelve a tu cuenta de administrador para ver las tuyas.
    </p>
</section>
<?php else: ?>

<section class="panel-seccion seccion-2fa">
    <p class="texto-suave">
        Estos son los dispositivos donde tienes la sesión abierta ahora mismo. Si no
        reconoces alguno, ciérralo — no necesitas la contraseña de ese dispositivo
        para hacerlo, solo la tuya, desde aquí.
    </p>
</section>

<section class="rejilla-sesiones" aria-label="Dispositivos con sesión activa">
    <?php foreach ($sesiones as $s): $esta = hash_equals($sidActual, (string) $s['session_id']); ?>
    <article class="tarjeta-sesion <?= $esta ? 'tarjeta-sesion-actual' : '' ?>">
        <div class="tarjeta-sesion-encabezado">
            <span class="tarjeta-sesion-icono">💻</span>
            <div>
                <h2><?= htmlspecialchars(SesionActiva::descripcionDispositivo($s['agente_usuario'])) ?></h2>
                <?php if ($esta): ?><span class="pill pill-exito">Esta sesión</span><?php endif; ?>
            </div>
        </div>
        <p class="texto-suave tarjeta-sesion-detalle">
            <?= $s['ip'] ? htmlspecialchars($s['ip']) . ' · ' : '' ?>
            Activa hace <?= tiempoRelativo($s['ultima_actividad']) ?>
            · Iniciada el <?= date('d/m/Y H:i', strtotime($s['iniciada_en'])) ?>
        </p>
        <form action="sesiones.php" method="POST" novalidate
              onsubmit="return confirm('<?= $esta ? '¿Cerrar esta sesión? Se cerrará tu sesión actual y tendrás que volver a iniciar sesión.' : '¿Cerrar esta sesión en ese dispositivo?' ?>');">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="cerrar_sesion">
            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button type="submit" class="<?= $esta ? 'boton-peligro' : 'boton-secundario' ?>">
                <?= $esta ? 'Cerrar esta sesión' : 'Cerrar sesión' ?>
            </button>
        </form>
    </article>
    <?php endforeach; ?>
</section>

<?php if (count($sesiones) > 1): ?>
<section class="panel-seccion seccion-2fa">
    <h2>Cerrar en otros dispositivos</h2>
    <p class="texto-suave">Cierra todas las sesiones activas excepto la que estás usando ahora mismo.</p>
    <form action="sesiones.php" method="POST" novalidate
          onsubmit="return confirm('¿Cerrar la sesión en todos los demás dispositivos? Esta sesión seguirá activa.');">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="cerrar_otras">
        <button type="submit" class="boton-peligro">Cerrar sesión en otros dispositivos</button>
    </form>
</section>
<?php endif; ?>

<?php endif; ?>
<?php require __DIR__ . '/parciales/pie.php'; ?>
