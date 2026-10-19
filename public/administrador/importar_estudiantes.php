<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/correo.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
requiereRol('administrador');
$error = ''; $exito = ''; $importados = 0; $omitidos = [];
$credencialesSinEnviar = []; // estudiantes con password autogenerado que no se pudo comunicar por correo

$gruposPorEtiqueta = [];
foreach (Grupo::listarConGrado($pdo) as $g) {
    $gruposPorEtiqueta[mb_strtolower($g['grado'] . ' ' . $g['nombre'])] = (int) $g['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $archivo = $_FILES['csv']['tmp_name'] ?? '';
    $nombreOriginal = $_FILES['csv']['name'] ?? '';
    if (!$archivo || !is_uploaded_file($archivo)) { $error = 'Sube un archivo CSV.'; }
    elseif (strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION)) !== 'csv') { $error = 'El archivo debe tener extensión .csv.'; }
    elseif (($_FILES['csv']['size'] ?? 0) > 2 * 1024 * 1024) { $error = 'El archivo pesa más de 2 MB.'; }
    else {
        $rolId = (int)$pdo->query("SELECT id FROM roles WHERE nombre='estudiante' LIMIT 1")->fetchColumn();
        $handle = fopen($archivo, 'r');
        fgetcsv($handle); // skip header
        $fila = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $fila++;
            [$nombre, $correo, $password, $grupoTexto, $fechaNacimiento, $documentoTexto] = array_pad($row, 6, '');
            $nombre = trim($nombre); $correo = trim($correo); $password = trim($password);
            $grupoTexto = trim($grupoTexto); $fechaNacimiento = trim($fechaNacimiento);
            if (empty($nombre) || empty($correo)) continue;

            $clave = mb_strtolower($grupoTexto);
            if ($grupoTexto === '' || !isset($gruposPorEtiqueta[$clave])) {
                $omitidos[] = "Fila {$fila} ({$nombre}): el grupo '{$grupoTexto}' no existe o está vacío — escríbelo igual que en Grupos, ej. 'Sexto A'.";
                continue;
            }
            $grupoId = $gruposPorEtiqueta[$clave];

            $existe = $pdo->prepare('SELECT id FROM usuarios WHERE correo=:c LIMIT 1');
            $existe->execute(['c' => $correo]);
            if ($existe->fetch()) { $omitidos[] = "Fila {$fila}: ya existe una cuenta con {$correo}."; continue; }

            $fechaNacimientoValida = null;
            if ($fechaNacimiento !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNacimiento)) {
                $fechaNacimientoValida = $fechaNacimiento;
            }

            $generada = empty($password);
            if (!$generada && !validarPassword($password)) {
                $omitidos[] = "Fila {$fila} ({$nombre}): la contraseña no cumple los requisitos (8 a 64 caracteres, mayúscula, minúscula, número y carácter especial).";
                continue;
            }
            $pass = $generada ? generarPasswordTemporalValida() : $password;
            $pdo->prepare(
                'INSERT INTO usuarios (nombre,correo,password_hash,rol_id,colegio_id,grupo_id,fecha_nacimiento,consentimiento_datos,fecha_consentimiento,fecha_registro)
                 VALUES (?,?,?,?,?,?,?,1,NOW(),NOW())'
            )->execute([$nombre, $correo, password_hash($pass, PASSWORD_DEFAULT), $rolId, colegioParaCrear(), $grupoId, $fechaNacimientoValida]);
            $nuevoId = (int) $pdo->lastInsertId();
            // Columna opcional «documento»: el estudiante también podrá iniciar sesión con su número.
            $docNorm = Usuario::normalizarDocumento((string) $documentoTexto);
            if ($docNorm !== '' && Usuario::documentoValido($docNorm) && !Usuario::documentoEnUso($pdo, $docNorm, colegioParaCrear(), $nuevoId)) {
                Usuario::guardarDocumento($pdo, $nuevoId, $docNorm);
            }
            $importados++;
            // Si la contraseña se generó sola, hay que comunicársela al estudiante de algún modo —
            // antes esto no pasaba nunca y podía dejar la cuenta inaccesible para siempre.
            if ($generada) {
                $textoPlano = "Hola {$nombre},\n\nSe creó tu cuenta en Serena.\n\n"
                    . "Correo: {$correo}\nContraseña temporal: {$pass}\n\n"
                    . "Te recomendamos cambiarla apenas inicies sesión.\n\n— Serena";

                $enlaceLogin = urlAbsoluta('/login.php');
                $cuerpoHtml = "<p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>"
                    . "<p>Se creó tu cuenta en Serena. Estos son tus datos de acceso:</p>"
                    . "<table role='presentation' cellpadding='0' cellspacing='0' style='width:100%;margin:16px 0;background:#F5F3EC;border-radius:10px;'>"
                    . "<tr><td style='padding:16px 20px'>"
                    . "<p style='margin:0 0 4px;font-size:12px;color:#6B6B6B;text-transform:uppercase;letter-spacing:1px'>Correo</p>"
                    . "<p style='margin:0 0 16px;font-size:15px;color:#2B2B2B'>" . htmlspecialchars($correo) . "</p>"
                    . "<p style='margin:0 0 4px;font-size:12px;color:#6B6B6B;text-transform:uppercase;letter-spacing:1px'>Contraseña temporal</p>"
                    . "<p style='margin:0;font-size:18px;font-family:Courier New,monospace;font-weight:bold;color:#2B2B2B;letter-spacing:1px'>" . htmlspecialchars($pass) . "</p>"
                    . "</td></tr></table>"
                    . botonCorreoHtml('Iniciar sesión', $enlaceLogin)
                    . "<p style='font-size:13px;color:#6B6B6B'>Te recomendamos cambiar esta contraseña apenas inicies sesión.</p>";
                $htmlCompleto = plantillaCorreoHtml('Bienvenido/a a Serena', $cuerpoHtml);

                $enviado = smtpConfigurado() && enviarCorreo($correo, 'Tu cuenta en Serena', $textoPlano, $htmlCompleto);
                if (!$enviado) $credencialesSinEnviar[] = ['nombre' => $nombre, 'correo' => $correo, 'password' => $pass];
            }
        }
        fclose($handle);
        auditarAccion($pdo, 'importar_estudiantes_csv', 'usuarios', 0, [], ['importados' => $importados]);
        $exito = "Se importaron $importados estudiantes." . (smtpConfigurado() ? ' Las contraseñas generadas se enviaron por correo.' : '');
    }
}
$tituloPagina = 'Importar estudiantes';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Importar estudiantes</h1>
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
  <strong>No hay correo SMTP configurado (o falló el envío).</strong> Copia y comunica estas
  contraseñas por otro medio — no van a volver a mostrarse:
  <table class="tabla-datos" style="margin-top:8px">
    <thead><tr><th>Nombre</th><th>Correo</th><th>Contraseña</th></tr></thead>
    <tbody>
      <?php foreach ($credencialesSinEnviar as $c): ?>
        <tr><td><?= htmlspecialchars($c['nombre']) ?></td><td><?= htmlspecialchars($c['correo']) ?></td><td><code><?= htmlspecialchars($c['password']) ?></code></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<section class="panel-seccion">
  <p>Columnas del CSV: <strong>nombre, correo, contraseña, grupo, fecha de nacimiento</strong> y, si quieres, <strong>documento</strong> (para que el estudiante también inicie sesión con su número).
     La contraseña es opcional (se genera sola y se envía por correo si hay SMTP configurado). Si la
     incluyes, debe tener entre 8 y 12 caracteres, con al menos una mayúscula, una minúscula, un
     número y un carácter especial — si no cumple, esa fila se omite.
     El grupo es obligatorio y va como texto igual a como aparece en <a href="grupos.php">Grupos</a>,
     por ejemplo <code>Sexto A</code> — así puedes importar estudiantes de varios grupos en un solo archivo.
     La fecha de nacimiento es opcional, formato <code>AAAA-MM-DD</code>.</p>
  <p><a href="importar_estudiantes_ejemplo.php">⬇ Descargar CSV de ejemplo</a></p>
  <form action="importar_estudiantes.php" method="POST" enctype="multipart/form-data" class="formulario-panel">
    <?php campoCSRF(); ?>
    <label>Archivo CSV</label><input type="file" name="csv" accept=".csv" required>
    <button type="submit">Importar</button>
  </form>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
