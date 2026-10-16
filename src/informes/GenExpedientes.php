<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 8: seguimiento y expediente estudiantil (respeta las capacidades de Expediente::capacidades). */
final class GenExpedientes extends GenBase
{
    // ---------------------------------------------------------- 48. Cuaderno de seguimiento
    public static function cuaderno(PDO $pdo, array $ctx, array $f): array
    {
        $cap = $ctx['cap'];
        if (!$cap['ver_observador']) {
            throw new InformeError('Tu rol no consulta el observador del estudiante.');
        }
        $roster = Informes::roster($pdo, $ctx, $f);
        $mapa = [];
        foreach ($roster as $r) {
            $mapa[(int) $r['id']] = $r;
        }
        $sql = 'SELECT o.estudiante_id, o.fecha, o.tipo, o.descripcion, o.compromiso, a.nombre AS autor FROM observador o LEFT JOIN usuarios a ON a.id = o.autor_id
                 WHERE o.colegio_id = ? AND o.estudiante_id IN (' . Informes::in(array_keys($mapa)) . ')';
        $p = [$ctx['colegioId']];
        if (!$cap['ver_obs_reservado']) {
            $sql .= " AND o.tipo <> 'seguimiento'";
        }
        if ($f['estado'] !== '') {
            $sql .= ' AND o.tipo = ?';
            $p[] = $f['estado'];
        }
        if ($f['desde'] !== '') {
            $sql .= ' AND o.fecha >= ?';
            $p[] = $f['desde'];
        }
        if ($f['hasta'] !== '') {
            $sql .= ' AND o.fecha <= ?';
            $p[] = $f['hasta'];
        }
        $q = $pdo->prepare($sql . ' ORDER BY o.fecha DESC, o.id DESC LIMIT 1500');
        $q->execute($p);
        $filas = [];
        foreach ($q->fetchAll() as $r) {
            $e = $mapa[(int) $r['estudiante_id']];
            $filas[] = [Informes::fecha((string) $r['fecha']), (string) $e['nombre'], self::grupoTxt($e), Expediente::TIPOS_OBSERVADOR[$r['tipo']] ?? (string) $r['tipo'], Informes::corto((string) $r['descripcion'], 400), Informes::corto((string) ($r['compromiso'] ?? ''), 200), (string) ($r['autor'] ?? '')];
        }
        return ['bloques' => [self::tabla(['Fecha', 'Estudiante', 'Grupo', 'Tipo', 'Anotación', 'Compromiso', 'Registró'], $filas, 'lllllll', null, null, [8, 15, 7, 9, 36, 17, 11]),
            self::nota('Documento reservado: contiene información sensible de menores de edad. Su uso es solo institucional.')]];
    }

    private static function acudientes(PDO $pdo, int $est): array
    {
        $q = $pdo->prepare('SELECT u.nombre, u.telefono, u.correo, ae.parentesco, ae.principal FROM acudientes_estudiantes ae INNER JOIN usuarios u ON u.id = ae.acudiente_id WHERE ae.estudiante_id = ? ORDER BY ae.principal DESC, u.nombre');
        $q->execute([$est]);
        return $q->fetchAll();
    }

    // ---------------------------------------------------------- 49. Ficha de seguimiento
    public static function ficha(PDO $pdo, array $ctx, array $f): array
    {
        $cap = $ctx['cap'];
        $roster = self::rosterAcotado($pdo, $ctx, $f, 60);
        $asist = self::asistencia($pdo, $ctx, $f, $roster);
        $bl = [];
        foreach ($roster as $i => $r) {
            $e = (int) $r['id'];
            if ($i > 0) {
                $bl[] = self::salto();
            }
            $bl[] = self::banda('FICHA DE SEGUIMIENTO · ' . mb_strtoupper((string) $r['nombre'], 'UTF-8'));
            $a = $asist[$e];
            $bl[] = self::pares([['Documento', self::docTxt($r)], ['Grado y grupo', self::grupoTxt($r)], ['Edad', Informes::edad((string) $r['fecha_nacimiento'])],
                ['Inasistencias del año', $a['aus'] . ' (excusadas ' . $a['exc'] . ', retardos ' . $a['tar'] . ')']], 'Datos generales');
            $ac = [];
            foreach (self::acudientes($pdo, $e) as $x) {
                $ac[] = [(string) $x['nombre'], (string) $x['parentesco'], (string) ($x['telefono'] ?? ''), (string) $x['correo']];
            }
            $bl[] = self::tabla(['Acudiente', 'Parentesco', 'Teléfono', 'Correo'], $ac, 'llll', 'Familia');
            $obs = Expediente::observaciones($pdo, $e, $cap['ver_obs_reservado'], false);
            $of = [];
            foreach (array_slice($obs, 0, 12) as $o) {
                $of[] = [Informes::fecha((string) $o['fecha']), Expediente::TIPOS_OBSERVADOR[$o['tipo']] ?? (string) $o['tipo'], Informes::corto((string) $o['descripcion'], 300), Informes::corto((string) ($o['compromiso'] ?? ''), 160)];
            }
            $bl[] = self::tabla(['Fecha', 'Tipo', 'Anotación', 'Compromiso'], $of, 'llll', 'Observador (últimas anotaciones)', null, [9, 11, 52, 28]);
            if ($cap['ver_convivencia']) {
                $of = [];
                foreach (Expediente::procesos($pdo, $e) as $x) {
                    $of[] = [Informes::fecha((string) $x['fecha']), 'Tipo ' . $x['situacion_tipo'], Expediente::ESTADOS_PROCESO[$x['estado']] ?? (string) $x['estado'], Informes::corto((string) $x['descripcion'], 240), Informes::corto((string) ($x['decision'] ?? ''), 160)];
                }
                $bl[] = self::tabla(['Fecha', 'Situación', 'Estado', 'Descripción', 'Decisión'], $of, 'lllll', 'Convivencia', null, [9, 9, 12, 40, 30]);
            }
            $th = [];
            foreach (Expediente::totalesHoras($pdo, $e) as $t) {
                $th[] = [(string) $t['nombre'], rtrim(rtrim(number_format((float) $t['cumplidas'], 1, '.', ''), '0'), '.') ?: '0', (string) $t['requeridas']];
            }
            $bl[] = self::tabla(['Concepto', 'Horas cumplidas', 'Horas requeridas'], $th, 'lcc', 'Servicio social y Constitución Política');
            if ($cap['ver_apoyo_completo'] || $cap['ver_apoyo_ajustes']) {
                $ap = Expediente::apoyo($pdo, $e);
                if ($ap) {
                    $items = [['Estado', ucfirst((string) $ap['estado'])]];
                    if ($cap['ver_apoyo_completo']) {
                        $items[] = ['Condición', (string) ($ap['condicion'] ?? '') ?: '—'];
                        $items[] = ['Barreras', Informes::corto((string) ($ap['barreras'] ?? ''), 300) ?: '—'];
                    }
                    $items[] = ['Ajustes razonables', Informes::corto((string) ($ap['ajustes'] ?? ''), 400) ?: '—'];
                    $bl[] = self::pares($items, 'Apoyo pedagógico (PIAR)');
                }
            }
            $bl[] = self::firmas([['', 'Director(a) de grupo'], ['', 'Orientación escolar'], ['', 'Acudiente']]);
        }
        return ['bloques' => $bl ?: [self::nota('No hay estudiantes para ese filtro.')]];
    }

    // ---------------------------------------------------------- 50. Hoja de vida
    public static function hojaVida(PDO $pdo, array $ctx, array $f): array
    {
        $cap = $ctx['cap'];
        $roster = self::rosterAcotado($pdo, $ctx, $f, 60);
        $bl = [];
        foreach ($roster as $i => $r) {
            $e = (int) $r['id'];
            $h = Expediente::hojaVida($pdo, $e) ?? [];
            if ($i > 0) {
                $bl[] = self::salto();
            }
            $v = static fn (string $k): string => (string) ($h[$k] ?? '') !== '' ? (string) $h[$k] : '—';
            $bl[] = self::banda('HOJA DE VIDA · ' . mb_strtoupper((string) $r['nombre'], 'UTF-8'));
            $bl[] = self::pares([['Documento', self::docTxt($r)], ['Fecha de nacimiento', Informes::fecha((string) $r['fecha_nacimiento'])], ['Género', $v('genero')], ['Lugar de nacimiento', $v('lugar_nacimiento')],
                ['Grado y grupo', self::grupoTxt($r)], ['Correo', (string) $r['correo']]], 'Datos personales');
            $bl[] = self::pares([['Dirección', $v('direccion')], ['Barrio', $v('barrio')], ['Teléfono', (string) ($r['telefono'] ?? '') ?: '—'], ['Teléfono alterno', $v('telefono_alterno')], ['Vive con', $v('vive_con')], ['EPS', $v('eps')]], 'Ubicación y familia');
            if ($cap['salud_sensible']) {
                $bl[] = self::pares([['Grupo sanguíneo', $v('grupo_sanguineo')], ['Alergias', $v('alergias')], ['Condiciones de salud', $v('condiciones_salud')], ['Medicamentos', $v('medicamentos')]], 'Salud');
                if ((string) ($h['desarrollo'] ?? '') !== '') {
                    $bl[] = self::texto((string) $h['desarrollo'], 'Desarrollo e historia escolar');
                }
            }
            $ac = [];
            foreach (self::acudientes($pdo, $e) as $x) {
                $ac[] = [(string) $x['nombre'], (string) $x['parentesco'] . ((int) $x['principal'] === 1 ? ' (principal)' : ''), (string) ($x['telefono'] ?? ''), (string) $x['correo']];
            }
            $bl[] = self::tabla(['Acudiente', 'Parentesco', 'Teléfono', 'Correo'], $ac, 'llll', 'Acudientes');
            $ce = [];
            foreach (Expediente::contactos($pdo, $e) as $c) {
                $ce[] = [(string) $c['nombre'], (string) $c['parentesco'], (string) $c['telefono'], Informes::si($c['autorizado_recoger'])];
            }
            $bl[] = self::tabla(['Contacto de emergencia', 'Parentesco', 'Teléfono', 'Autorizado a recoger'], $ce, 'lllc', 'Contactos de emergencia');
            if (!$cap['salud_sensible']) {
                $bl[] = self::nota('Los datos de salud están reservados para orientación y directivos.');
            }
        }
        return ['bloques' => $bl ?: [self::nota('No hay estudiantes para ese filtro.')]];
    }
}
