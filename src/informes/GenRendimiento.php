<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 2: rendimiento académico. */
final class GenRendimiento extends GenBase
{
    private static function etiquetaNota(array $f): string
    {
        return $f['periodo'] > 0 ? (string) $f['txt']['periodo'] : 'definitiva anual';
    }

    private static function nivelTxt(array $ctx, ?float $v): string
    {
        $n = Informes::nivel($ctx['escala'], $v);
        return $n ? (string) $n['nombre'] : '—';
    }

    // ---------------------------------------------------------- 11. Alto rendimiento
    public static function alto(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $umbral = $f['umbral'];
        if ($umbral === null) {
            $umbral = 0.0;
            foreach ($ctx['escala']['niveles'] as $n) {
                $umbral = max($umbral, (float) $n['desde']);
            }
        }
        $lista = [];
        foreach ($m['roster'] as $r) {
            $v = $m['prom'][(int) $r['id']];
            if ($v !== null && $v >= $umbral - 0.00001) {
                $lista[] = [$r, $v];
            }
        }
        usort($lista, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
        $filas = [];
        foreach ($lista as $i => [$r, $v]) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r), Informes::nota($ctx, $v), self::nivelTxt($ctx, $v), isset($m['puesto'][(int) $r['id']]) ? (string) $m['puesto'][(int) $r['id']] : '—'];
        }
        return ['bloques' => [
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Promedio', 'Desempeño', 'Puesto en el grupo'], $filas, 'cllccc'),
            self::nota('Promedio de ' . self::etiquetaNota($f) . ' igual o superior a ' . Informes::nota($ctx, (float) $umbral) . '. ' . count($lista) . ' de ' . count($m['roster']) . ' estudiantes.'),
        ]];
    }

    // ---------------------------------------------------------- 12. Bajo rendimiento
    public static function bajo(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $umbral = $f['umbral'] ?? Informes::minimoAprobatorio($ctx['escala']);
        $perd = Informes::perdidas($m);
        $lista = [];
        foreach ($m['roster'] as $r) {
            $v = $m['prom'][(int) $r['id']];
            if ($v !== null && $v < $umbral - 0.00001) {
                $lista[] = [$r, $v];
            }
        }
        usort($lista, static fn (array $a, array $b): int => $a[1] <=> $b[1]);
        $filas = [];
        foreach ($lista as $i => [$r, $v]) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r), Informes::nota($ctx, $v), self::nivelTxt($ctx, $v), (string) count($perd[(int) $r['id']] ?? [])];
        }
        return ['bloques' => [
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Promedio', 'Desempeño', 'Asignaturas perdidas'], $filas, 'cllccc'),
            self::nota('Promedio de ' . self::etiquetaNota($f) . ' inferior a ' . Informes::nota($ctx, (float) $umbral) . '. ' . count($lista) . ' de ' . count($m['roster']) . ' estudiantes.'),
        ]];
    }

    // ---------------------------------------------------------- 13. Logros perdidos
    public static function logrosPerdidos(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $perd = Informes::perdidas($m);
        $aids = [];
        foreach ($perd as $lista) {
            foreach ($lista as [$a, $v]) {
                $aids[(int) $a['id']] = true;
            }
        }
        $logros = [];
        if ($aids) {
            $q = $pdo->prepare('SELECT asignacion_id, descripcion FROM logros WHERE periodo_id = ? AND asignacion_id IN (' . Informes::in(array_keys($aids)) . ') ORDER BY orden, id');
            $q->execute([$f['periodo']]);
            foreach ($q->fetchAll() as $r) {
                $logros[(int) $r['asignacion_id']][] = (string) $r['descripcion'];
            }
        }
        $filas = [];
        foreach ($m['roster'] as $r) {
            foreach ($perd[(int) $r['id']] ?? [] as [$a, $v]) {
                $l = $logros[(int) $a['id']] ?? [];
                $filas[] = [(string) $r['nombre'], self::grupoTxt($r), (string) $a['asignatura'], Informes::nota($ctx, $v), $l ? Informes::corto(implode(' · ', $l), 420) : '(sin logros registrados)'];
            }
        }
        return ['bloques' => [
            self::tabla(['Estudiante', 'Grupo', 'Asignatura', 'Nota', 'Logros por recuperar'], $filas, 'lllcl', null, null, [24, 9, 16, 6, 45]),
            self::nota('Se listan las asignaturas perdidas en el período con los logros definidos por el docente para esa asignatura y período.'),
        ]];
    }

    // ---------------------------------------------------------- 14. Estudiantes perdiendo
    public static function perdiendo(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $perd = Informes::perdidas($m);
        $lista = [];
        foreach ($m['roster'] as $r) {
            if (!empty($perd[(int) $r['id']])) {
                $lista[] = [$r, $perd[(int) $r['id']]];
            }
        }
        usort($lista, static fn (array $a, array $b): int => count($b[1]) <=> count($a[1]));
        $filas = [];
        foreach ($lista as $i => [$r, $p]) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r), (string) count($p),
                implode('; ', array_map(static fn (array $x): string => $x[0]['asignatura'] . ' (' . Informes::nota($ctx, $x[1]) . ')', $p))];
        }
        return ['bloques' => [
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Perdidas', 'Asignaturas (nota)'], $filas, 'cllcl', null, null, [4, 24, 9, 7, 56]),
            self::nota(count($lista) . ' de ' . count($m['roster']) . ' estudiantes con al menos una asignatura perdida en ' . self::etiquetaNota($f) . '.'),
        ]];
    }

    // ---------------------------------------------------------- 15. Por área
    public static function porArea(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $ar = [];
        foreach ($m['roster'] as $r) {
            foreach ($m['porGrupo'][(int) $r['grupo_id']] ?? [] as $a) {
                $v = $m['val'][(int) $r['id']][(int) $a['id']] ?? null;
                if ($v === null) {
                    continue;
                }
                $k = trim((string) ($a['area'] ?? '')) !== '' ? (string) $a['area'] : 'Sin área definida';
                $ar[$k] ??= ['n' => 0, 'suma' => 0.0, 'ok' => 0, 'min' => $v, 'max' => $v];
                $ar[$k]['n']++;
                $ar[$k]['suma'] += $v;
                $ar[$k]['ok'] += Informes::aprueba($m['escala'], $v) === true ? 1 : 0;
                $ar[$k]['min'] = min($ar[$k]['min'], $v);
                $ar[$k]['max'] = max($ar[$k]['max'], $v);
            }
        }
        ksort($ar);
        $filas = [];
        foreach ($ar as $k => $x) {
            $filas[] = [$k, (string) $x['n'], Informes::nota($ctx, round($x['suma'] / $x['n'], $m['dec'])), Informes::nota($ctx, $x['max']), Informes::nota($ctx, $x['min']), (string) $x['ok'], (string) ($x['n'] - $x['ok']), Informes::pct((float) $x['ok'], (float) $x['n'])];
        }
        return ['bloques' => [self::tabla(['Área', 'Valoraciones', 'Promedio', 'Mayor', 'Menor', 'Aprobadas', 'Perdidas', '% aprobación'], $filas, 'lccccccc'),
            self::nota('Cada valoración es la nota de un estudiante en una asignatura del área (' . self::etiquetaNota($f) . ').')]];
    }

    // ---------------------------------------------------------- 16. Por asignatura
    public static function porAsignatura(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $ag = [];
        foreach ($m['roster'] as $r) {
            foreach ($m['porGrupo'][(int) $r['grupo_id']] ?? [] as $a) {
                $aid = (int) $a['id'];
                $ag[$aid] ??= ['a' => $a, 'n' => 0, 'suma' => 0.0, 'ok' => 0];
                $v = $m['val'][(int) $r['id']][$aid] ?? null;
                if ($v !== null) {
                    $ag[$aid]['n']++;
                    $ag[$aid]['suma'] += $v;
                    $ag[$aid]['ok'] += Informes::aprueba($m['escala'], $v) === true ? 1 : 0;
                }
            }
        }
        $filas = [];
        foreach ($ag as $x) {
            $a = $x['a'];
            $filas[] = [$a['grado'] . ' ' . $a['grupo'], (string) $a['asignatura'], (string) ($a['docente'] ?: '—'), (string) $x['n'], $x['n'] ? Informes::nota($ctx, round($x['suma'] / $x['n'], $m['dec'])) : '—',
                (string) $x['ok'], (string) ($x['n'] - $x['ok']), Informes::pct((float) $x['ok'], (float) $x['n'])];
        }
        usort($filas, static fn (array $a, array $b): int => strnatcasecmp($a[0] . $a[1], $b[0] . $b[1]));
        return ['bloques' => [self::tabla(['Grupo', 'Asignatura', 'Docente', 'Con nota', 'Promedio', 'Aprobados', 'Perdidos', '% aprobación'], $filas, 'lllccccc')]];
    }

    // ---------------------------------------------------------- 17. Estudiante (asignaturas)
    public static function estudianteAsignaturas(PDO $pdo, array $ctx, array $f): array
    {
        $roster = self::rosterAcotado($pdo, $ctx, $f, 80);
        $mats = self::matrices($pdo, $ctx, $f, $roster);
        $bl = [];
        foreach ($roster as $i => $r) {
            if ($i > 0) {
                $bl[] = self::salto();
            }
            $m = $mats[(int) $r['grupo_id']] ?? null;
            $e = (int) $r['id'];
            if ($m === null) {
                continue;
            }
            $bl[] = self::banda(mb_strtoupper((string) $r['nombre'], 'UTF-8') . ' · ' . self::grupoTxt($r));
            $filas = [];
            foreach ($m['porGrupo'][(int) $r['grupo_id']] ?? [] as $a) {
                $aid = (int) $a['id'];
                $v = $m['val'][$e][$aid] ?? null;
                $col = [];
                foreach ($m['roster'] as $o) {
                    $x = $m['val'][(int) $o['id']][$aid] ?? null;
                    if ($x !== null) {
                        $col[] = $x;
                    }
                }
                $pg = $col ? round(array_sum($col) / count($col), $m['dec']) : null;
                $dif = ($v !== null && $pg !== null) ? ($v - $pg) : null;
                $filas[] = [(string) $a['asignatura'], (string) ($a['docente'] ?: '—'), Informes::nota($ctx, $v), self::nivelTxt($ctx, $v), Informes::nota($ctx, $pg),
                    $dif === null ? '—' : (($dif > 0 ? '+' : '') . number_format($dif, $m['dec'], '.', ''))];
            }
            $pr = $m['prom'][$e] ?? null;
            $bl[] = self::tabla(['Asignatura', 'Docente', 'Nota', 'Desempeño', 'Promedio del grupo', 'Diferencia'], $filas, 'llcccc', null,
                ['Promedio general', '', Informes::nota($ctx, $pr), self::nivelTxt($ctx, $pr), '', isset($m['puesto'][$e]) ? 'Puesto ' . $m['puesto'][$e] : '']);
        }
        return ['bloques' => $bl ?: [self::nota('No hay estudiantes para ese filtro.')]];
    }

    // ---------------------------------------------------------- 18. Estudiante (promedios)
    public static function estudiantePromedios(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $enc = ['N.º', 'Estudiante', 'Grupo'];
        foreach ($m['periodos'] as $p) {
            $enc[] = (string) $p['nombre'];
        }
        array_push($enc, 'Promedio final', 'Desempeño', 'Puesto');
        $filas = [];
        foreach ($m['roster'] as $i => $r) {
            $e = (int) $r['id'];
            $fila = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r)];
            foreach ($m['periodos'] as $p) {
                $fila[] = Informes::nota($ctx, $m['promP'][$e][(int) $p['id']] ?? null);
            }
            array_push($fila, Informes::nota($ctx, $m['prom'][$e]), self::nivelTxt($ctx, $m['prom'][$e]), isset($m['puesto'][$e]) ? (string) $m['puesto'][$e] : '—');
            $filas[] = $fila;
        }
        return ['bloques' => [self::tabla($enc, $filas, 'cll' . str_repeat('c', count($m['periodos']) + 3))]];
    }

    // ---------------------------------------------------------- 19. Por grupo
    public static function porGrupo(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $perd = Informes::perdidas($m);
        $g = [];
        foreach ($m['roster'] as $r) {
            $k = (int) $r['grupo_id'];
            $g[$k] ??= ['nombre' => self::grupoTxt($r), 'n' => 0, 'vals' => [], 'ok' => 0];
            $g[$k]['n']++;
            $v = $m['prom'][(int) $r['id']];
            if ($v !== null) {
                $g[$k]['vals'][] = $v;
                $g[$k]['ok'] += empty($perd[(int) $r['id']]) ? 1 : 0;
            }
        }
        uasort($g, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));
        $filas = [];
        foreach ($g as $x) {
            $c = count($x['vals']);
            $filas[] = [$x['nombre'], (string) $x['n'], $c ? Informes::nota($ctx, round(array_sum($x['vals']) / $c, $m['dec'])) : '—', $c ? Informes::nota($ctx, max($x['vals'])) : '—', $c ? Informes::nota($ctx, min($x['vals'])) : '—',
                (string) $x['ok'], Informes::pct((float) $x['ok'], (float) $c)];
        }
        return ['bloques' => [self::tabla(['Grupo', 'Estudiantes', 'Promedio', 'Mejor promedio', 'Menor promedio', 'Sin áreas perdidas', '% sin pérdidas'], $filas, 'lcccccc'),
            self::nota('Valoración: ' . self::etiquetaNota($f) . '.')]];
    }
}
