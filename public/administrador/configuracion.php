<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/respaldo.php';
require_once __DIR__ . '/../../src/helpers/configuracion.php';
require_once __DIR__ . '/../../src/helpers/correo.php';
require_once __DIR__ . '/../../src/helpers/resumen_mensual.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
requiereRol('administrador', 'superadmin');

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_respaldo') {
        if (!esSuperadmin()) { http_response_code(403); exit('Solo el superadmin puede crear respaldos.'); }
        $resultado = crearRespaldoSeguro($pdo, $_SESSION['nombre']);
        if ($resultado['exito']) {
            auditarAccion($pdo, 'crear_respaldo_guardado', 'sistema', 0);
            $mensaje = $resultado['mensaje'];
        } else {
            $error = $resultado['mensaje'];
        }
    } elseif ($accion === 'guardar_correo') {
        // Texto plano (se escapa al enviarlo). Vacío = vuelve al texto original.
        configuracionGuardar($pdo, 'correo_lema', mb_substr(trim((string) ($_POST['correo_lema'] ?? '')), 0, 80));
        configuracionGuardar($pdo, 'correo_pie', mb_substr(trim((string) ($_POST['correo_pie'] ?? '')), 0, 400));
        auditarAccion($pdo, 'editar_config_correo', 'configuracion', 0);
        $mensaje = 'Textos de los correos actualizados.';
    } elseif ($accion === 'probar_correo') {
        if (!smtpConfigurado()) {
            $error = 'El envío de correo no está configurado en el servidor (falta BREVO_API_KEY).';
        } else {
            $yo = Usuario::buscarPorId($pdo, (int) $_SESSION['usuario_id']);
            $ok = enviarCorreo(
                (string) ($yo['correo'] ?? ''),
                'Correo de prueba de Serena',
                'Este es un correo de prueba de Serena.',
                plantillaCorreoHtml('Correo de prueba', '<p>Así se ven los correos de Serena con los textos que configuraste.</p>')
            );
            if ($ok) { $mensaje = 'Correo de prueba enviado a tu correo.'; } else { $error = 'No se pudo enviar el correo de prueba. Revisa la configuración de Brevo.'; }
        }
    } elseif ($accion === 'probar_resumen_mensual') {
        // Prueba manual: solo a tu correo, del mes anterior, sin afectar el envío real
        // del día 1 (esa es enviarResumenMensualSiToca(), con su propio control en
        // la tabla `configuracion` para no enviar dos veces el mismo mes).
        if (!smtpConfigurado()) {
            $error = 'El envío de correo no está configurado en el servidor (falta BREVO_API_KEY).';
        } else {
            $yo = Usuario::buscarPorId($pdo, (int) $_SESSION['usuario_id']);
            $inicio = date('Y-m-01', strtotime('first day of last month'));
            $fin = date('Y-m-t', strtotime('last day of last month'));
            $nombreMes = nombreMesEnEspanol((int) date('n', strtotime($inicio))) . ' de ' . date('Y', strtotime($inicio));
            $resumen = calcularResumenMensual($pdo, $inicio, $fin);
            $ok = enviarCorreo(
                (string) ($yo['correo'] ?? ''),
                "[Prueba] Resumen mensual de Serena — {$nombreMes}",
                resumenMensualTextoPlano($resumen),
                resumenMensualHtml($nombreMes, $resumen)
            );
            if ($ok) { $mensaje = 'Resumen mensual de prueba enviado a tu correo (datos de ' . $nombreMes . ').'; } else { $error = 'No se pudo enviar el resumen de prueba.'; }
        }
    }
}

$respaldos = listarRespaldos();
$respaldo = $respaldos[0] ?? null;

$tituloPagina = 'Configuración';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
    <h1>Configuración</h1>
    <?php if (esSuperadmin()): ?>
    <p class="texto-suave">Ajustes generales de la plataforma y copias de seguridad.</p>
    <?php else: ?>
    <p class="texto-suave">Ajustes de tu colegio. Las copias de seguridad y los ajustes del servidor los maneja quien administra la plataforma.</p>
    <?php endif; ?>
</div>

<?php if ($mensaje): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<?php if (esSuperadmin()): ?>
<section class="panel-seccion">
    <h2>Copia de seguridad</h2>
    <p class="texto-suave">
        Serena guarda las <strong>últimas <?= RESPALDOS_A_CONSERVAR ?> copias</strong> en el servidor. Al crear una
        nueva, primero se genera y se confirma que quedó completa en disco — solo entonces se
        eliminan las más antiguas que sobren. Si algo falla a mitad de camino, las anteriores quedan intactas.
    </p>

    <?php if ($respaldos): ?>
        <ul class="lista-respaldos">
            <?php foreach ($respaldos as $i => $r): ?>
            <li class="respaldo-info">
                <div>
                    <strong><?= $i === 0 ? 'Más reciente' : 'Copia anterior' ?></strong>
                    <p class="texto-suave">
                        <?= date('d/m/Y H:i', $r['fecha']) ?>
                        · <?= number_format($r['tamano'] / 1024, 0) ?> KB
                    </p>
                </div>
                <a href="<?= BASE_URL ?>/superadmin/respaldo_descargar.php?archivo=<?= urlencode($r['nombre']) ?>" class="boton-secundario">⬇️ Descargar</a>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="mensaje mensaje-info">Todavía no se ha creado ninguna copia de seguridad guardada.</p>
    <?php endif; ?>

    <button type="button" class="boton-acceso" data-abrir-modal="modal-respaldo" style="margin-top:var(--espacio-md)">
        🗄️ Crear nueva copia de seguridad
    </button>

    <p class="texto-suave" style="margin-top:var(--espacio-md)">
        ¿Prefieres descargar un archivo sin guardar nada en el servidor?
        <a href="<?= BASE_URL ?>/superadmin/respaldo.php">Descarga directa aquí</a>.
    </p>
</section>
<?php endif; ?>

<section class="panel-seccion">
    <h2>Correos de Serena</h2>
    <p class="texto-suave">
        <?php if (esSuperadmin()): ?>
        Personaliza el lema (bajo el logo) y el pie de los correos. Los textos se guardan para el colegio elegido en la barra
        superior; sin colegio elegido quedan como ajuste general de la plataforma.
        <?php else: ?>
        Personaliza el lema (bajo el logo) y el pie de los correos que envía la plataforma a tu colegio.
        <?php endif; ?>
        Déjalos vacíos para volver al texto original. El contenido de cada correo (por ejemplo, el
        código de verificación o el enlace de recuperación) no se cambia desde aquí.
    </p>
    <form action="configuracion.php" method="POST">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="guardar_correo">
        <label for="correo-lema">Lema</label>
        <input type="text" id="correo-lema" name="correo_lema" maxlength="80"
               value="<?= htmlspecialchars(configuracionObtener($pdo, 'correo_lema', '')) ?>"
               placeholder="Cuerpo • Mente • Bienestar">
        <label for="correo-pie">Pie del correo</label>
        <textarea id="correo-pie" name="correo_pie" rows="3" maxlength="400"
                  placeholder="Este correo fue enviado por Serena, plataforma de bienestar emocional escolar."><?= htmlspecialchars(configuracionObtener($pdo, 'correo_pie', '')) ?></textarea>
        <button type="submit" class="boton-acceso u-mt-sm">Guardar textos</button>
    </form>
    <form action="configuracion.php" method="POST" class="u-mt-sm">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="probar_correo">
        <button type="submit" class="boton-secundario">Enviarme un correo de prueba</button>
    </form>
    <form action="configuracion.php" method="POST" class="u-mt-sm">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="probar_resumen_mensual">
        <button type="submit" class="boton-secundario">Enviarme el resumen mensual de prueba</button>
    </form>
    <p class="texto-suave">
        Coordinación y rectoría reciben automáticamente este resumen por correo el día 1 de cada mes,
        con los datos del mes que acaba de terminar.
    </p>
</section>

<div class="modal-fondo" id="modal-respaldo">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-respaldo">
        <div class="modal-cabecera">
            <h2 id="titulo-modal-respaldo">¿Crear copia de seguridad?</h2>
            <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">✕</button>
        </div>
        <p>
            Se va a generar una copia completa de la base de datos ahora mismo.
            Cuando termine de guardarse correctamente, se conservan las últimas <?= RESPALDOS_A_CONSERVAR ?>
            copias y se eliminan las más antiguas que sobren.
        </p>
        <div class="modal-acciones">
            <button type="button" class="boton-secundario" data-cerrar-modal>Cancelar</button>
            <form action="configuracion.php" method="POST" style="margin:0">
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="crear_respaldo">
                <button type="submit" class="boton-acceso">Sí, crear copia ahora</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
