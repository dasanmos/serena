<?php
declare(strict_types=1);

/**
 * Catálogo de informes (69 informes en 15 categorías).
 * Cada entrada: cat, nombre, desc, roles, filtros (los que muestra el formulario), req (obligatorios), gen [Clase, método],
 * orient (v|h), tablas (que deben existir), estados (opciones del filtro «estado»), sensible (queda en la auditoría),
 * cert (si en vez de un informe EMITE un certificado verificable de ese tipo).
 * Para agregar un informe: una entrada aquí + un método estático en src/informes/Gen*.php.
 */

$TODOS = Informes::ROL_TODOS;
$DIR = Informes::ROL_DIRECTIVOS;
$SEC = Informes::ROL_SECRETARIA;
$ADM = Informes::ROL_ADMIN;
$SENS = Informes::ROL_SENSIBLE;
$SEG = Informes::ROL_SEGUIMIENTO;

$estadosInsc = ['pendiente' => 'Pendiente', 'en_revision' => 'En revisión', 'admitida' => 'Admitida', 'rechazada' => 'Rechazada', 'matriculada' => 'Matriculada'];
$estadosMat = ['activa' => 'Activa', 'pendiente' => 'Pendiente', 'rechazada' => 'Rechazada'];
$estadosPlan = ['activo' => 'Activo', 'cumplido' => 'Cumplido', 'incumplido' => 'Incumplido'];
$estadosPlaneacion = ['borrador' => 'Borrador', 'listo' => 'Listo', 'ejecutado' => 'Ejecutado'];

return [
    'categorias' => [
        1 => ['nombre' => 'Calificaciones, evaluación y resultados', 'desc' => 'Planillas, libro de calificaciones y consolidados.'],
        2 => ['nombre' => 'Rendimiento académico', 'desc' => 'Alto y bajo rendimiento, logros perdidos y comparativos.'],
        3 => ['nombre' => 'Boletines, certificados y constancias', 'desc' => 'Boletines en tres formatos y certificados con código QR.'],
        4 => ['nombre' => 'Matrícula, inscripción y admisiones', 'desc' => 'Hoja de matrícula, renovaciones, cupos e inscripciones.'],
        5 => ['nombre' => 'Asistencia e inasistencia', 'desc' => 'Control diario, escalas y porcentajes de inasistencia.'],
        6 => ['nombre' => 'Refuerzos y planes de mejoramiento', 'desc' => 'Estudiantes con refuerzos pendientes y eficiencia de los planes.'],
        7 => ['nombre' => 'Promoción e historial académico', 'desc' => 'Cursó y aprobó, promoción y record académico.'],
        8 => ['nombre' => 'Seguimiento y expediente estudiantil', 'desc' => 'Cuaderno y ficha de seguimiento, hoja de vida.'],
        9 => ['nombre' => 'Estudiantes y caracterización poblacional', 'desc' => 'Listados, directorio, discapacidad, víctimas y familias.'],
        10 => ['nombre' => 'Docentes y asignación académica', 'desc' => 'Carga académica y listado de docentes.'],
        11 => ['nombre' => 'Restaurante escolar', 'desc' => 'Beneficiarios, asistencia, pagos y distribución.'],
        12 => ['nombre' => 'Cartera y estados financieros', 'desc' => 'Estados de cuenta, saldos y pensión mensual.'],
        13 => ['nombre' => 'Deserción y movimientos estudiantiles', 'desc' => 'Deserción escolar y salidas.'],
        14 => ['nombre' => 'Planeación académica', 'desc' => 'Planes de período de los docentes.'],
        15 => ['nombre' => 'Administración, control e información institucional', 'desc' => 'Resumen DANE y registro de auditoría.'],
    ],
    'informes' => [
        // ---------------------------------------------------------- 1
        'planilla_auxiliar' => ['cat' => 1, 'nombre' => 'Planilla Auxiliar', 'desc' => 'Planilla en blanco con la lista del grupo para anotar notas a mano.', 'roles' => $TODOS, 'filtros' => ['anio', 'grupo', 'asignacion'], 'gen' => ['GenNotas', 'planillaAuxiliar'], 'orient' => 'h'],
        'planilla_notas' => ['cat' => 1, 'nombre' => 'Planilla de Notas', 'desc' => 'Notas por actividad y nota del período de una asignatura.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'asignacion'], 'req' => ['periodo', 'asignacion'], 'gen' => ['GenNotas', 'planillaNotas'], 'orient' => 'h'],
        'planilla_verificacion' => ['cat' => 1, 'nombre' => 'Planilla de Verificación', 'desc' => 'Revisa qué estudiantes tienen nota del período y qué falta por calificar.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'asignacion'], 'req' => ['periodo'], 'gen' => ['GenNotas', 'planillaVerificacion']],
        'progreso_evaluacion' => ['cat' => 1, 'nombre' => 'Progreso de Evaluación', 'desc' => 'Avance de la calificación del período por asignatura y docente.', 'roles' => $DIR, 'filtros' => ['anio', 'periodo', 'grupo'], 'req' => ['periodo'], 'gen' => ['GenNotas', 'progresoEvaluacion']],
        'evaluacion_semestral' => ['cat' => 1, 'nombre' => 'Evaluación Semestral', 'desc' => 'Promedio por semestre y anual de cada estudiante.', 'roles' => $TODOS, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenNotas', 'evaluacionSemestral']],
        'estudiantes_escala' => ['cat' => 1, 'nombre' => 'Estudiantes por Escala de Valoración', 'desc' => 'Cuántos estudiantes hay en cada nivel de desempeño.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo'], 'gen' => ['GenNotas', 'estudiantesEscala']],
        'resultados_academicos' => ['cat' => 1, 'nombre' => 'Resultados Académicos', 'desc' => 'Aprobados, con áreas perdidas y promedio por grupo.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo'], 'gen' => ['GenNotas', 'resultadosAcademicos']],
        'libro_calificaciones' => ['cat' => 1, 'nombre' => 'Libro de Calificaciones', 'desc' => 'Registro oficial por estudiante con todos los períodos y la definitiva.', 'roles' => $DIR, 'filtros' => ['anio', 'grupo', 'estudiante'], 'gen' => ['GenNotas', 'libroCalificaciones']],
        'indice_libro_calificaciones' => ['cat' => 1, 'nombre' => 'Índice del libro de calificaciones', 'desc' => 'Folio de cada estudiante en el libro de calificaciones.', 'roles' => $DIR, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenNotas', 'indiceLibro']],
        'consolidado_general' => ['cat' => 1, 'nombre' => 'Consolidado General', 'desc' => 'Matriz de estudiantes por asignaturas, con promedio y puesto.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grupo'], 'req' => ['grupo'], 'gen' => ['GenNotas', 'consolidado'], 'orient' => 'h'],

        // ---------------------------------------------------------- 2
        'alto_rendimiento' => ['cat' => 2, 'nombre' => 'Estudiantes con Alto Rendimiento', 'desc' => 'Estudiantes con promedio igual o superior al umbral (por defecto, el nivel más alto).', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo', 'umbral'], 'gen' => ['GenRendimiento', 'alto']],
        'bajo_rendimiento' => ['cat' => 2, 'nombre' => 'Estudiantes con Bajo Rendimiento', 'desc' => 'Estudiantes con promedio por debajo del umbral (por defecto, el mínimo aprobatorio).', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo', 'umbral'], 'gen' => ['GenRendimiento', 'bajo']],
        'logros_perdidos' => ['cat' => 2, 'nombre' => 'Estudiantes con Logros Perdidos', 'desc' => 'Asignaturas perdidas en el período con los logros que se deben recuperar.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo'], 'req' => ['periodo'], 'gen' => ['GenRendimiento', 'logrosPerdidos']],
        'estudiantes_perdiendo' => ['cat' => 2, 'nombre' => 'Listado de Estudiantes Perdiendo', 'desc' => 'Estudiantes con una o más asignaturas perdidas.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo'], 'gen' => ['GenRendimiento', 'perdiendo']],
        'rendimiento_area' => ['cat' => 2, 'nombre' => 'Rendimiento por Área', 'desc' => 'Promedio y aprobación por área del conocimiento.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo'], 'gen' => ['GenRendimiento', 'porArea']],
        'rendimiento_asignatura' => ['cat' => 2, 'nombre' => 'Rendimiento por Asignatura', 'desc' => 'Promedio, aprobados y perdidos por asignatura.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo'], 'gen' => ['GenRendimiento', 'porAsignatura']],
        'rendimiento_est_asignaturas' => ['cat' => 2, 'nombre' => 'Rendimiento por Estudiante (Asignaturas)', 'desc' => 'Nota de cada asignatura frente al promedio del grupo.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grupo', 'estudiante'], 'gen' => ['GenRendimiento', 'estudianteAsignaturas']],
        'rendimiento_est_promedios' => ['cat' => 2, 'nombre' => 'Rendimiento por Estudiante (Promedios)', 'desc' => 'Promedio de cada estudiante por período, promedio final y puesto.', 'roles' => $TODOS, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenRendimiento', 'estudiantePromedios'], 'orient' => 'h'],
        'rendimiento_grupo' => ['cat' => 2, 'nombre' => 'Rendimiento por Grupo', 'desc' => 'Comparativo de promedio y aprobación entre grupos.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado'], 'gen' => ['GenRendimiento', 'porGrupo']],

        // ---------------------------------------------------------- 3
        'boletin' => ['cat' => 3, 'nombre' => 'Boletín Informativo', 'desc' => 'Boletín por estudiante: asignaturas, períodos, definitiva y desempeño.', 'roles' => $DIR, 'filtros' => ['anio', 'grupo', 'estudiante', 'solo_pub'], 'gen' => ['GenBoletines', 'boletin']],
        'boletin_resumen' => ['cat' => 3, 'nombre' => 'Boletín Informativo (Con Resumen)', 'desc' => 'Boletín con resumen: promedio, puesto, áreas perdidas e inasistencias.', 'roles' => $DIR, 'filtros' => ['anio', 'grupo', 'estudiante', 'solo_pub'], 'gen' => ['GenBoletines', 'boletinResumen']],
        'boletin_areas' => ['cat' => 3, 'nombre' => 'Boletín Informativo (Por Áreas)', 'desc' => 'Boletín agrupado por áreas, con el promedio de cada área.', 'roles' => $DIR, 'filtros' => ['anio', 'grupo', 'estudiante', 'solo_pub'], 'gen' => ['GenBoletines', 'boletinAreas']],
        'cert_estudios' => ['cat' => 3, 'nombre' => 'Certificado de Estudios', 'desc' => 'Emite el certificado de estudio verificable con código QR.', 'roles' => $SEC, 'filtros' => ['anio', 'grupo', 'estudiante'], 'cert' => 'estudio'],
        'cert_notas_parciales' => ['cat' => 3, 'nombre' => 'Certificado de Notas Parciales', 'desc' => 'Emite el certificado de calificaciones con las notas registradas hasta hoy.', 'roles' => $SEC, 'filtros' => ['anio', 'grupo', 'estudiante'], 'cert' => 'notas'],
        'cert_traslado' => ['cat' => 3, 'nombre' => 'Certificado de Traslado', 'desc' => 'Emite el certificado para traslado a otra institución, con código QR.', 'roles' => $SEC, 'filtros' => ['anio', 'grupo', 'estudiante'], 'cert' => 'traslado'],
        'constancia_estudio' => ['cat' => 3, 'nombre' => 'Constancia de Estudio', 'desc' => 'Emite la constancia de matrícula/estudio verificable con código QR.', 'roles' => $SEC, 'filtros' => ['anio', 'grupo', 'estudiante'], 'cert' => 'matricula'],
        'estudiantes_sin_certificado' => ['cat' => 3, 'nombre' => 'Estudiantes Sin Certificado', 'desc' => 'Estudiantes del año que aún no tienen un certificado vigente del tipo elegido.', 'roles' => $SEC, 'filtros' => ['anio', 'grado', 'grupo', 'tipo_cert'], 'gen' => ['GenBoletines', 'sinCertificado'], 'tablas' => ['certificados']],

        // ---------------------------------------------------------- 4
        'hoja_matricula' => ['cat' => 4, 'nombre' => 'Hoja de Matrícula', 'desc' => 'Ficha de matrícula de cada estudiante con datos, acudientes y firmas.', 'roles' => $SEC, 'filtros' => ['anio', 'grupo', 'estudiante'], 'gen' => ['GenMatricula', 'hojaMatricula'], 'sensible' => true],
        'renovacion_matricula' => ['cat' => 4, 'nombre' => 'Renovación de Matrícula', 'desc' => 'Renovaciones del año y estudiantes del año anterior que faltan por renovar.', 'roles' => $SEC, 'filtros' => ['anio', 'grado', 'estado'], 'estados' => $estadosMat, 'gen' => ['GenMatricula', 'renovacion']],
        'reserva_cupo' => ['cat' => 4, 'nombre' => 'Reserva de Cupo', 'desc' => 'Cupos reservados: admitidos sin matricular y matrículas pendientes.', 'roles' => $SEC, 'filtros' => ['anio', 'grado'], 'gen' => ['GenMatricula', 'reservaCupo']],
        'preinscripcion' => ['cat' => 4, 'nombre' => 'Pre-Inscripción', 'desc' => 'Solicitudes de inscripción recibidas, con acudiente y estado.', 'roles' => $SEC, 'filtros' => ['anio', 'grado', 'estado', 'desde', 'hasta'], 'estados' => $estadosInsc, 'gen' => ['GenMatricula', 'preinscripcion'], 'orient' => 'h'],
        'inscripcion_en_proceso' => ['cat' => 4, 'nombre' => 'Estudiantes en proceso de inscripción', 'desc' => 'Inscripciones pendientes, en revisión o admitidas sin matricular.', 'roles' => $SEC, 'filtros' => ['anio', 'grado'], 'gen' => ['GenMatricula', 'enProceso']],
        'estudiantes_nuevos' => ['cat' => 4, 'nombre' => 'Listado de Estudiantes Nuevos', 'desc' => 'Estudiantes que se matricularon por primera vez en el año.', 'roles' => $SEC, 'filtros' => ['anio', 'grado'], 'gen' => ['GenMatricula', 'nuevos']],
        'estudiantes_cancelados' => ['cat' => 4, 'nombre' => 'Listado de Estudiantes Cancelados', 'desc' => 'Matrículas retiradas o canceladas en el año.', 'roles' => $SEC, 'filtros' => ['anio', 'grado', 'desde', 'hasta'], 'gen' => ['GenMatricula', 'cancelados']],
        'indice_libro_matriculas' => ['cat' => 4, 'nombre' => 'Índice del libro de matrículas', 'desc' => 'Folio, estudiante, grado y fecha de cada matrícula activa.', 'roles' => $SEC, 'filtros' => ['anio', 'grado'], 'gen' => ['GenMatricula', 'indiceLibro']],

        // ---------------------------------------------------------- 5
        'control_asistencia' => ['cat' => 5, 'nombre' => 'Control de Asistencia', 'desc' => 'Matriz estudiante por fecha de una asignatura (P, A, T, E) con totales.', 'roles' => $TODOS, 'filtros' => ['anio', 'asignacion', 'desde', 'hasta'], 'req' => ['asignacion'], 'gen' => ['GenAsistencia', 'control'], 'orient' => 'h'],
        'escala_inasistencia' => ['cat' => 5, 'nombre' => 'Escala de Inasistencia', 'desc' => 'Estudiantes agrupados por rangos de inasistencias.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo'], 'gen' => ['GenAsistencia', 'escala']],
        'inasistencia_tipo' => ['cat' => 5, 'nombre' => 'Inasistencia por Tipo', 'desc' => 'Ausencias, retardos y excusas por grupo.', 'roles' => $TODOS, 'filtros' => ['anio', 'grado', 'grupo', 'desde', 'hasta'], 'gen' => ['GenAsistencia', 'porTipo']],
        'inasistencia_porcentaje' => ['cat' => 5, 'nombre' => 'Porcentaje promedio de Inasistencia', 'desc' => 'Porcentaje de inasistencia por grupo y estudiantes sobre el umbral (por defecto 10 %).', 'roles' => $TODOS, 'filtros' => ['anio', 'grado', 'grupo', 'desde', 'hasta', 'umbral'], 'gen' => ['GenAsistencia', 'porcentaje']],

        // ---------------------------------------------------------- 6
        'refuerzos_pendientes' => ['cat' => 6, 'nombre' => 'Estudiantes con Refuerzos Pendientes', 'desc' => 'Refuerzos por presentar, con asignatura y fecha límite.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo'], 'gen' => ['GenRefuerzos', 'pendientes'], 'tablas' => ['refuerzos']],
        'planilla_refuerzos' => ['cat' => 6, 'nombre' => 'Planilla de Refuerzos', 'desc' => 'Planilla para registrar la nota del refuerzo de una asignatura.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'asignacion'], 'req' => ['periodo', 'asignacion'], 'gen' => ['GenRefuerzos', 'planilla'], 'tablas' => ['refuerzos']],
        'plan_mejoramiento' => ['cat' => 6, 'nombre' => 'Estudiantes en Plan de Mejoramiento', 'desc' => 'Planes de mejoramiento con compromisos y estado.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grado', 'grupo', 'estado'], 'estados' => $estadosPlan, 'gen' => ['GenRefuerzos', 'planes'], 'tablas' => ['planes_mejoramiento']],
        'eficiencia_plan' => ['cat' => 6, 'nombre' => 'Eficiencia del Plan de Mejoramiento', 'desc' => 'Cuántos planes y refuerzos lograron superar la asignatura.', 'roles' => $DIR, 'filtros' => ['anio', 'periodo', 'grado', 'grupo'], 'gen' => ['GenRefuerzos', 'eficiencia'], 'tablas' => ['planes_mejoramiento']],

        // ---------------------------------------------------------- 7
        'curso_aprobo' => ['cat' => 7, 'nombre' => 'Cursó y Aprobó', 'desc' => 'Estudiantes que cursaron el año y lo aprobaron, y los que no.', 'roles' => $DIR, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenHistorial', 'cursoAprobo']],
        'evaluacion_promocion' => ['cat' => 7, 'nombre' => 'Evaluación y Promoción de Estudiantes', 'desc' => 'Decisión sugerida de promoción según áreas perdidas (insumo de la comisión de evaluación).', 'roles' => $DIR, 'filtros' => ['anio', 'grado', 'grupo', 'umbral'], 'gen' => ['GenHistorial', 'promocion']],
        'estado_grados_anteriores' => ['cat' => 7, 'nombre' => 'Estado Académico de Grados Anteriores', 'desc' => 'Resumen año por año de un estudiante: grado, promedio y resultado.', 'roles' => $DIR, 'filtros' => ['estudiante'], 'req' => ['estudiante'], 'gen' => ['GenHistorial', 'gradosAnteriores']],
        'record_academico' => ['cat' => 7, 'nombre' => 'Record Académico', 'desc' => 'Historial completo de calificaciones de un estudiante, año por año.', 'roles' => $DIR, 'filtros' => ['estudiante'], 'req' => ['estudiante'], 'gen' => ['GenHistorial', 'record']],

        // ---------------------------------------------------------- 8
        'cuaderno_seguimiento' => ['cat' => 8, 'nombre' => 'Cuaderno de Seguimiento', 'desc' => 'Anotaciones del observador por fecha, estudiante y tipo.', 'roles' => $SEG, 'filtros' => ['anio', 'grupo', 'estudiante', 'desde', 'hasta', 'estado'], 'estados' => Expediente::TIPOS_OBSERVADOR, 'gen' => ['GenExpedientes', 'cuaderno'], 'sensible' => true, 'orient' => 'h'],
        'ficha_seguimiento' => ['cat' => 8, 'nombre' => 'Ficha de Seguimiento', 'desc' => 'Ficha integral: datos, observador, convivencia, horas y asistencia.', 'roles' => $SEG, 'filtros' => ['anio', 'grupo', 'estudiante'], 'gen' => ['GenExpedientes', 'ficha'], 'sensible' => true],
        'hoja_vida' => ['cat' => 8, 'nombre' => 'Hoja de Vida', 'desc' => 'Hoja de vida del estudiante: datos personales, familia, contactos y salud.', 'roles' => $SEG, 'filtros' => ['anio', 'grupo', 'estudiante'], 'gen' => ['GenExpedientes', 'hojaVida'], 'sensible' => true],

        // ---------------------------------------------------------- 9
        'listado_estudiantes' => ['cat' => 9, 'nombre' => 'Listado de Estudiantes', 'desc' => 'Lista oficial por grado y grupo con documento y edad.', 'roles' => $TODOS, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenEstudiantes', 'listado']],
        'directorio_estudiantes' => ['cat' => 9, 'nombre' => 'Directorio de Estudiantes', 'desc' => 'Contacto de estudiantes y acudientes (teléfono, correo, dirección).', 'roles' => $DIR, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenEstudiantes', 'directorio'], 'orient' => 'h'],
        'estudiantes_discapacidad' => ['cat' => 9, 'nombre' => 'Listado de Estudiantes con Discapacidad', 'desc' => 'Estudiantes con apoyo pedagógico activo (PIAR), según el acceso de tu rol.', 'roles' => $SENS, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenEstudiantes', 'discapacidad'], 'sensible' => true],
        'victimas_conflicto' => ['cat' => 9, 'nombre' => 'Estudiantes Víctimas del Conflicto', 'desc' => 'Estudiantes caracterizados como víctimas del conflicto armado.', 'roles' => $SENS, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenEstudiantes', 'victimas'], 'tablas' => ['caracterizacion_estudiante'], 'sensible' => true],
        'listado_familias' => ['cat' => 9, 'nombre' => 'Listado de Familias', 'desc' => 'Acudientes con sus hijos en la institución.', 'roles' => $DIR, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenEstudiantes', 'familias']],

        // ---------------------------------------------------------- 10
        'asignacion_academica' => ['cat' => 10, 'nombre' => 'Asignación Académica', 'desc' => 'Asignaturas, grupos e intensidad horaria de cada docente.', 'roles' => $TODOS, 'filtros' => ['anio', 'docente', 'grupo'], 'gen' => ['GenDocentes', 'asignacion']],
        'listado_docentes' => ['cat' => 10, 'nombre' => 'Listado de Docentes', 'desc' => 'Docentes activos con contacto, carga y dirección de grupo.', 'roles' => $DIR, 'filtros' => ['anio'], 'gen' => ['GenDocentes', 'listado']],

        // ---------------------------------------------------------- 11
        'rest_asistencia' => ['cat' => 11, 'nombre' => 'Asistencia al Restaurante', 'desc' => 'Días de asistencia al restaurante por estudiante en el rango.', 'roles' => $DIR, 'filtros' => ['anio', 'grado', 'grupo', 'desde', 'hasta'], 'gen' => ['GenRestaurante', 'asistencia'], 'tablas' => ['restaurante_beneficiarios', 'restaurante_asistencia']],
        'rest_pagos' => ['cat' => 11, 'nombre' => 'Control de Pagos Restaurante', 'desc' => 'Pagos del restaurante por estudiante y mes.', 'roles' => $DIR, 'filtros' => ['anio', 'grado', 'grupo', 'mes'], 'gen' => ['GenRestaurante', 'pagos'], 'tablas' => ['restaurante_beneficiarios', 'restaurante_pagos'], 'orient' => 'h'],
        'rest_distribucion' => ['cat' => 11, 'nombre' => 'Distribución Restaurante Escolar', 'desc' => 'Raciones diarias por grado, grupo y tipo de complemento.', 'roles' => $DIR, 'filtros' => ['anio'], 'gen' => ['GenRestaurante', 'distribucion'], 'tablas' => ['restaurante_beneficiarios']],
        'rest_listado' => ['cat' => 11, 'nombre' => 'Listado de Estudiantes Restaurante', 'desc' => 'Beneficiarios del restaurante con jornada y tipo.', 'roles' => $DIR, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenRestaurante', 'listado'], 'tablas' => ['restaurante_beneficiarios']],

        // ---------------------------------------------------------- 12
        'estados_cuenta' => ['cat' => 12, 'nombre' => 'Estados de Cuenta', 'desc' => 'Cargos, pagos y saldo de cada estudiante.', 'roles' => $SEC, 'filtros' => ['anio', 'grupo', 'estudiante'], 'gen' => ['GenCartera', 'estadosCuenta'], 'tablas' => ['cartera_cargos', 'cartera_pagos'], 'sensible' => true],
        'saldos_pendientes' => ['cat' => 12, 'nombre' => 'Estudiantes con Saldos pendientes', 'desc' => 'Estudiantes con saldo por pagar a la fecha o hasta el mes de corte.', 'roles' => $SEC, 'filtros' => ['anio', 'grado', 'grupo', 'mes'], 'gen' => ['GenCartera', 'saldos'], 'tablas' => ['cartera_cargos', 'cartera_pagos'], 'sensible' => true],
        'pension_mensual' => ['cat' => 12, 'nombre' => 'Pensión Mensual', 'desc' => 'Matriz estudiante por mes con el estado de la pensión.', 'roles' => $SEC, 'filtros' => ['anio', 'grado', 'grupo'], 'gen' => ['GenCartera', 'pension'], 'tablas' => ['cartera_cargos', 'cartera_pagos'], 'orient' => 'h', 'sensible' => true],

        // ---------------------------------------------------------- 13
        'desercion' => ['cat' => 13, 'nombre' => 'Deserción Escolar', 'desc' => 'Retiros del año, estudiantes que no volvieron y tasa de deserción.', 'roles' => $DIR, 'filtros' => ['anio', 'grado'], 'gen' => ['GenMovimientos', 'desercion']],
        'salidas' => ['cat' => 13, 'nombre' => 'Salidas', 'desc' => 'Estudiantes que salen de la institución (retiros y traslados) con fecha y motivo.', 'roles' => $DIR, 'filtros' => ['anio', 'grado', 'desde', 'hasta'], 'gen' => ['GenMovimientos', 'salidas']],

        // ---------------------------------------------------------- 14
        'plan_periodo' => ['cat' => 14, 'nombre' => 'Plan de período', 'desc' => 'Planeaciones de los docentes: tema, estándares, objetivos y evaluación.', 'roles' => $TODOS, 'filtros' => ['anio', 'periodo', 'grupo', 'docente', 'estado'], 'estados' => $estadosPlaneacion, 'gen' => ['GenPlaneacion', 'planPeriodo'], 'tablas' => ['planeaciones'], 'orient' => 'h'],

        // ---------------------------------------------------------- 15
        'dane' => ['cat' => 15, 'nombre' => 'Dane', 'desc' => 'Identificación institucional y matrícula por grado y sexo (resumen de apoyo para el reporte DANE).', 'roles' => $DIR, 'filtros' => ['anio'], 'gen' => ['GenInstitucional', 'dane']],
        'auditoria' => ['cat' => 15, 'nombre' => 'Registro de Auditoria', 'desc' => 'Acciones registradas en el sistema por las personas de tu colegio.', 'roles' => $ADM, 'filtros' => ['desde', 'hasta'], 'gen' => ['GenInstitucional', 'auditoria'], 'sensible' => true, 'orient' => 'h'],
    ],
];
