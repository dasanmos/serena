<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Academico.php';

// Matrículas de un año lectivo: confirmar solicitudes de renovación (con grupo), retirar, y renovar a varios estudiantes a la vez.
requiereRol('secretaria_academica', 'administrador', 'rector');
$colegioId = colegioParaCrear();
$usuarioId = (int) $_SESSION['usuario_id'];

$anios = Academico::anios($pdo, $colegioId);
$anioSel = (int) ($_GET['anio'] ?? $_POST['anio'] ?? 0);
if ($anioSel === 0) {
    $actual = Academico::anioActual($pdo, $colegioId);
    $anioSel = $actual ? (int) $actual['id'] : ($anios ? (int) $anios[0]['id'] : 0);
}
$anio = $anioSel > 0 ? Academico::anio($pdo, $anioSel, $colegioId) : null;

$flash = $_SESSION['flash_matriculas'] ?? null;
unset($_SESSION['flash_matriculas']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $anio !== null) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => ''];

    if ($accion === 'resolver') {
        $err = Academico::resolverMatricula($pdo, (int) ($_POST['id'] ?? 0), $colegioId, (string) ($_POST['estado'] ?? ''), (int) ($_POST['grupo_id'] ?? 0) ?: null);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Matrícula actualizada.' : $err;
        if ($err === '') {
            auditarAccion($pdo, 'resolver_matricula', 'matriculas', (int) ($_POST['id'] ?? 0), [], ['estado' => (string) ($_POST['estado'] ?? '')]);
        }
    } elseif ($accion === 'renovar') {
        $items = [];
        foreach ((array) ($_POST['renovar'] ?? []) as $estId => $marca) {
            $items[(int) $estId] = (int) (($_POST['grado'] ?? [])[$estId] ?? 0);
        }
        if (!$items) {
            $f['error'] = 'Marca al menos un estudiante.';
        } else {
            $r = Academico::renovar($pdo, $colegioId, $anioSel, $items, $usuarioId);
            $f['exito'] = "{$r['creadas']} matrícula(s) creada(s) como ACTIVAS" . ($r['omitidas'] ? " · {$r['omitidas']} omitida(s) (ya tenían matrícula o datos inválidos)" : '') . '. Asígnales grupo abajo.';
            auditarAccion($pdo, 'renovar_matriculas', 'matriculas', 0, [], ['anio_lectivo_id' => $anioSel, 'creadas' => $r['creadas']]);
        }
    }
    $_SESSION['flash_matriculas'] = $f;
    header('Location: matriculas.php?anio=' . $anioSel);
    exit;
}

$estadoSel = (string) ($_GET['estado'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$lista = $anio ? Academico::matriculas($pdo, $colegioId, $anioSel, $estadoSel, $q) : [];
$elegibles = $anio ? Academico::elegiblesRenovacion($pdo, $colegioId, $anioSel) : [];
$grados = Academico::grados($pdo, $colegioId);
$grupos = Academico::grupos($pdo, $colegioId);
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Matrículas';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Matrículas</h1>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>

<?php if (!$anios): ?>
    <p class="mensaje mensaje-aviso">Primero crea un año lectivo en <a href="<?= $e(BASE_URL) ?>/administrador/anios_lectivos.php">Años lectivos</a>.</p>
<?php else: ?>

<form action="matriculas.php" method="GET" class="acad-filtros">
    <label>Año lectivo
        <select name="anio" onchange="this.form.submit()">
            <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $anioSel === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?><?= (int) $a['actual'] === 1 ? ' (actual)' : '' ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Estado
        <select name="estado">
            <option value="">Todos</option>
            <?php foreach (Academico::ESTADOS_MATRICULA as $k => $t): ?><option value="<?= $e($k) ?>" <?= $estadoSel === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Buscar <input type="search" name="q" value="<?= $e($q) ?>" placeholder="Nombre o correo del estudiante"></label>
    <button type="submit">Filtrar</button>
</form>

<section class="panel-seccion">
    <h2>Matrículas del <?= (int) ($anio['anio'] ?? 0) ?> (<?= count($lista) ?>)</h2>
    <?php if (!$lista): ?>
        <div class="estado-vacio"><p>No hay matrículas con esos filtros.</p></div>
    <?php else: ?>
    <div class="acad-lista">
        <?php foreach ($lista as $m): ?>
            <?php $gruposDelGrado = array_filter($grupos, static fn (array $g): bool => (int) $g['grado_id'] === (int) $m['grado_id']); ?>
            <div class="acad-fila">
                <div class="acad-fila-info">
                    <strong><?= $e($m['estudiante']) ?></strong>
                    <span class="estado-pill estado-<?= $e($m['estado']) ?>"><?= $e(Academico::ESTADOS_MATRICULA[$m['estado']] ?? $m['estado']) ?></span>
                    <small><?= $e($m['grado']) ?><?= $m['grupo'] ? ' · Grupo ' . $e($m['grupo']) : ' · sin grupo' ?> · <?= $m['tipo'] === 'renovacion' ? 'Renovación' : 'Nueva' ?> · <?= $e($m['correo']) ?></small>
                </div>
                <form action="matriculas.php" method="POST" class="acad-acciones" novalidate>
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="resolver"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="anio" value="<?= $anioSel ?>">
                    <label>Grupo
                        <select name="grupo_id">
                            <option value="">—</option>
                            <?php foreach ($gruposDelGrado as $g): ?><option value="<?= (int) $g['id'] ?>" <?= (int) $m['grupo_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= $e($g['nombre']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" name="estado" value="activa"><?= $m['estado'] === 'activa' ? 'Guardar grupo' : 'Confirmar' ?></button>
                    <?php if ($m['estado'] !== 'rechazada'): ?><button type="submit" name="estado" value="rechazada" class="boton-secundario">Rechazar</button><?php endif; ?>
                    <?php if ($m['estado'] === 'activa'): ?><button type="submit" name="estado" value="retirada" class="boton-peligro">Retirar</button><?php endif; ?>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="panel-seccion">
    <h2>Renovar matrículas para el <?= (int) ($anio['anio'] ?? 0) ?></h2>
    <p class="texto-suave">
        Estudiantes activos que aún no tienen matrícula en este año (<?= count($elegibles) ?>). Marca los que continúan y elige el grado para el que se matriculan;
        quedarán <strong>activos</strong> y después les asignas grupo arriba. También puedes abrir la <em>renovación para acudientes</em> en Años lectivos.
    </p>
    <?php if (!$elegibles): ?>
        <div class="estado-vacio"><p>No hay estudiantes pendientes de renovar.</p></div>
    <?php else: ?>
    <form action="matriculas.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="renovar"><input type="hidden" name="anio" value="<?= $anioSel ?>">
        <p><label><input type="checkbox" id="marcar-todos"> Marcar todos</label></p>
        <div class="acad-tabla-envoltura">
            <table class="acad-tabla">
                <thead><tr><th></th><th>Estudiante</th><th>Grado actual</th><th>Se matricula en</th></tr></thead>
                <tbody>
                <?php foreach ($elegibles as $s): ?>
                    <tr>
                        <td><input type="checkbox" name="renovar[<?= (int) $s['id'] ?>]" value="1" class="marca-renovar" aria-label="Renovar a <?= $e($s['nombre']) ?>"></td>
                        <td><?= $e($s['nombre']) ?><br><small class="texto-suave"><?= $e($s['correo']) ?></small></td>
                        <td><?= $s['grado'] ? $e($s['grado'] . ' ' . $s['grupo']) : '<em>sin grupo</em>' ?></td>
                        <td>
                            <select name="grado[<?= (int) $s['id'] ?>]" aria-label="Grado para <?= $e($s['nombre']) ?>">
                                <?php foreach ($grados as $g): ?><option value="<?= (int) $g['id'] ?>" <?= (int) $s['grado_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= $e($g['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p><button type="submit" onclick="return confirm('¿Renovar la matrícula de los estudiantes marcados?');">Renovar seleccionados</button></p>
    </form>
    <script>
        document.getElementById('marcar-todos').addEventListener('change', function () {
            var m = this.checked;
            Array.prototype.forEach.call(document.querySelectorAll('.marca-renovar'), function (c) { c.checked = m; });
        });
    </script>
    <?php endif; ?>
</section>

<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
