<?php
declare(strict_types=1);
// Sin límite de tiempo: con 3000 estudiantes y ~8 meses de historial esto puede
// tardar varios minutos. Si corre por HTTP y el hosting igual lo corta (algunos
// planes compartidos ignoran set_time_limit), ejecútalo por línea de comandos:
// php public/superadmin/generar_datos_prueba.php  (ahí nunca hay límite de tiempo).
set_time_limit(0);
ini_set('memory_limit', '1536M'); // con ~270k+ filas de emociones/sueño en memoria antes de insertarlas, 512M se queda corto

$esCLI = php_sapi_name() === 'cli';

require __DIR__ . '/../../src/config/conexion.php';
if (!$esCLI) {
    require_once __DIR__ . '/../../src/helpers/auth.php';
    require_once __DIR__ . '/../../src/helpers/csrf.php';
    requiereRol('superadmin'); // multi-colegio: crea un colegio demo completo
}

// ============================================================
// CONFIGURACIÓN — ajusta esto antes de correrlo si 3000 es demasiado
// para una primera prueba (por ejemplo ESCALA=0.1 genera ~300 estudiantes).
// ============================================================
const DOMINIO_DEMO   = 'demo-institucion-2026.test';
const FECHA_INICIO   = '2026-01-01';
const FECHA_HOY      = '2026-09-07';
const TOTAL_ESTUDIANTES_BASE = 3000;
const ESCALA          = 1.0; // 1.0 = tamaño completo pedido
const GRADOS          = ['Sexto', 'Séptimo', 'Octavo', 'Noveno', 'Décimo', 'Once'];
const GRUPOS_POR_GRADO = 10;
const TAMANO_LOTE     = 500; // filas por INSERT — bajar si el servidor tiene poca memoria

function totalEstudiantes(): int { return (int) round(TOTAL_ESTUDIANTES_BASE * ESCALA); }

// ============================================================
// Utilidades de inserción por lotes (mucho más rápido que un INSERT por fila)
// ============================================================
function insertarLote(PDO $pdo, string $tabla, array $columnas, array $filas): void
{
    if (empty($filas)) return;
    $marcador = '(' . implode(',', array_fill(0, count($columnas), '?')) . ')';
    $colsSql = '`' . implode('`,`', $columnas) . '`';
    foreach (array_chunk($filas, TAMANO_LOTE) as $lote) {
        $marcadores = implode(',', array_fill(0, count($lote), $marcador));
        $valores = [];
        foreach ($lote as $fila) $valores = array_merge($valores, array_values($fila));
        $pdo->prepare("INSERT INTO `{$tabla}` ({$colsSql}) VALUES {$marcadores}")->execute($valores);
    }
}

function fechaAleatoriaEnRango(string $desde, string $hasta): string
{
    $ts = random_int(strtotime($desde), strtotime($hasta));
    return date('Y-m-d', $ts);
}

function elegir(array $opciones) { return $opciones[array_rand($opciones)]; }
function pesoAleatorio(array $pesos): string
{
    // $pesos = ['alto' => 20, 'medio' => 50, 'bajo' => 30] — devuelve una clave respetando el peso
    $total = array_sum($pesos);
    $r = random_int(1, $total);
    foreach ($pesos as $clave => $peso) {
        if ($r <= $peso) return $clave;
        $r -= $peso;
    }
    return array_key_first($pesos);
}

$hashPassword = '$2y$10$9mbBHAPTjWPT3cRpKXzqduAmzwpJVfU/OUYRnD2pJ7uI0xHg/xwUS'; // "serena2024" (ver memoria del proyecto)

// ============================================================
// Manejo de la solicitud (solo aplica cuando corre por navegador)
// ============================================================
$resultado = null;
$error = null;

if ($esCLI || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!$esCLI) verificarTokenCSRF($_POST['csrf_token'] ?? '');

    try {
        $pdo->beginTransaction();
        $resumen = [];

        // --- 1. Grados y grupos (nombres con "(Demo)" para nunca chocar con los reales) ---
        // La institución de prueba es su propio colegio (aislado del resto).
        require_once __DIR__ . '/../../src/modelos/Colegio.php';
        $colegioDemoId = Colegio::crear($pdo, 'Colegio Demo (prueba)', '999999999999', 'Medellín'); // DANE reservado para el demo
        $gradoIds = [];
        foreach (GRADOS as $nombreGrado) {
            $pdo->prepare('INSERT INTO grados (colegio_id, nombre) VALUES (?, ?)')->execute([$colegioDemoId, "{$nombreGrado} (Demo)"]);
            $gradoIds[$nombreGrado] = (int) $pdo->lastInsertId();
        }

        $grupoIds = [];
        $letras = range('A', 'J');
        foreach ($gradoIds as $nombreGrado => $gradoId) {
            foreach (array_slice($letras, 0, GRUPOS_POR_GRADO) as $letra) {
                $pdo->prepare('INSERT INTO grupos (grado_id, nombre) VALUES (?, ?)')->execute([$gradoId, $letra]);
                $grupoIds[] = (int) $pdo->lastInsertId();
            }
        }
        $resumen['grados'] = count($gradoIds);
        $resumen['grupos'] = count($grupoIds);

        // --- 2. Roles ---
        $rolId = [];
        foreach ($pdo->query("SELECT id, nombre FROM roles")->fetchAll() as $r) $rolId[$r['nombre']] = (int) $r['id'];

        // --- 3. Personal ---
        $nombresPila = ['Ana','Carlos','María','Juan','Laura','Andrés','Camila','Diego','Valentina','Santiago',
            'Isabella','Mateo','Sofía','Sebastián','Daniela','Nicolás','Paula','Julián','Gabriela','Felipe'];
        $apellidos = ['García','Rodríguez','Martínez','López','González','Pérez','Sánchez','Ramírez','Torres','Flores',
            'Rivera','Gómez','Díaz','Vargas','Castro','Ortiz','Romero','Mendoza','Silva','Reyes'];

        function nombreAleatorio(array $pila, array $apellidos): string
        {
            return elegir($pila) . ' ' . elegir($apellidos) . ' ' . elegir($apellidos);
        }

        $personal = ['administrador' => 1, 'docente' => 100, 'orientador' => 30, 'psicologo' => 20, 'rector' => 1, 'coordinador' => 5];
        $idsPorRol = [];
        $usuariosBatch = [];
        $contadorCorreo = 0;

        foreach ($personal as $rolNombre => $cantidad) {
            $idsPorRol[$rolNombre] = [];
            for ($i = 0; $i < $cantidad; $i++) {
                $contadorCorreo++;
                $nombre = nombreAleatorio($nombresPila, $apellidos);
                $correo = "{$rolNombre}{$contadorCorreo}@" . DOMINIO_DEMO;
                $grupoAsignado = null;
                if ($rolNombre === 'docente' && $i < count($grupoIds)) {
                    $grupoAsignado = $grupoIds[$i]; // los primeros 60 docentes son titulares de grupo
                }
                $usuariosBatch[] = [
                    'nombre' => $nombre, 'correo' => $correo, 'password_hash' => $hashPassword,
                    'rol_id' => $rolId[$rolNombre], 'grupo_id' => $grupoAsignado,
                    'consentimiento_datos' => 1, 'fecha_consentimiento' => FECHA_INICIO . ' 08:00:00',
                    'fecha_registro' => FECHA_INICIO . ' 08:00:00', 'activo' => 1,
                    '_rol' => $rolNombre, '_correo' => $correo,
                ];
            }
        }

        foreach ($usuariosBatch as $u) {
            $pdo->prepare(
                'INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, grupo_id, consentimiento_datos, fecha_consentimiento, fecha_registro, activo)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$u['nombre'], $u['correo'], $u['password_hash'], $u['rol_id'], $colegioDemoId, $u['grupo_id'], $u['consentimiento_datos'], $u['fecha_consentimiento'], $u['fecha_registro'], $u['activo']]);
            $idsPorRol[$u['_rol']][] = (int) $pdo->lastInsertId();
        }
        $resumen['personal'] = array_map('count', $idsPorRol);

        // Orientadores: 2 grupos cada uno, cubriendo los 60 grupos entre los 30 orientadores.
        $indice = 0;
        foreach ($idsPorRol['orientador'] as $orientadorId) {
            for ($k = 0; $k < 2 && $indice < count($grupoIds); $k++, $indice++) {
                $pdo->prepare('INSERT IGNORE INTO personal_grupos (usuario_id, grupo_id) VALUES (?,?)')->execute([$orientadorId, $grupoIds[$indice]]);
            }
        }
        // Los 40 docentes restantes (sin grupo titular): 2-3 grupos cada uno como docentes de área.
        $docentesExtra = array_slice($idsPorRol['docente'], count($grupoIds));
        foreach ($docentesExtra as $docenteId) {
            $n = random_int(2, 3);
            for ($k = 0; $k < $n; $k++) {
                $pdo->prepare('INSERT IGNORE INTO personal_grupos (usuario_id, grupo_id) VALUES (?,?)')->execute([$docenteId, elegir($grupoIds)]);
            }
        }

        // --- 4. Estudiantes, con un "perfil" de bienestar y de participación que decide
        // qué tan seguido registra cosas y con qué ánimo, para que los datos se vean
        // realistas (no todos iguales, no todos perfectos). ---
        $totalEst = totalEstudiantes();
        $porGrupo = (int) ceil($totalEst / count($grupoIds));
        $estudiantes = [];
        $lote = [];
        $contadorCorreo = 0;

        foreach ($grupoIds as $grupoId) {
            for ($i = 0; $i < $porGrupo && count($estudiantes) < $totalEst; $i++) {
                $contadorCorreo++;
                $perfilParticipacion = pesoAleatorio(['alto' => 25, 'medio' => 50, 'bajo' => 25]);
                $perfilAnimo = pesoAleatorio(['positivo' => 55, 'neutro' => 30, 'bajo' => 15]);
                $correo = "estudiante{$contadorCorreo}@" . DOMINIO_DEMO;
                $lote[] = [
                    'nombre' => nombreAleatorio($nombresPila, $apellidos), 'correo' => $correo,
                    'password_hash' => $hashPassword, 'rol_id' => $rolId['estudiante'], 'grupo_id' => $grupoId,
                    'consentimiento_datos' => 1, 'fecha_consentimiento' => FECHA_INICIO . ' 08:00:00',
                    'fecha_registro' => FECHA_INICIO . ' 08:00:00', 'activo' => 1,
                ];
                $estudiantes[] = ['grupo_id' => $grupoId, 'participacion' => $perfilParticipacion, 'animo' => $perfilAnimo, '_correo' => $correo];
            }
        }
        insertarLote($pdo, 'usuarios', ['nombre','correo','password_hash','rol_id','grupo_id','consentimiento_datos','fecha_consentimiento','fecha_registro','activo'], $lote);

        // Recuperar los IDs reales ya asignados por MySQL, en el mismo orden de inserción.
        $idsEstudiantes = $pdo->query(
            "SELECT id FROM usuarios WHERE correo LIKE 'estudiante%@" . DOMINIO_DEMO . "' ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($idsEstudiantes as $i => $id) $estudiantes[$i]['id'] = (int) $id;
        $resumen['estudiantes'] = count($estudiantes);

        // --- 5. Actividad histórica día por día, con probabilidad según el perfil ---
        $probabilidadPorDia = ['alto' => 0.55, 'medio' => 0.28, 'bajo' => 0.10];
        $emocionesPositivas = ['feliz','tranquilo','motivado','agradecido'];
        $emocionesNeutras   = ['cansado','ocupado','pensativo'];
        $emocionesBajas     = ['triste','ansioso','estresado','enojado','solo'];
        $momentos = ['mañana','tarde','noche'];

        $filasEmocion = [];
        $filasSueno = [];
        $fechaCursor = strtotime(FECHA_INICIO);
        $fechaFin = strtotime(FECHA_HOY);

        while ($fechaCursor <= $fechaFin) {
            $fechaStr = date('Y-m-d', $fechaCursor);
            foreach ($estudiantes as $e) {
                $prob = $probabilidadPorDia[$e['participacion']];
                if ((mt_rand() / mt_getrandmax()) < $prob) {
                    // Nivel emocional sesgado por el perfil de ánimo del estudiante, con ruido normal.
                    if ($e['animo'] === 'positivo') { $nivel = random_int(3, 5); $lista = $emocionesPositivas; }
                    elseif ($e['animo'] === 'bajo')  { $nivel = random_int(1, 3); $lista = $emocionesBajas; }
                    else                             { $nivel = random_int(2, 4); $lista = $emocionesNeutras; }

                    $filasEmocion[] = [
                        $e['id'], elegir($lista), $nivel, null, elegir($momentos),
                        "{$fechaStr} " . sprintf('%02d:%02d:00', random_int(6, 22), random_int(0, 59)),
                    ];
                }
                if ((mt_rand() / mt_getrandmax()) < ($prob * 0.85)) {
                    $horas = round(random_int(45, 95) / 10, 1);
                    $calidad = $horas >= 7.5 ? 'buena' : ($horas >= 6 ? 'regular' : 'mala');
                    $filasSueno[] = [$e['id'], $fechaStr, $horas, $calidad, null, null];
                }
            }
            $fechaCursor = strtotime('+1 day', $fechaCursor);
        }
        insertarLote($pdo, 'estado_emocional', ['usuario_id','emocion','nivel','nota','momento_dia','fecha_hora'], $filasEmocion);
        insertarLote($pdo, 'sueno', ['usuario_id','fecha','horas','calidad','hora_dormir','hora_despertar'], $filasSueno);
        $resumen['registros_emocionales'] = count($filasEmocion);
        $resumen['registros_sueno'] = count($filasSueno);

        // --- 6. Hábitos + seguimiento ---
        $catalogoHabitos = [
            ['Dormir 8 horas', 'sueño'], ['Tomar 2 litros de agua', 'salud'], ['Hacer ejercicio', 'salud'],
            ['Leer 20 minutos', 'aprendizaje'], ['Meditar 10 minutos', 'bienestar'], ['Salir a caminar', 'bienestar'],
        ];
        $filasHabitos = [];
        $posicionAEstudianteIdx = [];
        foreach ($estudiantes as $idx => $e) {
            $n = random_int(1, 3);
            $elegidos = array_rand($catalogoHabitos, min($n, count($catalogoHabitos)));
            foreach ((array) $elegidos as $ci) {
                $filasHabitos[] = [$e['id'], $catalogoHabitos[$ci][0], $catalogoHabitos[$ci][1], 'diaria', null, 1, FECHA_INICIO . ' 08:00:00'];
                $posicionAEstudianteIdx[count($filasHabitos) - 1] = $idx;
            }
        }
        insertarLote($pdo, 'habitos', ['usuario_id','nombre','categoria','frecuencia','dias_semana','activo','fecha_creacion'], $filasHabitos);
        $idsHabitos = $pdo->query(
            "SELECT h.id FROM habitos h INNER JOIN usuarios u ON u.id = h.usuario_id WHERE u.correo LIKE 'estudiante%@" . DOMINIO_DEMO . "' ORDER BY h.id"
        )->fetchAll(PDO::FETCH_COLUMN);

        $filasSeguimiento = [];
        foreach ($idsHabitos as $posicion => $habitoId) {
            $estudianteIdx = $posicionAEstudianteIdx[$posicion] ?? null;
            $participacion = $estudianteIdx !== null ? $estudiantes[$estudianteIdx]['participacion'] : 'medio';
            $probCompletar = ['alto' => 0.75, 'medio' => 0.5, 'bajo' => 0.25][$participacion];

            // Muestreo de días (cada 2-4 días) en vez de todos los días, para no disparar el volumen sin necesidad.
            $cursor = strtotime(FECHA_INICIO);
            while ($cursor <= $fechaFin) {
                if (random_int(1, 4) === 1) { // ~1 de cada 4 días se registra algo (con o sin cumplir)
                    $filasSeguimiento[] = [$habitoId, date('Y-m-d', $cursor), ((mt_rand() / mt_getrandmax()) < $probCompletar) ? 1 : 0];
                }
                $cursor = strtotime('+' . random_int(2, 4) . ' days', $cursor);
            }
        }
        insertarLote($pdo, 'seguimiento_habitos', ['habito_id','fecha','completado'], $filasSeguimiento);
        $resumen['habitos'] = count($idsHabitos);
        $resumen['seguimiento_habitos'] = count($filasSeguimiento);

        // --- 7. Metas + subtareas ---
        $catalogoMetas = ['Mejorar mis notas de matemáticas', 'Dormir mejor esta semana', 'Hacer más ejercicio',
            'Leer un libro completo', 'Hablar más con mis amigos', 'Manejar mejor el estrés de los exámenes'];
        $filasMetas = [];
        foreach ($estudiantes as $e) {
            $n = random_int(1, 2);
            for ($i = 0; $i < $n; $i++) {
                $estado = pesoAleatorio(['activa' => 60, 'completada' => 30, 'vencida' => 10]);
                $avance = $estado === 'completada' ? 100 : ($estado === 'vencida' ? random_int(0, 50) : random_int(10, 90));
                $filasMetas[] = [
                    $e['id'], null, elegir($catalogoMetas), null, elegir(['corto_plazo','largo_plazo']),
                    fechaAleatoriaEnRango(FECHA_INICIO, FECHA_HOY), $estado, $avance, FECHA_INICIO . ' 08:00:00',
                ];
            }
        }
        insertarLote($pdo, 'metas', ['usuario_id','habito_id','titulo','descripcion','tipo','fecha_limite','estado','porcentaje_avance','fecha_creacion'], $filasMetas);
        $resumen['metas'] = count($filasMetas);

        // --- 8. Diario (algunas entradas, no todos los días — es privado y de uso ocasional) ---
        $fragmentosDiario = [
            'Hoy fue un día tranquilo, nada fuera de lo normal.',
            'Tuve un examen y me sentí bastante nervioso antes de entrar.',
            'Hablé con mis amigos en el descanso y me sentí mejor.',
            'No dormí muy bien anoche, hoy estuve cansado todo el día.',
            'Logré terminar una tarea que llevaba días posponiendo.',
            'Discutí con alguien de mi familia, todavía me siento raro por eso.',
            'Fue un buen día, disfruté la clase de educación física.',
            'Estoy un poco estresado por los exámenes de la próxima semana.',
        ];
        $filasDiario = [];
        foreach ($estudiantes as $e) {
            $n = ['alto' => random_int(8, 20), 'medio' => random_int(3, 10), 'bajo' => random_int(0, 3)][$e['participacion']];
            for ($i = 0; $i < $n; $i++) {
                $filasDiario[] = [$e['id'], null, elegir($fragmentosDiario), fechaAleatoriaEnRango(FECHA_INICIO, FECHA_HOY) . ' ' . sprintf('%02d:%02d:00', random_int(18, 22), random_int(0, 59))];
            }
        }
        insertarLote($pdo, 'diario', ['usuario_id','titulo','contenido','fecha'], $filasDiario);
        $resumen['diario'] = count($filasDiario);

        // --- 9. Alertas tempranas (solo para el perfil de ánimo bajo, como sería en la realidad) ---
        $filasAlertas = [];
        foreach ($estudiantes as $e) {
            if ($e['animo'] === 'bajo' && (mt_rand() / mt_getrandmax()) < 0.6) {
                $filasAlertas[] = [$e['id'], 'emociones_bajas', 'Generada por datos de prueba: varios registros de ánimo bajo en el período.', elegir(['medio','alto']), (mt_rand()/mt_getrandmax()) < 0.4 ? 1 : 0, null, fechaAleatoriaEnRango(FECHA_INICIO, FECHA_HOY) . ' 09:00:00', null];
            }
        }
        insertarLote($pdo, 'alertas_tempranas', ['usuario_id','tipo','descripcion','nivel_urgencia','atendida','atendida_por','fecha_generada','fecha_atendida'], $filasAlertas);
        $resumen['alertas'] = count($filasAlertas);

        // --- 10. Citas con psicólogos/orientadores ---
        $filasCitas = [];
        $profesionalesCitas = array_merge($idsPorRol['psicologo'], $idsPorRol['orientador']);
        foreach (array_slice($estudiantes, 0, (int)(count($estudiantes) * 0.15)) as $e) {
            $fecha = fechaAleatoriaEnRango(FECHA_INICIO, FECHA_HOY);
            $horaInicio = sprintf('%02d:00:00', random_int(7, 15));
            $filasCitas[] = [
                elegir($profesionalesCitas), $e['id'], $fecha, $horaInicio,
                date('H:i:s', strtotime($horaInicio) + 1800), 'Seguimiento de bienestar (dato de prueba)',
                strtotime($fecha) < strtotime(FECHA_HOY) ? 'atendida' : 'confirmada', null, "{$fecha} 08:00:00",
            ];
        }
        // Las citas tienen restricción de franja única por profesional — se insertan una por una
        // (son relativamente pocas) para poder ignorar silenciosamente algún choque aleatorio.
        $insertadasCitas = 0;
        $stCita = $pdo->prepare('INSERT IGNORE INTO citas (profesional_id, estudiante_id, fecha, hora_inicio, hora_fin, motivo, estado, cancelado_por, creado_en) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($filasCitas as $fila) { if ($stCita->execute($fila)) $insertadasCitas += $stCita->rowCount(); }
        $resumen['citas'] = $insertadasCitas;

        $pdo->commit();
        $resultado = $resumen;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

if ($esCLI) {
    if ($error) { fwrite(STDERR, "Error: {$error}\n"); exit(1); }
    echo "Listo. Resumen:\n" . json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

$tituloPagina = 'Generar datos de prueba';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-saludo">
    <h1>Generar datos de prueba a escala institucional</h1>
    <p class="texto-suave">Crea una institución de prueba completa (grados, grupos, personal, 3000 estudiantes y ~8 meses de historial) para probar el sistema con volumen real.</p>
</div>

<?php if ($error): ?><p class="mensaje mensaje-error">Ocurrió un error y no se aplicó nada (se revirtió todo): <?= htmlspecialchars($error) ?></p><?php endif; ?>

<?php if ($resultado): ?>
    <div class="mensaje mensaje-exito">
        <p><strong>✅ Datos de prueba generados.</strong></p>
        <ul class="lista-simple">
            <?php foreach ($resultado as $clave => $valor): ?>
                <li><?= htmlspecialchars($clave) ?>: <?= is_array($valor) ? htmlspecialchars(json_encode($valor)) : htmlspecialchars((string)$valor) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="panel-seccion">
    <p>Correos de todo lo generado terminan en <code>@<?= DOMINIO_DEMO ?></code> y los grados llevan "(Demo)" en el nombre — así <code>eliminar_datos_prueba.php</code> puede borrar exactamente esto, sin tocar nada real.</p>
    <p class="mensaje mensaje-error">
        ⚠️ Con 3000 estudiantes y ~8 meses de historial esto genera varios cientos de miles de filas.
        Puede tardar varios minutos. Recomendado correrlo en local (Laragon/XAMPP), no en InfinityFree.
    </p>
    <button type="button" class="boton-acceso" data-abrir-modal="modal-generar">🏫 Generar institución de prueba</button>
    <p class="texto-suave" style="margin-top:var(--espacio-md)">
        ¿Ya la generaste y quieres quitarla? <a href="eliminar_datos_prueba.php">Eliminar datos de prueba</a>.
    </p>
</section>

<div class="modal-fondo" id="modal-generar">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-cabecera">
            <h2>¿Generar datos de prueba?</h2>
            <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">✕</button>
        </div>
        <p>Se van a crear ~3157 usuarios y varios cientos de miles de registros de actividad. No afecta tus datos reales — todo queda marcado como institución de prueba y se puede eliminar después con un clic.</p>
        <div class="modal-acciones">
            <button type="button" class="boton-secundario" data-cerrar-modal>Cancelar</button>
            <form action="generar_datos_prueba.php" method="POST" style="margin:0">
                <?php campoCSRF(); ?>
                <button type="submit" class="boton-acceso">Sí, generar ahora</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
