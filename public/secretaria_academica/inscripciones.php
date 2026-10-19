<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/colegio.php';
require_once __DIR__ . '/../../src/modelos/Academico.php';

// Bandeja de inscripciones: revisar, admitir / rechazar y matricular (crea al estudiante y a su acudiente).
requiereRol('secretaria_academica', 'administrador', 'rector');
$colegioId = colegioParaCrear();
$usuarioId = (int) $_SESSION['usuario_id'];

$flash = $_SESSION['flash_inscripciones'] ?? null;
unset($_SESSION['flash_inscripciones']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $f = ['exito' => '', 'error' => '', 'credenciales' => null];

    if ($accion === 'estado') {
        $err = Academico::resolverInscripcion($pdo, $id, $colegioId, (string) ($_POST['estado'] ?? ''), (string) ($_POST['nota'] ?? ''), $usuarioId);
        $f[$err === '' ? 'exito' : 'error'] = $err === '' ? 'Inscripción actualizada.' : $err;
        if ($err === '') {
            auditarAccion($pdo, 'resolver_inscripcion', 'inscripciones', $id, [], ['estado' => (string) ($_POST['estado'] ?? '')]);
        }
    } elseif ($accion === 'matricular') {
        $r = Academico::matricularDesdeInscripcion($pdo, $id, $colegioId, (int) ($_POST['grupo_id'] ?? 0), $usuarioId);
        if ($r['error'] !== '') {
            $f['error'] = $r['error'];
        } else {
            $f['exito'] = 'Estudiante matriculado. Entrega estas credenciales: no se vuelven a mostrar.';
            $f['credenciales'] = $r;
            auditarAccion($pdo, 'matricular_desde_inscripcion', 'inscripciones', $id, [], ['estudiante_id' => $r['estudiante']['id']]);
        }
    }
    $_SESSION['flash_inscripciones'] = $f;
    header('Location: inscripciones.php?' . http_build_query(array_filter(['anio' => $_POST['anio'] ?? '', 'estado' => $_POST['filtro_estado'] ?? '', 'q' => $_POST['q'] ?? ''], static fn ($v): bool => $v !== '')));
    exit;
}

$anios = Academico::anios($pdo, $colegioId);
$anioSel = (int) ($_GET['anio'] ?? 0);
$estadoSel = (string) ($_GET['estado'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$lista = Academico::inscripciones($pdo, $colegioId, $anioSel ?: null, $estadoSel, $q);
$grupos = Academico::grupos($pdo, $colegioId);
$e = static fn ($v): string => htmlspecialchars((string) $v);

$tituloPagina = 'Inscripciones';
$cssExtra = ['/assets/css/academico.css'];
require __DIR__ . '/../parciales/encabezado.php';
?>

<h1>Inscripciones</h1>
<p class="texto-suave">
    Solicitudes enviadas desde el formulario web: <a href="<?= $e(BASE_URL) ?>/inscripcion.php" target="_blank" rel="noopener"><?= $e(BASE_URL) ?>/inscripcion.php</a>.
    Para recibirlas, abre las inscripciones en <a href="<?= $e(BASE_URL) ?>/administrador/anios_lectivos.php">Años lectivos</a>.
</p>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if ($flash && !empty($flash['credenciales'])): $cr = $flash['credenciales']; ?>
    <div class="acad-credenciales">
        <h2>Credenciales de acceso (se muestran una sola vez)</h2>
        <p><strong>Estudiante:</strong> <?= $e($cr['estudiante']['nombre']) ?><br>
            Usuario: <code><?= $e($cr['estudiante']['correo']) ?></code> · Contraseña temporal: <code><?= $e($cr['estudiante']['password']) ?></code></p>
        <p><strong>Acudiente:</strong> <?= $e($cr['acudiente']['nombre']) ?><br>
            Usuario: <code><?= $e($cr['acudiente']['correo']) ?></code> ·
            <?php if ($cr['acudiente']['password'] !== null): ?>Contraseña temporal: <code><?= $e($cr['acudiente']['password']) ?></code>
            <?php elseif (!empty($cr['acudiente']['multirol'])): ?><em>ya tenía cuenta en Serena con otro rol: se le agregó el rol de familia con su misma contraseña. Al entrar elegirá con qué rol ingresar.</em>
            <?php else: ?><em>ya tenía cuenta de acudiente: usa su contraseña actual (el estudiante quedó vinculado).</em><?php endif; ?></p>
        <?php if (!empty($cr['aviso'])): ?><p class="mensaje mensaje-aviso"><?= $e($cr['aviso']) ?></p><?php endif; ?>
        <p class="texto-suave">Pídeles cambiar la contraseña al ingresar (Preferencias → Seguridad). Entrégalas de forma privada.</p>
        <button type="button" class="boton-secundario" onclick="window.print()">Imprimir</button>
    </div>
<?php endif; ?>

<form action="inscripciones.php" method="GET" class="acad-filtros">
    <label>Año
        <select name="anio">
            <option value="">Todos</option>
            <?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $anioSel === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Estado
        <select name="estado">
            <option value="">Todos</option>
            <?php foreach (Academico::ESTADOS_INSCRIPCION as $k => $t): ?><option value="<?= $e($k) ?>" <?= $estadoSel === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Buscar <input type="search" name="q" value="<?= $e($q) ?>" placeholder="Nombre, documento o código"></label>
    <button type="submit">Filtrar</button>
</form>

<?php if (!$lista): ?>
    <div class="estado-vacio"><p>No hay inscripciones con esos filtros.</p></div>
<?php endif; ?>

<?php foreach ($lista as $i): ?>
    <?php $gruposDelGrado = array_filter($grupos, static fn (array $g): bool => (int) $g['grado_id'] === (int) $i['grado_id']); ?>
    <details class="anio-tarjeta" <?= in_array($i['estado'], ['pendiente'], true) ? 'open' : '' ?>>
        <summary>
            <strong><?= $e($i['nombres'] . ' ' . $i['apellidos']) ?></strong>
            · <?= $e($i['grado']) ?> · <?= (int) $i['anio'] ?>
            <span class="estado-pill estado-<?= $e($i['estado']) ?>"><?= $e(Academico::ESTADOS_INSCRIPCION[$i['estado']] ?? $i['estado']) ?></span>
        </summary>
        <dl class="acad-detalle">
            <div><dt>Documento</dt><dd><?= $e($i['tipo_documento'] . ' ' . $i['numero_documento']) ?></dd></div>
            <div><dt>Nacimiento</dt><dd><?= $e($i['fecha_nacimiento']) ?></dd></div>
            <div><dt>Código</dt><dd><code><?= $e($i['codigo']) ?></code></dd></div>
            <div><dt>Recibida</dt><dd><?= $e($i['creado_en']) ?></dd></div>
            <?php if ($i['correo_estudiante']): ?><div><dt>Correo del estudiante</dt><dd><?= $e($i['correo_estudiante']) ?></dd></div><?php endif; ?>
            <?php if ($i['direccion']): ?><div><dt>Dirección</dt><dd><?= $e($i['direccion']) ?></dd></div><?php endif; ?>
            <?php if ($i['colegio_anterior']): ?><div><dt>Colegio anterior</dt><dd><?= $e($i['colegio_anterior']) ?></dd></div><?php endif; ?>
            <div><dt>Acudiente</dt><dd><?= $e($i['acudiente_nombre']) ?> (<?= $e($i['acudiente_parentesco']) ?>)<br><?= $e($i['acudiente_documento']) ?></dd></div>
            <div><dt>Contacto</dt><dd><?= $e($i['acudiente_correo']) ?><br><?= $e($i['acudiente_telefono']) ?></dd></div>
            <?php if ($i['observaciones']): ?><div><dt>Observaciones</dt><dd><?= $e($i['observaciones']) ?></dd></div><?php endif; ?>
            <?php if ($i['nota_resolucion']): ?><div><dt>Nota de la revisión</dt><dd><?= $e($i['nota_resolucion']) ?></dd></div><?php endif; ?>
        </dl>

        <?php if ($i['estado'] !== 'matriculada'): ?>
        <div class="acad-acciones">
            <form action="inscripciones.php" method="POST" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                <input type="hidden" name="anio" value="<?= $anioSel ?: '' ?>"><input type="hidden" name="filtro_estado" value="<?= $e($estadoSel) ?>"><input type="hidden" name="q" value="<?= $e($q) ?>">
                <label>Nota (visible para el acudiente) <input type="text" name="nota" maxlength="255" value="<?= $e($i['nota_resolucion']) ?>"></label>
                <button type="submit" name="estado" value="en_revision" class="boton-secundario">En revisión</button>
                <button type="submit" name="estado" value="admitida">Admitir</button>
                <button type="submit" name="estado" value="rechazada" class="boton-peligro">Rechazar</button>
            </form>
        </div>
        <?php if ($i['estado'] !== 'rechazada'): ?>
        <div class="acad-acciones">
            <form action="inscripciones.php" method="POST" onsubmit="return confirm('Se creará la cuenta del estudiante y la del acudiente, y quedará matriculado. ¿Continuar?');" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="matricular"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                <input type="hidden" name="anio" value="<?= $anioSel ?: '' ?>"><input type="hidden" name="filtro_estado" value="<?= $e($estadoSel) ?>"><input type="hidden" name="q" value="<?= $e($q) ?>">
                <label>Grupo (<?= $e($i['grado']) ?>)
                    <select name="grupo_id" required>
                        <option value="">Elige un grupo…</option>
                        <?php foreach ($gruposDelGrado as $g): ?><option value="<?= (int) $g['id'] ?>"><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Matricular</button>
                <?php if (!$gruposDelGrado): ?><span class="texto-suave">No hay grupos de este grado: créalos en Grupos.</span><?php endif; ?>
            </form>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </details>
<?php endforeach; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
