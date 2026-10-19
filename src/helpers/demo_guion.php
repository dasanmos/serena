<?php
declare(strict_types=1);

/**
 * CATÁLOGO DEL MODO DEMOSTRACIÓN
 *
 * Una sola fuente de verdad para explicar TODOS los módulos de Serena:
 *  - demoModulosBase(): cada módulo una vez (nombre, ruta, qué es, qué decir al presentarlo, tip con los datos demo).
 *  - demoCatalogo():    por rol, los módulos agrupados como en el menú lateral + un recorrido corto sugerido.
 *
 * Los textos «decir» están escritos para leerse en voz alta frente a un público (directivos, docentes, familias).
 * Si agregas un módulo al menú (navegacion.php), agrégalo aquí también para que aparezca en el guion.
 */

/** Cada módulo una sola vez. Clave => [nombre, ruta, qué es, qué decir, tip con los datos de demostración]. */
function demoModulosBase(): array
{
    return [
        // ---------------------------------------------------------------- Estudiante
        'est_inicio' => ['Inicio del estudiante', '/estudiante/dashboard.php', 'Tablero personal: cómo se siente hoy, rachas, metas, recordatorios y accesos rápidos.',
            'Es lo primero que ve el estudiante: un espacio amable, sin notas ni presión, que lo invita a registrarse cada día.', 'Valentina Rojas tiene 21 días de registros: se ven rachas y gráficas con datos.'],
        'est_emociones' => ['Emociones', '/estudiante/emociones.php', 'Registro de cómo se siente (nivel 1-5, emoción y momento del día) varias veces al día.',
            'Es el corazón de Serena: de estos registros salen las alertas tempranas que ve el equipo de apoyo. El estudiante decide qué compartir.', 'Registra una emoción «ansioso, nivel 2» en vivo y luego míralo en Estadísticas.'],
        'est_sueno' => ['Sueño', '/estudiante/sueno.php', 'Registro de horas y calidad del sueño, con promedio semanal.',
            'El sueño explica gran parte del ánimo y del rendimiento; aquí se vuelve visible para el propio estudiante.', 'Los 21 días de sueño están cargados; Mateo duerme poco los últimos 5 días (se cruza con su alerta).'],
        'est_diario' => ['Diario', '/estudiante/diario.php', 'Diario personal protegido con PIN, con imágenes y dictado por voz.',
            'Nadie más lo lee: ni docentes ni psicólogos. Es un espacio seguro para desahogarse.', 'Crea el PIN y dicta una entrada por voz con el micrófono.'],
        'est_habitos' => ['Hábitos', '/estudiante/habitos.php', 'Hábitos diarios con seguimiento, rachas y porcentaje de cumplimiento.',
            'Convierte el bienestar en acciones pequeñas y medibles: agua, lectura, dormir temprano.', 'Valentina tiene 3 hábitos con 14 días de seguimiento.'],
        'est_metas' => ['Metas', '/estudiante/metas.php', 'Metas de corto y largo plazo con porcentaje de avance y subtareas.',
            'Ayuda a ponerle rumbo al año: el estudiante ve cuánto ha avanzado.', 'Hay dos metas activas (proyecto de ciencias 60 %, leer 5 libros 40 %).'],
        'est_recordatorios' => ['Recordatorios', '/estudiante/recordatorios.php', 'Recordatorios propios por correo y en pantalla.',
            'Evita olvidos y refuerza hábitos; el estudiante elige qué y cuándo.', 'Crea uno para mañana y muestra el aviso en el tablero.'],
        'est_citas' => ['Citas', '/estudiante/citas.php', 'Solicitud y confirmación de citas con el psicólogo u orientador.',
            'Pedir ayuda queda a un clic; la cita aparece en la agenda del profesional.', 'Mateo tiene una cita confirmada mañana a las 10:00 (ábrela luego como Psicólogo).'],
        'est_encuestas' => ['Encuestas', '/estudiante/encuestas.php', 'Encuestas que publica el equipo de apoyo (escala, opción múltiple, texto libre).',
            'Así el colegio escucha a su comunidad: respuestas sencillas, resultados agregados.', 'Hay una encuesta de clima escolar abierta para Sexto A (la respondieron 5 de 6).'],
        'est_chat' => ['Chat', '/estudiante/mensajes.php', 'Mensajería privada con psicología y orientación.',
            'Un canal directo y confidencial; muchos estudiantes escriben antes de atreverse a pedir cita.', 'Hay una conversación con la psicóloga y un mensaje sin leer.'],
        'est_recursos' => ['Recursos de apoyo', '/estudiante/recursos_apoyo.php', 'Mapa con lugares de ayuda cercanos (salud mental, hospitales, comisarías, ICBF) y cómo llegar.',
            'Si el colegio no alcanza, el estudiante sabe a dónde ir; abre Google Maps, Waze o Apple Maps.', 'Prueba «Cómo llegar» en una tarjeta.'],
        'est_respiracion' => ['Respiración', '/estudiante/respiracion.php', 'Ejercicio guiado de respiración con animación.',
            'Una herramienta inmediata para la ansiedad antes de un examen.', 'Inícialo y deja correr un ciclo completo.'],
        'est_urgente' => ['Ayuda urgente', '/estudiante/ayuda_urgente.php', 'Líneas de ayuda, contactos de emergencia y pasos a seguir en una crisis.',
            'Siempre visible y a un toque: lo más importante que ofrece Serena.', 'Muestra que no exige iniciar ninguna conversación para ver los teléfonos.'],
        'est_estadisticas' => ['Estadísticas', '/estudiante/estadisticas.php', 'Gráficas de emociones, sueño y hábitos, con análisis y PDF descargable.',
            'El estudiante se conoce mejor: ve patrones por día y por momento del día.', 'Descarga el PDF de estadísticas.'],
        'est_calendario' => ['Calendario', '/estudiante/calendario.php', 'Calendario con citas, metas y recordatorios.',
            'Reúne en un solo lugar lo que viene.', 'Aparecen la cita de mañana y las fechas límite de las metas.'],
        'est_notificaciones' => ['Notificaciones', '/estudiante/notificaciones.php', 'Avisos de insignias, recordatorios y mensajes.',
            'Refuerza lo positivo: logros e insignias.', 'Se marcan como leídas al abrirlas.'],
        'est_perfil' => ['Mi perfil', '/estudiante/perfil.php', 'Datos personales, foto y solicitud de cambio de datos.',
            'Los cambios sensibles pasan por aprobación del administrador.', 'Pide un cambio de nombre y apruébalo luego como Administrador (módulo Aprobaciones).'],

        // ---------------------------------------------------------------- Académico (varios roles)
        'notas' => ['Notas y boletín', '/boletin.php', 'Boletín por período con notas por asignatura, desempeño, observaciones y asistencia. Estudiantes y familias ven solo períodos publicados.',
            'El boletín es de la familia: ve el período publicado, sin tener que ir al colegio.', 'Los períodos 1 a 3 están publicados; el 4 está en curso y no se ve.'],
        'horario' => ['Horario', '/horario.php', 'Horario semanal por grupo o por docente; el estudiante y la familia ven el suyo, el personal elige cualquier grupo o docente. Se imprime.',
            'Cada quien ve su horario siempre actualizado, sin hojas pegadas en la cartelera.', 'Elige Sexto A; cambia a la vista de docente «Prof. Ana Ríos».'],
        'asistencia' => ['Asistencia (consulta)', '/asistencia.php', 'Resumen de asistencia de un estudiante: porcentaje, incidencias y estados por fecha.',
            'La familia ve ausencias y retardos al instante; el colegio deja de depender de planillas en papel.', 'Mateo tiene 4 ausencias sin justificar en las últimas 2 semanas.'],
        'asistencia_tomar' => ['Tomar asistencia', '/docente/asistencia.php', 'Registro diario de asistencia por clase (presente, tarde, ausente, excusa, permiso) o resumen al final del período.',
            'En 30 segundos el docente toma lista; «marcar a todos presentes» y solo corrige las excepciones.', 'Elige Matemáticas Sexto A y marca una ausencia.'],
        'asistencia_reporte' => ['Reporte de asistencia', '/asistencia_reporte.php', 'Reporte por grupo y rango de fechas con alerta de ausentismo (3 o más días sin justificar en 2 semanas), CSV e impresión.',
            'La coordinación detecta temprano a quién se está yendo del colegio.', 'Sexto A muestra la alerta de ausentismo de Mateo.'],
        'horarios_admin' => ['Editor de horarios', '/horarios_admin.php', 'Franjas horarias y cuadrícula día × hora por grupo, con detección de cruces de docente y de salón.',
            'Armar el horario deja de ser un rompecabezas: el sistema avisa si un docente queda en dos grupos a la misma hora.', 'Intenta poner a la Prof. Ana Ríos en dos grupos a la misma hora y mira el aviso de cruce.'],
        'seguimiento' => ['Seguimiento académico', '/docente/seguimiento.php', 'Período > grupo > asignatura: tabla de notas, actividades, logros y dictado de notas por voz.',
            'El docente califica una vez y la nota del período se calcula sola con los pesos.', 'Dicta «Juan Muñoz 4.5» en la actividad «Evaluación escrita» (está sin notas a propósito).'],
        'planeador' => ['Planeador', '/docente/planeador.php', 'Planeación de clases por período y asignatura.',
            'La planeación queda digital y consultable por coordinación.', 'Crea una planeación corta de Matemáticas.'],
        'pulso' => ['Pulso de grupo', '/docente/pulso.php', 'Pregunta rápida al grupo (1-5) con resultados anónimos y agregados.',
            'En un minuto el docente siente el ánimo del salón, sin señalar a nadie.', 'Hay un pulso abierto en Sexto A con 6 respuestas.'],
        'docente_grupo' => ['Mi grupo', '/docente/grupo.php', 'Estudiantes del grupo y acceso a su hoja de vida (sin datos emocionales).',
            'El docente conoce a su grupo sin acceder a lo emocional: la privacidad es parte del diseño.', 'Abre un estudiante y muestra que no hay emociones ni diario.'],
        'refuerzos' => ['Refuerzos y planes de mejoramiento', '/refuerzos.php', 'Estudiantes con desempeño bajo: refuerzos con nota y plan de mejoramiento con compromisos.',
            'Cierra el ciclo: no solo se detecta quién va mal, se registra qué se acordó para recuperarse.', 'Mateo tiene un refuerzo pendiente de Matemáticas y un plan activo.'],
        'plan_academico' => ['Plan académico', '/administrador/plan_academico.php', 'Asignaturas, áreas, intensidad horaria y asignación de docentes por grupo.',
            'Aquí se define quién enseña qué a quién; de ahí salen horarios, planillas y boletines.', '5 asignaturas por grupo; cambia un docente y revisa el panel de cruces en Horarios.'],
        'anios' => ['Años lectivos', '/administrador/anios_lectivos.php', 'Año lectivo con sus períodos, fechas y publicación del boletín por período.',
            'Publicar el boletín de un período es un clic.', 'Año 2026 con 4 períodos; los 3 primeros publicados.'],
        'evaluacion' => ['Evaluación', '/administrador/evaluacion_config.php', 'Escalas de desempeño, componentes y forma de calcular la nota del período.',
            'Cada colegio adapta su sistema institucional de evaluación (SIEE) sin pedir cambios al programador.', 'Escala numérica 1.0-5.0 con niveles Bajo, Básico, Alto y Superior.'],
        'boletines' => ['Boletines', '/secretaria_academica/boletines.php', 'Consolidado por grupo, descarga CSV y publicación del período.',
            'En una pantalla ve qué grupos tienen todas las notas listas para publicar.', 'Publica el período 4 y mira cómo lo ve la familia.'],
        'certificados' => ['Certificados', '/secretaria_academica/certificados.php', 'Certificados de estudio con código QR verificable, PDF y anulación.',
            'Cualquiera puede verificar que el certificado es auténtico escaneando el QR: se acabaron las falsificaciones.', 'Emite uno para Valentina y verifícalo en la página pública de verificación.'],
        'cert_plantillas' => ['Plantillas de certificados', '/administrador/certificados_plantillas.php', 'Textos de cada tipo de certificado (estudio, notas, conducta, etc.).',
            'El colegio redacta sus propios textos y firmas.', 'Edita un párrafo y emite un certificado de nuevo.'],
        'hoja_vida' => ['Hoja de vida del estudiante', '/estudiantes.php', 'Expediente completo: datos, familia, salud, matrícula, asistencia, observador, procesos, horas formativas, apoyos pedagógicos y certificados.',
            'Un solo expediente en lugar de seis carpetas; cada rol ve solo lo que le corresponde.', 'Abre a Valentina y recorre las pestañas; compara con lo que ve un docente.'],
        'caracterizacion' => ['Caracterización', '/caracterizacion.php', 'Caracterización de la población estudiantil (estrato, Sisbén, población, víctimas del conflicto) con filtros y cifras.',
            'Los datos que exige el Ministerio, listos para reportar y para focalizar apoyos.', 'Hay 6 estudiantes caracterizados con datos ficticios.'],
        'casos' => ['Casos', '/casos.php', 'Procesos de convivencia y casos (situaciones tipo I, II y III) con actuaciones y cierre.',
            'Cada actuación queda con fecha y autor: ruta de atención clara y trazable.', 'Hay un proceso en seguimiento y otro cerrado en Décimo.'],
        'informes' => ['Informes', '/informes.php', 'Catálogo de informes por rol (estudiantes, docentes, matrícula, asistencia, notas, refuerzos, institucional...) con PDF y CSV.',
            'Los informes que antes tardaban días salen en un clic, con encabezado institucional.', 'Genera «Estudiantes» en PDF; la descarga queda en la bitácora.'],
        'restaurante' => ['Restaurante escolar', '/restaurante.php', 'Beneficiarios, asistencia diaria al comedor y pagos.',
            'Control de cupos y raciones entregadas, sin planillas.', '10 beneficiarios, asistencia de la última semana y pagos del mes.'],
        'cartera' => ['Cartera', '/cartera.php', 'Cargos, pagos y saldos por estudiante.',
            'Saber quién debe qué, con recibos y paz y salvo.', 'Cargos de matrícula y transporte con pagos parciales.'],
        'simat' => ['SIMAT', '/simat.php', 'Preparación del reporte al SIMAT: datos requeridos, validación, novedades (ingreso, retiro, traslado, cambio de grupo), exportación CSV y conciliación.',
            'Reportar al SIMAT deja de ser una pesadilla de fin de año: el sistema valida antes de exportar y lleva las novedades al día.', 'Pestañas Revisión, Datos SIMAT (8 estudiantes sin datos), Novedades (2 pendientes) y Reportes.'],
        'simpade' => ['SIMPADE', '/simpade.php', 'Prevención de la deserción: riesgos con puntaje y factores, seguimientos, retiros y estrategias.',
            'Detecta a tiempo quién está en riesgo de abandonar y deja registro de cada llamada, reunión o compromiso.', '4 casos de riesgo (1 alto), 3 seguimientos y 1 retiro.'],

        // ---------------------------------------------------------------- Matrícula
        'inscripciones' => ['Inscripciones', '/secretaria_academica/inscripciones.php', 'Solicitudes de cupo hechas por las familias desde la página pública, con código de seguimiento.',
            'La familia se inscribe desde su casa y consulta el estado con un código; el colegio revisa y decide.', 'Hay 4 inscripciones: 2 pendientes, 1 en revisión y 1 admitida.'],
        'matriculas' => ['Matrículas', '/secretaria_academica/matriculas.php', 'Matrícula por año, grado y grupo, con número de matrícula y paso de inscripción a matrícula.',
            'Al admitir una inscripción se crea el usuario del estudiante y su matrícula.', '24 matrículas activas con número asignado.'],
        'aprobaciones' => ['Aprobaciones', '/administrador/aprobaciones.php', 'Solicitudes de cambio de datos de usuarios que esperan aprobación.',
            'Los datos sensibles no cambian sin una segunda mirada.', 'Aprueba la solicitud que dejaste hecha como estudiante.'],
        'importar_est' => ['Importar estudiantes', '/administrador/importar_estudiantes.php', 'Carga masiva de estudiantes desde Excel/CSV con plantilla de ejemplo.',
            'Cargar 800 estudiantes toma minutos, no semanas.', 'Descarga el archivo de ejemplo.'],
        'promocion' => ['Promoción de año', '/administrador/promocion.php', 'Pasa a los estudiantes al siguiente grado al terminar el año, con copia de seguridad previa.',
            'El cierre de año es un asistente guiado, con respaldo automático antes de tocar nada.', 'Solo mirar: en la demo no la ejecutes.'],

        // ---------------------------------------------------------------- Apoyo / bienestar (personal)
        'panel_psicologo' => ['Panel de psicología', '/psicologo/panel.php', 'Resumen del día: alertas, citas, estudiantes con ánimo bajo y mensajes.',
            'Empieza el día viendo a quién atender primero.', 'Mateo aparece con alerta alta.'],
        'psi_estudiantes' => ['Estudiantes (psicología)', '/psicologo/estudiantes.php', 'Listado con estado emocional reciente y acceso al detalle de cada estudiante.',
            'Las emociones solo las ven psicología, orientación y administración.', 'Abre a Mateo: bajón de 5 días, poco sueño, inasistencias.'],
        'alertas' => ['Alertas tempranas', '/psicologo/alertas.php', 'Alertas automáticas por niveles emocionales bajos o inactividad, con nivel de urgencia y marcado como atendida.',
            'El sistema no diagnostica: avisa. La decisión siempre es de la persona profesional.', 'Hay una alerta de nivel alto (Mateo).'],
        'seguimientos' => ['Seguimientos', '/psicologo/seguimientos.php', 'Planes de seguimiento por estudiante con notas de sesión y próximos pasos.',
            'Cada sesión deja un registro ordenado; si cambia la persona profesional, el caso no se pierde.', 'Abre el seguimiento de Mateo.'],
        'agenda' => ['Agenda', '/psicologo/agenda.php', 'Agenda de citas con disponibilidad, recordatorios y exportación a calendario (.ics).',
            'Los estudiantes piden la cita y la agenda se organiza sola.', 'Cita de mañana 10:00 con Mateo; exporta el .ics.'],
        'psi_encuestas' => ['Encuestas (creación y resultados)', '/psicologo/encuestas.php', 'Crea encuestas para un grupo o todo el colegio y revisa resultados con CSV.',
            'Medir el clima escolar con datos propios, no con impresiones.', 'La encuesta «Clima escolar y bienestar» ya tiene resultados.'],
        'psi_reportes' => ['Reportes de bienestar', '/psicologo/reportes.php', 'Reportes agregados por grupo y período, con PDF y CSV.',
            'Reportes para el comité de convivencia sin exponer datos individuales.', 'Descarga el PDF.'],
        'panel_orientador' => ['Panel de orientación', '/orientador/panel.php', 'Resumen de los grupos a cargo: ánimo, alertas y seguimientos.',
            'Orientación acompaña a sus grupos asignados (uno o varios).', 'Orientador con Sexto A y B.'],
        'ori_grupo' => ['Mi grupo (orientación)', '/orientador/grupo.php', 'Estudiantes de los grupos a cargo con su estado reciente.',
            'Vista de grupo para conversar con quien más lo necesita.', 'Cambia entre Sexto A y Sexto B.'],

        // ---------------------------------------------------------------- Paneles por rol
        'panel_acudiente' => ['Panel de la familia', '/acudiente/panel.php', 'Hijos vinculados, matrícula, boletín reciente y accesos a su hoja de vida.',
            'La familia entra y encuentra todo de sus hijos en un solo lugar.', 'Luz Marina es acudiente de Valentina y Sofía.'],
        'panel_docente' => ['Panel del docente', '/docente/panel.php', 'Grupos, alertas de ausentismo del grupo a cargo y accesos a asistencia y seguimiento.',
            'Lo primero que ve el docente: lo que necesita hacer hoy.', 'Prof. Ana Ríos dirige Sexto A.'],
        'panel_coordinador' => ['Panel de coordinación', '/coordinador/panel.php', 'Indicadores de convivencia, asistencia y bienestar del colegio.',
            'Una mirada institucional para decidir con datos.', 'Compáralo luego con el del Rector.'],
        'coord_reportes' => ['Reportes de coordinación', '/coordinador/reportes.php', 'Reportes agregados de bienestar y convivencia con PDF y CSV.',
            'Insumos para el comité de convivencia.', 'Descarga el CSV.'],
        'panel_rector' => ['Panel institucional', '/rector/panel.php', 'Indicadores del colegio y accesos a lo académico, matrícula e informes.',
            'La rectoría ve el colegio completo sin pedirle datos a nadie.', 'Compara las cifras con los otros roles.'],
        'panel_secad' => ['Panel de secretaría académica', '/secretaria_academica/panel.php', 'Pendientes de inscripciones, matrículas, boletines y certificados.',
            'Qué se atiende hoy en la secretaría.', 'Inscripciones pendientes y períodos por publicar.'],
        'panel_admin' => ['Panel del administrador', '/administrador/panel.php', 'Resumen de usuarios, grupos, solicitudes y accesos a la configuración del colegio.',
            'El administrador gobierna su colegio: usuarios, grupos y configuración.', 'Camila Restrepo administra el Colegio Demostración.'],

        // ---------------------------------------------------------------- Institución y sistema (administrador)
        'institucion' => ['Institución', '/administrador/institucion.php', 'Perfil oficial del colegio: nombre, resolución, DANE, rector, firmas, logo y color institucional.',
            'Con estos datos se arman los encabezados de informes, certificados y boletines.', 'Perfil ya completo con datos ficticios.'],
        'grupos' => ['Grupos', '/administrador/grupos.php', 'Grados y grupos, director de grupo y cupos.',
            'La estructura del colegio: de aquí cuelga todo lo demás.', '4 grupos: Sexto A/B y Décimo A/B.'],
        'asignar_grupos' => ['Asignar grupos', '/administrador/asignar_grupos_personal.php', 'Asigna uno o varios grupos (o un grado) a orientadores y personal.',
            'Un orientador puede acompañar varios grupos sin duplicar usuarios.', 'El orientador demo tiene Sexto A y B.'],
        'usuarios' => ['Usuarios', '/administrador/usuarios.php', 'Crear, editar, importar y exportar usuarios; activar y desactivar.',
            'Alta masiva con Excel, contraseñas temporales y roles claros.', 'Prueba filtrar por rol; descarga la plantilla de importación.'],
        'auditoria' => ['Auditoría', '/administrador/auditoria.php', 'Bitácora de acciones: quién hizo qué y cuándo, con exportación CSV.',
            'Confianza y trazabilidad: toda acción sensible queda registrada.', 'Busca la acción «demo_entrar» de esta misma presentación.'],
        'ver_como' => ['Ver como usuario', '/administrador/ver_como.php', 'El administrador ve la plataforma tal como la ve otro usuario, para dar soporte.',
            'Soporte sin pedir contraseñas; queda en la auditoría.', 'Míralo como un estudiante y vuelve con «Volver a administrador».'],
        'resetear' => ['Resetear contraseña', '/administrador/resetear_password.php', 'Genera una contraseña temporal para quien la olvidó.',
            'Soporte en 20 segundos; el usuario debe cambiarla al entrar.', 'Solo mirar: no reinicies cuentas demo.'],
        'lugares' => ['Lugares de ayuda', '/administrador/lugares_apoyo.php', 'Lugares de ayuda del colegio que se suman a los globales y salen en el mapa del estudiante.',
            'Cada colegio carga sus aliados: comisaría de familia, centro de salud del barrio.', 'Agrega un lugar con coordenadas y mira el mapa del estudiante.'],
        'configuracion' => ['Configuración', '/administrador/configuracion.php', 'Textos de los correos del colegio, correo de prueba y resumen mensual de prueba.',
            'El colegio personaliza la voz de sus correos.', 'Envíate un correo de prueba (a cuentas demo no sale nada).'],
        'salud' => ['Salud del sistema', '/administrador/salud.php', 'Panel de solo lectura: uso, pendientes, alertas y si el correo funciona (el superadmin ve además copias, disco y servidor).',
            'Un vistazo para saber si todo está en orden sin entender de servidores.', 'Compara lo que ve el Administrador con lo que ve el Superadmin.'],

        // ---------------------------------------------------------------- Secretaría de Educación y Superadmin
        'sec_panel' => ['Panel de la Secretaría', '/secretaria/panel.php', 'Resumen de todos los colegios de la secretaría: uso, alertas y cobertura.',
            'La Secretaría ve su territorio completo sin pedir informes colegio por colegio.', 'Usa colegios reales: evítalo en presentaciones públicas.'],
        'sec_comparativo' => ['Comparativo', '/secretaria/comparativo.php', 'Compara colegios entre sí en participación, ánimo y alertas.',
            'Focaliza el apoyo donde más se necesita.', 'Cambia el colegio en contexto con el selector superior.'],
        'sec_reportes' => ['Reportes de la Secretaría', '/secretaria/reportes.php', 'Reportes agregados por colegio y por período.',
            'Sin datos individuales: solo tendencias.', 'Descarga un reporte.'],
        'sec_colegios' => ['Colegios', '/secretaria/colegios.php', 'Alta, edición y estado de los colegios; usuarios administradores iniciales.',
            'Incorporar un colegio nuevo toma minutos.', 'Mira la ficha de un colegio.'],
        'sec_importar' => ['Importar colegios', '/secretaria/colegios_importar.php', 'Carga masiva de colegios desde Excel/CSV.',
            'Un municipio entero en un solo archivo.', 'Descarga el ejemplo.'],
        'sec_territorio' => ['Territorio', '/secretaria/territorio.php', 'Municipios, núcleos educativos y zonas, con la voz de la comunidad.',
            'Conecta cada colegio con su territorio para planear apoyos.', 'Mira los núcleos y su cobertura.'],
        'sec_personal' => ['Personal', '/secretaria/personal.php', 'Personal de los colegios: roles, estado y reasignación.',
            'La Secretaría ve quién atiende a los estudiantes.', 'Filtra por rol psicólogo.'],
        'sec_trasladar' => ['Trasladar estudiante', '/secretaria/trasladar_estudiante.php', 'Traslada un estudiante de un colegio a otro conservando su historia.',
            'El estudiante no empieza de cero al cambiar de colegio.', 'Solo mirar: no traslades estudiantes reales.'],
        'sec_comunicados' => ['Comunicados', '/secretaria/comunicados.php', 'Comunicados por correo a colegios o a todos, con estado de envío.',
            'Una circular llega a todos los colegios en un envío.', 'Redacta uno sin enviarlo.'],
        'sa_panel' => ['Panel global', '/superadmin/panel.php', 'Panel de la plataforma: colegios, usuarios y uso.',
            'La visión de quien administra Serena para todos los colegios.', 'Usa colegios reales.'],
        'sa_comparativo' => ['Comparativo', '/superadmin/comparativo.php', 'Compara colegios entre sí.',
            'Detecta colegios con baja participación.', 'Elige un colegio en contexto.'],
        'sa_reportes' => ['Reportes globales', '/superadmin/reportes.php', 'Reportes agregados de toda la plataforma.',
            'Cifras de impacto para informes a aliados.', 'Descarga un reporte.'],
        'sa_colegios' => ['Colegios', '/superadmin/colegios.php', 'Gestión de colegios de la plataforma.',
            'Alta y baja de colegios con su administrador inicial.', 'Mira el Colegio Demostración (DANE 999999999999).'],
        'sa_personal' => ['Personal', '/superadmin/personal.php', 'Personal de todos los colegios.',
            'Soporte transversal.', 'Filtra por colegio.'],
        'sa_secretarias' => ['Secretarías', '/superadmin/secretarias.php', 'Alta de Secretarías de Educación y sus colegios asignados.',
            'Una secretaría ve solo los colegios que le corresponden.', 'Mira la Secretaría de Educación de Medellín.'],
        'sa_importar' => ['Importar colegios', '/superadmin/colegios_importar.php', 'Carga masiva de colegios.',
            'Escala a cientos de colegios.', 'Descarga el ejemplo.'],
        'sa_territorio' => ['Territorio', '/superadmin/territorio.php', 'Municipios, núcleos y zonas.',
            'Base geográfica de la plataforma.', 'Mira los núcleos.'],
        'sa_comunicados' => ['Comunicados', '/superadmin/comunicados.php', 'Comunicados de la plataforma a colegios.',
            'Avisos de mantenimiento o novedades.', 'Redacta uno sin enviarlo.'],
        'sa_trasladar' => ['Trasladar estudiante', '/superadmin/trasladar_estudiante.php', 'Traslado de estudiantes entre colegios.',
            'Conserva el historial.', 'Solo mirar.'],
        'sa_lugares' => ['Lugares de ayuda globales', '/administrador/lugares_apoyo.php', 'Lugares de ayuda que ven los estudiantes de todos los colegios.',
            'Una red de apoyo común para toda la plataforma.', 'Agrega uno y verifica en el mapa de un estudiante demo.'],
        'sa_salud' => ['Salud de la plataforma', '/administrador/salud.php', 'Copias de seguridad, base de datos, disco, correo, tareas programadas y servidor.',
            'El tablero del equipo técnico: si algo falla, se ve aquí primero.', 'Verás copias, almacenamiento y servidor, que el Administrador no ve.'],
        'sa_ia' => ['Diagnóstico de IA', '/superadmin/prueba_ia.php', 'Estado del servicio de IA, modelos en uso y últimas llamadas.',
            'Transparencia sobre la IA: qué modelos, con qué resultado y qué se registró.', 'Pulsa «Refrescar» para ver los modelos gratis disponibles.'],
        'sa_config' => ['Respaldos y ajustes', '/administrador/configuracion.php', 'Copias de seguridad guardadas, descarga directa y textos de correo.',
            'Las copias se generan, verifican y rotan solas (se conservan las últimas).', 'Crea una copia nueva si lo necesitas.'],
        'sa_datos' => ['Datos de prueba', '/superadmin/generar_datos_prueba.php', 'Genera datos ficticios masivos en un colegio de pruebas, y los elimina.',
            'Para probar con volumen real sin tocar datos de verdad.', 'Solo mirar.'],

        // ---------------------------------------------------------------- Cuenta (todos los roles)
        'cta_seguridad' => ['Seguridad', '/seguridad.php', 'Verificación en 2 pasos (app, correo, WhatsApp), reconocimiento facial y códigos de recuperación.',
            'La seguridad es opcional pero fácil: cada persona activa el método que prefiera.', 'Mira los métodos disponibles; no actives nada en cuentas demo.'],
        'cta_sesiones' => ['Mis sesiones', '/sesiones.php', 'Dispositivos con sesión abierta y cierre remoto.',
            'Si se perdió un celular, se cierra su sesión desde otro dispositivo.', 'Cierra una sesión de ejemplo.'],
        'cta_preferencias' => ['Preferencias', '/preferencias.php', 'Tema claro/oscuro, tamaño de texto, alto contraste y avisos por correo.',
            'Accesibilidad real: texto grande, alto contraste y modo oscuro.', 'Cambia a modo oscuro y a texto grande y vuelve a mostrar un tablero.'],
        'cta_ayuda' => ['Ayuda', '/ayuda.php', 'Centro de ayuda con la guía del rol, descargable en PDF y HTML.',
            'Cada rol tiene su manual, siempre actualizado.', 'Descarga el PDF de tu rol.'],
    ];
}

/** Cuenta común a todos los roles (se agrega al final del guion). */
function demoGrupoCuenta(): array
{
    return ['Cuenta' => ['cta_seguridad', 'cta_sesiones', 'cta_preferencias', 'cta_ayuda']];
}

/**
 * Por rol: icono, título, detalle, grupos (como el menú lateral) y recorrido sugerido de ~8 minutos.
 * 'recorrido' es una lista de claves de demoModulosBase() en el orden en que conviene presentarlas.
 */
function demoCatalogo(): array
{
    $c = [
        'estudiante' => ['🎓', 'Estudiante', 'Su espacio personal de bienestar y de notas', [
            'Inicio' => ['est_inicio', 'est_calendario', 'est_notificaciones', 'est_perfil'],
            'Académico' => ['notas', 'horario', 'asistencia', 'hoja_vida_est', 'est_estadisticas'],
            'Bienestar' => ['est_emociones', 'est_sueno', 'est_diario', 'est_habitos', 'est_respiracion', 'est_urgente', 'est_recursos'],
            'Organización' => ['est_metas', 'est_recordatorios', 'est_citas'],
            'Comunicación' => ['est_encuestas', 'est_chat'],
        ], ['est_inicio', 'est_emociones', 'est_diario', 'est_respiracion', 'est_urgente', 'est_estadisticas', 'est_chat', 'notas']],

        'acudiente' => ['👨‍👩‍👧', 'Acudiente', 'La familia ve boletín, asistencia, horario y hoja de vida', [
            'Inicio' => ['panel_acudiente'],
            'Mi estudiante' => ['hoja_vida_est', 'notas', 'asistencia', 'horario'],
        ], ['panel_acudiente', 'notas', 'asistencia', 'horario', 'hoja_vida_est']],

        'docente' => ['📚', 'Docente', 'Clase, seguimiento académico y pulso del grupo', [
            'Inicio' => ['panel_docente'],
            'Clase' => ['docente_grupo', 'asistencia_tomar', 'asistencia_reporte', 'planeador', 'seguimiento', 'horario'],
            'Informes' => ['informes', 'refuerzos'],
            'Estudiantes' => ['hoja_vida', 'pulso'],
        ], ['panel_docente', 'asistencia_tomar', 'seguimiento', 'pulso', 'horario', 'refuerzos']],

        'orientador' => ['🧭', 'Orientador/a', 'Alertas, grupos y seguimientos', [
            'Inicio' => ['panel_orientador'],
            'Estudiantes' => ['hoja_vida', 'ori_grupo', 'casos', 'alertas', 'seguimientos', 'caracterizacion'],
            'Informes' => ['informes', 'simpade'],
            'Agenda' => ['agenda'],
        ], ['panel_orientador', 'alertas', 'ori_grupo', 'seguimientos', 'agenda', 'simpade']],

        'psicologo' => ['🧠', 'Psicólogo/a', 'Atención, agenda, encuestas y reportes', [
            'Inicio' => ['panel_psicologo', 'psi_reportes'],
            'Estudiantes' => ['hoja_vida', 'psi_estudiantes', 'casos', 'alertas', 'seguimientos', 'caracterizacion'],
            'Informes' => ['informes', 'simpade'],
            'Agenda' => ['agenda', 'psi_encuestas'],
        ], ['panel_psicologo', 'alertas', 'psi_estudiantes', 'seguimientos', 'agenda', 'psi_encuestas', 'psi_reportes', 'simpade']],

        'coordinador' => ['📋', 'Coordinador', 'Convivencia, asistencia y reportes del colegio', [
            'Inicio' => ['panel_coordinador', 'coord_reportes'],
            'Estudiantes' => ['hoja_vida', 'casos', 'caracterizacion'],
            'Académico' => ['boletines', 'refuerzos'],
            'Horarios y asistencia' => ['horarios_admin', 'horario', 'asistencia_reporte'],
            'Informes' => ['informes', 'simpade'],
            'Servicios' => ['restaurante'],
        ], ['panel_coordinador', 'asistencia_reporte', 'casos', 'horarios_admin', 'refuerzos', 'simpade', 'restaurante']],

        'rector' => ['🏛️', 'Rector', 'Vista institucional completa del colegio', [
            'Inicio' => ['panel_rector'],
            'Estudiantes' => ['hoja_vida', 'casos', 'caracterizacion'],
            'Matrícula' => ['inscripciones', 'matriculas'],
            'Académico' => ['anios', 'plan_academico', 'evaluacion', 'seguimiento', 'refuerzos', 'boletines', 'certificados', 'cert_plantillas'],
            'Horarios y asistencia' => ['horarios_admin', 'horario', 'asistencia_tomar', 'asistencia_reporte'],
            'Informes' => ['informes', 'simat', 'simpade'],
            'Servicios' => ['restaurante', 'cartera'],
            'Institución' => ['institucion'],
        ], ['panel_rector', 'informes', 'simat', 'simpade', 'matriculas', 'certificados', 'institucion']],

        'secretaria_academica' => ['🗂️', 'Secretaría académica', 'Inscripciones, matrículas, boletines, certificados y SIMAT', [
            'Inicio' => ['panel_secad'],
            'Matrícula' => ['inscripciones', 'matriculas'],
            'Estudiantes' => ['hoja_vida', 'caracterizacion'],
            'Académico' => ['anios', 'plan_academico', 'evaluacion', 'seguimiento', 'refuerzos', 'boletines', 'certificados'],
            'Horarios y asistencia' => ['horarios_admin', 'horario', 'asistencia_tomar', 'asistencia_reporte'],
            'Informes' => ['informes', 'simat', 'simpade'],
            'Servicios' => ['restaurante', 'cartera'],
        ], ['panel_secad', 'inscripciones', 'matriculas', 'boletines', 'certificados', 'simat', 'cartera']],

        'administrador' => ['⚙️', 'Administrador', 'Configuración y gobierno del colegio', [
            'Inicio' => ['panel_admin'],
            'Estudiantes' => ['hoja_vida', 'casos', 'caracterizacion', 'importar_est', 'promocion'],
            'Matrícula' => ['inscripciones', 'matriculas', 'aprobaciones'],
            'Académico' => ['anios', 'plan_academico', 'evaluacion', 'seguimiento', 'refuerzos', 'boletines', 'certificados', 'cert_plantillas'],
            'Horarios y asistencia' => ['horarios_admin', 'horario', 'asistencia_tomar', 'asistencia_reporte'],
            'Informes' => ['informes', 'simat', 'simpade'],
            'Servicios' => ['restaurante', 'cartera'],
            'Institución' => ['institucion', 'grupos', 'asignar_grupos', 'usuarios'],
            'Sistema' => ['auditoria', 'ver_como', 'resetear', 'lugares', 'configuracion', 'salud'],
        ], ['panel_admin', 'usuarios', 'grupos', 'plan_academico', 'horarios_admin', 'simat', 'auditoria', 'salud']],

        'secretaria' => ['🏢', 'Secretaría de Educación', 'Vista de todos los colegios de su secretaría', [
            'Inicio' => ['sec_panel', 'sec_comparativo', 'sec_reportes'],
            'Estudiantes' => ['sec_trasladar'],
            'Colegios' => ['sec_colegios', 'sec_importar', 'sec_territorio', 'sec_personal'],
            'Comunicación' => ['sec_comunicados'],
        ], ['sec_panel', 'sec_comparativo', 'sec_territorio', 'sec_colegios', 'sec_comunicados']],

        'superadmin' => ['🛡️', 'Superadministrador', 'Vista global de la plataforma y del sistema', [
            'Inicio' => ['sa_panel', 'sa_comparativo', 'sa_reportes'],
            'Estudiantes' => ['hoja_vida', 'sa_trasladar'],
            'Plataforma' => ['sa_colegios', 'sa_personal', 'sa_secretarias', 'sa_importar', 'sa_territorio', 'sa_comunicados'],
            'Sistema' => ['auditoria', 'sa_lugares', 'sa_salud', 'sa_ia', 'sa_config', 'sa_datos'],
        ], ['sa_panel', 'sa_colegios', 'sa_secretarias', 'sa_salud', 'sa_ia', 'sa_config']],
    ];

    // Variantes de un mismo módulo según quién lo presenta (misma pantalla, otro énfasis).
    $base = demoModulosBase();
    $base['hoja_vida_est'] = ['Hoja de vida', '/estudiante_ficha.php', 'Su expediente: datos, matrícula, asistencia y observaciones visibles para la familia.',
        'La familia ve lo que el colegio sabe del estudiante y puede verificar sus datos.', 'Valentina tiene datos completos y matrícula activa.'];

    $cuenta = demoGrupoCuenta();
    $salida = [];
    foreach ($c as $rol => [$icono, $titulo, $detalle, $grupos, $recorrido]) {
        $gruposRol = $grupos + $cuenta;
        $out = [];
        foreach ($gruposRol as $nombreGrupo => $claves) {
            $mods = [];
            foreach ($claves as $k) {
                if (!isset($base[$k])) {
                    continue;
                }
                [$nombre, $ruta, $queEs, $decir, $tip] = $base[$k];
                $mods[] = ['id' => $k, 'nombre' => $nombre, 'ruta' => $ruta, 'que_es' => $queEs, 'decir' => $decir, 'tip' => $tip];
            }
            if ($mods) {
                $out[$nombreGrupo] = $mods;
            }
        }
        $salida[$rol] = ['icono' => $icono, 'titulo' => $titulo, 'detalle' => $detalle, 'grupos' => $out, 'recorrido' => $recorrido];
    }
    return $salida;
}

/** Lista plana de módulos de un rol: [nombre, ruta, qué es, qué decir, grupo, id, tip]. */
function demoItemsPlanos(string $rol): array
{
    $cat = demoCatalogo();
    $items = [];
    foreach (($cat[$rol]['grupos'] ?? []) as $grupo => $mods) {
        foreach ($mods as $m) {
            $items[] = [$m['nombre'], $m['ruta'], $m['que_es'], $m['decir'], $grupo, $m['id'], $m['tip']];
        }
    }
    return $items;
}

/** Cuántos módulos distintos (por ruta) explica el guion en total, y por rol. */
function demoConteos(): array
{
    $porRol = [];
    $rutas = [];
    foreach (demoCatalogo() as $rol => $r) {
        $n = 0;
        foreach ($r['grupos'] as $mods) {
            foreach ($mods as $m) {
                $n++;
                $rutas[$m['ruta']] = true;
            }
        }
        $porRol[$rol] = $n;
    }
    return ['por_rol' => $porRol, 'distintos' => count($rutas)];
}

/** Matriz módulo × rol para el «Mapa de módulos»: ruta => [nombre, que_es, [roles...]]. */
function demoMapaModulos(): array
{
    $mapa = [];
    foreach (demoCatalogo() as $rol => $r) {
        foreach ($r['grupos'] as $mods) {
            foreach ($mods as $m) {
                $k = $m['ruta'];
                if (!isset($mapa[$k])) {
                    $mapa[$k] = ['nombre' => $m['nombre'], 'que_es' => $m['que_es'], 'ruta' => $m['ruta'], 'roles' => []];
                }
                $mapa[$k]['roles'][$rol] = true;
            }
        }
    }
    uasort($mapa, static fn (array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']));
    return $mapa;
}
