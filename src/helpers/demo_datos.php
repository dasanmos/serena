<?php
declare(strict_types=1);

/**
 * Datos de demostración de los módulos que antes salían vacíos.
 * Se llama al final de demoSembrar(). Cada bloque es independiente: si a un módulo le falta su migración
 * o falla una consulta, solo ese bloque se omite (queda anotado en el mensaje) y el resto de la demo sigue.
 * Todo es ficticio y vive en el colegio demo; «Restablecer» lo borra y recrea.
 *
 * $c = colegio, anio_id, anio, periodos, grupos[clave=>[id,grado_id]], personal[rol=>id], docente2,
 *      estudiantes[id=>[grupo,nombre,n]], ids[], principal, mateo, asig_ids[], mapa_asig[clave][asignatura_id], hoy
 */
function demoSembrarModulos(PDO $pdo, array $c): string
{
    $resumen = [];
    $tiene = static function (string $tabla) use ($pdo): bool {
        $q = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $q->execute([$tabla]);
        return (bool) $q->fetchColumn();
    };
    $bloque = static function (string $nombre, callable $fn) use (&$resumen): void {
        try {
            $r = $fn();
            if ($r !== '') {
                $resumen[] = $r;
            }
        } catch (Throwable $e) {
            error_log('demoSembrarModulos ' . $nombre . ': ' . $e->getMessage());
            $resumen[] = '(' . $nombre . ' no se pudo sembrar: ' . $e->getMessage() . ')';
        }
    };

    $colegio = (int) $c['colegio'];
    $anioId = (int) $c['anio_id'];
    $ids = $c['ids'];
    $mateo = (int) $c['mateo'];
    $principal = (int) $c['principal'];
    $docente = (int) $c['personal']['docente'];
    $psico = (int) $c['personal']['psicologo'];
    $coord = (int) $c['personal']['coordinador'];
    $secad = (int) $c['personal']['secretaria_academica'];
    $hoy = (string) $c['hoy'];
    $inicioAnio = (string) ($c['anio']['fecha_inicio'] ?? '2026-01-26');
    $docenteDe = static fn (string $clave): int => strpos($clave, 'Sexto') === 0 ? $docente : (int) $c['docente2'];

    /** Últimos $n días hábiles (lunes a viernes) hasta hoy, dentro del año lectivo, del más reciente al más antiguo. */
    $diasHabiles = static function (int $n) use ($hoy, $inicioAnio): array {
        $dias = [];
        for ($d = 0; count($dias) < $n && $d < 40; $d++) {
            $f = date('Y-m-d', strtotime("{$hoy} -{$d} days"));
            if ((int) date('N', strtotime($f)) >= 6 || $f < $inicioAnio) {
                continue;
            }
            $dias[] = $f;
        }
        return $dias;
    };
    $estudiantesDe = static function (string $clave) use ($c): array {
        return array_keys(array_filter($c['estudiantes'], static fn (array $e): bool => $e['grupo'] === $clave));
    };

    // ------------------------------------------------------------------ Institución (perfil oficial)
    $bloque('Institución', function () use ($pdo, $tiene, $colegio, $c): string {
        if (!$tiene('institucion_perfil')) {
            return 'Institución quedó vacía: falta la migración 037 (certificados).';
        }
        $pdo->prepare('INSERT INTO institucion_perfil (colegio_id, nombre_oficial, naturaleza, caracter, niveles, jornadas, calendario, codigo_dane, nit,
                resolucion_numero, resolucion_fecha, resolucion_entidad, direccion, municipio, departamento, telefono, correo, ciudad_expedicion,
                rector_nombre, rector_cargo, secretaria_nombre, secretaria_cargo, dias_vigencia)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE nombre_oficial = VALUES(nombre_oficial), rector_nombre = VALUES(rector_nombre), secretaria_nombre = VALUES(secretaria_nombre)')
            ->execute([$colegio, 'Institución Educativa Demostración SERENA', 'Oficial', 'Académico', 'Básica secundaria y Media', 'Mañana', 'A', DEMO_DANE, '900.000.000-0',
                'Resolución 0123 de 2020 (ficticia)', '2020-03-15', 'Secretaría de Educación de Medellín (ficticia)', 'Calle 1 # 2-34 (ficticia)', 'Medellín', 'Antioquia',
                '604 000 0000', 'demo@' . DEMO_DOMINIO, 'Medellín', 'Hernán Ospina', 'Rector', 'Marta Gómez', 'Secretaria académica', 90]);
        return 'Institución: perfil oficial completo.';
    });

    // ------------------------------------------------------------------ Horarios
    $bloque('Horarios', function () use ($pdo, $tiene, $colegio, $anioId, $c): string {
        if (!$tiene('horario_franjas') || !$tiene('horario_bloques')) {
            return 'Horarios quedó vacío: falta la migración 037 (horarios).';
        }
        $franjas = [[1, '1.ª hora', '06:30', '07:20', 0], [2, '2.ª hora', '07:20', '08:10', 0], [3, '3.ª hora', '08:10', '09:00', 0],
            [4, 'Descanso', '09:00', '09:30', 1], [5, '4.ª hora', '09:30', '10:20', 0], [6, '5.ª hora', '10:20', '11:10', 0], [7, '6.ª hora', '11:10', '12:00', 0]];
        $pdo->prepare('DELETE FROM horario_franjas WHERE colegio_id = ?')->execute([$colegio]);
        $insF = $pdo->prepare('INSERT INTO horario_franjas (colegio_id, numero, nombre, hora_inicio, hora_fin, es_descanso) VALUES (?,?,?,?,?,?)');
        $fid = [];
        foreach ($franjas as [$n, $nom, $i, $f, $d]) {
            $insF->execute([$colegio, $n, $nom, $i . ':00', $f . ':00', $d]);
            $fid[$n] = (int) $pdo->lastInsertId();
        }
        $ensenanza = [1, 2, 3, 5, 6, 7]; // franjas de clase (la 4 es descanso)
        $salones = ['Sexto A' => 'Aula 601', 'Sexto B' => 'Aula 602', 'Décimo A' => 'Aula 1001', 'Décimo B' => 'Aula 1002'];
        $insB = $pdo->prepare('INSERT INTO horario_bloques (colegio_id, anio_lectivo_id, asignacion_id, dia, franja_id, salon) VALUES (?,?,?,?,?,?)');
        $nBloques = 0;
        $gi = 0;
        foreach ($c['grupos'] as $clave => $g) {
            // Grupos A usan las franjas 1-3 y los B las 5-7: el mismo docente (que tiene A y B) nunca queda en dos grupos a la vez.
            $mitad = ($gi % 2) * 3;
            foreach ($c['asig_ids'] as $a => $aid) {
                $asigId = (int) ($c['mapa_asig'][$clave][$aid] ?? 0);
                if ($asigId <= 0) {
                    continue;
                }
                for ($d = 0; $d < 5; $d++) {
                    for ($s = 0; $s < 3; $s++) {
                        if ((($d * 3) + $s) % 5 !== $a) { // 15 celdas por grupo, 3 por asignatura, ninguna repetida
                            continue;
                        }
                        $insB->execute([$colegio, $anioId, $asigId, $d + 1, $fid[$ensenanza[$mitad + $s]], $salones[$clave] ?? null]);
                        $nBloques++;
                    }
                }
            }
            $gi++;
        }
        return "Horarios: 7 franjas y {$nBloques} clases (sin cruces de docente ni de salón).";
    });

    // ------------------------------------------------------------------ Asistencia
    $bloque('Asistencia', function () use ($pdo, $tiene, $colegio, $anioId, $c, $mateo, $diasHabiles, $estudiantesDe, $docenteDe): string {
        if (!$tiene('asistencia_registros')) {
            return 'Asistencia quedó vacía: falta la migración 036 (asistencia).';
        }
        $ins = $pdo->prepare('INSERT INTO asistencia_registros (colegio_id, anio_lectivo_id, asignacion_id, estudiante_id, fecha, estado, observacion, registrado_por) VALUES (?,?,?,?,?,?,?,?)');
        $dias = $diasHabiles(10);
        $n = 0;
        foreach ($c['grupos'] as $clave => $g) {
            foreach ([0, 1] as $pos) { // las dos primeras asignaturas de cada grupo
                $asigId = (int) ($c['mapa_asig'][$clave][$c['asig_ids'][$pos] ?? 0] ?? 0);
                if ($asigId <= 0) {
                    continue;
                }
                foreach ($dias as $di => $fecha) {
                    foreach ($estudiantesDe($clave) as $eid) {
                        $estado = 'presente';
                        $obs = null;
                        if ($eid === $mateo && in_array($di, [1, 2, 3, 5], true)) {
                            $estado = 'ausente'; // 4 días sin justificar: dispara la alerta de ausentismo del reporte
                        } else {
                            $r = mt_rand(1, 100);
                            if ($r <= 5) { $estado = 'tarde'; }
                            elseif ($r <= 8) { $estado = 'excusa'; $obs = 'Cita médica'; }
                            elseif ($r <= 10) { $estado = 'ausente'; }
                            elseif ($r <= 11) { $estado = 'permiso'; $obs = 'Permiso de coordinación'; }
                        }
                        $ins->execute([$colegio, $anioId, $asigId, $eid, $fecha, $estado, $obs, $docenteDe($clave)]);
                        $n++;
                    }
                }
            }
        }
        return "Asistencia: {$n} registros de los últimos 10 días hábiles (Mateo con 4 ausencias sin justificar).";
    });

    // ------------------------------------------------------------------ Hoja de vida ampliada
    $bloque('Hoja de vida', function () use ($pdo, $tiene, $ids, $principal, $mateo, $c): string {
        if (!$tiene('hojas_vida')) {
            return '';
        }
        $barrios = ['Laureles', 'Belén', 'Robledo', 'Castilla', 'Manrique', 'Buenos Aires', 'Aranjuez', 'Guayabal'];
        $eps = ['Sura', 'Nueva EPS', 'Sanitas', 'Salud Total'];
        $viveCon = ['Madre y hermanos', 'Padres', 'Abuela y madre', 'Padre'];
        $sangre = ['O+', 'A+', 'B+', 'O-', 'AB+'];
        $up = $pdo->prepare('UPDATE hojas_vida SET direccion = ?, barrio = ?, telefono_alterno = ?, vive_con = ?, eps = ?, grupo_sanguineo = ? WHERE estudiante_id = ?');
        foreach ($ids as $k => $eid) {
            $up->execute(['Carrera ' . (30 + $k) . ' # ' . (40 + $k) . '-' . (10 + $k) . ' (ficticia)', $barrios[$k % 8], '300 000 ' . sprintf('%04d', 1000 + $k),
                $viveCon[$k % 4], $eps[$k % 4], $sangre[$k % 5], $eid]);
        }
        if ($tiene('contactos_emergencia')) {
            $ce = $pdo->prepare('INSERT INTO contactos_emergencia (estudiante_id, nombre, parentesco, telefono, autorizado_recoger) VALUES (?,?,?,?,?)');
            $ce->execute([$principal, 'Luz Marina Pérez', 'Madre', '300 111 2233', 1]);
            $ce->execute([$principal, 'Carlos Rojas', 'Tío', '301 222 3344', 1]);
            $ce->execute([$mateo, 'Rosa Londoño', 'Abuela', '302 333 4455', 1]);
        }
        return 'Hoja de vida: datos de contacto, salud básica y contactos de emergencia.';
    });

    // ------------------------------------------------------------------ Observador y convivencia
    $bloque('Observador y convivencia', function () use ($pdo, $tiene, $colegio, $ids, $mateo, $principal, $docente, $coord): string {
        $partes = [];
        if ($tiene('observador')) {
            $ins = $pdo->prepare('INSERT INTO observador (colegio_id, estudiante_id, autor_id, fecha, tipo, descripcion, compromiso, visible_familia) VALUES (?,?,?,?,?,?,?,?)');
            $obs = [
                [$principal, $docente, '-12 days', 'anecdota', 'Lideró con entusiasmo la presentación del proyecto de ciencias.', null, 1],
                [$mateo, $docente, '-9 days', 'academica', 'Ha entregado tarde los últimos tres talleres de Matemáticas.', 'Entregar los talleres pendientes antes del viernes.', 1],
                [$mateo, $coord, '-6 days', 'convivencia', 'Se mostró retraído en el descanso y evitó a sus compañeros.', 'Conversación con orientación esta semana.', 1],
                [$ids[3], $docente, '-4 days', 'seguimiento', 'Mejoró su participación en clase tras el acuerdo con la familia.', null, 1],
                [$ids[14], $coord, '-3 days', 'convivencia', 'Resolvió pacíficamente un desacuerdo con un compañero.', null, 1],
            ];
            foreach ($obs as [$eid, $autor, $f, $tipo, $desc, $comp, $vis]) {
                $ins->execute([$colegio, $eid, $autor, date('Y-m-d', strtotime($f)), $tipo, $desc, $comp, $vis]);
            }
            $partes[] = '5 anotaciones del observador';
        }
        if ($tiene('procesos_disciplinarios') && $tiene('actuaciones_disciplinarias')) {
            $ip = $pdo->prepare('INSERT INTO procesos_disciplinarios (colegio_id, estudiante_id, abierto_por, fecha, situacion_tipo, descripcion, estado, decision, cerrado_en, cerrado_por) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $ia = $pdo->prepare('INSERT INTO actuaciones_disciplinarias (proceso_id, autor_id, fecha, tipo, detalle) VALUES (?,?,?,?,?)');
            $ip->execute([$colegio, $ids[13], $coord, date('Y-m-d', strtotime('-14 days')), 'I', 'Llegadas tarde reiteradas y uso del celular en clase.', 'seguimiento', null, null, null]);
            $p1 = (int) $pdo->lastInsertId();
            $ia->execute([$p1, $coord, date('Y-m-d', strtotime('-13 days')), 'otra', 'Diálogo con el estudiante y compromiso verbal de puntualidad.']);
            $ia->execute([$p1, $coord, date('Y-m-d', strtotime('-7 days')), 'otra', 'Citación a la acudiente; se firmó compromiso escrito.']);
            $ip->execute([$colegio, $ids[16], $coord, date('Y-m-d', strtotime('-35 days')), 'I', 'Desacuerdo verbal con un compañero durante el descanso.', 'cerrado', 'Reconciliación y compromiso de respeto mutuo.', date('Y-m-d H:i:s', strtotime('-30 days')), $coord]);
            $p2 = (int) $pdo->lastInsertId();
            $ia->execute([$p2, $coord, date('Y-m-d', strtotime('-34 days')), 'otra', 'Espacio de mediación entre los estudiantes.']);
            $partes[] = '2 procesos de convivencia (1 en seguimiento, 1 cerrado)';
        }
        return $partes ? 'Convivencia: ' . implode(' y ', $partes) . '.' : 'Observador y convivencia quedaron vacíos: falta la migración 036 (expediente).';
    });

    // ------------------------------------------------------------------ Horas formativas y apoyos pedagógicos
    $bloque('Horas formativas y apoyos', function () use ($pdo, $tiene, $colegio, $ids, $docente, $psico): string {
        $partes = [];
        if ($tiene('horas_formativas')) {
            $ins = $pdo->prepare('INSERT INTO horas_formativas (colegio_id, estudiante_id, tipo, fecha, horas, actividad, entidad, registrado_por) VALUES (?,?,?,?,?,?,?,?)');
            foreach (array_slice($ids, 12, 8) as $k => $eid) {
                $ins->execute([$colegio, $eid, 'servicio_social', date('Y-m-d', strtotime('-' . (10 + $k * 7) . ' days')), 20 + ($k % 3) * 10, 'Apoyo en la biblioteca escolar', 'Biblioteca del colegio', $docente]);
                if ($k % 2 === 0) {
                    $ins->execute([$colegio, $eid, 'constitucion', date('Y-m-d', strtotime('-' . (20 + $k * 5) . ' days')), 10, 'Curso de Constitución Política', 'Personería de Medellín (ficticia)', $docente]);
                }
            }
            $partes[] = 'horas de servicio social y constitución en Décimo';
        }
        if ($tiene('apoyos_pedagogicos')) {
            $pdo->prepare('INSERT INTO apoyos_pedagogicos (colegio_id, estudiante_id, estado, condicion, barreras, ajustes, estrategias, responsable_id, piar_fecha) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$colegio, $ids[9], 'activo', 'Dificultad de aprendizaje en lectoescritura (ficticia)', 'Lectura lenta; se cansa en textos largos.',
                    'Más tiempo en evaluaciones escritas; textos con letra grande.', 'Lectura guiada en parejas y apoyo de orientación dos veces por semana.', $psico, date('Y-m-d', strtotime('-60 days'))]);
            $partes[] = '1 apoyo pedagógico (PIAR)';
        }
        return $partes ? 'Expediente: ' . implode(' y ', $partes) . '.' : '';
    });

    // ------------------------------------------------------------------ Refuerzos y planes de mejoramiento
    $bloque('Refuerzos y planes', function () use ($pdo, $tiene, $colegio, $anioId, $c, $mateo, $ids, $docente): string {
        if (!$tiene('refuerzos') || !$tiene('planes_mejoramiento')) {
            return 'Refuerzos quedó vacío: falta la migración 040 (informes).';
        }
        $periodoId = (int) ($c['periodos'][0]['id'] ?? 0);
        $mat = $c['asig_ids'][0] ?? 0; // Matemáticas
        $asigDe = static fn (int $eid): int => (int) ($c['mapa_asig'][$c['estudiantes'][$eid]['grupo']][$mat] ?? 0);
        if ($periodoId <= 0) {
            return '';
        }
        $ir = $pdo->prepare('INSERT INTO refuerzos (colegio_id, anio_lectivo_id, periodo_id, asignacion_id, estudiante_id, nota_original, fecha_limite, estado, nota_refuerzo, fecha_presentacion, observacion, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $ir->execute([$colegio, $anioId, $periodoId, $asigDe($mateo), $mateo, 2.4, date('Y-m-d', strtotime('+7 days')), 'pendiente', null, null, 'Taller de recuperación de fracciones y proporciones.', $docente]);
        $ir->execute([$colegio, $anioId, $periodoId, $asigDe($ids[2]), $ids[2], 2.8, date('Y-m-d', strtotime('-5 days')), 'aprobado', 3.6, date('Y-m-d', strtotime('-6 days')), 'Superó el refuerzo con buen desempeño.', $docente]);
        $ir->execute([$colegio, $anioId, $periodoId, $asigDe($ids[5]), $ids[5], 2.7, date('Y-m-d', strtotime('+2 days')), 'presentado', null, date('Y-m-d', strtotime('-1 day')), 'Presentó el refuerzo; pendiente de calificar.', $docente]);
        $pdo->prepare('INSERT INTO planes_mejoramiento (colegio_id, anio_lectivo_id, periodo_id, estudiante_id, asignacion_id, estado, fecha_inicio, nota_inicial, compromisos, responsable_id) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$colegio, $anioId, $periodoId, $mateo, $asigDe($mateo), 'activo', date('Y-m-d', strtotime('-10 days')), 2.4,
                'Asistir a las tutorías de los martes, entregar los talleres pendientes y presentar el refuerzo antes de la fecha límite.', $docente]);
        return 'Refuerzos: 3 refuerzos (pendiente, presentado, aprobado) y 1 plan de mejoramiento.';
    });

    // ------------------------------------------------------------------ Caracterización
    $bloque('Caracterización', function () use ($pdo, $tiene, $colegio, $ids, $secad): string {
        if (!$tiene('caracterizacion_estudiante')) {
            return 'Caracterización quedó vacía: falta la migración 040 (informes).';
        }
        $ins = $pdo->prepare('INSERT INTO caracterizacion_estudiante (estudiante_id, colegio_id, victima_conflicto, hecho_victimizante, poblacion_etnica, sisben, estrato, observacion, actualizado_por) VALUES (?,?,?,?,?,?,?,?,?)');
        $filas = [
            [$ids[0], 0, null, null, 'B2', 3, null], [$ids[1], 0, null, null, 'C5', 2, null], [$ids[2], 1, 'Desplazamiento forzado (ficticio)', null, 'A3', 2, 'Familia en proceso de reparación.'],
            [$ids[5], 0, null, 'Afrocolombiano', 'B1', 2, null], [$ids[9], 0, null, null, 'C2', 3, null], [$ids[14], 0, null, 'Indígena', 'B4', 1, 'Pertenece a un cabildo urbano.'],
        ];
        foreach ($filas as [$eid, $v, $hecho, $etnia, $sisben, $estrato, $obs]) {
            $ins->execute([$eid, $colegio, $v, $hecho, $etnia, $sisben, $estrato, $obs, $secad]);
        }
        return 'Caracterización: 6 estudiantes con datos ficticios.';
    });

    // ------------------------------------------------------------------ Restaurante escolar
    $bloque('Restaurante', function () use ($pdo, $tiene, $colegio, $anioId, $ids, $secad, $diasHabiles, $hoy): string {
        if (!$tiene('restaurante_beneficiarios') || !$tiene('restaurante_asistencia') || !$tiene('restaurante_pagos')) {
            return 'Restaurante quedó vacío: falta la migración 040 (informes).';
        }
        $ib = $pdo->prepare('INSERT INTO restaurante_beneficiarios (colegio_id, anio_lectivo_id, estudiante_id, jornada, tipo, activo) VALUES (?,?,?,?,?,1)');
        $ia = $pdo->prepare('INSERT INTO restaurante_asistencia (colegio_id, fecha, estudiante_id, asistio, registrado_por) VALUES (?,?,?,?,?)');
        $ip = $pdo->prepare('INSERT INTO restaurante_pagos (colegio_id, anio_lectivo_id, estudiante_id, mes, valor, fecha_pago, metodo, recibo, registrado_por) VALUES (?,?,?,?,?,?,?,?,?)');
        $dias = $diasHabiles(5);
        $mes = (int) date('n', strtotime($hoy));
        $n = 0;
        foreach (array_slice($ids, 0, 10) as $k => $eid) {
            $tipo = $k < 7 ? 'gratuito' : 'pago';
            $ib->execute([$colegio, $anioId, $eid, 'almuerzo', $tipo]);
            foreach ($dias as $fecha) {
                $ia->execute([$colegio, $fecha, $eid, mt_rand(1, 10) <= 9 ? 1 : 0, $secad]);
            }
            if ($tipo === 'pago') {
                $ip->execute([$colegio, $anioId, $eid, $mes, 45000, date('Y-m-d', strtotime($hoy . ' -3 days')), 'efectivo', 'R-' . sprintf('%04d', 100 + $k), $secad]);
            }
            $n++;
        }
        return "Restaurante: {$n} beneficiarios, asistencia de la última semana y pagos del mes.";
    });

    // ------------------------------------------------------------------ Cartera
    $bloque('Cartera', function () use ($pdo, $tiene, $colegio, $anioId, $ids, $secad, $hoy): string {
        if (!$tiene('cartera_cargos') || !$tiene('cartera_pagos')) {
            return 'Cartera quedó vacía: falta la migración 040 (informes).';
        }
        $ic = $pdo->prepare('INSERT INTO cartera_cargos (colegio_id, anio_lectivo_id, estudiante_id, concepto, mes, valor, vence, creado_por) VALUES (?,?,?,?,?,?,?,?)');
        $ip = $pdo->prepare('INSERT INTO cartera_pagos (colegio_id, cargo_id, estudiante_id, valor, fecha, metodo, recibo, registrado_por) VALUES (?,?,?,?,?,?,?,?)');
        $mes = (int) date('n', strtotime($hoy));
        $n = 0;
        foreach (array_slice($ids, 0, 8) as $k => $eid) {
            $ic->execute([$colegio, $anioId, $eid, 'Matrícula', null, 150000, date('Y-m-d', strtotime($hoy . ' -60 days')), $secad]);
            $matricula = (int) $pdo->lastInsertId();
            $ic->execute([$colegio, $anioId, $eid, 'Transporte', $mes, 60000, date('Y-m-d', strtotime($hoy . ' +5 days')), $secad]);
            $transporte = (int) $pdo->lastInsertId();
            if ($k % 4 !== 3) { // 6 de 8 pagaron la matrícula completa; los otros quedan con saldo
                $ip->execute([$colegio, $matricula, $eid, 150000, date('Y-m-d', strtotime($hoy . ' -55 days')), 'transferencia', 'R-' . sprintf('%04d', 200 + $k), $secad]);
            } else {
                $ip->execute([$colegio, $matricula, $eid, 70000, date('Y-m-d', strtotime($hoy . ' -50 days')), 'efectivo', 'R-' . sprintf('%04d', 200 + $k), $secad]);
            }
            if ($k % 2 === 0) {
                $ip->execute([$colegio, $transporte, $eid, 60000, date('Y-m-d', strtotime($hoy . ' -2 days')), 'efectivo', 'R-' . sprintf('%04d', 300 + $k), $secad]);
            }
            $n++;
        }
        return "Cartera: cargos de matrícula y transporte para {$n} estudiantes, con pagos completos y parciales.";
    });

    // ------------------------------------------------------------------ Pulso de grupo
    $bloque('Pulso de grupo', function () use ($pdo, $tiene, $c, $docente, $estudiantesDe): string {
        if (!$tiene('pulsos_grupo') || !$tiene('pulso_respuestas')) {
            return '';
        }
        $pdo->prepare('INSERT INTO pulsos_grupo (docente_id, grupo_id, pregunta, fecha_cierre) VALUES (?,?,?,?)')
            ->execute([$docente, $c['grupos']['Sexto A']['id'], '¿Cómo te sentiste en la clase de hoy?', date('Y-m-d H:i:s', strtotime('+3 days'))]);
        $pulso = (int) $pdo->lastInsertId();
        $ir = $pdo->prepare('INSERT INTO pulso_respuestas (pulso_id, estudiante_id, valor) VALUES (?,?,?)');
        foreach ($estudiantesDe('Sexto A') as $k => $eid) {
            $ir->execute([$pulso, $eid, [5, 4, 4, 3, 2, 4][$k % 6]]);
        }
        return 'Pulso: una pregunta abierta en Sexto A con 6 respuestas.';
    });

    // ------------------------------------------------------------------ Encuesta de clima escolar
    $bloque('Encuestas', function () use ($pdo, $tiene, $c, $psico, $estudiantesDe): string {
        if (!$tiene('encuestas') || !$tiene('preguntas_encuesta') || !$tiene('opciones_pregunta') || !$tiene('respuestas_encuesta')) {
            return '';
        }
        // colegio_id es obligatorio desde la migración multicolegio: sin él, la demo quedaba sin encuestas.
        $pdo->prepare('INSERT INTO encuestas (colegio_id, creador_id, titulo, descripcion, grupo_id, fecha_cierre) VALUES (?,?,?,?,?,?)')
            ->execute([(int) $c['colegio'], $psico, 'Clima escolar y bienestar', 'Cuéntanos cómo te sientes en el colegio. Tus respuestas ayudan a mejorar.', $c['grupos']['Sexto A']['id'], date('Y-m-d H:i:s', strtotime('+10 days'))]);
        $enc = (int) $pdo->lastInsertId();
        $ipq = $pdo->prepare('INSERT INTO preguntas_encuesta (encuesta_id, texto, tipo) VALUES (?,?,?)');
        $ipq->execute([$enc, '¿Qué tan seguro te sientes en el colegio? (1 a 10)', 'escala']);
        $qEscala = (int) $pdo->lastInsertId();
        $ipq->execute([$enc, '¿Con quién hablas cuando tienes un problema?', 'opcion_multiple']);
        $qOpc = (int) $pdo->lastInsertId();
        $ipq->execute([$enc, '¿Qué cambiarías del colegio?', 'texto_libre']);
        $qTexto = (int) $pdo->lastInsertId();
        $io = $pdo->prepare('INSERT INTO opciones_pregunta (pregunta_id, texto) VALUES (?,?)');
        $opciones = [];
        foreach (['Con mis amigos', 'Con mi familia', 'Con un docente', 'Con nadie'] as $t) {
            $io->execute([$qOpc, $t]);
            $opciones[] = (int) $pdo->lastInsertId();
        }
        $ir = $pdo->prepare('INSERT INTO respuestas_encuesta (pregunta_id, usuario_id, opcion_id, respuesta_texto) VALUES (?,?,?,?)');
        $textos = ['Más espacios para jugar.', 'Que haya más actividades de arte.', 'Nada, me gusta mucho.', 'Más tiempo de descanso.', 'Una cancha nueva.'];
        foreach (array_slice($estudiantesDe('Sexto A'), 0, 5) as $k => $eid) { // 5 de 6 respondieron
            $ir->execute([$qEscala, $eid, null, (string) [9, 8, 7, 9, 5][$k]]);
            $ir->execute([$qOpc, $eid, $opciones[[0, 1, 0, 2, 3][$k]], null]);
            $ir->execute([$qTexto, $eid, null, $textos[$k]]);
        }
        return 'Encuestas: «Clima escolar y bienestar» con 5 respuestas de Sexto A.';
    });

    // ------------------------------------------------------------------ Inscripciones
    $bloque('Inscripciones', function () use ($pdo, $tiene, $colegio, $anioId, $c, $secad): string {
        if (!$tiene('inscripciones')) {
            return '';
        }
        $ins = $pdo->prepare('INSERT INTO inscripciones (colegio_id, anio_lectivo_id, grado_id, codigo, estado, nombres, apellidos, tipo_documento, numero_documento, fecha_nacimiento,
                correo_estudiante, direccion, colegio_anterior, acudiente_nombre, acudiente_parentesco, acudiente_documento, acudiente_correo, acudiente_telefono, observaciones,
                consentimiento, consentimiento_en, nota_resolucion, resuelto_por, resuelto_en)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),?,?,?)');
        $sexto = (int) $c['grupos']['Sexto A']['grado_id'];
        $decimo = (int) $c['grupos']['Décimo A']['grado_id'];
        $filas = [
            [$sexto, 'DEMO00000001', 'pendiente', 'Lucía', 'Herrera Soto', '2014-04-12', 'Escuela Santa Rosa', 'Patricia Soto', 'Madre', 'patricia@' . DEMO_DOMINIO, null, null],
            [$sexto, 'DEMO00000002', 'pendiente', 'Andrés', 'Cuartas Mejía', '2014-09-03', 'Escuela El Rosal', 'Jorge Cuartas', 'Padre', 'jorge@' . DEMO_DOMINIO, null, null],
            [$decimo, 'DEMO00000003', 'en_revision', 'Karla', 'Pineda Ríos', '2010-01-27', 'I.E. San Juan', 'Marta Ríos', 'Madre', 'marta@' . DEMO_DOMINIO, 'Falta el certificado de notas del año anterior.', null],
            [$sexto, 'DEMO00000004', 'admitida', 'Samuel', 'Ortiz Vélez', '2014-06-18', 'Escuela La Esperanza', 'Elena Vélez', 'Madre', 'elena@' . DEMO_DOMINIO, 'Documentos completos; cupo asignado.', $secad],
        ];
        foreach ($filas as $k => [$grado, $codigo, $estado, $nom, $ape, $nac, $anterior, $acu, $par, $correo, $nota, $resuelve]) {
            $ins->execute([$colegio, $anioId, $grado, $codigo, $estado, $nom, $ape, 'TI', (string) (1100000000 + $k * 9137 + 77), $nac, null, 'Calle ' . (10 + $k) . ' # 20-30 (ficticia)', $anterior,
                $acu, $par, (string) (43000000 + $k * 811), $correo, '310 000 ' . sprintf('%04d', 2000 + $k), null, $nota, $resuelve, $resuelve ? date('Y-m-d H:i:s', strtotime('-1 day')) : null]);
        }
        return 'Inscripciones: 4 solicitudes (2 pendientes, 1 en revisión, 1 admitida).';
    });

    return $resumen ? ' ' . implode(' ', $resumen) : '';
}
