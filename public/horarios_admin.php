<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Evaluacion.php';
require_once __DIR__ . '/../src/modelos/Horario.php';

// Editor de horarios: coordinación, rectoría, administración y secretaría académica.
// vista=grupo (por defecto): cuadrícula día × franja de un grupo. vista=franjas: horas de timbre del colegio.
requiereRol('administrador', 'rector', 'coordinador', 'secretaria_academica');
$colegioId = colegioParaCrear();
$e = static fn ($v): string => htmlspecialchars((string) $v);

$vista = ($_GET['vista'] ?? $_POST['vista'] ?? 'grupo') === 'franjas' ? 'franjas' : 'grupo';
$anios = Academico::anios($pdo, $colegioId);
$anioSel = (int) ($_GET['anio'] ?? $_POST['anio'] ?? 0);
$anio = $anioSel > 0 ? Academico::anio($pdo, $anioSel, $colegioId) : Academico::anioActual($pdo, $colegioId);
if ($anio === null && $anios) {
    $anio = $anios[0];
}
$franjas = Horario::franjas($pdo, $colegioId);
$grupos = Academico::grupos($pdo, $colegioId);
$grupoId = (int) ($_GET['grupo'] ?? $_POST['grupo'] ?? 0);
$grupo = null;
foreach ($grupos as $g) {
    if ((int) $g['id'] === $grupoId) {
        $grupo = $g;
    }
}
$conSabado = isset($_GET['sabado']) || isset($_POST['sabado']);

$flash = $_SESSION['flash_horario'] ?? null;
unset($_SESSION['flash_horario']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $f = ['exito' => '', 'errores' => []];
    $destino = ['vista' => $vista, 'anio' => $anio ? (int) $anio['id'] : 0, 'grupo' => $grupoId];

    if ($accion === 'guardar_franjas') {
        $filas = [];
        foreach ((array) ($_POST['franja'] ?? []) as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $filas[] = ['nombre' => (string) ($fila['nombre'] ?? ''), 'inicio' => (string) ($fila['inicio'] ?? ''),
                        'fin' => (string) ($fila['fin'] ?? ''), 'descanso' => !empty($fila['descanso'])];
        }
        $r = Horario::guardarFranjas($pdo, $colegioId, $filas);
        if ($r['errores']) {
            $f['errores'] = $r['errores'];
        } else {
            $f['exito'] = 'Franjas guardadas (' . $r['guardadas'] . ').';
            auditarAccion($pdo, 'guardar_franjas_horario', 'horario_franjas', $colegioId, [], ['franjas' => $r['guardadas']]);
        }
    } elseif ($accion === 'guardar_horario') {
        if ($grupo === null || $anio === null) {
            $f['errores'][] = 'Elige un grupo válido.';
        } else {
            $celdas = [];
            foreach ((array) ($_POST['c'] ?? []) as $dia => $porFranja) {
                foreach ((array) $porFranja as $franjaId => $campos) {
                    if (is_array($campos)) {
                        $celdas[(int) $dia][(int) $franjaId] = ['asignacion_id' => (int) ($campos['a'] ?? 0), 'salon' => (string) ($campos['s'] ?? '')];
                    }
                }
            }
            $r = Horario::guardarGrupo($pdo, $colegioId, (int) $anio['id'], (int) $grupo['id'], $celdas);
            if ($r['errores']) {
                $f['errores'] = $r['errores'];
            } else {
                $f['exito'] = 'Horario guardado: ' . $r['guardados'] . ' clase(s) por semana.';
                auditarAccion($pdo, 'guardar_horario_grupo', 'grupos', (int) $grupo['id'], [], ['anio' => (int) $anio['anio'], 'clases' => $r['guardados']]);
            }
        }
    }
    $_SESSION['flash_horario'] = $f;
    if ($conSabado) {
        $destino['sabado'] = 1;
    }
    header('Location: horarios_admin.php?' . http_build_query($destino));
    exit;
}

// ---- datos para pintar ----
$bloques = [];
$asigs = [];
$horas = [];
$conflictos = [];
if ($vista === 'grupo' && $anio !== null) {
    $conflictos = Horario::conflictos($pdo, $colegioId, (int) $anio['id']);
    if ($grupo !== null) {
        $bloques = Horario::bloquesDeGrupo($pdo, $colegioId, (int) $anio['id'], (int) $grupo['id']);
        $asigs = Horario::asignacionesDeGrupo($pdo, $colegioId, (int) $anio['id'], (int) $grupo['id']);
        $horas = Horario::horasPorAsignacion($pdo, $colegioId, (int) $anio['id'], (int) $grupo['id']);
    }
}
$diasMostrar = range(1, Horario::DIAS_VISIBLES);
if (!$conSabado) {
    foreach ($bloques as $dia => $_) {
        if ($dia === 6) {
            $conSabado = true;
        }
    }
}
if ($conSabado) {
    $diasMostrar[] = 6;
}
$filasFranjas = $franjas
    ? array_map(static fn (array $f): array => ['nombre' => $f['nombre'], 'inicio' => $f['inicio'], 'fin' => $f['fin'], 'descanso' => $f['descanso']], $franjas)
    : Horario::franjasPorDefecto();
while (count($filasFranjas) < Horario::MAX_FRANJAS && count($filasFranjas) < 10) {
    $filasFranjas[] = ['nombre' => '', 'inicio' => '', 'fin' => '', 'descanso' => false];
}

$tituloPagina = 'Horarios';
$cssExtra = ['/assets/css/academico.css', '/assets/css/horarios.css'];
require __DIR__ . '/parciales/encabezado.php';
$qBase = ['anio' => $anio ? (int) $anio['id'] : 0, 'grupo' => $grupoId];
?>

<h1>Horarios</h1>

<nav class="pestanas-horario" aria-label="Secciones de horarios">
    <a href="?<?= $e(http_build_query($qBase)) ?>"<?= $vista === 'grupo' ? ' aria-current="page"' : '' ?>>Horario por grupo</a>
    <a href="?<?= $e(http_build_query($qBase + ['vista' => 'franjas'])) ?>"<?= $vista === 'franjas' ? ' aria-current="page"' : '' ?>>Franjas horarias</a>
</nav>

<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito" role="status"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if ($flash && $flash['errores']): ?>
    <div class="mensaje mensaje-error" role="alert">
        <strong>No se guardó nada.</strong> Corrige esto y vuelve a guardar:
        <ul><?php foreach ($flash['errores'] as $m): ?><li><?= $e($m) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($vista === 'franjas'): ?>

    <section class="panel-seccion">
        <h2>Franjas horarias del colegio</h2>
        <p class="texto-suave">Define las horas de timbre. Deja vacías las filas que no uses. Las clases ya asignadas siguen ligadas al <em>número</em> de franja: si cambias la hora de la franja 2, las clases de la franja 2 pasan a esa hora.</p>
        <form method="POST" action="horarios_admin.php" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="guardar_franjas">
            <input type="hidden" name="vista" value="franjas">
            <input type="hidden" name="anio" value="<?= $anio ? (int) $anio['id'] : 0 ?>">
            <input type="hidden" name="grupo" value="<?= $grupoId ?>">
            <div class="filas-franjas">
                <?php foreach ($filasFranjas as $i => $fr): ?>
                    <div class="fila-franja">
                        <span class="numero-franja" aria-hidden="true"><?= $i + 1 ?></span>
                        <label class="campo-nombre">Nombre
                            <input type="text" name="franja[<?= $i ?>][nombre]" maxlength="30" value="<?= $e($fr['nombre']) ?>" placeholder="<?= $i + 1 ?>.ª hora"></label>
                        <label>Inicio
                            <input type="time" name="franja[<?= $i ?>][inicio]" value="<?= $e($fr['inicio']) ?>"></label>
                        <label>Fin
                            <input type="time" name="franja[<?= $i ?>][fin]" value="<?= $e($fr['fin']) ?>"></label>
                        <label class="check-descanso"><input type="checkbox" name="franja[<?= $i ?>][descanso]" value="1" <?= $fr['descanso'] ? 'checked' : '' ?>> Es descanso</label>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit">Guardar franjas</button>
        </form>
    </section>

<?php else: ?>

    <?php if (!$anio): ?>
        <p class="mensaje mensaje-aviso">No hay un año lectivo configurado.</p>
    <?php elseif (!$franjas): ?>
        <div class="estado-vacio">
            <p>Primero define las franjas horarias del colegio (las horas de timbre).</p>
            <p><a class="boton-acceso" href="?<?= $e(http_build_query($qBase + ['vista' => 'franjas'])) ?>">Configurar franjas</a></p>
        </div>
    <?php else: ?>

        <?php if ($conflictos): ?>
            <section class="panel-seccion">
                <h2>Cruces que debes resolver</h2>
                <p class="texto-suave">Se crearon al cambiar docentes o salones después de armar los horarios. Corrígelos en el horario del grupo correspondiente.</p>
                <ul class="lista-conflictos"><?php foreach ($conflictos as $c): ?><li><?= $e($c) ?></li><?php endforeach; ?></ul>
            </section>
        <?php endif; ?>

        <form method="GET" action="horarios_admin.php" class="acad-filtros">
            <label>Año
                <select name="anio"><?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $anio['id'] === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?></option><?php endforeach; ?></select>
            </label>
            <label>Grupo
                <select name="grupo">
                    <?php if ($grupo === null): ?><option value="">Elige un grupo…</option><?php endif; ?>
                    <?php foreach ($grupos as $g): ?><option value="<?= (int) $g['id'] ?>" <?= $grupo && (int) $grupo['id'] === (int) $g['id'] ? 'selected' : '' ?>><?= $e($g['grado'] . ' ' . $g['nombre']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="check-descanso"><input type="checkbox" name="sabado" value="1" <?= $conSabado ? 'checked' : '' ?>> Mostrar sábado</label>
            <button type="submit">Abrir</button>
        </form>

        <?php if ($grupo === null): ?>
            <p class="texto-suave">Elige un grupo para armar o revisar su horario.</p>
        <?php elseif (!$asigs): ?>
            <div class="estado-vacio"><p>El grupo <?= $e($grupo['grado'] . ' ' . $grupo['nombre']) ?> no tiene clases asignadas en <?= (int) $anio['anio'] ?>. Asigna asignaturas y docentes en Plan académico.</p></div>
        <?php else: ?>
            <section class="panel-seccion">
                <h2><?= $e($grupo['grado'] . ' ' . $grupo['nombre']) ?> — <?= (int) $anio['anio'] ?></h2>
                <p class="texto-suave">En cada celda elige la clase (o déjala libre) y, si quieres, el salón. Al guardar se revisa que ningún docente ni salón quede en dos grupos a la misma hora.</p>
                <form method="POST" action="horarios_admin.php" id="form-horario" novalidate>
                    <?php campoCSRF(); ?>
                    <input type="hidden" name="accion" value="guardar_horario">
                    <input type="hidden" name="anio" value="<?= (int) $anio['id'] ?>">
                    <input type="hidden" name="grupo" value="<?= (int) $grupo['id'] ?>">
                    <?php if ($conSabado): ?><input type="hidden" name="sabado" value="1"><?php endif; ?>

                    <div class="horario-envoltura">
                        <table class="horario-tabla horario-editor">
                            <caption class="sr-only">Horario del grupo <?= $e($grupo['grado'] . ' ' . $grupo['nombre']) ?>: elige la clase de cada día y hora</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Hora</th>
                                    <?php foreach ($diasMostrar as $d): ?><th scope="col"><?= $e(Horario::DIAS[$d]) ?></th><?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($franjas as $fr): ?>
                                <?php if ($fr['descanso']): ?>
                                    <tr class="franja-descanso">
                                        <th scope="row"><?= $e($fr['nombre']) ?><small><?= $e($fr['inicio'] . '–' . $fr['fin']) ?></small></th>
                                        <td class="celda-descanso" colspan="<?= count($diasMostrar) ?>">Descanso</td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <th scope="row"><?= $e($fr['nombre']) ?><small><?= $e($fr['inicio'] . '–' . $fr['fin']) ?></small></th>
                                        <?php foreach ($diasMostrar as $d): $b = $bloques[$d][$fr['id']] ?? null; ?>
                                            <td>
                                                <select name="c[<?= $d ?>][<?= $fr['id'] ?>][a]" data-franja="<?= $fr['id'] ?>" aria-label="<?= $e(Horario::DIAS[$d] . ', ' . $fr['nombre'] . ': clase') ?>">
                                                    <option value="0">— Libre —</option>
                                                    <?php foreach ($asigs as $aid => $a): ?>
                                                        <option value="<?= (int) $aid ?>" <?= $b && $b['asignacion_id'] === (int) $aid ? 'selected' : '' ?>><?= $e($a['asignatura'] . ($a['docente'] ? ' · ' . $a['docente'] : '')) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="text" name="c[<?= $d ?>][<?= $fr['id'] ?>][s]" maxlength="30" value="<?= $e($b['salon'] ?? '') ?>" placeholder="Salón" aria-label="<?= $e(Horario::DIAS[$d] . ', ' . $fr['nombre'] . ': salón') ?>">
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <h3>Horas por semana de cada asignatura</h3>
                    <ul class="horas-asignatura" id="horas-asignatura">
                        <?php foreach ($horas as $h): ?>
                            <li data-asig="<?= (int) $h['id'] ?>" data-intensidad="<?= $h['intensidad'] !== null ? (int) $h['intensidad'] : '' ?>">
                                <span><?= $e($h['asignatura']) ?></span>
                                <span><strong class="n-horas"><?= (int) $h['horas'] ?></strong><?= $h['intensidad'] !== null ? ' de ' . (int) $h['intensidad'] : '' ?> h <span class="aviso-horas"></span></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="acad-acciones">
                        <button type="submit">Guardar horario</button>
                        <button type="button" class="boton-secundario" onclick="window.print()">Imprimir</button>
                        <span id="estado-horario" class="texto-suave" role="status" aria-live="polite"></span>
                    </div>
                </form>

                <script>
                // Cuenta en vivo las horas por asignatura y avisa de cambios sin guardar. Sin JavaScript el formulario funciona igual.
                (function () {
                    const form = document.getElementById('form-horario');
                    if (!form) return;
                    const estado = document.getElementById('estado-horario');
                    let sucio = false;
                    function contar() {
                        const n = {};
                        form.querySelectorAll('select[name^="c["]').forEach(function (s) { if (s.value !== '0') n[s.value] = (n[s.value] || 0) + 1; });
                        document.querySelectorAll('#horas-asignatura li').forEach(function (li) {
                            const horas = n[li.dataset.asig] || 0;
                            li.querySelector('.n-horas').textContent = horas;
                            const meta = li.dataset.intensidad === '' ? null : parseInt(li.dataset.intensidad, 10);
                            const aviso = li.querySelector('.aviso-horas');
                            aviso.textContent = meta === null ? '' : (horas < meta ? '(faltan ' + (meta - horas) + ')' : (horas > meta ? '(sobran ' + (horas - meta) + ')' : '✓'));
                        });
                    }
                    form.addEventListener('change', function (ev) {
                        sucio = true; estado.textContent = 'Hay cambios sin guardar.';
                        if (ev.target.tagName === 'SELECT') contar();
                    });
                    form.addEventListener('input', function (ev) { if (ev.target.type === 'text') { sucio = true; estado.textContent = 'Hay cambios sin guardar.'; } });
                    form.addEventListener('submit', function () { sucio = false; });
                    window.addEventListener('beforeunload', function (ev) { if (sucio) { ev.preventDefault(); ev.returnValue = ''; } });
                    contar();
                })();
                </script>
            </section>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/parciales/pie.php'; ?>
