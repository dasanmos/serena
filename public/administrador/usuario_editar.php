<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';

requiereRol('administrador');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
exigirUsuarioDeMiColegio($pdo, $id); // multi-colegio: 404 si es de otro colegio (o superadmin)
$usuario = Usuario::buscarPorId($pdo, $id);
if (!$usuario) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}

$error = '';
// Cualquiera de los cuatro métodos (app autenticadora, correo, WhatsApp o rostro).
$dosPasosActivo = ((int) ($usuario['totp_activo'] ?? 0)) === 1
    || ((int) ($usuario['correo_2fa_activo'] ?? 0)) === 1
    || ((int) ($usuario['whatsapp_2fa_activo'] ?? 0)) === 1
    || ((int) ($usuario['rostro_2fa_activo'] ?? 0)) === 1;
$metodoDosPasos = ((int) ($usuario['totp_activo'] ?? 0)) === 1 ? 'Google Authenticator'
    : (((int) ($usuario['correo_2fa_activo'] ?? 0)) === 1 ? 'código por correo'
    : (((int) ($usuario['whatsapp_2fa_activo'] ?? 0)) === 1 ? 'código por WhatsApp'
    : (((int) ($usuario['rostro_2fa_activo'] ?? 0)) === 1 ? 'reconocimiento facial' : '')));
$esEstudiante = $usuario['rol'] === 'estudiante';
$rolesDisponibles = $pdo->query(
    $esEstudiante
        ? "SELECT id, nombre FROM roles WHERE nombre = 'estudiante'"
        : "SELECT id, nombre FROM roles WHERE nombre NOT IN ('estudiante', 'superadmin') ORDER BY nombre"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $rolId = (int) ($_POST['rol_id'] ?? 0);
    $grupoId = ($_POST['grupo_id'] ?? '') !== '' ? (int) $_POST['grupo_id'] : null;
    $rolesValidos = array_map('intval', array_column($rolesDisponibles, 'id'));

    if (!in_array($rolId, $rolesValidos, true)) {
        $error = 'Rol no válido.';
    } elseif ($grupoId !== null && !grupoEsDeMiColegio($pdo, $grupoId)) {
        $error = 'El grupo no pertenece a tu colegio.';
    } elseif (!validarCorreo($correo)) {
        $error = 'El correo no es válido.';
    } elseif (trim($_POST['documento'] ?? '') !== '' && !Usuario::documentoValido(Usuario::normalizarDocumento((string) $_POST['documento']))) {
        $error = 'El documento no es válido (4 a 20 letras o números).';
    } elseif (trim($_POST['documento'] ?? '') !== '' && Usuario::documentoEnUso($pdo, Usuario::normalizarDocumento((string) $_POST['documento']), isset($usuario['colegio_id']) ? (int) $usuario['colegio_id'] : null, $id)) {
        $error = 'Ya hay otra cuenta con ese documento en este colegio.';
    } elseif ($nombre === '' || $rolId === 0) {
        $error = 'Completa nombre y rol.';
    } else {
        $existe = $pdo->prepare('SELECT id FROM usuarios WHERE correo = :correo AND id != :id LIMIT 1');
        $existe->execute(['correo' => $correo, 'id' => $id]);
        if ($existe->fetch()) {
            $error = 'Ya existe otra cuenta con ese correo.';
        } else {
            $anterior = ['nombre' => $usuario['nombre'], 'correo' => $usuario['correo'], 'rol' => $usuario['rol']];
            Usuario::actualizar($pdo, $id, $nombre, $correo, $rolId, $grupoId);
            $docNuevo = Usuario::normalizarDocumento((string) ($_POST['documento'] ?? ''));
            $docCambio = $docNuevo !== (string) ($usuario['documento'] ?? '');
            if ($docCambio) {
                Usuario::guardarDocumento($pdo, $id, $docNuevo);
            }
            auditarAccion($pdo, 'editar_usuario', 'usuarios', $id, $anterior, ['nombre' => $nombre, 'correo' => $correo, 'documento_cambiado' => $docCambio]);
            header('Location: usuarios.php?actualizado=1');
            exit;
        }
    }
}

$grupos = Grupo::listarConGrado($pdo);
$tituloPagina = 'Editar usuario';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Editar usuario</h1>

<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if (isset($_GET['dospasos'])): ?><p class="mensaje mensaje-exito">Verificación en 2 pasos desactivada para esta cuenta.</p><?php endif; ?>

<form action="usuario_editar.php" method="POST" class="formulario-panel" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <label for="nombre">Nombre completo</label>
    <input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? $usuario['nombre']) ?>">

    <label for="correo">Correo</label>
    <input type="email" id="correo" name="correo" required value="<?= htmlspecialchars($_POST['correo'] ?? $usuario['correo']) ?>
    <label for="documento">Documento (opcional)</label>
    <input type="text" id="documento" name="documento" maxlength="20" autocomplete="off" value="<?= htmlspecialchars($_POST['documento'] ?? ($usuario['documento'] ?? '')) ?>">
    <small class="texto-suave">Con este número también podrá iniciar sesión. Sin puntos ni espacios.</small>
">

    <label for="rol_id">Rol</label>
    <select id="rol_id" name="rol_id" required <?= $esEstudiante ? 'disabled' : '' ?>>
        <?php foreach ($rolesDisponibles as $rol): ?>
            <option value="<?= $rol['id'] ?>" <?= (int) $usuario['rol_id'] === (int) $rol['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars(ucfirst($rol['nombre'])) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($esEstudiante): ?>
        <input type="hidden" name="rol_id" value="<?= (int) $usuario['rol_id'] ?>">
        <p class="texto-suave" style="margin-top:-8px">El rol de un estudiante no se cambia desde aquí.</p>
    <?php endif; ?>

    <label for="grupo_id">Grupo <?= $esEstudiante ? '' : '(para docente director de grupo)' ?></label>
    <select id="grupo_id" name="grupo_id">
        <option value="">Sin asignar</option>
        <?php foreach ($grupos as $grupo): ?>
            <option value="<?= $grupo['id'] ?>" <?= (int) $usuario['grupo_id'] === (int) $grupo['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($grupo['grado'] . ' ' . $grupo['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (in_array($usuario['rol'], ['orientador', 'docente'], true)): ?>
        <p class="texto-suave" style="margin-top:-8px">¿Necesitas asignarle varios grupos (típico de un orientador)? Usa <a href="asignar_grupos_personal.php?usuario_id=<?= $id ?>">Asignar grupos</a>.</p>
    <?php endif; ?>

    <div class="u-flex u-gap-sm" style="margin-top:8px">
        <button type="submit">Guardar cambios</button>
        <a href="<?= BASE_URL ?>/administrador/resetear_password.php?id=<?= $id ?>" class="boton-secundario">Resetear contraseña</a>
    </div>
</form>

<?php if ($dosPasosActivo): ?>
<section class="panel-seccion" style="margin-top:24px">
    <h2>Verificación en 2 pasos</h2>
    <p class="texto-suave">
        Esta cuenta tiene la verificación en 2 pasos activa (<?= htmlspecialchars($metodoDosPasos) ?>).
        Si la persona perdió su teléfono, no le llegan los códigos o la cámara no la reconoce, puedes
        desactivarla para que pueda entrar solo con su contraseña (y volver a activarla después).
        La contraseña no cambia. Queda registrado en auditoría.
    </p>
    <form action="desactivar_2fa.php" method="POST" novalidate
          onsubmit="return confirm('¿Desactivar la verificación en 2 pasos de <?= htmlspecialchars(addslashes($usuario['nombre']), ENT_QUOTES) ?>? Confirma que hablaste con la persona y es quien dice ser.');">
        <?php campoCSRF(); ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="boton-peligro">Desactivar verificación en 2 pasos</button>
    </form>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
