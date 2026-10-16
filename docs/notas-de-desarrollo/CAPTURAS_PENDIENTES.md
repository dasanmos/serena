# Capturas pendientes para el Centro de ayuda

Guarda cada captura en `public/assets/img/ayuda/` con **exactamente** el nombre indicado (JPG, ~1000-1400 px de ancho, menos de 400 KB). Mientras un archivo no exista, la ayuda no muestra nada en ese lugar (no da error).

Forma rápida: `python tools/capturar_ayuda.py --url https://tu-sitio` las toma solas con el modo demostración (datos ficticios). Las que no tienen página (`—`) hay que tomarlas a mano.

**108 pendientes.** Este archivo se regenera con `python tools/capturar_ayuda.py --lista`.

## general (2)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `gen-registro-codigo-1.jpg` | Cómo entrar a Serena → Crear una cuenta (estudiantes) | `—` | Paso 1: escribir el código DANE del colegio |
| `gen-voz-1.jpg` | Conoce la pantalla → Dictado por voz 🎤 | `—` | Botón de micrófono junto a un cuadro de texto |

## cuenta (1)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `cta-ayuda-ia-1.jpg` | Ayuda y descargas → Pregúntale a Serena (asistente con IA) | `/ayuda.php` | Tarjeta «Pregúntale a Serena» con una respuesta |

## estudiante (10)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `est-notas-1.jpg` | Mis notas → Ver mi boletín | `/boletin.php` | Boletín de un período con notas por asignatura |
| `est-notificaciones-1.jpg` | Notificaciones → Qué son | `/estudiante/notificaciones.php` | Lista de notificaciones |
| `est-habitos-1.jpg` | Hábitos → Cumplirlos cada día | `/estudiante/habitos.php` | Hábitos de hoy |
| `est-metas-1.jpg` | Metas → Crear una meta | `/estudiante/metas.php` | Nueva meta con subtareas |
| `est-recordatorios-1.jpg` | Recordatorios → Crear un recordatorio | `/estudiante/recordatorios.php` | Nuevo recordatorio |
| `est-citas-1.jpg` | Citas → Pedir una cita | `/estudiante/citas.php` | Horarios disponibles para una cita |
| `est-encuestas-1.jpg` | Encuestas → Cómo funcionan | `/estudiante/encuestas.php` | Lista de encuestas |
| `est-chat-1.jpg` | Chat → Los dos canales | `/estudiante/mensajes.php` | Chat con los dos canales |
| `est-recursos-1.jpg` | Recursos de apoyo → Qué encuentras | `/estudiante/recursos_apoyo.php` | Recursos de apoyo |
| `est-respiracion-1.jpg` | Respiración → Cómo usarlo | `/estudiante/respiracion.php` | Ejercicio de respiración |

## psicologo (13)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `psi-panel-1.jpg` | Panel → Qué ves | `/psicologo/panel.php` | Panel del psicólogo |
| `psi-reportes-1.jpg` | Reportes → Generar un informe | `/psicologo/reportes.php` | Reportes con filtros |
| `simpade-1.jpg` | SIMPADE: permanencia escolar → Panel | `/simpade.php` | Panel de permanencia con indicadores |
| `simpade-2.jpg` | SIMPADE: permanencia escolar → Detección | `/simpade.php` | Estudiantes sugeridos con los factores de riesgo |
| `simpade-3.jpg` | SIMPADE: permanencia escolar → Casos y acciones de permanencia | `/simpade.php` | Detalle de un caso con sus acciones de permanencia |
| `simpade-4.jpg` | SIMPADE: permanencia escolar → Retiros | `/simpade.php` | Registro de un retiro con causa y destino |
| `psi-estudiantes-1.jpg` | Estudiantes → Buscar y filtrar | `/psicologo/estudiantes.php` | Lista de estudiantes |
| `psi-estudiantes-2.jpg` | Estudiantes → El detalle de un estudiante | `/psicologo/estudiantes.php` | Detalle de un estudiante (usa una cuenta de prueba) |
| `psi-seguimientos-1.jpg` | Mis seguimientos → Esta vista | `/psicologo/seguimientos.php` | Mis seguimientos (psicólogo) |
| `psi-agenda-1.jpg` | Agenda → Publicar disponibilidad | `/psicologo/agenda.php` | Agenda del psicólogo |
| `psi-alertas-1.jpg` | Alertas tempranas → Atenderlas | `/psicologo/alertas.php` | Alertas tempranas |
| `psi-encuestas-1.jpg` | Encuestas → Crear una encuesta | `/psicologo/encuestas.php` | Nueva encuesta |
| `psi-chat-1.jpg` | Chat → Cómo funciona | `/psicologo/mensajes.php` | Chat del personal |

## orientador (5)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `ori-panel-1.jpg` | Panel → Qué ves | `/orientador/panel.php` | Panel del orientador |
| `ori-grupo-1.jpg` | Mi grupo → La lista de estudiantes | `/orientador/grupo.php` | Mi grupo (orientador) |
| `ori-seguimientos-1.jpg` | Mis seguimientos → Cómo funciona | `/orientador/seguimientos.php` | Mis seguimientos (orientador) |
| `ori-agenda-1.jpg` | Agenda → Cómo funciona | `/psicologo/agenda.php` | Agenda del orientador |
| `ori-alertas-1.jpg` | Alertas → Cómo usarlas | `/orientador/alertas.php` | Alertas del orientador |

## docente (10)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `doc-panel-1.jpg` | Panel → Qué ves | `/docente/panel.php` | Panel del docente |
| `doc-grupo-1.jpg` | Mi grupo → La lista | `/docente/grupo.php` | Mi grupo (docente) |
| `doc-pulso-1.jpg` | Pulso de grupo → Ver los resultados | `/docente/pulso.php` | Pulso de grupo (docente) |
| `doc-seguimiento-1.jpg` | Seguimiento (notas) → Elegir qué vas a calificar | `/docente/seguimiento.php` | Tabla de Seguimiento con período, grupo, asignatura y evaluación |
| `doc-seguimiento-2.jpg` | Seguimiento (notas) → Calificar | `/docente/seguimiento.php` | Panel de calificación del estudiante seleccionado |
| `doc-seguimiento-3.jpg` | Seguimiento (notas) → Dictar notas por voz | `/docente/seguimiento.php` | Dictado de notas: botón de micrófono y cuadro «Aplicar» |
| `doc-seguimiento-4.jpg` | Seguimiento (notas) → Actividades y logros | `/docente/seguimiento.php` | Sección «Actividades y logros del período» al final de Seguimiento |
| `doc-expediente-1.jpg` | Expedientes y observador → Expedientes | `/estudiantes.php` | Lista de estudiantes con acceso al expediente |
| `doc-asistencia-1.jpg` | Asistencia → Tomar lista | `/docente/asistencia.php` | Lista de asistencia de un grupo |
| `doc-planeador-1.jpg` | Planeador y diario de campo → Cómo se usa | `/docente/planeador.php` | Planeador de clases por período |

## coordinador (2)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `coo-panel-1.jpg` | Panel → Qué ves | `/coordinador/panel.php` | Panel del coordinador |
| `coo-reportes-1.jpg` | Reportes → Generar un informe | `/coordinador/reportes.php` | Reportes del coordinador con filtros |

## rector (9)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `rec-panel-1.jpg` | Panel → Qué ves | `/rector/panel.php` | Panel del rector |
| `rec-informes-1.jpg` | Informes → Generar un informe | `/informes.php` | Catálogo de informes por categoría |
| `rec-informes-2.jpg` | Informes → Certificados con QR | `/informes.php` | Emisión de un certificado con código QR |
| `simat-1.jpg` | SIMAT: matrícula lista para reportar → Resumen | `/simat.php` | Resumen del SIMAT con tarjetas y lista «Antes de reportar» |
| `simat-2.jpg` | SIMAT: matrícula lista para reportar → Revisión | `/simat.php` | Revisión de estudiantes con errores y avisos |
| `simat-3.jpg` | SIMAT: matrícula lista para reportar → Datos SIMAT | `/simat.php` | Datos SIMAT: procedencia, subsidio, internado y matrícula contratada |
| `simat-4.jpg` | SIMAT: matrícula lista para reportar → Conciliar con el listado del SIMAT | `/simat.php` | Resultado de conciliar con el listado del SIMAT |
| `simat-5.jpg` | SIMAT: matrícula lista para reportar → Novedades | `/simat.php` | Lista de novedades pendientes y reportadas |
| `simat-6.jpg` | SIMAT: matrícula lista para reportar → Ajustes | `/simat.php` | Ajustes del reporte SIMAT |

## administrador (28)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `adm-panel-1.jpg` | Panel → Qué ves | `/administrador/panel.php` | Panel del administrador |
| `adm-auditoria-1.jpg` | Auditoría → Consultarla | `/administrador/auditoria.php` | Auditoría |
| `adm-ver-como-1.jpg` | Ver como usuario → Cómo usarlo | `/administrador/ver_como.php` | Ver como usuario |
| `adm-usuarios-1.jpg` | Usuarios → Buscar | `/administrador/usuarios.php` | Lista de usuarios |
| `adm-grupos-1.jpg` | Grupos → Crear grados y grupos | `/administrador/grupos.php` | Grados y grupos |
| `adm-asignar-1.jpg` | Asignar grupos → Cómo hacerlo | `/administrador/asignar_grupos_personal.php` | Asignar grupos al personal |
| `adm-importar-1.jpg` | Importar estudiantes → Importar | `/administrador/importar_estudiantes.php` | Importar estudiantes desde CSV |
| `adm-promocion-1.jpg` | Promoción de año → Aplicar la promoción | `/administrador/promocion.php` | Promoción de año |
| `adm-anios-1.jpg` | Años lectivos y períodos → Preparar el año | `/administrador/anios_lectivos.php` | Año lectivo con sus períodos y opciones de inscripción |
| `adm-plan-1.jpg` | Plan académico → Asignaturas y docentes | `/administrador/plan_academico.php` | Plan académico: asignaturas por grupo y docente |
| `adm-evaluacion-1.jpg` | Evaluación (escala y estructura) → Configuración inicial (una vez) | `/administrador/evaluacion_config.php` | Escala y estructura de evaluación del colegio |
| `adm-horarios-1.jpg` | Horarios → Armar los horarios | `/horarios_admin.php` | Editor de horario por grupo con conteo de horas |
| `adm-institucion-1.jpg` | Datos de la institución → Qué configurar | `/administrador/institucion.php` | Formulario de datos de la institución |
| `adm-expedientes-1.jpg` | Expedientes del estudiante → Qué contiene | `/estudiantes.php` | Expediente de un estudiante |
| `adm-casos-1.jpg` | Casos → Cómo se usa | `/casos.php` | Lista de casos de convivencia y apoyo |
| `adm-informes-1.jpg` | Informes → Generar un informe | `/informes.php` | Catálogo de informes por categoría |
| `adm-informes-2.jpg` | Informes → Certificados con QR | `/informes.php` | Emisión de un certificado con código QR |
| `adm-refuerzos-1.jpg` | Refuerzos y planes de mejoramiento → Cómo se usa | `/refuerzos.php` | Refuerzos del período y planes de mejoramiento |
| `adm-restaurante-1.jpg` | Restaurante escolar → Cómo se usa | `/restaurante.php` | Beneficiarios y asistencia del restaurante |
| `adm-cartera-1.jpg` | Cartera → Cómo se usa | `/cartera.php` | Cargos y pagos por estudiante |
| `adm-caracterizacion-1.jpg` | Caracterización poblacional → Cómo se usa | `/caracterizacion.php` | Formulario de caracterización de un estudiante |
| `adm-lugares-1.jpg` | Lugares de ayuda → Agregar y mantener lugares | `/administrador/lugares_apoyo.php` | Lugares de ayuda cargados |
| `adm-ia-1.jpg` | Diagnóstico de IA → Qué muestra | `/administrador/prueba_ia.php` | Diagnóstico del ecosistema IA |
| `adm-datos-prueba-1.jpg` | Generar datos de prueba → Solo para pruebas | `/administrador/generar_datos_prueba.php` | Pantalla de confirmación para generar datos de prueba |
| `adm-aprobaciones-1.jpg` | Aprobaciones → Resolverlas | `/administrador/aprobaciones.php` | Solicitudes pendientes |
| `adm-password-1.jpg` | Resetear contraseña → Cómo hacerlo | `/administrador/resetear_password.php` | Resetear contraseña |
| `adm-configuracion-1.jpg` | Configuración → Copias de seguridad guardadas | `/administrador/configuracion.php` | Copias de seguridad |
| `adm-salud-1.jpg` | Salud del sistema → Qué muestra | `/administrador/salud.php` | Salud del sistema |

## superadmin (17)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `sup-panel-1.jpg` | Panel → Qué ves | `/superadmin/panel.php` | Panel de plataforma del superadmin |
| `sup-colegios-1.jpg` | Colegios → Crear un colegio | `/superadmin/colegios.php` | Formulario para crear un colegio |
| `sup-colegios-2.jpg` | Colegios → Buscar, editar y suspender | `/superadmin/colegios.php` | Lista de colegios con filtros y acciones masivas |
| `sup-importar-1.jpg` | Importar colegios → Importar con un CSV | `/superadmin/colegios_importar.php` | Importación de colegios por CSV |
| `sup-territorio-1.jpg` | Territorio → Importar el territorio, en este orden | `/superadmin/territorio.php` | Pantalla de Territorio con las importaciones |
| `sup-secretarias-1.jpg` | Secretarías → Crear una secretaría | `/superadmin/secretarias.php` | Lista de secretarías |
| `sup-secretarias-2.jpg` | Secretarías → Asignarle colegios | `/superadmin/secretarias.php` | Asignación de municipios y colegios a una secretaría |
| `sup-personal-1.jpg` | Personal de colegios → Qué puedes hacer | `/superadmin/personal.php` | Personal de colegios con filtros y acciones |
| `sup-trasladar-1.jpg` | Trasladar estudiante → Cómo trasladarlo | `/superadmin/trasladar_estudiante.php` | Traslado de un estudiante a otro colegio |
| `sup-comparativo-1.jpg` | Comparativo → Cómo leerlo | `/superadmin/comparativo.php` | Comparativo de colegios con semáforo |
| `sup-reportes-1.jpg` | Reportes → Generar un reporte | `/superadmin/reportes.php` | Reporte mensual por colegio o municipio |
| `sup-comunicados-1.jpg` | Comunicados → Crear un comunicado | `/superadmin/comunicados.php` | Formulario para crear un comunicado |
| `sup-comunicados-2.jpg` | Comunicados → Enviarlo por lotes | `/superadmin/comunicados.php` | Avance del envío y estado de cada destinatario |
| `sup-maestro-1.jpg` | Cuenta maestra → Elegir con qué rol entrar | `/elegir_rol.php` | Pantalla para elegir el rol con una cuenta maestra |
| `sup-demo-1.jpg` | Cuenta maestra → Modo demostración | `/elegir_rol.php` | Centro de control del modo demostración |
| `sup-salud-1.jpg` | Auditoría, salud y respaldos → Salud del sistema | `/administrador/salud.php` | Salud del sistema |
| `sup-respaldos-1.jpg` | Auditoría, salud y respaldos → Respaldos y ajustes | `/administrador/salud.php` | Respaldos y ajustes |

## secretaria (9)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `sec-panel-1.jpg` | Panel → Qué ves | `/secretaria/panel.php` | Panel de la secretaría |
| `sec-comparativo-1.jpg` | Comparativo → Cómo leerlo | `/secretaria/comparativo.php` | Comparativo de los colegios de la secretaría |
| `sec-reportes-1.jpg` | Reportes → Generar un reporte | `/secretaria/reportes.php` | Reporte mensual de la secretaría |
| `sec-colegios-1.jpg` | Colegios → Lo que puedes hacer | `/secretaria/colegios.php` | Colegios de la secretaría |
| `sec-importar-1.jpg` | Colegios → Importar colegios | `/secretaria/colegios.php` | Importación de colegios por CSV |
| `sec-territorio-1.jpg` | Territorio → Importar el territorio, en este orden | `/secretaria/territorio.php` | Pantalla de Territorio con las importaciones |
| `sec-personal-1.jpg` | Personal → Qué puedes hacer | `/secretaria/personal.php` | Personal de los colegios de la secretaría |
| `sec-trasladar-1.jpg` | Trasladar estudiante → Cómo trasladarlo | `/secretaria/trasladar_estudiante.php` | Traslado de un estudiante a otro colegio |
| `sec-comunicados-1.jpg` | Comunicados → Crear y enviar | `/secretaria/comunicados.php` | Comunicados de la secretaría |

## secretaria_academica (2)

| Archivo | Capítulo → sección | Página | Qué mostrar |
|---|---|---|---|
| `secad-informes-1.jpg` | Informes → Generar un informe | `/informes.php` | Catálogo de informes por categoría |
| `secad-informes-2.jpg` | Informes → Certificados con QR | `/informes.php` | Emisión de un certificado con código QR |
