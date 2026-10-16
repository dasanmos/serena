<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/Colegio.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

// Traslado de un estudiante de una institución a otra.
// Privacidad: NO hay listado de estudiantes; se busca por correo EXACTO y solo se muestran
// nombre, colegio y grupo. El historial propio del estudiante (emociones, sueño, diario,
// hábitos, metas) lo sigue; lo del colegio de origen deja de ser visible para ese personal.

/** Estudiante (solo rol estudiante) dentro del alcance del usuario. $columna es constante del código. */
function buscarEstudianteTraslado(PDO $pdo, string $columna, string $valor): ?array
{
    $q = $pdo->prepare(
        "SELECT u.id, u.nombre, u.correo, u.activo, u.colegio_id, u.grupo_id, c.nombre AS colegio_nombre,
                (SELECT CONCAT(gr.nombre, ' ', g.nombre) FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id
                  WHERE g.id = u.grupo_id) AS grupo_nombre
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         LEFT JOIN colegios c ON c.id = u.colegio_id
         WHERE r.nombre = 'estudiante' AND {$columna} = :v" . sqlAlcanceColegios($pdo, 'u.colegio_id') . ' LIMIT 1'
    );
    $q->execute(['v' => $valor]);
    $e = $q->fetch();
    return $e ?: null;
}

$flash = $_SESSION['flash_traslado'] ?? null;
unset($_SESSION['flash_traslado']);
$urlPagina = BASE_URL . '/' . carpetaPlataforma() . '/trasladar_estudiante.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $f = ['exito' => '', 'error' => ''];
    $est = buscarEstudianteTraslado($pdo, 'u.id', (string) (int) ($_POST['usuario_id'] ?? 0));
    $grupoDestino = (int) ($_POST['grupo_destino_id'] ?? 0);
    $motivo = mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 200);

    $g = $pdo->prepare('SELECT g.id, gr.colegio_id FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE g.id = :id');
    $g->execute(['id' => $grupoDestino]);
    $grupo = $g->fetch();

    if (!$est) {
        $f['error'] = 'Estudiante no válido.';
    } elseif (!$grupo) {
        $f['error'] = 'Elige el grupo de destino.';
    } else {
        $colegioDestino = (int) $grupo['colegio_id'];
        $cd = $pdo->prepare('SELECT activo FROM colegios WHERE id = :id');
        $cd->execute(['id' => $colegioDestino]);
        $activoDestino = $cd->fetchColumn();

        if ($activoDestino === false || (int) $activoDestino !== 1 || !colegioEnAlcance($pdo, $colegioDestino)) {
            $f['error'] = 'El colegio de destino no es válido.';
        } elseif ($colegioDestino === (int) $est['colegio_id']) {
            $f['error'] = 'El estudiante ya pertenece a ese colegio. Para cambiarlo de grupo usa el panel del colegio.';
        } else {
            $uid = (int) $est['id'];
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE usuarios SET colegio_id = :c, grupo_id = :g WHERE id = :u')
                    ->execute(['c' => $colegioDestino, 'g' => $grupoDestino, 'u' => $uid]);
                // Citas pendientes con profesionales del colegio de origen: ya no aplican.
                $citas = $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE estudiante_id = :u AND estado = 'confirmada' AND fecha >= CURDATE()");
                $citas->execute(['u' => $uid]);
                // Las sesiones abiertas guardan el colegio de origen: se cierran para que vuelva a entrar ya en el nuevo.
                $pdo->prepare('UPDATE sesiones_activas SET revocada_en = NOW() WHERE usuario_id = :u AND revocada_en IS NULL')
                    ->execute(['u' => $uid]);
                auditarAccion($pdo, 'trasladar_estudiante', 'usuarios', $uid,
                    ['colegio_id' => (int) $est['colegio_id'], 'grupo_id' => $est['grupo_id'] !== null ? (int) $est['grupo_id'] : null],
                    ['colegio_id' => $colegioDestino, 'grupo_id' => $grupoDestino, 'motivo' => $motivo, 'citas_canceladas' => $citas->rowCount()]
                );
                $pdo->commit();
                $f['exito'] = "{$est['nombre']} fue trasladado(a) de «{$est['colegio_nombre']}» al nuevo colegio. Se cerraron sus sesiones abiertas"
                    . ($citas->rowCount() > 0 ? " y se cancelaron {$citas->rowCount()} cita(s) pendiente(s)." : '.');
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('trasladar_estudiante falló: ' . $e->getMessage());
                $f['error'] = 'No se pudo completar el traslado. No se hizo ningún cambio.';
            }
        }
    }
    $_SESSION['flash_traslado'] = $f;
    header('Location: ' . $urlPagina);
    exit;
}

$correo = trim((string) ($_GET['correo'] ?? ''));
$estudiante = null;
$buscado = $correo !== '';
$colegiosDestino = [];
$gruposPorColegio = [];
if ($buscado) {
    $estudiante = buscarEstudianteTraslado($pdo, 'u.correo', $correo);
    if ($estudiante) {
        foreach (Colegio::listarTodos($pdo, true) as $c) {
            if ((int) $c['id'] !== (int) $estudiante['colegio_id']) {
                $colegiosDestino[(int) $c['id']] = (string) $c['nombre'];
            }
        }
        if ($colegiosDestino) {
            $gq = $pdo->query('SELECT g.id, g.nombre AS grupo, gr.nombre AS grado, gr.colegio_id
                               FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id
                               WHERE 1 = 1' . sqlAlcanceColegios($pdo, 'gr.colegio_id') . ' ORDER BY gr.nombre, g.nombre');
            foreach ($gq->fetchAll() as $fila) {
                if (isset($colegiosDestino[(int) $fila['colegio_id']])) {
                    $gruposPorColegio[(int) $fila['colegio_id']][] = ['id' => (int) $fila['id'], 'nombre' => $fila['grado'] . ' ' . $fila['grupo']];
                }
            }
        }
    }
}

$tituloPagina = 'Trasladar estudiante';
require __DIR__ . '/../parciales/encabezado.php';
?>

<div class="cabecera-saludo">
    <h1>Trasladar estudiante</h1>
    <p class="texto-suave">Pasa a un estudiante de una institución a otra. Búscalo por su correo exacto.</p>
</div>

<?php if (!empty($flash['error'])): ?><p class="mensaje mensaje-error" role="alert"><?= htmlspecialchars($flash['error']) ?></p><?php endif; ?>
<?php if (!empty($flash['exito'])): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($flash['exito']) ?></p><?php endif; ?>

<section class="panel-seccion">
    <form action="trasladar_estudiante.php" method="GET">
        <label for="correo">Correo del estudiante
            <input type="email" id="correo" name="correo" value="<?= htmlspecialchars($correo) ?>" required autocomplete="off">
        </label>
        <button type="submit" class="boton-secundario">Buscar</button>
    </form>
</section>

<?php if ($buscado && !$estudiante): ?>
    <p class="mensaje mensaje-info">No se encontró un estudiante con ese correo dentro de tu alcance.</p>
<?php elseif ($estudiante): ?>
<section class="panel-seccion">
    <h2><?= htmlspecialchars($estudiante['nombre']) ?></h2>
    <p>
        <?= htmlspecialchars($estudiante['correo']) ?><br>
        Colegio actual: <strong><?= htmlspecialchars((string) $estudiante['colegio_nombre']) ?></strong><br>
        Grupo actual: <strong><?= htmlspecialchars((string) ($estudiante['grupo_nombre'] ?? 'Sin grupo')) ?></strong>
        <?= (int) $estudiante['activo'] === 0 ? ' · <em>cuenta desactivada</em>' : '' ?>
    </p>

    <?php if (!$colegiosDestino): ?>
        <p class="mensaje mensaje-info">No hay otros colegios activos disponibles como destino.</p>
    <?php else: ?>
    <form action="trasladar_estudiante.php" method="POST" id="form-traslado">
        <?php campoCSRF(); ?>
        <input type="hidden" name="usuario_id" value="<?= (int) $estudiante['id'] ?>">

        <label for="colegio_destino">Colegio de destino</label>
        <select id="colegio_destino" required>
            <option value="">— Elige un colegio —</option>
            <?php foreach ($colegiosDestino as $id => $nombre): ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($nombre) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="grupo_destino_id">Grupo de destino</label>
        <select id="grupo_destino_id" name="grupo_destino_id" required disabled>
            <option value="">— Primero elige el colegio —</option>
        </select>

        <label for="motivo">Motivo (opcional)</label>
        <input type="text" id="motivo" name="motivo" maxlength="200">

        <p class="mensaje mensaje-info">
            Su historial personal (emociones, sueño, diario, hábitos y metas) lo acompaña. Se cierran sus sesiones abiertas,
            se cancelan sus citas pendientes y el personal del colegio actual deja de ver su ficha.
            Las notas privadas de ese personal quedan guardadas, pero ya no serán visibles para nadie del colegio nuevo.
        </p>
        <button type="submit" onclick="return confirm('¿Confirmas el traslado de este estudiante? Se cerrarán sus sesiones abiertas.');">Trasladar estudiante</button>
    </form>
    <script>
        (function () {
            var grupos = <?= json_encode($gruposPorColegio, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
            var colegio = document.getElementById('colegio_destino');
            var grupo = document.getElementById('grupo_destino_id');
            colegio.addEventListener('change', function () {
                var lista = grupos[colegio.value] || [];
                grupo.innerHTML = '';
                var o = document.createElement('option');
                o.value = '';
                o.textContent = lista.length ? '— Elige un grupo —' : 'Ese colegio aún no tiene grupos';
                grupo.appendChild(o);
                lista.forEach(function (g) {
                    var op = document.createElement('option');
                    op.value = g.id;
                    op.textContent = g.nombre;
                    grupo.appendChild(op);
                });
                grupo.disabled = !lista.length;
            });
        })();
    </script>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
