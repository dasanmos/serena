<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Insignia.php';
require_once __DIR__ . '/../../src/modelos/Habito.php';
require_once __DIR__ . '/../../src/modelos/Meta.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Notificacion.php';
require_once __DIR__ . '/../../src/helpers/gamificacion.php';
require_once __DIR__ . '/../../src/modelos/Recordatorio.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/Pulso.php';
require_once __DIR__ . '/../../src/helpers/analisis.php';
require_once __DIR__ . '/../../src/helpers/analisis_cache.php';

requiereRol('estudiante');

$usuarioId = (int) $_SESSION['usuario_id'];

// Responder el pulso de grupo: se maneja aquí (no en un archivo aparte) porque
// vive únicamente como tarjeta de este panel — igual de sencillo que el resto
// del dashboard, sin necesitar su propia página.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pulso_id'])) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $valorPulso = (int) ($_POST['valor'] ?? 0);
    if ($valorPulso >= 1 && $valorPulso <= 5) {
        Pulso::responder($pdo, (int) $_POST['pulso_id'], $usuarioId, $valorPulso);
    }
    header('Location: dashboard.php');
    exit;
}

evaluarInsignias($pdo, $usuarioId);

$textoAnalisis = obtenerAnalisisCacheado($pdo, $usuarioId, fn() => analisisEstudiante($pdo, $usuarioId));

$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

$consultaEmocionHoy = $pdo->prepare(
    "SELECT emocion, nivel, momento_dia
     FROM estado_emocional
     WHERE usuario_id = :usuario_id AND DATE(fecha_hora) = CURDATE()
     ORDER BY fecha_hora DESC
     LIMIT 1"
);
$consultaEmocionHoy->execute(['usuario_id' => $usuarioId]);
$emocionHoy = $consultaEmocionHoy->fetch();

$consultaSuenoAnoche = $pdo->prepare(
    "SELECT horas, calidad
     FROM sueno
     WHERE usuario_id = :usuario_id AND fecha = CURDATE() - INTERVAL 1 DAY
     LIMIT 1"
);
$consultaSuenoAnoche->execute(['usuario_id' => $usuarioId]);
$suenoAnoche = $consultaSuenoAnoche->fetch();

// Sugerencias de la semana (no son un diagnóstico ni una alerta: solo ideas amables con
// un enlace a algo útil de Serena). Se basan únicamente en los propios registros de los
// últimos 7 días del estudiante, y se muestran como máximo 2.
$sugerencias = [];
$cuentaEmociones = [];
$c = $pdo->prepare(
    "SELECT emocion, COUNT(*) AS n FROM estado_emocional
     WHERE usuario_id = :u AND fecha_hora >= NOW() - INTERVAL 7 DAY GROUP BY emocion"
);
$c->execute(['u' => $usuarioId]);
foreach ($c->fetchAll() as $f) { $cuentaEmociones[$f['emocion']] = (int) $f['n']; }
$suma = fn(array $claves) => array_sum(array_map(fn($k) => $cuentaEmociones[$k] ?? 0, $claves));
if ($suma(['ansioso', 'estresado', 'enojado']) >= 3) {
    $sugerencias[] = ['texto' => 'Esta semana has anotado varias veces emociones intensas. Un ejercicio de respiración de un par de minutos puede ayudarte a bajar el ritmo.', 'enlace' => '/estudiante/respiracion.php', 'boton' => 'Respirar un momento'];
}
if ($suma(['triste', 'cansado']) >= 3) {
    $sugerencias[] = ['texto' => 'Parece que ha sido una semana pesada. Hablar con alguien ayuda: puedes escribirle a psicología u orientación cuando quieras.', 'enlace' => '/estudiante/mensajes.php', 'boton' => 'Ir al chat'];
}
$c = $pdo->prepare("SELECT AVG(horas) AS prom, COUNT(*) AS n FROM sueno WHERE usuario_id = :u AND fecha >= CURDATE() - INTERVAL 7 DAY");
$c->execute(['u' => $usuarioId]);
$suenoSemana = $c->fetch();
if ($suenoSemana && (int) $suenoSemana['n'] >= 3 && (float) $suenoSemana['prom'] < 7) {
    $sugerencias[] = ['texto' => 'Esta semana has dormido en promedio ' . number_format((float) $suenoSemana['prom'], 1) . ' horas. A tu edad se recomiendan entre 8 y 10: un horario fijo para acostarte puede ayudar.', 'enlace' => '/estudiante/sueno.php', 'boton' => 'Ver mi sueño'];
}
$sugerencias = array_slice($sugerencias, 0, 2);

// Pulso de grupo pendiente de responder (si el grupo del estudiante tiene uno activo).
$pulsoActivo = null;
$grupoIdEstudiante = $_SESSION['grupo_id'] ?? null;
if ($grupoIdEstudiante) {
    $candidato = Pulso::activoDeGrupo($pdo, (int) $grupoIdEstudiante);
    if ($candidato && !Pulso::yaRespondio($pdo, (int) $candidato['id'], $usuarioId)) {
        $pulsoActivo = $candidato;
    }
}

$diasAbrev = ['lun', 'mar', 'mie', 'jue', 'vie', 'sab', 'dom'];
$diaHoy = $diasAbrev[(int) date('N') - 1];

$consultaHabitosHoy = $pdo->prepare(
    "SELECT h.id, h.nombre,
        (SELECT completado FROM seguimiento_habitos sh
         WHERE sh.habito_id = h.id AND sh.fecha = CURDATE()) AS completado_hoy
     FROM habitos h
     WHERE h.usuario_id = :usuario_id AND h.activo = 1
       AND (h.frecuencia = 'diaria' OR FIND_IN_SET(:dia_hoy, h.dias_semana))
     ORDER BY h.nombre"
);
$consultaHabitosHoy->execute(['usuario_id' => $usuarioId, 'dia_hoy' => $diaHoy]);
$habitosHoy = $consultaHabitosHoy->fetchAll();
$habitosCompletados = count(array_filter($habitosHoy, fn($h) => (int) $h['completado_hoy'] === 1));

$consultaMetasActivas = $pdo->prepare(
    "SELECT COUNT(*) FROM metas WHERE usuario_id = :usuario_id AND estado = 'activa'"
);
$consultaMetasActivas->execute(['usuario_id' => $usuarioId]);
$metasActivas = (int) $consultaMetasActivas->fetchColumn();

$consultaNotificaciones = $pdo->prepare(
    "SELECT COUNT(*) FROM notificaciones WHERE usuario_id = :usuario_id AND leida = 0"
);
$consultaNotificaciones->execute(['usuario_id' => $usuarioId]);
$notificacionesSinLeer = (int) $consultaNotificaciones->fetchColumn();

$recordatoriosHoy = Recordatorio::activosDeHoy($pdo, $usuarioId);
$etiquetasTipoRecordatorio = ['habito' => '🔥 Hábito', 'sueno' => '🌙 Sueño', 'diario' => '📓 Diario', 'meta' => '🎯 Meta', 'personalizado' => '✏️ Recordatorio'];

// Mini vistazo de la última semana — el detalle completo vive en Estadísticas, esto
// es solo para no tener que salir del inicio a ver si ha estado más arriba o más abajo.
$tendenciaSemana = EstadoEmocional::resumenPorPeriodo($pdo, $usuarioId, date('Y-m-d', strtotime('-6 days')), date('Y-m-d'));
$tendenciaSemanaJson = json_encode(array_map(fn($d) => ['dia' => $d['dia'], 'promedio' => round((float) $d['promedio'], 1)], $tendenciaSemana));

$tituloPagina = 'Inicio';
require __DIR__ . '/../parciales/encabezado.php';
?>

<div class="cabecera-saludo">
    <h1><span class="efecto-escritura efecto-escritura-dorado"><?= htmlspecialchars($saludo) ?>, <?= htmlspecialchars($_SESSION['nombre']) ?></span></h1>
    <p class="texto-suave">Este es tu resumen de hoy.</p>
</div>

<?php $tituloAnalisis = 'Tu análisis de la semana'; require __DIR__ . '/../parciales/tarjeta_analisis.php'; ?>

<div class="tarjetas-resumen">
    <div class="tarjeta">
        <span class="tarjeta-icono" aria-hidden="true">✅</span>
        <span class="tarjeta-numero"><?= $habitosCompletados ?>/<?= count($habitosHoy) ?></span>
        <span class="tarjeta-etiqueta">Hábitos de hoy</span>
    </div>
    <div class="tarjeta">
        <span class="tarjeta-icono" aria-hidden="true">🎯</span>
        <span class="tarjeta-numero"><?= $metasActivas ?></span>
        <span class="tarjeta-etiqueta">Metas activas</span>
    </div>
    <div class="tarjeta">
        <span class="tarjeta-icono" aria-hidden="true">🌙</span>
        <span class="tarjeta-numero"><?= $suenoAnoche ? $suenoAnoche['horas'] : '—' ?></span>
        <span class="tarjeta-etiqueta">Horas de sueño anoche</span>
    </div>
    <div class="tarjeta">
        <span class="tarjeta-icono" aria-hidden="true">🔔</span>
        <span class="tarjeta-numero"><?= $notificacionesSinLeer ?></span>
        <span class="tarjeta-etiqueta">Notificaciones sin leer</span>
    </div>
</div>

<?php if (!empty($recordatoriosHoy)): ?>
<div class="panel-seccion tarjeta-acento">
    <h2>Recordatorios de hoy</h2>
    <ul class="lista-simple">
        <?php foreach ($recordatoriosHoy as $r): ?>
            <li><?= $etiquetasTipoRecordatorio[$r['tipo']] ?? ucfirst($r['tipo']) ?> — <?= substr($r['hora'], 0, 5) ?></li>
        <?php endforeach; ?>
    </ul>
    <a href="<?= BASE_URL ?>/estudiante/recordatorios.php" class="boton-secundario">Ver todos</a>
</div>
<?php endif; ?>

<?php if (!empty($sugerencias)): ?>
<div class="panel-seccion tarjeta-acento">
    <h2>Para esta semana</h2>
    <?php foreach ($sugerencias as $sg): ?>
        <p><?= htmlspecialchars($sg['texto']) ?></p>
        <a href="<?= BASE_URL . $sg['enlace'] ?>" class="boton-secundario"><?= htmlspecialchars($sg['boton']) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($pulsoActivo): ?>
<div class="panel-seccion tarjeta-acento tarjeta-pulso-grupo">
    <h2>Pulso de tu grupo</h2>
    <p><?= htmlspecialchars($pulsoActivo['pregunta']) ?></p>
    <form action="dashboard.php" method="POST" class="escala-pulso">
        <?php campoCSRF(); ?>
        <input type="hidden" name="pulso_id" value="<?= $pulsoActivo['id'] ?>">
        <div class="botones-escala-pulso">
            <?php for ($v = 1; $v <= 5; $v++): ?>
                <button type="submit" name="valor" value="<?= $v ?>" class="boton-escala-pulso"><?= $v ?></button>
            <?php endfor; ?>
        </div>
        <div class="rotulos-escala-pulso">
            <span class="texto-suave">Nada</span>
            <span class="texto-suave">Mucho</span>
        </div>
    </form>
    <p class="texto-suave">Tu respuesta es anónima: tu docente solo ve el promedio del grupo.</p>
</div>
<?php endif; ?>

<div class="columnas">

    <section class="panel-seccion">
        <h2>Estado emocional</h2>
        <?php if ($emocionHoy): ?>
            <p>
                Hoy (<?= htmlspecialchars($emocionHoy['momento_dia']) ?>) registraste:
                <strong><?= htmlspecialchars(ucfirst($emocionHoy['emocion'])) ?></strong>
                (nivel <?= (int) $emocionHoy['nivel'] ?>/5)
            </p>
        <?php else: ?>
            <p>Todavía no has registrado cómo te sientes hoy.</p>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/estudiante/emociones.php" class="boton-acceso">Registrar emoción</a>
    </section>

    <section class="panel-seccion">
        <h2>Hábitos de hoy</h2>
        <?php if (empty($habitosHoy)): ?>
            <p>Aún no tienes hábitos activos para hoy.</p>
        <?php else: ?>
            <ul class="lista-simple">
                <?php foreach ($habitosHoy as $habito): ?>
                    <li><?= (int) $habito['completado_hoy'] === 1 ? '✓' : '○' ?> <?= htmlspecialchars($habito['nombre']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/estudiante/habitos.php" class="boton-acceso">Ver hábitos</a>
    </section>

</div>

<?php if (count($tendenciaSemana) >= 2): ?>
<section class="panel-seccion">
    <h2>Tu semana</h2>
    <canvas id="grafico-tendencia-semana" height="140"></canvas>
</section>
<?php endif; ?>

<div class="accesos-rapidos">
    <a href="<?= BASE_URL ?>/estudiante/diario.php" class="boton-acceso">Diario</a>
    <a href="<?= BASE_URL ?>/estudiante/metas.php" class="boton-acceso">Metas</a>
    <a href="<?= BASE_URL ?>/estudiante/estadisticas.php" class="boton-acceso">Estadísticas</a>
    <a href="<?= BASE_URL ?>/estudiante/perfil.php" class="boton-acceso">Mi perfil</a>
</div>

<?php if (count($tendenciaSemana) >= 2):
    $chartJsLocal = __DIR__ . '/../assets/js/vendor/chart.min.js';
    $chartJsSrc = is_file($chartJsLocal) ? BASE_URL . '/assets/js/vendor/chart.min.js' : 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
    ?>
    <script src="<?= $chartJsSrc ?>"></script>
    <script>
    const tendenciaSemanaData = <?= $tendenciaSemanaJson ?>;
    new Chart(document.getElementById('grafico-tendencia-semana'), {
        type: 'line',
        data: {
            labels: tendenciaSemanaData.map(d => d.dia.slice(5)),
            datasets: [{ label: 'Nivel emocional', data: tendenciaSemanaData.map(d => d.promedio),
                borderColor: '#E8B923', backgroundColor: 'rgba(232,185,35,0.15)', tension: 0.3, fill: true }]
        },
        options: { scales: { y: { min: 1, max: 5 } }, plugins: { legend: { display: false } } }
    });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
