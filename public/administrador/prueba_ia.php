<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/ia.php';

requiereRol('superadmin');

$clave = iaClave();
$traza = null;
$respuesta = null;
$hizoPrueba = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'refrescar') {
        iaModelosGratis(true);
        iaLimpiarMalos();
        iaQuitarPausa();
    } elseif ($accion === 'probar' && $clave !== '') {
        iaQuitarPausa();
        $hizoPrueba = true;
        $respuesta = iaLlamar(
            'Responde con una sola oración corta en español que confirme que funcionas, terminada en punto.',
            ['tarea' => 'prueba', 'max_tokens' => 120],
            $traza
        );
    }
}

$modelos = $clave !== '' ? iaModelosGratis() : [];

$bitacora = [];
try {
    $bitacora = $pdo->query('SELECT fecha_hora, tarea, modelo, ok, http_codigo, ms, error FROM ia_registro ORDER BY id DESC LIMIT 20')->fetchAll();
} catch (Throwable $e) {
    $bitacora = null; // la tabla aún no existe (falta la migración 042)
}

$tituloPagina = 'Diagnóstico de IA';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Diagnóstico del ecosistema IA (OpenRouter)</h1>

<section class="panel-seccion">
    <p><strong>Clave detectada:</strong>
        <?= $clave !== '' ? '✅ Sí (' . htmlspecialchars(substr($clave, 0, 8)) . '…' . htmlspecialchars(substr($clave, -4)) . ')' : '❌ No — agrega OPENROUTER_API_KEY en src/config/.env' ?>
    </p>
    <p><strong>Privacidad:</strong>
        <?= iaPrivacidad() === 'deny'
            ? '🔒 Solo proveedores que NO guardan ni entrenan con los datos (OPENROUTER_DATA_COLLECTION=deny)'
            : '⚠️ Se permiten proveedores que pueden guardar/entrenar con los datos (allow)' ?>
    </p>
    <?php if (iaPrivacidad('analisis') !== iaPrivacidad()): ?>
        <p><strong>Privacidad de los análisis (cifras agregadas, sin nombres):</strong> <?= iaPrivacidad('analisis') === 'allow' ? '⚠️ allow' : '🔒 deny' ?></p>
    <?php endif; ?>
    <p><strong>Modelos saltados por fallos recientes:</strong> <?= count(iaModelosMalos()) ?></p>
    <p><strong>Estado:</strong> <?= iaPausaActiva() ? '⏸️ En pausa tras fallos recientes (se reactiva sola en unos minutos)' : '▶️ Activo' ?></p>

    <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px">
        <?php campoCSRF(); ?>
        <button type="submit" name="accion" value="probar">Hacer una llamada de prueba ahora</button>
        <button type="submit" name="accion" value="refrescar">Refrescar lista de modelos gratis</button>
    </form>

    <?php if ($hizoPrueba): ?>
        <div class="mensaje <?= $respuesta !== null ? 'mensaje-exito' : 'mensaje-error' ?>" style="margin-top:16px">
            <p><strong>Resultado:</strong> <?= $respuesta !== null ? htmlspecialchars($respuesta) : 'ningún modelo respondió bien (revisa la tabla de intentos)' ?></p>
            <table class="tabla-datos tabla-adaptable" style="margin-top:8px">
                <tr><th>Modelo</th><th>HTTP</th><th>ms</th><th>Detalle</th></tr>
                <?php foreach ($traza as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $t['modelo']) ?></td>
                        <td><?= (int) $t['http'] ?></td>
                        <td><?= (int) $t['ms'] ?></td>
                        <td><?= htmlspecialchars((string) ($t['error'] !== '' ? $t['error'] : 'OK')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel-seccion">
    <h2>Modelos gratis en uso (en orden de intento)</h2>
    <?php if (empty($modelos)): ?>
        <p class="texto-suave">Sin clave configurada no se consultan modelos.</p>
    <?php else: ?>
        <ol class="lista-simple"><?php foreach ($modelos as $m): ?><li><code><?= htmlspecialchars($m) ?></code></li><?php endforeach; ?></ol>
    <?php endif; ?>
</section>

<section class="panel-seccion">
    <h2>Últimas 20 llamadas</h2>
    <?php if ($bitacora === null): ?>
        <p class="texto-suave">Aplica <code>sql/migraciones/042_ia_registro.sql</code> en phpMyAdmin para activar la bitácora.</p>
    <?php elseif (empty($bitacora)): ?>
        <p class="texto-suave">Todavía no hay llamadas registradas.</p>
    <?php else: ?>
        <table class="tabla-datos tabla-adaptable">
            <tr><th>Fecha</th><th>Tarea</th><th>Modelo</th><th>OK</th><th>HTTP</th><th>ms</th><th>Error</th></tr>
            <?php foreach ($bitacora as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['fecha_hora']) ?></td>
                    <td><?= htmlspecialchars($f['tarea']) ?></td>
                    <td><?= htmlspecialchars($f['modelo']) ?></td>
                    <td><?= $f['ok'] ? '✅' : '❌' ?></td>
                    <td><?= (int) $f['http_codigo'] ?></td>
                    <td><?= (int) $f['ms'] ?></td>
                    <td><?= htmlspecialchars($f['error']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</section>

<section class="panel-seccion">
    <h2>Cómo leer el resultado</h2>
    <ul class="lista-simple">
        <li><strong>HTTP 401/403</strong>: la clave es inválida o fue revocada. Créala en openrouter.ai/keys.</li>
        <li><strong>HTTP 429</strong>: límite de los modelos gratis (≈20 peticiones/min y un tope diario). Serena prueba el siguiente modelo y, si todos fallan, muestra el texto normal sin IA y reintenta en 5 min.</li>
        <li><strong>HTTP 404 "No endpoints found"</strong>: ese modelo no tiene proveedor que cumpla la política de privacidad <code>deny</code>. Se salta al siguiente.</li>
        <li><strong>Error de conexión / "Could not resolve host"</strong>: el hosting bloquea salidas HTTPS hacia openrouter.ai.</li>
        <li><strong>"texto sin cierre de oración" o "cortada por límite"</strong>: se descarta para no mostrar frases a medias.</li>
    </ul>
</section>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
