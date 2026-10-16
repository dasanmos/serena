<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 10: docentes y asignación académica. */
final class GenDocentes extends GenBase
{
    public static function asignacion(PDO $pdo, array $ctx, array $f): array
    {
        $por = [];
        foreach (Informes::asignaciones($pdo, $ctx, $f['anio']) as $a) {
            if ($f['docente'] > 0 && (int) $a['docente_id'] !== $f['docente']) {
                continue;
            }
            if ($f['grupo'] > 0 && (int) $a['grupo_id'] !== $f['grupo']) {
                continue;
            }
            $por[(int) ($a['docente_id'] ?? 0)][] = $a;
        }
        $bl = [];
        foreach ($por as $lista) {
            $filas = [];
            $h = 0;
            foreach ($lista as $a) {
                $h += (int) ($a['intensidad'] ?? 0);
                $filas[] = [(string) $a['asignatura'], (string) ($a['area'] ?? ''), (string) $a['grado'], (string) $a['grupo'], $a['intensidad'] !== null ? (string) $a['intensidad'] : ''];
            }
            $bl[] = self::banda(($lista[0]['docente'] ?: 'Sin docente asignado'));
            $bl[] = self::tabla(['Asignatura', 'Área', 'Grado', 'Grupo', 'Horas / semana'], $filas, 'llccc', null, ['Total', '', '', '', (string) $h]);
        }
        return ['bloques' => $bl ?: [self::nota('No hay asignaciones académicas para ese filtro.')]];
    }

    public static function listado(PDO $pdo, array $ctx, array $f): array
    {
        $q = $pdo->prepare("SELECT u.nombre, u.correo, u.telefono,
                   (SELECT COUNT(*) FROM asignaciones_academicas a WHERE a.docente_id = u.id AND a.anio_lectivo_id = ?) AS n,
                   (SELECT COALESCE(SUM(s.intensidad), 0) FROM asignaciones_academicas a INNER JOIN asignaturas s ON s.id = a.asignatura_id WHERE a.docente_id = u.id AND a.anio_lectivo_id = ?) AS horas,
                   (SELECT GROUP_CONCAT(CONCAT(gr.nombre, ' ', g.nombre) SEPARATOR ', ') FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE g.docente_id = u.id AND gr.colegio_id = ?) AS dirige
              FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'docente' WHERE u.colegio_id = ? AND u.activo = 1 ORDER BY u.nombre");
        $q->execute([$f['anio'], $f['anio'], $ctx['colegioId'], $ctx['colegioId']]);
        $filas = [];
        foreach ($q->fetchAll() as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], (string) ($r['telefono'] ?? ''), (string) $r['correo'], (string) $r['n'], (string) $r['horas'], (string) ($r['dirige'] ?? '')];
        }
        return ['bloques' => [self::tabla(['N.º', 'Docente', 'Teléfono', 'Correo', 'Asignaciones', 'Horas / semana', 'Director(a) de grupo'], $filas, 'cllllcc')]];
    }
}
