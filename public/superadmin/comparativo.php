<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/ReporteColegios.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

$dias = (int) ($_GET['dias'] ?? 30);
if (!in_array($dias, [7, 30, 90], true)) { $dias = 30; }
$desde = date('Y-m-d H:i:s', strtotime("-{$dias} days"));
$hasta = date('Y-m-d H:i:s', strtotime('+1 day'));
$filas = ReporteColegios::metricas($pdo, $desde, $hasta);

$orden = ['rojo' => 0, 'amarillo' => 1, 'verde' => 2, 'gris' => 3];
usort($filas, fn($a, $b) => [$orden[ReporteColegios::semaforo($a)], $a['nombre']] <=> [$orden[ReporteColegios::semaforo($b)], $b['nombre']]);
$cuenta = ['rojo' => 0, 'amarillo' => 0, 'verde' => 0, 'gris' => 0];
foreach ($filas as $f) { $cuenta[ReporteColegios::semaforo($f)]++; }
$etiquetas = ['rojo' => '🔴 Atención', 'amarillo' => '🟡 Seguimiento', 'verde' => '🟢 Bien', 'gris' => '⚪ Sin datos / suspendido'];

$tituloPagina = 'Comparativo de colegios';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Comparativo de colegios</h1>
<p class="texto-suave">Solo cifras agregadas por colegio, sin datos de estudiantes individuales. Periodo: últimos <?= $dias ?> días.</p>

<form action="comparativo.php" method="GET">
    <label>Periodo
        <select name="dias" onchange="this.form.submit()">
            <?php foreach ([7, 30, 90] as $d): ?><option value="<?= $d ?>" <?= $d === $dias ? 'selected' : '' ?>>Últimos <?= $d ?> días</option><?php endforeach; ?>
        </select>
    </label>
    <noscript><button type="submit">Ver</button></noscript>
</form>

<section class="panel-seccion">
    <p>
        <?php foreach ($cuenta as $k => $n): ?><strong><?= $etiquetas[$k] ?>:</strong> <?= $n ?> &nbsp; <?php endforeach; ?>
    </p>
    <p class="texto-suave">
        Criterio: 🟢 participación ≥ 60 % y sin alertas altas pendientes · 🟡 participación 30–59 % o 1–4 alertas altas pendientes ·
        🔴 participación &lt; 30 % o 5+ alertas altas pendientes. Participación = estudiantes con al menos un registro emocional en el periodo.
    </p>
</section>

<section class="panel-seccion">
    <table class="tabla-datos">
        <thead><tr>
            <th>Estado</th><th>Colegio</th><th>Estudiantes</th><th>Participación</th><th>Promedio emocional</th>
            <th>Alertas altas pendientes</th><th>Último acceso del personal</th>
        </tr></thead>
        <tbody>
        <?php foreach ($filas as $f):
            $uso = ReporteColegios::participacion($f); $prom = ReporteColegios::promedio($f); ?>
            <tr>
                <td><?= $etiquetas[ReporteColegios::semaforo($f)] ?></td>
                <td><?= htmlspecialchars($f['nombre']) ?><br><span class="texto-suave"><?= htmlspecialchars((string) ($f['municipio'] ?? '')) ?></span></td>
                <td><?= (int) $f['estudiantes'] ?></td>
                <td><?= $uso === null ? '—' : $uso . ' %' ?></td>
                <td><?= $prom === null ? '—' : $prom . ' / 5' ?></td>
                <td><?= (int) $f['altas_pendientes'] ?></td>
                <td><?= $f['ultimo_acceso_personal'] ? date('d/m/Y', strtotime($f['ultimo_acceso_personal'])) : 'Nunca' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="texto-suave">"—" en promedio = menos de <?= ReporteColegios::MIN_PARA_PROMEDIO ?> estudiantes con registro (se oculta para proteger la privacidad).</p>
</section>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
