<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/impersonacion.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';

requiereRol('administrador');

// No tiene sentido (ni debe permitirse) elegir un nuevo usuario mientras ya se está
// viendo el sistema como otro — primero hay que volver a la cuenta de administrador.
if (estaViendoComoOtro()) {
    header('Location: ' . BASE_URL . '/administrador/panel.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? null);

    $adminId = (int) $_SESSION['usuario_id'];
    $idObjetivo = (int) ($_POST['usuario_id'] ?? 0);

    $resultado = iniciarVistaComo($pdo, $idObjetivo);

    if ($resultado['exito']) {
        // Se registra con el id del ADMINISTRADOR (capturado antes del cambio de sesión),
        // nunca leyendo $_SESSION en este punto: para cuando esto se ejecuta, la sesión
        // ya pertenece al usuario impersonado.
        Auditoria::registrar($pdo, $adminId, 'ver_como_usuario', 'usuarios', $idObjetivo, [], [
            'usuario_objetivo' => $resultado['usuario']['correo'],
        ]);
        header('Location: ' . rutaDashboardPorRol($resultado['usuario']['rol']));
        exit;
    }

    $error = $resultado['mensaje'];
}

$usuarios = Usuario::listarParaVerComo($pdo, (int) $_SESSION['usuario_id']);

$tituloPagina = 'Ver como usuario';
require __DIR__ . '/../parciales/encabezado.php';
?>

<div class="cabecera-saludo">
    <h1>Ver como usuario</h1>
    <p class="texto-suave">
        Elige una cuenta para ver el sistema exactamente como lo ve esa persona.
        Vas a poder volver a tu cuenta de administrador en cualquier momento.
    </p>
</div>

<?php if ($error): ?>
    <p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<p class="mensaje mensaje-info">
    Por diseño, no aparece aquí ningún estudiante real — solo la cuenta de estudiante
    de prueba del sistema. Esto protege la privacidad de los estudiantes.
</p>

<section class="panel-seccion">
    <table class="tabla-datos">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['correo']) ?></td>
                    <td><span class="pill pill-info"><?= htmlspecialchars(ucfirst($u['rol'])) ?></span></td>
                    <td>
                        <form action="ver_como.php" method="POST" style="display:inline">
                            <?php campoCSRF(); ?>
                            <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="boton-secundario">Ver como</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($usuarios)): ?>
                <tr><td colspan="4">No hay otras cuentas disponibles todavía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
