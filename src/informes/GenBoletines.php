<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 3: boletines y certificados (los certificados se emiten desde Certificados::emitir). */
final class GenBoletines extends GenBase
{
    private const MAX = 150;

    /** Datos comunes: estudiantes, matrices por grupo y firmas. */
    private static function contexto(PDO $pdo, array $ctx, array $f): array
    {
        if ($ctx['escala'] === null) {
            throw new InformeError('El colegio aún no tiene un sistema de evaluación configurado.');
        }
        $roster = self::rosterAcotado($pdo, $ctx, $f, self::MAX);
        return [$roster];
    }

    private static function cabecera(array $ctx, array $f, array $r): array
    {
        return self::pares([['Estudiante', mb_strtoupper((string) $r['nombre'], 'UTF-8')], ['Documento', self::docTxt($r)], ['Grado y grupo', self::grupoTxt($r)],
            ['Año lectivo', (string) $f['anio_row']['anio']], ['Escala de valoración', (string) $ctx['escala']['nombre']], ['Boletín', $f['solo_pub'] ? 'Solo períodos publicados' : 'Todos los períodos']]);
    }

    private static function pie(): array
    {
        return self::firmas([['', 'Director(a) de grupo'], ['', 'Rector(a)']]);
    }

    // ---------------------------------------------------------- 20. Boletín informativo
    public static function boletin(PDO $pdo, array $ctx, array $f): array
    {
        [$roster] = self::contexto($pdo, $ctx, $f);
        $bl = [];
        foreach ($roster as $i => $r) {
            if ($i > 0) {
                $bl[] = self::salto();
            }
            $b = Evaluacion::boletin($pdo, $ctx['colegioId'], (int) $r['id'], $f['anio_row'], $f['solo_pub']);
            $bl[] = self::banda('BOLETÍN INFORMATIVO');
            $bl[] = self::cabecera($ctx, $f, $r);
            $bl[] = self::tablaBoletin($ctx, $b, true);
            $bl[] = self::nota(self::leyenda($ctx));
            $bl[] = self::pie();
        }
        return ['bloques' => $bl ?: [self::nota('No hay estudiantes para ese filtro.')]];
    }

    private static function leyenda(array $ctx): string
    {
        $p = [];
        foreach ($ctx['escala']['niveles'] as $n) {
            $p[] = $n['nombre'] . ' (' . $n['abreviatura'] . '): ' . rtrim(rtrim(number_format((float) $n['desde'], 2, '.', ''), '0'), '.') . ' a ' . rtrim(rtrim(number_format((float) $n['hasta'], 2, '.', ''), '0'), '.') . ((int) $n['aprueba'] === 1 ? '' : ' (no aprueba)');
        }
        return 'Escala ' . $ctx['escala']['nombre'] . ' — ' . implode(' · ', $p) . '. I.H. = intensidad horaria semanal.';
    }

    // ---------------------------------------------------------- 21. Con resumen
    public static function boletinResumen(PDO $pdo, array $ctx, array $f): array
    {
        [$roster] = self::contexto($pdo, $ctx, $f);
        $f0 = $f;
        $f0['periodo'] = 0;
        $mats = self::matrices($pdo, $ctx, $f0, $roster);
        $asist = self::asistencia($pdo, $ctx, $f0, $roster);
        $bl = [];
        foreach ($roster as $i => $r) {
            if ($i > 0) {
                $bl[] = self::salto();
            }
            $e = (int) $r['id'];
            $m = $mats[(int) $r['grupo_id']] ?? null;
            $b = Evaluacion::boletin($pdo, $ctx['colegioId'], $e, $f['anio_row'], $f['solo_pub']);
            $perdidas = [];
            if ($m) {
                foreach ($m['porGrupo'][(int) $r['grupo_id']] ?? [] as $a) {
                    $v = $m['val'][$e][(int) $a['id']] ?? null;
                    if (Informes::aprueba($m['escala'], $v) === false) {
                        $perdidas[] = $a['asignatura'] . ' (' . Informes::nota($ctx, $v) . ')';
                    }
                }
            }
            $as = $asist[$e] ?? ['aus' => 0, 'tar' => 0, 'exc' => 0];
            $bl[] = self::banda('BOLETÍN INFORMATIVO CON RESUMEN');
            $bl[] = self::cabecera($ctx, $f, $r);
            $bl[] = self::tablaBoletin($ctx, $b, true);
            $prom = $m['prom'][$e] ?? null;
            $nv = Informes::nivel($ctx['escala'], $prom);
            $bl[] = self::pares([
                ['Promedio general', Informes::nota($ctx, $prom) . ($nv ? ' · ' . $nv['nombre'] : '')],
                ['Puesto en el grupo', isset($m['puesto'][$e]) ? $m['puesto'][$e] . ' de ' . count($m['roster']) : '—'],
                ['Asignaturas perdidas', $perdidas ? (string) count($perdidas) . ': ' . implode('; ', $perdidas) : 'Ninguna'],
                ['Inasistencias', $as['aus'] . ' (excusadas: ' . $as['exc'] . ' · retardos: ' . $as['tar'] . ')'],
            ], 'Resumen del año');
            $bl[] = self::nota(self::leyenda($ctx));
            $bl[] = self::pie();
        }
        return ['bloques' => $bl ?: [self::nota('No hay estudiantes para ese filtro.')]];
    }

    // ---------------------------------------------------------- 22. Por áreas
    public static function boletinAreas(PDO $pdo, array $ctx, array $f): array
    {
        [$roster] = self::contexto($pdo, $ctx, $f);
        $esc = $ctx['escala'];
        $dec = (int) $esc['decimales'];
        $bl = [];
        foreach ($roster as $i => $r) {
            if ($i > 0) {
                $bl[] = self::salto();
            }
            $b = Evaluacion::boletin($pdo, $ctx['colegioId'], (int) $r['id'], $f['anio_row'], $f['solo_pub']);
            $areas = [];
            foreach ($b['filas'] as $x) {
                $k = trim((string) ($x['area'] ?? '')) !== '' ? (string) $x['area'] : 'Sin área definida';
                $areas[$k][] = $x;
            }
            $enc = ['Área / Asignatura', 'I.H.'];
            foreach ($b['periodos'] as $p) {
                $enc[] = (string) $p['nombre'];
            }
            array_push($enc, 'Definitiva', 'Desempeño');
            $filas = [];
            foreach ($areas as $nombre => $items) {
                $fila = [mb_strtoupper($nombre, 'UTF-8'), (string) array_sum(array_map(static fn (array $x): int => (int) ($x['intensidad'] ?? 0), $items)) ?: ''];
                foreach ($b['periodos'] as $p) {
                    $vs = array_filter(array_map(static fn (array $x) => $x['periodos'][(int) $p['id']]['valor'] ?? null, $items), static fn ($v): bool => $v !== null);
                    $fila[] = $vs ? number_format(array_sum($vs) / count($vs), $dec, '.', '') : '—';
                }
                $ds = array_filter(array_map(static fn (array $x) => $x['definitiva'], $items), static fn ($v): bool => $v !== null);
                $dArea = $ds ? round(array_sum($ds) / count($ds), $dec) : null;
                $fila[] = $dArea !== null ? number_format($dArea, $dec, '.', '') : '—';
                $nv = $dArea !== null ? Informes::nivel($esc, $dArea) : null;
                $fila[] = $nv ? (string) $nv['nombre'] : '—';
                $filas[] = $fila;
                foreach ($items as $x) {
                    $sub = ['     ' . $x['asignatura'], $x['intensidad'] !== null ? (string) $x['intensidad'] : ''];
                    foreach ($b['periodos'] as $p) {
                        $c = $x['periodos'][(int) $p['id']] ?? ['valor' => null, 'nivel' => null];
                        $sub[] = Evaluacion::textoNota($c['valor'], $c['nivel'], $esc);
                    }
                    $sub[] = Evaluacion::textoNota($x['definitiva'], $x['nivel_definitiva'], $esc);
                    $sub[] = $x['nivel_definitiva'] ? (string) $x['nivel_definitiva']['nombre'] : '—';
                    $filas[] = $sub;
                }
            }
            $bl[] = self::banda('BOLETÍN INFORMATIVO POR ÁREAS');
            $bl[] = self::cabecera($ctx, $f, $r);
            $bl[] = self::tabla($enc, $filas, 'lc' . str_repeat('c', count($b['periodos']) + 2));
            $bl[] = self::nota('Las filas en mayúscula son el promedio simple del área; debajo aparece cada asignatura. ' . self::leyenda($ctx));
            $bl[] = self::pie();
        }
        return ['bloques' => $bl ?: [self::nota('No hay estudiantes para ese filtro.')]];
    }

    // ---------------------------------------------------------- 27. Sin certificado
    public static function sinCertificado(PDO $pdo, array $ctx, array $f): array
    {
        $tipo = $f['tipo_cert'] !== '' ? $f['tipo_cert'] : 'estudio';
        $roster = Informes::roster($pdo, $ctx, $f);
        $con = [];
        $q = $pdo->prepare("SELECT DISTINCT estudiante_id FROM certificados WHERE colegio_id = ? AND tipo = ? AND estado = 'vigente' AND anio_lectivo_id = ? AND (vence_en IS NULL OR vence_en >= CURDATE())");
        $q->execute([$ctx['colegioId'], $tipo, $f['anio']]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $con[(int) $id] = true;
        }
        $filas = [];
        foreach ($roster as $r) {
            if (!isset($con[(int) $r['id']])) {
                $filas[] = [(string) (count($filas) + 1), (string) $r['nombre'], self::docTxt($r), (string) $r['grado'], (string) $r['grupo']];
            }
        }
        return ['subtitulo' => $f['txt']['anio'] . ' · ' . (Certificados::TIPOS[$tipo] ?? $tipo), 'bloques' => [
            self::tabla(['N.º', 'Estudiante', 'Documento', 'Grado', 'Grupo'], $filas, 'cllcc'),
            self::nota(count($filas) . ' de ' . count($roster) . ' estudiantes sin «' . (Certificados::TIPOS[$tipo] ?? $tipo) . '» vigente en el año.'),
        ]];
    }
}
