<?php
declare(strict_types=1);

require_once __DIR__ . '/Academico.php';
require_once __DIR__ . '/Expediente.php';
require_once __DIR__ . '/../helpers/configuracion.php';
require_once __DIR__ . '/../helpers/subidaImagenes.php';

/**
 * Hoja de vida del estudiante con la estructura del Sistema Académico Master2000.
 * Requiere la migración 038_hoja_vida_master.sql (y la 036_expediente.sql).
 *
 * DATOS SENSIBLES DE MENORES. El acceso se decide en tres pasos que SIEMPRE van juntos:
 *   1) Expediente::puedeAccederEstudiante()  → ¿puede esta persona abrir a ESTE estudiante?
 *   2) HojaVida::permisos()                  → ¿qué pestañas ve y qué puede escribir?
 *   3) HojaVida::puedeVer()/puedeEditar()    → ¿qué campos concretos? (salud y población son más restringidos)
 */
class HojaVida
{
    /** Qué ve el acudiente además de Matrícula, Aprobación, Observaciones (certificados), Anecdotario y Hoja de vida
     *  (solo las anotaciones marcadas «visible para la familia»). Cámbialo aquí si el colegio decide otra cosa. */
    public const ACUDIENTE_VE = ['desarrollo' => false, 'procesos' => false, 'salud_sensible' => false];

    public const TABS = [
        'matricula' => 'Matrícula', 'desarrollo' => 'Desarrollo', 'aprobacion' => 'Aprobación ant.', 'observaciones' => 'Observaciones',
        'apoyo' => 'Aula de apoyo', 'anecdotario' => 'Anecdotario', 'observador' => 'Hoja de vida', 'procesos' => 'Procesos disciplinarios',
        'certificados' => 'Certificados', 'horas' => 'Horas formativas', 'asistencia' => 'Asistencia',
    ];
    public const SUBTABS = ['datos' => 'Datos del estudiante', 'familia' => 'Familia', 'caracteristicas' => 'Características', 'salud' => 'Salud'];

    public const ESCALA = ['muy_bueno' => 'Muy bueno', 'bueno' => 'Bueno', 'regular' => 'Regular', 'deficiente' => 'Deficiente'];
    public const SEMESTRES = [1 => 'Semestre 1', 2 => 'Semestre 2'];
    public const CRITERIOS = [
        'percepcion_visual_izq' => 'Percepción visual izquierda', 'percepcion_visual_der' => 'Percepción visual derecha',
        'percepcion_auditiva_izq' => 'Percepción auditiva izquierda', 'percepcion_auditiva_der' => 'Percepción auditiva derecha',
        'lateralidad' => 'Lateralidad', 'comprension_linguistica' => 'Comprensión lingüística', 'rendimiento_academico' => 'Rendimiento académico',
        'razonamiento' => 'Razonamiento', 'iniciativa' => 'Iniciativa', 'valoracion_personal' => 'Valoración personal',
        'seguridad_en_si_mismo' => 'Seguridad en sí mismo', 'estabilidad_emocional' => 'Estabilidad emocional', 'liderazgo' => 'Liderazgo',
        'trabajo_en_grupo' => 'Capacidad para trabajar en grupo',
    ];
    /** Núcleo familiar. Para sumar «Responsable de pagos» o «Codeudor» agrega la clave aquí (no requiere migración). */
    public const FAMILIARES = ['madre' => 'Madre', 'padre' => 'Padre', 'acudiente' => 'Acudiente'];
    /** campo => [etiqueta, tipo (t texto, d fecha, s sí/no), máximo, formato]. Se omite «Ingresos» a propósito (dato financiero innecesario). */
    public const FAMILIAR_CAMPOS = [
        'vive' => ['¿Vive?', 's', 1, ''], 'tipo_documento' => ['Tipo de documento', 'td', 5, ''], 'numero_documento' => ['Número de documento', 't', 20, 'doc'],
        'lugar_expedicion' => ['Lugar de expedición', 't', 100, ''], 'fecha_expedicion' => ['Fecha de expedición', 'd', 10, ''],
        'nacionalidad' => ['Nacionalidad', 't', 40, ''], 'apellido1' => ['Primer apellido', 't', 40, ''], 'apellido2' => ['Segundo apellido', 't', 40, ''],
        'nombre1' => ['Primer nombre', 't', 40, ''], 'nombre2' => ['Segundo nombre', 't', 40, ''], 'fecha_nacimiento' => ['Fecha de nacimiento', 'd', 10, ''],
        'telefono' => ['Teléfono', 't', 20, 'tel'], 'celular' => ['Celular', 't', 20, 'tel'], 'email' => ['Correo electrónico', 't', 150, 'email'],
        'direccion' => ['Dirección', 't', 200, ''], 'municipio' => ['Municipio de residencia', 't', 100, ''], 'escolaridad' => ['Escolaridad', 't', 60, ''],
        'profesion' => ['Profesión', 't', 80, ''], 'empresa' => ['Empresa', 't', 100, ''], 'cargo' => ['Cargo', 't', 80, ''],
        'telefono_trabajo' => ['Teléfono del trabajo', 't', 20, 'tel'],
    ];
    public const EVIDENCIAS_MAX = 5;
    private const HV_COLUMNAS = ['tipo_documento', 'numero_documento', 'genero', 'lugar_nacimiento', 'direccion', 'barrio', 'telefono_alterno', 'vive_con', 'eps', 'grupo_sanguineo', 'alergias', 'condiciones_salud', 'medicamentos'];

    // ------------------------------------------------------------------
    // Catálogo de campos (qué se pide en cada pestaña)
    // ------------------------------------------------------------------

    private static function lista(array $v): array
    {
        return array_combine($v, $v);
    }

    /**
     * subpestaña => [sección => [campo => spec]].
     * spec: l etiqueta · t tipo (t texto, d fecha, s lista, n número, a texto largo, r solo lectura) · op opciones · hv columna de hojas_vida
     *       inst solo la institución lo edita · sens salud|etnia (más restringido) · req obligatorio · fmt doc|tel|email · max · full ancho completo
     */
    public static function estructura(): array
    {
        static $e = null;
        if ($e !== null) {
            return $e;
        }
        $t = static fn (string $l, array $o = []): array => ['l' => $l, 't' => 't'] + $o;
        $d = static fn (string $l, array $o = []): array => ['l' => $l, 't' => 'd'] + $o;
        $n = static fn (string $l, array $o = []): array => ['l' => $l, 't' => 'n'] + $o;
        $a = static fn (string $l, array $o = []): array => ['l' => $l, 't' => 'a'] + $o;
        $r = static fn (string $l, string $src): array => ['l' => $l, 't' => 'r', 'src' => $src];
        $s = static fn (string $l, array $op, array $o = []): array => ['l' => $l, 't' => 's', 'op' => $op] + $o;
        $sn = ['S' => 'Sí', 'N' => 'No'];
        $calidad = self::lista(['Excelentes', 'Buenas', 'Regulares', 'Malas']);
        $e = [
            'datos' => [
                'Identificación' => [
                    'tipo_documento' => $s('Tipo de documento', Academico::TIPOS_DOCUMENTO, ['hv' => true, 'req' => true]),
                    'numero_documento' => $t('Número de documento', ['hv' => true, 'req' => true, 'fmt' => 'doc', 'max' => 20]),
                    'lugar_expedicion' => $t('Lugar de expedición', ['max' => 100]),
                    'fecha_expedicion' => $d('Fecha de expedición'),
                ],
                'Perfil' => [
                    'nombre_completo' => $r('Nombre completo', 'nombre'),
                    'apellido1' => $t('Primer apellido', ['max' => 40]), 'apellido2' => $t('Segundo apellido', ['max' => 40]),
                    'nombre1' => $t('Primer nombre', ['max' => 40]), 'nombre2' => $t('Segundo nombre', ['max' => 40]),
                    'genero' => $s('Género', ['F' => 'Femenino', 'M' => 'Masculino', 'O' => 'Otro'], ['hv' => true]),
                    'correo_estudiante' => $r('Correo del estudiante', 'correo'),
                ],
                'Matrícula' => [
                    'numero_matricula' => $t('Número de matrícula', ['inst' => true, 'max' => 20]),
                    'folio_matricula' => $t('Folio', ['inst' => true, 'max' => 20]),
                    'fecha_matricula' => $d('Fecha de matrícula', ['inst' => true]),
                    'fecha_renovacion' => $d('Fecha de renovación', ['inst' => true]),
                    'numero_alterno' => $t('Número alterno', ['inst' => true, 'max' => 20]),
                    'grupo' => $r('Grupo', 'grupo'),
                    'numero_orden' => $n('Número de orden', ['inst' => true]),
                    'matriculado' => $r('Matriculado (año actual)', 'matriculado'),
                    'metodologia' => $t('Metodología', ['inst' => true, 'max' => 60]),
                    'especialidad' => $t('Especialidad', ['inst' => true, 'max' => 80]),
                    'identificador_pagos' => $t('Identificador para pagos', ['inst' => true, 'max' => 30]),
                    'correo_institucional' => $t('Correo institucional', ['inst' => true, 'fmt' => 'email', 'max' => 150]),
                ],
                'Dirección' => [
                    'direccion' => $t('Dirección', ['hv' => true, 'max' => 200]),
                    'municipio_residencia' => $t('Municipio de residencia', ['max' => 100]),
                    'comuna' => $t('Comuna, distrito o localidad', ['max' => 60]),
                    'corregimiento' => $t('Corregimiento o vereda', ['max' => 80]),
                    'estrato' => $s('Estrato', self::lista(['1', '2', '3', '4', '5', '6'])),
                    'celular' => $t('Celular', ['fmt' => 'tel', 'max' => 20]),
                    'barrio' => $t('Barrio', ['hv' => true, 'max' => 80]),
                    'zona' => $s('Zona', self::lista(['Urbana', 'Rural'])),
                    'telefono_alterno' => $t('Teléfono', ['hv' => true, 'fmt' => 'tel', 'max' => 20]),
                    'vive_con' => $t('¿Con quién vive?', ['hv' => true, 'max' => 120]),
                    'celular_responsable' => $t('Celular del responsable de la residencia', ['fmt' => 'tel', 'max' => 20]),
                    'email_responsable' => $t('Correo del responsable de la residencia', ['fmt' => 'email', 'max' => 150]),
                ],
                'Nacimiento' => [
                    'fecha_nacimiento' => $r('Fecha de nacimiento', 'nacimiento'),
                    'barrio_nacimiento' => $t('Barrio de nacimiento', ['max' => 80]),
                    'corregimiento_nacimiento' => $t('Corregimiento o vereda de nacimiento', ['max' => 80]),
                    'lugar_nacimiento' => $t('Municipio de nacimiento', ['hv' => true, 'max' => 100]),
                    'zona_nacimiento' => $s('Zona de nacimiento', self::lista(['Urbana', 'Rural'])),
                    'nacionalidad' => $t('Nacionalidad', ['max' => 40]),
                ],
            ],
            'familia' => [
                'Relación con sus padres' => [
                    'relacion_padres' => $s('Relaciones con los padres', $calidad),
                    'beneficiario_madre_cabeza' => $s('¿Beneficiario hijo dependiente de madre cabeza de familia?', $sn),
                    'beneficiario_heroe_nacion' => $s('¿Es beneficiario héroe de la nación?', $sn),
                    'beneficiario_veterano' => $s('¿Es beneficiario veterano de la fuerza pública?', $sn),
                ],
                'Hermanos' => [
                    'hermanos_total' => $n('Hermanos'), 'hermanos_hombres' => $n('Hombres'), 'hermanos_mujeres' => $n('Mujeres'),
                    'lugar_ocupa' => $n('Lugar que ocupa'),
                ],
                'Otros datos familiares' => [
                    'condiciones_economicas' => $s('Condiciones económicas de la familia', $calidad),
                    'hermanos_institucion' => $s('¿Tiene hermanos en la institución?', $sn),
                    'hijo_egresado' => $s('¿Hijo de egresado de la institución?', $sn),
                    'subsidio' => $t('¿La familia recibe algún subsidio de alguna entidad o institución?', ['max' => 120, 'full' => true]),
                ],
            ],
            'caracteristicas' => [
                'Situación actual en el establecimiento' => [
                    'comienza_estudios' => $s('¿Comienza sus estudios?', $sn), 'estudiante_antiguo' => $s('¿Es estudiante antiguo?', $sn),
                    'otra_institucion' => $s('¿Ha estado vinculado en otra institución educativa?', $sn), 'sector_privado' => $s('¿Viene sector privado?', $sn),
                    'poblacion_vulnerable' => $s('Población vulnerable', self::lista(['Ninguna', 'Víctima del conflicto armado', 'Desplazado', 'Desmovilizado o hijo de desmovilizado', 'Población de frontera', 'Otra']), ['sens' => 'etnia']),
                    'anio_ultimo_grado' => $n('Año en que cursó el último grado'), 'ultimo_grado' => $t('Último grado cursado', ['max' => 30]),
                    'situacion_anterior' => $s('Situación académica del año anterior', self::lista(['Aprobó', 'Reprobó', 'Sin información'])),
                    'grado_aspira' => $t('Grado al que aspira', ['max' => 30]), 'repitente' => $s('Repitente', $sn),
                    'motivo_repitencia' => $t('Motivo de la repitencia', ['max' => 150]),
                ],
                'Transporte' => [
                    'medio_transporte' => $t('Medio de transporte', ['max' => 60]), 'ruta' => $t('Ruta', ['max' => 60]),
                    'costo_pasaje' => $t('Costo del pasaje', ['max' => 20]), 'tiempo_llegar' => $n('Tiempo en llegar, en minutos'),
                ],
                'Seguros, pagos y becas' => [
                    'ampliacion_cobertura' => $s('¿Ampliación de cobertura?', $sn), 'asegurado_por' => $t('Asegurado por', ['max' => 80]),
                    'subsidiado' => $s('¿Subsidiado?', $sn), 'becado' => $s('¿Es becado?', $sn), 'tipo_beca' => $t('Tipo de beca', ['max' => 80]),
                ],
                'Religión, grupo étnico y currículo' => [
                    'religion' => $t('Religión', ['sens' => 'etnia', 'max' => 60]), 'poblacion' => $t('Población', ['sens' => 'etnia', 'max' => 60]),
                    'indigena' => $s('¿Indígena?', $sn, ['sens' => 'etnia']), 'grupo_etnico' => $t('Grupo étnico', ['sens' => 'etnia', 'max' => 60]),
                    'lengua_materna' => $t('Lengua materna de la etnia', ['sens' => 'etnia', 'max' => 60]), 'resguardo' => $t('Resguardo', ['sens' => 'etnia', 'max' => 60]),
                    'curriculo' => $t('Currículo aplicado', ['max' => 80]),
                ],
                'Trabajo' => [
                    'trabaja' => $s('¿Trabaja actualmente?', $sn), 'ocupacion' => $t('Ocupación', ['max' => 80]), 'empresa_trabajo' => $t('Empresa', ['max' => 100]),
                    'telefono_trabajo' => $t('Teléfono del trabajo', ['fmt' => 'tel', 'max' => 20]), 'hobbie' => $t('¿Hobbie o pasatiempo?', ['max' => 100]),
                    'caja_compensacion' => $t('Caja de compensación', ['max' => 80]), 'tipo_usuario' => $t('Tipo de usuario', ['max' => 40]),
                ],
            ],
            'salud' => [
                'Salud' => [
                    'eps' => $t('EPS', ['hv' => true, 'max' => 80]),
                    'afiliacion' => $s('Régimen de afiliación', self::lista(['Contributivo', 'Subsidiado', 'Especial', 'No afiliado'])),
                    'sisben' => $t('SISBÉN (grupo o puntaje)', ['max' => 20]),
                    'grupo_sanguineo' => $s('Grupo sanguíneo', self::lista(Expediente::GRUPOS_SANGUINEOS), ['hv' => true]),
                    'alergias' => $t('Alergias (las ven los docentes del estudiante)', ['hv' => true, 'max' => 255, 'full' => true]),
                    'condiciones_salud' => $a('Condiciones de salud (reservado: orientación y directivos)', ['hv' => true, 'sens' => 'salud', 'max' => 500, 'full' => true]),
                    'medicamentos' => $t('Medicamentos (reservado)', ['hv' => true, 'sens' => 'salud', 'max' => 255, 'full' => true]),
                    'discapacidad' => $t('Discapacidad o capacidad excepcional (reservado)', ['sens' => 'salud', 'max' => 150, 'full' => true]),
                ],
            ],
        ];
        return $e;
    }

    // ------------------------------------------------------------------
    // Permisos
    // ------------------------------------------------------------------

    public static function esDirectorDeGrupo(PDO $pdo, int $docenteId, int $estudianteId): bool
    {
        $q = $pdo->prepare('SELECT 1 FROM usuarios u INNER JOIN grupos g ON g.id = u.grupo_id WHERE u.id = ? AND g.docente_id = ?');
        $q->execute([$estudianteId, $docenteId]);
        return (bool) $q->fetchColumn();
    }

    /** Ventana en la que el acudiente puede modificar los datos. Sin fechas, queda cerrada (solo lectura). */
    public static function ventana(PDO $pdo, int $colegioId): array
    {
        $desde = configuracionObtener($pdo, 'hv_edicion_desde', '', $colegioId);
        $hasta = configuracionObtener($pdo, 'hv_edicion_hasta', '', $colegioId);
        $hoy = date('Y-m-d');
        return ['desde' => $desde, 'hasta' => $hasta, 'abierta' => $desde !== '' && $hasta !== '' && $hoy >= $desde && $hoy <= $hasta];
    }

    public static function guardarVentana(PDO $pdo, int $colegioId, string $desde, string $hasta): string
    {
        if ($desde === '' && $hasta === '') {
            configuracionGuardar($pdo, 'hv_edicion_desde', '', $colegioId);
            configuracionGuardar($pdo, 'hv_edicion_hasta', '', $colegioId);
            return '';
        }
        if (!self::fechaOk($desde) || !self::fechaOk($hasta) || $hasta < $desde) {
            return 'Las fechas de la ventana no son válidas (la final no puede ser anterior a la inicial).';
        }
        configuracionGuardar($pdo, 'hv_edicion_desde', $desde, $colegioId);
        configuracionGuardar($pdo, 'hv_edicion_hasta', $hasta, $colegioId);
        return '';
    }

    public static function permisos(PDO $pdo, string $rol, int $usuarioId, int $estudianteId, int $colegioId): array
    {
        $cap = Expediente::capacidades($rol);
        $dir = in_array($rol, ['coordinador', 'rector', 'administrador'], true);
        $ori = in_array($rol, ['psicologo', 'orientador'], true);
        $sec = $rol === 'secretaria_academica';
        $doc = $rol === 'docente';
        $acu = $rol === 'acudiente';
        $est = $rol === 'estudiante';
        $plat = in_array($rol, ['superadmin', 'secretaria'], true); // solo lectura, del colegio en contexto
        $director = $doc && self::esDirectorDeGrupo($pdo, $usuarioId, $estudianteId);
        $ventana = $acu ? self::ventana($pdo, $colegioId) : ['desde' => '', 'hasta' => '', 'abierta' => false];
        $p = [
            'rol' => $rol, 'cap' => $cap, 'acudiente' => $acu, 'director' => $director, 'ventana' => $ventana,
            'inst' => $dir || $ori || $sec,
            'edita_datos' => (($dir || $ori || $sec) || ($acu && $ventana['abierta'])),
            'ver_salud_sens' => $dir || $ori || ($acu && self::ACUDIENTE_VE['salud_sensible']),
            'ver_etnia' => $dir || $ori || $sec || $acu,
            'ver_datos_completos' => $dir || $ori || $sec || $acu || $est || $plat,
            'ver_desarrollo' => $dir || $ori || $director || ($acu && self::ACUDIENTE_VE['desarrollo']),
            'escribir_desarrollo' => $dir || $ori || $director,
            'escribir_aprobacion' => $dir || $sec,
            'ver_obs_temporales' => $dir || $ori || $sec || $doc || $plat,
            'escribir_obs_temporales' => $dir || $ori || $sec,
            'escribir_obs_certificados' => $dir || $sec,
            'pasar_observador' => $dir || $ori,
            'pasar_proceso' => $dir || $doc,
            'revisar' => $dir,
            'ventana_admin' => $dir || $sec,
            'ver_procesos_lista' => $cap['ver_convivencia'] || ($acu && self::ACUDIENTE_VE['procesos']),
        ];
        // Todos los roles tienen hoja de vida; cada uno ve las pestañas que le corresponden (estudiante: la suya, en lectura).
        $tabs = [];
        if ($cap['personal'] || $acu || $est) { $tabs[] = 'matricula'; }
        if ($p['ver_desarrollo']) { $tabs[] = 'desarrollo'; }
        if ($dir || $ori || $sec || $acu || $est || $plat) { $tabs[] = 'aprobacion'; }
        if ($dir || $ori || $sec || $doc || $acu || $est || $plat) { $tabs[] = 'observaciones'; }
        if ($cap['ver_apoyo_completo'] || $cap['ver_apoyo_ajustes']) { $tabs[] = 'apoyo'; }
        if ($cap['ver_observador'] || $cap['ver_obs_familia']) { $tabs[] = 'anecdotario'; $tabs[] = 'observador'; }
        if ($p['ver_procesos_lista'] || $cap['reportar_convivencia']) { $tabs[] = 'procesos'; }
        $tabs[] = 'certificados';
        $tabs[] = 'horas';
        $tabs[] = 'asistencia';
        $p['tabs'] = $tabs;
        return $p;
    }

    public static function puedeVerCampo(array $c, array $perm): bool
    {
        $s = $c['sens'] ?? '';
        if ($s === 'salud') { return $perm['ver_salud_sens']; }
        if ($s === 'etnia') { return $perm['ver_etnia']; }
        return true;
    }

    public static function puedeEditarCampo(array $c, array $perm): bool
    {
        if (!$perm['edita_datos'] || ($c['t'] ?? '') === 'r' || !self::puedeVerCampo($c, $perm)) {
            return false;
        }
        return !(!empty($c['inst']) && $perm['acudiente']);
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    private static function txt(mixed $v, int $max): ?string
    {
        $t = mb_substr(trim((string) $v), 0, $max);
        return $t === '' ? null : $t;
    }

    public static function fechaOk(string $f): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $f);
        return $d !== false && $d->format('Y-m-d') === $f;
    }

    private static function limpiar(string $valor, array $c): ?string
    {
        $fmt = (string) ($c['fmt'] ?? '');
        if ($fmt === 'doc') { $valor = (string) preg_replace('/[^A-Za-z0-9]/', '', $valor); }
        if ($fmt === 'tel') { $valor = (string) preg_replace('/[^0-9+]/', '', $valor); }
        if (($c['t'] ?? '') === 'n') { $valor = (string) preg_replace('/\D/', '', $valor); }
        $max = (int) ($c['max'] ?? (($c['t'] ?? '') === 'n' ? 3 : (($c['t'] ?? '') === 'a' ? 500 : 120)));
        return self::txt($valor, $max);
    }

    // ------------------------------------------------------------------
    // Contexto y valores
    // ------------------------------------------------------------------

    /** Datos que vienen de otras partes de Serena y aquí solo se muestran. */
    public static function contexto(PDO $pdo, int $estudianteId, int $colegioId): array
    {
        $b = Expediente::estudianteBasico($pdo, $estudianteId, $colegioId) ?? [];
        $q = $pdo->prepare("SELECT 1 FROM matriculas m INNER JOIN anios_lectivos a ON a.id = m.anio_lectivo_id AND a.actual = 1 WHERE m.estudiante_id = ? AND m.estado = 'activa' LIMIT 1");
        $q->execute([$estudianteId]);
        $nac = (string) ($b['fecha_nacimiento'] ?? '');
        $edad = $nac !== '' ? (int) (new DateTime($nac))->diff(new DateTime('today'))->y : null;
        $codigo = '';
        try {
            $c = $pdo->prepare('SELECT codigo_matricula FROM usuarios WHERE id = ? AND colegio_id = ?');
            $c->execute([$estudianteId, $colegioId]);
            $codigo = (string) ($c->fetchColumn() ?: '');
        } catch (PDOException $ex) {
            $codigo = ''; // la migración 038_codigo_matricula aún no se aplicó
        }
        return [
            'codigo_matricula' => $codigo,
            'nombre' => (string) ($b['nombre'] ?? ''), 'correo' => (string) ($b['correo'] ?? ''),
            'grupo' => isset($b['grupo']) && $b['grupo'] ? trim((string) ($b['grado'] ?? '') . ' ' . $b['grupo']) : 'Sin grupo asignado',
            'matriculado' => $q->fetchColumn() ? 'Sí' : 'No',
            'nacimiento' => $nac !== '' ? $nac . ' (' . $edad . ' años)' : '—',
        ];
    }

    /** Todo lo que está guardado: columnas de hojas_vida + campos adicionales (clave => valor). */
    public static function valores(PDO $pdo, int $estudianteId): array
    {
        $out = [];
        $q = $pdo->prepare('SELECT * FROM hojas_vida WHERE estudiante_id = ?');
        $q->execute([$estudianteId]);
        $hv = $q->fetch() ?: [];
        foreach (self::HV_COLUMNAS as $col) {
            if (isset($hv[$col])) { $out[$col] = (string) $hv[$col]; }
        }
        $q = $pdo->prepare('SELECT clave, valor FROM hoja_vida_datos WHERE estudiante_id = ?');
        $q->execute([$estudianteId]);
        foreach ($q->fetchAll() as $f) {
            $out[(string) $f['clave']] = (string) ($f['valor'] ?? '');
        }
        return $out;
    }

    public static function ultimaModificacion(PDO $pdo, int $estudianteId): ?array
    {
        $q = $pdo->prepare('SELECT t.en, u.nombre FROM (
                SELECT actualizado_en AS en, actualizado_por AS por FROM hojas_vida WHERE estudiante_id = ?
                UNION ALL SELECT actualizado_en, actualizado_por FROM hoja_vida_datos WHERE estudiante_id = ?
                UNION ALL SELECT actualizado_en, actualizado_por FROM familiares WHERE estudiante_id = ?) t
              LEFT JOIN usuarios u ON u.id = t.por ORDER BY t.en DESC LIMIT 1');
        $q->execute([$estudianteId, $estudianteId, $estudianteId]);
        return $q->fetch() ?: null;
    }

    /** Guarda SOLO los campos de la subpestaña que esta persona puede editar. Devuelve un error o ''. */
    public static function guardarSub(PDO $pdo, int $colegioId, int $estudianteId, string $sub, array $post, array $perm, int $porId): string
    {
        $est = self::estructura();
        if (!isset($est[$sub]) || !$perm['edita_datos']) {
            return 'No tienes permiso para esa acción.';
        }
        $actuales = self::valores($pdo, $estudianteId);
        $hv = [];
        $kv = [];
        foreach ($est[$sub] as $campos) {
            foreach ($campos as $clave => $c) {
                if (!self::puedeEditarCampo($c, $perm)) { continue; }
                $v = self::limpiar((string) ($post[$clave] ?? ''), $c);
                if (($c['req'] ?? false) && $v === null) { return 'Falta un dato obligatorio: ' . $c['l'] . '.'; }
                if ($v !== null && $c['t'] === 's' && !isset($c['op'][$v]) && $v !== ($actuales[$clave] ?? null)) { return 'Valor no válido en: ' . $c['l'] . '.'; }
                if ($v !== null && $c['t'] === 'd' && !self::fechaOk($v)) { return 'Fecha no válida en: ' . $c['l'] . '.'; }
                if ($v !== null && ($c['fmt'] ?? '') === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { return 'Correo no válido en: ' . $c['l'] . '.'; }
                if (!empty($c['hv'])) { $hv[$clave] = $v; } else { $kv[$clave] = $v; }
            }
        }
        if ($hv) {
            $cols = array_keys($hv);
            $pdo->prepare('INSERT INTO hojas_vida (estudiante_id, colegio_id, actualizado_por, ' . implode(', ', $cols) . ') VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')
                ON DUPLICATE KEY UPDATE actualizado_por = VALUES(actualizado_por), ' . implode(', ', array_map(static fn (string $c): string => "{$c} = VALUES({$c})", $cols)))
                ->execute(array_merge([$estudianteId, $colegioId, $porId], array_values($hv)));
        }
        foreach ($kv as $clave => $v) {
            self::guardarDato($pdo, $estudianteId, $clave, $v, $porId);
        }
        return '';
    }

    public static function guardarDato(PDO $pdo, int $estudianteId, string $clave, ?string $valor, int $porId): void
    {
        if ($valor === null) {
            $pdo->prepare('DELETE FROM hoja_vida_datos WHERE estudiante_id = ? AND clave = ?')->execute([$estudianteId, $clave]);
            return;
        }
        $pdo->prepare('INSERT INTO hoja_vida_datos (estudiante_id, clave, valor, actualizado_por) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_por = VALUES(actualizado_por)')->execute([$estudianteId, $clave, $valor, $porId]);
    }

    // ------------------------------------------------------------------
    // Núcleo familiar y establecimientos anteriores
    // ------------------------------------------------------------------

    public static function familiares(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare('SELECT * FROM familiares WHERE estudiante_id = ?');
        $q->execute([$estudianteId]);
        $out = [];
        foreach ($q->fetchAll() as $f) { $out[(string) $f['parentesco']] = $f; }
        return $out;
    }

    public static function guardarFamiliar(PDO $pdo, int $estudianteId, string $parentesco, array $post, int $porId): string
    {
        if (!isset(self::FAMILIARES[$parentesco])) {
            return 'Parentesco no válido.';
        }
        $vals = [];
        foreach (self::FAMILIAR_CAMPOS as $col => [$etq, $tipo, $max, $fmt]) {
            $v = self::limpiar((string) ($post[$col] ?? ''), ['t' => 't', 'max' => $max, 'fmt' => $fmt]);
            if ($v !== null && $tipo === 'd' && !self::fechaOk($v)) { return 'Fecha no válida en: ' . $etq . '.'; }
            if ($v !== null && $tipo === 's' && !in_array($v, ['S', 'N'], true)) { return 'Valor no válido en: ' . $etq . '.'; }
            if ($v !== null && $tipo === 'td' && !isset(Academico::TIPOS_DOCUMENTO[$v])) { return 'Tipo de documento no válido.'; }
            if ($v !== null && $fmt === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { return 'Correo no válido en: ' . $etq . '.'; }
            $vals[$col] = $v;
        }
        $cols = array_keys($vals);
        $pdo->prepare('INSERT INTO familiares (estudiante_id, parentesco, actualizado_por, ' . implode(', ', $cols) . ') VALUES (?, ?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')
            ON DUPLICATE KEY UPDATE actualizado_por = VALUES(actualizado_por), ' . implode(', ', array_map(static fn (string $c): string => "{$c} = VALUES({$c})", $cols)))
            ->execute(array_merge([$estudianteId, $parentesco, $porId], array_values($vals)));
        return '';
    }

    public static function previos(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare('SELECT * FROM establecimientos_previos WHERE estudiante_id = ? ORDER BY anio DESC, id DESC');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    public static function agregarPrevio(PDO $pdo, int $estudianteId, array $d): string
    {
        $nombre = self::txt($d['p_nombre'] ?? '', 150);
        if ($nombre === null) {
            return 'Escribe el nombre del establecimiento.';
        }
        $anio = (int) ($d['p_anio'] ?? 0);
        if ($anio !== 0 && ($anio < 1980 || $anio > (int) date('Y'))) {
            return 'El año no es válido.';
        }
        $pdo->prepare('INSERT INTO establecimientos_previos (estudiante_id, grado, anio, municipio, dane, nombre, director_grupo, causa_cambio, causa_reprobacion) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$estudianteId, self::txt($d['p_grado'] ?? '', 30), $anio ?: null, self::txt($d['p_municipio'] ?? '', 100),
                self::txt(preg_replace('/\D/', '', (string) ($d['p_dane'] ?? '')), 20), $nombre, self::txt($d['p_director'] ?? '', 120),
                self::txt($d['p_causa_cambio'] ?? '', 150), self::txt($d['p_causa_reprobacion'] ?? '', 150)]);
        return '';
    }

    public static function eliminarPrevio(PDO $pdo, int $id, int $estudianteId): void
    {
        $pdo->prepare('DELETE FROM establecimientos_previos WHERE id = ? AND estudiante_id = ?')->execute([$id, $estudianteId]);
    }

    // ------------------------------------------------------------------
    // Desarrollo
    // ------------------------------------------------------------------

    /** [semestre][criterio] => fila */
    public static function desarrollo(PDO $pdo, int $estudianteId, int $anioId): array
    {
        $q = $pdo->prepare('SELECT * FROM desarrollo_estudiante WHERE estudiante_id = ? AND anio_lectivo_id = ?');
        $q->execute([$estudianteId, $anioId]);
        $out = [];
        foreach ($q->fetchAll() as $f) { $out[(int) $f['semestre']][(string) $f['criterio']] = $f; }
        return $out;
    }

    public static function guardarDesarrollo(PDO $pdo, int $estudianteId, int $anioId, int $semestre, array $d, int $porId): string
    {
        if (!isset(self::SEMESTRES[$semestre])) {
            return 'Semestre no válido.';
        }
        $filas = [];
        foreach (self::CRITERIOS as $k => $_) {
            $v = (string) ($d[$k]['v'] ?? '');
            if ($v !== '' && !isset(self::ESCALA[$v])) { return 'Valoración no válida.'; }
            $filas[$k] = [$v !== '' ? $v : null, self::txt($d[$k]['tf'] ?? '', 300), self::txt($d[$k]['ti'] ?? '', 300)];
        }
        $pdo->beginTransaction();
        try {
            foreach ($filas as $k => [$v, $tf, $ti]) {
                if ($v === null && $tf === null && $ti === null) {
                    $pdo->prepare('DELETE FROM desarrollo_estudiante WHERE estudiante_id = ? AND anio_lectivo_id = ? AND semestre = ? AND criterio = ?')->execute([$estudianteId, $anioId, $semestre, $k]);
                    continue;
                }
                $pdo->prepare('INSERT INTO desarrollo_estudiante (estudiante_id, anio_lectivo_id, semestre, criterio, valoracion, tratamiento_familiar, tratamiento_institucional, actualizado_por)
                               VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE valoracion = VALUES(valoracion), tratamiento_familiar = VALUES(tratamiento_familiar),
                               tratamiento_institucional = VALUES(tratamiento_institucional), actualizado_por = VALUES(actualizado_por)')
                    ->execute([$estudianteId, $anioId, $semestre, $k, $v, $tf, $ti, $porId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('HojaVida::guardarDesarrollo: ' . $e->getMessage());
            return 'No se pudo guardar el desarrollo.';
        }
        return '';
    }

    // ------------------------------------------------------------------
    // Aprobación anticipada y observaciones
    // ------------------------------------------------------------------

    public static function aprobaciones(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare('SELECT a.*, y.anio, go.nombre AS grado_origen, gd.nombre AS grado_destino, u.nombre AS registrador
                              FROM aprobaciones_anticipadas a LEFT JOIN anios_lectivos y ON y.id = a.anio_lectivo_id
                              LEFT JOIN grados go ON go.id = a.grado_origen_id LEFT JOIN grados gd ON gd.id = a.grado_destino_id
                              LEFT JOIN usuarios u ON u.id = a.registrado_por WHERE a.estudiante_id = ? ORDER BY a.fecha DESC, a.id DESC');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    public static function agregarAprobacion(PDO $pdo, int $colegioId, int $estudianteId, array $d, int $porId): string
    {
        $fecha = (string) ($d['fecha'] ?? '');
        if (!self::fechaOk($fecha)) {
            return 'La fecha no es válida.';
        }
        $grados = array_map(static fn (array $g): int => (int) $g['id'], Academico::grados($pdo, $colegioId));
        $origen = (int) ($d['grado_origen'] ?? 0);
        $destino = (int) ($d['grado_destino'] ?? 0);
        if (($origen && !in_array($origen, $grados, true)) || ($destino && !in_array($destino, $grados, true))) {
            return 'El grado no es válido.';
        }
        $anio = Academico::anioActual($pdo, $colegioId);
        $pdo->prepare('INSERT INTO aprobaciones_anticipadas (colegio_id, estudiante_id, anio_lectivo_id, grado_origen_id, grado_destino_id, fecha, acta, motivo, registrado_por) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, $estudianteId, $anio ? (int) $anio['id'] : null, $origen ?: null, $destino ?: null, $fecha, self::txt($d['acta'] ?? '', 60), self::txt($d['motivo'] ?? '', 5000), $porId]);
        return '';
    }

    public static function eliminarAprobacion(PDO $pdo, int $id, int $estudianteId, int $colegioId): void
    {
        $pdo->prepare('DELETE FROM aprobaciones_anticipadas WHERE id = ? AND estudiante_id = ? AND colegio_id = ?')->execute([$id, $estudianteId, $colegioId]);
    }

    public static function observacionesGenerales(PDO $pdo, int $estudianteId): array
    {
        $v = self::valores($pdo, $estudianteId);
        return ['temporales' => $v['obs_temporales'] ?? '', 'certificados' => $v['obs_certificados'] ?? ''];
    }

    public static function guardarObservacionesGenerales(PDO $pdo, int $estudianteId, string $campo, string $texto, int $porId): string
    {
        $clave = ['temporales' => 'obs_temporales', 'certificados' => 'obs_certificados'][$campo] ?? null;
        if ($clave === null) {
            return 'Campo no válido.';
        }
        self::guardarDato($pdo, $estudianteId, $clave, self::txt($texto, 5000), $porId);
        return '';
    }

    // ------------------------------------------------------------------
    // Periodos y filtros (Año / Período)
    // ------------------------------------------------------------------

    /** [desde, hasta] del año o del período elegido. */
    public static function rango(array $anio, array $periodos, int $periodoId): array
    {
        foreach ($periodos as $p) {
            if ((int) $p['id'] === $periodoId) { return [(string) $p['fecha_inicio'], (string) $p['fecha_fin']]; }
        }
        $y = (int) $anio['anio'];
        return [(string) ($anio['fecha_inicio'] ?: $y . '-01-01'), (string) ($anio['fecha_fin'] ?: $y . '-12-31')];
    }

    public static function etiquetaPeriodo(array $periodos, string $fecha): string
    {
        foreach ($periodos as $p) {
            if ($fecha >= (string) $p['fecha_inicio'] && $fecha <= (string) $p['fecha_fin']) { return (string) $p['nombre']; }
        }
        return '';
    }

    public static function filtrarPorFecha(array $filas, array $rango, string $campo = 'fecha'): array
    {
        return array_values(array_filter($filas, static fn (array $f): bool => (string) $f[$campo] >= $rango[0] && (string) $f[$campo] <= $rango[1]));
    }

    // ------------------------------------------------------------------
    // Anecdotario y observador
    // ------------------------------------------------------------------

    /** $grupo: 'anecdotario' (tipo anécdota) u 'observador' (académica, convivencia, seguimiento). */
    public static function anotaciones(PDO $pdo, int $estudianteId, string $grupo, bool $verReservado, bool $soloFamilia): array
    {
        $sql = 'SELECT o.*, u.nombre AS autor FROM observador o LEFT JOIN usuarios u ON u.id = o.autor_id WHERE o.estudiante_id = ?';
        $sql .= $grupo === 'anecdotario' ? " AND o.tipo = 'anecdota'" : " AND o.tipo <> 'anecdota'";
        if (!$verReservado) { $sql .= " AND o.tipo <> 'seguimiento'"; }
        if ($soloFamilia) { $sql .= ' AND o.visible_familia = 1'; }
        $q = $pdo->prepare($sql . ' ORDER BY o.fecha DESC, o.id DESC LIMIT 300');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    private static function siguienteFolio(PDO $pdo, string $tabla, int $estudianteId): int
    {
        $sql = $tabla === 'observador'
            ? "SELECT COALESCE(MAX(folio), 0) + 1 FROM observador WHERE estudiante_id = ? AND tipo <> 'anecdota'"
            : 'SELECT COALESCE(MAX(folio), 0) + 1 FROM procesos_disciplinarios WHERE estudiante_id = ?';
        $q = $pdo->prepare($sql);
        $q->execute([$estudianteId]);
        return (int) $q->fetchColumn();
    }

    /** @return array{0:string,1:int} [error, id] */
    public static function agregarAnotacion(PDO $pdo, int $colegioId, int $estudianteId, int $autorId, string $grupo, array $d, bool $puedeReservado): array
    {
        $tipo = $grupo === 'anecdotario' ? 'anecdota' : (string) ($d['tipo'] ?? '');
        if ($grupo !== 'anecdotario' && !in_array($tipo, ['academica', 'convivencia', 'seguimiento'], true)) {
            return ['Tipo de anotación no válido.', 0];
        }
        if ($tipo === 'seguimiento' && !$puedeReservado) {
            return ['Las anotaciones de seguimiento son reservadas a coordinación y orientación.', 0];
        }
        $fecha = (string) ($d['fecha'] ?? '');
        if (!self::fechaOk($fecha) || $fecha > date('Y-m-d')) {
            return ['La fecha no es válida (no puede ser futura).', 0];
        }
        $situacion = (string) ($d['situacion'] ?? '');
        if ($situacion !== '' && !isset(Expediente::SITUACIONES[$situacion])) {
            return ['Elige un tipo de situación válido.', 0];
        }
        $desc = self::txt($d['descripcion'] ?? '', 5000);
        if ($desc === null) {
            return ['Describe lo ocurrido.', 0];
        }
        $visible = $tipo !== 'seguimiento' && isset($d['visible_familia']);
        $pdo->prepare('INSERT INTO observador (colegio_id, estudiante_id, autor_id, fecha, tipo, descripcion, compromiso, visible_familia, folio, situacion, tratamiento_inmediato) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, $estudianteId, $autorId, $fecha, $tipo, $desc, self::txt($d['compromiso'] ?? '', 500), $visible ? 1 : 0,
                $tipo === 'anecdota' ? null : self::siguienteFolio($pdo, 'observador', $estudianteId), $situacion !== '' ? $situacion : null, self::txt($d['tratamiento'] ?? '', 500)]);
        return ['', (int) $pdo->lastInsertId()];
    }

    public static function anotacion(PDO $pdo, int $id, int $estudianteId, int $colegioId): ?array
    {
        $q = $pdo->prepare('SELECT * FROM observador WHERE id = ? AND estudiante_id = ? AND colegio_id = ?');
        $q->execute([$id, $estudianteId, $colegioId]);
        return $q->fetch() ?: null;
    }

    public static function eliminarAnotacion(PDO $pdo, int $id, int $estudianteId, int $colegioId): void
    {
        foreach (self::evidenciasDe($pdo, 'observador', $id) as $ev) {
            self::eliminarEvidencia($pdo, (int) $ev['id'], $estudianteId, $colegioId);
        }
        Expediente::eliminarObservacion($pdo, $id, $estudianteId, $colegioId);
    }

    /** «Pasar a»: de Anecdotario al Observador (académica o convivencia). */
    public static function pasarAObservador(PDO $pdo, int $colegioId, int $estudianteId, int $id, string $tipoDestino): string
    {
        if (!in_array($tipoDestino, ['academica', 'convivencia'], true)) {
            return 'Destino no válido.';
        }
        $a = self::anotacion($pdo, $id, $estudianteId, $colegioId);
        if (!$a || $a['tipo'] !== 'anecdota') {
            return 'Esa anotación ya no está en el anecdotario.';
        }
        $pdo->prepare("UPDATE observador SET tipo = ?, folio = ? WHERE id = ? AND estudiante_id = ? AND colegio_id = ? AND tipo = 'anecdota'")
            ->execute([$tipoDestino, self::siguienteFolio($pdo, 'observador', $estudianteId), $id, $estudianteId, $colegioId]);
        return '';
    }

    /** «Pasar a»: de una anotación a un proceso disciplinario (un docente lo envía como reporte a coordinación). */
    public static function pasarAProceso(PDO $pdo, int $colegioId, int $estudianteId, int $id, int $porId, bool $comoReporte): string
    {
        $a = self::anotacion($pdo, $id, $estudianteId, $colegioId);
        if (!$a) {
            return 'La anotación no existe.';
        }
        if (!empty($a['pasado_proceso_id'])) {
            return 'Esa anotación ya se pasó a un proceso disciplinario.';
        }
        [$err, $procesoId] = self::abrirProceso($pdo, $colegioId, $estudianteId, $porId, (string) $a['fecha'], (string) ($a['situacion'] ?: 'I'),
            (string) $a['descripcion'] . "\n(Origen: anotación del observador del " . $a['fecha'] . '.)', (string) ($a['tratamiento_inmediato'] ?? ''), $comoReporte);
        if ($err !== '') {
            return $err;
        }
        $pdo->prepare('UPDATE observador SET pasado_proceso_id = ? WHERE id = ? AND estudiante_id = ?')->execute([$procesoId, $id, $estudianteId]);
        return '';
    }

    // ------------------------------------------------------------------
    // Revisiones del observador
    // ------------------------------------------------------------------

    public static function revisiones(PDO $pdo, int $estudianteId): array
    {
        $q = $pdo->prepare('SELECT r.*, u.nombre AS coordinador FROM observador_revisiones r LEFT JOIN usuarios u ON u.id = r.coordinador_id WHERE r.estudiante_id = ? ORDER BY r.fecha DESC, r.id DESC');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }

    public static function agregarRevision(PDO $pdo, int $colegioId, int $estudianteId, int $porId, string $fecha, string $obs): string
    {
        if (!self::fechaOk($fecha) || $fecha > date('Y-m-d')) {
            return 'La fecha no es válida (no puede ser futura).';
        }
        $o = self::txt($obs, 2000);
        if ($o === null) {
            return 'Escribe las observaciones de la revisión.';
        }
        $pdo->prepare('INSERT INTO observador_revisiones (colegio_id, estudiante_id, fecha, observaciones, coordinador_id) VALUES (?,?,?,?,?)')->execute([$colegioId, $estudianteId, $fecha, $o, $porId]);
        return '';
    }

    public static function eliminarRevision(PDO $pdo, int $id, int $estudianteId, int $colegioId): void
    {
        $pdo->prepare('DELETE FROM observador_revisiones WHERE id = ? AND estudiante_id = ? AND colegio_id = ?')->execute([$id, $estudianteId, $colegioId]);
    }

    // ------------------------------------------------------------------
    // Procesos disciplinarios
    // ------------------------------------------------------------------

    /** @return array{0:string,1:int} [error, id] */
    public static function abrirProceso(PDO $pdo, int $colegioId, int $estudianteId, int $porId, string $fecha, string $situacion, string $descripcion, string $tratamiento, bool $comoReporte): array
    {
        if (!isset(Expediente::SITUACIONES[$situacion])) {
            return ['Elige el tipo de situación.', 0];
        }
        if (!self::fechaOk($fecha) || $fecha > date('Y-m-d')) {
            return ['La fecha no es válida (no puede ser futura).', 0];
        }
        $desc = self::txt($descripcion, 5000);
        if ($desc === null) {
            return ['Describe la situación.', 0];
        }
        $pdo->prepare('INSERT INTO procesos_disciplinarios (colegio_id, estudiante_id, abierto_por, fecha, situacion_tipo, descripcion, estado, folio, tratamiento_inmediato) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$colegioId, $estudianteId, $porId, $fecha, $situacion, $desc, $comoReporte ? 'reportado' : 'abierto',
                self::siguienteFolio($pdo, 'procesos_disciplinarios', $estudianteId), self::txt($tratamiento, 500)]);
        return ['', (int) $pdo->lastInsertId()];
    }

    /** Actuaciones de varios procesos a la vez: proceso_id => [filas]. */
    public static function actuacionesDe(PDO $pdo, array $procesoIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $procesoIds)));
        if (!$ids) {
            return [];
        }
        $q = $pdo->prepare('SELECT a.*, u.nombre AS autor FROM actuaciones_disciplinarias a LEFT JOIN usuarios u ON u.id = a.autor_id
                             WHERE a.proceso_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY a.fecha, a.id');
        $q->execute($ids);
        $out = [];
        foreach ($q->fetchAll() as $f) { $out[(int) $f['proceso_id']][] = $f; }
        return $out;
    }

    /** «Pasar a» de un proceso: remisión a orientación o al comité de convivencia (queda como actuación). */
    public static function pasarProceso(PDO $pdo, int $colegioId, int $procesoId, string $destino, int $porId): string
    {
        $mapa = ['orientacion' => ['remision_orientacion', 'Remitido a orientación escolar.'], 'comite' => ['comite_convivencia', 'Remitido al comité escolar de convivencia.']];
        if (!isset($mapa[$destino])) {
            return 'Destino no válido.';
        }
        $err = Expediente::agregarActuacion($pdo, $procesoId, $porId, date('Y-m-d'), $mapa[$destino][0], $mapa[$destino][1]);
        if ($err !== '') {
            return $err;
        }
        $pdo->prepare("UPDATE procesos_disciplinarios SET estado = 'seguimiento' WHERE id = ? AND colegio_id = ? AND estado IN ('reportado','abierto')")->execute([$procesoId, $colegioId]);
        return '';
    }

    // ------------------------------------------------------------------
    // Aula de apoyo
    // ------------------------------------------------------------------

    public static function agregarSeguimiento(PDO $pdo, int $apoyoId, int $autorId, string $fecha, string $nota, string $observacion): string
    {
        if (!self::fechaOk($fecha)) {
            return 'La fecha no es válida.';
        }
        $n = self::txt($nota, 5000);
        if ($n === null) {
            return 'Escribe la descripción del seguimiento.';
        }
        $pdo->prepare('INSERT INTO apoyo_seguimientos (apoyo_id, autor_id, fecha, nota, observacion) VALUES (?,?,?,?,?)')->execute([$apoyoId, $autorId, $fecha, $n, self::txt($observacion, 500)]);
        return '';
    }

    // ------------------------------------------------------------------
    // Evidencias (imágenes) — guardadas fuera de public/ y servidas por evidencia_ver.php
    // ------------------------------------------------------------------

    public static function carpetaEvidencias(): string
    {
        return __DIR__ . '/../uploads/evidencias';
    }

    public static function rutaEvidencia(string $archivo): string
    {
        return self::carpetaEvidencias() . '/' . basename($archivo);
    }

    /** origen_id => [evidencias] para todo el estudiante. */
    public static function evidencias(PDO $pdo, int $estudianteId, string $origen): array
    {
        $q = $pdo->prepare('SELECT * FROM hoja_vida_evidencias WHERE estudiante_id = ? AND origen = ? ORDER BY id');
        $q->execute([$estudianteId, $origen]);
        $out = [];
        foreach ($q->fetchAll() as $f) { $out[(int) $f['origen_id']][] = $f; }
        return $out;
    }

    public static function evidenciasDe(PDO $pdo, string $origen, int $origenId): array
    {
        $q = $pdo->prepare('SELECT * FROM hoja_vida_evidencias WHERE origen = ? AND origen_id = ?');
        $q->execute([$origen, $origenId]);
        return $q->fetchAll();
    }

    public static function evidencia(PDO $pdo, int $id): ?array
    {
        $q = $pdo->prepare('SELECT * FROM hoja_vida_evidencias WHERE id = ?');
        $q->execute([$id]);
        return $q->fetch() ?: null;
    }

    /** Devuelve un error o ''. Si no se envió archivo, no hace nada (no es un error). */
    public static function subirEvidencia(PDO $pdo, int $colegioId, int $estudianteId, string $origen, int $origenId, array $archivo, int $porId): string
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if (!in_array($origen, ['observador', 'proceso'], true)) {
            return 'Origen no válido.';
        }
        $existe = $origen === 'observador'
            ? self::anotacion($pdo, $origenId, $estudianteId, $colegioId)
            : Expediente::proceso($pdo, $origenId, $colegioId);
        if (!$existe || (int) $existe['estudiante_id'] !== $estudianteId) {
            return 'El registro al que adjuntas la evidencia no existe.';
        }
        if (count(self::evidenciasDe($pdo, $origen, $origenId)) >= self::EVIDENCIAS_MAX) {
            return 'Se alcanzó el máximo de ' . self::EVIDENCIAS_MAX . ' evidencias en este registro.';
        }
        try {
            $nombre = guardarImagenSubida($archivo, self::carpetaEvidencias());
        } catch (RuntimeException $e) {
            return $e->getMessage() === '__vacio__' ? '' : $e->getMessage();
        }
        $pdo->prepare('INSERT INTO hoja_vida_evidencias (colegio_id, estudiante_id, origen, origen_id, archivo, subido_por) VALUES (?,?,?,?,?,?)')
            ->execute([$colegioId, $estudianteId, $origen, $origenId, $nombre, $porId]);
        return '';
    }

    public static function eliminarEvidencia(PDO $pdo, int $id, int $estudianteId, int $colegioId): void
    {
        $q = $pdo->prepare('SELECT archivo FROM hoja_vida_evidencias WHERE id = ? AND estudiante_id = ? AND colegio_id = ?');
        $q->execute([$id, $estudianteId, $colegioId]);
        $archivo = $q->fetchColumn();
        if ($archivo === false) {
            return;
        }
        $pdo->prepare('DELETE FROM hoja_vida_evidencias WHERE id = ?')->execute([$id]);
        $ruta = self::rutaEvidencia((string) $archivo);
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}
