<?php
declare(strict_types=1);

// MODO DEMOSTRACIÓN
// Una sola cuenta (la MAESTRA) entra a un colegio ficticio ("Colegio Demostración SERENA") y puede actuar, con un clic,
// como cualquier rol: estudiante, acudiente, docente, psicólogo, orientador, coordinador, rector, administrador,
// secretaría académica (y, como siempre, superadmin / secretaría de educación con su propia identidad).
//  - Los usuarios demo NO tienen contraseña conocida: solo se llega a ellos desde la cuenta maestra.
//  - Los datos viven aparte (colegio con DANE 999999999999); no se mezclan con los reales. «Restablecer» los recrea.
//  - Ningún correo sale hacia direcciones @serena-demo.test (ver enviarCorreo en correo.php).

require_once __DIR__ . '/../modelos/Academico.php';
require_once __DIR__ . '/../modelos/Evaluacion.php';

const DEMO_DANE = '999999999999';
const DEMO_NOMBRE_COLEGIO = 'Colegio Demostración SERENA';
const DEMO_DOMINIO = 'serena-demo.test';

function correoDemo(string $clave): string
{
    return 'demo.' . $clave . '@' . DEMO_DOMINIO;
}

/** ¿El destinatario es una cuenta de demostración? (para no enviarle correos reales) */
function esCorreoDemo(string $correo): bool
{
    $sufijo = '@' . DEMO_DOMINIO;
    return substr(strtolower(trim($correo)), -strlen($sufijo)) === $sufijo;
}

function demoActiva(): bool
{
    return !empty($_SESSION['demo_maestro']);
}

/**
 * Roles que se pueden presentar: [icono, título, descripción, módulos planos].
 * Cada módulo plano = [nombre, ruta, qué es, qué decir, grupo, id, tip]. La fuente es demo_guion.php (catálogo completo).
 */
function demoRoles(): array
{
    require_once __DIR__ . '/demo_guion.php';
    $roles = [];
    foreach (demoCatalogo() as $clave => $r) {
        $roles[$clave] = [$r['icono'], $r['titulo'], $r['detalle'], demoItemsPlanos($clave)];
    }
    return $roles;
}

/** ¿La sesión (o el maestro guardado durante la demo) corresponde a la cuenta maestra? Se valida en la BD. */
function demoMaestroId(PDO $pdo): ?int
{
    $id = demoActiva() ? (int) $_SESSION['demo_maestro']['usuario_id'] : (int) ($_SESSION['usuario_id'] ?? 0);
    if ($id <= 0 || (!demoActiva() && ($_SESSION['rol_real'] ?? '') !== 'superadmin')) {
        return null;
    }
    $q = $pdo->prepare("SELECT 1 FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                         WHERE u.id = ? AND u.activo = 1 AND u.es_maestro = 1 AND r.nombre = 'superadmin'");
    $q->execute([$id]);
    return $q->fetchColumn() ? $id : null;
}

function demoColegioId(PDO $pdo, bool $crear = false): ?int
{
    $q = $pdo->prepare('SELECT id FROM colegios WHERE dane = ?');
    $q->execute([DEMO_DANE]);
    $id = $q->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    if (!$crear) {
        return null;
    }
    $pdo->prepare('INSERT INTO colegios (nombre, dane, municipio) VALUES (?,?,?)')->execute([DEMO_NOMBRE_COLEGIO, DEMO_DANE, 'Medellín']);
    return (int) $pdo->lastInsertId();
}

/** Cuántos usuarios demo hay (0 = aún no se han preparado los datos). */
function demoUsuariosCreados(PDO $pdo): int
{
    $id = demoColegioId($pdo);
    if ($id === null) {
        return 0;
    }
    $q = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE colegio_id = ?');
    $q->execute([$id]);
    return (int) $q->fetchColumn();
}

// ---------------------------------------------------------------------------------------------
// Entrar / cambiar de rol / salir
// ---------------------------------------------------------------------------------------------

/** Pone la sesión como el usuario demo de ese rol (o como la propia cuenta maestra en superadmin / secretaría). */
function demoEntrar(PDO $pdo, string $rol): ?string
{
    $maestroId = demoMaestroId($pdo);
    if ($maestroId === null) {
        return 'No tienes permiso para usar el modo demostración.';
    }
    if (!isset(demoRoles()[$rol])) {
        return 'Rol no válido.';
    }
    if (!demoActiva()) {
        $_SESSION['demo_maestro'] = [
            'usuario_id' => $maestroId, 'nombre' => (string) $_SESSION['nombre'], 'grupo_id' => $_SESSION['grupo_id'] ?? null,
            'grupos_ids' => $_SESSION['grupos_ids'] ?? [], 'colegio_id' => $_SESSION['colegio_id'] ?? null,
        ];
    }
    $m = $_SESSION['demo_maestro'];
    unset($_SESSION['maestro_elegir_rol'], $_SESSION['colegio_contexto']);
    session_regenerate_id(true);

    if (in_array($rol, ['superadmin', 'secretaria'], true)) {
        if ($rol === 'secretaria' && !$pdo->query('SELECT 1 FROM usuarios u INNER JOIN secretarias s ON s.id = u.secretaria_id WHERE s.activa = 1 AND u.id = ' . (int) $m['usuario_id'])->fetchColumn()) {
            return 'Tu cuenta aún no está asociada a una Secretaría de Educación (migración 031).';
        }
        $_SESSION['usuario_id'] = (int) $m['usuario_id'];
        $_SESSION['nombre'] = $m['nombre'];
        $_SESSION['rol'] = $rol;
        $_SESSION['grupo_id'] = null;
        $_SESSION['grupos_ids'] = [];
        $_SESSION['colegio_id'] = null;
    } else {
        $q = $pdo->prepare('SELECT u.id, u.nombre, u.grupo_id, u.colegio_id FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE u.correo = ? AND r.nombre = ?');
        $q->execute([correoDemo($rol), $rol]);
        $u = $q->fetch();
        if (!$u) {
            return 'Todavía no hay datos de demostración para este rol. Pulsa «Preparar datos de demostración».';
        }
        require_once __DIR__ . '/../modelos/Grupo.php';
        $_SESSION['usuario_id'] = (int) $u['id'];
        $_SESSION['nombre'] = $u['nombre'];
        $_SESSION['rol'] = $rol;
        $_SESSION['grupo_id'] = $u['grupo_id'];
        $_SESSION['grupos_ids'] = Grupo::idsGruposDePersonal($pdo, (int) $u['id']);
        $_SESSION['colegio_id'] = (int) $u['colegio_id'];
    }
    $_SESSION['rol_real'] = 'superadmin';
    $_SESSION['demo_rol'] = $rol;
    $_SESSION['sesion_recien_iniciada'] = true;
    return null;
}

/** Sale de la demostración y vuelve a la cuenta maestra (queda en el selector de rol). */
function demoSalir(): void
{
    $m = $_SESSION['demo_maestro'] ?? null;
    if ($m === null) {
        return;
    }
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $m['usuario_id'];
    $_SESSION['nombre'] = $m['nombre'];
    $_SESSION['rol'] = 'superadmin';
    $_SESSION['rol_real'] = 'superadmin';
    $_SESSION['grupo_id'] = $m['grupo_id'];
    $_SESSION['grupos_ids'] = $m['grupos_ids'];
    $_SESSION['colegio_id'] = $m['colegio_id'];
    $_SESSION['maestro_elegir_rol'] = true;
    unset($_SESSION['demo_maestro'], $_SESSION['demo_rol'], $_SESSION['colegio_contexto']);
}

// ---------------------------------------------------------------------------------------------
// Datos de demostración
// ---------------------------------------------------------------------------------------------

function demoLimpiar(PDO $pdo, int $colegioId): void
{
    $pdo->prepare('DELETE FROM usuarios WHERE colegio_id = ?')->execute([$colegioId]); // cascada: matrículas, notas, emociones...
    try { // reportes SIMAT del colegio demo (no cuelgan de usuarios); la tabla no existe si falta la migración 041
        $pdo->prepare('DELETE FROM simat_reportes WHERE colegio_id = ?')->execute([$colegioId]);
    } catch (Throwable $e) {
        // sin migración 041: nada que limpiar
    }
    try { // franjas del horario (cuelgan del colegio, no de usuarios); no existe sin la migración 037
        $pdo->prepare('DELETE FROM horario_franjas WHERE colegio_id = ?')->execute([$colegioId]);
    } catch (Throwable $e) {
        // sin migración 037: nada que limpiar
    }
    foreach (['anios_lectivos', 'asignaturas', 'escalas_evaluacion', 'componentes_evaluacion', 'configuracion_evaluacion'] as $tabla) {
        $pdo->prepare("DELETE FROM {$tabla} WHERE colegio_id = ?")->execute([$colegioId]);
    }
    $pdo->prepare('DELETE g FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id WHERE gr.colegio_id = ?')->execute([$colegioId]);
    $pdo->prepare('DELETE FROM grados WHERE colegio_id = ?')->execute([$colegioId]);
}

/**
 * Crea (o recrea) los datos de demostración. Devuelve un resumen. Lanza excepción si algo falla.
 * Solo toca el colegio demo (DANE 999999999999).
 */
function demoSembrar(PDO $pdo): array
{
    mt_srand(2026); // mismos datos cada vez
    $colegioId = (int) demoColegioId($pdo, true);
    demoLimpiar($pdo, $colegioId);

    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // nadie conoce esta contraseña: se entra solo desde la cuenta maestra
    $rolId = [];
    foreach ($pdo->query('SELECT id, nombre FROM roles') as $r) {
        $rolId[$r['nombre']] = (int) $r['id'];
    }
    $nUsuarios = 0;
    $crear = function (string $nombre, string $correo, string $rol, ?int $grupoId = null, ?string $nacimiento = null, int $diasRegistro = 60) use ($pdo, $colegioId, $hash, $rolId, &$nUsuarios): int {
        $pdo->prepare('INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, grupo_id, fecha_nacimiento, consentimiento_datos, fecha_consentimiento, fecha_registro)
                       VALUES (?,?,?,?,?,?,?,1,NOW(), NOW() - INTERVAL ? DAY)')
            ->execute([$nombre, $correo, $hash, $rolId[$rol], $colegioId, $grupoId, $nacimiento, $diasRegistro]);
        $nUsuarios++;
        return (int) $pdo->lastInsertId();
    };

    // --- Grados y grupos ---
    $grupos = []; // [clave => [id, grado_id]]
    foreach (['Sexto', 'Décimo'] as $gn) {
        $pdo->prepare('INSERT INTO grados (colegio_id, nombre) VALUES (?,?)')->execute([$colegioId, $gn]);
        $gradoId = (int) $pdo->lastInsertId();
        foreach (['A', 'B'] as $ln) {
            $pdo->prepare('INSERT INTO grupos (grado_id, nombre) VALUES (?,?)')->execute([$gradoId, $ln]);
            $grupos[$gn . ' ' . $ln] = ['id' => (int) $pdo->lastInsertId(), 'grado_id' => $gradoId];
        }
    }
    $sextoA = $grupos['Sexto A']['id'];

    // --- Personal (uno por rol) ---
    $personal = [
        'administrador' => $crear('Camila Restrepo (Administradora)', correoDemo('administrador'), 'administrador'),
        'rector' => $crear('Rector Hernán Ospina', correoDemo('rector'), 'rector'),
        'coordinador' => $crear('Lic. Pedro Salas', correoDemo('coordinador'), 'coordinador'),
        'psicologo' => $crear('Psic. Laura Mejía', correoDemo('psicologo'), 'psicologo'),
        'orientador' => $crear('Orient. Andrés Cardona', correoDemo('orientador'), 'orientador', $sextoA),
        'docente' => $crear('Prof. Ana Ríos', correoDemo('docente'), 'docente', $sextoA),
        'secretaria_academica' => $crear('Marta Gómez (Secretaría académica)', correoDemo('secretaria_academica'), 'secretaria_academica'),
    ];
    $docente2 = $crear('Prof. Carlos Zapata', correoDemo('docente2'), 'docente', $grupos['Décimo A']['id']);
    $pdo->prepare('UPDATE grupos SET docente_id = ? WHERE id = ?')->execute([$personal['docente'], $sextoA]);
    $pdo->prepare('UPDATE grupos SET docente_id = ? WHERE id = ?')->execute([$personal['docente'], $grupos['Sexto B']['id']]);
    $pdo->prepare('UPDATE grupos SET docente_id = ? WHERE id = ?')->execute([$docente2, $grupos['Décimo A']['id']]);
    $pdo->prepare('UPDATE grupos SET docente_id = ? WHERE id = ?')->execute([$docente2, $grupos['Décimo B']['id']]);
    $pg = $pdo->prepare('INSERT INTO personal_grupos (usuario_id, grupo_id) VALUES (?,?)');
    foreach (['Sexto A', 'Sexto B'] as $k) {
        $pg->execute([$personal['orientador'], $grupos[$k]['id']]);
    }

    // --- Estudiantes: 6 por grupo (el primero, Valentina, es la cuenta demo de «Estudiante») ---
    $nombres = ['Valentina', 'Mateo', 'Sofía', 'Santiago', 'Isabella', 'Juan', 'Mariana', 'Samuel', 'Luciana', 'Emmanuel', 'Daniela', 'Nicolás',
                'Gabriela', 'Alejandro', 'Camila', 'Tomás', 'Salomé', 'Miguel', 'Antonella', 'Felipe', 'Juliana', 'David', 'Paula', 'Esteban'];
    $apellidos = ['Rojas Pérez', 'Londoño Ruiz', 'Gómez Arango', 'Cano Restrepo', 'Pérez Montoya', 'Muñoz Giraldo', 'Zapata Ocampo', 'Álvarez Vélez',
                  'Henao Salazar', 'Duque Cardona', 'Ríos Mejía', 'Osorio Cano', 'Bedoya Marín', 'Correa Toro', 'Villa Cadavid', 'Sierra Mesa',
                  'Quintero Bravo', 'Arias Uribe', 'Gaviria Loaiza', 'Ospina Lopera', 'Montes Echeverri', 'Torres Agudelo', 'Franco Builes', 'Mora Escobar'];
    $estudiantes = []; // [id => ['grupo' => clave, 'nombre' => ...]]
    $i = 0;
    $nombresGrupo = array_keys($grupos);
    foreach ($nombresGrupo as $clave) {
        for ($k = 0; $k < 6; $k++, $i++) {
            $nac = ((strpos($clave, 'Sexto') === 0) ? 2014 : 2010) . sprintf('-%02d-%02d', mt_rand(1, 12), mt_rand(1, 28));
            $nom = $nombres[$i] . ' ' . $apellidos[$i];
            $id = $crear($nom, correoDemo($i === 0 ? 'estudiante' : sprintf('est%02d', $i + 1)), 'estudiante', $grupos[$clave]['id'], $nac, 45);
            $estudiantes[$id] = ['grupo' => $clave, 'nombre' => $nom, 'n' => $i];
        }
    }
    $ids = array_keys($estudiantes);
    $principal = $ids[0];
    $mateo = $ids[1];

    // --- Acudiente de Valentina y de Sofía ---
    $acudiente = $crear('Luz Marina Pérez (acudiente de Valentina)', correoDemo('acudiente'), 'acudiente');
    $ae = $pdo->prepare('INSERT INTO acudientes_estudiantes (acudiente_id, estudiante_id, parentesco, principal) VALUES (?,?,?,1)');
    $ae->execute([$acudiente, $principal, 'Madre']);
    $ae->execute([$acudiente, $ids[2], 'Tía']);

    // --- Año lectivo, períodos y evaluación ---
    [$anioId, $err] = Academico::crearAnio($pdo, $colegioId, 2026, 'Año lectivo 2026', '2026-01-26', '2026-11-27');
    if ($anioId === 0) {
        throw new RuntimeException($err);
    }
    $anio = Academico::anio($pdo, $anioId, $colegioId);
    $err = Academico::generarPeriodos($pdo, $anio, 4);
    if ($err !== '') {
        throw new RuntimeException($err);
    }
    $pdo->prepare('UPDATE periodos SET boletin_publicado = 1 WHERE anio_lectivo_id = ? AND fecha_fin < CURDATE() AND numero < 4')->execute([$anioId]);
    $periodos = Academico::periodos($pdo, $anioId);
    $escalaId = Evaluacion::crearEscalaPredeterminada($pdo, $colegioId, 'numerica');
    Evaluacion::guardarConfiguracion($pdo, $colegioId, 'actividades', $escalaId);
    $escala = Evaluacion::escalaActiva($pdo, $colegioId);

    // Matrículas, documento de identidad y número de matrícula
    $mat = $pdo->prepare("INSERT INTO matriculas (colegio_id, anio_lectivo_id, estudiante_id, grado_id, grupo_id, tipo, estado, resuelto_en) VALUES (?,?,?,?,?, 'nueva', 'activa', NOW())");
    $hv = $pdo->prepare('INSERT INTO hojas_vida (estudiante_id, colegio_id, tipo_documento, numero_documento) VALUES (?,?,?,?)');
    foreach ($estudiantes as $id => $e) {
        $g = $grupos[$e['grupo']];
        $mat->execute([$colegioId, $anioId, $id, $g['grado_id'], $g['id']]);
        $hv->execute([$id, $colegioId, 'TI', (string) (1000000000 + $e['n'] * 7919 + 4321)]);
    }
    Academico::asegurarCodigosMatricula($pdo, $colegioId);

    // Asignaturas, asignaciones, actividades, notas
    $asignaturas = [['Matemáticas', 'Matemáticas', 5], ['Lengua Castellana', 'Humanidades', 4], ['Ciencias Naturales', 'Ciencias', 4], ['Ciencias Sociales', 'Sociales', 3], ['Inglés', 'Humanidades', 3]];
    $asigIds = [];
    foreach ($asignaturas as [$n, $area, $h]) {
        Evaluacion::guardarAsignatura($pdo, $colegioId, null, $n, $area, $h, true);
    }
    foreach ($pdo->query("SELECT id FROM asignaturas WHERE colegio_id = {$colegioId} ORDER BY id") as $r) {
        $asigIds[] = (int) $r['id'];
    }
    $insAct = $pdo->prepare('INSERT INTO actividades_evaluacion (asignacion_id, periodo_id, nombre, peso, fecha, orden) VALUES (?,?,?,?,?,?)');
    $insCal = $pdo->prepare('INSERT INTO calificaciones (actividad_id, estudiante_id, valor, nivel_id) VALUES (?,?,?,?)');
    $hoy = date('Y-m-d');
    $nNotas = 0;
    $mapaAsig = []; // [clave de grupo][asignatura_id] = asignacion_id (lo usan horarios, asistencia y refuerzos)
    foreach ($grupos as $clave => $g) {
        $docenteId = (strpos($clave, 'Sexto') === 0) ? $personal['docente'] : $docente2;
        $estsGrupo = array_keys(array_filter($estudiantes, static fn (array $e): bool => $e['grupo'] === $clave));
        $estsRows = Evaluacion::estudiantesDeGrupo($pdo, $colegioId, $anio, $g['id']);
        foreach ($asigIds as $aid) {
            if (Evaluacion::crearAsignacion($pdo, $colegioId, $anioId, $g['id'], $aid, $docenteId) !== '') {
                continue;
            }
            $asignacionId = (int) $pdo->query("SELECT id FROM asignaciones_academicas WHERE anio_lectivo_id = {$anioId} AND grupo_id = {$g['id']} AND asignatura_id = {$aid}")->fetchColumn();
            $mapaAsig[$clave][$aid] = $asignacionId;
            $asig = Evaluacion::asignacion($pdo, $asignacionId, $colegioId);
            foreach ($periodos as $p) {
                if ($p['fecha_inicio'] > $hoy) {
                    continue; // períodos futuros: sin actividades
                }
                $esActual = $p['fecha_inicio'] <= $hoy && $hoy <= $p['fecha_fin'];
                $actIds = [];
                foreach ([['Taller 1', 30], ['Quiz', 30], ['Evaluación escrita', 40]] as $o => [$nom, $peso]) {
                    $insAct->execute([$asignacionId, (int) $p['id'], $nom, $peso, $p['fecha_inicio'], $o + 1]);
                    $actIds[$nom] = (int) $pdo->lastInsertId();
                }
                foreach ($actIds as $nom => $actId) {
                    if ($esActual && $nom === 'Evaluación escrita') {
                        continue; // queda sin notas a propósito: sirve para demostrar el dictado por voz
                    }
                    foreach ($estsGrupo as $eid) {
                        $v = round(max(1.0, min(5.0, 2.6 + mt_rand() / mt_getrandmax() * 2.4 - ($eid === $mateo ? 0.8 : 0))), 1);
                        $n = Evaluacion::nivelParaValor($escala['niveles'], $v);
                        $insCal->execute([$actId, $eid, $v, $n ? (int) $n['id'] : null]);
                        $nNotas++;
                    }
                }
                Evaluacion::recalcularPeriodo($pdo, $asig, (int) $p['id'], 'actividades', $escala, $estsRows, Evaluacion::actividades($pdo, $asignacionId, (int) $p['id']));
            }
        }
    }

    // --- Bienestar: emociones (21 días), sueño, hábitos, metas, diario, alerta, cita y chat ---
    $emo = [5 => 'feliz', 4 => 'tranquilo', 3 => 'cansado', 2 => 'ansioso', 1 => 'triste'];
    $insEmo = $pdo->prepare('INSERT INTO estado_emocional (usuario_id, emocion, nivel, momento_dia, fecha_hora) VALUES (?,?,?,?,?)');
    $insSue = $pdo->prepare('INSERT INTO sueno (usuario_id, fecha, horas, calidad, hora_dormir, hora_despertar) VALUES (?,?,?,?,?,?)');
    $nEmo = 0;
    foreach ($ids as $eid) {
        for ($d = 20; $d >= 0; $d--) {
            $fecha = date('Y-m-d', strtotime("-{$d} days"));
            $nivel = ($eid === $mateo && $d <= 5) ? mt_rand(1, 2) : mt_rand(3, 5);
            $insEmo->execute([$eid, $emo[$nivel], $nivel, ['mañana', 'tarde', 'noche'][mt_rand(0, 2)], $fecha . ' ' . sprintf('%02d:%02d:00', mt_rand(7, 20), mt_rand(0, 59))]);
            $horas = $eid === $mateo && $d <= 5 ? mt_rand(40, 55) / 10 : mt_rand(65, 90) / 10;
            $insSue->execute([$eid, $fecha, $horas, $horas < 6 ? 'mala' : ($horas < 7.5 ? 'regular' : 'buena'), '22:00:00', '06:00:00']);
            $nEmo++;
        }
    }
    $insHab = $pdo->prepare("INSERT INTO habitos (usuario_id, nombre, categoria, frecuencia, fecha_creacion) VALUES (?,?,?,'diaria', NOW() - INTERVAL 25 DAY)");
    $insSeg = $pdo->prepare('INSERT INTO seguimiento_habitos (habito_id, fecha, completado) VALUES (?,?,?)');
    foreach ([['Beber 2 litros de agua', 'salud'], ['Leer 20 minutos', 'estudio'], ['Dormir antes de las 10 pm', 'salud']] as [$hn, $hc]) {
        $insHab->execute([$principal, $hn, $hc]);
        $hid = (int) $pdo->lastInsertId();
        for ($d = 13; $d >= 0; $d--) {
            $insSeg->execute([$hid, date('Y-m-d', strtotime("-{$d} days")), mt_rand(0, 9) < 7 ? 1 : 0]);
        }
    }
    $insMeta = $pdo->prepare('INSERT INTO metas (usuario_id, titulo, descripcion, tipo, fecha_limite, estado, porcentaje_avance) VALUES (?,?,?,?,?,?,?)');
    $insMeta->execute([$principal, 'Terminar el proyecto de ciencias', 'Investigación sobre reciclaje para la feria escolar', 'corto_plazo', date('Y-m-d', strtotime('+10 days')), 'activa', 60]);
    $insMeta->execute([$principal, 'Leer 5 libros este semestre', 'Meta personal de lectura', 'largo_plazo', date('Y-m-d', strtotime('+60 days')), 'activa', 40]);
    $insDia = $pdo->prepare('INSERT INTO diario (usuario_id, titulo, contenido, fecha) VALUES (?,?,?,?)');
    $insDia->execute([$principal, 'Un buen día', 'Hoy me fue bien en el examen de matemáticas. Me sentí orgullosa de mí misma.', date('Y-m-d H:i:s', strtotime('-6 days'))]);
    $insDia->execute([$principal, null, 'Practicamos para el festival del colegio. Muy cansada, pero contenta.', date('Y-m-d H:i:s', strtotime('-2 days'))]);

    $pdo->prepare("INSERT INTO alertas_tempranas (usuario_id, tipo, descripcion, nivel_urgencia, atendida, fecha_generada) VALUES (?, 'emocional_bajo', 'Varios días consecutivos con niveles emocionales bajos (1-2).', 'alto', 0, NOW() - INTERVAL 1 DAY)")->execute([$mateo]);
    $pdo->prepare("INSERT INTO citas (profesional_id, estudiante_id, fecha, hora_inicio, hora_fin, motivo, estado) VALUES (?,?,?,?,?,?, 'confirmada')")
        ->execute([$personal['psicologo'], $mateo, date('Y-m-d', strtotime('+1 day')), '10:00:00', '10:30:00', 'Seguimiento emocional']);
    $pdo->prepare('INSERT INTO conversaciones (usuario_a_id, usuario_b_id) VALUES (?,?)')->execute([$principal, $personal['psicologo']]);
    $conv = (int) $pdo->lastInsertId();
    $insMsg = $pdo->prepare('INSERT INTO mensajes (conversacion_id, emisor_id, contenido, leido, fecha_envio) VALUES (?,?,?,?,?)');
    $insMsg->execute([$conv, $principal, 'Hola, ¿podemos hablar? Esta semana me he sentido un poco estresada con los exámenes.', 1, date('Y-m-d H:i:s', strtotime('-2 days'))]);
    $insMsg->execute([$conv, $personal['psicologo'], 'Claro que sí, Valentina. Te espero mañana en mi horario de atención. Mientras tanto, prueba el ejercicio de respiración.', 1, date('Y-m-d H:i:s', strtotime('-2 days +1 hour'))]);
    $insMsg->execute([$conv, $personal['psicologo'], '¿Cómo vas hoy?', 0, date('Y-m-d H:i:s', strtotime('-1 hour'))]);

    // --- SIMAT y SIMPADE (migración 041): datos para que cada pestaña se vea distinta en las capturas ---
    $tiene = static function (string $tabla) use ($pdo): bool {
        $q = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $q->execute([$tabla]);
        return (bool) $q->fetchColumn();
    };
    $secad = $personal['secretaria_academica'];
    $extra = '';
    try {
    if ($tiene('simat_datos') && $tiene('simat_novedades') && $tiene('simat_reportes')) {
        $insSd = $pdo->prepare('INSERT INTO simat_datos (estudiante_id, colegio_id, proviene_de, subsidio_condicionado, internado, matricula_contratada, actualizado_por) VALUES (?,?,?,?,?,?,?)');
        $proviene = ['oficial', 'no_oficial', 'otro_municipio', 'ninguno'];
        foreach ($ids as $k => $eid) {
            if ($k >= 16) {
                continue; // los últimos quedan sin datos SIMAT: así «Revisión» y «Datos SIMAT» muestran pendientes
            }
            $insSd->execute([$eid, $colegioId, $proviene[$k % 4], $k % 3 === 0 ? 1 : 0, 0, 0, $secad]);
        }
        $insNov = $pdo->prepare('INSERT INTO simat_novedades (colegio_id, anio_lectivo_id, estudiante_id, tipo, fecha, causa, destino, observacion, estado, reportada_en, reportada_por, origen, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insNov->execute([$colegioId, $anioId, $ids[23], 'retiro', date('Y-m-d', strtotime('-9 days')), 'Cambio de domicilio', null, 'La familia se trasladó de ciudad.', 'pendiente', null, null, 'manual', $secad]);
        $insNov->execute([$colegioId, $anioId, $ids[22], 'traslado', date('Y-m-d', strtotime('-6 days')), 'Traslado a otra institución', 'I.E. San José (demo)', null, 'pendiente', null, null, 'manual', $secad]);
        $insNov->execute([$colegioId, $anioId, $ids[21], 'ingreso', date('Y-m-d', strtotime('-20 days')), 'Matrícula extemporánea', null, null, 'reportada', date('Y-m-d H:i:s', strtotime('-15 days')), $secad, 'manual', $secad]);
        $insNov->execute([$colegioId, $anioId, $ids[20], 'cambio_grupo', date('Y-m-d', strtotime('-25 days')), 'Solicitud de la familia', null, null, 'reportada', date('Y-m-d H:i:s', strtotime('-22 days')), $secad, 'manual', $secad]);
        $insRep = $pdo->prepare('INSERT INTO simat_reportes (colegio_id, anio_lectivo_id, tipo, filas, errores, advertencias, detalle, generado_por, creado_en) VALUES (?,?,?,?,?,?,?,?, NOW() - INTERVAL ? DAY)');
        $insRep->execute([$colegioId, $anioId, 'validacion', 24, 0, 8, 'Validación de la matrícula', $secad, 6]);
        $insRep->execute([$colegioId, $anioId, 'matricula', 24, 0, 8, 'Exportación CSV de la matrícula', $secad, 5]);
        $insRep->execute([$colegioId, $anioId, 'conciliacion', 24, 2, 1, 'Conciliación con el listado del SIMAT', $secad, 2]);
        $extra .= ' SIMAT: datos de 16 estudiantes, 4 novedades y 3 reportes.';
    } else {
        $extra .= ' SIMAT quedó vacío: falta aplicar la migración 041.';
    }
    if ($tiene('simpade_riesgos') && $tiene('simpade_seguimientos') && $tiene('simpade_retiros')) {
        $psico = $personal['psicologo'];
        $insRi = $pdo->prepare('INSERT INTO simpade_riesgos (colegio_id, anio_lectivo_id, estudiante_id, nivel, estado, origen, puntaje, factores, observacion, responsable_id, fecha_deteccion, cerrado_en, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $casos = [
            [$mateo, 'alto', 'en_seguimiento', 'sugerido', 8, 'inasistencia,bajo_rendimiento,desmotivacion', 'Ha faltado con frecuencia y bajó su rendimiento en varias asignaturas.', '-20 days', null],
            [$ids[8], 'medio', 'activo', 'sugerido', 4, 'bajo_rendimiento,extraedad', 'Detectado por el sistema; pendiente de contacto con la familia.', '-8 days', null],
            [$ids[14], 'medio', 'en_seguimiento', 'manual', 3, 'conflicto_familiar', 'Situación familiar reportada por la docente.', '-15 days', null],
            [$ids[19], 'bajo', 'superado', 'manual', 2, 'trabajo', 'Se ajustó el horario y retomó la asistencia regular.', '-40 days', '-3 days'],
        ];
        $riesgoIds = [];
        foreach ($casos as [$eid, $nivel, $estado, $origen, $puntaje, $factores, $obs, $detec, $cierre]) {
            $insRi->execute([$colegioId, $anioId, $eid, $nivel, $estado, $origen, $puntaje, $factores, $obs, $psico, date('Y-m-d', strtotime($detec)), $cierre ? date('Y-m-d H:i:s', strtotime($cierre)) : null, $psico]);
            $riesgoIds[] = (int) $pdo->lastInsertId();
        }
        $insSg = $pdo->prepare('INSERT INTO simpade_seguimientos (riesgo_id, autor_id, fecha, tipo, nota, proxima_fecha) VALUES (?,?,?,?,?,?)');
        $insSg->execute([$riesgoIds[0], $psico, date('Y-m-d', strtotime('-18 days')), 'llamada', 'Se llamó a la acudiente; reporta dificultades para levantarse temprano.', date('Y-m-d', strtotime('-10 days'))]);
        $insSg->execute([$riesgoIds[0], $psico, date('Y-m-d', strtotime('-9 days')), 'reunion', 'Reunión con la familia; se acordó un plan de asistencia semanal.', date('Y-m-d', strtotime('+5 days'))]);
        $insSg->execute([$riesgoIds[2], $psico, date('Y-m-d', strtotime('-12 days')), 'compromiso', 'Compromiso de acompañamiento familiar firmado.', date('Y-m-d', strtotime('+10 days'))]);
        $pdo->prepare('INSERT INTO simpade_retiros (colegio_id, anio_lectivo_id, estudiante_id, fecha, causa, detalle, destino, familia_contactada, estrategia, reportado, creado_por) VALUES (?,?,?,?,?,?,?,1,?,0,?)')
            ->execute([$colegioId, $anioId, $ids[23], date('Y-m-d', strtotime('-9 days')), 'cambio_domicilio', 'La familia se mudó a otro municipio.', 'otro_municipio', 'Se ofreció apoyo con los documentos para el traslado de cupo.', $psico]);
        $extra .= ' SIMPADE: 4 casos de riesgo, 3 seguimientos y 1 retiro.';
    } else {
        $extra .= ' SIMPADE quedó vacío: falta aplicar la migración 041.';
    }
    } catch (Throwable $exSim) { // un fallo aquí no debe tumbar el resto de la demo
        error_log('demoSembrar SIMAT/SIMPADE: ' . $exSim->getMessage());
        $extra .= ' (No se pudieron sembrar SIMAT/SIMPADE: ' . $exSim->getMessage() . ')';
    }

    // --- Resto de módulos (horarios, asistencia, expediente, refuerzos, restaurante, cartera, pulso, encuestas, inscripciones...) ---
    require_once __DIR__ . '/demo_datos.php';
    $extra .= demoSembrarModulos($pdo, [
        'colegio' => $colegioId, 'anio_id' => $anioId, 'anio' => $anio, 'periodos' => $periodos, 'grupos' => $grupos,
        'personal' => $personal, 'docente2' => $docente2, 'estudiantes' => $estudiantes, 'ids' => $ids, 'principal' => $principal,
        'mateo' => $mateo, 'asig_ids' => $asigIds, 'mapa_asig' => $mapaAsig, 'hoy' => $hoy,
    ]);

    return ['colegio_id' => $colegioId, 'usuarios' => $nUsuarios, 'estudiantes' => count($estudiantes), 'notas' => $nNotas, 'registros_emocionales' => $nEmo, 'extra' => $extra];
}
