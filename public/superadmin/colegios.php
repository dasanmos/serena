<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/helpers/mapas.php';
require_once __DIR__ . '/../../src/modelos/Colegio.php';
require_once __DIR__ . '/../../src/modelos/Territorio.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';

require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

/** Guarda núcleo y zona del colegio desde el formulario (solo si ya se aplicó la migración 033 y el formulario los trae). */
function guardarTerritorioDesdePost(PDO $pdo, int $colegioId): void
{
    if (!Colegio::tieneTerritorio($pdo) || !array_key_exists('nucleo_id', $_POST)) {
        return;
    }
    $alcance = esSecretaria() ? secretariaIdActual($pdo) : null;
    $nucleo = (int) ($_POST['nucleo_id'] ?? 0) ?: null;
    $zona = (int) ($_POST['zona_id'] ?? 0) ?: null;
    if ($nucleo !== null && !Territorio::nucleoPermitido($pdo, $nucleo, $alcance)) {
        $nucleo = null;
    }
    if ($zona !== null && !Territorio::zonaPermitida($pdo, $zona, $alcance)) {
        $zona = null;
    }
    Territorio::asignarAColegio($pdo, $colegioId, $nucleo, $zona);
}

/** Selects «Núcleo educativo» y «Zona» (comuna / corregimiento / localidad / vereda) para los formularios del colegio. */
function selectsTerritorioHtml(array $nucleos, array $zonas, ?int $nucleoSel, ?int $zonaSel, bool $conSecretaria): string
{
    $e = static fn ($v): string => htmlspecialchars((string) $v);
    $h = '<label>Núcleo educativo <select name="nucleo_id"><option value="0">— Sin núcleo —</option>';
    foreach ($nucleos as $n) {
        $texto = ($conSecretaria ? $n['secretaria_nombre'] . ' · ' : '') . $n['nombre'] . ($n['municipio_nombre'] ? ' (' . $n['municipio_nombre'] . ')' : '');
        $h .= '<option value="' . (int) $n['id'] . '"' . ($nucleoSel === (int) $n['id'] ? ' selected' : '') . '>' . $e($texto) . '</option>';
    }
    $h .= '</select></label>';
    $h .= '<label>Comuna, corregimiento, localidad o vereda <select name="zona_id"><option value="0">— Sin zona —</option>';
    $actual = null;
    foreach ($zonas as $z) {
        if ($actual !== $z['municipio_nombre']) {
            $h .= $actual !== null ? '</optgroup>' : '';
            $h .= '<optgroup label="' . $e($z['municipio_nombre']) . '">';
            $actual = $z['municipio_nombre'];
        }
        $h .= '<option value="' . (int) $z['id'] . '"' . ($zonaSel === (int) $z['id'] ? ' selected' : '') . '>'
            . $e((Territorio::TIPOS_ZONA[$z['tipo']] ?? $z['tipo']) . ' ' . $z['nombre']) . '</option>';
    }
    $h .= $actual !== null ? '</optgroup>' : '';
    return $h . '</select></label>';
}

$error = '';
$exito = '';
$credencial = null; // contraseña temporal del administrador recién creado (se muestra UNA vez)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_colegio') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $dane = (string) ($_POST['dane'] ?? '');
        $municipio = mb_substr(trim((string) ($_POST['municipio'] ?? '')), 0, 80);
        // Una secretaría crea colegios solo dentro de su secretaría; el superadmin elige (o deja el del municipio).
        $secretariaNueva = esSecretaria() ? secretariaIdActual($pdo) : ((int) ($_POST['secretaria_id'] ?? 0) ?: null);
        if ($nombre === '' || mb_strlen($nombre) > 150) {
            $error = 'Escribe el nombre del colegio (máximo 150 caracteres).';
        } elseif (esSecretaria() && $secretariaNueva === null) {
            $error = 'Tu usuario no tiene una secretaría activa asignada.';
        } else {
            try {
                $id = Colegio::crear($pdo, $nombre, $dane, $municipio, $secretariaNueva);
                Colegio::guardarUbicacion($pdo, $id, textoCortoONulo($_POST['direccion'] ?? '', 200), coordenadasDesdePost($_POST['latitud'] ?? '', $_POST['longitud'] ?? ''));
                guardarTerritorioDesdePost($pdo, $id);
                auditarAccion($pdo, 'crear_colegio', 'colegios', $id, [], ['nombre' => $nombre, 'dane' => Colegio::normalizarDane($dane)]);
                $exito = 'Colegio creado. Sus estudiantes se registran con el DANE ' . Colegio::normalizarDane($dane) . '. Ahora crea su administrador.';
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($accion === 'editar_colegio') {
        $id = (int) ($_POST['colegio_id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $colegio = Colegio::buscarPorId($pdo, $id);
        if (!$colegio || $nombre === '' || !colegioEnAlcance($pdo, $id)) {
            $error = 'Colegio o nombre no válido.';
        } else {
            try {
                Colegio::actualizar($pdo, $id, $nombre, (string) ($_POST['dane'] ?? ''), mb_substr(trim((string) ($_POST['municipio'] ?? '')), 0, 80), isset($_POST['activo']));
                Colegio::guardarUbicacion($pdo, $id, textoCortoONulo($_POST['direccion'] ?? '', 200), coordenadasDesdePost($_POST['latitud'] ?? '', $_POST['longitud'] ?? ''));
                if (esSuperadmin() && isset($_POST['secretaria_id'])) {
                    Colegio::asignarSecretaria($pdo, $id, (int) $_POST['secretaria_id'] ?: null);
                }
                guardarTerritorioDesdePost($pdo, $id);
                auditarAccion($pdo, 'editar_colegio', 'colegios', $id,
                    ['nombre' => $colegio['nombre'], 'dane' => $colegio['dane'], 'activo' => (int) $colegio['activo']],
                    ['nombre' => $nombre, 'dane' => Colegio::normalizarDane((string) ($_POST['dane'] ?? '')), 'activo' => isset($_POST['activo']) ? 1 : 0]);
                $exito = 'Colegio actualizado.';
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($accion === 'masivo') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($i) => $i > 0 && colegioEnAlcance($pdo, $i))));
        $op = $_POST['operacion'] ?? '';
        if (!$ids || !in_array($op, ['suspender', 'activar'], true)) {
            $error = 'Marca al menos un colegio y elige una acción.';
        } else {
            $nuevo = $op === 'activar' ? 1 : 0;
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE colegios SET activo = ? WHERE id IN ($in)")->execute(array_merge([$nuevo], $ids));
            if ($nuevo === 0 && !empty($_SESSION['colegio_contexto']) && in_array((int) $_SESSION['colegio_contexto'], $ids, true)) {
                unset($_SESSION['colegio_contexto']);
            }
            auditarAccion($pdo, $op === 'activar' ? 'activar_colegios_masivo' : 'suspender_colegios_masivo', 'colegios', 0, [], ['ids' => $ids]);
            $exito = count($ids) . ($op === 'activar' ? ' colegio(s) activado(s).' : ' colegio(s) suspendido(s).');
        }
    } elseif ($accion === 'crear_admin') {
        $colegioId = (int) ($_POST['colegio_id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? ''));
        if (!Colegio::buscarPorId($pdo, $colegioId) || !colegioEnAlcance($pdo, $colegioId)) {
            $error = 'Colegio no válido.';
        } elseif ($nombre === '' || !validarCorreo($correo)) {
            $error = 'Escribe un nombre y un correo válido.';
        } elseif (Usuario::correoExiste($pdo, $correo)) {
            $error = 'Ya existe una cuenta con ese correo.';
        } else {
            $rolAdmin = (int) $pdo->query("SELECT id FROM roles WHERE nombre = 'administrador'")->fetchColumn();
            $temporal = generarPasswordTemporalValida();
            $pdo->prepare(
                'INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, consentimiento_datos, fecha_consentimiento, fecha_registro)
                 VALUES (?,?,?,?,?,1,NOW(),NOW())'
            )->execute([$nombre, $correo, password_hash($temporal, PASSWORD_DEFAULT), $rolAdmin, $colegioId]);
            auditarAccion($pdo, 'crear_admin_colegio', 'usuarios', (int) $pdo->lastInsertId(), [], ['correo' => $correo, 'colegio_id' => $colegioId]);
            $credencial = ['correo' => $correo, 'password' => $temporal];
            $exito = 'Administrador creado. Entrégale esta contraseña temporal por un canal seguro: no se vuelve a mostrar.';
        }
    }
}

$colegios = Colegio::listarTodos($pdo);
$secretarias = esSuperadmin() ? $pdo->query('SELECT id, nombre FROM secretarias ORDER BY nombre')->fetchAll() : [];
$alcanceTerritorio = esSecretaria() ? secretariaIdActual($pdo) : null;
$hayTerritorio = Colegio::tieneTerritorio($pdo);
$nucleosOpciones = $hayTerritorio ? Territorio::nucleos($pdo, $alcanceTerritorio) : [];
$zonasOpciones = $hayTerritorio ? Territorio::zonas($pdo, $alcanceTerritorio) : [];
$busqueda = trim((string) ($_GET['q'] ?? ''));
$estadoFiltro = (string) ($_GET['estado'] ?? '');
$colegios = array_values(array_filter($colegios, function ($c) use ($busqueda, $estadoFiltro) {
    if ($estadoFiltro === 'activo' && (int) $c['activo'] !== 1) return false;
    if ($estadoFiltro === 'suspendido' && (int) $c['activo'] === 1) return false;
    if ($busqueda !== '') {
        $texto = mb_strtolower($c['nombre'] . ' ' . $c['dane'] . ' ' . ($c['municipio'] ?? ''));
        if (mb_strpos($texto, mb_strtolower($busqueda)) === false) return false;
    }
    return true;
}));

// Datos para el mapa (solo los colegios de esta vista; ya vienen filtrados por alcance y por la búsqueda).
$datosMapa = [];
$sinUbicar = [];
foreach ($colegios as $c) {
    if ($c['latitud'] !== null && $c['longitud'] !== null) {
        $datosMapa[] = [
            'id' => (int) $c['id'], 'nombre' => (string) $c['nombre'], 'dane' => (string) $c['dane'],
            'municipio' => (string) ($c['municipio'] ?? ''), 'direccion' => (string) ($c['direccion'] ?? ''),
            'lat' => (float) $c['latitud'], 'lng' => (float) $c['longitud'],
            'activo' => (int) $c['activo'] === 1, 'estudiantes' => (int) $c['estudiantes_activos'],
        ];
    } else {
        $sinUbicar[] = (string) $c['nombre'];
    }
}

$tituloPagina = 'Colegios';
$cssExtra = ['/assets/css/mapas.css'];
$jsExtra  = ['/assets/js/mapas.js'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Colegios</h1>

<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>
<?php if ($credencial): ?>
    <p class="mensaje mensaje-info">
        Correo: <strong><?= htmlspecialchars($credencial['correo']) ?></strong><br>
        Contraseña temporal: <code><?= htmlspecialchars($credencial['password']) ?></code>
    </p>
<?php endif; ?>

<section class="panel-seccion">
    <h2>Mapa de colegios</h2>
    <p class="texto-suave">
        <?= count($datosMapa) ?> colegio(s) ubicado(s) en el mapa<?= $sinUbicar ? ' · ' . count($sinUbicar) . ' sin ubicar (escribe su dirección en «Editar colegio»)' : '' ?>.
        Se muestran los de la búsqueda actual.
    </p>
    <div class="mapa-bloque" data-mapa="colegios">
        <div class="mapa-lienzo" aria-label="Mapa con un pin por colegio"></div>
        <script type="application/json" class="mapa-datos"><?= jsonParaHtml($datosMapa) ?></script>
    </div>
    <?php if ($sinUbicar): ?>
    <details>
        <summary>Colegios sin ubicar (<?= count($sinUbicar) ?>)</summary>
        <ul class="lista-simple"><?php foreach ($sinUbicar as $nombreSin): ?><li><?= htmlspecialchars($nombreSin) ?></li><?php endforeach; ?></ul>
    </details>
    <?php endif; ?>
</section>

<section class="panel-seccion">
    <h2>Nuevo colegio</h2>
    <form action="colegios.php" method="POST" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="crear_colegio">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" maxlength="150" required>
        <label for="dane">Código DANE (12 dígitos) — es el código con el que sus estudiantes se registran</label>
        <input type="text" id="dane" name="dane" maxlength="14" inputmode="numeric" required>
        <label for="municipio">Municipio</label>
        <input type="text" id="municipio" name="municipio" maxlength="80" value="Medellín">
        <label for="direccion">Dirección (el mapa la ubica; después puedes mover el pin)</label>
        <input type="text" id="direccion" name="direccion" maxlength="200" placeholder="Ej.: Carrera 50 # 24-15">
        <input type="hidden" name="latitud" value="">
        <input type="hidden" name="longitud" value="">
        <?= mapaSelectorHtml() ?>
        <?php if ($hayTerritorio): ?><?= selectsTerritorioHtml($nucleosOpciones, $zonasOpciones, null, null, esSuperadmin()) ?><?php endif; ?>
        <?php if (esSuperadmin()): ?>
        <label for="secretaria_id">Secretaría de Educación (si lo dejas vacío, hereda la del municipio)</label>
        <select id="secretaria_id" name="secretaria_id">
            <option value="0">— Según el municipio —</option>
            <?php foreach ($secretarias as $sec): ?><option value="<?= (int) $sec['id'] ?>"><?= htmlspecialchars($sec['nombre']) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit">Crear colegio</button>
    </form>
</section>

<section class="panel-seccion">
    <h2>Buscar y acciones masivas</h2>
    <form action="colegios.php" method="GET" novalidate>
        <label>Buscar (nombre, DANE o municipio) <input type="text" name="q" value="<?= htmlspecialchars($busqueda) ?>"></label>
        <label>Estado
            <select name="estado">
                <option value="">Todos</option>
                <option value="activo" <?= $estadoFiltro === 'activo' ? 'selected' : '' ?>>Activos</option>
                <option value="suspendido" <?= $estadoFiltro === 'suspendido' ? 'selected' : '' ?>>Suspendidos</option>
            </select>
        </label>
        <button type="submit">Filtrar</button>
        <a href="colegios_exportar.php" class="boton-secundario">Exportar CSV</a>
        <a href="colegios_importar.php" class="boton-secundario">Importar CSV</a>
    </form>
    <p class="texto-suave"><?= count($colegios) ?> colegio(s) en esta vista.</p>
    <?php if ($colegios): ?>
    <form action="colegios.php" method="POST" novalidate onsubmit="return confirm('¿Aplicar la acción a los colegios marcados?');">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="masivo">
        <table class="tabla-datos">
            <thead><tr><th><input type="checkbox" aria-label="Marcar todos" onclick="document.querySelectorAll('.chk-colegio').forEach(c => c.checked = this.checked)"></th><th>Colegio</th><th>DANE</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($colegios as $c): ?>
                <tr>
                    <td><input type="checkbox" class="chk-colegio" name="ids[]" value="<?= (int) $c['id'] ?>"></td>
                    <td><?= htmlspecialchars($c['nombre']) ?></td>
                    <td><?= htmlspecialchars($c['dane']) ?></td>
                    <td><?= (int) $c['activo'] === 1 ? 'Activo' : 'Suspendido' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <label>Acción
            <select name="operacion">
                <option value="suspender">Suspender marcados</option>
                <option value="activar">Activar marcados</option>
            </select>
        </label>
        <button type="submit">Aplicar</button>
    </form>
    <?php endif; ?>
</section>

<?php foreach ($colegios as $c): ?>
<section class="panel-seccion">
    <h2><?= htmlspecialchars($c['nombre']) ?> <?= (int) $c['activo'] === 1 ? '' : '(suspendido)' ?></h2>
    <p class="texto-suave">
        Código DANE (registro de estudiantes): <strong><?= htmlspecialchars($c['dane']) ?></strong>
        <?php if (esSuperadmin()): ?>· Secretaría: <strong><?= htmlspecialchars((string) ($c['secretaria_nombre'] ?? 'Sin asignar')) ?></strong><?php endif; ?>
        · <?= (int) $c['estudiantes_activos'] ?> estudiantes · <?= (int) $c['personal_activo'] ?> personal
    </p>
    <p class="texto-suave">
        📍 <?= $c['direccion'] ? htmlspecialchars((string) $c['direccion']) : 'Sin dirección' ?>
        <?php if ($c['latitud'] !== null && $c['longitud'] !== null): ?>
            · <button type="button" class="boton-secundario" data-ver-mapa="<?= (int) $c['id'] ?>">Ver en el mapa</button>
            · <?= mapaComoLlegarHtml((float) $c['latitud'], (float) $c['longitud'], (string) $c['nombre']) ?>
        <?php else: ?>
            · <em>sin ubicar en el mapa</em>
        <?php endif; ?>
    </p>
    <?php if ($hayTerritorio): ?>
    <p class="texto-suave">
        🏫 Núcleo: <?= $c['nucleo_nombre'] ? htmlspecialchars((string) $c['nucleo_nombre']) : '<em>sin núcleo</em>' ?>
        · <?= $c['zona_nombre'] ? htmlspecialchars((Territorio::TIPOS_ZONA[$c['zona_tipo']] ?? 'Zona') . ' ' . $c['zona_nombre']) : '<em>sin comuna/corregimiento</em>' ?>
    </p>
    <?php endif; ?>

    <details>
        <summary>Editar colegio</summary>
        <form action="colegios.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="editar_colegio">
            <input type="hidden" name="colegio_id" value="<?= (int) $c['id'] ?>">
            <label>Nombre <input type="text" name="nombre" maxlength="150" value="<?= htmlspecialchars($c['nombre']) ?>" required></label>
            <label>DANE (12 dígitos) <input type="text" name="dane" maxlength="14" inputmode="numeric" required value="<?= htmlspecialchars($c['dane']) ?>"></label>
            <label>Municipio <input type="text" name="municipio" maxlength="80" value="<?= htmlspecialchars((string) ($c['municipio'] ?? '')) ?>"></label>
            <label>Dirección (el mapa la ubica; después puedes mover el pin) <input type="text" name="direccion" maxlength="200" value="<?= htmlspecialchars((string) ($c['direccion'] ?? '')) ?>"></label>
            <input type="hidden" name="latitud" value="<?= $c['latitud'] !== null ? htmlspecialchars((string) $c['latitud']) : '' ?>">
            <input type="hidden" name="longitud" value="<?= $c['longitud'] !== null ? htmlspecialchars((string) $c['longitud']) : '' ?>">
            <?= mapaSelectorHtml() ?>
            <?php if ($hayTerritorio): ?><?= selectsTerritorioHtml($nucleosOpciones, $zonasOpciones, $c['nucleo_id'] !== null ? (int) $c['nucleo_id'] : null, $c['zona_id'] !== null ? (int) $c['zona_id'] : null, esSuperadmin()) ?><?php endif; ?>
            <?php if (esSuperadmin()): ?>
            <label>Secretaría de Educación
                <select name="secretaria_id">
                    <option value="0">— Sin asignar —</option>
                    <?php foreach ($secretarias as $sec): ?><option value="<?= (int) $sec['id'] ?>" <?= (int) $sec['id'] === (int) ($c['secretaria_id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars($sec['nombre']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <label><input type="checkbox" name="activo" <?= (int) $c['activo'] === 1 ? 'checked' : '' ?>> Colegio activo (si se desmarca, nadie del colegio puede iniciar sesión ni registrarse)</label>
            <button type="submit">Guardar</button>
        </form>
    </details>

    <details>
        <summary>Crear administrador de este colegio</summary>
        <form action="colegios.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="crear_admin">
            <input type="hidden" name="colegio_id" value="<?= (int) $c['id'] ?>">
            <label>Nombre <input type="text" name="nombre" required></label>
            <label>Correo <input type="email" name="correo" required></label>
            <button type="submit">Crear administrador</button>
        </form>
    </details>

</section>
<?php endforeach; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
