<?php
declare(strict_types=1);

require_once __DIR__ . '/colegio.php';

// Cada función devuelve un párrafo (varias oraciones) en español, listo para mostrarse
// con el efecto de escritura (.efecto-escritura, ya en base.js). No es un modelo de IA:
// son resúmenes calculados con SQL directo sobre datos reales del rol. Ninguna de estas
// funciones diagnostica ni interpreta el estado emocional de nadie — solo describe
// cifras (cuántos registros, promedios, conteos, tendencias frente a la semana anterior).

function analisisEstudiante(PDO $pdo, int $usuarioId): string
{
    $emoActual = $pdo->prepare(
        "SELECT COUNT(*) total, AVG(nivel) promedio
         FROM estado_emocional
         WHERE usuario_id = :id AND fecha_hora >= CURDATE() - INTERVAL 7 DAY"
    );
    $emoActual->execute(['id' => $usuarioId]);
    $emo = $emoActual->fetch();

    $emoAnterior = $pdo->prepare(
        "SELECT AVG(nivel) promedio
         FROM estado_emocional
         WHERE usuario_id = :id AND fecha_hora >= CURDATE() - INTERVAL 14 DAY AND fecha_hora < CURDATE() - INTERVAL 7 DAY"
    );
    $emoAnterior->execute(['id' => $usuarioId]);
    $emoAnt = $emoAnterior->fetch();

    $sueno = $pdo->prepare(
        "SELECT AVG(horas) promedio, COUNT(*) total
         FROM sueno
         WHERE usuario_id = :id AND fecha >= CURDATE() - INTERVAL 7 DAY"
    );
    $sueno->execute(['id' => $usuarioId]);
    $suenoDatos = $sueno->fetch();

    $habitos = $pdo->prepare(
        "SELECT COUNT(*) total, COALESCE(SUM(sh.completado), 0) hechos
         FROM seguimiento_habitos sh
         INNER JOIN habitos h ON h.id = sh.habito_id
         WHERE h.usuario_id = :id AND sh.fecha >= CURDATE() - INTERVAL 7 DAY"
    );
    $habitos->execute(['id' => $usuarioId]);
    $habitosDatos = $habitos->fetch();

    $metas = $pdo->prepare(
        "SELECT COUNT(*) activas, AVG(porcentaje_avance) avance
         FROM metas WHERE usuario_id = :id AND estado = 'activa'"
    );
    $metas->execute(['id' => $usuarioId]);
    $metasDatos = $metas->fetch();

    $metasCompletadas = $pdo->prepare(
        "SELECT COUNT(*) FROM metas WHERE usuario_id = :id AND estado = 'completada'"
    );
    $metasCompletadas->execute(['id' => $usuarioId]);
    $totalCompletadas = (int) $metasCompletadas->fetchColumn();

    $diario = $pdo->prepare(
        "SELECT COUNT(*) FROM diario WHERE usuario_id = :id AND fecha >= CURDATE() - INTERVAL 7 DAY"
    );
    $diario->execute(['id' => $usuarioId]);
    $entradasDiario = (int) $diario->fetchColumn();

    if ((int) $emo['total'] === 0 && (int) $suenoDatos['total'] === 0 && (int) $habitosDatos['total'] === 0
        && (int) $metasDatos['activas'] === 0 && $entradasDiario === 0
    ) {
        return 'Todavía no tienes registros esta semana. Cuando empieces a anotar cómo te sientes, cómo duermes, tus hábitos y tus metas, aquí vas a ver un resumen completo de tu semana.';
    }

    $partes = [];

    if ((int) $emo['total'] > 0) {
        $promedio = round((float) $emo['promedio'], 1);
        $veces = (int) $emo['total'];
        $tendencia = '';
        if ($emoAnt['promedio'] !== null) {
            $anterior = round((float) $emoAnt['promedio'], 1);
            if ($promedio > $anterior) {
                $tendencia = ", subiendo respecto a la semana pasada ({$anterior}/5)";
            } elseif ($promedio < $anterior) {
                $tendencia = ", bajando respecto a la semana pasada ({$anterior}/5)";
            } else {
                $tendencia = ', igual que la semana pasada';
            }
        }
        $partes[] = "Esta semana registraste tu emoción {$veces} " . ($veces === 1 ? 'vez' : 'veces')
            . ", con un ánimo promedio de {$promedio}/5{$tendencia}.";
    } else {
        $partes[] = 'Esta semana todavía no registraste ninguna emoción.';
    }

    if ((int) $suenoDatos['total'] > 0) {
        $horas = round((float) $suenoDatos['promedio'], 1);
        $noches = (int) $suenoDatos['total'];
        $partes[] = "Dormiste un promedio de {$horas} horas por noche, con {$noches} " . ($noches === 1 ? 'noche registrada' : 'noches registradas') . '.';
    }

    if ((int) $habitosDatos['total'] > 0) {
        $partes[] = "Completaste {$habitosDatos['hechos']} de {$habitosDatos['total']} hábitos programados esta semana.";
    }

    $activas = (int) $metasDatos['activas'];
    if ($activas > 0) {
        $avance = round((float) $metasDatos['avance']);
        $partes[] = "Tienes {$activas} meta" . ($activas === 1 ? '' : 's') . " activa" . ($activas === 1 ? '' : 's')
            . ", con un avance promedio de {$avance}%" . ($totalCompletadas > 0 ? " (ya completaste {$totalCompletadas} en total)." : '.');
    } elseif ($totalCompletadas > 0) {
        $partes[] = "No tienes metas activas ahora mismo, pero ya completaste {$totalCompletadas} en total.";
    }

    if ($entradasDiario > 0) {
        $partes[] = "Escribiste en tu diario {$entradasDiario} " . ($entradasDiario === 1 ? 'vez' : 'veces') . ' esta semana.';
    }

    $partes[] = 'Sigue así, y recuerda que el psicólogo o tu orientador están para acompañarte si algo te está pesando.';

    return implode(' ', $partes);
}

function analisisPsicologo(PDO $pdo): string
{
    $alertas = $pdo->query(
        "SELECT nivel_urgencia, COUNT(*) total FROM alertas_tempranas WHERE atendida = 0" . sqlUsuarioDeColegio('usuario_id') . " GROUP BY nivel_urgencia"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $alto = (int) ($alertas['alto'] ?? 0);
    $medio = (int) ($alertas['medio'] ?? 0);
    $bajo = (int) ($alertas['bajo'] ?? 0);
    $totalAlertas = $alto + $medio + $bajo;

    $atendidasSemana = (int) $pdo->query(
        "SELECT COUNT(*) FROM alertas_tempranas WHERE atendida = 1 AND fecha_atendida >= CURDATE() - INTERVAL 7 DAY" . sqlUsuarioDeColegio('usuario_id') . ""
    )->fetchColumn();

    $totalEstudiantes = (int) $pdo->query(
        "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
         WHERE r.nombre = 'estudiante' AND u.activo = 1" . sqlColegio('u.colegio_id') . ""
    )->fetchColumn();

    $citasSemana = $pdo->query(
        "SELECT estado, COUNT(*) total FROM citas
         WHERE fecha >= CURDATE() AND fecha < CURDATE() + INTERVAL 7 DAY" . sqlUsuarioDeColegio('estudiante_id') . "
         GROUP BY estado"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $citasConfirmadas = (int) ($citasSemana['confirmada'] ?? 0);
    $citasAtendidas = (int) ($citasSemana['atendida'] ?? 0);

    $encuestasActivas = (int) $pdo->query(
        "SELECT COUNT(*) FROM encuestas WHERE (fecha_cierre IS NULL OR fecha_cierre > NOW())" . sqlColegio('colegio_id')
    )->fetchColumn();

    $respuestasRecibidas = (int) $pdo->query(
        "SELECT COUNT(*) FROM respuestas_encuesta re
         INNER JOIN preguntas_encuesta pe ON pe.id = re.pregunta_id
         INNER JOIN encuestas e ON e.id = pe.encuesta_id
         WHERE (e.fecha_cierre IS NULL OR e.fecha_cierre > NOW())" . sqlColegio('e.colegio_id')
    )->fetchColumn();

    $partes = [];

    if ($totalAlertas === 0) {
        $partes[] = "No hay alertas tempranas pendientes por atender en este momento, de un total de {$totalEstudiantes} estudiantes activos.";
    } else {
        $detalle = [];
        if ($alto > 0)  $detalle[] = "{$alto} de urgencia alta";
        if ($medio > 0) $detalle[] = "{$medio} de urgencia media";
        if ($bajo > 0)  $detalle[] = "{$bajo} de urgencia baja";
        $palabra = $totalAlertas === 1 ? 'alerta temprana pendiente' : 'alertas tempranas pendientes';
        $partes[] = "Tienes {$totalAlertas} {$palabra} (" . implode(', ', $detalle) . '). Te recomendamos priorizar las de urgencia alta.';
    }

    if ($atendidasSemana > 0) {
        $partes[] = "Esta semana ya atendiste {$atendidasSemana} " . ($atendidasSemana === 1 ? 'alerta' : 'alertas') . '.';
    }

    if ($citasConfirmadas > 0 || $citasAtendidas > 0) {
        $partes[] = "En los próximos 7 días tienes {$citasConfirmadas} " . ($citasConfirmadas === 1 ? 'cita confirmada' : 'citas confirmadas')
            . ($citasAtendidas > 0 ? " y ya atendiste {$citasAtendidas}." : '.');
    } else {
        $partes[] = 'No tienes citas agendadas para los próximos 7 días.';
    }

    if ($encuestasActivas > 0) {
        $partes[] = "Tienes {$encuestasActivas} encuesta" . ($encuestasActivas === 1 ? '' : 's') . " activa" . ($encuestasActivas === 1 ? '' : 's')
            . ", con {$respuestasRecibidas} respuesta" . ($respuestasRecibidas === 1 ? '' : 's') . ' recibidas hasta ahora.';
    }

    return implode(' ', $partes);
}

function analisisOrientador(PDO $pdo, array $gruposIds): string
{
    if (empty($gruposIds)) {
        return 'Todavía no tienes grupos asignados. Cuando el administrador te asigne uno o más grupos, aquí vas a ver un resumen completo de tus estudiantes.';
    }

    $marcadores = implode(',', array_fill(0, count($gruposIds), '?'));

    $c1 = $pdo->prepare(
        "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
         WHERE r.nombre = 'estudiante' AND u.grupo_id IN ({$marcadores})"
    );
    $c1->execute($gruposIds);
    $total = (int) $c1->fetchColumn();

    $c2 = $pdo->prepare(
        "SELECT nivel_urgencia, COUNT(*) total FROM alertas_tempranas a
         INNER JOIN usuarios u ON u.id = a.usuario_id
         WHERE a.atendida = 0 AND u.grupo_id IN ({$marcadores})
         GROUP BY nivel_urgencia"
    );
    $c2->execute($gruposIds);
    $alertasPorNivel = $c2->fetchAll(PDO::FETCH_KEY_PAIR);
    $totalAlertas = array_sum($alertasPorNivel);
    $altoAlertas = (int) ($alertasPorNivel['alto'] ?? 0);

    $c3 = $pdo->prepare(
        "SELECT AVG(e.nivel) promedio, COUNT(DISTINCT e.usuario_id) participantes
         FROM estado_emocional e INNER JOIN usuarios u ON u.id = e.usuario_id
         WHERE u.grupo_id IN ({$marcadores}) AND e.fecha_hora >= CURDATE() - INTERVAL 7 DAY"
    );
    $c3->execute($gruposIds);
    $animo = $c3->fetch();
    $participantes = (int) $animo['participantes'];

    $partes = [];
    $partes[] = "Tienes {$total} estudiante" . ($total === 1 ? '' : 's') . ' a cargo en tus grupos.';

    if ($participantes > 0) {
        $prom = round((float) $animo['promedio'], 1);
        $sinRegistro = max(0, $total - $participantes);
        $partes[] = "{$participantes} de ellos registraron su emoción esta semana (ánimo promedio {$prom}/5)"
            . ($sinRegistro > 0 ? ", y {$sinRegistro} todavía no han registrado nada." : '.');
    } else {
        $partes[] = 'Ningún estudiante de tus grupos ha registrado su emoción esta semana todavía.';
    }

    if ($totalAlertas > 0) {
        $partes[] = "Hay {$totalAlertas} alerta" . ($totalAlertas === 1 ? '' : 's') . ' temprana' . ($totalAlertas === 1 ? '' : 's') . ' sin atender entre ellos'
            . ($altoAlertas > 0 ? ", {$altoAlertas} de urgencia alta." : '.');
    } else {
        $partes[] = 'No hay alertas tempranas pendientes en tus grupos en este momento.';
    }

    return implode(' ', $partes);
}

function analisisDocente(PDO $pdo, ?int $grupoId): string
{
    if ($grupoId === null) {
        return 'Todavía no tienes un grupo asignado. Cuando el administrador te asigne uno, aquí vas a ver un resumen completo de tus estudiantes.';
    }

    $c1 = $pdo->prepare(
        "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
         WHERE r.nombre = 'estudiante' AND u.grupo_id = :gid"
    );
    $c1->execute(['gid' => $grupoId]);
    $total = (int) $c1->fetchColumn();

    $c2 = $pdo->prepare(
        "SELECT AVG(e.nivel) promedio, COUNT(DISTINCT e.usuario_id) participantes
         FROM estado_emocional e INNER JOIN usuarios u ON u.id = e.usuario_id
         WHERE u.grupo_id = :gid AND e.fecha_hora >= CURDATE() - INTERVAL 7 DAY"
    );
    $c2->execute(['gid' => $grupoId]);
    $animo = $c2->fetch();
    $participantes = (int) $animo['participantes'];

    $c3 = $pdo->prepare(
        "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
         WHERE r.nombre = 'estudiante' AND u.grupo_id = :gid
           AND (u.ultimo_acceso IS NULL OR u.ultimo_acceso < NOW() - INTERVAL 14 DAY)"
    );
    $c3->execute(['gid' => $grupoId]);
    $inactivos = (int) $c3->fetchColumn();

    $partes = [];
    $partes[] = "Tu grupo tiene {$total} estudiante" . ($total === 1 ? '' : 's') . ' registrado' . ($total === 1 ? '' : 's') . '.';

    if ($participantes > 0) {
        $prom = round((float) $animo['promedio'], 1);
        $sinRegistro = max(0, $total - $participantes);
        $partes[] = "{$participantes} registraron su emoción esta semana, con un ánimo promedio de {$prom}/5"
            . ($sinRegistro > 0 ? ", y {$sinRegistro} todavía no han registrado nada." : '.');
    } else {
        $partes[] = 'Ningún estudiante ha registrado su emoción esta semana todavía.';
    }

    if ($inactivos > 0) {
        $partes[] = "{$inactivos} estudiante" . ($inactivos === 1 ? '' : 's') . " no ha" . ($inactivos === 1 ? '' : 'n') . ' iniciado sesión en más de 14 días.';
    }

    return implode(' ', $partes);
}

function analisisCoordinador(PDO $pdo): string
{
    $totalAlertas = (int) $pdo->query("SELECT COUNT(*) FROM alertas_tempranas WHERE atendida = 0" . sqlUsuarioDeColegio('usuario_id'))->fetchColumn();
    $totalEstudiantes = (int) $pdo->query(
        "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'estudiante' AND u.activo = 1" . sqlColegio('u.colegio_id') . ""
    )->fetchColumn();

    $atendidasSemana = (int) $pdo->query(
        "SELECT COUNT(*) FROM alertas_tempranas WHERE atendida = 1 AND fecha_atendida >= CURDATE() - INTERVAL 7 DAY" . sqlUsuarioDeColegio('usuario_id') . ""
    )->fetchColumn();

    $grupoTop = $pdo->query(
        "SELECT gr.nombre AS grado, g.nombre AS grupo, COUNT(*) total
         FROM alertas_tempranas a
         INNER JOIN usuarios u ON u.id = a.usuario_id
         INNER JOIN grupos g ON g.id = u.grupo_id
         INNER JOIN grados gr ON gr.id = g.grado_id
         WHERE a.atendida = 0" . sqlColegio('u.colegio_id') . "
         GROUP BY g.id
         ORDER BY total DESC
         LIMIT 1"
    )->fetch();

    $totalGrupos = (int) $pdo->query('SELECT COUNT(*) FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE 1=1' . sqlColegio('gr.colegio_id'))->fetchColumn();
    $gruposConRegistro = (int) $pdo->query(
        "SELECT COUNT(DISTINCT u.grupo_id) FROM estado_emocional e
         INNER JOIN usuarios u ON u.id = e.usuario_id
         WHERE e.fecha_hora >= CURDATE() - INTERVAL 7 DAY AND u.grupo_id IS NOT NULL" . sqlColegio('u.colegio_id') . ""
    )->fetchColumn();

    $partes = [];
    $partes[] = "Hay {$totalEstudiantes} estudiantes activos y {$totalAlertas} alerta" . ($totalAlertas === 1 ? '' : 's')
        . ' temprana' . ($totalAlertas === 1 ? '' : 's') . ' sin atender.';

    if ($grupoTop) {
        $partes[] = "El grupo con más alertas pendientes es {$grupoTop['grado']} {$grupoTop['grupo']} ({$grupoTop['total']}).";
    }

    if ($atendidasSemana > 0) {
        $partes[] = "Esta semana se atendieron {$atendidasSemana} alerta" . ($atendidasSemana === 1 ? '' : 's') . ' en total.';
    }

    $partes[] = "{$gruposConRegistro} de {$totalGrupos} grupos tienen al menos un registro emocional esta semana.";

    return implode(' ', $partes);
}

function analisisRector(PDO $pdo): string
{
    $totalEstudiantes = (int) $pdo->query(
        "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'estudiante' AND u.activo = 1" . sqlColegio('u.colegio_id') . ""
    )->fetchColumn();

    $participantes = (int) $pdo->query(
        "SELECT COUNT(DISTINCT usuario_id) FROM estado_emocional WHERE fecha_hora >= CURDATE() - INTERVAL 7 DAY" . sqlUsuarioDeColegio('usuario_id') . ""
    )->fetchColumn();

    $alertasCriticas = (int) $pdo->query(
        "SELECT COUNT(*) FROM alertas_tempranas WHERE atendida = 0 AND nivel_urgencia = 'alto'" . sqlUsuarioDeColegio('usuario_id') . ""
    )->fetchColumn();

    $totalAlertas = (int) $pdo->query("SELECT COUNT(*) FROM alertas_tempranas WHERE atendida = 0" . sqlUsuarioDeColegio('usuario_id'))->fetchColumn();

    $promedioMes = $pdo->query(
        "SELECT AVG(nivel) FROM estado_emocional WHERE fecha_hora >= NOW() - INTERVAL 30 DAY" . sqlUsuarioDeColegio('usuario_id') . ""
    )->fetchColumn();

    $usuariosPorRol = $pdo->query(
        "SELECT r.nombre AS rol, COUNT(*) AS total FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE r.nombre != 'estudiante'" . sqlColegio('u.colegio_id') . "
         GROUP BY r.nombre ORDER BY total DESC"
    )->fetchAll();

    $porcentaje = $totalEstudiantes > 0 ? (int) round(($participantes / $totalEstudiantes) * 100) : 0;

    $partes = [];
    $partes[] = "De {$totalEstudiantes} estudiantes activos, {$participantes} ({$porcentaje}%) registraron su estado emocional esta semana.";

    if ($promedioMes !== null && $promedioMes !== false) {
        $partes[] = 'El ánimo promedio de los últimos 30 días es de ' . round((float) $promedioMes, 1) . '/5.';
    }

    $partes[] = $totalAlertas > 0
        ? "Hay {$totalAlertas} alerta" . ($totalAlertas === 1 ? '' : 's') . ' temprana' . ($totalAlertas === 1 ? '' : 's') . ' sin atender'
            . ($alertasCriticas > 0 ? ", {$alertasCriticas} de urgencia alta." : '.')
        : 'No hay alertas tempranas pendientes en este momento.';

    if (!empty($usuariosPorRol)) {
        $resumenRoles = implode(', ', array_map(fn($r) => "{$r['total']} {$r['rol']}", $usuariosPorRol));
        $partes[] = "Equipo de acompañamiento: {$resumenRoles}.";
    }

    return implode(' ', $partes);
}

function analisisAdministrador(PDO $pdo): string
{
    $totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE 1=1' . sqlColegio('colegio_id'))->fetchColumn();
    $totalEstudiantes = (int) $pdo->query(
        "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'estudiante'" . sqlColegio('u.colegio_id') . ""
    )->fetchColumn();
    $totalInactivos = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE activo = 0' . sqlColegio('colegio_id'))->fetchColumn();
    $solicitudesPendientes = (int) $pdo->query(
        "SELECT COUNT(*) FROM solicitudes_cambio WHERE estado = 'pendiente'" . sqlUsuarioDeColegio('usuario_id')
    )->fetchColumn();
    $registrosRecientes = (int) $pdo->query(
        "SELECT COUNT(*) FROM usuarios WHERE fecha_registro >= CURDATE() - INTERVAL 7 DAY" . sqlColegio('colegio_id')
    )->fetchColumn();

    $usuariosPorRol = $pdo->query(
        "SELECT r.nombre AS rol, COUNT(*) AS total FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE r.nombre != 'estudiante'" . sqlColegio('u.colegio_id') . "
         GROUP BY r.nombre ORDER BY total DESC"
    )->fetchAll();

    $accionesRecientes = (int) $pdo->query(
        "SELECT COUNT(*) FROM auditoria WHERE fecha >= CURDATE() - INTERVAL 7 DAY" . sqlColegio('colegio_id')
    )->fetchColumn();

    $partes = [];
    $partes[] = "La plataforma tiene {$totalUsuarios} usuarios registrados en total, {$totalEstudiantes} de ellos estudiantes.";

    if (!empty($usuariosPorRol)) {
        $resumenRoles = implode(', ', array_map(fn($r) => "{$r['total']} {$r['rol']}", $usuariosPorRol));
        $partes[] = "Personal: {$resumenRoles}.";
    }

    if ($totalInactivos > 0) {
        $partes[] = "{$totalInactivos} cuenta" . ($totalInactivos === 1 ? ' está desactivada' : 's están desactivadas') . '.';
    }

    if ($registrosRecientes > 0) {
        $verbo = $registrosRecientes === 1 ? 'se creó' : 'se crearon';
        $partes[] = "{$registrosRecientes} cuenta" . ($registrosRecientes === 1 ? '' : 's') . " nueva" . ($registrosRecientes === 1 ? '' : 's') . " {$verbo} en los últimos 7 días.";
    }

    $partes[] = $solicitudesPendientes > 0
        ? "Hay {$solicitudesPendientes} solicitud" . ($solicitudesPendientes === 1 ? '' : 'es') . ' de cambio pendiente' . ($solicitudesPendientes === 1 ? '' : 's') . ' por revisar.'
        : 'No hay solicitudes de cambio pendientes.';

    if ($accionesRecientes > 0) {
        $partes[] = "Se registraron {$accionesRecientes} acciones en el log de auditoría en los últimos 7 días.";
    }

    return implode(' ', $partes);
}
