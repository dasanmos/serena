<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/mapas.php';

// Lugares de ayuda que ven los estudiantes en «Recursos de apoyo» (hospitales, salud mental, Casa de Justicia...).
//  · administrador → lugares PROPIOS de su colegio.
//  · superadmin    → lugares GLOBALES (los ven los estudiantes de todos los colegios).
requiereRol('administrador', 'superadmin');

$esSuper = esSuperadmin();
$colegioDestino = $esSuper ? null : colegioParaCrear(); // NULL = global
$tipos = lugaresApoyoTipos();
$error = '';
$exito = '';

/** Lee y valida el formulario. Devuelve [datos, error]. */
function lugarDesdePost(array $tipos): array
{
    $nombre = textoCortoONulo($_POST['nombre'] ?? '', 150);
    $tipo = (string) ($_POST['tipo'] ?? 'otro');
    $coords = coordenadasDesdePost($_POST['latitud'] ?? '', $_POST['longitud'] ?? '');
    if ($nombre === null) {
        return [null, 'Escribe el nombre del lugar.'];
    }
    if (!isset($tipos[$tipo])) {
        return [null, 'Elige un tipo de lugar válido.'];
    }
    if ($coords === null) {
        return [null, 'Ubica el lugar en el mapa: escribe la dirección y pulsa «Ubicar la dirección en el mapa», o haz clic en el mapa.'];
    }
    return [[
        'nombre' => $nombre, 'tipo' => $tipo,
        'direccion' => textoCortoONulo($_POST['direccion'] ?? '', 200),
        'municipio' => textoCortoONulo($_POST['municipio'] ?? '', 80),
        'telefono' => textoCortoONulo($_POST['telefono'] ?? '', 40),
        'horario' => textoCortoONulo($_POST['horario'] ?? '', 120),
        'latitud' => $coords[0], 'longitud' => $coords[1],
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ], ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        [$d, $error] = lugarDesdePost($tipos);
        if ($d !== null) {
            $pdo->prepare(
                'INSERT INTO lugares_apoyo (colegio_id, nombre, tipo, direccion, municipio, telefono, horario, latitud, longitud, activo)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$colegioDestino, $d['nombre'], $d['tipo'], $d['direccion'], $d['municipio'], $d['telefono'], $d['horario'], $d['latitud'], $d['longitud'], $d['activo']]);
            auditarAccion($pdo, 'crear_lugar_apoyo', 'lugares_apoyo', (int) $pdo->lastInsertId(), [], ['nombre' => $d['nombre'], 'tipo' => $d['tipo']]);
            $exito = 'Lugar agregado. Ya aparece en el mapa de los estudiantes.';
        }
    } elseif ($accion === 'editar' || $accion === 'eliminar') {
        $id = (int) ($_POST['lugar_id'] ?? 0);
        $q = $pdo->prepare('SELECT * FROM lugares_apoyo WHERE id = ? AND colegio_id <=> ? LIMIT 1');
        $q->execute([$id, $colegioDestino]);
        $actual = $q->fetch();
        if (!$actual) {
            $error = 'Ese lugar no existe o no pertenece a tu alcance.';
        } elseif ($accion === 'eliminar') {
            $pdo->prepare('DELETE FROM lugares_apoyo WHERE id = ? AND colegio_id <=> ?')->execute([$id, $colegioDestino]);
            auditarAccion($pdo, 'eliminar_lugar_apoyo', 'lugares_apoyo', $id, ['nombre' => $actual['nombre']], []);
            $exito = 'Lugar eliminado.';
        } else {
            [$d, $error] = lugarDesdePost($tipos);
            if ($d !== null) {
                $pdo->prepare(
                    'UPDATE lugares_apoyo SET nombre = ?, tipo = ?, direccion = ?, municipio = ?, telefono = ?, horario = ?, latitud = ?, longitud = ?, activo = ?
                     WHERE id = ? AND colegio_id <=> ?'
                )->execute([$d['nombre'], $d['tipo'], $d['direccion'], $d['municipio'], $d['telefono'], $d['horario'], $d['latitud'], $d['longitud'], $d['activo'], $id, $colegioDestino]);
                auditarAccion($pdo, 'editar_lugar_apoyo', 'lugares_apoyo', $id, ['nombre' => $actual['nombre']], ['nombre' => $d['nombre']]);
                $exito = 'Lugar actualizado.';
            }
        }
    }
}

$lista = $pdo->prepare('SELECT * FROM lugares_apoyo WHERE colegio_id <=> ? ORDER BY nombre');
$lista->execute([$colegioDestino]);
$lugares = $lista->fetchAll();

$tituloPagina = 'Lugares de ayuda';
$cssExtra = ['/assets/css/mapas.css'];
$jsExtra  = ['/assets/js/mapas.js'];
require __DIR__ . '/../parciales/encabezado.php';

/** Campos comunes del formulario (crear / editar). */
function camposLugar(array $v, array $tipos): void
{
    ?>
    <label>Nombre <input type="text" name="nombre" maxlength="150" required value="<?= htmlspecialchars((string) ($v['nombre'] ?? '')) ?>"></label>
    <label>Tipo
        <select name="tipo">
            <?php foreach ($tipos as $clave => $texto): ?>
                <option value="<?= htmlspecialchars($clave) ?>" <?= ($v['tipo'] ?? 'otro') === $clave ? 'selected' : '' ?>><?= htmlspecialchars($texto) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Municipio <input type="text" name="municipio" maxlength="80" value="<?= htmlspecialchars((string) ($v['municipio'] ?? '')) ?>"></label>
    <label>Dirección (el mapa la ubica; después puedes mover el pin) <input type="text" name="direccion" maxlength="200" value="<?= htmlspecialchars((string) ($v['direccion'] ?? '')) ?>"></label>
    <label>Teléfono <input type="text" name="telefono" maxlength="40" inputmode="tel" value="<?= htmlspecialchars((string) ($v['telefono'] ?? '')) ?>"></label>
    <label>Horario <input type="text" name="horario" maxlength="120" placeholder="Ej.: Lunes a viernes, 7:00 a.m. – 5:00 p.m." value="<?= htmlspecialchars((string) ($v['horario'] ?? '')) ?>"></label>
    <input type="hidden" name="latitud" value="<?= isset($v['latitud']) ? htmlspecialchars((string) $v['latitud']) : '' ?>">
    <input type="hidden" name="longitud" value="<?= isset($v['longitud']) ? htmlspecialchars((string) $v['longitud']) : '' ?>">
    <?= mapaSelectorHtml() ?>
    <?php
}
?>

<h1>Lugares de ayuda</h1>
<p class="texto-suave">
    Los estudiantes los ven en <strong>Recursos de apoyo</strong>, en un mapa y en una lista.
    <?= $esSuper ? 'Los que cargues aquí son <strong>globales</strong>: los ven los estudiantes de todos los colegios.' : 'Los que cargues aquí los ven solo los estudiantes de tu colegio (además de los globales).' ?>
</p>

<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>

<section class="panel-seccion">
    <h2>Nuevo lugar</h2>
    <form action="lugares_apoyo.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="crear">
        <?php camposLugar(['municipio' => 'Medellín'], $tipos); ?>
        <input type="hidden" name="activo" value="1">
        <button type="submit">Agregar lugar</button>
    </form>
</section>

<section class="panel-seccion">
    <h2>Lugares cargados (<?= count($lugares) ?>)</h2>
    <?php if (!$lugares): ?>
        <p class="texto-suave">Todavía no hay lugares. Agrega el primero arriba.</p>
    <?php endif; ?>
    <?php foreach ($lugares as $l): ?>
        <details>
            <summary>
                <strong><?= htmlspecialchars((string) $l['nombre']) ?></strong>
                · <?= htmlspecialchars($tipos[$l['tipo']] ?? 'Otro') ?>
                <?= (int) $l['activo'] === 1 ? '' : '· (oculto)' ?>
            </summary>
            <form action="lugares_apoyo.php" method="POST" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="lugar_id" value="<?= (int) $l['id'] ?>">
                <?php camposLugar($l, $tipos); ?>
                <label><input type="checkbox" name="activo" <?= (int) $l['activo'] === 1 ? 'checked' : '' ?>> Visible para los estudiantes</label>
                <button type="submit">Guardar cambios</button>
            </form>
            <form action="lugares_apoyo.php" method="POST" novalidate onsubmit="return confirm('¿Eliminar este lugar?');">
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="lugar_id" value="<?= (int) $l['id'] ?>">
                <button type="submit" class="boton-peligro">Eliminar</button>
            </form>
        </details>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
