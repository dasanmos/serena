<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/modelos/RolesUsuario.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/correo.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
requiereRol('administrador');

$error = ''; $exito = ''; $importados = 0; $omitidos = [];
$credencialesSinEnviar = [];

$rolesValidos = $pdo->query("SELECT id, nombre FROM roles WHERE nombre NOT IN ('estudiante', 'superadmin')")->fetchAll(PDO::FETCH_KEY_PAIR);
$rolesPorNombre = array_flip($rolesValidos); // nombre => id

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
        $handle = fopen($archivo, 'r');
        fgetcsv($handle); // encabezado
        $fila = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $fila++;
            [$nombre, $correo, $password, $rolTexto, $grupoTexto, $documentoTexto] = array_pad($row, 6, '');
            $nombre = trim($nombre); $correo = trim($correo); $password = trim($password);
            $rolTexto = mb_strtolower(trim($rolTexto)); $grupoTexto = trim($grupoTexto);
            if ($nombre === '' || $correo === '') continue;

            if (!isset($rolesPorNombre[$rolTexto])) {
                $omitidos[] = "Fila {$fila}: rol '{$rolTexto}' no existe (usa docente, orientador, psicologo, coordinador, rector o administrador).";
                continue;
            }
            // La misma persona se reconoce primero por documento y luego por correo.
            $persona = RolesUsuario::cuentaDeLaPersona($pdo, (int) colegioParaCrear(), $correo, Usuario::normalizarDocumento((string) $documentoTexto));
            if (!empty($persona['conflicto'])) {
                $omitidos[] = "Fila {$fila}: {$persona['conflicto']}";
                continue;
            }
            $cuentaExistente = $persona['cuenta'] ?? null;
            if ($cuentaExistente) {
                // La misma persona con otro rol en este colegio: se le suma el rol (cuenta multirol).
                if ((int) $cuentaExistente['colegio_id'] === (int) colegioParaCrear()
                    && RolesUsuario::agregar($pdo, (int) $cuentaExistente['id'], $rolTexto, (int) $_SESSION['usuario_id']) === '') {
                    $omitidos[] = "Fila {$fila}: {$cuentaExistente['nombre']} ya tenía cuenta (" . ($persona['por'] === 'documento' ? 'mismo documento' : 'mismo correo') . "); se le agregó el rol {$rolTexto}: conserva su contraseña y elegirá con qué rol entrar.";
                } else {
                    $omitidos[] = "Fila {$fila}: ya existe una cuenta con {$correo}.";
                }
                continue;
            }

            $grupoId = null;
            if ($grupoTexto !== '') {
                $clave = mb_strtolower($grupoTexto);
                if (isset($gruposPorEtiqueta[$clave])) {
                    $grupoId = $gruposPorEtiqueta[$clave];
                } else {
                    $omitidos[] = "Fila {$fila}: el grupo '{$grupoTexto}' no existe, se creó sin grupo asignado.";
                }
            }

            $generada = $password === '';
            if (!$generada && !validarPassword($password)) {
                $omitidos[] = "Fila {$fila}: la contraseña de {$correo} no cumple los requisitos (8 a 64 caracteres, mayúscula, minúscula, número y carácter especial).";
                continue;
            }
            $pass = $generada ? generarPasswordTemporalValida() : $password;
            $rolId = $rolesPorNombre[$rolTexto];

            $pdo->prepare(
                'INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, grupo_id, consentimiento_datos, fecha_consentimiento, fecha_registro)
                 VALUES (?,?,?,?,?,?,1,NOW(),NOW())'
            )->execute([$nombre, $correo, password_hash($pass, PASSWORD_DEFAULT), $rolId, colegioParaCrear(), $grupoId]);
            $nuevoId = (int) $pdo->lastInsertId();
            // Columna opcional «documento»: permite que la persona también inicie sesión con su número.
            $docNorm = Usuario::normalizarDocumento((string) $documentoTexto);
            if ($docNorm !== '') {
                if (Usuario::documentoValido($docNorm) && !Usuario::documentoEnUso($pdo, $docNorm, colegioParaCrear(), $nuevoId)) {
                    Usuario::guardarDocumento($pdo, $nuevoId, $docNorm);
                } else {
                    $omitidos[] = "Fila {$fila}: el documento de {$correo} no es válido o ya lo tiene otra cuenta; se creó sin documento.";
                }
            }

            // Si además le tocó un grupo y su rol es docente, que quede como director de grupo
            // (mismo mecanismo que asignar_docente, para no dejarlo a medias).
            if ($grupoId && $rolTexto === 'docente') {
                Grupo::asignarDocente($pdo, $grupoId, $nuevoId);
            }

            $importados++;
            if ($generada) {
                $textoPlano = "Hola {$nombre},\n\nSe creó tu cuenta de personal en Serena.\n\n"
                    . "Correo: {$correo}\nContraseña temporal: {$pass}\n\nTe recomendamos cambiarla apenas inicies sesión.\n\n— Serena";
                $enlaceLogin = urlAbsoluta('/login.php');
                $cuerpoHtml = "<p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>"
                    . "<p>Se creó tu cuenta de personal en Serena. Estos son tus datos de acceso:</p>"
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
                $enviado = smtpConfigurado() && enviarCorreo($correo, 'Tu cuenta de personal en Serena', $textoPlano, $htmlCompleto);
                if (!$enviado) $credencialesSinEnviar[] = ['nombre' => $nombre, 'correo' => $correo, 'password' => $pass];
            }
        }
        fclose($handle);
        auditarAccion($pdo, 'importar_personal_csv', 'usuarios', 0, [], ['importados' => $importados]);
        $exito = "Se importaron {$importados} persona(s)." . (smtpConfigurado() ? ' Las contraseñas generadas se enviaron por correo.' : '');
    }
}

$tituloPagina = 'Importar personal';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Importar personal</h1>
<p class="texto-suave">Para docente, orientador, psicólogo, coordinador, rector o administrador — no para estudiantes (usa <a href="importar_estudiantes.php">Importar estudiantes</a> para eso).</p>

<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>
<?php if (!empty($omitidos)): ?>
<div class="mensaje mensaje-aviso">
  <strong>Algunas filas se omitieron o quedaron incompletas:</strong>
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
  <p>Columnas del CSV: <strong>nombre, correo, contraseña, rol, grupo</strong> y, si quieres, <strong>documento</strong> (para que la persona también inicie sesión con su número).
     La contraseña es opcional (se genera sola si se deja vacía). Si la incluyes, debe tener
     entre 8 y 12 caracteres, con al menos una mayúscula, una minúscula, un número y un
     carácter especial — si no cumple, esa fila se omite. El grupo es opcional y solo
     aplica a docentes directores de grupo — escríbelo igual que aparece en <a href="grupos.php">Grupos</a>,
     por ejemplo <code>Sexto A</code>.</p>
  <p><a href="usuarios_importar_ejemplo.php">⬇ Descargar CSV de ejemplo</a></p>
  <form action="usuarios_importar.php" method="POST" enctype="multipart/form-data" class="formulario-panel">
    <?php campoCSRF(); ?>
    <label>Archivo CSV</label><input type="file" name="csv" accept=".csv" required>
    <button type="submit">Importar</button>
  </form>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
