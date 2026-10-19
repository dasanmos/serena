<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/RolesUsuario.php';
require_once __DIR__ . '/GenBase.php';

/** Categoría 15: administración, control e información institucional. */
final class GenInstitucional extends GenBase
{
    public static function dane(PDO $pdo, array $ctx, array $f): array
    {
        $i = $ctx['ins'];
        $v = static fn ($x): string => (string) $x !== '' ? (string) $x : '—';
        $roster = Informes::roster($pdo, $ctx, $f);
        $g = [];
        foreach ($roster as $r) {
            $k = (int) $r['grado_id'];
            $g[$k] ??= ['grado' => (string) $r['grado'], 'grupos' => [], 'h' => 0, 'm' => 0, 'o' => 0];
            $g[$k]['grupos'][(int) $r['grupo_id']] = true;
            $s = mb_strtolower(trim((string) $r['genero']), 'UTF-8');
            if ($s !== '' && (str_starts_with($s, 'f') || str_starts_with($s, 'mu'))) {
                $g[$k]['m']++;
            } elseif ($s !== '' && (str_starts_with($s, 'm') || str_starts_with($s, 'h'))) {
                $g[$k]['h']++;
            } else {
                $g[$k]['o']++;
            }
        }
        uasort($g, static fn (array $a, array $b): int => strnatcasecmp($a['grado'], $b['grado']));
        $filas = [];
        $t = [0, 0, 0, 0, 0];
        foreach ($g as $x) {
            $tot = $x['h'] + $x['m'] + $x['o'];
            $row = [count($x['grupos']), $x['h'], $x['m'], $x['o'], $tot];
            foreach ($row as $k => $n) {
                $t[$k] += $n;
            }
            $filas[] = array_merge([$x['grado']], array_map('strval', $row));
        }
        $q = $pdo->prepare("SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE (r.nombre = 'docente'" . RolesUsuario::sqlAdicional($pdo, 'u', 'docente') . ") AND u.colegio_id = ? AND u.activo = 1");
        $q->execute([$ctx['colegioId']]);
        $doc = (int) $q->fetchColumn();
        return ['bloques' => [
            self::pares([['Establecimiento', $v($i['nombre_oficial'])], ['Código DANE', $v($i['codigo_dane'])], ['NIT', $v($i['nit'])], ['Naturaleza', $v($i['naturaleza'])], ['Carácter', $v($i['caracter'])], ['Niveles', $v($i['niveles'])],
                ['Jornadas', $v($i['jornadas'])], ['Calendario', $v($i['calendario'])], ['Resolución', $v($i['resolucion_numero'])], ['Municipio', $v($i['municipio'])], ['Departamento', $v($i['departamento'])], ['Dirección', $v($i['direccion'])],
                ['Teléfono', $v($i['telefono'])], ['Correo', $v($i['correo'])], ['Rector(a)', $v($i['rector_nombre'])], ['Docentes activos', (string) $doc]], 'Identificación del establecimiento'),
            self::tabla(['Grado', 'Grupos', 'Hombres', 'Mujeres', 'Sin dato', 'Total'], $filas, 'lccccc', 'Matrícula por grado y sexo · ' . $f['txt']['anio'], array_merge(['Total'], array_map('strval', $t))),
            self::nota('Resumen de apoyo con los datos del sistema (el sexo sale de la hoja de vida; «Sin dato» no está registrado). El reporte oficial se diligencia en las plataformas del Ministerio (SIMAT / formulario C600 del DANE).'),
        ]];
    }

    public static function auditoria(PDO $pdo, array $ctx, array $f): array
    {
        $sql = 'SELECT a.fecha, u.nombre, a.accion, a.tabla_afectada, a.registro_id, a.datos_nuevos FROM auditoria a INNER JOIN usuarios u ON u.id = a.usuario_id WHERE u.colegio_id = ?';
        $p = [$ctx['colegioId']];
        if ($f['desde'] !== '') {
            $sql .= ' AND a.fecha >= ?';
            $p[] = $f['desde'] . ' 00:00:00';
        }
        if ($f['hasta'] !== '') {
            $sql .= ' AND a.fecha <= ?';
            $p[] = $f['hasta'] . ' 23:59:59';
        }
        $q = $pdo->prepare($sql . ' ORDER BY a.id DESC LIMIT 1000');
        $q->execute($p);
        $filas = [];
        foreach ($q->fetchAll() as $r) {
            $filas[] = [(string) $r['fecha'], (string) $r['nombre'], (string) $r['accion'], (string) $r['tabla_afectada'], $r['registro_id'] !== null ? (string) $r['registro_id'] : '', Informes::corto((string) ($r['datos_nuevos'] ?? ''), 200)];
        }
        return ['bloques' => [self::tabla(['Fecha y hora', 'Usuario', 'Acción', 'Tabla', 'Registro', 'Detalle'], $filas, 'llllcl', null, null, [13, 16, 15, 14, 6, 36]), self::nota('Máximo 1000 registros, los más recientes primero.')]];
    }
}
