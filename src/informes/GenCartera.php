<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 12: cartera y estados financieros (datos capturados en public/cartera.php). */
final class GenCartera extends GenBase
{
    /** Cargos con lo pagado. @return array<int,array<int,array<string,mixed>>> por estudiante */
    private static function cargos(PDO $pdo, array $ctx, array $f, array $ids): array
    {
        $out = [];
        if (!$ids) {
            return $out;
        }
        $q = $pdo->prepare('SELECT c.*, COALESCE((SELECT SUM(p.valor) FROM cartera_pagos p WHERE p.cargo_id = c.id), 0) AS pagado FROM cartera_cargos c
                             WHERE c.colegio_id = ? AND c.anio_lectivo_id = ? AND c.estudiante_id IN (' . Informes::in($ids) . ') ORDER BY c.mes IS NULL, c.mes, c.id');
        $q->execute([$ctx['colegioId'], $f['anio']]);
        foreach ($q->fetchAll() as $c) {
            $out[(int) $c['estudiante_id']][] = $c;
        }
        return $out;
    }

    public static function estadosCuenta(PDO $pdo, array $ctx, array $f): array
    {
        $roster = self::rosterAcotado($pdo, $ctx, $f, 60);
        $cg = self::cargos($pdo, $ctx, $f, array_column($roster, 'id'));
        $bl = [];
        foreach ($roster as $i => $r) {
            $e = (int) $r['id'];
            if ($i > 0) {
                $bl[] = self::salto();
            }
            $bl[] = self::banda('ESTADO DE CUENTA · ' . mb_strtoupper((string) $r['nombre'], 'UTF-8') . ' · ' . self::grupoTxt($r));
            $filas = [];
            $tv = 0.0;
            $tp = 0.0;
            foreach ($cg[$e] ?? [] as $c) {
                $tv += (float) $c['valor'];
                $tp += (float) $c['pagado'];
                $filas[] = [(string) $c['concepto'], $c['mes'] ? Informes::MESES[(int) $c['mes']] : '', Informes::fecha((string) $c['vence']), Informes::moneda((float) $c['valor']), Informes::moneda((float) $c['pagado']), Informes::moneda((float) $c['valor'] - (float) $c['pagado'])];
            }
            $bl[] = self::tabla(['Concepto', 'Mes', 'Vence', 'Valor', 'Pagado', 'Saldo'], $filas, 'llcrrr', null, ['Total', '', '', Informes::moneda($tv), Informes::moneda($tp), Informes::moneda($tv - $tp)]);
            $q = $pdo->prepare('SELECT p.fecha, p.recibo, p.metodo, p.valor, c.concepto FROM cartera_pagos p INNER JOIN cartera_cargos c ON c.id = p.cargo_id WHERE p.colegio_id = ? AND p.estudiante_id = ? AND c.anio_lectivo_id = ? ORDER BY p.fecha, p.id');
            $q->execute([$ctx['colegioId'], $e, $f['anio']]);
            $fp = [];
            foreach ($q->fetchAll() as $p) {
                $fp[] = [Informes::fecha((string) $p['fecha']), (string) ($p['recibo'] ?? ''), (string) $p['concepto'], (string) ($p['metodo'] ?? ''), Informes::moneda((float) $p['valor'])];
            }
            $bl[] = self::tabla(['Fecha', 'Recibo', 'Concepto', 'Método', 'Valor'], $fp, 'lllcr', 'Pagos recibidos');
        }
        return ['bloques' => $bl ?: [self::nota('No hay estudiantes para ese filtro.')]];
    }

    public static function saldos(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $cg = self::cargos($pdo, $ctx, $f, array_column($roster, 'id'));
        $lista = [];
        foreach ($roster as $r) {
            $v = 0.0;
            $p = 0.0;
            $n = 0;
            foreach ($cg[(int) $r['id']] ?? [] as $c) {
                if ($f['mes'] > 0 && $c['mes'] !== null && (int) $c['mes'] > $f['mes']) {
                    continue;
                }
                $v += (float) $c['valor'];
                $p += (float) $c['pagado'];
                if ((float) $c['valor'] - (float) $c['pagado'] > 0.009) {
                    $n++;
                }
            }
            if ($v - $p > 0.009) {
                $lista[] = [$r, $n, $v, $p];
            }
        }
        usort($lista, static fn (array $a, array $b): int => ($b[2] - $b[3]) <=> ($a[2] - $a[3]));
        $filas = [];
        $ts = 0.0;
        foreach ($lista as $i => [$r, $n, $v, $p]) {
            $ts += $v - $p;
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r), (string) $n, Informes::moneda($v), Informes::moneda($p), Informes::moneda($v - $p)];
        }
        return ['bloques' => [self::tabla(['N.º', 'Estudiante', 'Grupo', 'Cargos pendientes', 'Facturado', 'Pagado', 'Saldo'], $filas, 'cllcrrr', null, ['', 'Total', '', '', '', '', Informes::moneda($ts)]),
            self::nota($f['mes'] > 0 ? 'Cargos mensuales hasta ' . Informes::MESES[$f['mes']] . ' (incluye los cargos sin mes).' : 'Todos los cargos del año.')]];
    }

    public static function pension(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $cg = self::cargos($pdo, $ctx, $f, array_column($roster, 'id'));
        $enc = ['N.º', 'Estudiante', 'Grupo'];
        for ($m = 1; $m <= 12; $m++) {
            $enc[] = mb_substr(Informes::MESES[$m], 0, 3, 'UTF-8');
        }
        $enc[] = 'Debe';
        $filas = [];
        foreach ($roster as $i => $r) {
            $mes = [];
            $debe = 0.0;
            $hay = false;
            foreach ($cg[(int) $r['id']] ?? [] as $c) {
                if ($c['mes'] !== null && stripos((string) $c['concepto'], 'pensi') === 0) {
                    $hay = true;
                    $s = (float) $c['valor'] - (float) $c['pagado'];
                    $mes[(int) $c['mes']] = $s <= 0.009 ? 'Pag' : ((float) $c['pagado'] > 0 ? 'Par' : 'Pen');
                    $debe += max(0.0, $s);
                }
            }
            if (!$hay) {
                continue;
            }
            $fila = [(string) (count($filas) + 1), (string) $r['nombre'], self::grupoTxt($r)];
            for ($m = 1; $m <= 12; $m++) {
                $fila[] = $mes[$m] ?? '';
            }
            $fila[] = Informes::moneda($debe);
            $filas[] = $fila;
        }
        return ['bloques' => [self::tabla($enc, $filas, 'cll' . str_repeat('c', 12) . 'r'), self::nota('Pag = pagada · Par = pago parcial · Pen = pendiente · en blanco = sin cargo ese mes. Solo cargos cuyo concepto empieza por «Pensión».')]];
    }
}
