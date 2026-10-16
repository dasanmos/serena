<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/correo.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/Colegio.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

// Los comunicados van a administradores y rectores ACTIVOS de colegios ACTIVOS.
// El envío es por lotes: el hosting corta los scripts largos, así que cada clic
// manda hasta TAM_LOTE correos y el avance queda guardado en comunicado_envios.
const TAM_LOTE = 15;

$flash = $_SESSION['flash_comunicados'] ?? null;
unset($_SESSION['flash_comunicados']);
$verId = (int) ($_GET['id'] ?? 0);
// Una secretaría solo ve y envía SUS comunicados; el superadmin ve todos.
$soloMios = esSecretaria() ? ' AND creado_por = ' . (int) $_SESSION['usuario_id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    $destino = 'comunicados.php';

    if ($accion === 'crear') {
        $asunto = trim((string) ($_POST['asunto'] ?? ''));
        $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
        $alcance = ($_POST['alcance'] ?? 'todos') === 'seleccion' ? 'seleccion' : 'todos';
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['colegios'] ?? [])), fn($i) => $i > 0)));
        if ($asunto === '' || mb_strlen($asunto) > 150 || $mensaje === '' || mb_strlen($mensaje) > 5000) {
            $f['error'] = 'Escribe un asunto (máx. 150) y un mensaje (máx. 5000 caracteres).';
        } elseif ($alcance === 'seleccion' && !$ids) {
            $f['error'] = 'Marca al menos un colegio o elige "Todos los colegios".';
        } else {
            $sql = "SELECT u.id, u.correo, u.colegio_id FROM usuarios u
                    INNER JOIN roles r ON r.id = u.rol_id INNER JOIN colegios c ON c.id = u.colegio_id
                    WHERE r.nombre IN ('administrador','rector') AND u.activo = 1 AND c.activo = 1"
                    . sqlAlcanceColegios($pdo, 'u.colegio_id');
            $params = [];
            if ($alcance === 'seleccion') {
                $sql .= ' AND u.colegio_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = $ids;
            }
            $q = $pdo->prepare($sql);
            $q->execute($params);
            $dest = $q->fetchAll();
            if (!$dest) {
                $f['error'] = 'No hay administradores ni rectores activos en ese alcance.';
            } else {
                $pdo->prepare('INSERT INTO comunicados (asunto, mensaje, alcance, creado_por) VALUES (?,?,?,?)')
                    ->execute([$asunto, $mensaje, $alcance, (int) $_SESSION['usuario_id']]);
                $cid = (int) $pdo->lastInsertId();
                $ins = $pdo->prepare('INSERT INTO comunicado_envios (comunicado_id, usuario_id, colegio_id, correo) VALUES (?,?,?,?)');
                foreach ($dest as $d) { $ins->execute([$cid, $d['id'], $d['colegio_id'], $d['correo']]); }
                auditarAccion($pdo, 'crear_comunicado', 'comunicados', $cid, [], ['asunto' => $asunto, 'destinatarios' => count($dest)]);
                $f['exito'] = 'Comunicado creado con ' . count($dest) . ' destinatario(s). Falta enviarlo: pulsa "Enviar siguiente lote".';
                $destino = 'comunicados.php?id=' . $cid;
            }
        }
    } elseif ($accion === 'enviar_lote' || $accion === 'reintentar') {
        $cid = (int) ($_POST['comunicado_id'] ?? 0);
        $destino = 'comunicados.php?id=' . $cid;
        $com = $pdo->prepare('SELECT * FROM comunicados WHERE id = ?' . $soloMios);
        $com->execute([$cid]);
        $com = $com->fetch();
        if (!$com) {
            $f['error'] = 'Comunicado no encontrado.';
        } elseif (!smtpConfigurado()) {
            $f['error'] = 'El correo no está configurado (BREVO_API_KEY / SMTP_DESDE en src/config/.env).';
        } else {
            if ($accion === 'reintentar') {
                $pdo->prepare("UPDATE comunicado_envios SET estado = 'pendiente' WHERE comunicado_id = ? AND estado = 'fallido'")->execute([$cid]);
            }
            $lote = $pdo->prepare("SELECT id, correo FROM comunicado_envios WHERE comunicado_id = ? AND estado = 'pendiente' ORDER BY id LIMIT " . TAM_LOTE);
            $lote->execute([$cid]);
            $html = plantillaCorreoHtml($com['asunto'], '<p>' . nl2br(htmlspecialchars($com['mensaje'])) . '</p>');
            $ok = 0; $mal = 0;
            $marca = $pdo->prepare('UPDATE comunicado_envios SET estado = ?, enviado_en = NOW() WHERE id = ?');
            foreach ($lote->fetchAll() as $e) {
                $enviado = enviarCorreo($e['correo'], $com['asunto'], $com['mensaje'], $html);
                $marca->execute([$enviado ? 'enviado' : 'fallido', $e['id']]);
                $enviado ? $ok++ : $mal++;
            }
            auditarAccion($pdo, 'enviar_lote_comunicado', 'comunicados', $cid, [], ['enviados' => $ok, 'fallidos' => $mal]);
            $f['exito'] = "Lote procesado: {$ok} enviado(s), {$mal} fallido(s).";
        }
    }
    $_SESSION['flash_comunicados'] = $f;
    header('Location: ' . $destino);
    exit;
}

$colegios = Colegio::listarTodos($pdo, true);
$detalle = null;
if ($verId > 0) {
    $d = $pdo->prepare('SELECT * FROM comunicados WHERE id = ?' . $soloMios);
    $d->execute([$verId]);
    $detalle = $d->fetch() ?: null;
}
$historial = $pdo->query(
    "SELECT m.id, m.asunto, m.creado_en,
            COUNT(e.id) AS total,
            COALESCE(SUM(e.estado = 'enviado'), 0) AS enviados,
            COALESCE(SUM(e.estado = 'fallido'), 0) AS fallidos,
            COALESCE(SUM(e.estado = 'pendiente'), 0) AS pendientes
     FROM comunicados m LEFT JOIN comunicado_envios e ON e.comunicado_id = m.id
     WHERE 1 = 1" . str_replace('creado_por', 'm.creado_por', $soloMios) . "
     GROUP BY m.id ORDER BY m.id DESC LIMIT 50"
)->fetchAll();

$tituloPagina = 'Comunicados';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Comunicados a colegios</h1>
<?php if (!empty($flash['error'])): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($flash['error']) ?></p><?php endif; ?>
<?php if (!empty($flash['exito'])): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($flash['exito']) ?></p><?php endif; ?>

<?php if ($detalle):
    $res = $pdo->prepare("SELECT e.correo, e.estado, e.enviado_en, c.nombre AS colegio
                          FROM comunicado_envios e LEFT JOIN colegios c ON c.id = e.colegio_id
                          WHERE e.comunicado_id = ? ORDER BY e.estado, c.nombre, e.correo");
    $res->execute([$verId]);
    $envios = $res->fetchAll();
    $pend = count(array_filter($envios, fn($e) => $e['estado'] === 'pendiente'));
    $fall = count(array_filter($envios, fn($e) => $e['estado'] === 'fallido'));
?>
<section class="panel-seccion">
    <h2><?= htmlspecialchars($detalle['asunto']) ?></h2>
    <p class="sa-mensaje-texto"><?= htmlspecialchars($detalle['mensaje']) ?></p>
    <p class="texto-suave"><?= count($envios) ?> destinatario(s) · <?= count($envios) - $pend - $fall ?> enviado(s) · <?= $pend ?> pendiente(s) · <?= $fall ?> fallido(s)</p>
    <form action="comunicados.php" method="POST">
        <?php campoCSRF(); ?>
        <input type="hidden" name="comunicado_id" value="<?= (int) $verId ?>">
        <?php if ($pend > 0): ?><button type="submit" name="accion" value="enviar_lote">Enviar siguiente lote (hasta <?= TAM_LOTE ?>)</button><?php endif; ?>
        <?php if ($fall > 0): ?><button type="submit" name="accion" value="reintentar" class="boton-secundario">Reintentar fallidos</button><?php endif; ?>
        <a class="boton-secundario" href="comunicados.php">Volver</a>
    </form>
    <table class="tabla-datos">
        <thead><tr><th>Colegio</th><th>Correo</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($envios as $e): ?>
            <tr><td><?= htmlspecialchars((string) $e['colegio']) ?></td><td><?= htmlspecialchars($e['correo']) ?></td><td><?= htmlspecialchars($e['estado']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php else: ?>
<section class="panel-seccion">
    <h2>Nuevo comunicado</h2>
    <p class="texto-suave">Se envía por correo a los administradores y rectores activos de los colegios activos.</p>
    <form action="comunicados.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="crear">
        <label>Asunto <input type="text" name="asunto" maxlength="150" required></label>
        <label>Mensaje <textarea name="mensaje" rows="8" maxlength="5000" required></textarea></label>
        <label><input type="radio" name="alcance" value="todos" checked> Todos los colegios</label>
        <label><input type="radio" name="alcance" value="seleccion"> Solo los colegios marcados:</label>
        <div class="sa-checks">
            <?php foreach ($colegios as $c): ?>
                <label><input type="checkbox" name="colegios[]" value="<?= (int) $c['id'] ?>"> <?= htmlspecialchars($c['nombre']) ?></label>
            <?php endforeach; ?>
        </div>
        <button type="submit">Crear comunicado</button>
    </form>
</section>
<section class="panel-seccion">
    <h2>Historial</h2>
    <table class="tabla-datos">
        <thead><tr><th>Fecha</th><th>Asunto</th><th>Enviados</th><th>Pendientes</th><th>Fallidos</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($historial as $h): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($h['creado_en'])) ?></td>
                <td><?= htmlspecialchars($h['asunto']) ?></td>
                <td><?= (int) $h['enviados'] ?> / <?= (int) $h['total'] ?></td>
                <td><?= (int) $h['pendientes'] ?></td>
                <td><?= (int) $h['fallidos'] ?></td>
                <td><a href="comunicados.php?id=<?= (int) $h['id'] ?>">Abrir</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$historial): ?><tr><td colspan="6">Aún no hay comunicados.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
