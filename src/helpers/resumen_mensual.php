<?php
declare(strict_types=1);

require_once __DIR__ . '/colegio.php';

require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/../modelos/Usuario.php';

/**
 * Resumen institucional del mes anterior, por correo, para coordinación y
 * rectoría — mismas cifras agrupadas que ya ven en sus Reportes/Panel, para
 * quien prefiere enterarse sin tener que entrar a la plataforma.
 *
 * Se llama desde public/tareas/recordatorios.php (el mismo cron de siempre, cada
 * 15 minutos) y se autolimita con la tabla `configuracion`: solo envía una vez
 * por mes, el primer día del mes, sin necesitar una tarea programada aparte.
 */
function enviarResumenMensualSiToca(PDO $pdo): void
{
    if ((int) date('j') !== 1) {
        return; // solo el día 1 de cada mes
    }
    if (!smtpConfigurado()) {
        return;
    }
    // Multi-colegio: un resumen por colegio, solo con SUS cifras y a SUS coordinadores/rectores.
    $colegios = $pdo->query('SELECT id FROM colegios WHERE activo = 1')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($colegios as $colegioId) {
        enviarResumenMensualDeColegio($pdo, (int) $colegioId);
    }
}

function enviarResumenMensualDeColegio(PDO $pdo, int $colegioId): void
{
    $mesActual = date('Y-m'); // mes que arranca hoy; el resumen es del mes ANTERIOR
    $yaEnviado = configuracionObtener($pdo, 'resumen_mensual_ultimo_envio', '', $colegioId);
    if ($yaEnviado === $mesActual) {
        return; // ya se mandó este mes a este colegio — evita reenviar en cada corrida del cron
    }

    if (!smtpConfigurado()) {
        // Sin correo configurado no hay nada que mandar. A propósito NO se marca como
        // enviado: si el administrador configura el correo más tarde ese mismo día 1,
        // la siguiente corrida del cron (15 min después) sí alcanza a mandarlo.
        return;
    }

    $inicio = date('Y-m-01', strtotime('first day of last month'));
    $fin = date('Y-m-t', strtotime('last day of last month'));
    $nombreMes = nombreMesEnEspanol((int) date('n', strtotime($inicio))) . ' de ' . date('Y', strtotime($inicio));

    $resumen = calcularResumenMensual($pdo, $inicio, $fin, $colegioId);
    $html = resumenMensualHtml($nombreMes, $resumen);
    $textoPlano = "Resumen de Serena — {$nombreMes}\n\n" . resumenMensualTextoPlano($resumen);

    $destinatarios = array_merge(
        Usuario::listarPorRol($pdo, 'coordinador', $colegioId),
        Usuario::listarPorRol($pdo, 'rector', $colegioId)
    );

    foreach ($destinatarios as $d) {
        enviarCorreo((string) $d['correo'], "Resumen mensual de Serena — {$nombreMes}", $textoPlano, $html);
    }

    // Se marca como enviado AUNQUE no haya destinatarios o el envío individual falle
    // (mismo criterio de "mejor esfuerzo" que el resto del cron) — así no se reintenta
    // sin fin durante el día 1 completo. Si smtpConfigurado() fue false arriba, en
    // cambio, NO se llega hasta aquí, así que si configuran el correo más tarde ese
    // mismo día 1, el siguiente disparo del cron sí alcanza a mandarlo.
    configuracionGuardar($pdo, 'resumen_mensual_ultimo_envio', $mesActual, $colegioId);
}

function calcularResumenMensual(PDO $pdo, string $inicio, string $fin, ?int $colegioId = null): array
{
    $uCol = sqlUsuarioDeColegio('usuario_id', $colegioId);
    $totalEstudiantes = (int) $pdo->query(
        "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'estudiante' AND u.activo = 1" . sqlColegio('u.colegio_id', $colegioId)
    )->fetchColumn();

    $c = $pdo->prepare(
        "SELECT COUNT(DISTINCT usuario_id) FROM estado_emocional WHERE fecha_hora BETWEEN :i AND :f" . $uCol
    );
    $c->execute(['i' => $inicio . ' 00:00:00', 'f' => $fin . ' 23:59:59']);
    $participantes = (int) $c->fetchColumn();

    $c = $pdo->prepare("SELECT AVG(nivel) FROM estado_emocional WHERE fecha_hora BETWEEN :i AND :f" . $uCol);
    $c->execute(['i' => $inicio . ' 00:00:00', 'f' => $fin . ' 23:59:59']);
    $animoPromedio = $c->fetchColumn();

    $c = $pdo->prepare("SELECT nivel_urgencia, COUNT(*) AS n FROM alertas_tempranas WHERE fecha_generada BETWEEN :i AND :f" . $uCol . " GROUP BY nivel_urgencia");
    $c->execute(['i' => $inicio . ' 00:00:00', 'f' => $fin . ' 23:59:59']);
    $alertasPorNivel = $c->fetchAll(PDO::FETCH_KEY_PAIR);
    $alertasGeneradas = array_sum($alertasPorNivel);

    $c = $pdo->prepare("SELECT COUNT(*) FROM alertas_tempranas WHERE atendida = 1 AND fecha_atendida BETWEEN :i AND :f" . $uCol);
    $c->execute(['i' => $inicio . ' 00:00:00', 'f' => $fin . ' 23:59:59']);
    $alertasAtendidas = (int) $c->fetchColumn();

    $alertasPendientesHoy = (int) $pdo->query("SELECT COUNT(*) FROM alertas_tempranas WHERE atendida = 0" . $uCol)->fetchColumn();

    $c = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE fecha BETWEEN :i AND :f AND estado = 'atendida'" . sqlUsuarioDeColegio('estudiante_id', $colegioId));
    $c->execute(['i' => $inicio, 'f' => $fin]);
    $citasAtendidas = (int) $c->fetchColumn();

    $c = $pdo->prepare(
        "SELECT gr.nombre AS grado, g.nombre AS grupo, AVG(e.nivel) AS promedio, COUNT(*) AS n
         FROM estado_emocional e
         INNER JOIN usuarios u ON u.id = e.usuario_id
         INNER JOIN grupos g ON g.id = u.grupo_id
         INNER JOIN grados gr ON gr.id = g.grado_id
         WHERE e.fecha_hora BETWEEN :i AND :f" . sqlColegio('u.colegio_id', $colegioId) . "
         GROUP BY g.id HAVING n >= 3 ORDER BY promedio ASC LIMIT 3"
    );
    $c->execute(['i' => $inicio . ' 00:00:00', 'f' => $fin . ' 23:59:59']);
    $gruposAnimoBajo = $c->fetchAll();

    $c = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE fecha_registro BETWEEN :i AND :f" . sqlColegio('colegio_id', $colegioId));
    $c->execute(['i' => $inicio . ' 00:00:00', 'f' => $fin . ' 23:59:59']);
    $registrosNuevos = (int) $c->fetchColumn();

    return [
        'total_estudiantes'    => $totalEstudiantes,
        'participantes'        => $participantes,
        'porcentaje_participa' => $totalEstudiantes > 0 ? (int) round($participantes / $totalEstudiantes * 100) : 0,
        'animo_promedio'       => $animoPromedio !== null ? round((float) $animoPromedio, 1) : null,
        'alertas_generadas'    => $alertasGeneradas,
        'alertas_alto'         => (int) ($alertasPorNivel['alto'] ?? 0),
        'alertas_atendidas'    => $alertasAtendidas,
        'alertas_pendientes_hoy' => $alertasPendientesHoy,
        'citas_atendidas'      => $citasAtendidas,
        'grupos_animo_bajo'    => $gruposAnimoBajo,
        'registros_nuevos'     => $registrosNuevos,
    ];
}

function resumenMensualTextoPlano(array $r): string
{
    $lineas = [];
    $lineas[] = "{$r['total_estudiantes']} estudiantes activos, {$r['participantes']} registraron su emoción al menos una vez ({$r['porcentaje_participa']}%).";
    if ($r['animo_promedio'] !== null) {
        $lineas[] = "Ánimo promedio del mes: {$r['animo_promedio']}/5.";
    }
    $lineas[] = "{$r['alertas_generadas']} alertas tempranas generadas ({$r['alertas_alto']} de urgencia alta), {$r['alertas_atendidas']} atendidas en el mes. {$r['alertas_pendientes_hoy']} siguen pendientes hoy.";
    $lineas[] = "{$r['citas_atendidas']} citas atendidas con psicología/orientación.";
    if ($r['registros_nuevos'] > 0) {
        $lineas[] = "{$r['registros_nuevos']} cuentas nuevas creadas en el mes.";
    }
    return implode("\n", $lineas);
}

function resumenMensualHtml(string $nombreMes, array $r): string
{
    $filas = '';
    $fila = function (string $etiqueta, string $valor) use (&$filas): void {
        $filas .= "<tr><td style=\"padding:6px 10px;color:#6B6B6B\">{$etiqueta}</td><td style=\"padding:6px 10px;font-weight:600\">{$valor}</td></tr>";
    };
    $fila('Estudiantes activos', (string) $r['total_estudiantes']);
    $fila('Registraron su emoción', "{$r['participantes']} ({$r['porcentaje_participa']}%)");
    if ($r['animo_promedio'] !== null) {
        $fila('Ánimo promedio del mes', $r['animo_promedio'] . '/5');
    }
    $fila('Alertas tempranas generadas', "{$r['alertas_generadas']} ({$r['alertas_alto']} de urgencia alta)");
    $fila('Alertas atendidas en el mes', (string) $r['alertas_atendidas']);
    $fila('Alertas pendientes hoy', (string) $r['alertas_pendientes_hoy']);
    $fila('Citas atendidas', (string) $r['citas_atendidas']);
    if ($r['registros_nuevos'] > 0) {
        $fila('Cuentas nuevas en el mes', (string) $r['registros_nuevos']);
    }

    $gruposHtml = '';
    if (!empty($r['grupos_animo_bajo'])) {
        $items = array_map(
            fn($g) => '<li>' . htmlspecialchars($g['grado'] . ' ' . $g['grupo']) . ' — promedio ' . round((float) $g['promedio'], 1) . '/5</li>',
            $r['grupos_animo_bajo']
        );
        $gruposHtml = '<p style="margin-top:16px"><strong>Grupos con el ánimo más bajo este mes</strong> (solo orientativo, sin reemplazar el seguimiento de psicología/orientación):</p><ul>' . implode('', $items) . '</ul>';
    }

    $cuerpo = "<p>Resumen institucional de <strong>{$nombreMes}</strong>:</p>"
        . "<table style=\"width:100%;border-collapse:collapse\">{$filas}</table>"
        . $gruposHtml
        . '<p style="margin-top:16px;color:#6B6B6B;font-size:13px">Estas son cifras agrupadas; ningún dato individual de un estudiante se incluye en este correo.</p>';

    return plantillaCorreoHtml("Resumen de {$nombreMes}", $cuerpo);
}

function nombreMesEnEspanol(int $mes): string
{
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
              'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $meses[$mes] ?? '';
}
