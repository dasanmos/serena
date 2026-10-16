<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Disponibilidad.php';
require_once __DIR__ . '/../../src/modelos/Cita.php';
require_once __DIR__ . '/../../src/modelos/Notificacion.php';
requiereRol('estudiante');

$uid = (int) $_SESSION['usuario_id'];
$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'reservar') {
        $profesionalId = (int) ($_POST['profesional_id'] ?? 0);
        $franja = explode('|', (string) ($_POST['franja'] ?? ''), 2);
        $motivo = trim((string) ($_POST['motivo'] ?? ''));

        if ($profesionalId === 0 || count($franja) !== 2) {
            $error = 'Selecciona un horario disponible.';
        } else {
            [$fechaElegida, $horaInicioElegida] = $franja;
            // Se recalculan las franjas disponibles justo antes de guardar (no se confía en
            // lo que mandó el formulario) — así, si alguien más tomó ese horario un segundo
            // antes, se detecta aquí en vez de guardar una hora de fin inventada.
            $slotEncontrado = null;
            foreach (Cita::slotsDisponiblesDeProfesional($pdo, $profesionalId) as $dia) {
                if ($dia['fecha'] !== $fechaElegida) continue;
                foreach ($dia['slots'] as $s) {
                    if ($s['hora_inicio'] === $horaInicioElegida) { $slotEncontrado = $s; break 2; }
                }
            }
            if (!$slotEncontrado) {
                $error = 'Ese horario ya no está disponible. Elige otro de la lista.';
            } else {
                $resultado = Cita::reservar($pdo, $profesionalId, $uid, $fechaElegida, $slotEncontrado['hora_inicio'], $slotEncontrado['hora_fin'], $motivo);
                if ($resultado['exito']) {
                    $profesional = Usuario::buscarPorId($pdo, $profesionalId);
                    Notificacion::crear(
                        $pdo, $uid, 'cita',
                        'Tu cita con ' . $profesional['nombre'] . ' quedó para el ' . date('d/m/Y', strtotime($fechaElegida)) . ' a las ' . substr($slotEncontrado['hora_inicio'], 0, 5) . '.',
                        BASE_URL . '/estudiante/citas.php'
                    );
                    header('Location: citas.php?reservada=1');
                    exit;
                }
                $error = $resultado['mensaje'];
            }
        }
    }

    if ($accion === 'cancelar') {
        $citaId = (int) ($_POST['cita_id'] ?? 0);
        if (Cita::cancelar($pdo, $citaId, $uid)) {
            header('Location: citas.php?cancelada=1');
            exit;
        }
        $error = 'No se pudo cancelar esa cita.';
    }

    if ($accion === 'reprogramar') {
        $citaId = (int) ($_POST['cita_id'] ?? 0);
        $citaOriginal = Cita::buscarPorId($pdo, $citaId);
        $franja = explode('|', (string) ($_POST['franja'] ?? ''), 2);
        if (!$citaOriginal || (int) $citaOriginal['estudiante_id'] !== $uid || count($franja) !== 2) {
            $error = 'Selecciona un horario disponible.';
        } else {
            [$fechaElegida, $horaInicioElegida] = $franja;
            // Igual que al reservar: se recalculan las franjas disponibles justo antes
            // de guardar, nunca se confía en lo que mandó el formulario.
            $slotEncontrado = null;
            foreach (Cita::slotsDisponiblesDeProfesional($pdo, (int) $citaOriginal['profesional_id']) as $dia) {
                if ($dia['fecha'] !== $fechaElegida) continue;
                foreach ($dia['slots'] as $s) {
                    if ($s['hora_inicio'] === $horaInicioElegida) { $slotEncontrado = $s; break 2; }
                }
            }
            if (!$slotEncontrado) {
                $error = 'Ese horario ya no está disponible. Elige otro de la lista.';
            } else {
                $resultado = Cita::reprogramar($pdo, $citaId, $uid, $fechaElegida, $slotEncontrado['hora_inicio'], $slotEncontrado['hora_fin']);
                if ($resultado['exito']) {
                    $profesional = Usuario::buscarPorId($pdo, (int) $citaOriginal['profesional_id']);
                    Notificacion::crear(
                        $pdo, $uid, 'cita',
                        'Tu cita con ' . $profesional['nombre'] . ' se movió al ' . date('d/m/Y', strtotime($fechaElegida)) . ' a las ' . substr($slotEncontrado['hora_inicio'], 0, 5) . '.',
                        BASE_URL . '/estudiante/citas.php'
                    );
                    header('Location: citas.php?reprogramada=1');
                    exit;
                }
                $error = $resultado['mensaje'];
            }
        }
    }
}

if (isset($_GET['reservada'])) $exito = '¡Listo! Tu cita quedó confirmada.';
if (isset($_GET['cancelada'])) $exito = 'Cita cancelada.';
if (isset($_GET['reprogramada'])) $exito = '¡Listo! Tu cita quedó reprogramada.';

// Reprogramar una cita existente: fuerza el profesional de ESA cita (no se puede
// cambiar de profesional al reprogramar — eso sería una reserva nueva) y muestra
// sus horarios disponibles con un formulario distinto (accion=reprogramar).
$citaAReprogramar = null;
if (isset($_GET['reprogramar'])) {
    $candidata = Cita::buscarPorId($pdo, (int) $_GET['reprogramar']);
    if ($candidata && (int) $candidata['estudiante_id'] === $uid && $candidata['estado'] === 'confirmada') {
        $citaAReprogramar = $candidata;
    }
}

$profesionalId = $citaAReprogramar ? (int) $citaAReprogramar['profesional_id'] : (int) ($_GET['profesional_id'] ?? 0);
$profesionales = array_merge(
    array_map(fn($u) => $u + ['rol' => 'psicologo'], Usuario::listarPorRol($pdo, 'psicologo')),
    array_map(fn($u) => $u + ['rol' => 'orientador'], Usuario::listarPorRol($pdo, 'orientador'))
);
$profesionalActual = null;
$diasConSlots = [];
if ($profesionalId) {
    foreach ($profesionales as $p) {
        if ((int) $p['id'] === $profesionalId) { $profesionalActual = $p; break; }
    }
    if ($profesionalActual) {
        $diasConSlots = Cita::slotsDisponiblesDeProfesional($pdo, $profesionalId);
    }
}
$misCitas = Cita::listarDeEstudiante($pdo, $uid);

$tituloPagina = 'Citas';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Pedir cita</h1>
<div class="recordatorio-criterio-humano">
  <span aria-hidden="true">ℹ️</span>
  <span>Esto es para agendar un espacio de conversación, no reemplaza pedir ayuda de inmediato si la necesitas ahora — en ese caso, busca directamente al psicólogo, al orientador o a un adulto de confianza.</span>
</div>

<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<?php if ($citaAReprogramar): ?>
<div class="mensaje mensaje-info">
    Reprogramando tu cita del <?= date('d/m/Y', strtotime($citaAReprogramar['fecha'])) ?> a las
    <?= substr($citaAReprogramar['hora_inicio'], 0, 5) ?> con <?= htmlspecialchars($profesionalActual['nombre'] ?? '') ?>.
    <a href="citas.php">Cancelar y volver</a>
</div>
<?php else: ?>
<section class="panel-seccion">
    <h2>¿Con quién quieres hablar?</h2>
    <?php if (empty($profesionales)): ?>
        <div class="estado-vacio"><p>No hay psicólogo ni orientador disponible por ahora.</p></div>
    <?php else: ?>
    <div class="accesos-rapidos">
        <?php foreach ($profesionales as $p): ?>
            <a href="citas.php?profesional_id=<?= $p['id'] ?>"
               class="<?= (int) $p['id'] === $profesionalId ? 'boton-acceso' : 'boton-secundario' ?>">
                <?= htmlspecialchars($p['nombre']) ?> · <?= $p['rol'] === 'psicologo' ? 'Psicólogo' : 'Orientador' ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($profesionalActual): ?>
<section class="panel-seccion" style="margin-top:20px">
    <h2><?= $citaAReprogramar ? 'Elige el nuevo horario' : 'Horarios disponibles con ' . htmlspecialchars($profesionalActual['nombre']) ?></h2>
    <?php if (empty($diasConSlots)): ?>
        <div class="estado-vacio"><p>No tiene horarios abiertos por ahora. Vuelve a revisar más tarde.</p></div>
    <?php else: ?>
    <form action="citas.php?profesional_id=<?= $profesionalId ?>" method="POST" class="formulario-panel">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="<?= $citaAReprogramar ? 'reprogramar' : 'reservar' ?>">
        <input type="hidden" name="profesional_id" value="<?= $profesionalId ?>">
        <?php if ($citaAReprogramar): ?>
            <input type="hidden" name="cita_id" value="<?= $citaAReprogramar['id'] ?>">
        <?php endif; ?>
        <?php foreach ($diasConSlots as $dia): ?>
            <p style="font-weight:600;margin-bottom:6px">
                <?= htmlspecialchars(ucfirst(fechaLarga($dia['fecha']))) ?>
            </p>
            <div class="accesos-rapidos" style="margin-bottom:16px">
                <?php foreach ($dia['slots'] as $s): ?>
                    <label class="chip-grupo" style="cursor:pointer">
                        <input type="radio" name="franja" value="<?= htmlspecialchars($dia['fecha'] . '|' . $s['hora_inicio']) ?>" required style="width:auto;margin-right:6px">
                        <?= substr($s['hora_inicio'], 0, 5) ?> - <?= substr($s['hora_fin'], 0, 5) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$citaAReprogramar): ?>
        <label for="motivo">¿Quieres contar de qué se trata? (opcional)</label>
        <textarea id="motivo" name="motivo" rows="3" maxlength="255" placeholder="Puedes dejarlo en blanco si prefieres contarlo en persona."></textarea>
        <?php endif; ?>
        <button type="submit"><?= $citaAReprogramar ? 'Confirmar nuevo horario' : 'Reservar' ?></button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel-seccion" style="margin-top:20px">
    <h2>Mis citas</h2>
    <?php if (empty($misCitas)): ?>
        <div class="estado-vacio"><p>Todavía no has pedido ninguna cita.</p></div>
    <?php else: ?>
    <ul class="lista-simple">
        <?php foreach ($misCitas as $c): ?>
            <li>
                <?= date('d/m/Y', strtotime($c['fecha'])) ?> · <?= substr($c['hora_inicio'], 0, 5) ?>
                — <?= htmlspecialchars($c['profesional_nombre']) ?> (<?= $c['profesional_rol'] === 'psicologo' ? 'Psicólogo' : 'Orientador' ?>)
                <span class="pill pill-severidad-<?= $c['estado'] === 'confirmada' ? 'baja' : ($c['estado'] === 'cancelada' ? 'alta' : 'media') ?>"><?= ucfirst($c['estado']) ?></span>
                <?php if ($c['estado'] === 'confirmada'): ?>
                <a href="citas.php?reprogramar=<?= $c['id'] ?>" class="boton-secundario">Reprogramar</a>
                <form action="citas.php" method="POST" style="display:inline">
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="cancelar">
                    <input type="hidden" name="cita_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="boton-secundario">Cancelar</button>
                </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
