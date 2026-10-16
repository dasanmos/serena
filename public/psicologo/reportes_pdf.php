<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/DocumentoPdf.php';
require_once __DIR__ . '/../../src/helpers/ia.php';
require_once __DIR__ . '/../../src/helpers/texto_ia_cache.php';
require_once __DIR__ . '/../../src/modelos/Grado.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
requiereRol('psicologo', 'coordinador');

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$gradoId = (int) ($_GET['grado_id'] ?? 0);
$grupoId = (int) ($_GET['grupo_id'] ?? 0);

$condicionGrupo = '';
$paramsGrupo = [];
// Multi-colegio: todos los reportes quedan limitados al colegio del usuario (u = usuarios del estudiante).
$condicionColegio = sqlColegio('u.colegio_id');
$etiquetaFiltro = 'Toda la institución';
if ($grupoId) {
    $condicionGrupo = ' AND u.grupo_id = :grupo_id';
    $paramsGrupo['grupo_id'] = $grupoId;
    $g = $pdo->prepare("SELECT g.nombre, gr.nombre AS grado FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE g.id=:id" . sqlColegio('gr.colegio_id'));
    $g->execute(['id' => $grupoId]);
    $fila = $g->fetch();
    if ($fila) $etiquetaFiltro = $fila['grado'] . ' ' . $fila['nombre'];
} elseif ($gradoId) {
    $condicionGrupo = ' AND g.grado_id = :grado_id';
    $paramsGrupo['grado_id'] = $gradoId;
    $etiquetaFiltro = (string) $pdo->query("SELECT nombre FROM grados WHERE id = {$gradoId}" . sqlColegio('colegio_id'))->fetchColumn();
}
$necesitaJoinGrupo = $condicionGrupo !== '' ? 'INNER JOIN usuarios u ON u.id = e.usuario_id INNER JOIN grupos g ON g.id = u.grupo_id' : 'INNER JOIN usuarios u ON u.id = e.usuario_id';

$promedioEmociones = $pdo->prepare(
    "SELECT AVG(e.nivel) AS prom, COUNT(*) AS total FROM estado_emocional e {$necesitaJoinGrupo}
     WHERE DATE(e.fecha_hora) BETWEEN :d AND :h {$condicionGrupo}{$condicionColegio}"
);
$promedioEmociones->execute(['d' => $desde, 'h' => $hasta] + $paramsGrupo);
$resEmo = $promedioEmociones->fetch();

$necesitaJoinGrupoSueno = $condicionGrupo !== '' ? 'INNER JOIN usuarios u ON u.id = s.usuario_id INNER JOIN grupos g ON g.id = u.grupo_id' : 'INNER JOIN usuarios u ON u.id = s.usuario_id';
$promedioSueno = $pdo->prepare(
    "SELECT AVG(s.horas) AS prom FROM sueno s {$necesitaJoinGrupoSueno} WHERE s.fecha BETWEEN :d AND :h {$condicionGrupo}{$condicionColegio}"
);
$promedioSueno->execute(['d' => $desde, 'h' => $hasta] + $paramsGrupo);
$resSueno = $promedioSueno->fetch();

$emocionesFrec = $pdo->prepare(
    "SELECT e.emocion, COUNT(*) AS veces FROM estado_emocional e {$necesitaJoinGrupo}
     WHERE DATE(e.fecha_hora) BETWEEN :d AND :h {$condicionGrupo}{$condicionColegio}
     GROUP BY e.emocion ORDER BY veces DESC LIMIT 8"
);
$emocionesFrec->execute(['d' => $desde, 'h' => $hasta] + $paramsGrupo);
$frecc = $emocionesFrec->fetchAll();

$pdf = DocumentoPdf::crear($pdo, 'Reporte institucional', 'Reporte institucional — ' . $etiquetaFiltro, DocumentoPdf::pideQr());
$pdf->titulo('Reporte institucional');
$pdf->textoSuave('Alcance: ' . $etiquetaFiltro . '  ·  Período: ' . $desde . ' a ' . $hasta . '  ·  Generado el ' . date('d/m/Y H:i') . ' por ' . $_SESSION['nombre']);
$pdf->espaciador(6);
$pdf->lineaSeparadora();

// Multi-colegio: el colegio va en la clave; sin él, el informe institucional (grado 0, grupo 0) se compartiría entre colegios.
$claveInforme = "informe_c" . (int) (colegioParaConsulta() ?? 0) . "_{$desde}_{$hasta}_{$gradoId}_{$grupoId}";
$textoInforme = obtenerTextoIACacheado(
    $pdo,
    $claveInforme,
    function () use ($resEmo, $resSueno, $frecc, $desde, $hasta) {
        $total = (int) $resEmo['total'];
        if ($total === 0) {
            return "No se registraron datos emocionales entre el {$desde} y el {$hasta} para el filtro seleccionado.";
        }
        $promedio = round((float) $resEmo['prom'], 1);
        $partes = ["Entre el {$desde} y el {$hasta} se registraron {$total} entradas emocionales, con un nivel promedio de {$promedio}/5."];
        if ($resSueno['prom'] !== null) {
            $partes[] = 'El promedio de sueño reportado en este período fue de ' . round((float) $resSueno['prom'], 1) . ' horas por noche.';
        }
        if (!empty($frecc)) {
            $top3 = array_slice($frecc, 0, 3);
            $nombres = implode(', ', array_map(fn($f) => ucfirst($f['emocion']), $top3));
            $partes[] = "Las emociones más frecuentes en este período fueron: {$nombres}.";
        }
        return implode(' ', $partes);
    },
    'reescribirConIA'
);
$pdf->subtitulo('Informe del período');
$pdf->texto($textoInforme);
$pdf->espaciador(6);

$pdf->subtitulo('Resumen');
$pdf->indicadores([
    ['Registros emocionales', (string) (int) $resEmo['total']],
    ['Ánimo promedio', $resEmo['prom'] ? round((float) $resEmo['prom'], 1) . ' / 5' : 'sin datos'],
    ['Sueño promedio', $resSueno['prom'] ? round((float) $resSueno['prom'], 1) . ' h' : 'sin datos'],
]);
$pdf->espaciador(6);

$pdf->subtitulo('Emociones más frecuentes');
if (empty($frecc)) {
    $pdf->texto('Sin datos para el rango seleccionado.');
} else {
    $pdf->graficoBarras('Emociones más frecuentes', array_map(fn($f) => ucfirst((string) $f['emocion']), $frecc), array_map(fn($f) => (int) $f['veces'], $frecc));
    $pdf->tabla(['Emoción', 'Veces registrada'], array_map(
        fn($f) => [ucfirst($f['emocion']), (string) $f['veces']],
        $frecc
    ));
}

$pdf->espaciador(14);
$pdf->textoSuave('Reporte agregado e institucional: no identifica estudiantes individuales.');

$pdf->salidaDescarga('Serena-Reporte-institucional-' . date('Y-m-d'));
