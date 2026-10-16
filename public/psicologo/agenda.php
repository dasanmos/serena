<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Disponibilidad.php';
require_once __DIR__ . '/../../src/modelos/Cita.php';
require_once __DIR__ . '/../../src/modelos/Notificacion.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
requiereRol('psicologo', 'orientador');

$uid = (int) $_SESSION['usuario_id'];
$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_bloque') {
        $resultado = Disponibilidad::crear(
            $pdo, $uid,
            $_POST['fecha'] ?? '', $_POST['hora_inicio'] ?? '', $_POST['hora_fin'] ?? '',
            (int) ($_POST['duracion'] ?? 20)
        );
        if ($resultado['exito']) {
            $exito = 'Disponibilidad publicada.';
        } else {
            $error = $resultado['mensaje'];
        }
    }

    if ($accion === 'eliminar_bloque') {
        Disponibilidad::eliminar($pdo, (int) ($_POST['bloque_id'] ?? 0), $uid);
        $exito = 'Bloque eliminado.';
    }

    if ($accion === 'cancelar_cita') {
        $citaId = (int) ($_POST['cita_id'] ?? 0);
        $cita = Cita::buscarPorId($pdo, $citaId);
        if ($cita && Cita::cancelar($pdo, $citaId, $uid)) {
            Notificacion::crear(
                $pdo, (int) $cita['estudiante_id'], 'cita',
                'Tu cita del ' . date('d/m/Y', strtotime($cita['fecha'])) . ' a las ' . substr($cita['hora_inicio'], 0, 5) . ' fue cancelada por el profesional.',
                BASE_URL . '/estudiante/citas.php'
            );
            $exito = 'Cita cancelada. Se le avisó al estudiante.';
        } else {
            $error = 'No se pudo cancelar esa cita.';
        }
    }

    if ($accion === 'reprogramar_cita') {
        $citaId = (int) ($_POST['cita_id'] ?? 0);
        $citaOriginal = Cita::buscarPorId($pdo, $citaId);
        $franja = explode('|', (string) ($_POST['franja'] ?? ''), 2);
        if (!$citaOriginal || (int) $citaOriginal['profesional_id'] !== $uid || count($franja) !== 2) {
            $error = 'Selecciona un horario disponible.';
        } else {
            [$fechaElegida, $horaInicioElegida] = $franja;
            $slotEncontrado = null;
            foreach (Cita::slotsDisponiblesDeProfesional($pdo, $uid) as $dia) {
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
                    Notificacion::crear(
                        $pdo, (int) $citaOriginal['estudiante_id'], 'cita',
                        'Tu cita se movió al ' . date('d/m/Y', strtotime($fechaElegida)) . ' a las ' . substr($slotEncontrado['hora_inicio'], 0, 5) . '.',
                        BASE_URL . '/estudiante/citas.php'
                    );
                    $exito = 'Cita reprogramada. Se le avisó al estudiante.';
                } else {
                    $error = $resultado['mensaje'];
                }
            }
        }
    }
}

// Reprogramar: se reutilizan los propios bloques de disponibilidad del
// profesional (igual que para publicar/ver citas) — no un profesional distinto.
$citaAReprogramar = null;
if (isset($_GET['reprogramar'])) {
    $candidata = Cita::buscarPorId($pdo, (int) $_GET['reprogramar']);
    if ($candidata && (int) $candidata['profesional_id'] === $uid && $candidata['estado'] === 'confirmada') {
        $citaAReprogramar = $candidata;
    }
}
$diasConSlotsReprogramar = $citaAReprogramar ? Cita::slotsDisponiblesDeProfesional($pdo, $uid) : [];

$bloques = Disponibilidad::listarDeProfesional($pdo, $uid);
$citas = Cita::listarDeProfesional($pdo, $uid);

$tituloPagina = 'Agenda';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Mi agenda de citas</h1>
<div class="u-mb-md"><a href="citas_ics.php" class="boton-secundario" data-sonido="clic">📅 Exportar a Google Calendar / Outlook (.ics)</a></div>
<div class="recordatorio-criterio-humano">
  <span aria-hidden="true">ℹ️</span>
  <span>Publica los espacios en los que puedes recibir estudiantes. Se abren para que los reserven apenas los guardas, y desaparecen solos en cuanto alguien toma el turno.</span>
</div>

<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<div class="columnas">
<section class="panel-seccion">
    <h2>Publicar disponibilidad</h2>
    <form action="agenda.php" method="POST" class="formulario-panel">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="crear_bloque">
        <label for="fecha">Fecha</label>
        <input type="date" id="fecha" name="fecha" min="<?= date('Y-m-d') ?>" required>
        <label for="hora_inicio">Desde</label>
        <input type="time" id="hora_inicio" name="hora_inicio" required>
        <label for="hora_fin">Hasta</label>
        <input type="time" id="hora_fin" name="hora_fin" required>
        <label for="duracion">Duración de cada turno</label>
        <select id="duracion" name="duracion">
            <option value="15">15 minutos</option>
            <option value="20" selected>20 minutos</option>
            <option value="30">30 minutos</option>
            <option value="45">45 minutos</option>
            <option value="60">60 minutos</option>
        </select>
        <button type="submit">Publicar</button>
    </form>
</section>

<section class="panel-seccion">
    <h2>Mis bloques publicados</h2>
    <?php if (empty($bloques)): ?>
        <div class="estado-vacio"><p>No has publicado disponibilidad todavía.</p></div>
    <?php else: ?>
    <ul class="lista-simple">
        <?php foreach ($bloques as $b): ?>
            <li>
                <?= htmlspecialchars(ucfirst(fechaLarga($b['fecha']))) ?>,
                <?= substr($b['hora_inicio'], 0, 5) ?>–<?= substr($b['hora_fin'], 0, 5) ?>
                (turnos de <?= (int) $b['duracion_slot_minutos'] ?> min)
                <form action="agenda.php" method="POST" style="display:inline">
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="eliminar_bloque">
                    <input type="hidden" name="bloque_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="boton-secundario">Quitar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
</div>

<?php if ($citaAReprogramar): ?>
<section class="panel-seccion" style="margin-top:20px">
    <?php $estudianteOriginal = Usuario::buscarPorId($pdo, (int) $citaAReprogramar['estudiante_id']); ?>
    <h2>Reprogramar cita con <?= htmlspecialchars($estudianteOriginal['nombre'] ?? '') ?></h2>
    <p class="texto-suave">
        Estaba para el <?= date('d/m/Y', strtotime($citaAReprogramar['fecha'])) ?> a las
        <?= substr($citaAReprogramar['hora_inicio'], 0, 5) ?>. Elige el nuevo horario entre tus propios bloques publicados.
        <a href="agenda.php">Cancelar</a>
    </p>
    <?php if (empty($diasConSlotsReprogramar)): ?>
        <div class="estado-vacio"><p>No tienes otros horarios abiertos por ahora. Publica un bloque primero.</p></div>
    <?php else: ?>
    <form action="agenda.php" method="POST" class="formulario-panel">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="reprogramar_cita">
        <input type="hidden" name="cita_id" value="<?= $citaAReprogramar['id'] ?>">
        <?php foreach ($diasConSlotsReprogramar as $dia): ?>
            <p style="font-weight:600;margin-bottom:6px"><?= htmlspecialchars(ucfirst(fechaLarga($dia['fecha']))) ?></p>
            <div class="accesos-rapidos" style="margin-bottom:16px">
                <?php foreach ($dia['slots'] as $s): ?>
                    <label class="chip-grupo" style="cursor:pointer">
                        <input type="radio" name="franja" value="<?= htmlspecialchars($dia['fecha'] . '|' . $s['hora_inicio']) ?>" required style="width:auto;margin-right:6px">
                        <?= substr($s['hora_inicio'], 0, 5) ?> - <?= substr($s['hora_fin'], 0, 5) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit">Confirmar nuevo horario</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel-seccion" style="margin-top:20px">
    <h2>Próximas citas reservadas</h2>
    <?php if (empty($citas)): ?>
        <div class="estado-vacio"><p>Nadie ha reservado un turno todavía.</p></div>
    <?php else: ?>
    <ul class="lista-simple">
        <?php foreach ($citas as $c): ?>
            <li>
                <?= date('d/m/Y', strtotime($c['fecha'])) ?> · <?= substr($c['hora_inicio'], 0, 5) ?>
                — <strong><?= htmlspecialchars($c['estudiante_nombre']) ?></strong>
                <?php if (!empty($c['motivo'])): ?><br><span class="texto-suave"><?= htmlspecialchars($c['motivo']) ?></span><?php endif; ?>
                <a href="agenda.php?reprogramar=<?= $c['id'] ?>" class="boton-secundario">Reprogramar</a>
                <form action="agenda.php" method="POST" style="display:inline">
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="cancelar_cita">
                    <input type="hidden" name="cita_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="boton-secundario">Cancelar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
