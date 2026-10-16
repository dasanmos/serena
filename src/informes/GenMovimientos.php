<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 13: deserción y movimientos estudiantiles. */
final class GenMovimientos extends GenBase
{
    private static function retirados(PDO $pdo, array $ctx, array $f): array
    {
        $p = [$ctx['colegioId'], $f['anio']];
        $w = '';
        if ($f['grado'] > 0) {
            $w .= ' AND m.grado_id = ?';
            $p[] = $f['grado'];
        }
        if ($f['desde'] !== '') {
            $w .= ' AND DATE(COALESCE(m.resuelto_en, m.creado_en)) >= ?';
            $p[] = $f['desde'];
        }
        if ($f['hasta'] !== '') {
            $w .= ' AND DATE(COALESCE(m.resuelto_en, m.creado_en)) <= ?';
            $p[] = $f['hasta'];
        }
        $q = $pdo->prepare("SELECT u.id, u.nombre, gr.nombre AS grado, g.nombre AS grupo, COALESCE(m.resuelto_en, m.creado_en) AS fecha, m.observaciones
                              FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id INNER JOIN grados gr ON gr.id = m.grado_id LEFT JOIN grupos g ON g.id = m.grupo_id
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'retirada'{$w} ORDER BY fecha DESC, u.nombre");
        $q->execute($p);
        return $q->fetchAll();
    }

    public static function desercion(PDO $pdo, array $ctx, array $f): array
    {
        $col = $ctx['colegioId'];
        $ret = self::retirados($pdo, $ctx, $f);
        $filas = [];
        foreach ($ret as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], (string) $r['grado'], (string) ($r['grupo'] ?? ''), Informes::fecha((string) $r['fecha']), (string) ($r['observaciones'] ?? '')];
        }
        $q = $pdo->prepare("SELECT COUNT(*) FROM matriculas WHERE colegio_id = ? AND anio_lectivo_id = ? AND estado IN ('activa','retirada')");
        $q->execute([$col, $f['anio']]);
        $total = (int) $q->fetchColumn();
        $bl = [self::tabla(['N.º', 'Estudiante', 'Grado', 'Grupo', 'Fecha de retiro', 'Motivo / observación'], $filas, 'cllccl', 'Retirados durante el año ' . $f['anio_row']['anio'])];

        $prev = $pdo->prepare('SELECT id, anio FROM anios_lectivos WHERE colegio_id = ? AND anio < ? ORDER BY anio DESC LIMIT 1');
        $prev->execute([$col, $f['anio_row']['anio']]);
        $ant = $prev->fetch();
        $nocont = 0;
        $previos = 0;
        if ($ant) {
            $p = [$col, (int) $ant['id'], $col, $f['anio']];
            $w = '';
            if ($f['grado'] > 0) {
                $w = ' AND m.grado_id = ?';
                $p[] = $f['grado'];
            }
            $q = $pdo->prepare("SELECT u.nombre, gr.nombre AS grado, g.nombre AS grupo FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id INNER JOIN grados gr ON gr.id = m.grado_id LEFT JOIN grupos g ON g.id = m.grupo_id
                                 WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'activa'
                                   AND NOT EXISTS (SELECT 1 FROM matriculas n WHERE n.colegio_id = ? AND n.estudiante_id = m.estudiante_id AND n.anio_lectivo_id = ? AND n.estado IN ('activa','pendiente','retirada')){$w}
                              ORDER BY gr.nombre, u.nombre");
            $q->execute($p);
            $fc = [];
            foreach ($q->fetchAll() as $i => $r) {
                $fc[] = [(string) ($i + 1), (string) $r['nombre'], (string) $r['grado'], (string) ($r['grupo'] ?? '')];
            }
            $nocont = count($fc);
            $q2 = $pdo->prepare("SELECT COUNT(*) FROM matriculas WHERE colegio_id = ? AND anio_lectivo_id = ? AND estado = 'activa'");
            $q2->execute([$col, (int) $ant['id']]);
            $previos = (int) $q2->fetchColumn();
            $bl[] = self::tabla(['N.º', 'Estudiante', 'Grado ' . $ant['anio'], 'Grupo ' . $ant['anio']], $fc, 'cllc', 'Estudiantes de ' . $ant['anio'] . ' que no aparecen matriculados en ' . $f['anio_row']['anio']);
        }
        $bl[] = self::pares([['Matrículas del año (activas + retiradas)', (string) $total], ['Retirados en el año', (string) count($ret)], ['Tasa de deserción intraanual', Informes::pct((float) count($ret), (float) $total, 2)],
            ['No continúan respecto al año anterior', $ant ? (string) $nocont : 'Sin año anterior'], ['Tasa de no continuidad', $ant ? Informes::pct((float) $nocont, (float) $previos, 2) : '—']], 'Indicadores');
        $bl[] = self::nota('«No continúan» incluye a quienes terminaron el último grado (graduados): descártalos antes de reportar deserción.');
        return ['bloques' => $bl];
    }

    public static function salidas(PDO $pdo, array $ctx, array $f): array
    {
        $ret = self::retirados($pdo, $ctx, $f);
        $tr = [];
        $q = $pdo->prepare("SELECT estudiante_id, codigo FROM certificados WHERE colegio_id = ? AND tipo = 'traslado' AND estado = 'vigente' AND anio_lectivo_id = ?");
        $q->execute([$ctx['colegioId'], $f['anio']]);
        foreach ($q->fetchAll() as $r) {
            $tr[(int) $r['estudiante_id']] = Certificados::formatearCodigo((string) $r['codigo']);
        }
        $docs = self::documentos($pdo, array_column($ret, 'id'));
        $filas = [];
        foreach ($ret as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], $docs[(int) $r['id']] ?? '—', (string) $r['grado'], (string) ($r['grupo'] ?? ''), Informes::fecha((string) $r['fecha']), (string) ($r['observaciones'] ?? ''), $tr[(int) $r['id']] ?? 'No emitido'];
        }
        return ['bloques' => [self::tabla(['N.º', 'Estudiante', 'Documento', 'Grado', 'Grupo', 'Fecha de salida', 'Motivo', 'Cert. de traslado'], $filas, 'cllccclc'), self::nota(count($filas) . ' salidas.')]];
    }
}
