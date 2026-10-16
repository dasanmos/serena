<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respaldo.php';
require_once __DIR__ . '/../../src/helpers/correo.php';
requiereRol('administrador', 'superadmin');

// Alcance por rol:
//  · superadmin     → estado de TODA la plataforma (copias, base de datos, disco, correo, servidor).
//                     Las cifras de uso se filtran por el colegio en contexto, si hay uno elegido.
//  · administrador  → solo lo de SU colegio (usuarios, accesos, sesiones, pendientes, alertas) y si el
//                     correo funciona. Nunca ve copias, discos, servidor ni datos de otros colegios.
$esSuper = esSuperadmin();

// Panel de salud del sistema: solo lectura. Cada dato se consulta por separado y
// dentro de try/catch — si una consulta falla (o el hosting deshabilita una función
// como disk_free_space), ese renglón dice «no disponible» y el resto de la página
// sigue funcionando.
$consulta = function (string $sql, array $params = []) use ($pdo) {
    try {
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return $c->fetchColumn();
    } catch (Throwable $e) {
        error_log('salud.php: ' . $e->getMessage());
        return null;
    }
};
$bytes = function ($n): string {
    if ($n === null || $n === false) return 'no disponible';
    $n = (float) $n;
    if ($n >= 1073741824) return number_format($n / 1073741824, 2) . ' GB';
    if ($n >= 1048576) return number_format($n / 1048576, 1) . ' MB';
    return number_format($n / 1024, 0) . ' KB';
};
$tamanoCarpeta = function (string $ruta) {
    if (!is_dir($ruta)) return 0;
    $total = 0;
    try {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) $total += $f->getSize();
        }
    } catch (Throwable $e) {
        return null;
    }
    return $total;
};

// Cada renglón: [estado (ok|aviso|mal|info), etiqueta, valor/detalle]
$secciones = [];

// --- Respaldos (solo plataforma) ---
if ($esSuper) {
$respaldos = listarRespaldos();
$ultimo = $respaldos[0] ?? null;
$filasRespaldo = [];
if (!$ultimo) {
    $filasRespaldo[] = ['mal', 'Última copia de seguridad', 'Nunca se ha creado una. Créala en Respaldos y ajustes.'];
} else {
    $dias = (int) floor((time() - $ultimo['fecha']) / 86400);
    $filasRespaldo[] = [$dias > 7 ? 'aviso' : 'ok', 'Última copia de seguridad', date('d/m/Y H:i', $ultimo['fecha']) . ($dias > 0 ? " (hace {$dias} días)" : ' (hoy)')];
}
$filasRespaldo[] = ['info', 'Copias guardadas', count($respaldos) . ' de ' . RESPALDOS_A_CONSERVAR];
$filasRespaldo[] = [entornoServer('RESPALDO_CRON_SECRETO') !== '' ? 'ok' : 'aviso', 'Respaldo automático', entornoServer('RESPALDO_CRON_SECRETO') !== '' ? 'Configurado (falta que un servicio externo llame al endpoint cada día).' : 'No configurado: falta RESPALDO_CRON_SECRETO en src/config/.env.'];
$secciones['Respaldos'] = $filasRespaldo;

// --- Base de datos (tamaño y tablas son de TODA la plataforma) ---
$tamanoBd = $consulta("SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE()");
$tablas = $consulta("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()");
$secciones['Base de datos'] = [
    ['info', 'Tamaño', $bytes($tamanoBd)],
    ['info', 'Tablas', $tablas === null ? 'no disponible' : (string) $tablas],
    ['info', 'Registros emocionales', (string) ($consulta('SELECT COUNT(*) FROM estado_emocional WHERE 1=1' . sqlUsuarioDeColegio('usuario_id')) ?? 'no disponible')],
];

// --- Almacenamiento ---
$libre = function_exists('disk_free_space') ? @disk_free_space(__DIR__) : false;
$total = function_exists('disk_total_space') ? @disk_total_space(__DIR__) : false;
$estadoDisco = 'info';
$detalleDisco = 'no disponible en este hosting';
if ($libre !== false && $total) {
    $pctLibre = $libre / $total * 100;
    $estadoDisco = $pctLibre < 10 ? 'mal' : ($pctLibre < 20 ? 'aviso' : 'ok');
    $detalleDisco = $bytes($libre) . ' libres de ' . $bytes($total) . ' (' . round($pctLibre) . '%)';
}
$secciones['Almacenamiento'] = [
    [$estadoDisco, 'Espacio en disco', $detalleDisco],
    ['info', 'Copias de seguridad', $bytes(array_sum(array_column($respaldos, 'tamano')))],
    ['info', 'Imágenes subidas (diario)', $bytes($tamanoCarpeta(__DIR__ . '/../../src/uploads/diario'))],
];

// --- Correo y tareas programadas ---
$ultimoRecordatorio = $consulta('SELECT MAX(ultimo_envio) FROM recordatorios');
$secciones['Correo y tareas programadas'] = [
    [smtpConfigurado() ? 'ok' : 'mal', 'Envío de correo (Brevo)', smtpConfigurado() ? 'Configurado' : 'No configurado (falta BREVO_API_KEY)'],
    [entornoServer('RECORDATORIOS_CRON_SECRETO') !== '' ? 'ok' : 'aviso', 'Recordatorios por correo', entornoServer('RECORDATORIOS_CRON_SECRETO') !== '' ? 'Configurado. Último envío: ' . ($ultimoRecordatorio ? date('d/m/Y', strtotime((string) $ultimoRecordatorio)) : 'ninguno todavía') : 'No configurado (falta RECORDATORIOS_CRON_SECRETO)'],
];
} else {
    // Administrador de colegio: lo suyo + si el correo funciona (le afecta: códigos de verificación y recuperación de clave).
    $secciones['Datos de tu colegio'] = [
        ['info', 'Registros emocionales', (string) ($consulta('SELECT COUNT(*) FROM estado_emocional WHERE 1=1' . sqlUsuarioDeColegio('usuario_id')) ?? 'no disponible')],
    ];
    $secciones['Correo'] = [
        [smtpConfigurado() ? 'ok' : 'mal', 'Envío de correos de la plataforma', smtpConfigurado() ? 'Funciona' : 'No disponible. Avisa a quien administra la plataforma.'],
    ];
}

// --- Usuarios y pendientes ---
$secciones['Uso'] = [
    ['info', 'Usuarios activos / totales', ($consulta('SELECT COUNT(*) FROM usuarios WHERE activo = 1' . sqlColegio('colegio_id')) ?? '?') . ' / ' . ($consulta('SELECT COUNT(*) FROM usuarios WHERE 1=1' . sqlColegio('colegio_id')) ?? '?')],
    ['info', 'Accesos en las últimas 24 h', (string) ($consulta('SELECT COUNT(*) FROM usuarios WHERE ultimo_acceso >= NOW() - INTERVAL 1 DAY' . sqlColegio('colegio_id')) ?? 'no disponible')],
    ['info', 'Sesiones abiertas (actividad en los últimos 30 min)', (string) ($consulta('SELECT COUNT(*) FROM sesiones_activas WHERE revocada_en IS NULL AND ultima_actividad >= NOW() - INTERVAL 30 MINUTE' . sqlUsuarioDeColegio('usuario_id')) ?? 'no disponible')],
];
$pendSol = (int) ($consulta("SELECT COUNT(*) FROM solicitudes_cambio WHERE estado = 'pendiente'" . sqlUsuarioDeColegio('usuario_id')) ?? 0);
$pendAlertas = (int) ($consulta('SELECT COUNT(*) FROM alertas_tempranas WHERE atendida = 0' . sqlUsuarioDeColegio('usuario_id')) ?? 0);
$secciones['Pendientes'] = [
    [$pendSol > 0 ? 'aviso' : 'ok', 'Solicitudes de cambio por aprobar', (string) $pendSol],
    [$pendAlertas > 0 ? 'aviso' : 'ok', 'Alertas tempranas sin atender', (string) $pendAlertas],
];

// --- Servidor (solo plataforma) ---
if ($esSuper) {
    $extensiones = [];
    foreach (['pdo_mysql', 'mbstring', 'openssl', 'curl', 'gd'] as $ext) {
        $extensiones[] = $ext . ' ' . (extension_loaded($ext) ? '✓' : '✗');
    }
    $secciones['Servidor'] = [
        ['info', 'PHP', PHP_VERSION],
        ['info', 'Límite de memoria / subida / tiempo', ini_get('memory_limit') . ' / ' . ini_get('upload_max_filesize') . ' / ' . ini_get('max_execution_time') . ' s'],
        [extension_loaded('gd') ? 'ok' : 'aviso', 'Extensiones', implode(' · ', $extensiones)],
    ];
}

$etiquetasEstado = ['ok' => 'Bien', 'aviso' => 'Revisar', 'mal' => 'Atención', 'info' => 'Dato'];

$tituloPagina = 'Salud del sistema';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
    <h1>Salud del sistema</h1>
    <?php if ($esSuper): ?>
    <p class="texto-suave">Un vistazo al estado de la plataforma. Solo muestra información: no cambia nada.
        <?= (int) ($_SESSION['colegio_contexto'] ?? 0) > 0 ? 'Las cifras de uso y pendientes son del colegio elegido en la barra superior.' : 'Las cifras de uso y pendientes suman todos los colegios.' ?></p>
    <?php else: ?>
    <p class="texto-suave">Un vistazo al estado de tu colegio en la plataforma. Solo muestra información: no cambia nada.</p>
    <?php endif; ?>
</div>

<?php foreach ($secciones as $titulo => $filas): ?>
<section class="panel-seccion">
    <h2><?= htmlspecialchars($titulo) ?></h2>
    <ul class="lista-salud">
        <?php foreach ($filas as [$estado, $etiqueta, $detalle]): ?>
        <li>
            <div>
                <strong><?= htmlspecialchars($etiqueta) ?></strong>
                <div class="detalle-salud"><?= htmlspecialchars((string) $detalle) ?></div>
            </div>
            <span class="pill pill-salud-<?= $estado ?>"><?= $etiquetasEstado[$estado] ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
