<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 14: planeación académica. */
final class GenPlaneacion extends GenBase
{
    public static function planPeriodo(PDO $pdo, array $ctx, array $f): array
    {
        $sql = 'SELECT p.*, s.nombre AS asignatura, g.nombre AS grupo, gr.nombre AS grado, d.nombre AS docente, pe.nombre AS periodo
                  FROM planeaciones p INNER JOIN asignaciones_academicas a ON a.id = p.asignacion_id INNER JOIN asignaturas s ON s.id = a.asignatura_id
                  INNER JOIN grupos g ON g.id = a.grupo_id INNER JOIN grados gr ON gr.id = g.grado_id INNER JOIN usuarios d ON d.id = p.docente_id LEFT JOIN periodos pe ON pe.id = p.periodo_id
                 WHERE p.colegio_id = ? AND a.anio_lectivo_id = ?';
        $pr = [$ctx['colegioId'], $f['anio']];
        if ($ctx['rol'] === 'docente') {
            $sql .= ' AND p.docente_id = ?';
            $pr[] = $ctx['usuarioId'];
        } elseif ($f['docente'] > 0) {
            $sql .= ' AND p.docente_id = ?';
            $pr[] = $f['docente'];
        }
        if ($f['periodo'] > 0) {
            $sql .= ' AND p.periodo_id = ?';
            $pr[] = $f['periodo'];
        }
        if ($f['grupo'] > 0) {
            $sql .= ' AND a.grupo_id = ?';
            $pr[] = $f['grupo'];
        }
        if ($f['estado'] !== '') {
            $sql .= ' AND p.estado = ?';
            $pr[] = $f['estado'];
        }
        $q = $pdo->prepare($sql . ' ORDER BY gr.nombre, g.nombre, s.nombre, p.fecha_inicio LIMIT 800');
        $q->execute($pr);
        $filas = [];
        foreach ($q->fetchAll() as $r) {
            $filas[] = [$r['asignatura'] . ' · ' . $r['grado'] . ' ' . $r['grupo'] . ' (' . $r['docente'] . ')', (string) ($r['periodo'] ?? '') . ' ' . Informes::fecha((string) $r['fecha_inicio']) . ($r['fecha_fin'] ? ' a ' . Informes::fecha((string) $r['fecha_fin']) : ''),
                (string) $r['tema'] . ((string) ($r['estandar'] ?? '') !== '' ? ' — ' . Informes::corto((string) $r['estandar'], 160) : ''), Informes::corto((string) ($r['objetivos'] ?? ''), 260), Informes::corto((string) ($r['actividades'] ?? ''), 300),
                Informes::corto((string) ($r['recursos'] ?? ''), 120), Informes::corto((string) ($r['evaluacion'] ?? ''), 160), ucfirst((string) $r['estado'])];
        }
        return ['bloques' => [self::tabla(['Asignatura, grupo y docente', 'Período y fechas', 'Tema y estándar', 'Objetivos', 'Actividades', 'Recursos', 'Evaluación', 'Estado'], $filas, 'llllllll', null, null, [14, 10, 17, 18, 20, 8, 10, 6])]];
    }
}
