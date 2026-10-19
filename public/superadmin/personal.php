<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/correo.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Colegio.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

// Solo PERSONAL de colegios (nunca estudiantes ni otros superadmins).
$flash = $_SESSION['flash_personal'] ?? null;
unset($_SESSION['flash_personal']);

$q = trim((string) ($_GET['q'] ?? ''));
$colegioFiltro = (int) ($_GET['colegio'] ?? 0);
$rolFiltro = (string) ($_GET['rol'] ?? 'admin_rector');
$estadoFiltro = (string) ($_GET['estado'] ?? '');
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = 50;
$filtros = ['q' => $q, 'colegio' => $colegioFiltro, 'rol' => $rolFiltro, 'estado' => $estadoFiltro];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $uid = (int) ($_POST['usuario_id'] ?? 0);
    $f = ['exito' => '', 'error' => '', 'credencial' => null];

    $c = $pdo->prepare("SELECT u.id, u.nombre, u.correo, u.activo, u.colegio_id, r.nombre AS rol
                        FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                        WHERE u.id = :id AND r.nombre NOT IN ('estudiante','superadmin','secretaria')"
                        . sqlAlcanceColegios($pdo, 'u.colegio_id'));
    $c->execute(['id' => $uid]);
    $u = $c->fetch();

    if (!$u) {
        $f['error'] = 'Usuario no válido.';
    } elseif ($accion === 'restablecer') {
        $temporal = generarPasswordTemporalValida();
        $pdo->prepare('UPDATE usuarios SET password_hash = ?, intentos_login_fallidos = 0, login_bloqueado_hasta = NULL WHERE id = ?')
            ->execute([password_hash($temporal, PASSWORD_DEFAULT), $uid]);
        $pdo->prepare('UPDATE sesiones_activas SET revocada_en = NOW() WHERE usuario_id = ? AND revocada_en IS NULL')->execute([$uid]);
        auditarAccion($pdo, 'restablecer_password_personal', 'usuarios', $uid, [], ['correo' => $u['correo']]);

        $enlace = urlAbsoluta('/login.php');
        $texto = "Hola {$u['nombre']},\n\nSe restableció tu acceso a Serena.\n\nCorreo: {$u['correo']}\nContraseña temporal: {$temporal}\n\nCámbiala apenas inicies sesión.\n\n— Serena";
        $html = plantillaCorreoHtml('Tu acceso a Serena',
            '<p>Hola <strong>' . htmlspecialchars($u['nombre']) . '</strong>,</p><p>Se restableció tu acceso.</p>'
            . '<p><strong>Correo:</strong> ' . htmlspecialchars($u['correo']) . '<br><strong>Contraseña temporal:</strong> <code>' . htmlspecialchars($temporal) . '</code></p>'
            . botonCorreoHtml('Iniciar sesión', $enlace)
            . '<p class="sa-nota">Cámbiala apenas inicies sesión.</p>');
        if (smtpConfigurado() && enviarCorreo($u['correo'], 'Tu acceso a Serena', $texto, $html)) {
            $f['exito'] = "Contraseña restablecida y enviada a {$u['correo']}. Se cerraron sus sesiones abiertas.";
        } else {
            $f['exito'] = 'Contraseña restablecida, pero el correo no se pudo enviar. Entrégala por otro canal (no se vuelve a mostrar):';
            $f['credencial'] = ['correo' => $u['correo'], 'password' => $temporal];
        }
    } elseif ($accion === 'desbloquear') {
        $pdo->prepare('UPDATE usuarios SET intentos_login_fallidos = 0, login_bloqueado_hasta = NULL WHERE id = ?')->execute([$uid]);
        auditarAccion($pdo, 'desbloquear_personal', 'usuarios', $uid, [], ['correo' => $u['correo']]);
        $f['exito'] = 'Cuenta desbloqueada.';
    } elseif ($accion === 'activar' || $accion === 'desactivar') {
        $nuevo = $accion === 'activar' ? 1 : 0;
        $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$nuevo, $uid]);
        if ($nuevo === 0) {
            $pdo->prepare('UPDATE sesiones_activas SET revocada_en = NOW() WHERE usuario_id = ? AND revocada_en IS NULL')->execute([$uid]);
        }
        auditarAccion($pdo, $nuevo ? 'activar_personal' : 'desactivar_personal', 'usuarios', $uid, ['activo' => (int) $u['activo']], ['activo' => $nuevo]);
        $f['exito'] = $nuevo ? 'Cuenta activada.' : 'Cuenta desactivada y sesiones cerradas.';
    } else {
        $f['error'] = 'Acción no válida.';
    }
    $_SESSION['flash_personal'] = $f;
    header('Location: personal.php?' . http_build_query($filtros + ['p' => $pagina]));
    exit;
}

$donde = " WHERE r.nombre NOT IN ('estudiante','superadmin','secretaria')" . sqlAlcanceColegios($pdo, 'u.colegio_id');
$params = [];
if ($rolFiltro === 'admin_rector') {
    $donde .= " AND r.nombre IN ('administrador','rector')";
} elseif ($rolFiltro !== '' && $rolFiltro !== 'todos') {
    $donde .= ' AND r.nombre = :rol';
    $params['rol'] = $rolFiltro;
}
if ($colegioFiltro > 0) { $donde .= ' AND u.colegio_id = :colegio'; $params['colegio'] = $colegioFiltro; }
if ($estadoFiltro === 'activo') { $donde .= ' AND u.activo = 1'; }
if ($estadoFiltro === 'inactivo') { $donde .= ' AND u.activo = 0'; }
if ($estadoFiltro === 'bloqueado') { $donde .= ' AND u.login_bloqueado_hasta IS NOT NULL AND u.login_bloqueado_hasta > NOW()'; }
if ($q !== '') { $donde .= ' AND (u.nombre LIKE :q1 OR u.correo LIKE :q2)'; $params['q1'] = $params['q2'] = '%' . $q . '%'; }

$from = ' FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id LEFT JOIN colegios c ON c.id = u.colegio_id';
$total = $pdo->prepare('SELECT COUNT(*)' . $from . $donde);
$total->execute($params);
$total = (int) $total->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);

$lista = $pdo->prepare('SELECT u.id, u.nombre, u.correo, u.activo, u.ultimo_acceso, u.login_bloqueado_hasta, r.nombre AS rol, c.nombre AS colegio'
    . $from . $donde . ' ORDER BY c.nombre, r.nombre, u.nombre LIMIT ' . $porPagina . ' OFFSET ' . (($pagina - 1) * $porPagina));
$lista->execute($params);
$lista = $lista->fetchAll();

$colegios = Colegio::listarTodos($pdo);
$roles = $pdo->query("SELECT nombre FROM roles WHERE nombre NOT IN ('estudiante','superadmin','secretaria') ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$qs = http_build_query($filtros + ['p' => $pagina]);

$tituloPagina = 'Personal de colegios';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Personal de colegios</h1>
<?php if (!empty($flash['error'])): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($flash['error']) ?></p><?php endif; ?>
<?php if (!empty($flash['exito'])): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($flash['exito']) ?></p><?php endif; ?>
<?php if (!empty($flash['credencial'])): ?>
    <p class="mensaje mensaje-info">Correo: <strong><?= htmlspecialchars($flash['credencial']['correo']) ?></strong><br>
    Contraseña temporal: <code><?= htmlspecialchars($flash['credencial']['password']) ?></code></p>
<?php endif; ?>

<section class="panel-seccion">
    <form action="personal.php" method="GET">
        <label>Buscar (nombre o correo) <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"></label>
        <label>Colegio
            <select name="colegio">
                <option value="0">Todos</option>
                <?php foreach ($colegios as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $colegioFiltro ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Rol
            <select name="rol">
                <option value="admin_rector" <?= $rolFiltro === 'admin_rector' ? 'selected' : '' ?>>Administradores y rectores</option>
                <option value="todos" <?= $rolFiltro === 'todos' ? 'selected' : '' ?>>Todo el personal</option>
                <?php foreach ($roles as $r): ?><option value="<?= htmlspecialchars($r) ?>" <?= $rolFiltro === $r ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($r)) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Estado
            <select name="estado">
                <option value="">Todos</option>
                <option value="activo" <?= $estadoFiltro === 'activo' ? 'selected' : '' ?>>Activos</option>
                <option value="inactivo" <?= $estadoFiltro === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                <option value="bloqueado" <?= $estadoFiltro === 'bloqueado' ? 'selected' : '' ?>>Bloqueados</option>
            </select>
        </label>
        <button type="submit">Filtrar</button>
    </form>
    <p class="texto-suave"><?= $total ?> persona(s) · página <?= $pagina ?> de <?= $paginas ?>.</p>
</section>

<section class="panel-seccion">
    <table class="tabla-datos">
        <thead><tr><th>Nombre</th><th>Colegio</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($lista as $u):
            $bloqueado = $u['login_bloqueado_hasta'] && strtotime($u['login_bloqueado_hasta']) > time(); ?>
            <tr>
                <td><?= htmlspecialchars($u['nombre']) ?><br><span class="texto-suave"><?= htmlspecialchars($u['correo']) ?></span></td>
                <td><?= htmlspecialchars((string) ($u['colegio'] ?? '—')) ?></td>
                <td><?= htmlspecialchars(ucfirst($u['rol'])) ?></td>
                <td><?= (int) $u['activo'] === 1 ? 'Activo' : 'Inactivo' ?><?= $bloqueado ? ' · 🔒 Bloqueado' : '' ?></td>
                <td><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?></td>
                <td>
                    <form action="personal.php?<?= htmlspecialchars($qs) ?>" method="POST" class="sa-en-linea" onsubmit="return confirm('¿Seguro? Esta acción queda en la auditoría.');">
                        <?php campoCSRF(); ?>
                        <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" name="accion" value="restablecer" title="Genera una contraseña nueva, la envía por correo y cierra sus sesiones">Restablecer / reenviar contraseña</button>
                        <?php if ($bloqueado): ?><button type="submit" name="accion" value="desbloquear">Desbloquear</button><?php endif; ?>
                        <?php if ((int) $u['activo'] === 1): ?>
                            <button type="submit" name="accion" value="desactivar" class="boton-peligro">Desactivar</button>
                        <?php else: ?>
                            <button type="submit" name="accion" value="activar">Activar</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$lista): ?><tr><td colspan="6">Sin resultados.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <p>
        <?php if ($pagina > 1): ?><a class="boton-secundario" href="personal.php?<?= htmlspecialchars(http_build_query($filtros + ['p' => $pagina - 1])) ?>">← Anterior</a><?php endif; ?>
        <?php if ($pagina < $paginas): ?><a class="boton-secundario" href="personal.php?<?= htmlspecialchars(http_build_query($filtros + ['p' => $pagina + 1])) ?>">Siguiente →</a><?php endif; ?>
    </p>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
