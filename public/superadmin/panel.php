<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Colegio.php';
require_once __DIR__ . '/../../src/modelos/ReporteColegios.php';

require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

// Solo cifras agregadas por colegio: el superadmin (Secretaría de Educación) administra
// la plataforma, pero NO tiene acceso a datos individuales de estudiantes (diseño de privacidad).
$metricas = ReporteColegios::metricas($pdo, date('Y-m-d H:i:s', strtotime('-30 days')), date('Y-m-d H:i:s', strtotime('+1 day')));
$totalColegios = count($metricas);
$colegiosActivos = count(array_filter($metricas, fn($c) => (int) $c['activo'] === 1));
$totalEstudiantes = array_sum(array_map(fn($c) => (int) $c['estudiantes'], $metricas));
$conRegistro = array_sum(array_map(fn($c) => (int) $c['con_registro'], $metricas));
$participacion = $totalEstudiantes > 0 ? round(100 * $conRegistro / $totalEstudiantes) : 0;
$altasPendientes = array_sum(array_map(fn($c) => (int) $c['altas_pendientes'], $metricas));
$totalPersonal = (int) $pdo->query("SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                                    WHERE u.activo = 1 AND u.colegio_id IS NOT NULL AND r.nombre <> 'estudiante'"
                                    . sqlAlcanceColegios($pdo, 'u.colegio_id'))->fetchColumn();

$semaforo = ['rojo' => 0, 'amarillo' => 0, 'verde' => 0, 'gris' => 0];
$atencion = [];
foreach ($metricas as $m) {
    $s = ReporteColegios::semaforo($m);
    $semaforo[$s]++;
    if ($s === 'rojo' || $s === 'amarillo') { $atencion[] = $m + ['semaforo' => $s]; }
}
usort($atencion, fn($a, $b) => [$a['semaforo'] === 'rojo' ? 0 : 1, -(int) $a['altas_pendientes']] <=> [$b['semaforo'] === 'rojo' ? 0 : 1, -(int) $b['altas_pendientes']]);
$atencion = array_slice($atencion, 0, 6);

$correoCuenta = (string) ($pdo->query('SELECT correo FROM usuarios WHERE id = ' . (int) $_SESSION['usuario_id'])->fetchColumn() ?: '');
$primerNombre = explode(' ', trim((string) $_SESSION['nombre']))[0] ?? '';
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

$secretariaCtx = esSecretaria() ? secretariaActual($pdo) : null;
$tituloPagina = esSecretaria() ? 'Panel de la secretaría' : 'Panel de plataforma';
require __DIR__ . '/../parciales/encabezado.php';
?>

<section class="sa-hero">
    <div>
        <h1><?= $saludo ?>, <?= htmlspecialchars($primerNombre) ?></h1>
        <?php if (esSecretaria()): ?>
            <p><strong><?= htmlspecialchars($secretariaCtx['nombre'] ?? 'Sin secretaría asignada') ?></strong>
            <?php if (!empty($secretariaCtx['municipios'])): ?> · <?= htmlspecialchars(implode(', ', $secretariaCtx['municipios'])) ?><?php endif; ?>
            <br>Una vista de los colegios a tu cargo. Solo cifras agregadas, sin datos individuales de estudiantes.</p>
        <?php else: ?>
            <p>Panel de la plataforma: una vista de todos los colegios y secretarías en un solo lugar. Solo cifras agregadas, sin datos individuales de estudiantes.</p>
        <?php endif; ?>
    </div>
    <div class="sa-hero-cuenta">
        <strong>Rol</strong><span><?= esSecretaria() ? 'Secretaría de Educación' : 'Superadmin · Equipo de Serena' ?></span>
        <strong>Ingreso con</strong><span><?= htmlspecialchars($correoCuenta) ?></span>
    </div>
</section>

<div class="sa-kpis">
    <div class="sa-kpi">
        <div class="sa-kpi-etiqueta">Colegios activos</div>
        <div class="sa-kpi-valor"><?= $colegiosActivos ?></div>
        <div class="sa-kpi-pie">de <?= $totalColegios ?> registrados</div>
    </div>
    <div class="sa-kpi es-azul">
        <div class="sa-kpi-etiqueta">Estudiantes activos</div>
        <div class="sa-kpi-valor"><?= number_format($totalEstudiantes) ?></div>
        <div class="sa-kpi-pie"><?= number_format($totalPersonal) ?> personas de personal</div>
    </div>
    <div class="sa-kpi es-verde">
        <div class="sa-kpi-etiqueta">Participación (30 días)</div>
        <div class="sa-kpi-valor"><?= $participacion ?>%</div>
        <div class="sa-kpi-pie"><?= number_format($conRegistro) ?> estudiantes con registro</div>
    </div>
    <div class="sa-kpi <?= $altasPendientes > 0 ? 'es-rojo' : 'es-verde' ?>">
        <div class="sa-kpi-etiqueta">Alertas altas pendientes</div>
        <div class="sa-kpi-valor"><?= number_format($altasPendientes) ?></div>
        <div class="sa-kpi-pie"><?= $altasPendientes > 0 ? 'Sin atender en los colegios' : 'Todo atendido' ?></div>
    </div>
</div>

<div class="sa-grid-2">
    <div>
        <section class="panel-seccion">
            <h2>🚦 Estado de los colegios <small class="texto-suave">· últimos 30 días</small></h2>
            <div class="sa-semaforo">
                <div class="sa-semaforo-item rojo"><b><?= $semaforo['rojo'] ?></b>Atención</div>
                <div class="sa-semaforo-item amarillo"><b><?= $semaforo['amarillo'] ?></b>Seguimiento</div>
                <div class="sa-semaforo-item verde"><b><?= $semaforo['verde'] ?></b>Bien</div>
                <div class="sa-semaforo-item gris"><b><?= $semaforo['gris'] ?></b>Sin datos</div>
            </div>
            <p class="sa-espaciado"><a href="comparativo.php" class="boton-acceso">Ver comparativo completo</a></p>
        </section>

        <section class="panel-seccion">
            <h2>Colegios</h2>
            <?php if (!$metricas): ?>
                <p class="mensaje mensaje-info">Todavía no hay colegios. <a href="colegios.php">Crear el primero</a> o <a href="colegios_importar.php">importarlos en masa</a>.</p>
            <?php else: ?>
                <div class="sa-scroll">
                <table class="tabla">
                    <thead><tr><th>Colegio</th><th>Municipio</th><th>Estudiantes</th><th>Participación</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($metricas as $c): $uso = ReporteColegios::participacion($c); ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nombre']) ?></td>
                            <td><?= htmlspecialchars((string) ($c['municipio'] ?? '')) ?></td>
                            <td><?= (int) $c['estudiantes'] ?></td>
                            <td><?= $uso === null ? '—' : $uso . ' %' ?></td>
                            <td><?= (int) $c['activo'] === 1 ? '<span class="sa-chip ok">Activo</span>' : '<span class="sa-chip off">Suspendido</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <p class="sa-espaciado"><a href="colegios.php" class="boton-acceso">Gestionar colegios</a></p>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="panel-seccion">
            <h2>⚡ Accesos rápidos</h2>
            <div class="sa-accesos">
                <a class="sa-acceso" href="colegios.php"><span>🏫</span>Colegios</a>
                <a class="sa-acceso" href="colegios_importar.php"><span>📥</span>Importar</a>
                <a class="sa-acceso" href="personal.php"><span>👥</span>Personal</a>
                <a class="sa-acceso" href="comunicados.php"><span>📣</span>Comunicados</a>
                <a class="sa-acceso" href="reportes.php"><span>📊</span>Reportes</a>
                <?php if (esSuperadmin()): ?><a class="sa-acceso" href="secretarias.php"><span>🏛️</span>Secretarías</a><?php endif; ?>
                <a class="sa-acceso" href="perfil.php"><span>👤</span>Mi perfil</a>
            </div>
        </section>

        <section class="panel-seccion">
            <h2>🔔 Requieren atención</h2>
            <?php if (!$atencion): ?>
                <p class="texto-suave">Ningún colegio en rojo o amarillo. 🎉</p>
            <?php else: ?>
                <ul class="sa-lista">
                <?php foreach ($atencion as $a): $uso = ReporteColegios::participacion($a); ?>
                    <li>
                        <div>
                            <?= htmlspecialchars($a['nombre']) ?>
                            <small>Participación <?= $uso ?> % · <?= (int) $a['altas_pendientes'] ?> alerta(s) alta(s) pendiente(s)</small>
                        </div>
                        <span class="sa-chip <?= $a['semaforo'] === 'rojo' ? 'bad' : 'warn' ?>"><?= $a['semaforo'] === 'rojo' ? 'Atención' : 'Seguimiento' ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
