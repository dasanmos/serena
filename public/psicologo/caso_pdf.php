<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/DocumentoPdf.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/Sueno.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/PlanSeguimiento.php';
require_once __DIR__ . '/../../src/modelos/AccesoFicha.php';
requiereRol('psicologo', 'orientador');

$id = (int) ($_GET['id'] ?? 0);
exigirUsuarioDeMiColegio($pdo, $id); // multi-colegio
$estudiante = Usuario::buscarPorId($pdo, $id);
if (!$estudiante || $estudiante['rol'] !== 'estudiante') { http_response_code(404); exit('Estudiante no encontrado.'); }

// Mismo control de acceso que estudiante_detalle.php: el orientador solo puede
// exportar el caso de estudiantes de sus propios grupos.
if ($_SESSION['rol'] === 'orientador') {
    $gruposOrientador = $_SESSION['grupos_ids'] ?? [];
    if (empty($gruposOrientador) || !in_array((int) $estudiante['grupo_id'], $gruposOrientador, true)) {
        http_response_code(404); exit('Estudiante no encontrado.');
    }
}

$autorId = (int) $_SESSION['usuario_id'];
AccesoFicha::registrar($pdo, $autorId, $id);

$emociones = EstadoEmocional::listarDeUsuario($pdo, $id, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$sueno = Sueno::listarDeUsuario($pdo, $id, 14);

$alertas = $pdo->prepare('SELECT * FROM alertas_tempranas WHERE usuario_id = :id ORDER BY fecha_generada DESC LIMIT 15');
$alertas->execute(['id' => $id]);
$alertas = $alertas->fetchAll();

// Solo las notas del propio autor — exportar el caso no debe filtrar las notas
// privadas de otro profesional.
$notas = $pdo->prepare('SELECT * FROM notas_sesion WHERE estudiante_id = :est AND autor_id = :autor ORDER BY fecha_creacion DESC');
$notas->execute(['est' => $id, 'autor' => $autorId]);
$notas = $notas->fetchAll();

// Igual que las notas: solo el plan de seguimiento del propio autor.
$plan = PlanSeguimiento::listarDeEstudiante($pdo, $autorId, $id);

$pdf = DocumentoPdf::crear($pdo, 'Caso (confidencial)', 'Caso — ' . $estudiante['nombre'], DocumentoPdf::pideQr());
$pdf->titulo('Caso: ' . $estudiante['nombre']);
$pdf->textoSuave('Generado el ' . date('d/m/Y H:i') . ' por ' . $_SESSION['nombre'] . '. Documento confidencial — uso interno.');
$pdf->espaciador(6);
$pdf->lineaSeparadora();

$pdf->subtitulo('Registros emocionales (últimos 30 días)');
if (empty($emociones)) {
    $pdf->texto('Sin registros.');
} else {
    $pdf->tabla(['Fecha', 'Emoción', 'Nivel'], array_map(
        fn($e) => [date('d/m/Y H:i', strtotime($e['fecha_hora'])), ucfirst($e['emocion']), (string) $e['nivel']],
        array_slice($emociones, 0, 20)
    ));
}
$pdf->espaciador(10);

$pdf->subtitulo('Sueño (últimas 2 semanas)');
if (empty($sueno)) {
    $pdf->texto('Sin registros.');
} else {
    $pdf->tabla(['Fecha', 'Horas', 'Calidad'], array_map(
        fn($s) => [date('d/m/Y', strtotime($s['fecha'])), $s['horas'] . 'h', ucfirst($s['calidad'])],
        $sueno
    ));
}
$pdf->espaciador(10);

$pdf->subtitulo('Alertas tempranas');
if (empty($alertas)) {
    $pdf->texto('Sin alertas registradas.');
} else {
    foreach ($alertas as $a) {
        $estado = $a['atendida'] ? 'Atendida' : 'Pendiente';
        $pdf->texto(date('d/m/Y', strtotime($a['fecha_generada'])) . ' — ' . ucfirst($a['nivel_urgencia']) . ' — ' . $a['descripcion'] . ' (' . $estado . ')');
    }
}
$pdf->espaciador(10);

$pdf->subtitulo('Mis notas de sesión');
if (empty($notas)) {
    $pdf->texto('Sin notas registradas.');
} else {
    foreach ($notas as $n) {
        $pdf->texto(date('d/m/Y H:i', strtotime($n['fecha_creacion'])) . ' — ' . $n['contenido']);
        $pdf->espaciador(4);
    }
}
$pdf->espaciador(10);

$pdf->subtitulo('Mi plan de seguimiento');
if (empty($plan)) {
    $pdf->texto('Sin tareas de seguimiento registradas.');
} else {
    $etiquetasEstado = ['pendiente' => 'Pendiente', 'en_curso' => 'En curso', 'completado' => 'Completada'];
    foreach ($plan as $t) {
        $linea = $t['titulo'] . ' — ' . $etiquetasEstado[$t['estado']];
        if ($t['fecha_limite']) {
            $linea .= ' (fecha límite: ' . date('d/m/Y', strtotime($t['fecha_limite'])) . ')';
        }
        $pdf->texto($linea);
        if ($t['descripcion']) {
            $pdf->textoSuave($t['descripcion']);
        }
        $pdf->espaciador(4);
    }
}

$pdf->salidaDescarga('Caso-' . preg_replace('/\s+/', '-', $estudiante['nombre']) . '-' . date('Y-m-d'));
