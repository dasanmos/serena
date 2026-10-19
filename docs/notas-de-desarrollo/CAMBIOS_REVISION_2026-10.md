# Revisión de seguridad, roles y estética — octubre 2026

## Antes de desplegar (obligatorio)
1. Rotar TODAS las claves del .env anterior (BD, Brevo, OpenRouter, reCAPTCHA, SERENA_2FA_KEY*, secretos de cron).
   *Si cambias SERENA_2FA_KEY, quienes tengan 2FA activo deberán reactivarlo.
2. Este paquete NO trae `src/config/.env`: conserva el tuyo (con claves nuevas) y agrégale
   `APP_URL=https://serena.infinityfreeapp.com` (sin /public; ya viene así en .env.example).
3. Los tokens de recuperación ahora se guardan con hash: los enlaces enviados antes de actualizar dejan de servir (duran 1 h).

## Seguridad
- Fuera del paquete: .env reales, respaldos .sql y archivos subidos de estudiantes.
- `006_reset_admin_y_nuevos.sql`: el hash fijo era de la clave «password» (no «serena2024»). Ahora trae un marcador inutilizable.
- `.htaccess` raíz: bloquea src, sql, tools, docs, archivos ocultos y .md/.txt/.py/.sql/.env.
- Enlaces de correos con `origenSitio()` / `urlAbsoluta()` (config.php) en vez de la cabecera Host.
- Corregido: el resumen semanal armaba enlaces con BASE_URL duplicado.
- `api/emociones.php` y `api/habitos.php`: CSRF + validación (emoción, nivel 1–5, momento, fecha).

## Roles
- Orientador limitado a sus grupos en hoja de vida, directorio, Casos y alertas.
- Administrador sin acceso a salud, desarrollo, observaciones reservadas ni aula de apoyo.
- «Ver como»: ya no permite psicólogo, orientador, administrador, superadmin ni secretaría.
- Chat: el acudiente ya no aparece como destinatario (no tiene bandeja).
- Nuevos: `secretaria_academica/perfil.php`, `secretaria_academica/mensajes.php`, `acudiente/perfil.php`.
- Coordinador puede tomar asistencia.
- Páginas solo-superadmin movidas a `/superadmin/` (respaldo, respaldo_descargar, generar/eliminar datos de prueba, prueba_ia); las rutas viejas redirigen.

## Limpieza
- Eliminados: gemini.php, prueba_gemini.php, docente/planilla.php (ya redirigida por .htaccess), menu_lateral.css/js, 042_ia_registro.sql (duplicado).
- Corregidos avisos que pedían `038_informes.sql` (no existe) → `040_informes.sql`.
- `sql/migraciones/ORDEN.md` explica los prefijos repetidos.
- Documentación movida a `docs/`.

## Estética
- 6 iconos nuevos en el mismo estilo dorado (asistencia, horario, documento, certificado, planeador, académico).
- Etiquetas: «Hojas de vida» (directorio), «Bienestar por estudiante», «Gestionar horarios», «Consultar horario».
- login_pro.css y landing_pro.css unidos a login.css y landing.css.
- Chart.js 4.4.1 local en assets/js/vendor (el CDN de respaldo quedó con versión fija).

## Pendiente (requiere probar visualmente en navegador)
- Pasar los ~118 colores hex sueltos a variables y unificar breakpoints (riesgo en modo oscuro).
- Decidir si la Secretaría de Educación debe ver hojas de vida individuales.
- Decidir si el orientador debe ver Caracterización y SIMPADE de todo el colegio.

## Landing y ayuda (segunda tanda)
- Portada reescrita: textos alineados con los permisos reales, tabla «Quién ve qué», caminos para
  estudiantes, familias y personal, y tarjeta «¿Cómo te sientes hoy?» que se puede probar (no guarda nada).
  Sin animaciones de aparición al hacer scroll. JS renombrado a `assets/js/landing.js`.
- La tabla «Quién ve qué» está en `public/index.php` ($filasPrivacidad): si cambian los permisos
  (por ejemplo, el acceso de la Secretaría a la hoja de vida), actualizarla también.
- Ayuda: buscador primero, temas frecuentes, asistente y descargas en un bloque compacto, Línea 106
  visible, barra pública arreglada (botón de modo oscuro) y pie con el autor.
- Manual 1.5: corregido quién ve salud y emociones; nuevos capítulos de asistencia (coordinador) y chat
  (secretaría académica); perfil para secretaría académica y acudiente; límites de «Ver como».
- `tools/capturar_ayuda.py` ya no deja el aviso de cookies en las capturas. Las imágenes actuales del
  manual todavía lo muestran: hay que volver a generarlas con el script.

## Permisos decididos (tercera tanda)
- Secretaría de Educación: ya no abre hojas de vida (estudiantes.php, estudiante_ficha.php,
  evidencia_ver.php). Solo cifras agrupadas; en traslados ve nombre, colegio y grupo.
- Orientador: Caracterización y SIMPADE (casos, detección, retiros y descargas CSV) solo con
  estudiantes de sus grupos. El panel de indicadores de SIMPADE sigue mostrando cifras del colegio.

## Experiencia (estudiante primero)
- Nuevos: `assets/css/experiencia.css` y `assets/js/experiencia.js`, cargados en todos los paneles.
- Color propio por módulo (`<body data-modulo>`) y por emoción; cabecera de módulo con icono.
- Movimiento: entrada escalonada de tarjetas y listas, barras que se llenan, cifras que cuentan,
  onda al tocar, llama animada en rachas de 3+ días, confeti al cumplir hábitos (✨) y metas (🎉).
- Sonidos nuevos en `sonidos.js`: elegir (nota distinta por opción), tic al deslizar, abrir/cerrar,
  navegar, enfoque y celebrar. El botón 🔊 los silencia todos.
- Accesibilidad: con «reducir movimiento» o alto contraste no hay animaciones ni confeti; los botones
  de emoción ahora se pueden elegir con teclado. En «Ayuda urgente» no hay celebraciones.

## Experiencia en los paneles del personal (cuarta tanda)
- Color por rol: psicología violeta, orientación verde, docente ámbar, coordinación coral, rectoría
  vino, administración azul pizarra, secretaría académica turquesa, acudiente azul, plataforma índigo.
- Saludo del panel con cabecera viva y etiqueta del rol; cifras con el color del rol.
- Gráficos: experiencia.js los recrea en una caja de alto fijo (los circulares ya no ocupan media
  pantalla), con el color del rol, animación de entrada y separadores acordes al modo oscuro.
- Tablas de personas: iniciales de color junto al nombre, fila resaltada con el color del rol,
  cabecera fija y entrada escalonada.
- Alertas: las de urgencia alta laten suavemente; tarjetas con entrada escalonada y respuesta al pasar.

## Administración y académico (quinta tanda)
- Cabecera con icono también en usuarios, grupos, años lectivos, plan académico, evaluación,
  inscripciones, matrículas, boletines, certificados, SIMAT, SIMPADE, horarios, configuración, etc.
- Dictado por voz: el micrófono ahora va dentro del campo (voz.js envuelve el campo en .voz-campo);
  antes ocupaba una línea propia bajo cada campo y alargaba los formularios.
- Casillas y opciones: base.css daba width:100% a todos los <input>, y cada casilla quedaba sola en su
  línea. Ahora miden 18 px, toman el color del rol y las opciones de evaluación se ven como tarjetas.
- Botones de peligro (Quitar, Retirar, Eliminar, Desactivar) con contorno en reposo y sólidos al pasar.
- Filas de edición (asignaturas, periodos, niveles) en una sola línea con «Quitar» al final; filtros en
  una fila dentro de una caja; acciones de las tablas compactas.
- Login: el ojo de «mostrar contraseña» se estiraba a todo el ancho del campo; corregido.

## Cuentas de familia desde la hoja de vida (sexta tanda)
- Nuevo `src/modelos/CuentasFamilia.php`. Al guardar Madre, Padre o Acudiente en Hoja de vida →
  Matrícula → Familia, el personal del colegio crea (o vincula) la cuenta de familia (rol acudiente)
  con el parentesco «Madre», «Padre», «Acudiente» o combinado («Madre y acudiente»).
- Misma persona = mismo documento; si falta el documento en alguno, mismo correo. Si el acudiente es
  la madre o el padre, no se crea otra cuenta. Madre y padre iguales: se rechaza al guardar.
- Reutiliza la cuenta de un hermano; no toca cuentas de otros roles (avisa); no crea cuenta para
  fallecidos ni sin correo/documento; sin correo, entra con su documento (correo interno @serena.local).
- Las credenciales se muestran una sola vez (imprimibles). Tabla «Cuentas de familia en Serena» en la
  pestaña Familia. Si quien guarda es el acudiente (ventana de actualización), no se crean cuentas.
- Manual 1.6: capítulo «Cuentas de familia desde la hoja de vida» (secretaría académica, administrador,
  coordinador).

## Cuentas con varios roles (séptima tanda)
- Migración `044_multirol.sql` (tabla usuario_roles) y modelo `src/modelos/RolesUsuario.php`.
- Una persona = una cuenta (mismo correo/documento) con varios roles del colegio. Al iniciar sesión,
  si tiene más de uno, `ingresar_como.php` pregunta «¿Con qué rol quieres entrar?»; luego puede
  cambiar desde Cuenta → «Cambiar de rol». Cada cambio queda en la auditoría.
- El rol principal es el de más prioridad (administrador > rector > coordinador > secretaría académica >
  psicólogo > orientador > docente > familia), así sigue apareciendo en listas y selectores.
- Las listas por rol (docentes, psicólogos, orientadores, responsables de SIMPADE, informes) incluyen a
  quien tiene ese rol como adicional.
- Se suma el rol en lugar de rechazar: hoja de vida (familia), matrícula desde inscripción, Crear usuario
  e Importar personal. En Editar usuario hay una sección «Roles de esta cuenta» para agregar y quitar.
- Nunca multirol: estudiante, superadmin y secretaría de educación. Sin la migración, todo funciona
  con un solo rol.
- Corregido: en Crear y Editar usuario el campo «Documento» quedaba dentro del atributo del correo
  (comilla sin cerrar) y no se mostraba.

## Multirol por documento y varias instituciones (octava tanda)
- La persona se reconoce primero por DOCUMENTO (en el colegio) y luego por correo
  (`RolesUsuario::cuentaDeLaPersona`). Crear usuario, Importar personal y Cuentas de familia suman el
  rol a la cuenta existente en vez de mostrar «Ya hay otra cuenta con ese documento en este colegio».
  Si el correo es de una cuenta y el documento de otra (o el mismo correo con otro documento), avisa.
- Mismo documento en varias instituciones: si la contraseña coincide en más de una, el login ya no
  falla: `elegir_colegio.php` pregunta «¿A qué institución quieres entrar?» (válido 5 minutos, solo para
  las cuentas que comprobaron la contraseña). Luego, si tiene varios roles, elige el rol.
- `AuthControlador::completarLogin()` agrupa los pasos posteriores a la contraseña (cuenta y colegio
  activos, verificación en 2 pasos, inicio de sesión).
- Cuentas de familia: si el correo del familiar es de otra institución, la cuenta de aquí se crea con
  correo interno y la persona entra con su documento.
- Unir cuentas: si al editar un usuario se pone un documento que ya es de otra cuenta del colegio, aparece
  «Unir en una sola cuenta». Se elige qué correo y contraseña se conservan; roles, estudiantes vinculados,
  grupos, mensajes, registros y autorías pasan a esa cuenta (`RolesUsuario::unirCuentas`, recorre las
  llaves foráneas hacia usuarios y las columnas de autoría) y la otra se elimina. Queda en la auditoría.
- Varias instituciones con contraseñas distintas: el login con documento ya no entra directo a la
  única cuya contraseña coincide; siempre muestra «¿A qué institución quieres entrar?». Las que
  comprobaron la contraseña entran con un toque; las demás piden su propia contraseña (con el mismo
  límite de intentos). Solo se muestra la lista si la contraseña coincidió en al menos una.

## Versión para GitHub (novena tanda)
- `sql/instalacion_completa.sql`: base completa (110 tablas) para instalaciones nuevas, generada aplicando
  schema.sql + todas las migraciones y probada desde cero con todos los roles. `schema.sql` solo NO bastaba
  (dejaba 44 tablas y 7 roles), aunque la documentación anterior decía lo contrario.
- `tools/crear_maestro.php`: crea la primera cuenta (superadmin maestra).
- `docs/INSTALACION.md` reescrita. Se quitó una instrucción peligrosa que cambiaba la contraseña de TODOS
  los usuarios (`UPDATE usuarios SET password_hash = … WHERE 1`).
- `docs/` ordenada: notas de cada fase en `docs/notas-de-desarrollo/`; presentación corregida en `docs/`.
- Textos de requisitos de contraseña corregidos (8 a 64 caracteres, no 8 a 12).
- README nuevo con capturas, diagramas, matriz de privacidad, arquitectura y hoja de ruta.
