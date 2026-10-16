<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 5: asistencia e inasistencia. */
final class GenAsistencia extends GenBase
{
    // ---------------------------------------------------------- 36. Control de asistencia
    public static function control(PDO $pdo, array $ctx, array $f): array
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
        $desde = $f['desde'] !== '' ? $f['desde'] : (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $hasta = $f['hasta'] !== '' ? $f['hasta'] : (new DateTimeImmutable('today'))->format('Y-m-d');
        $q = $pdo->prepare('SELECT DISTINCT fecha FROM asistencias WHERE asignacion_id = ? AND fecha BETWEEN ? AND ? ORDER BY fecha LIMIT 31');
        $q->execute([(int) $a['id'], $desde, $hasta]);
        $fechas = $q->fetchAll(PDO::FETCH_COLUMN);
        $reg = [];
        if ($fechas) {
            $q = $pdo->prepare('SELECT estudiante_id, fecha, estado FROM asistencias WHERE asignacion_id = ? AND fecha BETWEEN ? AND ?');
            $q->execute([(int) $a['id'], $desde, $hasta]);
            foreach ($q->fetchAll() as $r) {
                $reg[(int) $r['estudiante_id']][(string) $r['fecha']] = (string) $r['estado'];
            }
        }
        $ests = Evaluacion::estudiantesDeGrupo($pdo, $ctx['colegioId'], $f['anio_row'], (int) $a['grupo_id']);
        $enc = ['N.º', 'Estudiante'];
        foreach ($fechas as $d) {
            $enc[] = substr((string) $d, 8, 2) . '/' . substr((string) $d, 5, 2);
        }
        array_push($enc, 'A', 'T', 'E');
        $simbolo = ['presente' => '·', 'ausente' => 'A', 'tarde' => 'T', 'excusa' => 'E'];
        $filas = [];
        $tot = ['A' => 0, 'T' => 0, 'E' => 0];
        foreach ($ests as $i => $e) {
            $fila = [(string) ($i + 1), (string) $e['nombre']];
            $c = ['A' => 0, 'T' => 0, 'E' => 0];
            foreach ($fechas as $d) {
                $st = $reg[(int) $e['id']][(string) $d] ?? '';
                $fila[] = $simbolo[$st] ?? '';
                if ($st === 'ausente') {
                    $c['A']++;
                } elseif ($st === 'tarde') {
                    $c['T']++;
                } elseif ($st === 'excusa') {
                    $c['E']++;
                }
            }
            foreach ($c as $k => $v) {
                $tot[$k] += $v;
            }
            array_push($fila, (string) $c['A'], (string) $c['T'], (string) $c['E']);
            $filas[] = $fila;
        }
        return ['subtitulo' => $a['asignatura'] . ' · ' . $a['grado'] . ' ' . $a['grupo'] . ' · Del ' . Informes::fecha($desde) . ' al ' . Informes::fecha($hasta), 'bloques' => [
            self::pares([['Asignatura', (string) $a['asignatura']], ['Grupo', $a['grado'] . ' ' . $a['grupo']], ['Docente', (string) ($a['docente'] ?: 'Sin asignar')], ['Clases registradas', (string) count($fechas)]]),
            self::tabla($enc, $filas, 'cl' . str_repeat('c', count($fechas) + 3), null, array_merge(['', 'Totales'], array_fill(0, count($fechas), ''), [(string) $tot['A'], (string) $tot['T'], (string) $tot['E']]), array_merge([4, 26], array_fill(0, count($fechas), 3.6), [4, 4, 4])),
            self::nota('· presente   A ausente   T tarde   E excusa. ' . ($fechas ? '' : 'No hay asistencia registrada en ese rango. ') . 'Máximo 31 fechas por informe.'),
        ]];
    }

    // ---------------------------------------------------------- 37. Escala de inasistencia
    public static function escala(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $as = self::asistencia($pdo, $ctx, $f, $roster);
        $rangos = [[0, 0, '0'], [1, 3, '1 a 3'], [4, 7, '4 a 7'], [8, 12, '8 a 12'], [13, 20, '13 a 20'], [21, PHP_INT_MAX, 'Más de 20']];
        $cuenta = array_fill(0, count($rangos), 0);
        foreach ($roster as $r) {
            $a = $as[(int) $r['id']]['aus'];
            foreach ($rangos as $i => [$lo, $hi]) {
                if ($a >= $lo && $a <= $hi) {
                    $cuenta[$i]++;
                    break;
                }
            }
        }
        $total = count($roster);
        $filas = [];
        foreach ($rangos as $i => $x) {
            $filas[] = [$x[2], (string) $cuenta[$i], Informes::pct((float) $cuenta[$i], (float) $total)];
        }
        $lista = array_filter($roster, static fn (array $r): bool => $as[(int) $r['id']]['aus'] > 0);
        usort($lista, static fn (array $a, array $b): int => $as[(int) $b['id']]['aus'] <=> $as[(int) $a['id']]['aus']);
        $det = [];
        foreach ($lista as $i => $r) {
            $x = $as[(int) $r['id']];
            $det[] = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r), (string) $x['aus'], (string) $x['exc'], (string) $x['tar']];
        }
        return ['bloques' => [
            self::tabla(['Inasistencias', 'Estudiantes', '%'], $filas, 'lcc', 'Escala de inasistencia', ['Total', (string) $total, '']),
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Ausencias', 'Excusadas', 'Retardos'], $det, 'cllccc', 'Detalle de estudiantes con inasistencias'),
            self::nota('Se cuentan las ausencias a clase registradas por los docentes (por asignatura) más el resumen por período, cuando no se filtra por fechas.'),
        ]];
    }

    // ---------------------------------------------------------- 38. Por tipo
    public static function porTipo(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $as = self::asistencia($pdo, $ctx, $f, $roster);
        $g = [];
        foreach ($roster as $r) {
            $k = (int) $r['grupo_id'];
            $g[$k] ??= ['n' => self::grupoTxt($r), 'est' => 0, 'aus' => 0, 'tar' => 0, 'exc' => 0];
            $g[$k]['est']++;
            foreach (['aus', 'tar', 'exc'] as $t) {
                $g[$k][$t] += $as[(int) $r['id']][$t];
            }
        }
        uasort($g, static fn (array $a, array $b): int => strnatcasecmp($a['n'], $b['n']));
        $filas = [];
        $t = ['est' => 0, 'aus' => 0, 'tar' => 0, 'exc' => 0];
        foreach ($g as $x) {
            foreach ($t as $k => $_) {
                $t[$k] += $x[$k];
            }
            $filas[] = [$x['n'], (string) $x['est'], (string) $x['aus'], (string) $x['exc'], (string) $x['tar'], (string) ($x['aus'] + $x['exc'] + $x['tar'])];
        }
        return ['bloques' => [self::tabla(['Grupo', 'Estudiantes', 'Ausencias', 'Excusas', 'Retardos', 'Total'], $filas, 'lccccc', null, ['Total', (string) $t['est'], (string) $t['aus'], (string) $t['exc'], (string) $t['tar'], (string) ($t['aus'] + $t['exc'] + $t['tar'])]),
            self::nota('Ausencia = falta sin excusa. Excusa = falta justificada. Retardo = llegada tarde.')]];
    }

    // ---------------------------------------------------------- 39. Porcentaje
    public static function porcentaje(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $as = self::asistencia($pdo, $ctx, $f, $roster);
        $umbral = $f['umbral'] ?? 10.0;
        $g = [];
        $det = [];
        foreach ($roster as $r) {
            $x = $as[(int) $r['id']];
            $k = (int) $r['grupo_id'];
            $g[$k] ??= ['n' => self::grupoTxt($r), 'est' => 0, 'aus' => 0, 'reg' => 0];
            $g[$k]['est']++;
            $g[$k]['aus'] += $x['aus'];
            $g[$k]['reg'] += $x['reg'];
            if ($x['reg'] > 0) {
                $p = $x['aus'] * 100 / $x['reg'];
                if ($p >= $umbral) {
                    $det[] = [$r, $x, $p];
                }
            }
        }
        usort($det, static fn (array $a, array $b): int => $b[2] <=> $a[2]);
        uasort($g, static fn (array $a, array $b): int => strnatcasecmp($a['n'], $b['n']));
        $filas = [];
        foreach ($g as $x) {
            $filas[] = [$x['n'], (string) $x['est'], (string) $x['aus'], (string) $x['reg'], Informes::pct((float) $x['aus'], (float) $x['reg'], 2)];
        }
        $filasD = [];
        foreach ($det as $i => [$r, $x, $p]) {
            $filasD[] = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r), (string) $x['aus'], (string) $x['reg'], number_format($p, 1, ',', '') . ' %'];
        }
        return ['bloques' => [
            self::tabla(['Grupo', 'Estudiantes', 'Ausencias', 'Registros de asistencia', '% de inasistencia'], $filas, 'lcccc', 'Porcentaje de inasistencia por grupo'),
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Ausencias', 'Registros', '%'], $filasD, 'cllccc', 'Estudiantes con inasistencia igual o superior a ' . number_format((float) $umbral, 1, ',', '') . ' %'),
            self::nota('% = ausencias sin excusa / registros de asistencia diaria (presente, ausente, tarde, excusa). No incluye el resumen por período, que no guarda el total de clases.'),
        ]];
    }
}
