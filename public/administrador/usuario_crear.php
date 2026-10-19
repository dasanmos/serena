<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
require_once __DIR__ . '/../../src/modelos/RolesUsuario.php';

requiereRol('administrador');

$error = '';

$rolesDisponibles = $pdo->query(
    "SELECT id, nombre FROM roles WHERE nombre NOT IN ('estudiante', 'superadmin') ORDER BY nombre"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
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
    } elseif (!validarPassword($password)) {
        $error = mensajeRequisitosPassword();
    } elseif ($nombre === '' || $rolId === 0) {
        $error = 'Completa nombre y rol.';
    } else {
        // ¿Ya tiene cuenta? Primero por DOCUMENTO en este colegio, luego por correo: si es la misma persona
        // se le suma el rol a su cuenta (multirol) en lugar de crear otra o de rechazar.
        $persona = RolesUsuario::cuentaDeLaPersona($pdo, (int) colegioParaCrear(), $correo, Usuario::normalizarDocumento((string) ($_POST['documento'] ?? '')));
        $existente = $persona['cuenta'] ?? null;

        if (!empty($persona['conflicto'])) {
            $error = $persona['conflicto'];
        } elseif ($existente) {
            // La misma persona con otro rol en este colegio: se le suma el rol a SU cuenta (multirol).
            $rolNuevo = '';
            foreach ($rolesDisponibles as $rd) { if ((int) $rd['id'] === $rolId) { $rolNuevo = (string) $rd['nombre']; } }
            if ((int) $existente['colegio_id'] !== (int) colegioParaCrear()) {
                $error = 'Ese correo ya lo usa una cuenta de otra institución. Para que esta persona también tenga cuenta aquí, usa otro correo: con su mismo documento, al iniciar sesión elegirá a qué institución entrar.';
            } elseif ($existente['rol'] === $rolNuevo || in_array($rolNuevo, RolesUsuario::adicionales($pdo, (int) $existente['id']), true)) {
                $error = 'Esa persona ya tiene ese rol.';
            } else {
                $errRol = RolesUsuario::agregar($pdo, (int) $existente['id'], $rolNuevo, (int) $_SESSION['usuario_id']);
                if ($errRol !== '') {
                    $error = $errRol;
                } else {
                    header('Location: usuarios.php?rol_agregado=' . (int) $existente['id']);
                    exit;
                }
            }
        } else {
            $consulta = $pdo->prepare(
                'INSERT INTO usuarios
                    (nombre, correo, password_hash, rol_id, colegio_id, grupo_id, consentimiento_datos, fecha_consentimiento, fecha_registro)
                 VALUES
                    (:nombre, :correo, :password_hash, :rol_id, :colegio_id, :grupo_id, 1, NOW(), NOW())'
            );
            $consulta->execute([
                'nombre'        => $nombre,
                'correo'        => $correo,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'rol_id'        => $rolId,
                'colegio_id'    => colegioParaCrear(),
                'grupo_id'      => $grupoId,
            ]);

            if (trim($_POST['documento'] ?? '') !== '') {
                Usuario::guardarDocumento($pdo, (int) $pdo->lastInsertId(), (string) $_POST['documento']);
            }
            header('Location: usuarios.php?creado=1');
            exit;
        }
    }
}

$grupos = Grupo::listarConGrado($pdo);

$tituloPagina = 'Crear usuario';
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Crear usuario</h1>

<?php if ($error): ?>
    <p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form action="usuario_crear.php" method="POST" class="formulario-panel" novalidate>
    <?php campoCSRF(); ?>
    <label for="nombre">Nombre completo</label>
    <input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">

    <label for="correo">Correo</label>
    <input type="email" id="correo" name="correo" required value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
    <small class="texto-suave">Si ya existe una cuenta con este correo en tu colegio (por ejemplo, la de un padre de familia), se le agrega este rol: tendrá una sola cuenta y elegirá con qué rol entrar.</small>

    <label for="documento">Documento (opcional)</label>
    <input type="text" id="documento" name="documento" maxlength="20" autocomplete="off" value="<?= htmlspecialchars($_POST['documento'] ?? '') ?>">
    <small class="texto-suave">Con este número también podrá iniciar sesión. Sin puntos ni espacios.</small>

    <label for="rol_id">Rol</label>
    <select id="rol_id" name="rol_id" required>
        <option value="">Selecciona un rol</option>
        <?php foreach ($rolesDisponibles as $rol): ?>
            <option value="<?= $rol['id'] ?>"><?= htmlspecialchars(ucfirst($rol['nombre'])) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="grupo_id">Grupo asignado (opcional, para docentes)</label>
    <select id="grupo_id" name="grupo_id">
        <option value="">Sin asignar</option>
        <?php foreach ($grupos as $grupo): ?>
            <option value="<?= $grupo['id'] ?>"><?= htmlspecialchars($grupo['grado'] . ' ' . $grupo['nombre']) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="password">Contraseña temporal</label>
    <input type="password" id="password" name="password" autocomplete="new-password" required
           minlength="8" maxlength="64" data-requisitos="1"
           pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}"
           title="Entre 8 y 64 caracteres, con mayúscula, minúscula, número y carácter especial.">

    <button type="submit">Crear usuario</button>
</form>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
