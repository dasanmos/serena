<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 9: estudiantes y caracterización poblacional. */
final class GenEstudiantes extends GenBase
{
    public static function listado(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $filas = [];
        foreach ($roster as $i => $r) {
            $filas[] = [(string) ($i + 1), self::docTxt($r), (string) $r['nombre'], (string) $r['grado'], (string) $r['grupo'], Informes::fecha((string) $r['fecha_nacimiento']), Informes::edad((string) $r['fecha_nacimiento']), (string) $r['genero']];
        }
        return ['bloques' => [self::tabla(['N.º', 'Documento', 'Estudiante', 'Grado', 'Grupo', 'Nacimiento', 'Edad', 'Género'], $filas, 'cllccccc'), self::nota(count($roster) . ' estudiantes.')]];
    }

    public static function directorio(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $ids = array_column($roster, 'id');
        $dir = [];
        if ($ids && Informes::tablaExiste($pdo, 'hojas_vida')) {
            foreach ($pdo->query('SELECT estudiante_id, direccion, barrio FROM hojas_vida WHERE estudiante_id IN (' . Informes::in($ids) . ')')->fetchAll() as $r) {
                $dir[(int) $r['estudiante_id']] = trim((string) $r['direccion'] . ((string) $r['barrio'] !== '' ? ' · ' . $r['barrio'] : ''));
            }
        }
        $ac = [];
        if ($ids) {
            foreach ($pdo->query('SELECT ae.estudiante_id, u.nombre, u.telefono FROM acudientes_estudiantes ae INNER JOIN usuarios u ON u.id = ae.acudiente_id WHERE ae.estudiante_id IN (' . Informes::in($ids) . ') ORDER BY ae.principal DESC, u.nombre')->fetchAll() as $r) {
                $ac[(int) $r['estudiante_id']] ??= $r;
            }
        }
        $filas = [];
        foreach ($roster as $i => $r) {
            $e = (int) $r['id'];
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], self::grupoTxt($r), (string) ($r['telefono'] ?? ''), (string) $r['correo'], $dir[$e] ?? '', (string) ($ac[$e]['nombre'] ?? ''), (string) ($ac[$e]['telefono'] ?? '')];
        }
        return ['bloques' => [self::tabla(['N.º', 'Estudiante', 'Grupo', 'Teléfono', 'Correo', 'Dirección', 'Acudiente', 'Tel. acudiente'], $filas, 'cllllllc', null, null, [3, 17, 7, 9, 18, 18, 17, 9]),
            self::nota('Información personal protegida (Ley 1581 de 2012). Uso exclusivamente institucional.')]];
    }

    public static function discapacidad(PDO $pdo, array $ctx, array $f): array
    {
        $cap = $ctx['cap'];
        if (!$cap['ver_apoyo_completo'] && !$cap['ver_apoyo_ajustes']) {
            throw new InformeError('Tu rol no consulta los apoyos pedagógicos.');
        }
        $roster = Informes::roster($pdo, $ctx, $f);
        $mapa = [];
        foreach ($roster as $r) {
            $mapa[(int) $r['id']] = $r;
        }
        $q = $pdo->prepare("SELECT a.*, u.nombre AS responsable FROM apoyos_pedagogicos a LEFT JOIN usuarios u ON u.id = a.responsable_id
                             WHERE a.colegio_id = ? AND a.estado = 'activo' AND a.estudiante_id IN (" . Informes::in(array_keys($mapa)) . ') ORDER BY a.id');
        $q->execute([$ctx['colegioId']]);
        $enc = ['N.º', 'Estudiante', 'Grupo'];
        if ($cap['ver_apoyo_completo']) {
            $enc[] = 'Condición';
            $enc[] = 'Barreras';
        }
        array_push($enc, 'Ajustes razonables', 'PIAR', 'Responsable');
        $filas = [];
        foreach ($q->fetchAll() as $i => $a) {
            $e = $mapa[(int) $a['estudiante_id']];
            $fila = [(string) ($i + 1), (string) $e['nombre'], self::grupoTxt($e)];
            if ($cap['ver_apoyo_completo']) {
                $fila[] = (string) ($a['condicion'] ?? '');
                $fila[] = Informes::corto((string) ($a['barreras'] ?? ''), 200);
            }
            array_push($fila, Informes::corto((string) ($a['ajustes'] ?? ''), 260), Informes::fecha((string) $a['piar_fecha']), (string) ($a['responsable'] ?? ''));
            $filas[] = $fila;
        }
        return ['bloques' => [self::tabla($enc, $filas, 'cll' . str_repeat('l', count($enc) - 3)), self::nota('Datos sensibles (Ley 1581 de 2012; Decreto 1421 de 2017). Su consulta queda registrada en la auditoría.')]];
    }

    public static function victimas(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $mapa = [];
        foreach ($roster as $r) {
            $mapa[(int) $r['id']] = $r;
        }
        $q = $pdo->prepare('SELECT * FROM caracterizacion_estudiante WHERE colegio_id = ? AND victima_conflicto = 1 AND estudiante_id IN (' . Informes::in(array_keys($mapa)) . ')');
        $q->execute([$ctx['colegioId']]);
        $filas = [];
        foreach ($q->fetchAll() as $c) {
            $e = $mapa[(int) $c['estudiante_id']];
            $filas[] = [(string) (count($filas) + 1), (string) $e['nombre'], self::grupoTxt($e), (string) ($c['hecho_victimizante'] ?? ''), (string) ($c['poblacion_etnica'] ?? ''), (string) ($c['sisben'] ?? ''), $c['estrato'] !== null ? (string) $c['estrato'] : '', Informes::corto((string) ($c['observacion'] ?? ''), 200)];
        }
        return ['bloques' => [self::tabla(['N.º', 'Estudiante', 'Grupo', 'Hecho victimizante', 'Población étnica', 'SISBÉN', 'Estrato', 'Observación'], $filas, 'cllllccl'),
            self::nota('Datos sensibles de población vulnerable (Ley 1448 de 2011; Ley 1581 de 2012). Su consulta queda registrada en la auditoría. Se capturan en «Caracterización».')]];
    }

    public static function familias(PDO $pdo, array $ctx, array $f): array
    {
        $roster = Informes::roster($pdo, $ctx, $f);
        $mapa = [];
        foreach ($roster as $r) {
            $mapa[(int) $r['id']] = $r;
        }
        $fam = [];
        if ($mapa) {
            foreach ($pdo->query('SELECT ae.acudiente_id, ae.estudiante_id, ae.parentesco, u.nombre, u.correo, u.telefono FROM acudientes_estudiantes ae INNER JOIN usuarios u ON u.id = ae.acudiente_id
                                   WHERE ae.estudiante_id IN (' . Informes::in(array_keys($mapa)) . ') ORDER BY u.nombre')->fetchAll() as $r) {
                $k = (int) $r['acudiente_id'];
                $fam[$k] ??= ['nombre' => $r['nombre'], 'correo' => $r['correo'], 'tel' => $r['telefono'], 'par' => [], 'hijos' => []];
                $fam[$k]['par'][(string) $r['parentesco']] = true;
                $fam[$k]['hijos'][] = $mapa[(int) $r['estudiante_id']]['nombre'] . ' (' . self::grupoTxt($mapa[(int) $r['estudiante_id']]) . ')';
            }
        }
        $filas = [];
        foreach ($fam as $x) {
            $filas[] = [(string) (count($filas) + 1), (string) $x['nombre'], implode(', ', array_keys($x['par'])), (string) ($x['tel'] ?? ''), (string) $x['correo'], (string) count($x['hijos']), implode('; ', $x['hijos'])];
        }
        return ['bloques' => [self::tabla(['N.º', 'Acudiente', 'Parentesco', 'Teléfono', 'Correo', 'Hijos', 'Estudiantes'], $filas, 'cllllcl'), self::nota(count($filas) . ' familias.')]];
    }
}
