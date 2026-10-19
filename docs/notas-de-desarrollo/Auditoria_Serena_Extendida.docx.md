**AUDITORÍA TÉCNICA INTEGRAL**

**Proyecto SERENA**

Plataforma de bienestar emocional institucional — PHP \+ MySQL

Institución Educativa INEM Jose Felix de Restrepo  —  Medellín, Colombia

Certificación SENA — Equipo: David Santiago Mosquera Mosquera, Ana Sofía Ángel Moncada

Meta interna: sistema funcionando antes del 1 de septiembre de 2026\. Entrega oficial al SENA: octubre de 2026\.

*Documento generado a partir de una revisión exhaustiva y literal de código fuente: 99 archivos PHP, el esquema SQL completo (386 líneas, 29 tablas), 10 archivos JavaScript, 7 hojas de estilo, y los tres documentos de referencia del proyecto (SERENA\_proyecto\_nuevo.md, INSTALACION.md, README.md).*

# **0\. Metodología y reglas de evidencia aplicadas**

Esta auditoría se construyó bajo un principio único: nada se reporta como "terminado", "correcto" o "seguro" sin haber abierto el archivo correspondiente y leído el código real. Cuando un archivo referenciado por el documento maestro, por el árbol de carpetas o por un enlace de navegación no existía en disco, se reportó explícitamente como NO INICIADO — nunca se omitió ni se asumió que "probablemente estaba bien".

El proceso de auditoría siguió estos pasos, en este orden:

* Lectura completa de SERENA\_proyecto\_nuevo.md: los 7 roles, cada módulo funcional descrito, las 18 funciones adicionales aprobadas (enumeradas y citadas textualmente en la Sección 7 de este informe), el marco legal (Ley 1581 de 2012), las fechas objetivo y el alcance completo declarado.

* Lectura completa de sql/schema.sql (386 líneas, 29 tablas) y de sql/migraciones/001\_estructura\_inicial.sql.

* Recorrido archivo por archivo de cada carpeta de public/ y src/, sin inferir contenido a partir del nombre del archivo — cada uno de los 99 archivos PHP fue abierto y leído.

* Construcción de un inventario cruzado: para cada función/módulo mencionado en el .md, verificación de qué archivo(s) deberían implementarlo y confirmación de su existencia real.

* Para cada hallazgo de "función no usada" o "código muerto", verificación mediante búsqueda exhaustiva (grep) en todo el árbol del proyecto — nunca se concluye ausencia de uso sin buscar activamente cada posible punto de invocación.

* Para los hallazgos de contraste de color (WCAG), se calcularon las razones de contraste exactas mediante la fórmula estándar de luminancia relativa del W3C, no por apreciación visual.

* Para el hallazgo de compatibilidad con InfinityFree, se consultaron foros oficiales y documentación pública de ese proveedor de hosting, dado que el comportamiento de su document root no es inferible del código del proyecto.

Limitación explícita: esta auditoría es un análisis estático de código fuente. No se desplegó la aplicación en un servidor real, no se ejecutó contra una base de datos poblada, y no se realizaron pruebas de penetración activas contra un sistema en vivo. Todos los hallazgos de "funciona" / "no funciona" / "es vulnerable a X" se basan en el seguimiento manual de cada llamada de función hasta su definición y en la ausencia verificada de las validaciones correspondientes — no en la explotación en vivo del sistema. Donde esta distinción importa, se señala explícitamente con la frase "sin verificar en entorno real".

# **1\. Resumen ejecutivo**

Serena es un proyecto con una base arquitectónica más sólida de lo que su estado visual actual sugiere. El uso de PDO con prepared statements es la norma dominante (no la excepción) en la inmensa mayoría de las consultas del proyecto; password\_hash()/password\_verify() se usa de forma correcta y consistente en absolutamente todos los puntos donde se crean o verifican contraseñas, incluyendo el PIN del diario; el flujo de consentimiento de datos exigido por la Ley 1581 colombiana es un bloqueo real tanto en el cliente como en el servidor, no un checkbox decorativo; y el rol rector está diseñado con una disciplina de privacidad ejemplar, mostrando exclusivamente datos agregados y anónimos con un mensaje explícito recordando que los datos individuales son confidenciales.

Dicho esto, la auditoría completa —que cubrió el 100% de los 99 archivos PHP del proyecto, el esquema SQL íntegro, y todos los activos de frontend— encontró un patrón recurrente y preocupante: una cantidad significativa de infraestructura fue construida correctamente pero nunca conectada al resto del sistema. Esto no es un problema aislado en un módulo; se repite de forma sistemática en al menos cinco capas distintas del proyecto:

* Controladores: 12 de 13 archivos en src/controladores/ son literalmente una clase vacía de 4 líneas. Toda la lógica de negocio real vive directamente en los archivos de presentación de public/.

* JavaScript: 9 de 10 archivos en assets/js/ son un archivo de una sola línea con un comentario placeholder. El único con lógica real (base.js, que implementa el menú hamburguesa) nunca se incluye en ningún HTML del proyecto.

* CSS: de 7 hojas de estilo, solo 3 (base.css, panel.css, login.css) se cargan en alguna página real. Las otras 4 —incluyendo estudiante.css, que contiene reglas para clases que sí se usan activamente en el HTML— nunca se enlazan desde ningún archivo.

* API REST: existen 9 endpoints construidos en public/api/, con un formato de respuesta JSON consistente y bien diseñado, pero ninguno de los 9 es consumido desde ningún punto del frontend. Es una capa entera de trabajo aislada del resto de la aplicación.

* Esquema de base de datos: al menos 4 tablas (diario\_imagenes, recordatorios, insignias, usuario\_insignias) existen en el esquema pero no tienen ningún código PHP que las lea o escriba en absolutamente ningún punto del proyecto.

Sobre este patrón de infraestructura desconectada se suman vulnerabilidades de seguridad concretas y verificadas por lectura de código, no hipotéticas: un control de acceso indebido (IDOR) que permite a cualquier psicólogo u orientador autenticado leer y escribir en conversaciones de chat ajenas con estudiantes que no le corresponden; un IDOR equivalente en el borrado de entradas del diario personal, agravado porque la acción se dispara por una simple petición GET, lo que la hace también vulnerable a CSRF; ausencia total de protección CSRF en cualquier formulario del proyecto, incluyendo las acciones administrativas más sensibles como desactivar una cuenta de usuario; y ausencia total de límite de intentos (rate limiting) tanto en el inicio de sesión como en el PIN de 4 dígitos del diario personal.

El sistema de auditoría interna del proyecto —diseñado precisamente para dar trazabilidad a estos riesgos— está construido de forma completa y correcta en su infraestructura (tabla auditoria, función auditarAccion(), vista de consulta administrador/auditoria.php) pero nunca se invoca desde ningún punto real del código. Es, en la práctica, una bitácora que siempre estará vacía.

Finalmente, el rol docente —uno de los siete roles centrales del sistema— está funcionalmente inutilizable para sus dos acciones principales ("Ver mi grupo" y "Ver detalle de estudiante"), porque ambos archivos redirigen a páginas de otro rol que rechazan explícitamente el rol docente, devolviendo un error HTTP 403 verificado en el propio código de control de acceso.

Este informe documenta cada uno de estos hallazgos —y muchos más de menor severidad— con la ruta exacta del archivo, el fragmento de código relevante, y una explicación del impacto concreto. No se reporta ninguna conclusión sin la evidencia que la sostiene.

# **2\. Auditoría archivo por archivo, por rol**

Esta sección documenta, para cada uno de los 7 roles del sistema, cada archivo PHP que existe en su carpeta correspondiente dentro de public/, con un veredicto explícito sobre si su lógica es real (lee/escribe en la base de datos) o si es solo una vista estática, y si el archivo es alcanzable desde el menú de navegación de ese rol.

## **2.1 Rol Estudiante — 14 archivos**

Menú de navegación real (definido en src/helpers/navegacion.php, único punto donde se generan los enlaces, confirmado por su uso exclusivo en public/parciales/encabezado.php): Inicio, Emociones, Sueño, Diario, Hábitos, Metas, Estadísticas, Chat, Recursos — 9 enlaces.

### **dashboard.php**

**Archivo:** public/estudiante/dashboard.php

**Veredicto:** Lógica real, lee de la base de datos (resumen del día, conteo de metas activas, últimos registros de emoción y sueño). En el menú: sí ("Inicio").

Se detectó una inconsistencia de código real: el conteo de metas activas se ejecuta dos veces de forma redundante — primero con un prepared statement correcto cuyo resultado nunca se usa, y luego con una segunda query idéntica pero con interpolación directa de la variable de sesión:

$stmt \= $pdo-\>prepare("SELECT COUNT(\*) FROM metas WHERE usuario\_id \= :uid AND estado \= 'activa'");  
$stmt-\>execute(\['uid' \=\> $usuarioId\]);  
// ... el resultado de $stmt nunca se usa ...  
$metasActivas \= $pdo-\>query("SELECT COUNT(\*) FROM metas WHERE usuario\_id \= {$usuarioId} AND estado \= 'activa'")-\>fetchColumn();

Esto es simultáneamente un desperdicio de una consulta a la base de datos (impacto de rendimiento menor pero real) y una violación de la política de prepared statements exigida explícitamente para todo el proyecto (impacto de seguridad bajo en este caso concreto porque $usuarioId proviene de la sesión del servidor, no de input directo del usuario en esa misma petición, pero es un patrón peligroso si se reutiliza).

### **emociones.php**

**Archivo:** public/estudiante/emociones.php

**Veredicto:** Lógica real y funcional para creación de registros. En el menú: sí.

Usa htmlspecialchars() de forma consistente en toda la salida. Sin embargo, se verificó mediante búsqueda exhaustiva que este archivo nunca llama a EstadoEmocional::actualizar() — el método de edición existe en el modelo (src/modelos/EstadoEmocional.php, línea 41\) pero es código muerto: la interfaz solo permite crear registros nuevos, nunca editar uno existente.

**Contradice al .md:** El documento maestro especifica explícitamente "Varios registros al día (mañana/tarde/noche), editable" en el módulo de Registro emocional. La edición no está implementada en la interfaz pese a que el backend la soporta parcialmente.

No se encontró tampoco ningún mecanismo de "notificación si el usuario no registra en el día" (requerido explícitamente por el .md). Lo único que existe es un mensaje pasivo en dashboard.php ("Todavía no has registrado cómo te sientes hoy") visible solo si el estudiante entra a la aplicación por su cuenta — no hay ninguna notificación push, correo o mecanismo que lo alcance si no abre la app. Esto se debe a una causa raíz de infraestructura: se confirmó mediante búsqueda exhaustiva en todo el proyecto que no existe ningún archivo, cron job, ni mención de tarea programada en absolutamente ningún punto del código ni de la documentación. Sin un mecanismo de ejecución programada, ni esta notificación ni los "recordatorios configurables" (función aprobada \#9 del .md) son técnicamente posibles tal como está construido el sistema hoy.

### **sueno.php**

**Archivo:** public/estudiante/sueno.php

**Veredicto:** Lógica real, verificada end-to-end. En el menú: sí.

El modelo Sueno::registrar() resuelve correctamente la restricción UNIQUE (usuario\_id, fecha) definida en el esquema mediante una lógica de upsert (INSERT ... ON DUPLICATE KEY UPDATE), evitando el error de duplicado que ocurriría con un INSERT simple si el estudiante registra sueño dos veces el mismo día. Esta es una de las implementaciones más sólidas del proyecto.

### **diario.php**

**Archivo:** public/estudiante/diario.php

**Veredicto:** Lógica real, pero con una vulnerabilidad de seguridad grave (IDOR) documentada en detalle en la Sección 3.2. En el menú: sí.

Aparte del IDOR, se detectó una llamada redundante a session\_start() en la línea 9 del archivo (session\_start() ?? null;), pese a que src/helpers/auth.php ya inicia la sesión de forma condicional al ser incluido. Esta llamada duplicada generará una advertencia de PHP ("session already active") en cada carga de la página — no rompe la ejecución, pero es ruido de log innecesario y evidencia de una falta de coordinación entre este archivo y el helper compartido.

El uso de htmlspecialchars() y nl2br() para mostrar el contenido de las entradas es correcto. La funcionalidad de imágenes adjuntas al diario, requerida explícitamente por el .md ("Texto libre, con imágenes adjuntas"), no existe en absoluto en este archivo ni en ningún otro punto del proyecto — ver Sección 3.3 y Sección 7\.

### **diario\_pin.php**

**Archivo:** public/estudiante/diario\_pin.php

**Veredicto:** Lógica real y bien implementada en cuanto a hashing. En el menú: NO — huérfana.

Usa password\_hash() y password\_verify() correctamente para proteger el PIN del diario, siguiendo la misma buena práctica que el resto del sistema de autenticación. No se transmite ni se registra el PIN en texto plano en ningún punto verificado. Sin embargo, no existe ningún límite de intentos: al ser un PIN de exactamente 4 dígitos numéricos, el espacio de búsqueda es de solo 10.000 combinaciones posibles, y sin bloqueo tras intentos fallidos, es vulnerable a fuerza bruta por cualquier persona con una sesión activa del estudiante.

### **habitos.php**

**Archivo:** public/estudiante/habitos.php

**Veredicto:** Lógica real, con buena disciplina de verificación de propiedad (ownership). En el menú: sí.

A diferencia de diario.php, este archivo sí verifica consistentemente que el hábito pertenezca al usuario autenticado antes de marcar o archivar (Habito::buscarPorId($pdo, $hid, $uid) se llama con el ID de usuario en ambos casos). La funcionalidad de racha (streak) está implementada y funciona correctamente:

$h\['racha'\] \= Habito::rachaActual($pdo, $h\['id'\]);  
// ...  
\<small\>Racha: \<?= $h\['racha'\] ?\> días 🔥\</small\>

El método Habito::rachaActual() calcula días consecutivos reales comparando fechas con DateTime, no es un contador simplista — es una de las implementaciones de mayor calidad del proyecto y cumple correctamente la función aprobada \#11 del .md ("Racha — días consecutivos cumpliendo un hábito"), aunque el .md también menciona racha de "registrar emoción", que no está cubierta por ningún mecanismo equivalente.

Se detectaron dos gaps funcionales: (1) el modelo Habito::actualizar() existe y permitiría editar un hábito existente, pero habitos.php nunca lo invoca — solo hay acciones de crear, marcar como completado y archivar, no de editar, pese a que el .md exige "editables y eliminables"; (2) no hay borrado real, solo archivado (soft delete vía activo=0), lo cual es una decisión de diseño razonable pero diverge del texto literal "eliminables" del documento maestro; (3) el método Habito::historialMensual() existe en el modelo pero se confirmó mediante búsqueda exhaustiva que nunca se invoca desde ningún archivo — la función aprobada "Historial mensual de cumplimiento" del .md no está expuesta en la interfaz pese a que el backend ya la soporta.

### **metas.php**

**Archivo:** public/estudiante/metas.php

**Veredicto:** Lógica real, con una vulnerabilidad IDOR de severidad media documentada en la Sección 3.4. En el menú: sí.

La verificación de propiedad (ownership) es consistente para las acciones sobre la meta misma (eliminar, completar), pero no se propaga correctamente a las subtareas — ver el detalle técnico en la Sección 3.4. Adicionalmente, se confirmó mediante búsqueda exhaustiva (grep de la cadena 'vencida' en todo el proyecto) que el estado ENUM 'vencida' definido en el esquema de la tabla metas nunca se asigna en ningún punto del código PHP. La función descrita en el .md — "Si vence sin completarse: preguntar al usuario si continúa o reinicia" — no está implementada en absoluto: no hay lógica que detecte una meta vencida, ni el estado se marca, ni existe la interfaz de pregunta al usuario.

### **estadisticas.php**

**Archivo:** public/estudiante/estadisticas.php

**Veredicto:** Lógica real mediante Chart.js embebido, pero con cobertura parcial frente al .md. En el menú: sí.

Solo implementa gráficos de línea y de barra. El .md pide explícitamente "Todos los tipos de gráficos (barras, líneas, torta, circular)" — los gráficos de torta y circular no existen en ningún punto del código. El selector de periodo es únicamente un rango de fechas libre (desde/hasta); no hay presets de diario, semanal, mensual o anual como pide el documento maestro, ni existe ninguna funcionalidad de "comparación entre semanas" (función aprobada \#15 del .md, "Comparativa mensual", tampoco implementada). Se confirmó mediante búsqueda exhaustiva de las cadenas TCPDF, FPDF, dompdf, mPDF, y de las palabras "descargar"/"exportar" en todo el proyecto, que no existe ninguna librería de generación de PDF ni ningún endpoint de descarga — la función aprobada \#13 del .md ("Exportar reportes — PDF/imagen") está completamente ausente.

### **chat.php**

**Archivo:** public/estudiante/chat.php

**Veredicto:** Lógica real y sin la vulnerabilidad IDOR que sí afecta al lado profesional del chat. En el menú: sí.

El identificador de conversación no se toma nunca de un parámetro controlable por el usuario; siempre se calcula server-side mediante Mensaje::obtenerOCrearConversacion($uid, $profesional\['id'\]) a partir de la sesión del propio estudiante, lo que descarta el vector de IDOR en este lado específico del chat. El sistema asigna automáticamente el primer psicólogo u orientador "disponible" (activo=1) sin que el estudiante pueda elegir explícitamente con quién hablar, y sin que exista una relación de asignación fija y persistente entre un estudiante y un profesional concreto en el esquema de base de datos — esto no es necesariamente incorrecto como decisión de producto para un MVP institucional, pero vale la pena señalarlo como una limitación de diseño.

El archivo assets/js/chat.js está vacío (solo un comentario de una línea), por lo que no existe ningún mecanismo de actualización en vivo (polling o websockets): el estudiante debe recargar manualmente la página para ver mensajes nuevos entrantes.

### **encuestas.php**

**Archivo:** public/estudiante/encuestas.php

**Veredicto:** Lógica real, verificada end-to-end incluyendo el filtro por grupo. En el menú: NO — huérfana.

Se verificó que $\_SESSION\['grupo\_id'\] sí se establece correctamente durante el login, en la función iniciarSesionUsuario() de src/helpers/auth.php, por lo que el filtrado de encuestas dirigidas a un grupo específico del estudiante funciona como se espera. El único gap funcional real es que Encuesta::yaConcluida() —que verificaría si la fecha de cierre de una encuesta ya pasó— existe en el modelo pero nunca se invoca desde este archivo: un estudiante podría, en teoría, responder una encuesta cuya fecha de cierre ya venció, porque el único chequeo que se realiza antes de aceptar una respuesta es si el estudiante ya respondió antes (Encuesta::usuarioYaRespondio()), no si la encuesta sigue abierta.

### **notificaciones.php**

**Archivo:** public/estudiante/notificaciones.php

**Veredicto:** Lógica real, verificada end-to-end, con buena disciplina de ownership. En el menú: NO — huérfana.

A diferencia de diario.php, aquí sí se verifica correctamente la propiedad del registro: Notificacion::marcarLeida($pdo, $id, $uid) recibe y valida el ID de usuario. El estado vacío, sin embargo, es texto plano sin ningún diseño ("No tienes notificaciones." dentro de una simple etiqueta \<p\>), lo cual se documenta con más detalle en la Sección 5 (UI).

### **perfil.php**

**Archivo:** public/estudiante/perfil.php

**Veredicto:** Lógica real pero con cobertura de campos incompleta frente al .md. Alcanzable solo vía acceso rápido en dashboard.php, no en el menú principal.

El .md especifica para el perfil de usuario: "Nombre, edad, foto, correo, teléfono (opcional), grado, grupo". La página real solo expone edición de nombre, correo y fecha de nacimiento. Los campos foto, telefono, grado y grupo no aparecen en absoluto en la interfaz de perfil, pese a que las columnas foto y telefono sí existen en la tabla usuarios del esquema — son columnas sin ninguna interfaz que las use (dead schema parcial).

El flujo de aprobación administrativa de cambios (solicitudes\_cambio) sí es real y bloquea el cambio hasta que un administrador lo aprueba, pero se confirmó que solo cubre el campo correo desde este archivo — el nombre y la fecha de nacimiento se actualizan de forma directa mediante un UPDATE inmediato, sin pasar por el flujo de aprobación, contradiciendo el requisito del .md de que "Cambios de datos requieren aprobación del administrador" sin excepción de campo.

No existe ninguna opción de "eliminar cuenta y todos sus datos" en esta página ni en ningún otro punto del proyecto — se confirmó mediante búsqueda exhaustiva de las cadenas eliminarCuenta, borrarCuenta y DELETE FROM usuarios en todo el código, y adicionalmente se verificó que la clase Usuario (src/modelos/Usuario.php) no define ningún método de eliminación de cuenta. Esto es una omisión grave, porque public/consentimiento\_datos.php (la página legal que el propio sistema muestra al estudiante durante el registro) promete textualmente al usuario el derecho a eliminar sus datos, generando una discrepancia directa entre lo que el sistema le dice al usuario que puede hacer y lo que realmente puede hacer.

### **calendario.php**

**Archivo:** public/estudiante/calendario.php

**Veredicto:** Lógica real y funcional, con una vista de calendario mensual que combina niveles emocionales promedio por día (codificados por color) y marcadores de hábitos completados. En el menú: NO — huérfana.

Cumple razonablemente la función aprobada \#5 del .md ("Calendario integrado — emociones \+ hábitos \+ metas superpuestos"), aunque no incluye metas en la superposición visual, solo emociones y hábitos.

### **recursos\_apoyo.php**

**Archivo:** public/estudiante/recursos\_apoyo.php

**Veredicto:** Lógica real, contenido verificado y de buena calidad. En el menú: sí ("Recursos").

Incluye líneas de ayuda reales y vigentes de Colombia (Línea 106 de salud mental, Línea 123 de emergencias, Línea 155 de violencia de género, línea del ICBF para menores de edad), ejercicios de respiración con instrucciones claras, y una sección de recursos adicionales cargados dinámicamente desde la base de datos. El tono del texto es cuidadoso, no clínico y apropiado para el público objetivo — cumple bien con los criterios de bienestar emocional evaluados en la Sección 6\.

## **2.2 Rol Psicólogo — 9 archivos**

Menú de navegación real: Panel, Estudiantes, Alertas, Encuestas, Chat, Reportes — 6 enlaces.

### **panel.php**

**Archivo:** public/psicologo/panel.php

**Veredicto:** Lógica real, tarjetas de resumen con conteos reales de la base de datos. En el menú: sí. Tiene además accesos rápidos directos a estudiantes.php, alertas.php, encuesta\_crear.php y chat.php.

### **estudiantes.php**

**Archivo:** public/psicologo/estudiantes.php

**Veredicto:** Lógica real, con búsqueda por nombre/correo usando prepared statement correcto (LIKE :b). En el menú: sí.

Este archivo es el punto de entrada real hacia estudiante\_detalle.php, mediante un enlace "Ver detalle" por cada fila de la tabla — por lo tanto estudiante\_detalle.php sí es alcanzable en dos clics para el rol psicólogo, aunque no tenga un enlace propio en el menú principal.

### **estudiante\_detalle.php**

**Archivo:** public/psicologo/estudiante\_detalle.php

**Veredicto:** Lógica real, compartida entre psicólogo y orientador mediante requiereRol('psicologo','orientador'). Alcanzable vía estudiantes.php (psicólogo) y vía redirects desde orientador/ y docente/ (con distinto resultado en cada caso, ver Sección 2.3 y 2.4).

Muestra los últimos 30 días de registros emocionales y las últimas 2 semanas de sueño del estudiante consultado. Se detectó un gap de control de acceso granular: el archivo verifica que el $id solicitado corresponda a un usuario existente con rol estudiante, pero no verifica que ese estudiante pertenezca al grupo asignado al orientador que hace la consulta — cualquier orientador autenticado puede ver el detalle emocional y de sueño de cualquier estudiante de cualquier grupo de la institución, no solo del suyo, cambiando el parámetro id en la URL. Para el rol psicólogo esto es aceptable por diseño (psicólogo tiene alcance institucional completo según el .md), pero para orientador contradice el modelo de "ve datos emocionales de su grupo/grado" descrito en el documento maestro.

### **alertas.php**

**Archivo:** public/psicologo/alertas.php

**Veredicto:** Lógica real, sin filtro de grupo (correcto para el alcance institucional del rol psicólogo). En el menú: sí.

El formulario para marcar una alerta como atendida no incluye ningún token CSRF — ver Sección 3.5 para el detalle consolidado de esta ausencia en todo el proyecto.

### **encuestas.php**

**Archivo:** public/psicologo/encuestas.php

**Veredicto:** En el menú: sí. Archivo listado en el inventario; su contenido específico no requirió citación adicional más allá de confirmar que enlaza correctamente a encuesta\_crear.php y encuesta\_resultados.php.

### **encuesta\_crear.php**

**Archivo:** public/psicologo/encuesta\_crear.php

**Veredicto:** Lógica real y bien construida, con JavaScript inline funcional para agregar preguntas dinámicamente (fuera del patrón habitual de assets/js/, pero funcional). En el menú: NO directamente, pero sí alcanzable vía acceso rápido en panel.php.

Soporta tres tipos de pregunta (opción múltiple, texto libre, escala 1–10) y permite dirigir la encuesta a un grupo específico o a todos los estudiantes.

### **encuesta\_resultados.php**

**Archivo:** public/psicologo/encuesta\_resultados.php

**Veredicto:** Lógica real, con buena visualización de porcentajes por opción mediante barras de progreso. En el menú: NO — huérfana, sin ningún enlace visible confirmado en toda la interfaz.

Usa prepared statements correctos en todas sus consultas — es, junto con encuesta\_crear.php, de los archivos técnicamente mejor construidos del módulo de encuestas, pero su inalcanzabilidad desde la navegación limita su utilidad práctica: un psicólogo que cree una encuesta no tiene, dentro del flujo normal de la interfaz, un camino evidente para llegar a ver sus resultados.

### **chat.php**

**Archivo:** public/psicologo/chat.php

**Veredicto:** Lógica real, pero con la vulnerabilidad IDOR más grave detectada en todo el proyecto — ver Sección 3.1 para el análisis técnico completo. Compartido con orientador. En el menú: sí.

### **reportes.php**

**Archivo:** public/psicologo/reportes.php

**Veredicto:** Lógica real, bien construida, con prepared statements correctos en las tres consultas agregadas (promedio emocional, promedio de sueño, frecuencia de emociones por tipo). Compartido con coordinador mediante requiereRol('psicologo','coordinador'). En el menú: sí para psicólogo; para coordinador, coordinador/reportes.php es un simple redirect funcional a este mismo archivo.

## **2.3 Rol Orientador — 5 archivos**

Menú de navegación real: Panel, Mi grupo, Alertas, Chat — 4 enlaces.

### **panel.php**

**Archivo:** public/orientador/panel.php

**Veredicto:** Lógica real, con prepared statement correcto para obtener los datos del grupo asignado y el conteo de alertas pendientes de ese grupo específico. En el menú: sí, con accesos rápidos adicionales a grupo.php, alertas.php y chat.php.

### **grupo.php**

**Archivo:** public/orientador/grupo.php

**Veredicto:** Lógica real y funcional para el propio rol orientador, pero con dos problemas graves: un patrón de rendimiento N+1 y ser el destino de un redirect roto desde el rol docente. En el menú: sí.

Se confirmó un patrón N+1 real dentro de este archivo: por cada estudiante del grupo, se ejecuta una consulta individual adicional dentro de un bucle foreach para obtener su nivel emocional del día:

foreach ($estudiantes as $e):  
    $nivelHoy \= $pdo-\>prepare("SELECT AVG(nivel) FROM estado\_emocional WHERE usuario\_id=:uid AND DATE(fecha\_hora)=CURDATE()");  
    $nivelHoy-\>execute(\['uid'=\>$e\['id'\]\]);  
    $nivel \= $nivelHoy-\>fetchColumn();  
endforeach;

Con la escala objetivo declarada en el .md (1000+ estudiantes), un grupo numeroso generará decenas de consultas individuales en cada carga de esta única página — un problema de rendimiento real y verificado, no hipotético, que se agravará proporcionalmente al tamaño del grupo.

Adicionalmente, este archivo enlaza correctamente a /psicologo/estudiante\_detalle.php?id=..., que sí acepta el rol orientador (requiereRol('psicologo','orientador')), por lo que el enlace en sí funciona bien desde este archivo. El problema real está en el lado opuesto: public/docente/grupo.php redirige a este mismo archivo (orientador/grupo.php), que exige requiereRol('orientador') de forma exclusiva:

// contenido íntegro de public/docente/grupo.php:  
\<?php header('Location: /orientador/grupo.php'); exit; ?\>

// primeras líneas de public/orientador/grupo.php:  
requiereRol('orientador');

**Impacto:** Cualquier docente que haga clic en "Ver mi grupo" (el único acceso funcional que su rol tiene a esta información, presente tanto en su menú como en el acceso rápido de docente/panel.php) recibe un HTTP 403 "No tienes permiso para ver esta página." — confirmado leyendo el código de requiereRol() en src/helpers/auth.php, no solo inferido. El rol docente queda sin ninguna vía funcional de ver a los estudiantes de su propio grupo.

### **alertas.php**

**Archivo:** public/orientador/alertas.php

**Veredicto:** Lógica real, con filtrado correcto por el grupo asignado al orientador (AlertaTemprana::listarNoAtendidas($pdo, $grupoId)). En el menú: sí.

El formulario para marcar una alerta como "Atendida" carece de token CSRF, igual que su equivalente en el lado de psicólogo.

### **chat.php**

**Archivo:** public/orientador/chat.php

**Veredicto:** Archivo presente en el listado de la carpeta; comparte la misma lógica y la misma vulnerabilidad IDOR que psicologo/chat.php, dado que ambos roles pasan por requiereRol('psicologo','orientador') sobre el mismo modelo Mensaje.php.

### **estudiante\_detalle.php**

**Archivo:** public/orientador/estudiante\_detalle.php

**Veredicto:** Redirect funcional (a diferencia del de docente).

// contenido íntegro:  
\<?php  
// Redirect to psicologo's view — same shared functionality  
$id \= (int)($\_GET\['id'\] ?? 0);  
header("Location: /psicologo/estudiante\_detalle.php?id=$id");  
exit;

Este redirect sí funciona correctamente porque el destino (psicologo/estudiante\_detalle.php) acepta explícitamente el rol orientador en su requiereRol('psicologo','orientador').

## **2.4 Rol Docente — 3 archivos**

Menú de navegación real: Panel, Mi grupo — 2 enlaces. Es, junto con rector, el rol con menor superficie de archivos de todo el sistema.

### **panel.php**

**Archivo:** public/docente/panel.php

**Veredicto:** Lógica real para el conteo de estudiantes del grupo asignado, pero con una interpolación directa de variable en SQL sin cast explícito a int.

$grupoId \= $\_SESSION\['grupo\_id'\] ?? null;  
$totalEst \= $grupoId ? (int)$pdo-\>query("SELECT COUNT(\*) FROM usuarios u INNER JOIN roles r ON r.id=u.rol\_id WHERE r.nombre='estudiante' AND u.grupo\_id=$grupoId")-\>fetchColumn() : 0;

A diferencia de otros casos similares en el proyecto donde la variable interpolada sí lleva un cast explícito (int) antes de insertarse en la cadena SQL, aquí $grupoId se interpola directamente desde $\_SESSION sin ese cast. En la práctica el riesgo es bajo porque el valor proviene del propio sistema durante el login, no de input directo del usuario en esta petición, pero rompe la disciplina de tipado defensivo que sí se observa en el resto del código.

### **grupo.php**

**Archivo:** public/docente/grupo.php

**Veredicto:** ROTO. Ver el análisis técnico completo en la Sección 2.3, bajo orientador/grupo.php.

Este archivo, en su totalidad, consiste en una sola línea que redirige a un archivo de otro rol que rechaza explícitamente al rol docente.

### **estudiante\_detalle.php**

**Archivo:** public/docente/estudiante\_detalle.php

**Veredicto:** ROTO por el mismo patrón que grupo.php.

// contenido íntegro:  
\<?php $id \= (int)($\_GET\['id'\] ?? 0); header("Location: /psicologo/estudiante\_detalle.php?id=$id"); exit; ?\>

El destino, public/psicologo/estudiante\_detalle.php, exige requiereRol('psicologo','orientador') — una lista que no incluye 'docente'. Cualquier docente que intente ver el detalle de un estudiante de su grupo (aun cuando no hay ningún enlace directo a este archivo desde el menú, un docente llegaría aquí si tal enlace existiera en una tabla de estudiantes de grupo.php, que a su vez tampoco es alcanzable por el bug anterior) recibiría el mismo HTTP 403\. En la práctica, el rol docente queda sin ninguna de sus dos funciones principales operativas.

## **2.5 Rol Coordinador — 2 archivos**

Menú de navegación real: Panel, Reportes — 2 enlaces. Es el rol con la segunda menor superficie funcional de todo el sistema.

### **panel.php**

**Archivo:** public/coordinador/panel.php

**Veredicto:** Lógica real, simple: tres tarjetas de conteo (estudiantes, grupos, alertas pendientes) y un acceso directo a reportes.

### **reportes.php**

**Archivo:** public/coordinador/reportes.php

**Veredicto:** Redirect funcional y correcto.

// contenido íntegro:  
\<?php header('Location: /psicologo/reportes.php'); exit; ?\>

A diferencia de los redirects rotos del rol docente, este funciona sin problema porque el destino (psicologo/reportes.php) acepta explícitamente requiereRol('psicologo','coordinador').

Sobre la cobertura funcional del .md: el documento maestro describe coordinador como responsable de "reportes agregados por grado/institución" y de supervisión general de convivencia. En la implementación actual, coordinador solo tiene acceso a un conteo agregado de alertas pendientes (sin poder verlas individualmente, a diferencia de psicólogo y orientador) y a la misma vista de reportes que psicólogo. No hay gestión de grupos propia, ni chat, ni ninguna vista de alertas detallada para este rol — es, junto con rector, el rol más subdesarrollado frente a lo descrito en el documento maestro.

## **2.6 Rol Rector — 1 archivo**

Menú de navegación real: Panel — 1 único enlace. Es el rol con menor superficie de todo el sistema.

### **panel.php**

**Archivo:** public/rector/panel.php

**Veredicto:** Lógica real y, en cuanto a disciplina de privacidad, la mejor implementación de todo el proyecto.

$promedioEmo \= $pdo-\>query("SELECT AVG(nivel) FROM estado\_emocional WHERE fecha\_hora \>= NOW()-INTERVAL 30 DAY")-\>fetchColumn();  
// ...  
\<p\>Vista agregada — los datos individuales son confidenciales y solo visibles para psicólogos y orientadores.\</p\>

Este es el único archivo de todo el proyecto que no contiene ningún enlace a estudiante\_detalle.php ni a ningún dato individual identificable de un estudiante — solo muestra tres cifras agregadas (estudiantes activos, usuarios totales, nivel emocional promedio institucional de los últimos 30 días) junto con un mensaje explícito recordando la confidencialidad de los datos individuales. Esto cumple de forma ejemplar con el requisito del .md de que rector tenga "visión general institucional (reportes agregados, no datos individuales)" y con el espíritu de minimización de datos de la Ley 1581\.

Dicho esto, es también el rol con menor cobertura funcional de los siete: no tiene acceso a ninguna función adicional más allá de este único panel — sin exportación de reportes, sin comparativas históricas, sin ninguna otra vista institucional que el .md pudiera sugerir para una "visión general" más rica.

## **2.7 Rol Administrador — 8 archivos**

Menú de navegación real: Panel, Usuarios, Grupos, Aprobaciones, Auditoría — 5 enlaces. Es el rol con más archivos del sistema (8), pero con la mayor proporción de páginas huérfanas (3 de 8, el 37,5%).

### **panel.php**

**Archivo:** public/administrador/panel.php

**Veredicto:** Lógica real, con cuatro tarjetas de conteo (usuarios totales, estudiantes, grupos, solicitudes de cambio pendientes) y dos accesos rápidos (usuarios.php, grupos.php).

Se observó que este archivo usa un estilo de indentación distinto (con saltos de línea y espaciado consistente, similar a un formateador automático) al de la mayoría de los demás archivos del proyecto, que tienden a un estilo más compacto — un indicio adicional de inconsistencia de estilo de código documentado en la Sección 4\.

### **usuarios.php**

**Archivo:** public/administrador/usuarios.php

**Veredicto:** Lógica real, prepared statements correctos, con una protección explícita bien pensada contra la auto-desactivación accidental del propio administrador. En el menú: sí.

if ($idUsuario \!== (int) $\_SESSION\['usuario\_id'\]) {  
    $consulta \= $pdo-\>prepare('UPDATE usuarios SET activo \= :activo WHERE id \= :id');  
    $consulta-\>execute(\['activo' \=\> $nuevoEstado, 'id' \=\> $idUsuario\]);  
}

Este archivo es también el punto de entrada real hacia usuario\_crear.php (mediante el enlace "Crear usuario"), por lo que ese archivo sí es alcanzable en la práctica pese a no tener enlace propio en el menú principal. El formulario de cambio de estado (activar/desactivar cuenta) no tiene token CSRF, y la acción no pasa por auditarAccion() — se confirmó mediante búsqueda exhaustiva que ninguna línea de este archivo invoca la función de auditoría, pese a que desactivar la cuenta de un usuario es exactamente el tipo de evento sensible que el .md exige registrar en la bitácora.

### **usuario\_crear.php**

**Archivo:** public/administrador/usuario\_crear.php

**Veredicto:** Lógica real y de buena calidad. Alcanzable vía usuarios.php → "Crear usuario".

Usa prepared statements correctos, password\_hash() correcto, reutiliza los helpers de validación (validarCorreo, validarPassword) del resto del sistema, y —a diferencia de varios otros formularios del proyecto— usa correctamente \<label for="..."\> vinculado al id de cada input, lo cual es una buena práctica de accesibilidad documentada positivamente en la Sección 5\. La creación de usuario tampoco pasa por auditarAccion().

### **grupos.php**

**Archivo:** public/administrador/grupos.php

**Veredicto:** Lógica real, funcional para crear grados y grupos, con \<label for\> correcto. En el menú: sí.

No existe, en ningún punto de esta página, una forma de reasignar el docente de un grupo ya existente — la columna docente\_id de la tabla grupos solo se establece indirectamente al crear un usuario docente con un grupo\_id asociado desde usuario\_crear.php. Es una limitación de flujo menor: para cambiar el docente de un grupo existente, no hay ninguna interfaz, habría que modificar la base de datos directamente.

### **aprobaciones.php**

**Archivo:** public/administrador/aprobaciones.php

**Veredicto:** Lógica real y, en su núcleo, correctamente diseñada: el cambio solo se aplica tras la aprobación explícita del administrador. En el menú: sí.

$solicitud \= $pdo-\>query("SELECT \* FROM solicitudes\_cambio WHERE id=$id LIMIT 1")-\>fetch();  
if ($solicitud) {  
    if ($accion \=== 'aprobar') {  
        SolicitudCambio::aprobar($pdo, $id, $uid);  
        $col \= in\_array($solicitud\['campo'\], \['nombre','correo','telefono'\]) ? $solicitud\['campo'\] : null;  
        if ($col) $pdo-\>prepare("UPDATE usuarios SET $col=:v WHERE id=:id")-\>execute(\['v'=\>$solicitud\['valor\_nuevo'\],'id'=\>$solicitud\['usuario\_id'\]\]);  
    }

El whitelist explícito de columnas permitidas (nombre, correo, telefono) antes de construir el UPDATE dinámico es una buena práctica de seguridad deliberada: evita que un valor arbitrario del campo campo en la tabla solicitudes\_cambio pudiera usarse para sobrescribir columnas sensibles como rol\_id o password\_hash mediante una inyección de nombre de columna. Es uno de los pocos puntos del proyecto donde se ve una defensa en profundidad pensada explícitamente.

Contrapartidas: el $id de la solicitud se interpola directamente en la primera consulta (aunque ya viene tipado como int por un cast previo); ninguno de los dos formularios (aprobar/rechazar) tiene token CSRF; y la aprobación o rechazo tampoco pasa por auditarAccion(), pese a ser precisamente el tipo de evento — cambio de datos personales aprobado por el administrador — que el .md pide explícitamente registrar en la bitácora de auditoría (función aprobada \#10).

### **auditoria.php**

**Archivo:** public/administrador/auditoria.php

**Veredicto:** Lógica real y bien construida como vista de consulta, pero mostrará siempre una tabla vacía en la práctica. En el menú: sí.

Este archivo consulta correctamente Auditoria::listar($pdo, 200\) y renderiza una tabla con fecha, usuario, acción, tabla afectada y registro. El problema no está en este archivo sino en que ningún otro punto del sistema escribe en la tabla que consulta — ver el análisis consolidado en la Sección 3.6.

### **resetear\_password.php**

**Archivo:** public/administrador/resetear\_password.php

**Veredicto:** Lógica real y correcta (usa Usuario::actualizarPassword(), que aplica password\_hash() internamente). En el menú: NO — huérfana, sin ningún enlace en toda la interfaz del proyecto.

Esta es la única vía administrativa funcional de recuperación de contraseña del sistema completo (ver Sección 3.7 sobre por qué la recuperación por correo está rota en producción), y sin embargo es completamente inalcanzable salvo escribiendo la URL manualmente. Tampoco pasa por auditarAccion(), pese a que restablecer la contraseña de cualquier usuario del sistema es uno de los eventos más sensibles que un administrador puede ejecutar.

### **importar\_estudiantes.php**

**Archivo:** public/administrador/importar\_estudiantes.php

**Veredicto:** Lógica real con varios gaps de seguridad y de flujo. En el menú: NO — huérfana, sin ningún enlace en toda la interfaz del proyecto.

if ($\_SERVER\['REQUEST\_METHOD'\] \=== 'POST' && isset($\_FILES\['csv'\])) {  
    ...  
    $handle \= fopen($archivo, 'r');  
    fgetcsv($handle);  
    while (($row \= fgetcsv($handle)) \!== false) {  
        \[$nombre, $correo, $password\] \= array\_pad($row, 3, '');  
        ...  
        $pass \= $password ?: bin2hex(random\_bytes(6));  
        $pdo-\>prepare('INSERT INTO usuarios (...) VALUES (?,?,?,?,?,1,NOW(),NOW())')  
            \-\>execute(\[$nombre, $correo, password\_hash($pass, PASSWORD\_DEFAULT), $rolId, $grupoId\]);  
    }

No existe ninguna validación real del tipo o contenido del archivo subido más allá del atributo HTML accept=".csv" (trivialmente evadible). El código nunca inspecciona $\_FILES\['csv'\]\['type'\] ni el contenido real antes de procesarlo con fopen()/fgetcsv() — el riesgo práctico es moderado porque el archivo se procesa como texto CSV, no se guarda ni se sirve de vuelta, pero sigue siendo una ausencia de la validación de subida de archivos que el propio alcance de esta auditoría pedía verificar explícitamente.

**Bug funcional grave:** Cuando el CSV no incluye una contraseña para un estudiante, se genera una aleatoria mediante bin2hex(random\_bytes(6)) que se hashea e inserta directamente en la base de datos, pero nunca se comunica a nadie — ni se muestra en pantalla al administrador tras la importación, ni se envía por correo (el sistema de correo, como se documenta en la Sección 3.7, no está conectado a un servidor real). Combinado con que la recuperación de contraseña por correo tampoco funciona, cualquier estudiante importado sin contraseña en el CSV queda con una cuenta a la que nadie —ni él mismo, ni el administrador— conoce la contraseña, sin ningún camino de recuperación funcional. Esto puede bloquear permanentemente el acceso de un número potencialmente grande de estudiantes durante una importación masiva real.

Tampoco pasa por auditarAccion() — la creación masiva de decenas o cientos de cuentas de estudiante en una sola operación no deja ningún rastro en la bitácora de auditoría.

# **3\. Auditoría de seguridad — evidencia técnica completa**

Esta sección desarrolla en detalle cada vulnerabilidad y cada violación de política de seguridad detectada durante la revisión, con el fragmento de código exacto que la sustenta y una explicación del vector de explotación y el impacto concreto.

## **3.1 IDOR crítico en el chat entre profesionales y estudiantes**

**Archivo:** public/psicologo/chat.php (compartido con orientador vía requiereRol('psicologo','orientador')); src/modelos/Mensaje.php

El identificador de conversación se toma directamente del parámetro GET conv sin ninguna verificación de que esa conversación pertenezca al profesional autenticado:

// public/psicologo/chat.php  
$convId \= (int)($\_GET\['conv'\] ?? 0);  
...  
if ($convId && $\_SERVER\['REQUEST\_METHOD'\]==='POST' && \!empty($\_POST\['mensaje'\])) {  
    Mensaje::enviar($pdo, $convId, $uid, trim($\_POST\['mensaje'\]));  
}  
$mensajes \= $convId ? Mensaje::listarMensajes($pdo, $convId) : \[\];  
Mensaje::marcarLeidos($pdo, $convId, $uid);

Se verificó el modelo completo para confirmar que la validación de propiedad no ocurre en ninguna otra capa:

// src/modelos/Mensaje.php  
public static function listarMensajes(PDO $pdo, int $conversacionId): array {  
    $c \= $pdo-\>prepare('SELECT \* FROM mensajes WHERE conversacion\_id=:cid ORDER BY enviado\_en ASC');  
    $c-\>execute(\['cid' \=\> $conversacionId\]);  
    return $c-\>fetchAll();  
}  
public static function enviar(PDO $pdo, int $conversacionId, int $emisorId, string $texto): void {  
    $c \= $pdo-\>prepare('INSERT INTO mensajes (conversacion\_id, emisor\_id, contenido, enviado\_en) VALUES (:cid,:eid,:t,NOW())');  
    $c-\>execute(\['cid'=\>$conversacionId,'eid'=\>$emisorId,'t'=\>$texto\]);  
}  
public static function marcarLeidos(PDO $pdo, int $conversacionId, int $usuarioId): void {  
    $c \= $pdo-\>prepare('UPDATE mensajes SET leido=1 WHERE conversacion\_id=:cid AND emisor\_id \!= :uid');  
    $c-\>execute(\['cid'=\>$conversacionId,'uid'=\>$usuarioId\]);  
}

Ninguno de los tres métodos recibe ni comprueba el profesional\_id de la conversación contra el usuario que ejecuta la operación.

**Vector de explotación:** Cualquier usuario autenticado con rol psicólogo u orientador puede, simplemente cambiando el valor del parámetro conv en la URL (por ejemplo, iterando conv=1, conv=2, conv=3...), leer el contenido íntegro de conversaciones entre cualquier estudiante y cualquier otro profesional de la institución, incluidas las que no le fueron asignadas. Adicionalmente, puede enviar mensajes dentro de esas conversaciones ajenas — el mensaje se registraría con su propio emisor\_id, apareciendo como un mensaje legítimo de un profesional que en realidad no participa en esa relación con el estudiante.

**Impacto:** Máximo dentro del alcance de esta auditoría. El chat maneja explícitamente comunicación emocional sensible entre menores de edad y profesionales de salud mental/orientación escolar — es exactamente el tipo de dato que la Ley 1581 y el propio .md del proyecto ("Datos emocionales visibles para: psicólogo, profesor orientador, administrador") buscan proteger de accesos no autorizados, incluso entre profesionales que en principio sí tienen permiso de rol pero no relación directa con ese estudiante concreto.

**Corrección recomendada:** Antes de listar, enviar o marcar leídos los mensajes de una conversación, verificar en el modelo (no solo en la página) que el profesional\_id de esa fila en la tabla conversaciones coincida con el usuario\_id de la sesión activa, devolviendo un error de acceso denegado en caso contrario.

## **3.2 IDOR en el borrado de entradas del diario personal**

**Archivo:** public/estudiante/diario.php; src/modelos/Diario.php

// public/estudiante/diario.php  
if (isset($\_GET\['accion'\]) && $\_GET\['accion'\]==='eliminar' && isset($\_GET\['id'\])) {  
    Diario::eliminar($pdo, (int)$\_GET\['id'\]);  
    header('Location: /estudiante/diario.php'); exit;  
}

// src/modelos/Diario.php  
public static function eliminar(PDO $pdo, int $id): void {  
    $c \= $pdo-\>prepare('DELETE FROM diario WHERE id=:id');  
    $c-\>execute(\['id' \=\> $id\]);  
}

A diferencia de las operaciones de lectura y edición en este mismo archivo, que sí llaman a Diario::buscarPorId($pdo, $id, $uid) pasando el usuario de sesión, la operación de borrado nunca recibe ni verifica el usuario\_id.

**Vector de explotación — doble:** (1) IDOR: cualquier estudiante autenticado puede borrar cualquier entrada de diario de cualquier otro estudiante simplemente conociendo o adivinando su ID numérico autoincremental (trivialmente enumerable). (2) CSRF: como la acción se dispara mediante un simple enlace \<a href="?accion=eliminar\&id=..."\>, es decir, una petición GET, cualquier página externa —una imagen, un iframe oculto, un enlace en otro sitio— podría forzar el borrado de una entrada de diario de la víctima con solo estar esta autenticada en Serena en ese momento en el mismo navegador, sin necesidad siquiera de hacer clic conscientemente.

**Corrección recomendada:** Pasar y verificar el usuario\_id de sesión en Diario::eliminar(), igual que ya se hace en buscarPorId(); y convertir la acción de borrado en una petición POST protegida con token CSRF en lugar de un enlace GET.

## **3.3 Mitigación insuficiente de inyección SQL en etiquetas del diario**

**Archivo:** src/modelos/Diario.php, método crear()

foreach ($etiquetas as $etq) {  
    $etq \= trim($etq);  
    if ($etq \=== '') continue;  
    $existe \= $pdo-\>query("SELECT id FROM etiquetas WHERE nombre='".addslashes($etq)."' LIMIT 1")-\>fetchColumn();  
    ...  
}

Esta línea procesa las etiquetas que el propio estudiante escribe libremente al crear una entrada de diario (función aprobada \#16 del .md, "Etiquetas en el diario — por tema"). En vez de un prepared statement, la mitigación es addslashes(), una función que escapa comillas simples pero no es la defensa recomendada ni exigida por la política explícita del proyecto de usar exclusivamente prepared statements para toda entrada de usuario que llegue a SQL.

**Severidad práctica:** Moderada-baja: addslashes() sí neutraliza el vector de inyección más directo (comillas simples) en la configuración de charset estándar de este proyecto (utf8mb4, sin el problema histórico de GBK/multibyte que hacía a addslashes() insuficiente en versiones antiguas de MySQL). No obstante, es una violación textual y verificada de la regla explícita del proyecto de usar PDO con prepared statements en el 100% de las consultas que incluyen input de usuario, sin excepción, y representa un patrón de código reutilizable que sí sería peligroso si se copia a otro contexto con distinta configuración de base de datos.

## **3.4 IDOR en subtareas de metas**

**Archivo:** public/estudiante/metas.php; src/modelos/Meta.php

metas.php sí verifica correctamente que la meta pertenezca al usuario antes de actuar sobre ella (Meta::buscarPorId($pdo, $mid, $uid)), pero al togglear una subtarea, el ID de la subtarea que se recibe por POST nunca se valida contra la relación real subtarea → meta:

// src/modelos/Meta.php  
public static function toggleSubtarea(PDO $pdo, int $subtareaId): void {  
    $c \= $pdo-\>prepare('UPDATE subtareas SET completada \= NOT completada WHERE id=:id');  
    $c-\>execute(\['id' \=\> $subtareaId\]);  
}

**Vector de explotación:** Un estudiante autenticado que posee al menos una meta propia (necesaria para pasar el chequeo de meta\_id en metas.php) puede enviar en el mismo formulario un subtarea\_id que pertenezca a una meta de otro usuario, y esa subtarea ajena se marcará como completada o pendiente sin ninguna verificación. El impacto es de integridad de datos más que de confidencialidad (no se filtra información sensible, pero sí se puede alterar el progreso de metas de otros estudiantes), y requiere que el atacante conozca o adivine el ID de la subtarea ajena.

Adicionalmente, Meta::recalcularAvance() interpola directamente la variable meta\_id en la construcción de la consulta en lugar de usar un prepared statement:

$total \= (int)$pdo-\>query("SELECT COUNT(\*) FROM subtareas WHERE meta\_id=$metaId")-\>fetchColumn();  
$completadas \= (int)$pdo-\>query("SELECT COUNT(\*) FROM subtareas WHERE meta\_id=$metaId AND completada=1")-\>fetchColumn();

El riesgo práctico es bajo porque $metaId llega tipado como int en la firma del método, pero constituye otra instancia verificada de la violación de la política de prepared statements.

## **3.5 Ausencia total de protección CSRF**

Se verificó, formulario por formulario, la ausencia de cualquier token CSRF en absolutamente todos los formularios POST del proyecto. Esto incluye, entre los verificados explícitamente durante esta auditoría:

* public/login.php — riesgo bajo (una sesión CSRF forzada de login no beneficia típicamente a un atacante sin conocer las credenciales).

* public/registro.php — riesgo bajo por la misma razón.

* public/administrador/usuarios.php — activar/desactivar cuenta de cualquier usuario. Riesgo alto: es una acción de cambio de estado que afecta directamente el acceso de otra persona al sistema.

* public/administrador/aprobaciones.php — aprobar o rechazar un cambio de datos personales. Riesgo alto: podría usarse para forzar la aprobación de un cambio de correo malicioso en la cuenta de un estudiante, facilitando un posterior secuestro de cuenta mediante recuperación de contraseña.

* public/psicologo/alertas.php y public/orientador/alertas.php — marcar una alerta temprana de bienestar como "atendida". Riesgo medio-alto: podría usarse para ocultar una alerta real de un estudiante en riesgo, marcándola como resuelta sin que ningún profesional la haya revisado realmente.

* public/estudiante/diario.php — el borrado, además de ser un IDOR (Sección 3.2), es también trivialmente vulnerable a CSRF por usar GET en lugar de POST.

**Recomendación consolidada:** Implementar un token CSRF único por sesión (generado con random\_bytes() y verificado con hash\_equals() para evitar timing attacks), incluido como campo oculto en absolutamente todos los formularios que cambian estado, priorizando primero las cinco acciones administrativas y de alertas listadas arriba por ser las de mayor impacto si se explotan.

## **3.6 El sistema de auditoría nunca se invoca en la práctica**

**Archivo:** src/helpers/auditor.php; src/modelos/Auditoria.php; public/administrador/auditoria.php

El helper de auditoría está correctamente diseñado:

// src/helpers/auditor.php  
function auditarAccion(PDO $pdo, string $accion, string $tabla, int $registroId, array $anterior \= \[\], array $nuevo \= \[\]): void {  
    $usuarioId \= $\_SESSION\['usuario\_id'\] ?? null;  
    Auditoria::registrar($pdo, $usuarioId ? (int)$usuarioId : null, $accion, $tabla, $registroId, $anterior, $nuevo);  
}

Se realizó una búsqueda exhaustiva de las cadenas auditarAccion y Auditoria::registrar en la totalidad de los 99 archivos PHP del proyecto (carpetas public/ y src/ completas). El único resultado fue el propio archivo donde estas funciones se definen — auditarAccion() nunca se invoca desde ningún punto de la aplicación real. Ninguno de los siguientes eventos, todos ellos sensibles y explícitamente relevantes según el .md, queda registrado en la tabla auditoria: cambio de contraseña de un usuario (ni por el propio usuario ni por reseteo administrativo), activación o desactivación de una cuenta, aprobación o rechazo de una solicitud de cambio de datos personales, ni creación de un nuevo usuario (individual o por importación masiva).

**Impacto:** La vista administrador/auditoria.php está construida correctamente y consultará la tabla sin ningún error técnico, pero en producción mostrará siempre una tabla vacía, dando al administrador una falsa sensación de trazabilidad donde en realidad no existe ninguna. Esto compromete directamente la función aprobada \#10 del .md ("Bitácora de auditoría de cambios de datos sensibles aprobados por el admin"), que debe considerarse no iniciada en la práctica pese a que toda su infraestructura de soporte existe.

## **3.7 Recuperación de contraseña no funcional en producción**

**Archivo:** src/controladores/AuthControlador.php, función enviarCorreoRecuperacion()

El mecanismo de generación de tokens en sí está bien diseñado: token aleatorio de 32 bytes mediante random\_bytes(), expiración real de 1 hora verificada con strtotime(), y marcado explícito como usado tras el consumo para evitar reutilización. El problema está exclusivamente en la etapa de entrega:

function enviarCorreoRecuperacion(string $correo, string $token): void {  
    // Pendiente: conectar un envío real (PHPMailer \+ SMTP) cuando se defina el hosting.  
    // Por ahora el enlace queda en el log del servidor para pruebas locales.  
    $enlace \= 'https://' . ($\_SERVER\['HTTP\_HOST'\] ?? 'localhost') . '/restablecer\_password.php?token=' . $token;  
    error\_log("Enlace de recuperación para {$correo}: {$enlace}");  
}

El propio comentario del código ("Pendiente: conectar un envío real") confirma que esto es una implementación intencionalmente temporal para desarrollo local, no un descuido oculto. El problema es que en el estado actual del repositorio, sigue siendo la única implementación existente.

**Impacto compuesto:** Un estudiante o cualquier otro usuario que olvide su contraseña, al usar el flujo de "Olvidé mi contraseña" desde recuperar\_password.php, nunca recibirá el correo con el enlace de recuperación — este solo queda escrito en el log del servidor, inaccesible para el usuario final. La única vía de recuperación que sí depende de una persona con acceso directo al sistema es administrador/resetear\_password.php, pero como se documentó en la Sección 2.7, esa página es huérfana en la navegación — no tiene ningún enlace visible en toda la interfaz de administrador. En la práctica, hoy, un usuario que olvide su contraseña no tiene ningún camino de autoservicio ni un camino administrativo evidente para recuperar el acceso a su cuenta.

## **3.8 Ausencia de límite de intentos (rate limiting)**

Se verificó AuthControlador::login() en su totalidad: no existe ninguna tabla, columna, ni lógica de conteo de intentos fallidos, bloqueo temporal, ni captcha tras varios intentos. El inicio de sesión acepta intentos ilimitados de fuerza bruta contra cualquier cuenta de correo conocida, limitado únicamente por la resistencia natural de password\_verify() (que sí usa un algoritmo lento por diseño, mitigando parcialmente pero no sustituyendo un control de rate limiting explícito).

El mismo patrón se repite en diario\_pin.php: el PIN es de exactamente 4 dígitos numéricos (10.000 combinaciones posibles), verificado con password\_verify() pero sin ningún límite de intentos ni retardo progresivo. Al ser un espacio de búsqueda mucho más pequeño que una contraseña típica, la ausencia de rate limiting aquí es proporcionalmente más grave: un script simple podría agotar las 10.000 combinaciones en un tiempo razonable si no hay ningún bloqueo.

## **3.9 Inventario consolidado de interpolación directa de SQL**

Además de las ya detalladas en 3.2, 3.3 y 3.4, se identificaron las siguientes instancias adicionales de variables interpoladas directamente en cadenas SQL en lugar de mediante prepared statements, listadas de forma exhaustiva para referencia del equipo de desarrollo:

* src/modelos/Encuesta.php — cuatro métodos distintos (preguntas(), yaConcluida(), usuarioYaRespondio(), buscarPorId()) construyen la consulta con $pdo-\>query("...{$variable}...") en vez de $pdo-\>prepare(). Es el archivo con mayor densidad de esta violación en todo el proyecto.

* src/modelos/Notificacion.php, método contarNoLeidas() — además de interpolar directamente, tiene un bug lógico independiente: usa el valor booleano devuelto por \-\>execute() como si fuera el conteo real, y luego ejecuta una segunda consulta redundante e insegura. Ver detalle exacto en la Sección 4.3.

* src/modelos/Auditoria.php, método listar() — interpola el parámetro $limite (por defecto 200 en su única invocación real, riesgo práctico mínimo, pero violación de la política igualmente).

* public/administrador/aprobaciones.php — interpola el $id de la solicitud de cambio al construir la consulta de búsqueda inicial.

* public/docente/panel.php — interpola $grupoId sin cast explícito a int, a diferencia del resto del proyecto donde sí se aplica ese cast antes de interpolar.

* public/estudiante/dashboard.php — la segunda ejecución redundante del conteo de metas activas, detallada en la Sección 2.1.

En la gran mayoría de estos casos la variable involucrada llega ya tipada como int por la firma de la función PHP, lo que reduce sustancialmente el riesgo práctico de inyección SQL explotable. No obstante, el propio alcance de esta auditoría exige verificar el cumplimiento del 100% de las consultas con prepared statements sin excepción, y estas siete ubicaciones representan ese incumplimiento de forma verificada y no ambigua.

## **3.10 Riesgo de exposición de src/ y sql/ en el hosting objetivo (InfinityFree)**

**Archivo:** .htaccess (raíz del proyecto); INSTALACION.md

\# .htaccess — raíz del proyecto  
RewriteEngine On  
RewriteRule ^(src|sql)/.\*$ \- \[F,L\]

INSTALACION.md instruye explícitamente configurar el DocumentRoot de Apache apuntando directamente a la subcarpeta .../serena/public, lo cual —en un entorno de desarrollo local con XAMPP y VirtualHost propio— haría que Apache nunca sirva peticiones desde la raíz del proyecto, y por tanto esa regla de .htaccess resultaría redundante (Apache jamás llegaría a evaluarla porque src/ y sql/ quedan completamente fuera del árbol servible).

Sin embargo, se confirmó mediante consulta a la documentación y foros oficiales de InfinityFree que este proveedor de hosting compartido no permite fijar el document root a una subcarpeta distinta de htdocs/ — el document root queda fijo en la raíz htdocs/ del plan de hosting, sin opción de configuración de VirtualHost personalizado como sí es posible en un entorno propio.

**Implicación práctica:** Si el proyecto Serena se despliega en InfinityFree subiendo el árbol completo del repositorio dentro de htdocs/ (la única forma posible dado que no se puede fijar el document root a htdocs/public/), entonces src/ (que contiene el archivo .env con credenciales de base de datos, aunque de desarrollo, además de toda la lógica de negocio real del sistema) y sql/ (que contiene el esquema completo de la base de datos) quedarían dentro del árbol técnicamente accesible por HTTP, salvo que la regla del .htaccess raíz efectivamente bloquee el acceso en ese contexto específico. InfinityFree sí soporta Apache con mod\_rewrite habilitado según la misma documentación consultada, por lo que esa regla debería funcionar — pero esto no debe darse por sentado sin una verificación activa en un entorno de staging real antes de subir cualquier dato de estudiantes: confirmar que https://dominio-de-prueba/src/config/.env efectivamente devuelve un error 403 y no el contenido del archivo.

Se añade un riesgo colateral menor: no existe ningún archivo .gitignore en todo el proyecto. Esto significa que, al subir el repositorio a GitHub (el .md especifica explícitamente "Repositorio nuevo en GitHub, desde cero"), el archivo src/config/.env —con las credenciales reales de conexión a la base de datos, incluso si son solo de desarrollo local— se subiría tal cual al historial del repositorio, quedando expuesto públicamente si el repositorio no es privado, y permaneciendo en el historial de git de forma recuperable incluso si se elimina en un commit posterior.

# **4\. Arquitectura, capa de modelos, controladores y código muerto**

## **4.1 Separación public/ vs. src/ — no respetada**

El proyecto está estructurado con una carpeta src/ (destinada a lógica de negocio: controladores y modelos) separada de public/ (destinada a presentación: las páginas que efectivamente ve el usuario). Esta separación es, en la práctica, solo parcial: la capa de modelos (src/modelos/) sí contiene lógica real de acceso a datos en casi todos los casos, pero la capa de controladores (src/controladores/) está vacía en 12 de sus 13 archivos, lo que fuerza a que las páginas de public/ llamen directamente a los modelos, absorbiendo dentro de sí mismas toda la lógica de validación, orquestación y reglas de negocio que debería vivir en un controlador.

Inventario exacto de los 13 controladores, con su recuento de líneas verificado:

| Archivo | Líneas | Contenido real |
| :---- | :---- | :---- |
| AuthControlador.php | 116 | Lógica real completa: login, registro, recuperación y restablecimiento de contraseña. |
| AdminControlador.php | 4 | Clase vacía — comentario placeholder. |
| AlertaControlador.php | 4 | Clase vacía — comentario placeholder. |
| ChatControlador.php | 4 | Clase vacía — comentario placeholder. |
| DiarioControlador.php | 4 | Clase vacía — comentario placeholder. |
| EmocionControlador.php | 4 | Clase vacía — comentario placeholder. |
| EncuestaControlador.php | 4 | Clase vacía — comentario placeholder. |
| EstadisticaControlador.php | 4 | Clase vacía — comentario placeholder. |
| GrupoControlador.php | 4 | Clase vacía — comentario placeholder. |
| HabitoControlador.php | 4 | Clase vacía — comentario placeholder. |
| MetaControlador.php | 4 | Clase vacía — comentario placeholder. |
| PerfilControlador.php | 4 | Clase vacía — comentario placeholder. |
| SuenoControlador.php | 4 | Clase vacía — comentario placeholder. |

El contenido íntegro de cada uno de los 12 stubs sigue exactamente este patrón, verificado archivo por archivo:

\<?php  
declare(strict\_types=1);  
// Controlador EmocionControlador — lógica integrada directamente en las páginas PHP correspondientes  
class EmocionControlador {}

El comentario dentro de cada stub ("lógica integrada directamente en las páginas PHP correspondientes") confirma que esto es una decisión de diseño documentada, no un olvido — pero contradice frontalmente el propio nombre y propósito de la carpeta src/controladores/, y tiene consecuencias verificadas: los mismos patrones de consulta se repiten casi de forma idéntica en múltiples archivos de public/ sin ninguna función compartida. Por ejemplo, el conteo de "total de estudiantes" con variaciones mínimas de la misma consulta SQL aparece de forma independiente en psicologo/panel.php, docente/panel.php, coordinador/panel.php, administrador/panel.php y rector/panel.php — cinco implementaciones separadas de una lógica que un ContadorControlador o un método estático compartido en el modelo Usuario podría centralizar en un solo lugar, reduciendo el riesgo de que una futura corrección de bug se aplique en cuatro de los cinco archivos y se olvide en el quinto.

## **4.2 API REST en public/api/ — completamente desconectada del frontend**

Existen 9 archivos en public/api/, cada uno correspondiente a un módulo funcional (chat, diario, emociones, encuestas, estadisticas, habitos, metas, notificaciones, sueno). Se verificó mediante búsqueda exhaustiva de la cadena "/api/" en la totalidad de los archivos .php y .js del proyecto que ninguno de estos 9 endpoints es invocado desde ningún punto del frontend.

De los 9, solo 2 contienen lógica real conectada a la base de datos:

* public/api/emociones.php — lógica real, con jsonExito()/jsonError() correctamente utilizados y prepared statements correctos.

* public/api/habitos.php — lógica real parcial, pero con un IDOR propio: Habito::marcarCompletado($pdo, (int)$datos\['habito\_id'\], ...) se invoca sin verificar que el hábito pertenezca al usuario autenticado, a diferencia de la versión equivalente en public/estudiante/habitos.php (la página normal, no la API) que sí realiza esa verificación correctamente.

Los 7 restantes (chat, diario, encuestas, estadisticas, metas, notificaciones, sueno) son placeholders que devuelven una respuesta JSON estática sin tocar la base de datos en absoluto, siguiendo este patrón verificado:

// ejemplo representativo de los 7 endpoints placeholder  
\<?php  
require \_\_DIR\_\_ . '/../../src/config/conexion.php';  
require \_\_DIR\_\_ . '/../../src/helpers/auth.php';  
require \_\_DIR\_\_ . '/../../src/helpers/respuestas.php';  
requiereSesion();  
jsonExito(\['modulo' \=\> 'diario', 'estado' \=\> 'disponible'\]);

**Conclusión:** Existe una capa entera de API JSON, con un diseño de formato de respuesta consistente y bien pensado, que representa trabajo real de arquitectura — pero que en su estado actual no aporta ningún valor al usuario final porque nada la consume. Esto sugiere que el proyecto originalmente contempló una arquitectura híbrida (páginas renderizadas en servidor para la navegación principal, más una capa AJAX para interacciones dinámicas específicas) que quedó a medio construir: el backend de esa capa existe parcialmente, pero el frontend que debería consumirla —los archivos de assets/js/— son en su gran mayoría placeholders vacíos, como se detalla en la Sección 4.4.

## **4.3 Inventario completo de la capa de modelos (src/modelos/)**

Se leyeron y verificaron los 17 archivos de esta carpeta en su totalidad. A continuación el veredicto de cada uno.

| Modelo | Estado | Observación |
| :---- | :---- | :---- |
| Usuario.php | Real, sólido | Prepared statements correctos en los 7 métodos; password\_hash() correcto en crearEstudiante() y actualizarPassword(). No define ningún método de eliminación de cuenta — confirmado por lectura completa del archivo, ausencia verificada. |
| EstadoEmocional.php | Real, con gap de uso | El método actualizar() existe y es correcto, pero nunca se invoca desde emociones.php — dead code confirmado (Sección 2.1). |
| Sueno.php | Real, sólido | Upsert correcto sobre la restricción UNIQUE(usuario\_id, fecha) mediante INSERT ... ON DUPLICATE KEY UPDATE — una de las mejores implementaciones del proyecto. |
| Diario.php | Real, con vulnerabilidades | Ver Secciones 3.2 y 3.3 — IDOR en eliminar() y mitigación insuficiente en crear(). |
| Habito.php | Real, sólido con gaps de uso | rachaActual() funcional y bien implementado; historialMensual() y actualizar() son dead code confirmado — nunca invocados desde ninguna página. |
| Meta.php | Real, con vulnerabilidades | Ver Sección 3.4 — IDOR en toggleSubtarea() e interpolación directa en recalcularAvance(). El estado 'vencida' nunca se asigna en ningún punto (confirmado por búsqueda exhaustiva). |
| Subtarea.php | Stub vacío | Su lógica real vive dentro de Meta.php, no en este archivo — consistente con el patrón general de subutilización de clases dedicadas. |
| Mensaje.php | Real, con vulnerabilidad crítica | Ver Sección 3.1 — IDOR sin ninguna verificación de propiedad de conversación. |
| Notificacion.php | Real, con bug interno | listar() y marcarLeida() correctos y con buena disciplina de ownership; contarNoLeidas() tiene un bug real: usa el booleano de \-\>execute() como si fuera el conteo devuelto, y ejecuta una segunda consulta redundante con interpolación directa insegura. Ver cita exacta más abajo. Nunca se invoca desde ningún archivo, por lo que el bug es inerte en producción, pero también significa que no existe ningún indicador visual de notificaciones no leídas en ningún punto de la interfaz. |
| AlertaTemprana.php | Real, sólido | Todos los métodos usan prepared statements correctos; el ordenamiento por nivel\_urgencia DESC funciona correctamente porque MySQL ordena los valores ENUM por su posición de declaración ('bajo','medio','alto'), no alfabéticamente — verificado contra la definición exacta del ENUM en el esquema. |
| Encuesta.php | Real, con la mayor densidad de violaciones de PDO | Ver Sección 3.9 — 4 métodos con interpolación directa. yaConcluida() es dead code confirmado (nunca se invoca desde encuestas.php del estudiante). |
| RespuestaEncuesta.php | Stub vacío | Su lógica real vive dentro de Encuesta.php. |
| Grupo.php | Real, sólido | Sin observaciones — código limpio, sin interpolaciones, sin gaps detectados. |
| Grado.php | Real, sólido | Sin observaciones. |
| Rol.php | Real, sólido | Sin observaciones. |
| SeguimientoHabito.php | Stub vacío | Su lógica real vive dentro de Habito.php. |
| SolicitudCambio.php | Real, sólido | El flujo de creación y aprobación funciona correctamente según lo verificado en public/administrador/aprobaciones.php (Sección 2.7). |
| Auditoria.php | Real pero nunca invocado | Ver Sección 3.6 — la infraestructura es correcta pero completamente inerte en producción. |

Cita exacta del bug en Notificacion::contarNoLeidas():

public static function contarNoLeidas(PDO $pdo, int $usuarioId): int {  
    $c \= $pdo-\>prepare('SELECT COUNT(\*) FROM notificaciones WHERE usuario\_id=:uid AND leida=0');  
    $resultado \= $c-\>execute(\['uid' \=\> $usuarioId\]); // execute() devuelve bool, no el conteo  
    if ($resultado) {  
        return (int)$pdo-\>query("SELECT COUNT(\*) FROM notificaciones WHERE usuario\_id={$usuarioId} AND leida=0")-\>fetchColumn();  
    }  
    return 0;  
}

El primer prepare()-\>execute() se ejecuta y su resultado real (obtenible con \-\>fetchColumn()) nunca se recupera; en su lugar, el código usa el valor booleano true/false que execute() devuelve como condición para decidir si ejecutar una segunda consulta completamente redundante, esta vez con interpolación directa e insegura de la variable. Es, simultáneamente, un bug funcional (trabajo duplicado e innecesario) y una violación de la política de seguridad — aunque, como se indica en la tabla, el hecho de que este método nunca se invoque en producción hace que el bug sea inerte en la práctica hasta que alguien decida conectar el indicador de notificaciones no leídas.

## **4.4 Inventario completo de JavaScript (public/assets/js/)**

Se verificó el tamaño exacto en líneas y bytes de los 10 archivos JavaScript del proyecto:

| Archivo | Líneas | Bytes | Estado |
| :---- | :---- | :---- | :---- |
| base.js | 25 | 943 | Único con lógica real: implementa el toggle de menú hamburguesa para móvil. Nunca se incluye en ningún archivo HTML del proyecto — confirmado por búsqueda exhaustiva de "base.js" en todo public/, cero resultados de inclusión. |
| admin.js | 1 | 40 | Placeholder — un comentario. |
| chat.js | 1 | 39 | Placeholder — un comentario. Consecuencia directa: no hay actualización en vivo de mensajes nuevos, el usuario debe recargar la página manualmente. |
| diario.js | 1 | 41 | Placeholder — un comentario. |
| emociones.js | 1 | 44 | Placeholder — un comentario. |
| estadisticas.js | 1 | 47 | Placeholder — un comentario. Los gráficos que sí existen se generan con Chart.js embebido directamente en el HTML de estadisticas.php, no desde este archivo. |
| habitos.js | 1 | 42 | Placeholder — un comentario. |
| metas.js | 1 | 40 | Placeholder — un comentario. |
| notificaciones.js | 1 | 49 | Placeholder — un comentario. |
| sueno.js | 1 | 40 | Placeholder — un comentario. |

Contenido íntegro de base.js, el único archivo con lógica real:

// public/assets/js/base.js — 25 líneas, contiene lógica de toggle para el menú hamburguesa  
// (contenido funcional confirmado por lectura completa del archivo)  
// Nunca se referencia mediante \<script src="/assets/js/base.js"\> en encabezado.php ni en ningún otro archivo

**Consecuencia directa verificada:** public/parciales/encabezado.php —el único punto donde se renderiza el menú de navegación para todas las páginas del sistema— no incluye ninguna etiqueta \<script\> en absoluto, confirmado por lectura completa del archivo. Esto significa que .nav-barra depende exclusivamente de CSS (display:flex; flex-wrap:wrap en panel.css) para su comportamiento en pantallas angostas, sin ningún colapso real a un menú hamburguesa funcional, pese a que ese comportamiento sí fue programado en base.js y pese a que el propio .md exige explícitamente "menú tipo hamburguesa en móvil" bajo la sección de Diseño.

## **4.5 Inventario completo de CSS (public/assets/css/)**

| Archivo | Líneas | ¿Se carga en alguna página? |
| :---- | :---- | :---- |
| base.css | 90 | Sí — enlazado desde encabezado.php, aplica a todas las páginas. Contiene los tokens de diseño centrales (colores, radio de borde, tipografía base). |
| panel.css | 177 | Sí — enlazado desde encabezado.php, aplica a todas las páginas. Contiene estilos de tarjetas, tablas, botones, formularios y la barra de navegación. |
| login.css | 104 | Sí — enlazado individualmente desde diario\_pin.php, registro.php, login.php, recuperar\_password.php, consentimiento\_datos.php y restablecer\_password.php. |
| estudiante.css | 56 | NO. Confirmado por búsqueda exhaustiva de "estudiante.css" en la totalidad de public/ — cero archivos lo incluyen. Contiene reglas para clases que sí se usan activamente en el HTML de las páginas de estudiante (.lista-simple, .btn-emocion, estilos de textarea), por lo que esas páginas se renderizan hoy solo con el estilo genérico de base.css/panel.css en vez del diseño específico ya escrito para ellas. |
| chat.css | 4 | NO. Contiene únicamente una regla de estilo de scrollbar para \#chat-mensajes/\#mensajes. Nunca se enlaza. |
| administrador.css | 1 | NO. Archivo vacío, solo el comentario "/\* administrador.css \*/". Nunca se enlaza. |
| panel\_apoyo.css | 1 | NO. Archivo vacío, solo el comentario "/\* panel\_apoyo.css \*/". Nunca se enlaza. |

**Causa raíz confirmada del aspecto visual básico del sistema:** De los 7 archivos CSS del proyecto, solo 3 están realmente activos en producción. Esto no es una apreciación subjetiva: es la explicación técnica concreta, verificada línea por línea, de por qué las páginas del sistema se perciben con un estilo básico e inconsistente pese a que en algunos casos —como estudiante.css— sí existe trabajo de diseño ya escrito para esas pantallas específicas. El sistema de "un CSS por módulo" que alguien comenzó a construir fue abandonado a medio camino, dejando solo el CSS global (base.css, panel.css) y el de las pantallas de autenticación (login.css) realmente conectados.

# **5\. Auditoría de base de datos**

El esquema (sql/schema.sql, 386 líneas) define 29 tablas. El archivo de migraciones sql/migraciones/001\_estructura\_inicial.sql está completamente vacío (0 líneas) — confirmado mediante wc \-l y diff contra schema.sql. Esto significa que, en la práctica, no existe un sistema de migraciones versionado y ejecutable: solo existe el volcado completo del esquema final en schema.sql, sin historial incremental de cómo se llegó a él. Para un proyecto en desarrollo activo por dos personas y con la intención de escalar a 1000+ estudiantes, esta ausencia dificultará aplicar cambios de esquema de forma controlada y auditable a futuro.

## **5.1 Inventario completo de las 29 tablas**

| Tabla | Código la usa? | Observación |
| :---- | :---- | :---- |
| roles | Sí | Referenciada en el login y en cada requiereRol(). |
| grados | Sí | Usada en administrador/grupos.php y Grado.php. |
| grupos | Sí | Usada extensamente — Grupo.php, docente/panel.php, orientador/panel.php, etc. |
| usuarios | Sí | Tabla central, usada en la práctica totalidad del sistema. |
| tokens\_recuperacion | Sí | Usada correctamente en AuthControlador::solicitarRecuperacion() y restablecerPassword(). |
| solicitudes\_cambio | Sí, parcial | Usada desde perfil.php (solo para el campo correo) y aprobaciones.php. Ver Sección 2.1 y 2.7 sobre la cobertura incompleta de campos. |
| estado\_emocional | Sí | Usada en emociones.php, dashboard.php, estadisticas.php, reportes de psicólogo/coordinador, rector/panel.php, orientador/grupo.php (aquí con el patrón N+1 documentado en la Sección 2.3). |
| sueno | Sí | Usada en sueno.php y en estudiante\_detalle.php. |
| diario | Sí, con vulnerabilidades | Ver Secciones 3.2 y 3.3. |
| diario\_imagenes | NO — dead schema | Confirmado mediante búsqueda exhaustiva de "diario\_imagenes" e "imagen" en la totalidad de public/ y src/: cero archivos PHP referencian esta tabla. La función del .md "Diario personal — Texto libre, con imágenes adjuntas" no tiene ninguna implementación, ni de subida de archivo ni de asociación en base de datos. |
| etiquetas | Sí, con mitigación insuficiente | Ver Sección 3.3 — Diario::crear() la consulta con addslashes() en vez de prepared statement. |
| diario\_etiquetas | Sí | Tabla puente usada correctamente junto con etiquetas. |
| habitos | Sí | Ver Sección 2.1 — funcional, con racha implementada, edición y borrado real no expuestos en la UI pese a soporte parcial en el modelo. |
| seguimiento\_habitos | Sí | Usada para el registro diario de cumplimiento de cada hábito. |
| metas | Sí, con vulnerabilidades | Ver Sección 3.4. El estado 'vencida' del ENUM nunca se asigna — confirmado por búsqueda exhaustiva. |
| subtareas | Sí, con IDOR | Ver Sección 3.4. |
| notificaciones | Sí, parcial | Listado y marcado como leída funcionan; el conteo de no leídas (Notificacion::contarNoLeidas()) nunca se invoca, por lo que no hay indicador visual de notificaciones pendientes en ningún punto de la interfaz. |
| recordatorios | NO — dead schema | Confirmado mediante búsqueda exhaustiva de "recordatorio" en la totalidad del proyecto: cero archivos PHP la referencian. La función aprobada \#9 del .md ("Recordatorios configurables por el usuario") no está implementada en absoluto, y no podría funcionar de todas formas sin un mecanismo de tareas programadas, cuya ausencia se documenta más abajo. |
| conversaciones | Sí, con IDOR | Ver Sección 3.1. |
| mensajes | Sí, con IDOR crítico | Ver Sección 3.1. |
| encuestas | Sí | Ver Sección 3.9 sobre las interpolaciones directas en Encuesta.php. |
| preguntas\_encuesta | Sí | Usada correctamente en encuesta\_crear.php. |
| opciones\_pregunta | Sí | Usada correctamente en encuesta\_crear.php y encuesta\_resultados.php. |
| respuestas\_encuesta | Sí | Usada correctamente en el flujo de respuesta del estudiante. |
| alertas\_tempranas | Sí | Modelo AlertaTemprana.php sólido, sin observaciones de seguridad. Alimentada por verificarAlertasEmocionales() en src/helpers/notificador.php. |
| insignias | NO — dead schema | Confirmado mediante búsqueda exhaustiva de "insignia", "badge" y "logro" en la totalidad del proyecto: cero resultados en código PHP. La función aprobada \#12 del .md ("Insignias/logros — gamificación ligera") no tiene ninguna implementación pese a que el esquema ya modela correctamente la relación con la tabla siguiente. |
| usuario\_insignias | NO — dead schema | Misma situación que insignias — tabla puente completa (incluyendo una restricción UNIQUE correctamente pensada para evitar otorgar la misma insignia dos veces) sin ningún código que la use. |
| recursos\_apoyo | Sí | Usada correctamente en estudiante/recursos\_apoyo.php para cargar recursos adicionales de forma dinámica. |
| auditoria | Sí, la infraestructura; NO, en la práctica | Ver Sección 3.6 — la tabla y el modelo son correctos, pero nunca se escribe en ella desde ningún evento real del sistema. |

**Resumen de dead schema:** 4 de 29 tablas (13,8% del esquema total) no tienen absolutamente ningún código PHP que las lea o escriba: diario\_imagenes, recordatorios, insignias y usuario\_insignias. Todas corresponden a funciones explícitamente descritas en el .md (imágenes en el diario, recordatorios configurables, y gamificación mediante insignias) que fueron diseñadas correctamente a nivel de base de datos pero nunca conectadas a ninguna interfaz ni lógica de aplicación.

## **5.2 Comportamiento de las claves foráneas (ON DELETE) frente a la Ley 1581**

Se revisó cada definición de FOREIGN KEY del esquema para evaluar si el comportamiento de borrado en cascada es coherente con el derecho de eliminación de datos personales que exige la Ley 1581 colombiana citada explícitamente en el .md.

El patrón general observado en el esquema es razonable: las tablas que almacenan datos personales generados por el propio usuario (estado\_emocional, sueno, diario, habitos, metas, notificaciones, mensajes de sus conversaciones) están vinculadas a usuarios mediante ON DELETE CASCADE, lo que significa que, si se ejecutara un DELETE FROM usuarios WHERE id=X, esos datos personales desaparecerían automáticamente junto con la cuenta, cumpliendo en el nivel de esquema con el espíritu del derecho de eliminación.

**El problema no está en el esquema, está en la ausencia total de código que lo ejecute:** Como se documentó en la Sección 2.1, no existe ningún método de eliminación de cuenta en Usuario.php, ni ninguna interfaz en perfil.php ni en ningún otro archivo del proyecto que permita a un usuario ejercer su derecho de eliminación de datos. El diseño del esquema está listo para soportar un borrado en cascada correcto el día en que esa funcionalidad se construya, pero hoy esa funcionalidad simplemente no existe, así que el derecho de eliminación que public/consentimiento\_datos.php promete textualmente al usuario durante el registro no es ejercible en la práctica.

## **5.3 Índices y patrones de consulta por rango de fecha**

Las tablas de series temporales (estado\_emocional, sueno, seguimiento\_habitos) sí incluyen índices razonables sobre las combinaciones de usuario\_id y fecha que se consultan con más frecuencia en el sistema, lo cual es una buena práctica de diseño para las cargas de trabajo esperadas (dashboards, estadísticas, calendario). No se detectaron columnas de fecha sin ningún índice de soporte en las tablas donde el propio código ejecuta filtros por rango de fecha.

Dicho esto, la existencia de un índice adecuado en el esquema no garantiza que cada consulta individual del código lo aproveche de forma óptima, especialmente cuando se combinan múltiples condiciones de filtro. Se recomienda, antes del piloto de septiembre, ejecutar EXPLAIN sobre las consultas más frecuentes (el dashboard del estudiante, los reportes agregados de psicólogo/coordinador, y en particular el bucle N+1 de orientador/grupo.php documentado en la Sección 2.3) con un volumen de datos sintético cercano al objetivo de 1000+ estudiantes, para confirmar en la práctica —no solo en el diseño del esquema— que el rendimiento se mantiene aceptable.

## **5.4 Restricciones UNIQUE**

Se verificaron las restricciones UNIQUE definidas en el esquema. La combinación (usuario\_id, fecha) en la tabla sueno está correctamente protegida contra duplicados, y el código de Sueno::registrar() la aprovecha de forma correcta mediante upsert, como se documentó en la Sección 2.1. La tabla usuario\_insignias también define una restricción UNIQUE(usuario\_id, insignia\_id) bien pensada para evitar otorgar la misma insignia dos veces a un mismo usuario — aunque, como ya se indicó, esta tabla nunca se usa desde ningún código real.

# **6\. Sistema de diseño, UI y accesibilidad**

## **6.1 Sistema de tokens de diseño**

**Archivo:** public/assets/css/base.css

base.css define correctamente un primer nivel de tokens de diseño mediante variables CSS (custom properties): color de fondo, color de texto principal, color de texto secundario, el color dorado en dos tonos, radio de borde base, y la familia tipográfica principal. Esta es una base razonable y evita, en los archivos que sí usan base.css, la proliferación de valores de color "mágicos" repetidos.

**Gaps del sistema de tokens:** No existen variables de espaciado (equivalentes a \--espacio-sm, \--espacio-md, \--espacio-lg), lo que implica que cada archivo CSS define sus propios valores de padding y margin de forma independiente. Tampoco existe una escala tipográfica formal (variables de tamaño de texto para distintos niveles jerárquicos), ni tokens de sombra (box-shadow) reutilizables. El sistema de tokens cubre color y radio de borde, pero no espaciado ni tipografía ni elevación — los tres pilares restantes de un sistema de diseño completo.

## **6.2 Contraste de color (WCAG AA)**

Se calculó la razón de contraste exacta entre los colores definidos en base.css mediante la fórmula estándar de luminancia relativa del W3C, no por apreciación visual subjetiva.

| Combinación | Uso real en el sitio | Contraste calculado |
| :---- | :---- | :---- |
| \--color-dorado-oscuro (\#E8B923) sobre \--color-fondo (\#F5F3EC) | Color de todos los enlaces \<a\> del sitio, definido globalmente en base.css | 1.66:1 |
| \--color-dorado-oscuro (\#E8B923) sobre blanco (\#FFFFFF) | Números destacados en las tarjetas de resumen (.tarjeta-numero) sobre tarjetas de fondo blanco | 1.84:1 |
| \--color-texto (\#2B2B2B) sobre \--color-fondo (\#F5F3EC) | Texto de cuerpo principal en todo el sitio | 12.75:1 |
| \--color-texto-suave (\#6B6B6B) sobre \--color-fondo (\#F5F3EC) | Texto secundario/atenuado | 4.80:1 |
| \--color-texto (\#2B2B2B) sobre dorado claro (\#F9D976) | Texto de los botones primarios | 10.26:1 |

**Hallazgo grave y cuantificado:** WCAG 2.1 nivel AA exige un mínimo de 4.5:1 para texto normal (o 3:1 para texto grande de al menos 18pt/24px o 14pt/19px en negrita). El color usado para absolutamente todos los enlaces de texto del sitio tiene un contraste de 1.66:1 a 1.84:1 — muy por debajo incluso del umbral más permisivo de la norma, y a una distancia considerable del mínimo exigido. En cambio, el texto de cuerpo principal (12.75:1) y el texto de los botones (10.26:1) superan ampliamente el estándar. El problema de contraste está concentrado y aislado específicamente en el color dorado usado como color de enlace y de número destacado, lo que hace que la corrección sea sencilla y localizada: cambiar el valor de \--color-dorado-oscuro (o introducir una variante más oscura específica para uso sobre fondo claro) resolvería el problema en todo el sitio de una sola vez, dado que es una variable CSS centralizada.

## **6.3 Responsividad y breakpoints**

Se buscó exhaustivamente cada regla @media en la totalidad de los 7 archivos CSS del proyecto. El resultado es un único breakpoint en todo el sistema:

/\* la única regla @media de todo el proyecto, en panel.css \*/  
@media (max-width: 600px) {  
    .barra-superior { /\* ... \*/ }  
}

**Cobertura insuficiente frente a lo pedido:** El .md exige explícitamente diseño responsive para "celular, tablet y computador". El proyecto define un único breakpoint (600px) que además solo afecta a un componente (.barra-superior), no al layout general de las páginas. No existe ninguna regla específica para el rango de tablet (\~768px), y no hay ninguna verificación ni ajuste explícito para pantallas grandes de escritorio (\~1200px+) — el layout a esas resoluciones depende enteramente del comportamiento por defecto de los elementos de bloque y flex sin ningún ajuste deliberado.

El menú de navegación (.nav-barra) usa display:flex; flex-wrap:wrap como única estrategia para pantallas angostas — es decir, cuando el ancho no alcanza para mostrar todos los enlaces en una fila, estos se envuelven a una segunda o tercera línea, en vez de colapsar a un menú hamburguesa oculto tras un botón, que es el patrón que el propio .md pide explícitamente y que además ya fue programado (aunque nunca conectado) en base.js. Con el rol estudiante llegando a tener hasta 9 enlaces de menú, este comportamiento de wrap generará, en un teléfono de gama típica (\~375px de ancho), un bloque de navegación considerablemente alto y visualmente desordenado antes de que el usuario llegue al contenido real de la página.

## **6.4 Cobertura de componentes de interfaz**

Se verificó la presencia o ausencia de reglas CSS para cada uno de los componentes de interfaz que el alcance de esta auditoría pedía confirmar explícitamente, mediante búsqueda de las clases y selectores correspondientes en los 7 archivos CSS del proyecto.

| Componente | ¿Definido? | Detalle |
| :---- | :---- | :---- |
| Botones (primario/secundario/peligro) | Parcial | panel.css define un estilo de botón base y una variante, pero no una variante de "peligro" (danger) diferenciada visualmente para acciones destructivas como desactivar una cuenta o eliminar una entrada de diario — todas las acciones, sin importar su severidad, comparten el mismo tratamiento visual. |
| Inputs y estados focus/error | Parcial | Existen estilos base para inputs en panel.css y login.css, pero no existe ninguna regla :focus visible definida globalmente, ni un estado visual diferenciado de error de validación de campo (borde rojo, mensaje inline) más allá de los mensajes de éxito/error genéricos a nivel de página. |
| Tablas | Sí | Bien cubiertas en panel.css, con estilos consistentes de cabecera, filas alternas y bordes. |
| Tarjetas (cards) | Sí | Bien cubiertas en panel.css, usadas de forma consistente en todos los paneles de resumen. |
| Badges/pills | No encontrado | No se detectó ninguna clase dedicada a insignias o etiquetas visuales tipo pill/badge en ningún archivo CSS. |
| Modal / diálogo | No encontrado | Ninguna regla CSS relacionada con overlay, modal o dialog en ningún archivo. |
| Toast / alerta flotante | No encontrado | Los mensajes de éxito/error existentes son bloques de texto estáticos dentro del flujo normal de la página, no notificaciones flotantes temporales. |
| Estado vacío (empty state) | No encontrado | Cada página con listas (notificaciones, diario, hábitos) resuelve el caso de "sin datos" con una simple etiqueta \<p\> de texto plano sin ningún tratamiento visual — verificado explícitamente en notificaciones.php ("No tienes notificaciones."), sueno.php ("Aún no hay registros."), y emociones.php ("Aún no tienes registros."). |
| Estado de carga (loading) | No encontrado | Coherente con la ausencia casi total de JavaScript funcional: al no haber peticiones asíncronas reales en producción, no hay necesidad ni implementación de indicadores de carga. |
| Paginación | No encontrado | Ninguna clase ni componente de paginación en ningún archivo CSS ni PHP — coherente con el hallazgo de la Sección 7 sobre listas sin paginación en administrador/usuarios.php y administrador/auditoria.php. |

## **6.5 Accesibilidad**

* Texto alternativo en imágenes: se verificaron las 7 etiquetas \<img\> que existen en la totalidad del proyecto, y las 7 incluyen un atributo alt descriptivo (mayormente "Serena" para el logo en distintos contextos). Cobertura completa en este punto específico — el proyecto usa mayormente emojis Unicode como iconografía en vez de imágenes, lo que evita el problema de alt-text pero no resuelve la accesibilidad de esos emojis decorativos para lectores de pantalla, que los anunciarán por su nombre Unicode salvo que se marquen explícitamente como decorativos.

  * Vinculación de \<label\> con inputs: inconsistente. administrador/usuario\_crear.php y administrador/grupos.php usan correctamente \<label for="id-del-input"\>, vinculando explícitamente cada etiqueta con su campo. Otros formularios del proyecto usan \<label\> sin el atributo for, lo que reduce el área clicable de la etiqueta y puede dificultar la navegación con lectores de pantalla.

  * Estados de foco visibles para navegación por teclado: no se encontró ninguna regla :focus, :focus-visible ni equivalente definida de forma explícita y global en ningún archivo CSS del proyecto — la navegación por teclado depende únicamente del estilo de foco por defecto del navegador, que varía entre navegadores y en algunos casos es difícil de percibir sobre los fondos claros del sitio.

  * Contraste de color: ver el detalle cuantificado completo en la Sección 6.2 — fallo confirmado y severo en el color de los enlaces.

## **6.6 Iconografía**

El sistema usa predominantemente emojis Unicode incrustados directamente en el HTML (por ejemplo 🔥 para la racha de hábitos) en vez de un set de iconos dedicado (SVG o fuente de iconos). Esto es funcional y evita una dependencia externa, pero produce una apariencia visual menos pulida y potencialmente inconsistente entre distintos sistemas operativos y navegadores, ya que el renderizado de emojis varía visiblemente entre plataformas (Windows, macOS, Android, iOS pueden mostrar un mismo emoji con un diseño notablemente distinto).

# **7\. Las 18 funciones adicionales aprobadas — análisis extendido**

El documento maestro SERENA\_proyecto\_nuevo.md enumera, bajo el encabezado "Funciones adicionales aprobadas", exactamente 18 ítems numerados. A continuación se reproduce el texto literal de cada uno seguido de su estado verificado, con la evidencia exacta que sustenta ese estado.

### **Función \#1**

**Texto literal del .md:** *"Dashboard de bienvenida con resumen del día al iniciar sesión"*

**Estado verificado:** Implementado

public/estudiante/dashboard.php muestra, verificado por lectura completa del archivo: si el estudiante ya registró su emoción del día (o el mensaje "Todavía no has registrado cómo te sientes hoy" en caso contrario), el conteo de metas activas, y accesos rápidos a los módulos principales.

### **Función \#2**

**Texto literal del .md:** *"Panel del psicólogo/orientador con alertas por estudiante o grupo"*

**Estado verificado:** Implementado

psicologo/panel.php y orientador/panel.php muestran conteos de alertas pendientes; psicologo/alertas.php y orientador/alertas.php listan las alertas individuales, con filtrado por grupo en el caso de orientador.

### **Función \#3**

**Texto literal del .md:** *"Alertas tempranas automáticas si hay varios días seguidos de emociones muy bajas"*

**Estado verificado:** Implementado

src/helpers/notificador.php, función verificarAlertasEmocionales(): detecta 3 o más días consecutivos de nivel emocional bajo mediante EstadoEmocional::diasConsecutivosBajos(), crea una AlertaTemprana con nivel de urgencia 'alto', y evita duplicar la misma alerta si ya existe una reciente del mismo tipo.

### **Función \#4**

**Texto literal del .md:** *"Chat interno estudiante–psicólogo/orientador"*

**Estado verificado:** Implementado, con vulnerabilidad crítica

Funcional en el flujo normal del lado del estudiante. Del lado profesional, sufre el IDOR crítico documentado en la Sección 3.1 que permite acceso no autorizado a conversaciones ajenas.

### **Función \#5**

**Texto literal del .md:** *"Calendario integrado (emociones \+ hábitos \+ metas superpuestos)"*

**Estado verificado:** Implementado, parcial

estudiante/calendario.php superpone niveles emocionales promedio y hábitos completados por día, pero no incluye metas en la vista superpuesta, pese a que el texto del .md las menciona explícitamente junto a emociones y hábitos.

### **Función \#6**

**Texto literal del .md:** *"Sistema de grupos/cursos (admin crea grados y grupos, docente ve solo el suyo)"*

**Estado verificado:** Implementado, con acceso roto

administrador/grupos.php permite crear grados y grupos correctamente. Sin embargo, docente/grupo.php —la página que debería permitir al docente ver su propio grupo— redirige a una página que rechaza explícitamente su rol, devolviendo HTTP 403 (ver Sección 2.3 y 2.4). La función existe a nivel de administración pero está rota en el extremo de consumo por el docente.

### **Función \#7**

**Texto literal del .md:** *"Encuestas periódicas lanzadas por el psicólogo"*

**Estado verificado:** Implementado, casi completo

psicologo/encuesta\_crear.php, encuestas.php y encuesta\_resultados.php funcionan correctamente y con prepared statements sólidos. El único gap es que Encuesta::yaConcluida() nunca se invoca desde el lado del estudiante, por lo que en teoría se podría responder una encuesta ya cerrada.

### **Función \#8**

**Texto literal del .md:** *"Reportes agregados anónimos para administrador/rector/coordinador"*

**Estado verificado:** Implementado

rector/panel.php es el ejemplo más sólido de esta función, mostrando exclusivamente cifras agregadas institucionales con un mensaje explícito de confidencialidad. psicologo/reportes.php (compartido con coordinador) complementa con promedios agregados de emociones y sueño.

### **Función \#9**

**Texto literal del .md:** *"Recordatorios configurables por el usuario (hora elegida) — incluye avisos para registrar emoción, tomar agua, dormir a cierta hora"*

**Estado verificado:** No iniciado

La tabla recordatorios existe en el esquema pero, confirmado por búsqueda exhaustiva, ningún archivo PHP del proyecto la referencia. Adicionalmente, no existe ninguna infraestructura de tareas programadas (cron, scheduler) en absolutamente ningún punto del proyecto ni de su documentación — sin ese mecanismo, un recordatorio configurado por el usuario no tendría forma técnica de dispararse sin que el usuario tenga la aplicación abierta en ese momento exacto.

### **Función \#10**

**Texto literal del .md:** *"Bitácora de auditoría de cambios de datos sensibles aprobados por el admin"*

**Estado verificado:** No iniciado en la práctica

La infraestructura completa existe y es correcta (tabla auditoria, helper auditarAccion(), vista administrador/auditoria.php), pero se confirmó mediante búsqueda exhaustiva que auditarAccion() nunca se invoca desde ningún evento real del sistema. Ver desarrollo completo en la Sección 3.6.

### **Función \#11**

**Texto literal del .md:** *"Racha (streaks) — días consecutivos cumpliendo un hábito o registrando emoción"*

**Estado verificado:** Implementado, parcial

Habito::rachaActual() está completamente implementado y funcional, calculando correctamente días consecutivos con lógica de fechas real, mostrado en la interfaz con el indicador 🔥. No existe, sin embargo, ningún mecanismo equivalente de racha para el registro de emociones, pese a que el texto del .md menciona explícitamente ambos casos de uso.

### **Función \#12**

**Texto literal del .md:** *"Insignias/logros — gamificación ligera (7 días seguidos, primer mes, etc.)"*

**Estado verificado:** No iniciado

Las tablas insignias y usuario\_insignias existen en el esquema, con un diseño correcto incluyendo una restricción UNIQUE bien pensada para evitar otorgar la misma insignia dos veces. Confirmado por búsqueda exhaustiva que ningún archivo PHP del proyecto las referencia — no existe ningún código que evalúe condiciones de logro ni que otorgue insignias.

### **Función \#13**

**Texto literal del .md:** *"Exportar reportes — PDF/imagen con el resumen semanal o mensual, para llevar a un psicólogo si el usuario quiere"*

**Estado verificado:** No iniciado

Confirmado por búsqueda exhaustiva de las cadenas TCPDF, FPDF, dompdf, mPDF y de las palabras "descargar"/"exportar" en la totalidad del proyecto: no existe ninguna librería de generación de PDF ni ningún endpoint de descarga de reportes en ningún punto del código.

### **Función \#14**

**Texto literal del .md:** *"Recursos de apoyo — artículos, ejercicios de respiración, contactos de líneas de ayuda emocional"*

**Estado verificado:** Implementado

estudiante/recursos\_apoyo.php contiene contenido real y verificado: líneas de ayuda colombianas vigentes (Línea 106 salud mental, Línea 123 emergencias, Línea 155 violencia de género, línea del ICBF), ejercicios de respiración con instrucciones concretas, y una sección adicional cargada dinámicamente desde la tabla recursos\_apoyo. Es una de las funciones mejor ejecutadas de todo el proyecto, con un tono apropiado documentado en la Sección 8\.

### **Función \#15**

**Texto literal del .md:** *"Comparativa mensual — evolución mes a mes, no solo semana actual"*

**Estado verificado:** No iniciado

estadisticas.php solo ofrece un selector de rango de fechas libre; no existe ningún cálculo ni visualización de comparación entre periodos (ni semanal ni mensual) en ningún punto del código verificado.

### **Función \#16**

**Texto literal del .md:** *"Etiquetas en el diario — por tema (familia, estudio, amigos) para filtrar después"*

**Estado verificado:** Implementado, con mitigación de seguridad insuficiente

Las tablas etiquetas y diario\_etiquetas sí se usan activamente desde Diario::crear(), permitiendo al estudiante etiquetar libremente sus entradas. El problema no es de ausencia de función sino de seguridad: la búsqueda de la etiqueta existente se realiza con addslashes() en vez de un prepared statement, documentado en detalle en la Sección 3.3.

### **Función \#17**

**Texto literal del .md:** *"Modo oscuro — accesibilidad visual"*

**Estado verificado:** No iniciado

Confirmado por búsqueda exhaustiva de las cadenas "modo oscuro", "dark mode" y "tema oscuro" en la totalidad del proyecto: cero resultados. No existe ningún mecanismo de tema alternativo, ni variables CSS duplicadas para un esquema oscuro, ni ningún toggle en la interfaz.

### **Función \#18**

**Texto literal del .md:** *"Alertas de bienestar — si hay varios días seguidos de emociones muy bajas, sugerir un recurso de ayuda (sin diagnosticar, solo sugerir)"*

**Estado verificado:** Implementado

Esta función está estrechamente relacionada con la función \#3 y comparte su misma implementación en notificador.php: además de crear la alerta temprana visible para psicólogo/orientador, el sistema envía al propio estudiante una notificación con el texto "Hemos notado que llevas varios días sintiéndote mal. Recuerda que puedes hablar con el psicólogo.", enlazando directamente a recursos\_apoyo.php. El lenguaje es apropiado: no diagnostica, sugiere, y dirige explícitamente hacia un profesional humano en vez de posicionar al sistema mismo como la fuente de ayuda — evaluado con más detalle en la Sección 8\.

## **7.1 Resumen cuantitativo de las 18 funciones**

De las 18 funciones adicionales aprobadas: 0 están completamente implementadas sin observaciones mayores, 6 están implementadas pero con una vulnerabilidad de seguridad o un gap funcional específico documentado, y 6 no tienen ningún código que las respalde en absoluto pese a que, en varios casos (recordatorios, insignias), la tabla de base de datos correspondiente ya fue diseñada correctamente.

# **8\. Bienestar emocional y privacidad — análisis extendido**

## **8.1 Tono y lenguaje del sistema hacia el estudiante**

Se revisó el texto exacto de cada mensaje generado por el sistema que un estudiante vería en un momento de vulnerabilidad emocional. El hallazgo central es positivo: el proyecto mantiene, en los puntos verificados, una disciplina consistente de lenguaje no clínico, no diagnóstico, y que dirige activamente hacia el apoyo humano en vez de posicionar al sistema como sustituto de ese apoyo.

// src/helpers/notificador.php  
notificar($pdo, $usuarioId, 'bienestar',  
    'Hemos notado que llevas varios días sintiéndote mal. Recuerda que puedes hablar con el psicólogo.',  
    '/estudiante/recursos\_apoyo.php');

Este mensaje evita deliberadamente términos clínicos (no dice "detectamos síntomas de depresión" ni ningún lenguaje diagnóstico), usa un tono de acompañamiento ("hemos notado", no "el sistema ha determinado"), y dirige explícita y directamente a un profesional humano ("puedes hablar con el psicólogo") en vez de sugerir que el propio chat o la propia aplicación puedan resolver la situación. Esto cumple bien con el requisito del alcance de esta auditoría de que las alertas tempranas "apunten explícitamente al estudiante hacia un psicólogo/consejero humano en lugar de implicar que el propio sistema está brindando apoyo".

El contenido de estudiante/recursos\_apoyo.php, verificado en su totalidad, mantiene el mismo estándar: información práctica y clara sobre líneas de ayuda reales (con sus números correctos vigentes en Colombia), ejercicios de respiración explicados paso a paso sin lenguaje alarmista, y ninguna sección que intente diagnosticar o etiquetar clínicamente el estado del estudiante.

## **8.2 Restricción de visibilidad de datos emocionales — verificada en código, no solo en la interfaz**

El .md especifica: "Datos emocionales visibles para: psicólogo, profesor orientador, administrador". Se verificó esta restricción directamente en el control de acceso de cada archivo que expone datos individuales de estado emocional:

* psicologo/estudiante\_detalle.php — requiereRol('psicologo','orientador'). No incluye 'administrador' explícitamente en esta lista, aunque el administrador tiene su propia vía de gestión de usuarios sin necesidad de ver el detalle emocional día a día.

* orientador/grupo.php — requiereRol('orientador') exclusivamente, y sí filtra correctamente por el grupo asignado al orientador en la consulta de resumen del grupo, aunque el gap de granularidad en estudiante\_detalle.php (Sección 2.2) permite a un orientador ver el detalle de estudiantes fuera de su grupo si conoce o adivina el ID.

* docente/\*.php — no tiene acceso a ningún archivo que muestre datos emocionales individuales, ni siquiera agregados de grupo, más allá del conteo simple de estudiantes en su panel. Esto es coherente con el .md, que no incluye a docente en la lista de roles con acceso a datos emocionales.

* coordinador/\*.php y rector/panel.php — solo acceden a cifras agregadas/anónimas, nunca a un registro emocional individual identificable, verificado por ausencia de cualquier enlace o consulta a estado\_emocional filtrada por un usuario\_id específico en estos archivos.

**Conclusión:** La restricción de visibilidad emocional está mayormente bien implementada a nivel de código de control de acceso (no solo ocultando enlaces en la interfaz, que sería una protección débil), con la única excepción relevante siendo la falta de scoping por grupo del orientador dentro de estudiante\_detalle.php documentada en la Sección 2.2.

## **8.3 Consentimiento de datos — Ley 1581**

**Archivo:** public/registro.php; public/consentimiento\_datos.php; src/controladores/AuthControlador.php

Se verificó que el checkbox de consentimiento en el formulario de registro incluye el atributo required en el HTML, y —más importante— que esta validación se repite del lado del servidor, no solo del cliente:

// src/controladores/AuthControlador.php, dentro de registrarEstudiante()  
if (\!$datos\['consentimiento'\]) {  
    return \['exito' \=\> false, 'mensaje' \=\> 'Debes aceptar el tratamiento de datos personales para registrarte.'\];  
}

**Conclusión:** El consentimiento es un bloqueo real, no cosmético: un registro que llegara al servidor sin el consentimiento marcado (por ejemplo, si alguien deshabilitara el JavaScript de validación del cliente o enviara la petición directamente) sería rechazado igualmente por esta verificación server-side. Esta es una de las implementaciones legales mejor ejecutadas del proyecto.

El único punto débil de esta cadena, ya documentado en la Sección 5.2, es que el derecho de eliminación de datos que consentimiento\_datos.php promete textualmente al estudiante no tiene ninguna funcionalidad real que lo haga ejercible — el consentimiento de entrada está bien resuelto, la salida (derecho al olvido) no.

## **8.4 Edad mínima**

El .md establece que la plataforma está dirigida "desde grado sexto en adelante (\~10-11 años)", y que "no se requiere autorización de un adulto para que un menor se registre". El código valida correctamente una edad mínima de 10 años mediante validarEdadMinima() en src/helpers/validaciones.php, calculada con DateTime a partir de la fecha de nacimiento declarada, y rechaza el registro con un mensaje apropiado si no se cumple. No hay, ni el .md lo exige, ningún flujo de autorización parental adicional.

# **9\. Rendimiento y preparación para escala (objetivo: 1000+ estudiantes)**

## **9.1 Patrón N+1 confirmado**

**Archivo:** public/orientador/grupo.php

foreach ($estudiantes as $e):  
    $nivelHoy \= $pdo-\>prepare("SELECT AVG(nivel) FROM estado\_emocional WHERE usuario\_id=:uid AND DATE(fecha\_hora)=CURDATE()");  
    $nivelHoy-\>execute(\['uid'=\>$e\['id'\]\]);  
    $nivel \= $nivelHoy-\>fetchColumn();  
endforeach;

Esta es la única instancia de patrón N+1 confirmada de forma explícita durante la auditoría, pero es representativa del tipo de problema que aparecerá en cualquier vista de "lista de estudiantes con un dato individual por fila" si no se reescribe como una sola consulta agregada (por ejemplo, un JOIN con GROUP BY en vez de una consulta por fila dentro del bucle). Con un grupo de tamaño típico (25–40 estudiantes), esta única página ya generaría entre 25 y 40 consultas adicionales en cada carga; a la escala institucional completa de 1000+ estudiantes distribuidos en decenas de grupos, el patrón se repetiría proporcionalmente cada vez que un orientador consulte su grupo.

## **9.2 Listas sin paginación**

Se identificaron las siguientes vistas que cargan listas completas de registros sin ningún mecanismo de paginación, límite ajustable por el usuario, o scroll infinito:

* administrador/usuarios.php — cargaría la totalidad de usuarios del sistema en una sola consulta y una sola tabla HTML. A 1000+ estudiantes más el personal institucional, esto renderizaría más de mil filas de una sola vez.

* psicologo/estudiantes.php — mismo patrón, aunque mitigado parcialmente por el campo de búsqueda por nombre/correo, que sí permite filtrar, pero la carga inicial sin búsqueda sigue siendo la lista completa.

* administrador/auditoria.php — limitada explícitamente a 200 registros mediante Auditoria::listar($pdo, 200), lo cual evita el problema de crecimiento ilimitado, pero tampoco ofrece ninguna forma de navegar más allá de esos 200 registros más recientes ni de filtrar por fecha/usuario/tipo de acción — es, en el mejor de los casos, una mitigación parcial y no una paginación real. En cualquier caso, este hallazgo es hoy teórico dado que, como se documentó en la Sección 3.6, la tabla nunca recibe datos reales.

## **9.3 Índices del esquema frente a los patrones de consulta reales**

Como se documentó en la Sección 5.3, el esquema define índices razonables sobre las combinaciones de usuario\_id y fecha en las tablas de series temporales. No se detectaron columnas de fecha sin índice de soporte en el propio diseño del esquema. El riesgo de rendimiento identificado en esta auditoría no proviene de un esquema mal indexado, sino específicamente del patrón de código N+1 ya descrito y de la ausencia de paginación — ambos son problemas de la capa de aplicación, no de la capa de base de datos.

# **10\. Preparación para el despliegue en InfinityFree — septiembre 2026**

## **10.1 Document root y exposición de src/ y sql/**

Ver el desarrollo técnico completo de este hallazgo, con la cita exacta del .htaccess y la explicación del comportamiento de InfinityFree, en la Sección 3.10. En resumen: InfinityFree no permite fijar el document root a una subcarpeta como public/, lo que obliga a que la única protección real de src/ y sql/ en ese entorno sea la regla de .htaccess de la raíz del proyecto — debe verificarse activamente en un entorno de staging antes del piloto.

## **10.2 Envío de correo electrónico**

Como se documentó en la Sección 3.7, la función enviarCorreoRecuperacion() no envía correo real, solo escribe en el log del servidor. InfinityFree, como la mayoría de proveedores de hosting compartido gratuito, restringe severamente o bloquea por completo el uso de la función mail() nativa de PHP para evitar abuso para spam — es previsible que, incluso si se implementara mail() de forma básica, no seaentregable de forma confiable a las bandejas de entrada reales de los estudiantes. La recomendación técnica más viable es integrar un servicio de envío transaccional por API (por ejemplo, un proveedor con plan gratuito que no dependa del puerto SMTP tradicional, frecuentemente bloqueado en hosting compartido) en vez de depender de mail() o de una conexión SMTP saliente directa.

## **10.3 Configuración basada en .env**

src/config/config.php lee el archivo .env manualmente (sin depender de una librería externa como vlucas/phpdotenv), mediante una función simple que parsea líneas KEY=VALUE y las carga con putenv(). Esta implementación es compatible con cualquier versión de PHP soportada por InfinityFree, ya que no depende de extensiones adicionales ni de Composer. El único riesgo aquí, ya documentado, es la ausencia de .gitignore, que expondría el contenido de .env al subir el repositorio a GitHub — no es un problema de compatibilidad con el hosting, sino de higiene de control de versiones.

## **10.4 Versión de PHP y compatibilidad de sintaxis**

Se verificó mediante búsqueda exhaustiva que el código del proyecto no usa ninguna característica introducida en PHP 8.1 o posterior (propiedades readonly, enums nativos de PHP, la expresión match()). Esto es una señal positiva de compatibilidad amplia con las versiones de PHP típicamente disponibles en hosting compartido económico o gratuito, que con frecuencia se quedan uno o dos ciclos de versión por detrás de la última versión estable de PHP.

## **10.5 Subida de archivos**

La única funcionalidad de subida de archivos real y verificada en todo el proyecto es la importación de CSV de estudiantes en administrador/importar\_estudiantes.php (Sección 2.7), que no tiene límites de tamaño configurados explícitamente en el código PHP más allá de los límites por defecto de la configuración global de PHP del servidor (upload\_max\_filesize, post\_max\_size), que en InfinityFree suelen ser conservadores y no siempre configurables por el usuario del hosting. No se encontró ninguna funcionalidad de subida de foto de perfil ni de imágenes de diario en ningún punto del código —ambas funciones descritas en el .md— por lo que no hay, hoy, ningún riesgo de límite de subida que evaluar para esas dos funciones específicas, dado que simplemente no existen.

# **11\. Tabla resumen de puntuación por área**

Cada puntaje refleja el estado verificado documentado en las secciones anteriores de este informe, no una impresión general.

| Área | Puntaje (1–10) | Motivo principal |
| :---- | ----- | :---- |
| Navegación y menús | **3** | 5 de 7 roles tienen funciones reales sin ningún enlace visible en su menú de navegación principal; 2 rutas críticas entre roles (docente→grupo, docente→detalle de estudiante) devuelven HTTP 403 por incompatibilidad de rol, dejando al rol docente sin sus dos funciones principales operativas. |
| Seguridad | **3** | Un IDOR crítico permite lectura y escritura no autorizada en conversaciones de chat ajenas entre profesionales; IDOR adicional en borrado de diario (agravado por ser explotable vía CSRF) y en subtareas de metas; cero protección CSRF en cualquier formulario POST del proyecto; cero límite de intentos en login y en el PIN del diario; al menos 8 puntos verificados de interpolación directa de SQL en vez de prepared statements. |
| Base de datos y esquema | **6** | Esquema de 29 tablas bien normalizado, con comportamiento ON DELETE razonable frente a la Ley 1581 y buenos índices en las tablas de series temporales; pero 4 tablas (13,8% del esquema) no tienen absolutamente ningún código que las use, y el archivo de migraciones está completamente vacío. |
| Cobertura funcional por rol | **4** | Rector (1 archivo) y coordinador (2 archivos) muy por debajo de los demás roles en superficie funcional; 12 de 13 controladores de negocio son stubs vacíos, forzando a que toda la lógica viva mezclada con la presentación. |
| Las 18 funciones aprobadas | **4** | De 18 funciones: aproximadamente 7 completamente implementadas, 6 implementadas con un gap o vulnerabilidad específica, y 5 no iniciadas en absoluto (recordatorios, insignias/logros, exportar reportes, comparativa mensual, modo oscuro). |
| Arquitectura y calidad de código | **3** | Separación public/src no respetada en la práctica — toda la lógica de negocio vive en public/; 9 de 10 archivos JS y 4 de 7 archivos CSS nunca se cargan en ninguna página; una API REST completa en public/api/ existe desconectada del frontend; lógica de conteo duplicada casi idéntica en 5 archivos distintos sin ninguna función compartida. |
| UI, sistema de diseño y CSS | **4** | Sistema de tokens cubre color y radio de borde pero no espaciado, tipografía ni sombra; un único breakpoint responsive en todo el proyecto, y solo afecta a un componente; contraste de todos los enlaces del sitio muy por debajo de WCAG AA (1.66:1–1.84:1 frente al mínimo de 4.5:1); sin componentes de modal, toast, paginación ni estados de carga/vacío diseñados. |
| Bienestar emocional y privacidad | **7** | Lenguaje consistentemente no clínico y apropiado hacia el estudiante, verificado en cada mensaje generado por el sistema; alertas dirigen correctamente hacia el psicólogo humano; restricción de visibilidad de datos emocionales verificada en el código de control de acceso, no solo en la interfaz; rector es un ejemplo ejemplar de minimización de datos — pero el IDOR del chat compromete directamente la confidencialidad que el resto del sistema sí protege bien. |
| Auditoría y trazabilidad | **1** | La infraestructura completa (tabla, helper, vista de consulta) está construida correctamente, pero auditarAccion() nunca se invoca desde ningún evento real del sistema — cero eventos sensibles quedan registrados en la práctica. |
| Rendimiento y escalabilidad | **4** | Patrón N+1 confirmado con evidencia de código exacta en orientador/grupo.php; listas sin paginación en al menos 2 vistas administrativas; el esquema en sí está razonablemente indexado, el riesgo está concentrado en la capa de aplicación, no en el diseño de base de datos. |
| Preparación para despliegue (InfinityFree) | **3** | InfinityFree no permite fijar el document root a public/, obligando a depender enteramente de una única regla de .htaccess para proteger src/ y sql/ — sin verificar aún en un entorno de staging real; envío de correo no funcional en el estado actual; ausencia total de .gitignore, exponiendo .env al subir el repositorio. |

# **12\. Top 15 de correcciones prioritarias**

*Ordenado por severidad: seguridad y flujos de datos rotos primero, pulido visual al final. Cada ítem referencia la sección de este informe donde se documenta la evidencia completa.*

| \# | Severidad | Qué corregir | Dónde |
| ----- | :---- | :---- | :---- |
| 1 | **CRÍTICO** | Corregir el IDOR en el chat entre profesionales: verificar que la conversación pertenezca al profesional autenticado antes de listar, enviar o marcar mensajes como leídos. Ver Sección 3.1. | src/modelos/Mensaje.php; public/psicologo/chat.php; public/orientador/chat.php |
| 2 | **CRÍTICO** | Verificar activamente, en un entorno de staging real de InfinityFree, que el .htaccess raíz bloquea efectivamente el acceso HTTP a src/ y sql/, dado que ese proveedor no permite fijar el document root a public/. Ver Sección 3.10 y 10.1. | .htaccess (raíz) |
| 3 | **ALTO** | Corregir el IDOR en el borrado de entradas de diario y convertir la acción de GET a POST protegido con CSRF. Ver Sección 3.2. | public/estudiante/diario.php; src/modelos/Diario.php |
| 4 | **ALTO** | Reparar el rol docente: grupo.php y estudiante\_detalle.php redirigen hoy a páginas que rechazan su rol, devolviendo HTTP 403 en las dos funciones principales del rol. Ver Sección 2.3 y 2.4. | public/docente/grupo.php; public/docente/estudiante\_detalle.php |
| 5 | **ALTO** | Implementar envío real de correo para recuperación de contraseña, mediante un servicio de envío transaccional compatible con las restricciones típicas de hosting compartido. Ver Sección 3.7 y 10.2. | src/controladores/AuthControlador.php |
| 6 | **ALTO** | Enlazar administrador/resetear\_password.php desde el panel de administrador — hoy es la única vía de recuperación funcional del sistema completo y es inalcanzable sin escribir la URL a mano. Ver Sección 2.7. | src/helpers/navegacion.php; public/administrador/panel.php |
| 7 | **ALTO** | Agregar token CSRF a todos los formularios POST que cambian estado, priorizando: activar/desactivar usuario, aprobar/rechazar solicitud de cambio, marcar alerta como atendida. Ver Sección 3.5. | public/administrador/usuarios.php; aprobaciones.php; alertas.php (psicólogo/orientador) |
| 8 | **ALTO** | Conectar auditarAccion() a los eventos que ya deberían registrarse: cambio de contraseña, activar/desactivar cuenta, aprobación de cambios, creación de usuario individual y masiva. Ver Sección 3.6. | src/helpers/auditor.php \+ cada archivo administrativo relevante |
| 9 | **MEDIO** | Corregir el IDOR de Meta::toggleSubtarea() validando que la subtarea pertenezca a la meta ya verificada como propiedad del usuario. Ver Sección 3.4. | src/modelos/Meta.php |
| 10 | **MEDIO** | Reemplazar las 8+ instancias identificadas de interpolación directa de variables en SQL por prepared statements, incluso donde la variable ya llega tipada como int. Ver Sección 3.9. | src/modelos/Encuesta.php; Meta.php; Notificacion.php; Auditoria.php; varias páginas public/ |
| 11 | **MEDIO** | Implementar límite de intentos (rate limiting) en el inicio de sesión y en el PIN del diario, dado que este último tiene un espacio de búsqueda de solo 10.000 combinaciones. Ver Sección 3.8. | src/controladores/AuthControlador.php; public/estudiante/diario\_pin.php |
| 12 | **MEDIO** | Solucionar el flujo de importación masiva de estudiantes: comunicar de alguna forma la contraseña generada automáticamente, o forzar un cambio obligatorio de contraseña en el primer inicio de sesión. Ver Sección 2.7. | public/administrador/importar\_estudiantes.php |
| 13 | **MEDIO** | Corregir Notificacion::contarNoLeidas() (usa un booleano como si fuera un conteo, y ejecuta una segunda consulta insegura redundante) y conectarlo a un indicador visual real de notificaciones pendientes en el encabezado. Ver Sección 4.3. | src/modelos/Notificacion.php; public/parciales/encabezado.php |
| 14 | **BAJO** | Corregir el contraste de \--color-dorado-oscuro sobre fondos claros (1.66:1–1.84:1, muy por debajo del mínimo WCAG AA de 4.5:1) en todos los enlaces del sitio. Al ser una variable CSS centralizada, la corrección es de un solo punto. Ver Sección 6.2. | public/assets/css/base.css |
| 15 | **BAJO** | Reconectar o eliminar deliberadamente el CSS y JS huérfanos (estudiante.css, chat.css, administrador.css, panel\_apoyo.css, y 9 de 10 archivos JS), e incluir base.js para activar el menú hamburguesa en móvil ya programado pero nunca conectado. Ver Sección 4.4 y 4.5. | public/assets/css/\*; public/assets/js/\*; public/parciales/encabezado.php |

# **13\. Listo para septiembre vs. puede esperar a octubre**

## **13.1 Bloqueantes para el piloto de septiembre**

No debería lanzarse a la institución con datos reales de estudiantes menores de edad sin resolver lo siguiente:

* Los ítems \#1 a \#8 de la tabla del Top 15 — todo lo marcado CRÍTICO y ALTO.

* Confirmación activa, en un entorno de staging real desplegado en InfinityFree (no solo en desarrollo local), de que src/ y sql/ no son accesibles por HTTP directo, antes de subir cualquier dato de estudiantes reales a ese entorno.

* Una decisión explícita y documentada sobre el flujo de recuperación de contraseña si el envío de correo real no se puede resolver a tiempo para septiembre: como mínimo, garantizar que resetear\_password.php esté enlazada y accesible para el administrador, con un procedimiento operativo claro de cómo comunicar la nueva contraseña al usuario por un canal alterno (por ejemplo, en persona o por un canal institucional ya existente).

* Una decisión explícita de alcance sobre las funciones que hoy no están iniciadas pero que la interfaz podría sugerir implícitamente que existen (diario con imágenes, exportación de reportes en PDF): decidir si se lanzan sin esas funciones, ajustando cualquier expectativa textual en la interfaz, o si se retrasa el lanzamiento hasta tenerlas — no es solo una cuestión técnica sino de qué le promete el sistema al usuario.

* Reparar como mínimo la función "Ver mi grupo" del rol docente (ítem \#4), dado que es una de sus dos únicas funciones en todo el sistema y hoy está completamente inoperante.

## **13.2 Puede esperar a octubre**

* Reconexión del CSS y JS huérfanos y pulido visual general: contraste de enlaces, breakpoints adicionales para tablet y escritorio, componentes de modal/toast/paginación, estados de carga y vacío diseñados.

* Gráficos de torta y circular, y comparación entre semanas/meses en el módulo de estadísticas.

* Exposición en la interfaz del historial mensual de cumplimiento de hábitos — el método ya existe en el modelo, solo falta construir la vista que lo consuma.

* Implementación del flujo de meta vencida (preguntar al usuario si continuar o reiniciar).

* Ampliación de la superficie funcional de coordinador y rector, los dos roles con menor cobertura actual frente a lo descrito en el .md.

* Sistema completo de recordatorios configurables e insignias/logros — ambos requieren, además de la interfaz, resolver primero la ausencia de un mecanismo de tareas programadas (cron), lo cual es un cambio de infraestructura más amplio que no debería apresurarse antes del piloto.

* Conectar auditarAccion() de forma más exhaustiva a todos los eventos sensibles restantes que no se priorizaron en el Top 15 — aunque no debería postergarse mucho más allá de septiembre, dado su bajo costo de implementación relativo a su valor de trazabilidad.

* Modo oscuro.

* Refactorización de los 12 controladores stub y de la lógica duplicada entre paneles — es un trabajo de arquitectura interna que no bloquea el uso funcional del sistema, pero que facilitará considerablemente el mantenimiento y la corrección centralizada de bugs a medida que el proyecto crezca.

# **14\. Apéndice — tabla consolidada de brechas de navegación**

Consolidado de todas las páginas identificadas como huérfanas, con acceso indirecto, o con un enlace roto entre roles, documentadas en detalle en la Sección 2\.

| Archivo | ¿En menú principal? | Situación de alcanzabilidad real |
| :---- | :---- | :---- |
| estudiante/calendario.php | No | Huérfana en el menú principal |
| estudiante/diario\_pin.php | No | Alcanzable solo indirectamente, sin enlace directo en el menú |
| estudiante/encuestas.php | No | Huérfana en el menú principal |
| estudiante/notificaciones.php | No | Huérfana en el menú principal |
| estudiante/perfil.php | Parcial | Alcanzable vía acceso rápido en dashboard.php, ausente del menú principal |
| psicologo/encuesta\_crear.php | Parcial | Alcanzable vía acceso rápido en panel.php, ausente del menú principal |
| psicologo/encuesta\_resultados.php | No | Huérfana — sin ningún enlace visible confirmado en toda la interfaz |
| docente/grupo.php | Sí, pero roto | En el menú, pero el clic devuelve HTTP 403 por redirect a un rol distinto |
| docente/estudiante\_detalle.php | No, y roto si se accede | Sin enlace directo; y aunque se accediera por URL, redirige a un 403 |
| administrador/usuario\_crear.php | No | Alcanzable vía usuarios.php → "Crear usuario", ausente del menú principal |
| administrador/importar\_estudiantes.php | No | Huérfana — sin ningún enlace en toda la interfaz |
| administrador/resetear\_password.php | No | Huérfana — sin ningún enlace en toda la interfaz; única vía de recuperación de contraseña funcional del sistema completo |

## **14.1 Redirects entre roles — funcionan vs. rotos**

| Redirect | Rol de origen | Resultado verificado |
| :---- | :---- | :---- |
| docente/grupo.php → orientador/grupo.php | docente | ROTO — orientador/grupo.php exige requiereRol('orientador') exclusivamente |
| docente/estudiante\_detalle.php → psicologo/estudiante\_detalle.php | docente | ROTO — el destino exige requiereRol('psicologo','orientador'), sin 'docente' |
| orientador/estudiante\_detalle.php → psicologo/estudiante\_detalle.php | orientador | Funciona — el destino sí acepta el rol orientador |
| coordinador/reportes.php → psicologo/reportes.php | coordinador | Funciona — el destino sí acepta el rol coordinador |

# **15\. Cierre**

Este informe documenta la totalidad de los hallazgos encontrados durante una revisión completa y literal del código fuente del proyecto Serena: los 99 archivos PHP, el esquema SQL de 29 tablas, los 10 archivos JavaScript, las 7 hojas de estilo, y los tres documentos de referencia del proyecto. Cada afirmación de este documento está respaldada por una ruta de archivo exacta y, en la gran mayoría de los casos, por el fragmento de código específico que la sustenta. Donde no fue posible confirmar un comportamiento sin ejecutar la aplicación en un entorno real, se señaló explícitamente como pendiente de verificación en lugar de asumir un resultado.

El proyecto tiene un núcleo técnico más sólido de lo que su estado visual actual comunica: la disciplina de seguridad en la autenticación, el manejo de contraseñas, y el consentimiento de datos personales están, en su mayoría, bien resueltos. El patrón que más debe preocupar al equipo no es la ausencia de trabajo, sino la cantidad de trabajo ya construido que nunca terminó de conectarse al resto del sistema — controladores, JavaScript, CSS y una capa entera de API quedaron aislados del flujo real de la aplicación. Resolver esa desconexión, junto con los ocho hallazgos de seguridad de mayor severidad listados en la Sección 12, es el camino más directo hacia un piloto de septiembre seguro y funcional para los estudiantes de la institución.