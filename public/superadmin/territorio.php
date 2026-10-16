<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
require_once __DIR__ . '/../../src/modelos/Colegio.php';
require_once __DIR__ . '/../../src/modelos/Territorio.php';

// Organización territorial: Secretaría → Municipio → Núcleo educativo → Colegio (y zonas: comunas, corregimientos, localidades).
//  · superadmin: importa secretarías, municipios, núcleos y zonas de todo el país.
//  · secretaria: gestiona los núcleos y zonas de SU secretaría.
requiereRol('superadmin', 'secretaria');
@set_time_limit(120);

$esSuper = esSuperadmin();
$secId = $esSuper ? null : secretariaIdActual($pdo);
$migrado = Colegio::tieneTerritorio($pdo);
$sinSecretaria = !$esSuper && $secId === null;

$flash = $_SESSION['flash_territorio'] ?? null;
unset($_SESSION['flash_territorio']);

/** Valida el archivo subido. Devuelve un mensaje de error o '' si está bien. */
function errorArchivoCsv(array $archivos, string $campo): string
{
    $tmp = $archivos[$campo]['tmp_name'] ?? '';
    $nombre = (string) ($archivos[$campo]['name'] ?? '');
    if (!$tmp || !is_uploaded_file($tmp)) {
        return 'Sube un archivo CSV.';
    }
    if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'csv') {
        return 'El archivo debe tener extensión .csv (en Excel: Guardar como → CSV).';
    }
    if (($archivos[$campo]['size'] ?? 0) > 2 * 1024 * 1024) {
        return 'El archivo pesa más de 2 MB.';
    }
    return '';
}

/** Ejecuta un importador dentro de una transacción y arma el mensaje de resultado. */
function ejecutarImportacion(PDO $pdo, string $tipo, array $filas, ?int $secId): array
{
    $f = ['exito' => '', 'error' => '', 'omitidas' => []];
    $requeridas = [
        'secretarias' => ['nombre', 'departamento'],
        'municipios' => ['departamento', 'municipio'],
        'nucleos' => $secId === null ? ['nombre', 'secretaria'] : ['nombre'],
        'zonas' => ['municipio', 'nombre'],
    ][$tipo] ?? [];
    $faltan = Territorio::columnasFaltantes($filas, $requeridas);
    if ($faltan) {
        $f['error'] = 'Faltan columnas en el encabezado: ' . implode(', ', $faltan) . '. Descarga el CSV de ejemplo para ver el formato.';
        return $f;
    }
    if (!$filas) {
        $f['error'] = 'El archivo no tiene filas con datos.';
        return $f;
    }
    try {
        $pdo->beginTransaction();
        if ($tipo === 'secretarias') {
            $r = Territorio::importarSecretarias($pdo, $filas);
        } elseif ($tipo === 'municipios') {
            $r = Territorio::importarMunicipios($pdo, $filas);
        } elseif ($tipo === 'nucleos') {
            $r = Territorio::importarNucleos($pdo, $filas, $secId);
        } else {
            $r = Territorio::importarZonas($pdo, $filas, $secId);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $f['error'] = 'No se pudo importar (no se guardó nada): ' . $e->getMessage();
        return $f;
    }
    $etiqueta = ['secretarias' => 'Secretarías', 'municipios' => 'Municipios', 'nucleos' => 'Núcleos', 'zonas' => 'Zonas'][$tipo];
    $f['exito'] = "{$etiqueta}: {$r['ok']} procesada(s), {$r['nuevos']} nueva(s)."
        . (isset($r['municipios']) ? " {$r['municipios']} municipio(s) autónomo(s) enlazado(s)." : '')
        . (!empty($r['colegios']) ? " {$r['colegios']} colegio(s) heredaron su secretaría." : '');
    $f['omitidas'] = array_slice($r['omitidas'], 0, 60);
    if (count($r['omitidas']) > 60) {
        $f['omitidas'][] = '… y ' . (count($r['omitidas']) - 60) . ' fila(s) más omitidas.';
    }
    auditarAccion($pdo, 'importar_territorio_' . $tipo, 'territorio', 0, [], ['procesadas' => $r['ok'], 'nuevas' => $r['nuevos'], 'omitidas' => count($r['omitidas'])]);
    return $f;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'error' => '', 'omitidas' => []];
    $tipo = (string) ($_POST['tipo'] ?? '');

    if (!$migrado) {
        $f['error'] = 'Primero ejecuta sql/migraciones/033_territorio.sql.';
    } elseif ($sinSecretaria) {
        $f['error'] = 'Tu usuario no tiene una secretaría activa asignada.';
    } elseif ($accion === 'cargar_oficial') {
        if (!$esSuper) {
            $f['error'] = 'Solo el equipo de Serena puede cargar el listado de secretarías.';
        } else {
            [$filas, $err] = Territorio::leerCsv(__DIR__ . '/../../sql/datos/secretarias_certificadas.csv');
            $f = $err !== '' ? ['exito' => '', 'error' => $err, 'omitidas' => []] : ejecutarImportacion($pdo, 'secretarias', $filas, null);
        }
    } elseif ($accion === 'importar') {
        if (!in_array($tipo, ['secretarias', 'municipios', 'nucleos', 'zonas'], true)) {
            $f['error'] = 'Tipo de importación no válido.';
        } elseif (in_array($tipo, ['secretarias', 'municipios'], true) && !$esSuper) {
            $f['error'] = 'Solo el equipo de Serena puede importar ese catálogo.';
        } elseif (($e = errorArchivoCsv($_FILES, 'csv')) !== '') {
            $f['error'] = $e;
        } else {
            [$filas, $err] = Territorio::leerCsv((string) $_FILES['csv']['tmp_name']);
            $f = $err !== '' && !$filas ? ['exito' => '', 'error' => $err, 'omitidas' => []] : ejecutarImportacion($pdo, $tipo, $filas, $secId);
            if ($err !== '' && $f['error'] === '') {
                $f['omitidas'][] = $err;
            }
        }
    } elseif ($accion === 'crear_nucleo' || $accion === 'crear_zona') {
        // Alta manual: se reutiliza el importador con una sola fila, para validar igual que el CSV.
        $destino = $esSuper ? (int) ($_POST['secretaria_id'] ?? 0) : (int) $secId;
        if ($accion === 'crear_nucleo') {
            if ($destino <= 0) {
                $f['error'] = 'Elige la secretaría del núcleo.';
            } else {
                $r = Territorio::importarNucleos($pdo, [[
                    '_n' => '1', 'nombre' => trim((string) ($_POST['nombre'] ?? '')), 'municipio' => trim((string) ($_POST['municipio'] ?? '')),
                    'departamento' => '', 'codigo' => trim((string) ($_POST['codigo'] ?? '')), 'secretaria' => '',
                ]], $destino);
                $f['exito'] = $r['ok'] ? ($r['nuevos'] ? 'Núcleo creado.' : 'Ese núcleo ya existía: se actualizó.') : '';
                $f['error'] = $r['ok'] ? '' : ($r['omitidas'][0] ?? 'No se pudo crear el núcleo.');
            }
        } else {
            $r = Territorio::importarZonas($pdo, [[
                '_n' => '1', 'municipio' => trim((string) ($_POST['municipio'] ?? '')), 'departamento' => '',
                'tipo' => (string) ($_POST['tipo_zona'] ?? 'comuna'), 'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            ]], $esSuper ? null : $secId);
            $f['exito'] = $r['ok'] ? ($r['nuevos'] ? 'Zona creada.' : 'Esa zona ya existía.') : '';
            $f['error'] = $r['ok'] ? '' : ($r['omitidas'][0] ?? 'No se pudo crear la zona.');
        }
        if ($f['exito'] !== '') {
            auditarAccion($pdo, $accion . '_territorio', 'territorio', 0, [], ['nombre' => (string) ($_POST['nombre'] ?? '')]);
        }
    } elseif ($accion === 'eliminar') {
        $que = (string) ($_POST['que'] ?? '');
        $ok = Territorio::eliminar($pdo, $que, (int) ($_POST['id'] ?? 0), $secId);
        $f[$ok ? 'exito' : 'error'] = $ok ? 'Eliminado. Los colegios que lo usaban quedaron sin ese dato.' : 'No se pudo eliminar (no existe o no es de tu secretaría).';
        if ($ok) {
            auditarAccion($pdo, 'eliminar_' . $que . '_territorio', 'territorio', (int) ($_POST['id'] ?? 0), [], []);
        }
    }
    $_SESSION['flash_territorio'] = $f;
    header('Location: territorio.php');
    exit;
}

$resumen = $migrado && !$sinSecretaria ? Territorio::resumen($pdo, $secId) : null;
$arbol = $migrado && !$sinSecretaria ? Territorio::arbol($pdo, $secId) : [];
$todasSecretarias = ($migrado && $esSuper) ? $pdo->query('SELECT id, nombre FROM secretarias ORDER BY nombre')->fetchAll() : [];
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Territorio';
$cssExtra = ['/assets/css/territorio.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Territorio: secretarías, municipios, núcleos y zonas</h1>
<p class="texto-suave">
    Organiza los colegios así: <strong>Secretaría → Municipio → Núcleo educativo → Colegio</strong>, y cada colegio puede estar en una
    <strong>comuna, corregimiento, localidad o vereda</strong>. Los colegios se asignan a un núcleo y a una zona desde
    <em>Colegios → Editar colegio</em>, o con las columnas <code>nucleo</code> y <code>zona</code> del CSV de colegios.
</p>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if ($flash && !empty($flash['omitidas'])): ?>
    <div class="mensaje mensaje-aviso">
        <strong>Algunas filas se omitieron:</strong>
        <ul class="lista-simple"><?php foreach ($flash['omitidas'] as $o): ?><li><?= $e($o) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if (!$migrado): ?>
    <p class="mensaje mensaje-aviso">Falta aplicar <code>sql/migraciones/033_territorio.sql</code> en la base de datos. Después recarga esta página.</p>
<?php elseif ($sinSecretaria): ?>
    <p class="mensaje mensaje-error">Tu usuario no tiene una secretaría activa asignada.</p>
<?php else: ?>

<section class="panel-seccion">
    <h2>Resumen</h2>
    <div class="terr-resumen">
        <?php if ($esSuper): ?><div class="terr-dato"><strong><?= $resumen['secretarias'] ?></strong><span>Secretarías</span></div><?php endif; ?>
        <div class="terr-dato"><strong><?= $resumen['municipios'] ?></strong><span>Municipios</span></div>
        <div class="terr-dato"><strong><?= $resumen['nucleos'] ?></strong><span>Núcleos</span></div>
        <div class="terr-dato"><strong><?= $resumen['zonas'] ?></strong><span>Zonas</span></div>
        <div class="terr-dato"><strong><?= $resumen['colegios'] ?></strong><span>Colegios</span></div>
        <?php if ($esSuper): ?><div class="terr-dato <?= $resumen['sin_secretaria'] ? 'terr-alerta' : '' ?>"><strong><?= $resumen['sin_secretaria'] ?></strong><span>Colegios sin secretaría</span></div><?php endif; ?>
        <div class="terr-dato <?= $resumen['sin_nucleo'] ? 'terr-alerta' : '' ?>"><strong><?= $resumen['sin_nucleo'] ?></strong><span>Colegios sin núcleo</span></div>
        <div class="terr-dato <?= $resumen['sin_zona'] ? 'terr-alerta' : '' ?>"><strong><?= $resumen['sin_zona'] ?></strong><span>Colegios sin zona</span></div>
    </div>
</section>

<?php if ($esSuper): ?>
<section class="panel-seccion">
    <h2>1. Secretarías de Educación certificadas</h2>
    <p class="terr-aviso-cifra">
        Incluí el listado que me compartiste: <strong>32 secretarías departamentales y 61 municipales/distritales (93)</strong>.
        Ese documento menciona 97, así que <strong>faltan 4</strong> (por ejemplo, creo que Soacha, Tuluá y Tumaco son certificadas, pero verifícalo con el directorio del MEN).
        Agrégalas con un CSV o desde <em>Secretarías</em>. Si ya existe una (p. ej. la de Medellín), se conserva y solo se completan su departamento y tipo.
    </p>
    <div class="terr-importar">
        <form action="territorio.php" method="POST" onsubmit="return confirm('¿Cargar las 93 secretarías del listado incluido? Las que ya existan se conservan.');">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="cargar_oficial">
            <p class="texto-suave">Carga el listado incluido en Serena (los municipios autónomos quedan enlazados a su secretaría).</p>
            <button type="submit">Cargar listado incluido (93)</button>
        </form>
        <form action="territorio.php" method="POST" enctype="multipart/form-data" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="importar">
            <input type="hidden" name="tipo" value="secretarias">
            <p class="texto-suave">O sube tu CSV: <code>departamento</code>, <code>tipo</code>, <code>nombre</code>, <code>municipio</code>.</p>
            <input type="file" name="csv" accept=".csv" required>
            <button type="submit" class="boton-secundario">Importar secretarías</button>
            <a href="territorio_ejemplo.php?tipo=secretarias">CSV de ejemplo</a>
        </form>
    </div>
</section>

<section class="panel-seccion">
    <h2>2. Municipios</h2>
    <p class="texto-suave">
        Sube el listado oficial <strong>DIVIPOLA</strong> del DANE (departamento, municipio y código) guardado como CSV.
        Cada municipio queda con su secretaría: la propia si es autónomo, o la departamental si no está certificado.
        Importa primero las secretarías. Hasta 5.000 filas por archivo.
    </p>
    <form action="territorio.php" method="POST" enctype="multipart/form-data" novalidate>
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="importar">
        <input type="hidden" name="tipo" value="municipios">
        <p class="texto-suave">Columnas: <code>departamento</code>, <code>municipio</code>, <code>codigo_dane</code> (opcional).</p>
        <input type="file" name="csv" accept=".csv" required>
        <button type="submit">Importar municipios</button>
        <a href="territorio_ejemplo.php?tipo=municipios">CSV de ejemplo</a>
    </form>
</section>
<?php endif; ?>

<section class="panel-seccion">
    <h2><?= $esSuper ? '3.' : '1.' ?> Núcleos educativos</h2>
    <div class="terr-importar">
        <form action="territorio.php" method="POST" enctype="multipart/form-data" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="importar">
            <input type="hidden" name="tipo" value="nucleos">
            <p class="texto-suave">
                CSV con <?= $esSuper ? '<code>secretaria</code>, ' : '' ?><code>nombre</code>, <code>municipio</code> (opcional; vacío si el núcleo cubre varios),
                <code>departamento</code> (opcional, si el municipio se repite) y <code>codigo</code> (opcional).
            </p>
            <input type="file" name="csv" accept=".csv" required>
            <button type="submit">Importar núcleos</button>
            <a href="territorio_ejemplo.php?tipo=nucleos">CSV de ejemplo</a>
        </form>
        <form action="territorio.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="crear_nucleo">
            <p class="texto-suave">O agrega uno a mano:</p>
            <?php if ($esSuper): ?>
                <label>Secretaría
                    <select name="secretaria_id" required>
                        <option value="">Elige…</option>
                        <?php foreach ($todasSecretarias as $s): ?><option value="<?= (int) $s['id'] ?>"><?= $e($s['nombre']) ?></option><?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>Nombre del núcleo <input type="text" name="nombre" maxlength="120" required></label>
            <label>Municipio (opcional) <input type="text" name="municipio" maxlength="80"></label>
            <label>Código (opcional) <input type="text" name="codigo" maxlength="20"></label>
            <button type="submit" class="boton-secundario">Agregar núcleo</button>
        </form>
    </div>
</section>

<section class="panel-seccion">
    <h2><?= $esSuper ? '4.' : '2.' ?> Comunas, corregimientos, localidades y veredas</h2>
    <div class="terr-importar">
        <form action="territorio.php" method="POST" enctype="multipart/form-data" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="importar">
            <input type="hidden" name="tipo" value="zonas">
            <p class="texto-suave">
                CSV con <code>municipio</code>, <code>tipo</code> (comuna, corregimiento, localidad o vereda), <code>nombre</code>
                y <code>departamento</code> (opcional). El municipio debe existir.
            </p>
            <input type="file" name="csv" accept=".csv" required>
            <button type="submit">Importar zonas</button>
            <a href="territorio_ejemplo.php?tipo=zonas">CSV de ejemplo</a>
        </form>
        <form action="territorio.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="crear_zona">
            <p class="texto-suave">O agrega una a mano:</p>
            <label>Municipio <input type="text" name="municipio" maxlength="80" required></label>
            <label>Tipo
                <select name="tipo_zona">
                    <?php foreach (Territorio::TIPOS_ZONA as $clave => $texto): ?><option value="<?= $e($clave) ?>"><?= $e($texto) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Nombre <input type="text" name="nombre" maxlength="100" required></label>
            <button type="submit" class="boton-secundario">Agregar zona</button>
        </form>
    </div>
</section>

<section class="panel-seccion terr-arbol">
    <h2>Organización actual</h2>
    <?php if (!$arbol): ?>
        <p class="texto-suave">Todavía no hay secretarías cargadas.</p>
    <?php endif; ?>
    <?php foreach ($arbol as $s): ?>
        <details>
            <summary>
                <?= $e($s['nombre']) ?>
                <span class="terr-meta">
                    · <?= $e(Territorio::TIPOS_SECRETARIA[$s['tipo']] ?? $s['tipo']) ?><?= $s['departamento'] ? ' · ' . $e($s['departamento']) : '' ?>
                    · <?= count($s['municipios']) ?> municipio(s) · <?= count($s['nucleos']) ?> núcleo(s) · <?= (int) $s['colegios'] ?> colegio(s)
                    <?= (int) $s['activa'] === 1 ? '' : '· inactiva' ?>
                </span>
            </summary>
            <div class="terr-cuerpo">
                <h3 class="terr-sub">Núcleos educativos</h3>
                <?php if (!$s['nucleos']): ?><p class="texto-suave">Sin núcleos.</p><?php else: ?>
                    <ul class="terr-lista">
                        <?php foreach ($s['nucleos'] as $n): ?>
                            <li>
                                <strong><?= $e($n['nombre']) ?></strong>
                                <span class="terr-meta"><?= $n['municipio'] ? $e($n['municipio']) . ' · ' : '' ?><?= (int) $n['colegios'] ?> colegio(s)</span>
                                <form action="territorio.php" method="POST" class="terr-form-x" onsubmit="return confirm('¿Eliminar este núcleo?');">
                                    <?php campoCSRF(); ?>
                                    <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="que" value="nucleo"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                    <button type="submit" class="terr-x" aria-label="Eliminar el núcleo <?= $e($n['nombre']) ?>">×</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3 class="terr-sub">Municipios y zonas</h3>
                <?php if (!$s['municipios']): ?><p class="texto-suave">Sin municipios asignados.</p><?php else: ?>
                    <ul class="terr-lista">
                        <?php foreach ($s['municipios'] as $m): ?>
                            <li style="display:block">
                                <strong><?= $e($m['nombre']) ?></strong>
                                <span class="terr-meta"><?= $e($m['departamento']) ?> · <?= (int) $m['colegios'] ?> colegio(s)</span>
                                <?php if ($m['zonas']): ?>
                                    <div class="terr-chips">
                                        <?php foreach ($m['zonas'] as $z): ?>
                                            <span class="terr-chip">
                                                <?= $e(Territorio::TIPOS_ZONA[$z['tipo']] ?? $z['tipo']) ?> <?= $e($z['nombre']) ?> (<?= (int) $z['colegios'] ?>)
                                                <form action="territorio.php" method="POST" class="terr-form-x" onsubmit="return confirm('¿Eliminar esta zona?');">
                                                    <?php campoCSRF(); ?>
                                                    <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="que" value="zona"><input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
                                                    <button type="submit" class="terr-x" aria-label="Eliminar la zona <?= $e($z['nombre']) ?>">×</button>
                                                </form>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </details>
    <?php endforeach; ?>
</section>

<?php endif; ?>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
