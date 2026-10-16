<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/Encuesta.php';
requiereRol('psicologo');

$encuestaId = (int) ($_GET['id'] ?? 0);
$encuesta = Encuesta::buscarPorId($pdo, $encuestaId);
if (!$encuesta) {
    http_response_code(404);
    exit('Encuesta no encontrada.');
}
$preguntas = Encuesta::preguntas($pdo, $encuestaId);

// Un CSV real (no .xlsx) — Excel, Google Sheets y LibreOffice lo abren nativamente
// sin necesitar ninguna librería adicional en el servidor. Mismo patrón que
// public/psicologo/reportes_csv.php.
$nombreArchivo = preg_replace('/[^A-Za-z0-9]+/', '-', $encuesta['titulo']) ?? 'encuesta';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-encuesta-' . trim((string) $nombreArchivo, '-') . '.csv"');

$salida = fopen('php://output', 'w');
fputs($salida, "\xEF\xBB\xBF"); // BOM UTF-8, para que Excel no muestre acentos rotos
fputcsv($salida, ['Serena — Resultados de encuesta']);
fputcsv($salida, ['Encuesta', $encuesta['titulo']]);
fputcsv($salida, ['Generado', date('d/m/Y H:i')]);
fputcsv($salida, []);

foreach ($preguntas as $p) {
    fputcsv($salida, ['Pregunta', $p['texto']]);

    if ($p['tipo'] === 'opcion_multiple') {
        $c = $pdo->prepare(
            'SELECT o.texto, COUNT(r.id) AS votos FROM opciones_pregunta o
             LEFT JOIN respuestas_encuesta r ON r.opcion_id = o.id
             WHERE o.pregunta_id = :pid GROUP BY o.id'
        );
        $c->execute(['pid' => $p['id']]);
        $opciones = $c->fetchAll();
        $total = array_sum(array_column($opciones, 'votos'));

        fputcsv($salida, ['Opción', 'Votos', 'Porcentaje']);
        foreach ($opciones as $o) {
            $pct = $total ? round($o['votos'] / $total * 100) : 0;
            fputcsv($salida, [$o['texto'], $o['votos'], $pct . '%']);
        }
    } elseif ($p['tipo'] === 'escala') {
        $c = $pdo->prepare(
            "SELECT respuesta_texto FROM respuestas_encuesta
             WHERE pregunta_id = :pid AND respuesta_texto IS NOT NULL AND respuesta_texto != ''"
        );
        $c->execute(['pid' => $p['id']]);
        $valores = array_map('intval', $c->fetchAll(PDO::FETCH_COLUMN));

        fputcsv($salida, ['Respuesta (1 a 10)']);
        foreach ($valores as $v) {
            fputcsv($salida, [$v]);
        }
        if (!empty($valores)) {
            fputcsv($salida, ['Promedio', round(array_sum($valores) / count($valores), 1)]);
        }
    } else { // texto_libre
        $c = $pdo->prepare(
            "SELECT respuesta_texto FROM respuestas_encuesta
             WHERE pregunta_id = :pid AND respuesta_texto IS NOT NULL AND respuesta_texto != ''"
        );
        $c->execute(['pid' => $p['id']]);
        $textos = $c->fetchAll(PDO::FETCH_COLUMN);

        // Anónimo a propósito: sin nombre de estudiante, igual que ya hace la
        // pantalla de resultados — una encuesta de bienestar no debe poder
        // rastrearse a una persona por su respuesta en un archivo exportado.
        fputcsv($salida, ['Respuesta (texto libre, anónima)']);
        foreach ($textos as $t) {
            fputcsv($salida, [$t]);
        }
    }
    fputcsv($salida, []);
}
fclose($salida);
