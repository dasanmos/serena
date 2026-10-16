<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 6: refuerzos y planes de mejoramiento (datos capturados en public/refuerzos.php). */
final class GenRefuerzos extends GenBase
{
    private static function ids(array $roster): string
    {
        return Informes::in(array_column($roster, 'id'));
    }

    // ---------------------------------------------------------- 40. Refuerzos pendientes
    public static function pendientes(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $mapa = [];
        foreach ($roster as $r) {
            $mapa[(int) $r['id']] = $r;
        }
        $sql = "SELECT r.*, s.nombre AS asignatura, p.nombre AS periodo FROM refuerzos r
                  INNER JOIN asignaciones_academicas a ON a.id = r.asignacion_id INNER JOIN asignaturas s ON s.id = a.asignatura_id INNER JOIN periodos p ON p.id = r.periodo_id
                 WHERE r.colegio_id = ? AND r.anio_lectivo_id = ? AND r.estado = 'pendiente' AND r.estudiante_id IN (" . self::ids($roster) . ')';
        $p = [$ctx['colegioId'], $f['anio']];
        if ($f['periodo'] > 0) {
            $sql .= ' AND r.periodo_id = ?';
            $p[] = $f['periodo'];
        }
        $q = $pdo->prepare($sql . ' ORDER BY r.fecha_limite IS NULL, r.fecha_limite, r.id');
        $q->execute($p);
        $hoy = date('Y-m-d');
        $filas = [];
        foreach ($q->fetchAll() as $i => $r) {
            $e = $mapa[(int) $r['estudiante_id']];
            $venc = $r['fecha_limite'] && (string) $r['fecha_limite'] < $hoy;
            $filas[] = [(string) ($i + 1), (string) $e['nombre'], self::grupoTxt($e), (string) $r['asignatura'], (string) $r['periodo'], Informes::nota($ctx, $r['nota_original'] !== null ? (float) $r['nota_original'] : null),
                Informes::fecha((string) $r['fecha_limite']) . ($venc ? ' (vencido)' : '')];
        }
        return ['bloques' => [self::tabla(['N.º', 'Estudiante', 'Grupo', 'Asignatura', 'Período', 'Nota original', 'Fecha límite'], $filas, 'cllllcc'),
            self::nota('Registra o genera los refuerzos en el módulo «Refuerzos y planes» del menú.')]];
    }

    // ---------------------------------------------------------- 41. Planilla de refuerzos
    public static function planilla(PDO $pdo, array $ctx, array $f): array
    {
        $a = null;
        foreach (Informes::asignaciones($pdo, $ctx, $f['anio']) as $x) {
            if ((int) $x['id'] === $f['asignacion']) {
                $a = $x;
            }
        }
        if ($a === null) {
            throw new InformeError('No se encontró la asignatura.');
        }
        $q = $pdo->prepare('SELECT r.*, u.nombre FROM refuerzos r INNER JOIN usuarios u ON u.id = r.estudiante_id WHERE r.asignacion_id = ? AND r.periodo_id = ? AND r.colegio_id = ? ORDER BY u.nombre');
        $q->execute([(int) $a['id'], $f['periodo'], $ctx['colegioId']]);
        $filas = [];
        foreach ($q->fetchAll() as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], Informes::nota($ctx, $r['nota_original'] !== null ? (float) $r['nota_original'] : null), Informes::fecha((string) $r['fecha_limite']),
                Informes::nota($ctx, $r['nota_refuerzo'] !== null ? (float) $r['nota_refuerzo'] : null), ucfirst((string) $r['estado']), ''];
        }
        return ['bloques' => [
            self::pares([['Asignatura', (string) $a['asignatura']], ['Grupo', $a['grado'] . ' ' . $a['grupo']], ['Docente', (string) ($a['docente'] ?: 'Sin asignar')], ['Período', (string) $f['txt']['periodo']]]),
            self::tabla(['N.º', 'Estudiante', 'Nota original', 'Fecha límite', 'Nota refuerzo', 'Estado', 'Firma del estudiante'], $filas, 'clccccl', null, null, [4, 26, 9, 10, 9, 10, 24]),
            self::nota($filas ? 'Planilla de refuerzos.' : 'No hay refuerzos registrados para esta asignatura y período: genéralos en «Refuerzos y planes».'),
            self::firmas([['', 'Docente'], ['', 'Coordinación académica']]),
        ]];
    }

    // ---------------------------------------------------------- 42. Planes de mejoramiento
    public static function planes(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $mapa = [];
        foreach ($roster as $r) {
            $mapa[(int) $r['id']] = $r;
        }
        $sql = "SELECT pm.*, s.nombre AS asignatura, p.nombre AS periodo FROM planes_mejoramiento pm
                  LEFT JOIN asignaciones_academicas a ON a.id = pm.asignacion_id LEFT JOIN asignaturas s ON s.id = a.asignatura_id LEFT JOIN periodos p ON p.id = pm.periodo_id
                 WHERE pm.colegio_id = ? AND pm.anio_lectivo_id = ? AND pm.estudiante_id IN (" . self::ids($roster) . ')';
        $p = [$ctx['colegioId'], $f['anio']];
        if ($f['periodo'] > 0) {
            $sql .= ' AND pm.periodo_id = ?';
            $p[] = $f['periodo'];
        }
        if ($f['estado'] !== '') {
            $sql .= ' AND pm.estado = ?';
            $p[] = $f['estado'];
        }
        $q = $pdo->prepare($sql . ' ORDER BY pm.fecha_inicio DESC, pm.id DESC');
        $q->execute($p);
        $filas = [];
        foreach ($q->fetchAll() as $i => $r) {
            $e = $mapa[(int) $r['estudiante_id']];
            $filas[] = [(string) ($i + 1), (string) $e['nombre'], self::grupoTxt($e), (string) ($r['asignatura'] ?: 'General'), Informes::fecha((string) $r['fecha_inicio']), Informes::fecha((string) $r['fecha_fin']),
                Informes::nota($ctx, $r['nota_inicial'] !== null ? (float) $r['nota_inicial'] : null), Informes::nota($ctx, $r['nota_final'] !== null ? (float) $r['nota_final'] : null), ucfirst((string) $r['estado']), Informes::corto((string) $r['compromisos'], 220)];
        }
        return ['bloques' => [self::tabla(['N.º', 'Estudiante', 'Grupo', 'Asignatura', 'Inicio', 'Fin', 'Nota inicial', 'Nota final', 'Estado', 'Compromisos'], $filas, 'clllcccccl', null, null, [3, 16, 7, 11, 7, 7, 6, 6, 7, 30])]];
    }

    // ---------------------------------------------------------- 43. Eficiencia
    public static function eficiencia(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $mapa = [];
        foreach ($roster as $r) {
            $mapa[(int) $r['id']] = $r;
        }
        $in = self::ids($roster);
        $p = [$ctx['colegioId'], $f['anio']];
        $per = '';
        if ($f['periodo'] > 0) {
            $per = ' AND periodo_id = ?';
            $p[] = $f['periodo'];
        }
        $q = $pdo->prepare("SELECT estudiante_id, estado, nota_inicial, nota_final FROM planes_mejoramiento WHERE colegio_id = ? AND anio_lectivo_id = ?{$per} AND estudiante_id IN ({$in})");
        $q->execute($p);
        $g = [];
        $tot = ['n' => 0, 'ok' => 0, 'no' => 0, 'act' => 0, 'mej' => [], 'x' => 0];
        foreach ($q->fetchAll() as $r) {
            $e = $mapa[(int) $r['estudiante_id']];
            $k = (int) $e['grupo_id'];
            $g[$k] ??= ['nombre' => self::grupoTxt($e), 'n' => 0, 'ok' => 0, 'no' => 0, 'act' => 0, 'mej' => []];
            $g[$k]['n']++;
            $tot['n']++;
            $campo = ['cumplido' => 'ok', 'incumplido' => 'no', 'activo' => 'act'][(string) $r['estado']] ?? 'act';
            $g[$k][$campo]++;
            $tot[$campo]++;
            if ($r['nota_inicial'] !== null && $r['nota_final'] !== null) {
                $g[$k]['mej'][] = (float) $r['nota_final'] - (float) $r['nota_inicial'];
                $tot['mej'][] = (float) $r['nota_final'] - (float) $r['nota_inicial'];
            }
        }
        uasort($g, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));
        $fmt = static fn (array $m): string => $m ? (($v = array_sum($m) / count($m)) > 0 ? '+' : '') . number_format($v, (int) ($ctx['escala']['decimales'] ?? 1), '.', '') : '—';
        $filas = [];
        foreach ($g as $x) {
            $filas[] = [$x['nombre'], (string) $x['n'], (string) $x['ok'], (string) $x['no'], (string) $x['act'], Informes::pct((float) $x['ok'], (float) ($x['ok'] + $x['no'])), $fmt($x['mej'])];
        }
        $bl = [self::tabla(['Grupo', 'Planes', 'Cumplidos', 'Incumplidos', 'Activos', 'Eficiencia', 'Mejora promedio de nota'], $filas, 'lcccccc', 'Planes de mejoramiento',
            ['Total', (string) $tot['n'], (string) $tot['ok'], (string) $tot['no'], (string) $tot['act'], Informes::pct((float) $tot['ok'], (float) ($tot['ok'] + $tot['no'])), $fmt($tot['mej'])])];
        if (Informes::tablaExiste($pdo, 'refuerzos')) {
            $p = [$ctx['colegioId'], $f['anio']];
            $per = '';
            if ($f['periodo'] > 0) {
                $per = ' AND periodo_id = ?';
                $p[] = $f['periodo'];
            }
            $q = $pdo->prepare("SELECT estado, COUNT(*) AS n FROM refuerzos WHERE colegio_id = ? AND anio_lectivo_id = ?{$per} AND estudiante_id IN ({$in}) GROUP BY estado");
            $q->execute($p);
            $c = ['pendiente' => 0, 'presentado' => 0, 'aprobado' => 0, 'reprobado' => 0];
            foreach ($q->fetchAll() as $r) {
                $c[(string) $r['estado']] = (int) $r['n'];
            }
            $resueltos = $c['aprobado'] + $c['reprobado'];
            $bl[] = self::pares([['Refuerzos pendientes', (string) $c['pendiente']], ['Presentados sin calificar', (string) $c['presentado']], ['Aprobados', (string) $c['aprobado']], ['Reprobados', (string) $c['reprobado']],
                ['Eficiencia de refuerzos', Informes::pct((float) $c['aprobado'], (float) $resueltos)]], 'Refuerzos');
        }
        $bl[] = self::nota('Eficiencia = planes (o refuerzos) cumplidos / planes (o refuerzos) ya resueltos. Los activos o pendientes no cuentan todavía.');
        return ['bloques' => $bl];
    }
}
