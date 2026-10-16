<?php
declare(strict_types=1);

require_once __DIR__ . '/GenBase.php';

/** Categoría 4: matrícula, inscripción y admisiones. */
final class GenMatricula extends GenBase
{
    private static function gradoSql(array $f, string $col, array &$p): string
    {
        if ($f['grado'] > 0) {
            $p[] = $f['grado'];
            return " AND {$col} = ?";
        }
        return '';
    }

    // ---------------------------------------------------------- 28. Hoja de matrícula
    public static function hojaMatricula(PDO $pdo, array $ctx, array $f): array
    {
        $roster = self::rosterAcotado($pdo, $ctx, $f, 120);
        $ids = array_column($roster, 'id');
        $in = Informes::in($ids);
        $hv = [];
        foreach ($pdo->query("SELECT * FROM hojas_vida WHERE estudiante_id IN ({$in})")->fetchAll() as $r) {
            $hv[(int) $r['estudiante_id']] = $r;
        }
        $acu = [];
        foreach ($pdo->query("SELECT ae.estudiante_id, ae.parentesco, ae.principal, u.nombre, u.correo, u.telefono FROM acudientes_estudiantes ae
                               INNER JOIN usuarios u ON u.id = ae.acudiente_id WHERE ae.estudiante_id IN ({$in}) ORDER BY ae.principal DESC, u.nombre")->fetchAll() as $r) {
            $acu[(int) $r['estudiante_id']][] = $r;
        }
        $con = [];
        foreach ($pdo->query("SELECT * FROM contactos_emergencia WHERE estudiante_id IN ({$in}) ORDER BY id")->fetchAll() as $r) {
            $con[(int) $r['estudiante_id']][] = $r;
        }
        $mat = [];
        $q = $pdo->prepare("SELECT estudiante_id, tipo, estado, creado_en FROM matriculas WHERE colegio_id = ? AND anio_lectivo_id = ? AND estudiante_id IN ({$in})");
        $q->execute([$ctx['colegioId'], $f['anio']]);
        foreach ($q->fetchAll() as $r) {
            $mat[(int) $r['estudiante_id']] = $r;
        }
        $sens = $ctx['cap']['salud_sensible'];
        $ins = $ctx['ins'];
        $bl = [];
        foreach ($roster as $i => $r) {
            $e = (int) $r['id'];
            $h = $hv[$e] ?? [];
            $m = $mat[$e] ?? null;
            if ($i > 0) {
                $bl[] = self::salto();
            }
            $bl[] = self::banda('HOJA DE MATRÍCULA · AÑO LECTIVO ' . $f['anio_row']['anio']);
            $bl[] = self::pares([
                ['Estudiante', mb_strtoupper((string) $r['nombre'], 'UTF-8')], ['Documento', self::docTxt($r)],
                ['Fecha de nacimiento', Informes::fecha((string) $r['fecha_nacimiento'])], ['Edad', Informes::edad((string) $r['fecha_nacimiento'])],
                ['Género', (string) ($h['genero'] ?? '') ?: '—'], ['Lugar de nacimiento', (string) ($h['lugar_nacimiento'] ?? '') ?: '—'],
                ['Grado', (string) $r['grado']], ['Grupo', (string) $r['grupo'] ?: '—'],
                ['Tipo de matrícula', $m ? ($m['tipo'] === 'renovacion' ? 'Renovación' : 'Nueva') : '—'], ['Fecha de matrícula', $m ? Informes::fecha((string) $m['creado_en']) : '—'],
            ], 'Datos del estudiante');
            $bl[] = self::pares([
                ['Dirección', (string) ($h['direccion'] ?? '') ?: '—'], ['Barrio', (string) ($h['barrio'] ?? '') ?: '—'],
                ['Teléfono', (string) ($r['telefono'] ?? '') ?: '—'], ['Teléfono alterno', (string) ($h['telefono_alterno'] ?? '') ?: '—'],
                ['Correo', (string) $r['correo']], ['Vive con', (string) ($h['vive_con'] ?? '') ?: '—'],
                ['EPS', (string) ($h['eps'] ?? '') ?: '—'], ['Grupo sanguíneo', $sens ? ((string) ($h['grupo_sanguineo'] ?? '') ?: '—') : 'Reservado'],
            ], 'Ubicación y afiliación');
            if ($sens) {
                $bl[] = self::pares([['Alergias', (string) ($h['alergias'] ?? '') ?: 'Ninguna registrada'], ['Condiciones de salud', (string) ($h['condiciones_salud'] ?? '') ?: 'Ninguna registrada'], ['Medicamentos', (string) ($h['medicamentos'] ?? '') ?: 'Ninguno registrado']], 'Salud');
            }
            $filas = [];
            foreach ($acu[$e] ?? [] as $a) {
                $filas[] = [(string) $a['nombre'], (string) $a['parentesco'] . ((int) $a['principal'] === 1 ? ' (principal)' : ''), (string) ($a['telefono'] ?? ''), (string) $a['correo']];
            }
            $bl[] = self::tabla(['Acudiente', 'Parentesco', 'Teléfono', 'Correo'], $filas, 'llll', 'Acudientes');
            $filas = [];
            foreach ($con[$e] ?? [] as $c) {
                $filas[] = [(string) $c['nombre'], (string) $c['parentesco'], (string) $c['telefono'], Informes::si($c['autorizado_recoger'])];
            }
            $bl[] = self::tabla(['Contacto de emergencia', 'Parentesco', 'Teléfono', 'Autorizado a recoger'], $filas, 'lllc', 'Contactos de emergencia');
            $bl[] = self::nota('Con la firma el acudiente certifica que los datos son veraces y acepta el manual de convivencia y el tratamiento de datos personales de ' . ((string) $ins['nombre_oficial'] ?: 'la institución') . '.');
            $bl[] = self::firmas([['', 'Acudiente (firma y documento)'], ['', 'Estudiante'], ['', (string) ($ins['secretaria_cargo'] ?: 'Secretaría académica')]]);
        }
        return ['bloques' => $bl ?: [self::nota('No hay estudiantes para ese filtro.')]];
    }

    // ---------------------------------------------------------- 29. Renovación
    public static function renovacion(PDO $pdo, array $ctx, array $f): array
    {
        $col = $ctx['colegioId'];
        $p = [$col, $f['anio']];
        $w = self::gradoSql($f, 'm.grado_id', $p);
        if ($f['estado'] !== '') {
            $w .= ' AND m.estado = ?';
            $p[] = $f['estado'];
        }
        $q = $pdo->prepare("SELECT u.nombre, gr.nombre AS grado, g.nombre AS grupo, m.estado, m.creado_en, m.resuelto_en, s.nombre AS solicitante
                              FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id INNER JOIN grados gr ON gr.id = m.grado_id
                              LEFT JOIN grupos g ON g.id = m.grupo_id LEFT JOIN usuarios s ON s.id = m.solicitado_por
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.tipo = 'renovacion'{$w} ORDER BY gr.nombre, u.nombre");
        $q->execute($p);
        $filas = [];
        foreach ($q->fetchAll() as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], (string) $r['grado'], (string) ($r['grupo'] ?? ''), ucfirst((string) $r['estado']), Informes::fecha((string) $r['creado_en']), Informes::fecha((string) $r['resuelto_en']), (string) ($r['solicitante'] ?? '')];
        }
        $bl = [self::tabla(['N.º', 'Estudiante', 'Grado', 'Grupo', 'Estado', 'Solicitada', 'Resuelta', 'Solicitada por'], $filas, 'cllccccl', 'Renovaciones del año ' . $f['anio_row']['anio'])];

        $prev = $pdo->prepare('SELECT id, anio FROM anios_lectivos WHERE colegio_id = ? AND anio < ? ORDER BY anio DESC LIMIT 1');
        $prev->execute([$col, $f['anio_row']['anio']]);
        $ant = $prev->fetch();
        if ($ant) {
            $p2 = [$col, (int) $ant['id'], $col, $f['anio']];
            $w2 = self::gradoSql($f, 'm.grado_id', $p2);
            $q = $pdo->prepare("SELECT u.nombre, gr.nombre AS grado, g.nombre AS grupo FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id AND u.activo = 1
                                  INNER JOIN grados gr ON gr.id = m.grado_id LEFT JOIN grupos g ON g.id = m.grupo_id
                                 WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'activa'
                                   AND NOT EXISTS (SELECT 1 FROM matriculas n WHERE n.colegio_id = ? AND n.estudiante_id = m.estudiante_id AND n.anio_lectivo_id = ? AND n.estado IN ('activa','pendiente')){$w2}
                              ORDER BY gr.nombre, u.nombre");
            $q->execute($p2);
            $filas = [];
            foreach ($q->fetchAll() as $i => $r) {
                $filas[] = [(string) ($i + 1), (string) $r['nombre'], (string) $r['grado'], (string) ($r['grupo'] ?? '')];
            }
            $bl[] = self::tabla(['N.º', 'Estudiante', 'Grado ' . $ant['anio'], 'Grupo ' . $ant['anio']], $filas, 'cllc', 'Estudiantes de ' . $ant['anio'] . ' que aún no renuevan ni se matriculan en ' . $f['anio_row']['anio']);
            $bl[] = self::nota('Incluye a quienes terminan el último grado: descártalos al revisar la lista.');
        }
        return ['bloques' => $bl];
    }

    // ---------------------------------------------------------- 30. Reserva de cupo
    public static function reservaCupo(PDO $pdo, array $ctx, array $f): array
    {
        $p = [$ctx['colegioId'], $f['anio']];
        $w = self::gradoSql($f, 'i.grado_id', $p);
        $q = $pdo->prepare("SELECT i.codigo, i.nombres, i.apellidos, i.tipo_documento, i.numero_documento, gr.nombre AS grado, i.acudiente_nombre, i.acudiente_telefono, i.resuelto_en, i.creado_en
                              FROM inscripciones i INNER JOIN grados gr ON gr.id = i.grado_id
                             WHERE i.colegio_id = ? AND i.anio_lectivo_id = ? AND i.estado = 'admitida' AND i.estudiante_id IS NULL{$w} ORDER BY gr.nombre, i.apellidos, i.nombres");
        $q->execute($p);
        $filas = [];
        foreach ($q->fetchAll() as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['codigo'], $r['apellidos'] . ' ' . $r['nombres'], $r['tipo_documento'] . ' ' . $r['numero_documento'], (string) $r['grado'], (string) $r['acudiente_nombre'], (string) $r['acudiente_telefono'], Informes::fecha((string) ($r['resuelto_en'] ?: $r['creado_en']))];
        }
        $bl = [self::tabla(['N.º', 'Código', 'Aspirante', 'Documento', 'Grado', 'Acudiente', 'Teléfono', 'Admitido'], $filas, 'clllllcc', 'Admitidos con cupo reservado (sin matricular)')];
        $p = [$ctx['colegioId'], $f['anio']];
        $w = self::gradoSql($f, 'm.grado_id', $p);
        $q = $pdo->prepare("SELECT u.nombre, gr.nombre AS grado, m.tipo, m.creado_en FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id INNER JOIN grados gr ON gr.id = m.grado_id
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'pendiente'{$w} ORDER BY gr.nombre, u.nombre");
        $q->execute($p);
        $filas = [];
        foreach ($q->fetchAll() as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], (string) $r['grado'], $r['tipo'] === 'renovacion' ? 'Renovación' : 'Nueva', Informes::fecha((string) $r['creado_en'])];
        }
        $bl[] = self::tabla(['N.º', 'Estudiante', 'Grado', 'Tipo', 'Solicitada'], $filas, 'cllcc', 'Matrículas pendientes de aprobación (cupo reservado)');
        return ['bloques' => $bl];
    }

    // ---------------------------------------------------------- 31. Preinscripción
    public static function preinscripcion(PDO $pdo, array $ctx, array $f): array
    {
        $p = [$ctx['colegioId'], $f['anio']];
        $w = self::gradoSql($f, 'i.grado_id', $p);
        if ($f['estado'] !== '') {
            $w .= ' AND i.estado = ?';
            $p[] = $f['estado'];
        }
        if ($f['desde'] !== '') {
            $w .= ' AND DATE(i.creado_en) >= ?';
            $p[] = $f['desde'];
        }
        if ($f['hasta'] !== '') {
            $w .= ' AND DATE(i.creado_en) <= ?';
            $p[] = $f['hasta'];
        }
        $q = $pdo->prepare("SELECT i.*, gr.nombre AS grado FROM inscripciones i INNER JOIN grados gr ON gr.id = i.grado_id
                             WHERE i.colegio_id = ? AND i.anio_lectivo_id = ?{$w} ORDER BY i.creado_en DESC, i.id DESC LIMIT 2000");
        $q->execute($p);
        $filas = [];
        foreach ($q->fetchAll() as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['codigo'], $r['apellidos'] . ' ' . $r['nombres'], $r['tipo_documento'] . ' ' . $r['numero_documento'], (string) $r['grado'], (string) $r['acudiente_nombre'],
                (string) $r['acudiente_telefono'], (string) $r['acudiente_correo'], ucfirst(str_replace('_', ' ', (string) $r['estado'])), Informes::fecha((string) $r['creado_en'])];
        }
        return ['bloques' => [self::tabla(['N.º', 'Código', 'Aspirante', 'Documento', 'Grado', 'Acudiente', 'Teléfono', 'Correo', 'Estado', 'Fecha'], $filas, 'clllllllcc')]];
    }

    // ---------------------------------------------------------- 32. En proceso
    public static function enProceso(PDO $pdo, array $ctx, array $f): array
    {
        $p = [$ctx['colegioId'], $f['anio']];
        $w = self::gradoSql($f, 'i.grado_id', $p);
        $q = $pdo->prepare("SELECT i.codigo, i.nombres, i.apellidos, i.tipo_documento, i.numero_documento, i.estado, gr.nombre AS grado, i.acudiente_nombre, i.acudiente_telefono, i.creado_en, i.nota_resolucion
                              FROM inscripciones i INNER JOIN grados gr ON gr.id = i.grado_id
                             WHERE i.colegio_id = ? AND i.anio_lectivo_id = ? AND i.estado IN ('pendiente','en_revision','admitida'){$w}
                          ORDER BY FIELD(i.estado, 'pendiente', 'en_revision', 'admitida'), gr.nombre, i.apellidos");
        $q->execute($p);
        $filas = [];
        $cuenta = ['pendiente' => 0, 'en_revision' => 0, 'admitida' => 0];
        foreach ($q->fetchAll() as $i => $r) {
            $cuenta[(string) $r['estado']]++;
            $filas[] = [(string) ($i + 1), (string) $r['codigo'], $r['apellidos'] . ' ' . $r['nombres'], $r['tipo_documento'] . ' ' . $r['numero_documento'], (string) $r['grado'], ucfirst(str_replace('_', ' ', (string) $r['estado'])),
                (string) $r['acudiente_nombre'] . ' · ' . $r['acudiente_telefono'], Informes::fecha((string) $r['creado_en'])];
        }
        return ['bloques' => [
            self::pares([['Pendientes', (string) $cuenta['pendiente']], ['En revisión', (string) $cuenta['en_revision']], ['Admitidos sin matricular', (string) $cuenta['admitida']]]),
            self::tabla(['N.º', 'Código', 'Aspirante', 'Documento', 'Grado', 'Estado', 'Acudiente y teléfono', 'Recibida'], $filas, 'clllllll'),
        ]];
    }

    // ---------------------------------------------------------- 33. Nuevos
    public static function nuevos(PDO $pdo, array $ctx, array $f): array
    {
        $p = [$ctx['colegioId'], $f['anio']];
        $w = self::gradoSql($f, 'm.grado_id', $p);
        $q = $pdo->prepare("SELECT u.id, u.nombre, gr.nombre AS grado, g.nombre AS grupo, COALESCE(m.resuelto_en, m.creado_en) AS fecha, i.colegio_anterior
                              FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id INNER JOIN grados gr ON gr.id = m.grado_id
                              LEFT JOIN grupos g ON g.id = m.grupo_id LEFT JOIN inscripciones i ON i.id = m.inscripcion_id
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.tipo = 'nueva' AND m.estado = 'activa'{$w} ORDER BY gr.nombre, g.nombre, u.nombre");
        $q->execute($p);
        $rows = $q->fetchAll();
        $docs = self::documentos($pdo, array_column($rows, 'id'));
        $filas = [];
        foreach ($rows as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], $docs[(int) $r['id']] ?? '—', (string) $r['grado'], (string) ($r['grupo'] ?? ''), Informes::fecha((string) $r['fecha']), (string) ($r['colegio_anterior'] ?? '')];
        }
        return ['bloques' => [self::tabla(['N.º', 'Estudiante', 'Documento', 'Grado', 'Grupo', 'Matriculado', 'Institución de procedencia'], $filas, 'cllcccl'), self::nota(count($filas) . ' estudiantes nuevos.')]];
    }

    // ---------------------------------------------------------- 34. Cancelados
    public static function cancelados(PDO $pdo, array $ctx, array $f): array
    {
        $p = [$ctx['colegioId'], $f['anio']];
        $w = self::gradoSql($f, 'm.grado_id', $p);
        if ($f['desde'] !== '') {
            $w .= ' AND DATE(COALESCE(m.resuelto_en, m.creado_en)) >= ?';
            $p[] = $f['desde'];
        }
        if ($f['hasta'] !== '') {
            $w .= ' AND DATE(COALESCE(m.resuelto_en, m.creado_en)) <= ?';
            $p[] = $f['hasta'];
        }
        $q = $pdo->prepare("SELECT u.id, u.nombre, gr.nombre AS grado, g.nombre AS grupo, COALESCE(m.resuelto_en, m.creado_en) AS fecha, m.observaciones
                              FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id INNER JOIN grados gr ON gr.id = m.grado_id LEFT JOIN grupos g ON g.id = m.grupo_id
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'retirada'{$w} ORDER BY fecha DESC, u.nombre");
        $q->execute($p);
        $rows = $q->fetchAll();
        $docs = self::documentos($pdo, array_column($rows, 'id'));
        $filas = [];
        foreach ($rows as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], $docs[(int) $r['id']] ?? '—', (string) $r['grado'], (string) ($r['grupo'] ?? ''), Informes::fecha((string) $r['fecha']), (string) ($r['observaciones'] ?? '')];
        }
        return ['bloques' => [self::tabla(['N.º', 'Estudiante', 'Documento', 'Grado', 'Grupo', 'Fecha', 'Observación'], $filas, 'cllcccl')]];
    }

    // ---------------------------------------------------------- 35. Índice libro de matrículas
    public static function indiceLibro(PDO $pdo, array $ctx, array $f): array
    {
        $p = [$ctx['colegioId'], $f['anio']];
        $w = self::gradoSql($f, 'm.grado_id', $p);
        $q = $pdo->prepare("SELECT u.id, u.nombre, gr.nombre AS grado, g.nombre AS grupo, m.tipo, m.creado_en
                              FROM matriculas m INNER JOIN usuarios u ON u.id = m.estudiante_id INNER JOIN grados gr ON gr.id = m.grado_id LEFT JOIN grupos g ON g.id = m.grupo_id
                             WHERE m.colegio_id = ? AND m.anio_lectivo_id = ? AND m.estado = 'activa'{$w} ORDER BY m.creado_en, m.id");
        $q->execute($p);
        $rows = $q->fetchAll();
        $docs = self::documentos($pdo, array_column($rows, 'id'));
        $filas = [];
        foreach ($rows as $i => $r) {
            $filas[] = [(string) ($i + 1), (string) $r['nombre'], $docs[(int) $r['id']] ?? '—', (string) $r['grado'], (string) ($r['grupo'] ?? ''), $r['tipo'] === 'renovacion' ? 'Renovación' : 'Nueva', Informes::fecha((string) $r['creado_en'])];
        }
        return ['bloques' => [self::tabla(['Folio', 'Estudiante', 'Documento', 'Grado', 'Grupo', 'Tipo', 'Fecha de matrícula'], $filas, 'cllcccc'), self::nota('Folios asignados por orden cronológico de la matrícula.')]];
    }
}
