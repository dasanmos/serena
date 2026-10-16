<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/modelos/Academico.php';

// Inscripción web (pública): el acudiente elige el colegio con inscripciones abiertas y envía la solicitud.
// Antes de enviarla no se crea ninguna cuenta: la secretaría del colegio la revisa y, si la admite, matricula.

$colegios = Academico::colegiosConInscripcion($pdo);
$colegioId = (int) ($_POST['colegio_id'] ?? $_GET['colegio'] ?? 0);
$colegio = null;
foreach ($colegios as $c) {
    if ((int) $c['id'] === $colegioId) {
        $colegio = $c;
    }
}

/** Valor anterior del campo (para no perder lo escrito si hay un error). */
$v = static fn (string $k): string => htmlspecialchars((string) ($_POST[$k] ?? ''));

$error = '';
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $colegio !== null && isset($_POST['nombres'])) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    if (trim((string) ($_POST['sitio_web'] ?? '')) !== '') {
        // Campo trampa para programas: se finge éxito y no se guarda nada.
        $exito = ['codigo' => strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)), 'colegio' => $colegio['nombre']];
    } else {
        $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        $r = Academico::crearInscripcion($pdo, (int) $colegio['id'], $_POST, hash_hmac('sha256', $ip, 'serena-inscripciones'));
        if ($r['ok']) {
            $exito = ['codigo' => $r['codigo'], 'colegio' => $colegio['nombre']];
        } else {
            $error = $r['error'];
        }
    }
}

$grados = $colegio !== null ? Academico::grados($pdo, (int) $colegio['id']) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serena — Inscripción</title>
    <link rel="icon" href="assets/favicon/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= assetVersion('/assets/css/base.css') ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= assetVersion('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="assets/css/login_pro.css?v=<?= assetVersion('/assets/css/login_pro.css') ?>">
    <link rel="stylesheet" href="assets/css/academico.css?v=<?= assetVersion('/assets/css/academico.css') ?>">
    <meta name="theme-color" content="#E8B923">
    <script>window.SERENA_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?= assetVersion('/assets/css/responsive.css') ?>">
</head>
<body class="login-body login-pro">
    <div class="contenedor-login">
        <div class="card-login">
<?php $marcaTitulo = 'Inscripciones abiertas'; $marcaTexto = 'Inscribe al estudiante desde casa. El colegio revisa la solicitud y te contacta con el resultado.'; require __DIR__ . '/parciales/auth_marca.php'; ?>
<div class="login-form-lado">

            <h2>Inscripción de estudiantes</h2>

            <?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

            <?php if ($exito !== null): ?>
                <p class="mensaje mensaje-exito">¡Recibimos la inscripción en <strong><?= htmlspecialchars($exito['colegio']) ?></strong>!</p>
                <p>Guarda este código. Con él y el documento del aspirante puedes consultar el estado:</p>
                <span class="insc-codigo"><?= htmlspecialchars($exito['codigo']) ?></span>
                <p class="texto-suave">El colegio revisará la solicitud. Si es admitida, recibirás el contacto del colegio para completar la matrícula.</p>
                <div class="enlaces-login">
                    <a href="inscripcion_estado.php">Consultar estado de una inscripción</a>
                    <a href="index.php">Volver al inicio</a>
                </div>

            <?php elseif ($colegio === null): ?>
                <?php if (!$colegios): ?>
                    <p class="mensaje mensaje-aviso">Por ahora ningún colegio tiene inscripciones abiertas. Vuelve a intentarlo más adelante o comunícate directamente con el colegio.</p>
                <?php else: ?>
                    <p>Elige el colegio al que quieres inscribir al estudiante:</p>
                    <ul class="insc-lista-colegios">
                        <?php foreach ($colegios as $c): ?>
                            <li><a href="inscripcion.php?colegio=<?= (int) $c['id'] ?>">
                                <?= htmlspecialchars($c['nombre']) ?>
                                <small><?= $c['municipio'] ? htmlspecialchars($c['municipio']) . ' · ' : '' ?>Año lectivo <?= (int) $c['anio'] ?></small>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="enlaces-login">
                    <a href="inscripcion_estado.php">Ya me inscribí: consultar estado</a>
                    <a href="index.php">Volver al inicio</a>
                </div>

            <?php else: ?>
                <p class="mensaje mensaje-exito">Colegio: <strong><?= htmlspecialchars($colegio['nombre']) ?></strong> · Año lectivo <?= (int) $colegio['anio'] ?>
                    · <a href="inscripcion.php">cambiar</a></p>
                <?php if (!$grados): ?>
                    <p class="mensaje mensaje-aviso">Este colegio aún no ha configurado sus grados. Comunícate con el colegio.</p>
                <?php else: ?>
                <form action="inscripcion.php" method="POST" novalidate>
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="colegio_id" value="<?= (int) $colegio['id'] ?>">
                    <div class="insc-honeypot" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>

                    <div class="insc-grid">
                        <fieldset>
                            <legend>Datos del estudiante</legend>
                            <div class="insc-grid">
                                <div><label for="nombres">Nombres</label>
                                    <input type="text" id="nombres" name="nombres" required maxlength="80" autocomplete="off" value="<?= $v('nombres') ?>"></div>
                                <div><label for="apellidos">Apellidos</label>
                                    <input type="text" id="apellidos" name="apellidos" required maxlength="80" autocomplete="off" value="<?= $v('apellidos') ?>"></div>
                                <div><label for="tipo_documento">Tipo de documento</label>
                                    <select id="tipo_documento" name="tipo_documento" required>
                                        <option value="">Selecciona…</option>
                                        <?php foreach (Academico::TIPOS_DOCUMENTO as $k => $t): ?>
                                            <option value="<?= $k ?>" <?= ($_POST['tipo_documento'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div><label for="numero_documento">Número de documento</label>
                                    <input type="text" id="numero_documento" name="numero_documento" required maxlength="20" inputmode="numeric" autocomplete="off" value="<?= $v('numero_documento') ?>"></div>
                                <div><label for="fecha_nacimiento">Fecha de nacimiento</label>
                                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required value="<?= $v('fecha_nacimiento') ?>"></div>
                                <div><label for="grado_id">Grado al que aspira</label>
                                    <select id="grado_id" name="grado_id" required>
                                        <option value="">Selecciona…</option>
                                        <?php foreach ($grados as $g): ?>
                                            <option value="<?= (int) $g['id'] ?>" <?= (int) ($_POST['grado_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div><label for="correo_estudiante">Correo del estudiante (opcional)</label>
                                    <input type="email" id="correo_estudiante" name="correo_estudiante" maxlength="150" autocomplete="off" value="<?= $v('correo_estudiante') ?>"></div>
                                <div><label for="colegio_anterior">Colegio anterior (opcional)</label>
                                    <input type="text" id="colegio_anterior" name="colegio_anterior" maxlength="150" value="<?= $v('colegio_anterior') ?>"></div>
                                <div class="ancho"><label for="direccion">Dirección de residencia (opcional)</label>
                                    <input type="text" id="direccion" name="direccion" maxlength="200" value="<?= $v('direccion') ?>"></div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend>Datos del acudiente</legend>
                            <div class="insc-grid">
                                <div class="ancho"><label for="acudiente_nombre">Nombre completo</label>
                                    <input type="text" id="acudiente_nombre" name="acudiente_nombre" required maxlength="120" autocomplete="name" value="<?= $v('acudiente_nombre') ?>"></div>
                                <div><label for="acudiente_parentesco">Parentesco</label>
                                    <select id="acudiente_parentesco" name="acudiente_parentesco" required>
                                        <option value="">Selecciona…</option>
                                        <?php foreach (Academico::PARENTESCOS as $p): ?>
                                            <option <?= ($_POST['acudiente_parentesco'] ?? '') === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                                        <?php endforeach; ?>
                                    </select></div>
                                <div><label for="acudiente_documento">Número de documento</label>
                                    <input type="text" id="acudiente_documento" name="acudiente_documento" required maxlength="20" inputmode="numeric" autocomplete="off" value="<?= $v('acudiente_documento') ?>"></div>
                                <div><label for="acudiente_correo">Correo (aquí llegará su acceso a Serena)</label>
                                    <input type="email" id="acudiente_correo" name="acudiente_correo" required maxlength="150" autocomplete="email" value="<?= $v('acudiente_correo') ?>"></div>
                                <div><label for="acudiente_telefono">Teléfono / WhatsApp</label>
                                    <input type="tel" id="acudiente_telefono" name="acudiente_telefono" required maxlength="20" autocomplete="tel" value="<?= $v('acudiente_telefono') ?>"></div>
                            </div>
                        </fieldset>

                        <div class="ancho"><label for="observaciones">Observaciones (opcional)</label>
                            <textarea id="observaciones" name="observaciones" rows="3" maxlength="500"><?= $v('observaciones') ?></textarea></div>

                        <label class="opcion-consentimiento ancho">
                            <input type="checkbox" name="consentimiento" value="1" required <?= !empty($_POST['consentimiento']) ? 'checked' : '' ?>>
                            Como acudiente, autorizo el <a href="consentimiento_datos.php" target="_blank" rel="noopener">tratamiento de los datos personales</a> del estudiante y los míos para el proceso de inscripción.
                        </label>
                        <div class="ancho"><button type="submit">Enviar inscripción</button></div>
                    </div>
                </form>
                <?php endif; ?>
                <div class="enlaces-login"><a href="inscripcion_estado.php">Ya me inscribí: consultar estado</a></div>
            <?php endif; ?>

        </div><!-- /login-form-lado -->
        </div>
    </div>
    <script src="assets/js/base.js?v=<?= assetVersion('/assets/js/base.js') ?>"></script>
    <script src="assets/js/cookies.js?v=<?= assetVersion('/assets/js/cookies.js') ?>"></script>
</body>
</html>
