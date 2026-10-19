<?php
declare(strict_types=1);
set_time_limit(0);

$esCLI = php_sapi_name() === 'cli';

require __DIR__ . '/../../src/config/conexion.php';
if (!$esCLI) {
    require_once __DIR__ . '/../../src/helpers/auth.php';
    require_once __DIR__ . '/../../src/helpers/csrf.php';
    requiereRol('superadmin');
}

const DOMINIO_DEMO = 'demo-institucion-2026.test';

$resultado = null;
$error = null;

if ($esCLI || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!$esCLI) verificarTokenCSRF($_POST['csrf_token'] ?? '');

    try {
        $pdo->beginTransaction();

        // El orden importa: primero los usuarios (arrastra en cascada casi todo lo
        // demás por las FOREIGN KEY ON DELETE CASCADE del esquema — estado_emocional,
        // sueno, diario, habitos+seguimiento, metas+subtareas, notificaciones,
        // alertas_tempranas, citas, personal_grupos, notas_sesion, etc.), y solo
        // al final los grupos y grados, que no dependen de ningún usuario para existir.
        $totalUsuarios = (int) $pdo->query(
            "SELECT COUNT(*) FROM usuarios WHERE correo LIKE '%@" . DOMINIO_DEMO . "'"
        )->fetchColumn();
        $pdo->exec("DELETE FROM usuarios WHERE correo LIKE '%@" . DOMINIO_DEMO . "'");

        $totalGrupos = (int) $pdo->query(
            "SELECT COUNT(*) FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE gr.nombre LIKE '%(Demo)'"
        )->fetchColumn();
        $pdo->exec("DELETE g FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE gr.nombre LIKE '%(Demo)'");

        $totalGrados = (int) $pdo->query("SELECT COUNT(*) FROM grados WHERE nombre LIKE '%(Demo)'")->fetchColumn();
        $pdo->exec("DELETE FROM grados WHERE nombre LIKE '%(Demo)'");
        // El colegio demo ya quedó sin usuarios ni grados: se borra también.
        $pdo->exec("DELETE FROM colegios WHERE nombre = 'Colegio Demo (prueba)' AND NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.colegio_id = colegios.id)");

        $pdo->commit();
        $resultado = ['usuarios_eliminados' => $totalUsuarios, 'grupos_eliminados' => $totalGrupos, 'grados_eliminados' => $totalGrados];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

if ($esCLI) {
    if ($error) { fwrite(STDERR, "Error: {$error}\n"); exit(1); }
    echo "Listo. " . json_encode($resultado, JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

$totalActual = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE correo LIKE '%@" . DOMINIO_DEMO . "'")->fetchColumn();

$tituloPagina = 'Eliminar datos de prueba';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
    <h1>Eliminar datos de prueba</h1>
    <p class="texto-suave">Borra por completo la institución de prueba (todo lo que termina en @<?= DOMINIO_DEMO ?>) y deja la base de datos exactamente como estaba antes.</p>
</div>

<?php if ($error): ?><p class="mensaje mensaje-error">Ocurrió un error y no se borró nada (se revirtió todo): <?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($resultado): ?>
    <p class="mensaje mensaje-exito">
        ✅ Eliminados: <?= $resultado['usuarios_eliminados'] ?> usuarios, <?= $resultado['grupos_eliminados'] ?> grupos, <?= $resultado['grados_eliminados'] ?> grados.
        La base de datos ya no tiene ningún dato de prueba.
    </p>
<?php endif; ?>

<section class="panel-seccion">
    <?php if ($totalActual > 0): ?>
        <p>Hay <strong><?= $totalActual ?></strong> usuarios de prueba todavía en la base de datos.</p>
        <button type="button" class="boton-peligro" data-abrir-modal="modal-eliminar">🗑️ Eliminar todos los datos de prueba</button>
    <?php else: ?>
        <p class="mensaje mensaje-info">No hay datos de prueba en este momento — no hay nada que eliminar.</p>
    <?php endif; ?>
</section>

<div class="modal-fondo" id="modal-eliminar">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-cabecera">
            <h2>¿Eliminar todos los datos de prueba?</h2>
            <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">✕</button>
        </div>
        <p>Esto borra permanentemente toda la institución de prueba generada (usuarios, grupos, grados y toda su actividad). No afecta ningún dato real — solo lo que termina en <code>@<?= DOMINIO_DEMO ?></code>. No se puede deshacer.</p>
        <div class="modal-acciones">
            <button type="button" class="boton-secundario" data-cerrar-modal>Cancelar</button>
            <form action="eliminar_datos_prueba.php" method="POST" style="margin:0">
                <?php campoCSRF(); ?>
                <button type="submit" class="boton-peligro">Sí, eliminar todo</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
