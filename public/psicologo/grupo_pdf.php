<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/DocumentoPdf.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/Sueno.php';
requiereRol('psicologo', 'orientador');

// Mismo control de acceso que caso_pdf.php y orientador/grupo.php: el
// orientador solo puede exportar sus propios grupos asignados; el psicólogo
// puede exportar cualquiera (ve todos los estudiantes, igual que en
// psicologo/estudiantes.php).
$grupoId = (int) ($_GET['grupo_id'] ?? 0);
if ($grupoId <= 0) { http_response_code(400); exit('Falta el grupo.'); }

if ($_SESSION['rol'] === 'orientador') {
    $gruposOrientador = $_SESSION['grupos_ids'] ?? [];
    if (!in_array($grupoId, $gruposOrientador, true)) {
        http_response_code(404); exit('Grupo no encontrado.');
    }
}

exigirGrupoDeMiColegio($pdo, $grupoId); // multi-colegio
$g = $pdo->prepare(
    "SELECT g.nombre, gr.nombre AS grado FROM grupos g
     INNER JOIN grados gr ON gr.id = g.grado_id
     WHERE g.id = :id"
);
$g->execute(['id' => $grupoId]);
$grupo = $g->fetch();
if (!$grupo) { http_response_code(404); exit('Grupo no encontrado.'); }
$etiquetaGrupo = $grupo['grado'] . ' ' . $grupo['nombre'];

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$c = $pdo->prepare(
    "SELECT u.id, u.nombre, u.correo, u.ultimo_acceso FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE r.nombre = 'estudiante' AND u.grupo_id = :gid
     ORDER BY u.nombre"
);
$c->execute(['gid' => $grupoId]);
$estudiantes = $c->fetchAll();

// Una sola consulta agregada para todo el grupo en vez de una por estudiante
// (mismo criterio que se aplicó en orientador/grupo.php): promedio emocional y
// conteo de alertas pendientes del período, agrupados por usuario_id.
$promediosPorUsuario = [];
$alertasPorUsuario = [];
$suenoPorUsuario = [];
if (!empty($estudiantes)) {
    $ids = array_column($estudiantes, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $pe = $pdo->prepare(
        "SELECT usuario_id, AVG(nivel) AS prom, COUNT(*) AS total
         FROM estado_emocional
         WHERE usuario_id IN ({$marcadores}) AND DATE(fecha_hora) BETWEEN ? AND ?
         GROUP BY usuario_id"
    );
    $pe->execute([...$ids, $desde, $hasta]);
    foreach ($pe->fetchAll() as $fila) {
        $promediosPorUsuario[(int) $fila['usuario_id']] = $fila;
    }

    $ps = $pdo->prepare(
        "SELECT usuario_id, AVG(horas) AS prom
         FROM sueno
         WHERE usuario_id IN ({$marcadores}) AND fecha BETWEEN ? AND ?
         GROUP BY usuario_id"
    );
    $ps->execute([...$ids, $desde, $hasta]);
    foreach ($ps->fetchAll() as $fila) {
        $suenoPorUsuario[(int) $fila['usuario_id']] = $fila['prom'];
    }

    $pa = $pdo->prepare(
        "SELECT usuario_id, COUNT(*) AS pendientes
         FROM alertas_tempranas
         WHERE usuario_id IN ({$marcadores}) AND atendida = 0
         GROUP BY usuario_id"
    );
    $pa->execute($ids);
    foreach ($pa->fetchAll() as $fila) {
        $alertasPorUsuario[(int) $fila['usuario_id']] = (int) $fila['pendientes'];
    }
}

$pdf = DocumentoPdf::crear($pdo, 'Reporte de grupo', 'Reporte de grupo — ' . $etiquetaGrupo, DocumentoPdf::pideQr());
$pdf->titulo('Reporte de grupo: ' . $etiquetaGrupo);
$pdf->textoSuave(
    'Período: ' . $desde . ' a ' . $hasta . '  ·  ' . count($estudiantes) . ' estudiantes'
    . '  ·  Generado el ' . date('d/m/Y H:i') . ' por ' . $_SESSION['nombre'] . '. Documento confidencial — uso interno.'
);
$pdf->espaciador(6);
$pdf->lineaSeparadora();

if (empty($estudiantes)) {
    $pdf->texto('Este grupo no tiene estudiantes asignados.');
} else {
    $pdf->tabla(
        ['Estudiante', 'Ánimo prom.', 'Sueño prom.', 'Alertas pend.', 'Último acceso'],
        array_map(function ($e) use ($promediosPorUsuario, $suenoPorUsuario, $alertasPorUsuario) {
            $prom = $promediosPorUsuario[$e['id']]['prom'] ?? null;
            $sueno = $suenoPorUsuario[$e['id']] ?? null;
            $pendientes = $alertasPorUsuario[$e['id']] ?? 0;
            return [
                $e['nombre'],
                $prom !== null ? round((float) $prom, 1) . '/5' : 'sin datos',
                $sueno !== null ? round((float) $sueno, 1) . 'h' : 'sin datos',
                (string) $pendientes,
                $e['ultimo_acceso'] ? date('d/m/Y', strtotime($e['ultimo_acceso'])) : 'nunca',
            ];
        }, $estudiantes)
    );
}

$pdf->espaciador(14);
$pdf->textoSuave('Para el detalle completo de un estudiante (registros, notas de sesión, alertas), exporta su caso individual desde su ficha.');

$pdf->salidaDescarga('Serena-Grupo-' . preg_replace('/\s+/', '-', $etiquetaGrupo) . '-' . date('Y-m-d'));
