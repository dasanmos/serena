<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 1: calificaciones, evaluación y resultados académicos. */
final class GenNotas extends GenBase
{
    /** Asignaciones del alcance (filtro de asignación o de grupo). */
    private static function asignacionesDe(PDO $pdo, array $ctx, array $f, bool $exigir = true): array
    {
        $todas = Informes::asignaciones($pdo, $ctx, $f['anio']);
        if ($f['asignacion'] > 0) {
            $todas = array_values(array_filter($todas, static fn (array $a): bool => (int) $a['id'] === $f['asignacion']));
        } elseif ($f['grupo'] > 0) {
            $todas = array_values(array_filter($todas, static fn (array $a): bool => (int) $a['grupo_id'] === $f['grupo']));
        } elseif ($exigir) {
            throw new InformeError('Elige el grupo o la asignatura.');
        }
        return $todas;
    }

    private static function celda(array $ctx, ?float $valor, ?array $nivel): string
    {
        if ($valor === null) {
            return '';
        }
        if (($ctx['escala']['tipo'] ?? '') === 'descriptiva' && $nivel) {
            return (string) $nivel['abreviatura'];
        }
        return Informes::nota($ctx, $valor);
    }

    // ---------------------------------------------------------- 1. Planilla auxiliar
    public static function planillaAuxiliar(PDO $pdo, array $ctx, array $f): array
    {
        $asigs = self::asignacionesDe($pdo, $ctx, $f);
        $b = [];
        foreach ($asigs as $i => $a) {
            if ($i > 0) {
                $b[] = self::salto();
            }
            $b[] = self::banda($a['asignatura'] . ' · ' . $a['grado'] . ' ' . $a['grupo'] . ' · Docente: ' . ($a['docente'] ?: 'sin asignar'));
            $ests = Evaluacion::estudiantesDeGrupo($pdo, $ctx['colegioId'], $f['anio_row'], (int) $a['grupo_id']);
            $enc = ['N.º', 'Estudiante'];
            for ($k = 1; $k <= 10; $k++) {
                $enc[] = (string) $k;
            }
            $enc[] = 'Def.';
            $filas = [];
            foreach ($ests as $n => $e) {
                $filas[] = array_merge([(string) ($n + 1), (string) $e['nombre']], array_fill(0, 11, ''));
            }
            $b[] = self::tabla($enc, $filas, 'lc' . str_repeat('c', 11), null, null, array_merge([4, 30], array_fill(0, 11, 4.5)));
            $b[] = self::nota('Planilla auxiliar para anotar a mano. Las notas oficiales se registran en la planilla del sistema.');
        }
        if (!$b) {
            $b[] = self::nota('No hay asignaciones académicas para ese filtro.');
        }
        return ['bloques' => $b];
    }

    // ---------------------------------------------------------- 2. Planilla de notas
    public static function planillaNotas(PDO $pdo, array $ctx, array $f): array
    {
        $a = self::asignacionesDe($pdo, $ctx, $f)[0] ?? null;
        if ($a === null) {
            throw new InformeError('No se encontró la asignatura.');
        }
        $esc = $ctx['escala'];
        if ($esc === null) {
            throw new InformeError('El colegio aún no tiene un sistema de evaluación configurado.');
        }
        $niveles = [];
        foreach ($esc['niveles'] as $n) {
            $niveles[(int) $n['id']] = $n;
        }
        $acts = Evaluacion::actividades($pdo, (int) $a['id'], $f['periodo']);
        $ests = Evaluacion::estudiantesDeGrupo($pdo, $ctx['colegioId'], $f['anio_row'], (int) $a['grupo_id']);
        $cal = [];
        if ($acts) {
            $q = $pdo->query('SELECT actividad_id, estudiante_id, valor, nivel_id FROM calificaciones WHERE actividad_id IN (' . Informes::in(array_column($acts, 'id')) . ')');
            foreach ($q->fetchAll() as $r) {
                $cal[(int) $r['estudiante_id']][(int) $r['actividad_id']] = $r;
            }
        }
        $q = $pdo->prepare('SELECT estudiante_id, valor, nivel_id, observacion FROM notas_periodo WHERE asignacion_id = ? AND periodo_id = ?');
        $q->execute([(int) $a['id'], $f['periodo']]);
        $np = [];
        foreach ($q->fetchAll() as $r) {
            $np[(int) $r['estudiante_id']] = $r;
        }
        $enc = ['N.º', 'Estudiante'];
        foreach ($acts as $x) {
            $enc[] = Informes::corto((string) $x['nombre'], 18) . ((float) $x['peso'] > 0 ? ' (' . rtrim(rtrim(number_format((float) $x['peso'], 1, '.', ''), '0'), '.') . '%)' : '');
        }
        $enc[] = 'Nota período';
        $enc[] = 'Desempeño';
        $filas = [];
        foreach ($ests as $n => $e) {
            $id = (int) $e['id'];
            $r = [(string) ($n + 1), (string) $e['nombre']];
            foreach ($acts as $x) {
                $c = $cal[$id][(int) $x['id']] ?? null;
                $r[] = $c ? self::celda($ctx, $c['valor'] !== null ? (float) $c['valor'] : null, isset($c['nivel_id']) ? ($niveles[(int) $c['nivel_id']] ?? null) : null) : '';
            }
            $p = $np[$id] ?? null;
            $v = ($p && $p['valor'] !== null) ? (float) $p['valor'] : null;
            $nv = $p && $p['nivel_id'] !== null ? ($niveles[(int) $p['nivel_id']] ?? null) : Informes::nivel($esc, $v);
            $r[] = $v !== null ? Evaluacion::textoNota($v, $nv, $esc) : '—';
            $r[] = $nv ? (string) $nv['nombre'] : '—';
            $filas[] = $r;
        }
        return ['bloques' => [
            self::pares([['Asignatura', (string) $a['asignatura']], ['Grupo', $a['grado'] . ' ' . $a['grupo']], ['Docente', (string) ($a['docente'] ?: 'Sin asignar')], ['Período', (string) $f['txt']['periodo']],
                ['Escala', (string) $esc['nombre']], ['Actividades', (string) count($acts)]]),
            self::tabla($enc, $filas, 'lc' . str_repeat('c', count($acts) + 2)),
            self::firmas([[(string) ($a['docente'] ?: ''), 'Docente'], ['', 'Coordinación académica']]),
        ]];
    }

    // ---------------------------------------------------------- 3 y 4. Verificación y progreso
    /** @return array<int,array<string,mixed>> una fila de avance por asignación */
    private static function avance(PDO $pdo, array $ctx, array $f, array $asigs): array
    {
        $ids = Informes::in(array_column($asigs, 'id'));
        $pid = $f['periodo'];
        $con = [];
        $q = $pdo->prepare("SELECT asignacion_id, COUNT(*) AS n FROM notas_periodo WHERE periodo_id = ? AND valor IS NOT NULL AND asignacion_id IN ({$ids}) GROUP BY asignacion_id");
        $q->execute([$pid]);
        foreach ($q->fetchAll() as $r) {
            $con[(int) $r['asignacion_id']] = (int) $r['n'];
        }
        $act = [];
        $q = $pdo->prepare("SELECT asignacion_id, COUNT(*) AS n FROM actividades_evaluacion WHERE periodo_id = ? AND asignacion_id IN ({$ids}) GROUP BY asignacion_id");
        $q->execute([$pid]);
        foreach ($q->fetchAll() as $r) {
            $act[(int) $r['asignacion_id']] = (int) $r['n'];
        }
        $out = [];
        foreach ($asigs as $a) {
            $n = count(Evaluacion::estudiantesDeGrupo($pdo, $ctx['colegioId'], $f['anio_row'], (int) $a['grupo_id']));
            $c = $con[(int) $a['id']] ?? 0;
            $na = $act[(int) $a['id']] ?? 0;
            $estado = $na === 0 ? 'Sin actividades' : ($c === 0 ? 'Sin notas' : ($c < $n ? 'Incompleta' : 'Completa'));
            $out[] = ['a' => $a, 'n' => $n, 'con' => $c, 'act' => $na, 'estado' => $estado];
        }
        return $out;
    }

    public static function planillaVerificacion(PDO $pdo, array $ctx, array $f): array
    {
        $asigs = self::asignacionesDe($pdo, $ctx, $f, false);
        if ($f['asignacion'] === 0) {
            $filas = [];
            foreach (self::avance($pdo, $ctx, $f, $asigs) as $x) {
                $a = $x['a'];
                $filas[] = [$a['grado'] . ' ' . $a['grupo'], (string) $a['asignatura'], (string) ($a['docente'] ?: '—'), (string) $x['n'], (string) $x['con'], (string) max(0, $x['n'] - $x['con']), (string) $x['act'], $x['estado']];
            }
            return ['bloques' => [self::tabla(['Grupo', 'Asignatura', 'Docente', 'Estudiantes', 'Con nota', 'Sin nota', 'Actividades', 'Estado'], $filas, 'llllcccc'),
                self::nota('Elige una asignatura para ver el detalle por estudiante.')]];
        }
        $a = $asigs[0] ?? null;
        if ($a === null) {
            throw new InformeError('No se encontró la asignatura.');
        }
        $ests = Evaluacion::estudiantesDeGrupo($pdo, $ctx['colegioId'], $f['anio_row'], (int) $a['grupo_id']);
        $acts = Evaluacion::actividades($pdo, (int) $a['id'], $f['periodo']);
        $np = [];
        $q = $pdo->prepare('SELECT estudiante_id, valor, nivel_id FROM notas_periodo WHERE asignacion_id = ? AND periodo_id = ?');
        $q->execute([(int) $a['id'], $f['periodo']]);
        foreach ($q->fetchAll() as $r) {
            $np[(int) $r['estudiante_id']] = $r;
        }
        $cal = [];
        if ($acts) {
            $q = $pdo->query('SELECT estudiante_id, COUNT(*) AS n FROM calificaciones WHERE valor IS NOT NULL AND actividad_id IN (' . Informes::in(array_column($acts, 'id')) . ') GROUP BY estudiante_id');
            foreach ($q->fetchAll() as $r) {
                $cal[(int) $r['estudiante_id']] = (int) $r['n'];
            }
        }
        $filas = [];
        foreach ($ests as $n => $e) {
            $id = (int) $e['id'];
            $v = isset($np[$id]) && $np[$id]['valor'] !== null ? (float) $np[$id]['valor'] : null;
            $c = $cal[$id] ?? 0;
            $estado = $v === null ? 'Sin nota del período' : ($c < count($acts) ? 'Nota con actividades sin calificar' : 'Verificado');
            $filas[] = [(string) ($n + 1), (string) $e['nombre'], $c . ' de ' . count($acts), Informes::nota($ctx, $v), $estado];
        }
        return ['bloques' => [
            self::pares([['Asignatura', (string) $a['asignatura']], ['Grupo', $a['grado'] . ' ' . $a['grupo']], ['Docente', (string) ($a['docente'] ?: 'Sin asignar')], ['Período', (string) $f['txt']['periodo']]]),
            self::tabla(['N.º', 'Estudiante', 'Actividades calificadas', 'Nota del período', 'Estado'], $filas, 'llccl'),
            self::firmas([['', 'Docente'], ['', 'Coordinación académica']]),
        ]];
    }

    public static function progresoEvaluacion(PDO $pdo, array $ctx, array $f): array
    {
        $asigs = self::asignacionesDe($pdo, $ctx, $f, false);
        $filas = [];
        $tn = 0;
        $tc = 0;
        foreach (self::avance($pdo, $ctx, $f, $asigs) as $x) {
            $a = $x['a'];
            $tn += $x['n'];
            $tc += $x['con'];
            $filas[] = [$a['grado'] . ' ' . $a['grupo'], (string) $a['asignatura'], (string) ($a['docente'] ?: '—'), (string) $x['act'], $x['con'] . ' de ' . $x['n'], Informes::pct((float) $x['con'], (float) $x['n'], 0), $x['estado']];
        }
        return ['bloques' => [
            self::tabla(['Grupo', 'Asignatura', 'Docente', 'Actividades', 'Notas del período', 'Avance', 'Estado'], $filas, 'lllcccc', null, ['Total', '', '', '', $tc . ' de ' . $tn, Informes::pct((float) $tc, (float) $tn, 0), '']),
            self::nota('Avance = estudiantes con nota del período sobre estudiantes del grupo.'),
        ]];
    }

    // ---------------------------------------------------------- 5. Evaluación semestral
    public static function evaluacionSemestral(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $per = $m['periodos'];
        if (count($per) < 2) {
            throw new InformeError('El año lectivo necesita al menos dos períodos para dividirlo en semestres.');
        }
        $corte = (int) ceil(count($per) / 2);
        $s1 = array_slice($per, 0, $corte);
        $s2 = array_slice($per, $corte);
        $filas = [];
        foreach ($m['roster'] as $i => $r) {
            $e = (int) $r['id'];
            $p1 = [];
            $p2 = [];
            foreach ($m['porGrupo'][(int) $r['grupo_id']] ?? [] as $a) {
                $porP = $m['n'][$e][(int) $a['id']] ?? [];
                $d1 = Evaluacion::definitiva($porP, $s1);
                if ($d1 !== null) {
                    $p1[] = [round($d1, $m['dec']), (float) ($a['intensidad'] ?? 0)];
                }
                $d2 = Evaluacion::definitiva($porP, $s2);
                if ($d2 !== null) {
                    $p2[] = [round($d2, $m['dec']), (float) ($a['intensidad'] ?? 0)];
                }
            }
            $a1 = Informes::promedio($p1);
            $a2 = Informes::promedio($p2);
            $anual = $m['prom'][$e];
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r), Informes::nota($ctx, $a1 !== null ? round($a1, $m['dec']) : null), Informes::nota($ctx, $a2 !== null ? round($a2, $m['dec']) : null),
                Informes::nota($ctx, $anual), ($n = Informes::nivel($ctx['escala'], $anual)) ? (string) $n['nombre'] : '—'];
        }
        return ['bloques' => [
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Semestre 1', 'Semestre 2', 'Promedio anual', 'Desempeño'], $filas, 'llccccc'),
            self::nota('Semestre 1 = ' . implode(', ', array_column($s1, 'nombre')) . '. Semestre 2 = ' . implode(', ', array_column($s2, 'nombre')) . '. Cada semestre pondera sus períodos por el peso configurado.'),
        ]];
    }

    // ---------------------------------------------------------- 6. Estudiantes por escala
    public static function estudiantesEscala(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $esc = $ctx['escala'];
        $niveles = $esc['niveles'];
        $cuenta = array_fill_keys(array_map(static fn (array $n): int => (int) $n['id'], $niveles), 0);
        $sinNota = 0;
        foreach ($m['roster'] as $r) {
            $n = Informes::nivel($esc, $m['prom'][(int) $r['id']]);
            if ($n === null) {
                $sinNota++;
            } else {
                $cuenta[(int) $n['id']]++;
            }
        }
        $total = count($m['roster']);
        $filas = [];
        foreach ($niveles as $n) {
            $filas[] = [(string) $n['nombre'], rtrim(rtrim(number_format((float) $n['desde'], 2, '.', ''), '0'), '.') . ' a ' . rtrim(rtrim(number_format((float) $n['hasta'], 2, '.', ''), '0'), '.'), (int) $n['aprueba'] === 1 ? 'Aprueba' : 'No aprueba', (string) $cuenta[(int) $n['id']], Informes::pct((float) $cuenta[(int) $n['id']], (float) $total)];
        }
        $filas[] = ['Sin valoración', '', '', (string) $sinNota, Informes::pct((float) $sinNota, (float) $total)];
        $b = [self::tabla(['Nivel', 'Rango', 'Resultado', 'Estudiantes', '%'], $filas, 'llccc', 'Promedio general de cada estudiante', ['Total', '', '', (string) $total, '']), ];
        $asig = [];
        $porNombre = [];
        foreach ($m['roster'] as $r) {
            foreach ($m['porGrupo'][(int) $r['grupo_id']] ?? [] as $a) {
                $k = (string) $a['asignatura'];
                $porNombre[$k] ??= array_fill_keys(array_keys($cuenta), 0);
                $n = Informes::nivel($esc, $m['val'][(int) $r['id']][(int) $a['id']] ?? null);
                if ($n !== null) {
                    $porNombre[$k][(int) $n['id']]++;
                }
            }
        }
        ksort($porNombre);
        foreach ($porNombre as $nombre => $c) {
            $asig[] = array_merge([$nombre], array_map('strval', array_values($c)));
        }
        $b[] = self::tabla(array_merge(['Asignatura'], array_map(static fn (array $n): string => (string) $n['nombre'], $niveles)), $asig, 'l' . str_repeat('c', count($niveles)), 'Valoración por asignatura (número de estudiantes)');
        return ['bloques' => $b];
    }

    // ---------------------------------------------------------- 7. Resultados académicos
    public static function resultadosAcademicos(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $per = Informes::perdidas($m);
        $g = [];
        foreach ($m['roster'] as $r) {
            $k = (int) $r['grupo_id'];
            $g[$k] ??= ['nombre' => self::grupoTxt($r), 'n' => 0, 'con' => 0, 'ok' => 0, 'p12' => 0, 'p3' => 0, 'suma' => 0.0];
            $g[$k]['n']++;
            $e = (int) $r['id'];
            if ($m['prom'][$e] === null) {
                continue;
            }
            $g[$k]['con']++;
            $g[$k]['suma'] += $m['prom'][$e];
            $np = count($per[$e] ?? []);
            if ($np === 0) {
                $g[$k]['ok']++;
            } elseif ($np <= 2) {
                $g[$k]['p12']++;
            } else {
                $g[$k]['p3']++;
            }
        }
        uasort($g, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));
        $filas = [];
        $t = ['n' => 0, 'con' => 0, 'ok' => 0, 'p12' => 0, 'p3' => 0, 'suma' => 0.0];
        foreach ($g as $x) {
            foreach ($t as $k => $_) {
                $t[$k] += $x[$k];
            }
            $filas[] = [$x['nombre'], (string) $x['n'], (string) $x['con'], (string) $x['ok'], (string) $x['p12'], (string) $x['p3'], $x['con'] ? Informes::nota($ctx, round($x['suma'] / $x['con'], $m['dec'])) : '—', Informes::pct((float) $x['ok'], (float) $x['con'])];
        }
        return ['bloques' => [
            self::tabla(['Grupo', 'Estudiantes', 'Con valoración', 'Sin áreas perdidas', '1 a 2 perdidas', '3 o más perdidas', 'Promedio', '% sin pérdidas'], $filas, 'lccccccc', null,
                ['Total', (string) $t['n'], (string) $t['con'], (string) $t['ok'], (string) $t['p12'], (string) $t['p3'], $t['con'] ? Informes::nota($ctx, round($t['suma'] / $t['con'], $m['dec'])) : '—', Informes::pct((float) $t['ok'], (float) $t['con'])]),
            self::nota('«Perdidas» = asignaturas cuyo desempeño no aprueba según la escala del colegio' . ($f['periodo'] > 0 ? ' en el período elegido.' : ' en la definitiva anual.')),
        ]];
    }

    // ---------------------------------------------------------- 8. Libro de calificaciones
    public static function libroCalificaciones(PDO $pdo, array $ctx, array $f): array
    {
        $roster = self::rosterAcotado($pdo, $ctx, $f, 120);
        if ($ctx['escala'] === null) {
            throw new InformeError('El colegio aún no tiene un sistema de evaluación configurado.');
        }
        $bl = [];
        foreach ($roster as $i => $r) {
            if ($i > 0) {
                $bl[] = self::salto();
            }
            $b = Evaluacion::boletin($pdo, $ctx['colegioId'], (int) $r['id'], $f['anio_row'], false);
            $bl[] = self::banda('Folio ' . ($i + 1) . ' · ' . mb_strtoupper((string) $r['nombre'], 'UTF-8'));
            $bl[] = self::pares([['Documento', self::docTxt($r)], ['Grado y grupo', self::grupoTxt($r)], ['Año lectivo', (string) $f['anio_row']['anio']], ['Escala', (string) $ctx['escala']['nombre']]]);
            $bl[] = self::tablaBoletin($ctx, $b, true);
            $bl[] = self::firmas([['', 'Rector(a)'], ['', 'Secretario(a) académico(a)']]);
        }
        return ['bloques' => $bl ?: [self::nota('No hay estudiantes para ese filtro.')]];
    }

    // ---------------------------------------------------------- 9. Índice del libro
    public static function indiceLibro(PDO $pdo, array $ctx, array $f): array
    {
        $filas = [];
        foreach (Informes::roster($pdo, $ctx, $f) as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], self::docTxt($r), (string) $r['grado'], (string) $r['grupo']];
        }
        return ['bloques' => [self::tabla(['Folio', 'Estudiante', 'Documento', 'Grado', 'Grupo'], $filas, 'clllc'), self::nota('El folio corresponde al orden del Libro de Calificaciones generado con el mismo filtro (grado y grupo, en orden alfabético).')]];
    }

    // ---------------------------------------------------------- 10. Consolidado general
    public static function consolidado(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $asigs = [];
        foreach ($m['roster'] as $r) {
            foreach ($m['porGrupo'][(int) $r['grupo_id']] ?? [] as $a) {
                $asigs[(int) $a['id']] = $a;
            }
        }
        $enc = ['N.º', 'Estudiante'];
        foreach ($asigs as $a) {
            $enc[] = Informes::corto((string) $a['asignatura'], 14);
        }
        array_push($enc, 'Promedio', 'Puesto', 'Perdidas');
        $perd = Informes::perdidas($m);
        $filas = [];
        $sumCol = [];
        foreach ($m['roster'] as $i => $r) {
            $e = (int) $r['id'];
            $fila = [(string) ($i + 1), (string) $r['nombre']];
            foreach ($asigs as $aid => $a) {
                $v = (int) $a['grupo_id'] === (int) $r['grupo_id'] ? ($m['val'][$e][$aid] ?? null) : null;
                $fila[] = Informes::nota($ctx, $v);
                if ($v !== null) {
                    $sumCol[$aid][] = $v;
                }
            }
            array_push($fila, Informes::nota($ctx, $m['prom'][$e]), isset($m['puesto'][$e]) ? (string) $m['puesto'][$e] : '—', (string) count($perd[$e] ?? []));
            $filas[] = $fila;
        }
        $pie = ['', 'Promedio del grupo'];
        foreach ($asigs as $aid => $_) {
            $pie[] = isset($sumCol[$aid]) ? Informes::nota($ctx, round(array_sum($sumCol[$aid]) / count($sumCol[$aid]), $m['dec'])) : '—';
        }
        $pp = array_filter($m['prom'], static fn ($v): bool => $v !== null);
        array_push($pie, $pp ? Informes::nota($ctx, round(array_sum($pp) / count($pp), $m['dec'])) : '—', '', '');
        return ['bloques' => [
            self::tabla($enc, $filas, 'll' . str_repeat('c', count($asigs) + 3), null, $pie),
            self::nota('Nota mostrada: ' . ($f['periodo'] > 0 ? (string) $f['txt']['periodo'] : 'definitiva anual') . '. Las celdas en blanco («—») no tienen valoración.'),
        ]];
    }
}
