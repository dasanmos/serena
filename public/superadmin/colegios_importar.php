<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/correo.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Colegio.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Territorio.php';
require_once __DIR__ . '/../../src/helpers/mapas.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

$error = ''; $exito = '';
$importados = 0; $admins = 0;
$omitidos = [];
$credencialesSinEnviar = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $archivo = $_FILES['csv']['tmp_name'] ?? '';
    $nombreOriginal = $_FILES['csv']['name'] ?? '';
    if (!$archivo || !is_uploaded_file($archivo)) {
        $error = 'Sube un archivo CSV.';
    } elseif (strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'El archivo debe tener extensión .csv.';
    } elseif (($_FILES['csv']['size'] ?? 0) > 2 * 1024 * 1024) {
        $error = 'El archivo pesa más de 2 MB.';
    } else {
        $secretariaImport = esSecretaria() ? secretariaIdActual($pdo) : null; // superadmin: hereda por municipio
        if (esSecretaria() && $secretariaImport === null) {
            $error = 'Tu usuario no tiene una secretaría activa asignada.';
        }
    }
    if ($error === '') {
        $rolAdmin = (int) $pdo->query("SELECT id FROM roles WHERE nombre = 'administrador'")->fetchColumn();
        $handle = fopen($archivo, 'r');
        $encabezado = fgetcsv($handle);
        if (isset($encabezado[0])) { $encabezado[0] = ltrim((string) $encabezado[0], "\xEF\xBB\xBF"); }
        $fila = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $fila++;
            [$nombre, $dane, $municipio, $adminNombre, $adminCorreo, $nucleoTxt, $zonaTxt, $direccionTxt] = array_pad($row, 8, '');
            $nombre = trim((string) $nombre);
            $municipio = mb_substr(trim((string) $municipio), 0, 80);
            $adminNombre = trim((string) $adminNombre);
            $adminCorreo = trim((string) $adminCorreo);
            if ($nombre === '' && trim((string) $dane) === '') continue; // fila vacía

            if ($nombre === '' || mb_strlen($nombre) > 150) {
                $omitidos[] = "Fila {$fila}: nombre vacío o de más de 150 caracteres.";
                continue;
            }
            // Si piden crear administrador, validarlo ANTES de crear el colegio.
            $crearAdmin = $adminCorreo !== '' || $adminNombre !== '';
            if ($crearAdmin) {
                if ($adminNombre === '' || !validarCorreo($adminCorreo)) {
                    $omitidos[] = "Fila {$fila} ({$nombre}): admin_nombre y admin_correo deben venir juntos y válidos, o dejarse ambos vacíos.";
                    continue;
                }
                if (Usuario::correoExiste($pdo, $adminCorreo)) {
                    $omitidos[] = "Fila {$fila} ({$nombre}): ya existe una cuenta con {$adminCorreo}.";
                    continue;
                }
            }
            try {
                $colegioId = Colegio::crear($pdo, $nombre, (string) $dane, $municipio, $secretariaImport);
            } catch (InvalidArgumentException $e) {
                $omitidos[] = "Fila {$fila} ({$nombre}): " . $e->getMessage();
                continue;
            }
            $importados++;
            auditarAccion($pdo, 'crear_colegio', 'colegios', $colegioId, [], ['nombre' => $nombre, 'dane' => Colegio::normalizarDane((string) $dane), 'origen' => 'importacion_csv']);

            // Dirección (el mapa la ubica después, desde Colegios → Editar) y organización territorial.
            $dirTxt = textoCortoONulo($direccionTxt, 200);
            if ($dirTxt !== null) {
                Colegio::guardarUbicacion($pdo, $colegioId, $dirTxt, null);
            }
            if (Colegio::tieneTerritorio($pdo)) {
                $nucleoTxt = trim((string) $nucleoTxt);
                $zonaTxt = trim((string) $zonaTxt);
                $sidColegio = (int) $pdo->query('SELECT secretaria_id FROM colegios WHERE id = ' . (int) $colegioId)->fetchColumn() ?: null;
                $nid = Territorio::resolverNucleoId($pdo, $nucleoTxt, $sidColegio);
                $zid = Territorio::resolverZonaId($pdo, $zonaTxt, $municipio, $sidColegio);
                if ($nucleoTxt !== '' && $nid === null) {
                    $omitidos[] = "Fila {$fila} ({$nombre}): el colegio se creó, pero no encontré el núcleo «{$nucleoTxt}» (o hay varios con ese nombre).";
                }
                if ($zonaTxt !== '' && $zid === null) {
                    $omitidos[] = "Fila {$fila} ({$nombre}): el colegio se creó, pero no encontré la zona «{$zonaTxt}» en {$municipio}.";
                }
                Territorio::asignarAColegio($pdo, $colegioId, $nid, $zid);
            }

            if ($crearAdmin) {
                $temporal = generarPasswordTemporalValida();
                $pdo->prepare(
                    'INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, consentimiento_datos, fecha_consentimiento, fecha_registro)
                     VALUES (?,?,?,?,?,1,NOW(),NOW())'
                )->execute([$adminNombre, $adminCorreo, password_hash($temporal, PASSWORD_DEFAULT), $rolAdmin, $colegioId]);
                $admins++;
                auditarAccion($pdo, 'crear_admin_colegio', 'usuarios', (int) $pdo->lastInsertId(), [], ['correo' => $adminCorreo, 'colegio_id' => $colegioId]);

                $enlaceLogin = urlAbsoluta('/login.php');
                $texto = "Hola {$adminNombre},\n\nSe creó tu cuenta de administrador de {$nombre} en Serena.\n\n"
                    . "Correo: {$adminCorreo}\nContraseña temporal: {$temporal}\n\nCámbiala apenas inicies sesión.\n\n— Serena";
                $html = plantillaCorreoHtml('Bienvenido/a a Serena',
                    '<p>Hola <strong>' . htmlspecialchars($adminNombre) . '</strong>,</p>'
                    . '<p>Se creó tu cuenta de administrador de <strong>' . htmlspecialchars($nombre) . '</strong>.</p>'
                    . '<p><strong>Correo:</strong> ' . htmlspecialchars($adminCorreo)
                    . '<br><strong>Contraseña temporal:</strong> <code>' . htmlspecialchars($temporal) . '</code></p>'
                    . botonCorreoHtml('Iniciar sesión', $enlaceLogin)
                    . '<p class="sa-nota">Cámbiala apenas inicies sesión.</p>');
                $enviado = smtpConfigurado() && enviarCorreo($adminCorreo, 'Tu cuenta de administrador en Serena', $texto, $html);
                if (!$enviado) {
                    $credencialesSinEnviar[] = ['colegio' => $nombre, 'correo' => $adminCorreo, 'password' => $temporal];
                }
            }
        }
        fclose($handle);
        auditarAccion($pdo, 'importar_colegios_csv', 'colegios', 0, [], ['colegios' => $importados, 'administradores' => $admins]);
        $exito = "Se importaron {$importados} colegios y {$admins} administradores.";
    }
}

$tituloPagina = 'Importar colegios';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Importar colegios</h1>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>
<?php if (!empty($omitidos)): ?>
<div class="mensaje mensaje-aviso">
  <strong>Algunas filas se omitieron:</strong>
  <ul class="lista-simple"><?php foreach ($omitidos as $o): ?><li><?= htmlspecialchars($o) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<?php if (!empty($credencialesSinEnviar)): ?>
<div class="mensaje mensaje-aviso">
  <strong>No se pudo enviar el correo a estos administradores.</strong> Comunica estas contraseñas por otro medio — no se vuelven a mostrar:
  <table class="tabla-datos sa-espaciado">
    <thead><tr><th>Colegio</th><th>Correo</th><th>Contraseña</th></tr></thead>
    <tbody>
      <?php foreach ($credencialesSinEnviar as $c): ?>
        <tr><td><?= htmlspecialchars($c['colegio']) ?></td><td><?= htmlspecialchars($c['correo']) ?></td><td><code><?= htmlspecialchars($c['password']) ?></code></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<section class="panel-seccion">
  <h2>Sube un CSV</h2>
  <p class="texto-suave">
    Columnas: <code>nombre</code>, <code>dane</code> (12 dígitos), <code>municipio</code>, <code>admin_nombre</code>, <code>admin_correo</code>,
    <code>nucleo</code>, <code>zona</code>, <code>direccion</code>.
    Desde <code>admin_nombre</code> en adelante son opcionales: si llenas el administrador, se crea y se le envía su contraseña por correo;
    <code>nucleo</code> y <code>zona</code> (comuna, corregimiento, localidad o vereda) deben existir antes en <a href="territorio.php">Territorio</a>;
    la <code>direccion</code> se ubica en el mapa después, desde Colegios → Editar.
  </p>
  <form action="colegios_importar.php" method="POST" enctype="multipart/form-data" novalidate>
    <?php campoCSRF(); ?>
    <label for="csv">Archivo CSV (máx. 2 MB)</label>
    <input type="file" id="csv" name="csv" accept=".csv" required>
    <button type="submit">Importar colegios</button>
  </form>
  <p><a href="colegios_importar_ejemplo.php" class="boton-secundario">Descargar CSV de ejemplo</a></p>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
