<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 7: promoción e historial académico. */
final class GenHistorial extends GenBase
{
    // ---------------------------------------------------------- 44. Cursó y aprobó
    public static function cursoAprobo(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $perd = Informes::perdidas($m);
        $ok = [];
        $no = [];
        $sin = [];
        foreach ($m['roster'] as $r) {
            $e = (int) $r['id'];
            if ($m['prom'][$e] === null) {
                $sin[] = [(string) (count($sin) + 1), (string) $r['nombre'], self::grupoTxt($r)];
            } elseif (empty($perd[$e])) {
                $nv = Informes::nivel($ctx['escala'], $m['prom'][$e]);
                $ok[] = [(string) (count($ok) + 1), (string) $r['nombre'], self::grupoTxt($r), Informes::nota($ctx, $m['prom'][$e]), $nv ? (string) $nv['nombre'] : '—'];
            } else {
                $no[] = [(string) (count($no) + 1), (string) $r['nombre'], self::grupoTxt($r), Informes::nota($ctx, $m['prom'][$e]),
                    implode('; ', array_map(static fn (array $x): string => $x[0]['asignatura'] . ' (' . Informes::nota($ctx, $x[1]) . ')', $perd[$e]))];
            }
        }
        return ['bloques' => [
            self::pares([['Cursaron', (string) (count($ok) + count($no))], ['Aprobaron', (string) count($ok)], ['No aprobaron', (string) count($no)], ['Sin valoración', (string) count($sin)]]),
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Promedio', 'Desempeño'], $ok, 'cllcc', 'Cursaron y aprobaron'),
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Promedio', 'Asignaturas perdidas'], $no, 'cllcl', 'Cursaron y no aprobaron'),
            self::tabla(['N.º', 'Estudiante', 'Grupo'], $sin, 'cll', 'Sin valoraciones registradas'),
            self::nota('«Aprobó» = ninguna asignatura perdida en la definitiva anual. La promoción oficial la decide la Comisión de Evaluación y Promoción.'),
        ]];
    }

    // ---------------------------------------------------------- 45. Evaluación y promoción
    public static function promocion(PDO $pdo, array $ctx, array $f): array
    {
        $m = Informes::matriz($pdo, $ctx, $f);
        $perd = Informes::perdidas($m);
        $lim = $f['umbral'] !== null ? (int) $f['umbral'] : 2;
        $filas = [];
        $c = ['Promovido' => 0, 'Pendiente de recuperación' => 0, 'No promovido (sugerido)' => 0, 'Sin información' => 0];
        foreach ($m['roster'] as $i => $r) {
            $e = (int) $r['id'];
            $n = count($perd[$e] ?? []);
            $d = $m['prom'][$e] === null ? 'Sin información' : ($n === 0 ? 'Promovido' : ($n <= $lim ? 'Pendiente de recuperación' : 'No promovido (sugerido)'));
            $c[$d]++;
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r), Informes::nota($ctx, $m['prom'][$e]), (string) $n,
                implode('; ', array_map(static fn (array $x): string => (string) $x[0]['asignatura'], $perd[$e] ?? [])), $d];
        }
        return ['bloques' => [
            self::pares([['Promovidos', (string) $c['Promovido']], ['Pendientes de recuperación', (string) $c['Pendiente de recuperación']], ['No promovidos (sugerido)', (string) $c['No promovido (sugerido)']], ['Sin información', (string) $c['Sin información']]]),
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Promedio', 'Perdidas', 'Asignaturas perdidas', 'Decisión sugerida'], $filas, 'cllccll', null, null, [4, 22, 8, 7, 7, 32, 16]),
            self::nota("Criterio usado: 0 asignaturas perdidas = promovido; de 1 a {$lim} = pendiente de recuperación; más de {$lim} = no promovido. Es una SUGERENCIA para la Comisión de Evaluación y Promoción, que decide conforme al SIEE de la institución."),
            self::firmas([['', 'Coordinador(a) académico(a)'], ['', 'Rector(a)']]),
        ]];
    }

    /** Años del estudiante con matrícula. @return array<int,array<string,mixed>> */
    private static function aniosDe(PDO $pdo, array $ctx, int $estId): array
    {
        $q = $pdo->prepare('SELECT m.anio_lectivo_id, m.estado, gr.nombre AS grado, g.nombre AS grupo FROM matriculas m INNER JOIN grados gr ON gr.id = m.grado_id LEFT JOIN grupos g ON g.id = m.grupo_id
                             WHERE m.estudiante_id = ? AND m.colegio_id = ? AND m.estado IN (\'activa\',\'retirada\') ORDER BY m.anio_lectivo_id');
        $q->execute([$estId, $ctx['colegioId']]);
        $out = [];
        foreach ($q->fetchAll() as $r) {
            $a = Academico::anio($pdo, (int) $r['anio_lectivo_id'], $ctx['colegioId']);
            if ($a) {
                $out[] = ['anio' => $a, 'm' => $r];
            }
        }
        usort($out, static fn (array $x, array $y): int => (int) $x['anio']['anio'] <=> (int) $y['anio']['anio']);
        return $out;
    }

    private static function perdidasDe(array $ctx, array $b): int
    {
        $n = 0;
        foreach ($b['filas'] as $x) {
            if (Informes::aprueba($ctx['escala'], $x['definitiva']) === false) {
                $n++;
            }
        }
        return $n;
    }

    // ---------------------------------------------------------- 46. Grados anteriores
    public static function gradosAnteriores(PDO $pdo, array $ctx, array $f): array
    {
        if ($ctx['escala'] === null) {
            throw new InformeError('El colegio aún no tiene un sistema de evaluación configurado.');
        }
        $filas = [];
        foreach (self::aniosDe($pdo, $ctx, $f['estudiante']) as $x) {
            $b = Evaluacion::boletin($pdo, $ctx['colegioId'], $f['estudiante'], $x['anio'], false);
            $tiene = $b['promedio_final'] !== null;
            $np = self::perdidasDe($ctx, $b);
            $filas[] = [(string) $x['anio']['anio'], (string) $x['m']['grado'], (string) ($x['m']['grupo'] ?? ''), $x['m']['estado'] === 'retirada' ? 'Retirado' : 'Matriculado', Informes::nota($ctx, $tiene ? (float) $b['promedio_final'] : null),
                $tiene ? (string) $np : '—', !$tiene ? 'Sin valoraciones' : ($np === 0 ? 'Aprobó' : 'Con asignaturas perdidas')];
        }
        return ['bloques' => [self::tabla(['Año', 'Grado', 'Grupo', 'Matrícula', 'Promedio final', 'Asignaturas perdidas', 'Resultado'], $filas, 'ccccccl'),
            self::nota('Se calcula con las valoraciones registradas en el sistema; los años anteriores a su uso no aparecen.')]];
    }

    // ---------------------------------------------------------- 47. Record académico
    public static function record(PDO $pdo, array $ctx, array $f): array
    {
        if ($ctx['escala'] === null) {
            throw new InformeError('El colegio aún no tiene un sistema de evaluación configurado.');
        }
        $bl = [self::pares([['Estudiante', mb_strtoupper((string) $f['txt']['estudiante'], 'UTF-8')], ['Escala de valoración', (string) $ctx['escala']['nombre']]])];
        foreach (self::aniosDe($pdo, $ctx, $f['estudiante']) as $x) {
            $b = Evaluacion::boletin($pdo, $ctx['colegioId'], $f['estudiante'], $x['anio'], false);
            if (!$b['filas']) {
                continue;
            }
            $bl[] = self::banda('Año lectivo ' . $x['anio']['anio'] . ' · Grado ' . $x['m']['grado'] . ((string) ($x['m']['grupo'] ?? '') !== '' ? ' · Grupo ' . $x['m']['grupo'] : ''));
            $bl[] = self::tablaBoletin($ctx, $b, false);
        }
        if (count($bl) === 1) {
            $bl[] = self::nota('El estudiante no tiene valoraciones registradas.');
        }
        $bl[] = self::firmas([['', 'Rector(a)'], ['', 'Secretario(a) académico(a)']]);
        return ['bloques' => $bl];
    }
}
