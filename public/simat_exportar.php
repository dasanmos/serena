<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Simat.php';

// Descargas CSV del SIMAT: matrícula, novedades pendientes y diferencias de la última conciliación.
requiereRol('secretaria_academica', 'administrador', 'rector');
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$anio = Academico::anio($pdo, (int) ($_GET['anio'] ?? 0), $colegioId);
if ($anio === null) {
    http_response_code(404);
    exit('Año lectivo no encontrado.');
}
$anioId = (int) $anio['id'];
$tipo = (string) ($_GET['tipo'] ?? 'matricula');
$ajustes = Simat::ajustes($pdo, $colegioId);
$col = Simat::colegio($pdo, $colegioId);
$base = 'serena-simat-' . preg_replace('/\D+/', '', (string) $col['dane']) . '-' . (int) $anio['anio'];

header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: no-store');
$salida = fopen('php://output', 'w');

if ($tipo === 'matricula') {
    $soloValidos = ($_GET['solo_validos'] ?? '') === '1';
    $est = Simat::estudiantes($pdo, $colegioId, $anioId);
    header('Content-Disposition: attachment; filename="' . $base . '-matricula' . ($soloValidos ? '-validos' : '') . '-' . date('Ymd') . '.csv"');
    [$n, $omitidas] = Simat::escribirCsv($salida, $est, $ajustes, $soloValidos);
    $r = Simat::resumen($est);
    Simat::registrarReporte($pdo, $colegioId, $anioId, 'matricula', $n, $r['con_errores'], $r['con_avisos'], $soloValidos ? "Solo válidos; omitidos: {$omitidas}" : 'Todos', $usuarioId);
    auditarAccion($pdo, 'simat_exportar_matricula', 'colegios', $colegioId, [], ['filas' => $n, 'omitidas' => $omitidas, 'anio' => (int) $anio['anio']]);
} elseif ($tipo === 'novedades') {
    header('Content-Disposition: attachment; filename="' . $base . '-novedades-' . date('Ymd') . '.csv"');
    $sep = $ajustes['separador'];
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, ['TIPO_DOCUMENTO', 'NUMERO_DOCUMENTO', 'ESTUDIANTE', 'NOVEDAD', 'FECHA', 'CAUSA', 'DESTINO', 'OBSERVACION'], $sep, '"', '\\');
    $n = 0;
    foreach (Simat::novedades($pdo, $colegioId, $anioId, 'pendiente') as $f) {
        $fechaNov = strtotime((string) $f['fecha']);
        fputcsv($salida, array_map([Simat::class, 'celdaSegura'], [(string) $f['tipo_documento'], (string) $f['documento'], (string) $f['estudiante'], Simat::NOVEDADES[$f['tipo']] ?? (string) $f['tipo'],
            $fechaNov ? date($ajustes['formato_fecha'], $fechaNov) : (string) $f['fecha'], (string) ($f['causa'] ?? ''), (string) ($f['destino'] ?? ''), (string) ($f['observacion'] ?? '')]), $sep, '"', '\\');
        $n++;
    }
    Simat::registrarReporte($pdo, $colegioId, $anioId, 'novedades', $n, 0, 0, 'Pendientes', $usuarioId);
    auditarAccion($pdo, 'simat_exportar_novedades', 'colegios', $colegioId, [], ['filas' => $n]);
} elseif ($tipo === 'conciliacion') {
    $c = $_SESSION['simat_conciliacion'] ?? null;
    if (!is_array($c) || (int) ($c['anio'] ?? 0) !== $anioId) {
        header_remove('Content-Type');
        header('Content-Type: text/plain; charset=utf-8');
        exit('No hay una conciliación reciente. Vuelve a subir el archivo del SIMAT.');
    }
    header('Content-Disposition: attachment; filename="' . $base . '-diferencias-' . date('Ymd') . '.csv"');
    Simat::escribirCsvConciliacion($salida, $c, $ajustes['separador']);
} else {
    http_response_code(400);
    exit('Tipo de descarga no válido.');
}
fclose($salida);
