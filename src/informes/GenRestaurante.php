<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 11: restaurante escolar (datos capturados en public/restaurante.php). */
final class GenRestaurante extends GenBase
{
    private const JORNADAS = ['desayuno' => 'Desayuno', 'almuerzo' => 'Almuerzo', 'refrigerio' => 'Refrigerio'];

    /** Beneficiarios activos del año dentro del alcance. @return array<int,array<string,mixed>> */
    private static function beneficiarios(PDO $pdo, array $ctx, array $f, string $tipo = ''): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $mapa = [];
        foreach ($roster as $r) {
            $mapa[(int) $r['id']] = $r;
        }
        $sql = 'SELECT * FROM restaurante_beneficiarios WHERE colegio_id = ? AND anio_lectivo_id = ? AND activo = 1 AND estudiante_id IN (' . Informes::in(array_keys($mapa)) . ')';
        $p = [$ctx['colegioId'], $f['anio']];
        if ($tipo !== '') {
            $sql .= ' AND tipo = ?';
            $p[] = $tipo;
        }
        $q = $pdo->prepare($sql);
        $q->execute($p);
        $out = [];
        foreach ($q->fetchAll() as $b) {
            $b['est'] = $mapa[(int) $b['estudiante_id']];
            $out[] = $b;
        }
        usort($out, static fn (array $a, array $b): int => strnatcasecmp(self::grupoTxt($a['est']) . $a['est']['nombre'], self::grupoTxt($b['est']) . $b['est']['nombre']));
        return $out;
    }

    public static function listado(PDO $pdo, array $ctx, array $f): array
    {
        $filas = [];
        foreach (self::beneficiarios($pdo, $ctx, $f) as $i => $b) {
            $filas[] = [(string) ($i + 1), (string) $b['est']['nombre'], (string) $b['est']['grado'], (string) $b['est']['grupo'], self::JORNADAS[$b['jornada']] ?? (string) $b['jornada'], ucfirst((string) $b['tipo']), (string) ($b['observacion'] ?? '')];
        }
        return ['bloques' => [self::tabla(['N.º', 'Estudiante', 'Grado', 'Grupo', 'Jornada', 'Tipo', 'Observación'], $filas, 'cllcccl'), self::nota(count($filas) . ' beneficiarios.')]];
    }

    public static function asistencia(PDO $pdo, array $ctx, array $f): array
    {
        $desde = $f['desde'] !== '' ? $f['desde'] : (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $hasta = $f['hasta'] !== '' ? $f['hasta'] : (new DateTimeImmutable('today'))->format('Y-m-d');
        $ben = self::beneficiarios($pdo, $ctx, $f);
        $ids = array_map(static fn (array $b): int => (int) $b['estudiante_id'], $ben);
        $c = [];
        $dia = [];
        if ($ids) {
            $q = $pdo->prepare('SELECT estudiante_id, fecha, asistio FROM restaurante_asistencia WHERE colegio_id = ? AND fecha BETWEEN ? AND ? AND estudiante_id IN (' . Informes::in($ids) . ')');
            $q->execute([$ctx['colegioId'], $desde, $hasta]);
            foreach ($q->fetchAll() as $r) {
                $e = (int) $r['estudiante_id'];
                $c[$e]['reg'] = ($c[$e]['reg'] ?? 0) + 1;
                $c[$e]['ok'] = ($c[$e]['ok'] ?? 0) + (int) $r['asistio'];
                $dia[(string) $r['fecha']]['reg'] = ($dia[(string) $r['fecha']]['reg'] ?? 0) + 1;
                $dia[(string) $r['fecha']]['ok'] = ($dia[(string) $r['fecha']]['ok'] ?? 0) + (int) $r['asistio'];
            }
        }
        $filas = [];
        foreach ($ben as $i => $b) {
            $x = $c[(int) $b['estudiante_id']] ?? ['reg' => 0, 'ok' => 0];
            $filas[] = [(string) ($i + 1), (string) $b['est']['nombre'], self::grupoTxt($b['est']), (string) $x['ok'], (string) ($x['reg'] - $x['ok']), (string) $x['reg'], Informes::pct((float) $x['ok'], (float) $x['reg'])];
        }
        ksort($dia);
        $fd = [];
        foreach (array_slice($dia, 0, 62, true) as $d => $x) {
            $fd[] = [Informes::fecha($d), (string) $x['ok'], (string) ($x['reg'] - $x['ok']), (string) $x['reg']];
        }
        return ['subtitulo' => $f['txt']['anio'] . ' · Del ' . Informes::fecha($desde) . ' al ' . Informes::fecha($hasta), 'bloques' => [
            self::tabla(['N.º', 'Estudiante', 'Grupo', 'Asistió', 'No asistió', 'Días registrados', '% asistencia'], $filas, 'cllcccc'),
            self::tabla(['Fecha', 'Raciones servidas', 'No asistieron', 'Registrados'], $fd, 'lccc', 'Total diario'),
        ]];
    }

    public static function pagos(PDO $pdo, array $ctx, array $f): array
    {
        $ben = self::beneficiarios($pdo, $ctx, $f, 'pago');
        $ids = array_map(static fn (array $b): int => (int) $b['estudiante_id'], $ben);
        $pag = [];
        if ($ids) {
            $q = $pdo->prepare('SELECT estudiante_id, mes, SUM(valor) AS v FROM restaurante_pagos WHERE colegio_id = ? AND anio_lectivo_id = ? AND estudiante_id IN (' . Informes::in($ids) . ') GROUP BY estudiante_id, mes');
            $q->execute([$ctx['colegioId'], $f['anio']]);
            foreach ($q->fetchAll() as $r) {
                $pag[(int) $r['estudiante_id']][(int) $r['mes']] = (float) $r['v'];
            }
        }
        $meses = $f['mes'] > 0 ? [$f['mes']] : range(1, 12);
        $enc = ['N.º', 'Estudiante', 'Grupo'];
        foreach ($meses as $m) {
            $enc[] = mb_substr(Informes::MESES[$m], 0, 3, 'UTF-8');
        }
        $enc[] = 'Total';
        $filas = [];
        $col = array_fill_keys($meses, 0.0);
        foreach ($ben as $i => $b) {
            $fila = [(string) ($i + 1), (string) $b['est']['nombre'], self::grupoTxt($b['est'])];
            $s = 0.0;
            foreach ($meses as $m) {
                $v = $pag[(int) $b['estudiante_id']][$m] ?? 0.0;
                $s += $v;
                $col[$m] += $v;
                $fila[] = $v > 0 ? Informes::moneda($v) : '';
            }
            $fila[] = Informes::moneda($s);
            $filas[] = $fila;
        }
        $pie = ['', 'Total recaudado', ''];
        foreach ($meses as $m) {
            $pie[] = Informes::moneda($col[$m]);
        }
        $pie[] = Informes::moneda(array_sum($col));
        return ['bloques' => [self::tabla($enc, $filas, 'cll' . str_repeat('r', count($meses) + 1), null, $pie), self::nota('Solo beneficiarios de tipo «pago». Las celdas vacías no tienen pago registrado.')]];
    }

    public static function distribucion(PDO $pdo, array $ctx, array $f): array
    {
        $g = [];
        foreach (self::beneficiarios($pdo, $ctx, $f) as $b) {
            $k = (int) $b['est']['grupo_id'];
            $g[$k] ??= ['n' => self::grupoTxt($b['est']), 'gra' => 0, 'pag' => 0, 'des' => 0, 'alm' => 0, 'ref' => 0];
            $g[$k][$b['tipo'] === 'pago' ? 'pag' : 'gra']++;
            $g[$k][['desayuno' => 'des', 'almuerzo' => 'alm', 'refrigerio' => 'ref'][(string) $b['jornada']] ?? 'alm']++;
        }
        uasort($g, static fn (array $a, array $b): int => strnatcasecmp($a['n'], $b['n']));
        $filas = [];
        $t = ['gra' => 0, 'pag' => 0, 'des' => 0, 'alm' => 0, 'ref' => 0];
        foreach ($g as $x) {
            foreach ($t as $k => $_) {
                $t[$k] += $x[$k];
            }
            $filas[] = [$x['n'], (string) ($x['gra'] + $x['pag']), (string) $x['gra'], (string) $x['pag'], (string) $x['des'], (string) $x['alm'], (string) $x['ref']];
        }
        return ['bloques' => [self::tabla(['Grupo', 'Beneficiarios', 'Gratuitos', 'Pago', 'Desayunos', 'Almuerzos', 'Refrigerios'], $filas, 'lcccccc', null, ['Total', (string) ($t['gra'] + $t['pag']), (string) $t['gra'], (string) $t['pag'], (string) $t['des'], (string) $t['alm'], (string) $t['ref']]),
            self::nota('Raciones diarias por servir = desayunos + almuerzos + refrigerios.')]];
    }
}
