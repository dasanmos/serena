<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
require_once __DIR__ . '/../../src/modelos/RolesUsuario.php';

requiereRol('administrador');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
exigirUsuarioDeMiColegio($pdo, $id); // multi-colegio: 404 si es de otro colegio (o superadmin)
$usuario = Usuario::buscarPorId($pdo, $id);
if (!$usuario) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}

$error = '';
$cuentaMismoDocumento = null;
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

// Unir dos cuentas de la misma persona (mismo documento en este colegio)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'unir_cuentas') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $otraId = (int) ($_POST['otra_id'] ?? 0);
    $q = $pdo->prepare('SELECT id, colegio_id, documento, nombre FROM usuarios WHERE id = ?');
    $q->execute([$otraId]);
    $otraCuenta = $q->fetch();
    $docForm = Usuario::normalizarDocumento((string) ($_POST['documento_unir'] ?? ''));
    $mensajeUnir = '';
    if (!$otraCuenta || (int) $otraCuenta['colegio_id'] !== (int) $usuario['colegio_id']
        || Usuario::normalizarDocumento((string) $otraCuenta['documento']) !== $docForm || $docForm === '') {
        $mensajeUnir = 'No se pudo comprobar que las dos cuentas tengan el mismo documento.';
    } else {
        [$conservar, $eliminar] = ($_POST['conservar'] ?? '') === 'esta' ? [$id, $otraId] : [$otraId, $id];
        $mensajeUnir = RolesUsuario::unirCuentas($pdo, $conservar, $eliminar, (int) $_SESSION['usuario_id']);
        if ($mensajeUnir === '') {
            auditarAccion($pdo, 'unir_cuentas', 'usuarios', $conservar, ['eliminada' => $eliminar], ['conservada' => $conservar]);
            if ($conservar === $id && $docForm !== '') {
                Usuario::guardarDocumento($pdo, $id, $docForm);
            }
            $_SESSION['flash_roles'] = ['exito' => 'Cuentas unidas: ahora es una sola cuenta con todos sus roles. Al entrar elegirá con qué rol ingresar.'];
            header('Location: usuario_editar.php?id=' . $conservar . '#roles-cuenta');
            exit;
        }
    }
    $_SESSION['flash_roles'] = ['error' => $mensajeUnir];
    header('Location: usuario_editar.php?id=' . $id . '#roles-cuenta');
    exit;
}

// Roles adicionales (cuenta con varios roles, migración 044)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['rol_agregar', 'rol_quitar'], true)) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $rolMov = (string) ($_POST['rol'] ?? '');
    $errRol = $_POST['accion'] === 'rol_agregar'
        ? RolesUsuario::agregar($pdo, $id, $rolMov, (int) $_SESSION['usuario_id'])
        : RolesUsuario::quitar($pdo, $id, $rolMov);
    if ($errRol === '') {
        auditarAccion($pdo, $_POST['accion'], 'usuarios', $id, [], ['rol' => $rolMov]);
    }
    $_SESSION['flash_roles'] = $errRol === '' ? ['exito' => $_POST['accion'] === 'rol_agregar' ? 'Rol agregado.' : 'Rol quitado.'] : ['error' => $errRol];
    header('Location: usuario_editar.php?id=' . $id . '#roles-cuenta');
    exit;
}
$flashRoles = $_SESSION['flash_roles'] ?? null;
unset($_SESSION['flash_roles']);

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
        $otra = null;
        foreach (Usuario::buscarPorDocumento($pdo, Usuario::normalizarDocumento((string) $_POST['documento'])) as $cx) {
            if ((int) $cx['colegio_id'] === (int) $usuario['colegio_id'] && (int) $cx['id'] !== $id) { $otra = $cx; }
        }
        $error = $otra
            ? "Ese documento ya es de la cuenta de {$otra['nombre']} ({$otra['correo']}). Si es la misma persona, puedes unir las dos cuentas en una sola aquí abajo."
            : 'Ese documento ya lo tiene otra cuenta de este colegio.';
        $cuentaMismoDocumento = $otra;
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
<?php if ($cuentaMismoDocumento && in_array($usuario['rol'], RolesUsuario::COMBINABLES, true) && in_array($cuentaMismoDocumento['rol'], RolesUsuario::COMBINABLES, true)): ?>
<section class="panel-seccion" style="margin-bottom:20px">
    <h2>Unir en una sola cuenta</h2>
    <p>Si <strong><?= htmlspecialchars($cuentaMismoDocumento['nombre']) ?></strong> y <strong><?= htmlspecialchars($usuario['nombre']) ?></strong> son la misma persona, une las dos cuentas: quedará <strong>una sola</strong> con todos sus roles (<?= htmlspecialchars(implode(', ', array_map(static fn (string $r): string => RolesUsuario::NOMBRES[$r][1] ?? $r, array_values(array_unique(array_merge(RolesUsuario::roles($pdo, (int) $cuentaMismoDocumento['id']), RolesUsuario::roles($pdo, $id))))))) ?>), y al entrar elegirá con qué rol ingresar. Sus estudiantes vinculados, grupos, mensajes y registros pasan a la cuenta que se conserva.</p>
    <p class="texto-suave">Elige qué correo y contraseña se quedan. La otra cuenta se elimina.</p>
    <div class="u-flex u-gap-sm" style="flex-wrap:wrap">
        <?php foreach ([['otra', $cuentaMismoDocumento['correo']], ['esta', $usuario['correo']]] as [$cual, $correoQueda]): ?>
        <form action="usuario_editar.php?id=<?= $id ?>" method="POST" onsubmit="return confirm('¿Unir las dos cuentas? Se conserva <?= htmlspecialchars(addslashes((string) $correoQueda)) ?> y la otra se elimina.');">
            <?php campoCSRF(); ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="accion" value="unir_cuentas">
            <input type="hidden" name="otra_id" value="<?= (int) $cuentaMismoDocumento['id'] ?>">
            <input type="hidden" name="conservar" value="<?= $cual ?>">
            <input type="hidden" name="documento_unir" value="<?= htmlspecialchars(Usuario::normalizarDocumento((string) ($_POST['documento'] ?? ''))) ?>">
            <button type="submit" class="<?= $cual === 'otra' ? '' : 'boton-secundario' ?>">Unir y conservar <?= htmlspecialchars((string) $correoQueda) ?></button>
        </form>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php if (isset($_GET['dospasos'])): ?><p class="mensaje mensaje-exito">Verificación en 2 pasos desactivada para esta cuenta.</p><?php endif; ?>

<form action="usuario_editar.php" method="POST" class="formulario-panel" novalidate>
    <?php campoCSRF(); ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <label for="nombre">Nombre completo</label>
    <input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? $usuario['nombre']) ?>">

    <label for="correo">Correo</label>
    <input type="email" id="correo" name="correo" required value="<?= htmlspecialchars($_POST['correo'] ?? $usuario['correo']) ?>">

    <label for="documento">Documento (opcional)</label>
    <input type="text" id="documento" name="documento" maxlength="20" autocomplete="off" value="<?= htmlspecialchars($_POST['documento'] ?? ($usuario['documento'] ?? '')) ?>">
    <small class="texto-suave">Con este número también podrá iniciar sesión. Sin puntos ni espacios.</small>

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

<?php if (!$esEstudiante && in_array($usuario['rol'], RolesUsuario::COMBINABLES, true)):
    $rolesCuenta = RolesUsuario::roles($pdo, $id);
    $porAgregar = array_values(array_diff(RolesUsuario::COMBINABLES, $rolesCuenta)); ?>
<section class="panel-seccion" id="roles-cuenta" style="margin-top:24px">
    <h2>Roles de esta cuenta</h2>
    <p class="texto-suave">Una misma persona puede tener varios roles en el colegio (por ejemplo, docente y madre de un estudiante). Con más de un rol, al iniciar sesión elige con cuál entrar.</p>
    <?php if ($flashRoles && !empty($flashRoles['error'])): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($flashRoles['error']) ?></p><?php endif; ?>
    <?php if ($flashRoles && !empty($flashRoles['exito'])): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($flashRoles['exito']) ?></p><?php endif; ?>
    <ul class="lista-simple">
        <?php foreach ($rolesCuenta as $i => $rc): [$ic, $tit] = RolesUsuario::NOMBRES[$rc] ?? ['•', ucfirst($rc)]; ?>
        <li class="u-flex u-gap-sm" style="align-items:center;justify-content:space-between">
            <span><?= $ic ?> <strong><?= htmlspecialchars($tit) ?></strong><?= $i === 0 ? ' <span class="pill pill-neutro">principal</span>' : '' ?></span>
            <?php if (count($rolesCuenta) > 1): ?>
            <form action="usuario_editar.php?id=<?= $id ?>" method="POST" onsubmit="return confirm('¿Quitar este rol de la cuenta?');">
                <?php campoCSRF(); ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="rol_quitar"><input type="hidden" name="rol" value="<?= htmlspecialchars($rc) ?>">
                <button type="submit" class="boton-peligro">Quitar</button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($porAgregar && RolesUsuario::disponible($pdo)): ?>
    <form action="usuario_editar.php?id=<?= $id ?>" method="POST" class="acad-filtros">
        <?php campoCSRF(); ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accion" value="rol_agregar">
        <label>Agregar rol
            <select name="rol">
                <?php foreach ($porAgregar as $pa): ?><option value="<?= htmlspecialchars($pa) ?>"><?= htmlspecialchars(RolesUsuario::NOMBRES[$pa][1]) ?></option><?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Agregar</button>
    </form>
    <?php elseif (!RolesUsuario::disponible($pdo)): ?>
    <p class="mensaje mensaje-aviso">Para dar varios roles a una cuenta, aplica la migración <code>sql/migraciones/044_multirol.sql</code>.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

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
