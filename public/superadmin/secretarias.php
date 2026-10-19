<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/correo.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
requiereRol('superadmin'); // solo el equipo de Serena administra las secretarías

$flash = $_SESSION['flash_secretarias'] ?? null;
unset($_SESSION['flash_secretarias']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $sid = (int) ($_POST['secretaria_id'] ?? 0);
    $f = ['exito' => '', 'error' => '', 'credencial' => null];

    $existe = false;
    if ($sid > 0) {
        $q = $pdo->prepare('SELECT 1 FROM secretarias WHERE id = ?');
        $q->execute([$sid]);
        $existe = (bool) $q->fetchColumn();
    }

    if ($accion === 'crear') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 150) {
            $f['error'] = 'Escribe el nombre de la secretaría (máximo 150 caracteres).';
        } else {
            try {
                $pdo->prepare('INSERT INTO secretarias (nombre) VALUES (?)')->execute([$nombre]);
                auditarAccion($pdo, 'crear_secretaria', 'secretarias', (int) $pdo->lastInsertId(), [], ['nombre' => $nombre]);
                $f['exito'] = 'Secretaría creada. Ahora asígnale municipios o colegios y crea sus usuarios.';
            } catch (PDOException $e) {
                $f['error'] = 'Ya existe una secretaría con ese nombre.';
            }
        }
    } elseif (!$existe) {
        $f['error'] = 'Secretaría no válida.';
    } elseif ($accion === 'editar') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $activa = isset($_POST['activa']) ? 1 : 0;
        if ($nombre === '' || mb_strlen($nombre) > 150) {
            $f['error'] = 'Nombre no válido.';
        } else {
            try {
                $pdo->prepare('UPDATE secretarias SET nombre = ?, activa = ? WHERE id = ?')->execute([$nombre, $activa, $sid]);
                auditarAccion($pdo, 'editar_secretaria', 'secretarias', $sid, [], ['nombre' => $nombre, 'activa' => $activa]);
                $f['exito'] = 'Secretaría actualizada.' . ($activa ? '' : ' Sus usuarios no podrán iniciar sesión mientras esté inactiva.');
            } catch (PDOException $e) {
                $f['error'] = 'Ya existe otra secretaría con ese nombre.';
            }
        }
    } elseif ($accion === 'asignar_municipio') {
        $municipio = mb_substr(trim((string) ($_POST['municipio'] ?? '')), 0, 80);
        if ($municipio === '') {
            $f['error'] = 'Elige o escribe un municipio.';
        } else {
            $pdo->prepare('INSERT INTO secretaria_municipios (municipio, secretaria_id) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE secretaria_id = VALUES(secretaria_id)')->execute([$municipio, $sid]);
            $n = $pdo->prepare('UPDATE colegios SET secretaria_id = ? WHERE municipio = ?');
            $n->execute([$sid, $municipio]);
            auditarAccion($pdo, 'asignar_municipio_secretaria', 'secretarias', $sid, [], ['municipio' => $municipio, 'colegios' => $n->rowCount()]);
            $f['exito'] = "Municipio {$municipio} asignado: {$n->rowCount()} colegio(s) actualizados. Los colegios nuevos de ese municipio heredarán esta secretaría.";
        }
    } elseif ($accion === 'quitar_municipio') {
        $municipio = (string) ($_POST['municipio'] ?? '');
        $pdo->prepare('DELETE FROM secretaria_municipios WHERE municipio = ? AND secretaria_id = ?')->execute([$municipio, $sid]);
        $n = $pdo->prepare('UPDATE colegios SET secretaria_id = NULL WHERE municipio = ? AND secretaria_id = ?');
        $n->execute([$municipio, $sid]);
        auditarAccion($pdo, 'quitar_municipio_secretaria', 'secretarias', $sid, ['municipio' => $municipio], []);
        $f['exito'] = "Municipio {$municipio} desasignado ({$n->rowCount()} colegio(s) quedaron sin secretaría).";
    } elseif ($accion === 'sincronizar_colegios') {
        // Los marcados pasan a esta secretaría; los de esta secretaría que se desmarcan quedan sin asignar.
        $marcados = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['colegios'] ?? [])), fn($i) => $i > 0)));
        $pdo->prepare('UPDATE colegios SET secretaria_id = NULL WHERE secretaria_id = ?')->execute([$sid]);
        if ($marcados) {
            $in = implode(',', array_fill(0, count($marcados), '?'));
            $pdo->prepare("UPDATE colegios SET secretaria_id = ? WHERE id IN ($in)")->execute(array_merge([$sid], $marcados));
        }
        auditarAccion($pdo, 'asignar_colegios_secretaria', 'secretarias', $sid, [], ['colegios' => $marcados]);
        $f['exito'] = 'Colegios de la secretaría actualizados (' . count($marcados) . ' asignado(s)).';
    } elseif ($accion === 'crear_usuario') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? ''));
        if ($nombre === '' || !validarCorreo($correo)) {
            $f['error'] = 'Escribe un nombre y un correo válido.';
        } elseif (Usuario::correoExiste($pdo, $correo)) {
            $f['error'] = 'Ya existe una cuenta con ese correo.';
        } else {
            $rolId = (int) $pdo->query("SELECT id FROM roles WHERE nombre = 'secretaria'")->fetchColumn();
            if ($rolId === 0) {
                $f['error'] = "No existe el rol 'secretaria'. Ejecuta sql/migraciones/029_secretarias.sql.";
            } else {
                $temporal = generarPasswordTemporalValida();
                $pdo->prepare('INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, secretaria_id, consentimiento_datos, fecha_consentimiento, fecha_registro)
                               VALUES (?,?,?,?,NULL,?,1,NOW(),NOW())')
                    ->execute([$nombre, $correo, password_hash($temporal, PASSWORD_DEFAULT), $rolId, $sid]);
                $uid = (int) $pdo->lastInsertId();
                auditarAccion($pdo, 'crear_usuario_secretaria', 'usuarios', $uid, [], ['correo' => $correo, 'secretaria_id' => $sid]);
                $f = array_merge($f, enviarCredenciales($pdo, $nombre, $correo, $temporal, 'Tu cuenta de la Secretaría de Educación en Serena fue creada.'));
            }
        }
    } elseif (in_array($accion, ['restablecer_usuario', 'activar_usuario', 'desactivar_usuario'], true)) {
        $uid = (int) ($_POST['usuario_id'] ?? 0);
        $q = $pdo->prepare("SELECT u.id, u.nombre, u.correo FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                            WHERE u.id = ? AND u.secretaria_id = ? AND r.nombre = 'secretaria'");
        $q->execute([$uid, $sid]);
        $u = $q->fetch();
        if (!$u) {
            $f['error'] = 'Usuario no válido.';
        } elseif ($accion === 'restablecer_usuario') {
            $temporal = generarPasswordTemporalValida();
            $pdo->prepare('UPDATE usuarios SET password_hash = ?, intentos_login_fallidos = 0, login_bloqueado_hasta = NULL WHERE id = ?')
                ->execute([password_hash($temporal, PASSWORD_DEFAULT), $uid]);
            $pdo->prepare('UPDATE sesiones_activas SET revocada_en = NOW() WHERE usuario_id = ? AND revocada_en IS NULL')->execute([$uid]);
            auditarAccion($pdo, 'restablecer_password_secretaria', 'usuarios', $uid, [], ['correo' => $u['correo']]);
            $f = array_merge($f, enviarCredenciales($pdo, $u['nombre'], $u['correo'], $temporal, 'Se restableció tu acceso a Serena.'));
        } else {
            $nuevo = $accion === 'activar_usuario' ? 1 : 0;
            $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$nuevo, $uid]);
            if ($nuevo === 0) {
                $pdo->prepare('UPDATE sesiones_activas SET revocada_en = NOW() WHERE usuario_id = ? AND revocada_en IS NULL')->execute([$uid]);
            }
            auditarAccion($pdo, $nuevo ? 'activar_usuario_secretaria' : 'desactivar_usuario_secretaria', 'usuarios', $uid, [], ['activo' => $nuevo]);
            $f['exito'] = $nuevo ? 'Usuario activado.' : 'Usuario desactivado y sesiones cerradas.';
        }
    }
    $_SESSION['flash_secretarias'] = $f;
    header('Location: secretarias.php' . ($sid > 0 ? '#sec-' . $sid : ''));
    exit;
}

/** Envía la contraseña temporal por correo; si falla, la devuelve para mostrarla UNA vez. */
function enviarCredenciales(PDO $pdo, string $nombre, string $correo, string $temporal, string $intro): array
{
    $enlace = urlAbsoluta('/login.php');
    $texto = "Hola {$nombre},\n\n{$intro}\n\nCorreo: {$correo}\nContraseña temporal: {$temporal}\n\nCámbiala apenas inicies sesión.\n\n— Serena";
    $html = plantillaCorreoHtml('Tu acceso a Serena',
        '<p>Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p><p>' . htmlspecialchars($intro) . '</p>'
        . '<p><strong>Correo:</strong> ' . htmlspecialchars($correo) . '<br><strong>Contraseña temporal:</strong> <code>' . htmlspecialchars($temporal) . '</code></p>'
        . botonCorreoHtml('Iniciar sesión', $enlace)
        . '<p class="sa-nota">Cámbiala apenas inicies sesión.</p>');
    if (smtpConfigurado() && enviarCorreo($correo, 'Tu acceso a Serena', $texto, $html)) {
        return ['exito' => "Listo: la contraseña temporal se envió a {$correo}.", 'credencial' => null];
    }
    return ['exito' => 'El correo no se pudo enviar. Entrega esta contraseña por otro canal (no se vuelve a mostrar):',
            'credencial' => ['correo' => $correo, 'password' => $temporal]];
}

// ---- Datos para la vista
$secretarias = $pdo->query(
    "SELECT s.*, (SELECT COUNT(*) FROM colegios c WHERE c.secretaria_id = s.id) AS n_colegios
     FROM secretarias s ORDER BY s.nombre"
)->fetchAll();
$municipiosPorSec = [];
foreach ($pdo->query('SELECT municipio, secretaria_id FROM secretaria_municipios ORDER BY municipio')->fetchAll() as $m) {
    $municipiosPorSec[(int) $m['secretaria_id']][] = $m['municipio'];
}
$usuariosPorSec = [];
foreach ($pdo->query("SELECT u.id, u.nombre, u.correo, u.activo, u.ultimo_acceso, u.secretaria_id FROM usuarios u
                      INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'secretaria' ORDER BY u.nombre")->fetchAll() as $u) {
    $usuariosPorSec[(int) $u['secretaria_id']][] = $u;
}
$colegios = $pdo->query('SELECT c.id, c.nombre, c.municipio, c.secretaria_id,
        (SELECT s.nombre FROM secretarias s WHERE s.id = c.secretaria_id) AS secretaria_nombre
        FROM colegios c ORDER BY c.nombre')->fetchAll();
$sinAsignar = count(array_filter($colegios, fn($c) => $c['secretaria_id'] === null));
$municipiosDisponibles = $pdo->query("SELECT DISTINCT municipio FROM colegios WHERE municipio IS NOT NULL AND municipio <> '' ORDER BY municipio")->fetchAll(PDO::FETCH_COLUMN);

$tituloPagina = 'Secretarías de Educación';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Secretarías de Educación</h1>
<p class="texto-suave">Cada secretaría ve y gestiona solo los colegios que le asignes, por municipio o uno a uno. El superadmin (equipo de Serena) ve todo.</p>

<?php if (!empty($flash['error'])): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($flash['error']) ?></p><?php endif; ?>
<?php if (!empty($flash['exito'])): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($flash['exito']) ?></p><?php endif; ?>
<?php if (!empty($flash['credencial'])): ?>
    <p class="mensaje mensaje-info">Correo: <strong><?= htmlspecialchars($flash['credencial']['correo']) ?></strong><br>
    Contraseña temporal: <code><?= htmlspecialchars($flash['credencial']['password']) ?></code></p>
<?php endif; ?>
<?php if ($sinAsignar > 0): ?>
    <p class="mensaje mensaje-aviso"><?= $sinAsignar ?> colegio(s) sin secretaría: solo tú los ves hasta que los asignes.</p>
<?php endif; ?>

<section class="panel-seccion">
    <h2>Nueva secretaría</h2>
    <form action="secretarias.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="crear">
        <label>Nombre (ej. Secretaría de Educación de Medellín) <input type="text" name="nombre" maxlength="150" required></label>
        <button type="submit">Crear secretaría</button>
    </form>
</section>

<?php foreach ($secretarias as $s): $id = (int) $s['id']; ?>
<section class="panel-seccion" id="sec-<?= $id ?>">
    <h2>🏛️ <?= htmlspecialchars($s['nombre']) ?>
        <span class="sa-chip <?= (int) $s['activa'] === 1 ? 'ok' : 'off' ?>"><?= (int) $s['activa'] === 1 ? 'Activa' : 'Inactiva' ?></span></h2>
    <p class="texto-suave">
        <?= (int) $s['n_colegios'] ?> colegio(s) ·
        Municipios: <?= !empty($municipiosPorSec[$id]) ? htmlspecialchars(implode(', ', $municipiosPorSec[$id])) : 'ninguno asignado' ?> ·
        <?= count($usuariosPorSec[$id] ?? []) ?> usuario(s)
    </p>

    <details>
        <summary>Municipios asignados</summary>
        <?php foreach ($municipiosPorSec[$id] ?? [] as $m): ?>
            <form action="secretarias.php" method="POST" class="sa-en-linea" onsubmit="return confirm('¿Quitar este municipio? Sus colegios quedarán sin secretaría.');">
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="quitar_municipio">
                <input type="hidden" name="secretaria_id" value="<?= $id ?>">
                <input type="hidden" name="municipio" value="<?= htmlspecialchars($m) ?>">
                <span class="sa-chip warn"><?= htmlspecialchars($m) ?></span> <button type="submit" class="boton-secundario">Quitar</button>
            </form><br>
        <?php endforeach; ?>
        <form action="secretarias.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="asignar_municipio">
            <input type="hidden" name="secretaria_id" value="<?= $id ?>">
            <label>Asignar municipio (se asignan todos sus colegios; los nuevos lo heredan)
                <input type="text" name="municipio" list="municipios-lista" maxlength="80" placeholder="Escribe o elige un municipio" required>
            </label>
            <button type="submit">Asignar municipio</button>
        </form>
    </details>

    <details>
        <summary>Colegios de esta secretaría (marca los que le pertenecen)</summary>
        <form action="secretarias.php" method="POST" novalidate onsubmit="return confirm('¿Guardar esta asignación de colegios?');">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="sincronizar_colegios">
            <input type="hidden" name="secretaria_id" value="<?= $id ?>">
            <div class="sa-checks">
                <?php foreach ($colegios as $c):
                    $propio = (int) $c['secretaria_id'] === $id; ?>
                    <label>
                        <input type="checkbox" name="colegios[]" value="<?= (int) $c['id'] ?>" <?= $propio ? 'checked' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                        <span class="texto-suave">· <?= htmlspecialchars((string) ($c['municipio'] ?? '—')) ?>
                        <?php if (!$propio && $c['secretaria_id'] !== null): ?>· hoy en: <?= htmlspecialchars((string) $c['secretaria_nombre']) ?><?php endif; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit">Guardar colegios</button>
        </form>
    </details>

    <details>
        <summary>Usuarios de la secretaría</summary>
        <?php if (!empty($usuariosPorSec[$id])): ?>
        <table class="tabla-datos">
            <thead><tr><th>Nombre</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($usuariosPorSec[$id] as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nombre']) ?><br><span class="texto-suave"><?= htmlspecialchars($u['correo']) ?></span></td>
                    <td><?= (int) $u['activo'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                    <td><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?></td>
                    <td>
                        <form action="secretarias.php" method="POST" class="sa-en-linea" onsubmit="return confirm('¿Seguro? Queda en la auditoría.');">
                            <?php campoCSRF(); ?>
                            <input type="hidden" name="secretaria_id" value="<?= $id ?>">
                            <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" name="accion" value="restablecer_usuario">Restablecer contraseña</button>
                            <?php if ((int) $u['activo'] === 1): ?>
                                <button type="submit" name="accion" value="desactivar_usuario" class="boton-peligro">Desactivar</button>
                            <?php else: ?>
                                <button type="submit" name="accion" value="activar_usuario">Activar</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p class="texto-suave">Aún no tiene usuarios.</p><?php endif; ?>
        <form action="secretarias.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="crear_usuario">
            <input type="hidden" name="secretaria_id" value="<?= $id ?>">
            <label>Nombre <input type="text" name="nombre" maxlength="100" required></label>
            <label>Correo <input type="email" name="correo" required></label>
            <button type="submit">Crear usuario (se envía la contraseña por correo)</button>
        </form>
    </details>

    <details>
        <summary>Editar o suspender secretaría</summary>
        <form action="secretarias.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="secretaria_id" value="<?= $id ?>">
            <label>Nombre <input type="text" name="nombre" maxlength="150" value="<?= htmlspecialchars($s['nombre']) ?>" required></label>
            <label><input type="checkbox" name="activa" <?= (int) $s['activa'] === 1 ? 'checked' : '' ?>> Activa (si se desmarca, sus usuarios no pueden iniciar sesión)</label>
            <button type="submit">Guardar</button>
        </form>
    </details>
</section>
<?php endforeach; ?>
<?php if (!$secretarias): ?><p class="mensaje mensaje-info">Todavía no hay secretarías. Crea la primera arriba.</p><?php endif; ?>

<datalist id="municipios-lista">
    <?php foreach ($municipiosDisponibles as $m): ?><option value="<?= htmlspecialchars($m) ?>"><?php endforeach; ?>
</datalist>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
