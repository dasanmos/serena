<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Informes.php';

/** Utilidades comunes a todos los generadores de informes (constructores de bloques y consultas repetidas). */
abstract class GenBase
{
    protected static function tabla(array $enc, array $filas, string $alin = '', ?string $titulo = null, ?array $pie = null, ?array $anchos = null): array
    {
        return ['t' => 'tabla', 'titulo' => $titulo, 'enc' => $enc, 'filas' => $filas, 'alin' => $alin, 'pie' => $pie, 'anchos' => $anchos];
    }

    protected static function pares(array $items, ?string $titulo = null): array
    {
        return ['t' => 'pares', 'titulo' => $titulo, 'items' => $items];
    }

    protected static function texto(string $t, ?string $titulo = null): array
    {
        return ['t' => 'texto', 'titulo' => $titulo, 'texto' => $t];
    }

    protected static function nota(string $t): array
    {
        return ['t' => 'nota', 'texto' => $t];
    }

    protected static function banda(string $t): array
    {
        return ['t' => 'banda', 'texto' => $t];
    }

    protected static function salto(): array
    {
        return ['t' => 'salto'];
    }

    protected static function firmas(array $items): array
    {
        return ['t' => 'firmas', 'items' => $items];
    }

    protected static function grupoTxt(array $r): string
    {
        return trim((string) ($r['grado'] ?? '') . ' ' . (string) ($r['grupo'] ?? ''));
    }

    protected static function docTxt(array $r): string
    {
        return ($r['doc'] ?? '') !== '' ? (string) $r['doc'] : '—';
    }

    /** Documentos «TI 123…» de una lista de estudiantes. @return array<int,string> */
    protected static function documentos(PDO $pdo, array $ids): array
    {
        $out = [];
        if ($ids && Informes::tablaExiste($pdo, 'hojas_vida')) {
            $q = $pdo->query('SELECT estudiante_id, tipo_documento, numero_documento FROM hojas_vida WHERE estudiante_id IN (' . Informes::in($ids) . ')');
            foreach ($q->fetchAll() as $r) {
                $d = trim((string) $r['tipo_documento'] . ' ' . (string) $r['numero_documento']);
                if ($d !== '') {
                    $out[(int) $r['estudiante_id']] = $d;
                }
            }
        }
        return $out;
    }

    /** Matriz de notas de cada grupo presente en la lista (para puestos y promedios del grupo aunque se pida un solo estudiante). */
    protected static function matrices(PDO $pdo, array $ctx, array $f, array $roster): array
    {
        $out = [];
        foreach (array_unique(array_column($roster, 'grupo_id')) as $gid) {
            if ((int) $gid > 0) {
                $f2 = $f;
                $f2['estudiante'] = 0;
                $f2['grado'] = 0;
                $f2['grupo'] = (int) $gid;
                $out[(int) $gid] = Informes::matriz($pdo, $ctx, $f2);
            }
        }
        return $out;
    }

    /** Exige que el informe se pida para un grupo o un estudiante y limita el volumen. */
    protected static function rosterAcotado(PDO $pdo, array $ctx, array $f, int $max): array
    {
        if ($f['grupo'] === 0 && $f['estudiante'] === 0) {
            throw new InformeError('Elige un grupo o un estudiante.');
        }
        $r = Informes::roster($pdo, $ctx, $f);
        if (count($r) > $max) {
            throw new InformeError("Este informe admite hasta {$max} estudiantes por vez; elige un grupo más pequeño o un estudiante.");
        }
        return $r;
    }

    /**
     * Inasistencias por estudiante. Cuenta las anotaciones diarias de asistencia (por clase) y, si no se pidió un rango de fechas,
     * suma también el resumen por período que capturan los docentes.
     * @return array<int, array{aus:int, tar:int, exc:int, pre:int, reg:int}>
     */
    protected static function asistencia(PDO $pdo, array $ctx, array $f, array $roster): array
    {
        $out = [];
        foreach ($roster as $r) {
            $out[(int) $r['id']] = ['aus' => 0, 'tar' => 0, 'exc' => 0, 'pre' => 0, 'reg' => 0];
        }
        if (!$out) {
            return $out;
        }
        $ids = Informes::in(array_keys($out));
        $desde = $f['desde'];
        $hasta = $f['hasta'];
        if ($f['periodo'] > 0 && $f['periodo_row']) {
            $desde = $desde !== '' ? $desde : (string) $f['periodo_row']['fecha_inicio'];
            $hasta = $hasta !== '' ? $hasta : (string) $f['periodo_row']['fecha_fin'];
        }
        $sql = "SELECT a.estudiante_id, a.estado, COUNT(*) AS n FROM asistencias a INNER JOIN asignaciones_academicas s ON s.id = a.asignacion_id
                 WHERE s.colegio_id = ? AND s.anio_lectivo_id = ? AND a.estudiante_id IN ({$ids})";
        $p = [$ctx['colegioId'], $f['anio']];
        if ($desde !== '') {
            $sql .= ' AND a.fecha >= ?';
            $p[] = $desde;
        }
        if ($hasta !== '') {
            $sql .= ' AND a.fecha <= ?';
            $p[] = $hasta;
        }
        $q = $pdo->prepare($sql . ' GROUP BY a.estudiante_id, a.estado');
        $q->execute($p);
        $mapa = ['ausente' => 'aus', 'tarde' => 'tar', 'excusa' => 'exc', 'presente' => 'pre'];
        foreach ($q->fetchAll() as $r) {
            $k = $mapa[(string) $r['estado']] ?? null;
            if ($k !== null) {
                $out[(int) $r['estudiante_id']][$k] += (int) $r['n'];
                $out[(int) $r['estudiante_id']]['reg'] += (int) $r['n'];
            }
        }
        if ($f['desde'] === '' && $f['hasta'] === '') {
            $sql2 = "SELECT ip.estudiante_id, SUM(ip.ausencias) AS aus, SUM(ip.excusadas) AS exc FROM inasistencias_periodo ip
                      INNER JOIN asignaciones_academicas s ON s.id = ip.asignacion_id
                      WHERE s.colegio_id = ? AND s.anio_lectivo_id = ? AND ip.estudiante_id IN ({$ids})";
            $p2 = [$ctx['colegioId'], $f['anio']];
            if ($f['periodo'] > 0) {
                $sql2 .= ' AND ip.periodo_id = ?';
                $p2[] = $f['periodo'];
            }
            $q2 = $pdo->prepare($sql2 . ' GROUP BY ip.estudiante_id');
            $q2->execute($p2);
            foreach ($q2->fetchAll() as $r) {
                $out[(int) $r['estudiante_id']]['aus'] += (int) $r['aus'];
                $out[(int) $r['estudiante_id']]['exc'] += (int) $r['exc'];
            }
        }
        return $out;
    }

    /** Tabla de un boletín (resultado de Evaluacion::boletin) en el formato institucional. */
    protected static function tablaBoletin(array $ctx, array $b, bool $conDocente = true, ?string $titulo = null): array
    {
        $esc = $b['escala'];
        $enc = ['Asignatura', 'I.H.'];
        foreach ($b['periodos'] as $p) {
            $enc[] = (string) $p['nombre'];
        }
        $enc[] = 'Definitiva';
        $enc[] = 'Desempeño';
        if ($conDocente) {
            $enc[] = 'Docente';
        }
        $filas = [];
        foreach ($b['filas'] as $f) {
            $r = [(string) $f['asignatura'], $f['intensidad'] !== null ? (string) $f['intensidad'] : ''];
            foreach ($b['periodos'] as $p) {
                $c = $f['periodos'][(int) $p['id']] ?? ['valor' => null, 'nivel' => null];
                $r[] = Evaluacion::textoNota($c['valor'], $c['nivel'], $esc);
            }
            $r[] = Evaluacion::textoNota($f['definitiva'], $f['nivel_definitiva'], $esc);
            $r[] = $f['nivel_definitiva'] ? (string) $f['nivel_definitiva']['nombre'] : '—';
            if ($conDocente) {
                $r[] = (string) ($f['docente'] ?? '');
            }
            $filas[] = $r;
        }
        $pie = null;
        if ($esc['tipo'] !== 'descriptiva' && $b['promedio_final'] !== null) {
            $pie = ['Promedio general', ''];
            foreach ($b['periodos'] as $p) {
                $pie[] = isset($b['promedios'][(int) $p['id']]) ? number_format((float) $b['promedios'][(int) $p['id']], (int) $esc['decimales'], '.', '') : '—';
            }
            $pie[] = number_format((float) $b['promedio_final'], (int) $esc['decimales'], '.', '');
            $pie[] = '';
            if ($conDocente) {
                $pie[] = '';
            }
        }
        $alin = 'lc' . str_repeat('c', count($b['periodos']) + 2) . ($conDocente ? 'l' : '');
        return self::tabla($enc, $filas, $alin, $titulo, $pie);
    }
}
