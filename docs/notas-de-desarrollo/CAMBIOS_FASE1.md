# Serena — Fase 1: correcciones de seguridad críticas

Aplicado sobre el archivo `serena.zip` auditado. Antes de desplegar: **ejecutar la migración SQL nueva** (ver abajo) y revisar el checklist de despliegue al final.

## 1. IDOR crítico en el chat (Sección 3.1 de la auditoría)
- `src/modelos/Mensaje.php`: nuevo método `perteneceAUsuario()`.
- `public/psicologo/chat.php` y `public/estudiante/chat.php`: verifican propiedad de la conversación antes de leer/escribir; devuelven 403 si no coincide.

## 2. IDOR + CSRF en borrado del diario (Sección 3.2)
- `Diario::eliminar()` ahora exige `usuario_id` y lo valida en el `WHERE`.
- El borrado dejó de ser un enlace `GET` y ahora es un `<form method="POST">` con token CSRF.

## 3. Inyección SQL en etiquetas del diario (Sección 3.3)
- `Diario::crear()`: `addslashes()` reemplazado por sentencia preparada.

## 4. IDOR en subtareas de metas (Sección 3.4)
- `Meta::toggleSubtarea()` ahora hace `JOIN` contra `metas.usuario_id` y no togglea nada si la subtarea no pertenece al usuario.
- Nuevo `Meta::metaIdDeSubtarea()`: la página nunca vuelve a confiar en el `meta_id` que llega por POST, siempre lo revalida contra la base de datos.
- `Meta::recalcularAvance()`: interpolación directa reemplazada por sentencias preparadas.

## 5. CSRF (Sección 3.5)
- Nuevo helper `src/helpers/csrf.php` (`tokenCSRF()`, `campoCSRF()`, `verificarTokenCSRF()`).
- Aplicado en: chat (estudiante y psicólogo/orientador), diario, metas, login, `administrador/usuarios.php` (activar/desactivar), `administrador/aprobaciones.php`, `administrador/resetear_password.php`, `psicologo/alertas.php`, `orientador/alertas.php`, PIN del diario.
- **Pendiente de aplicar el mismo patrón** (no crítico pero recomendado): registro, recuperación de contraseña, resto de formularios administrativos que aún no se tocaron en esta fase.

## 6. Auditoría nunca invocada (Sección 3.6)
- `auditarAccion()` ahora se llama realmente desde: activar/desactivar usuario, aprobar/rechazar cambio de dato, resetear contraseña por admin.
- **Pendiente**: invocarla también en creación de usuario individual/masiva y cambio de contraseña por el propio usuario.

## 7. Rate limiting (Sección 3.8) — nuevo
- Migración `sql/migraciones/002_limite_intentos.sql` añade 4 columnas a `usuarios`: `intentos_login_fallidos`, `login_bloqueado_hasta`, `intentos_pin_fallidos`, `pin_bloqueado_hasta`.
- Nuevo helper `src/helpers/limiteIntentos.php`.
- Login: 5 intentos fallidos → bloqueo de 15 minutos para esa cuenta.
- PIN del diario: mismo límite y bloqueo (espacio de búsqueda de solo 10.000 combinaciones, por eso es igual de estricto).
- **Nota de diseño**: el bloqueo es por cuenta, no por IP. No mitiga un atacante probando muchas cuentas distintas a la vez, pero sí detiene fuerza bruta contra una cuenta conocida, que era el vector documentado en la auditoría.

## 8. Interpolación SQL residual (Sección 3.9)
- `Encuesta.php`: los 4 métodos señalados (`preguntas`, `yaConcluida`, `usuarioYaRespondio`, `buscarPorId`) ahora usan sentencias preparadas.
- `Notificacion::contarNoLeidas()`: corregido el bug real (usaba el booleano de `execute()` como si fuera el conteo) y ya no interpola.
- `Auditoria::listar()`: `LIMIT` ahora se pasa con `bindValue(..., PDO::PARAM_INT)`.
- `administrador/aprobaciones.php`: la búsqueda de la solicitud ahora usa sentencia preparada; además la columna a actualizar sigue viniendo de una whitelist explícita (ya existía, se mantuvo).
- `docente/panel.php`: se agregó el cast a `int` que faltaba antes de interpolar `grupoId`.
- `estudiante/dashboard.php`: eliminada la segunda consulta redundante e insegura del conteo de metas activas.

## 9. Exposición de src/ y sql/ en GitHub (Sección 3.10)
- Nuevo `.gitignore` en la raíz, excluye `src/config/.env`.
- **Importante**: esto solo evita que el `.env` se suba de aquí en adelante. Si ya hiciste `git add`/`commit` de ese archivo en algún momento, necesitas purgarlo del historial (`git filter-repo` o BFG Repo-Cleaner) antes de subir el repo, aunque sea privado — el historial de git conserva el archivo igual.
- La regla del `.htaccess` raíz ya existía y se mantiene; sigue siendo la única defensa real en InfinityFree porque ese proveedor no permite fijar el document root a `public/`. **Antes de subir datos reales de estudiantes**, verifica manualmente que `https://tu-dominio-de-prueba/src/config/.env` devuelve 403.

## 10. Rol docente roto (Sección 2.4) — reescrito, no solo "arreglado"
El hallazgo original era que `docente/grupo.php` y `docente/estudiante_detalle.php` redirigían a páginas de orientador/psicólogo que rechazan el rol docente (403). Pero copiar esas páginas tal cual habría sido peor: le habría dado a cualquier docente acceso a **datos emocionales individuales**, que tu propio `.md` reserva explícitamente a psicólogo/orientador/administrador.

En su lugar:
- `docente/grupo.php`: lista real de estudiantes de su grupo — nombre, último acceso, conteo de metas y hábitos activos. **Sin niveles emocionales, sin sueño, sin diario.**
- `docente/estudiante_detalle.php`: detalle de un estudiante, pero **solo si pertenece al grupo del docente que hace la petición** (nuevo control de acceso — antes no existía verificación de grupo en absoluto). Muestra metas y hábitos, con una nota invitando a escalar a psicología/orientación si hay señales de alerta.

## 11. Navegación incompleta (hallazgo de auditorías previas)
`src/helpers/navegacion.php` ahora incluye los enlaces que ya existían como archivos pero no aparecían en el menú:
- Estudiante: Calendario, Encuestas, Notificaciones, Mi perfil.
- Administrador: Importar estudiantes, Resetear contraseña (ambas antes completamente huérfanas — sin este cambio esas páginas eran inalcanzables desde la interfaz).

## 12. CSS/JS desconectados (Sección 4.4–4.5)
- `public/parciales/encabezado.php`: ahora enlaza también `estudiante.css` y `chat.css` (antes solo `base.css` + `panel.css`).
- `public/parciales/pie.php`: ahora incluye `<script src="/assets/js/base.js">`, que activa el menú hamburguesa en móvil (ya estaba escrito, nunca se cargaba).
- `administrador.css` y `panel_apoyo.css` siguen vacíos — no había nada que conectar.

---

## Checklist de despliegue para esta fase
1. Ejecutar `sql/migraciones/002_limite_intentos.sql` contra la base de datos (además del `schema.sql` si es una instalación nueva — ya se actualizó también, así que una instalación desde cero no necesita la migración por separado).
2. Verificar que `.gitignore` esté en el repo **antes** del próximo commit que toque `src/config/.env`.
3. Probar en local: login con 5 contraseñas incorrectas seguidas → debe bloquear 15 min. Mismo con el PIN del diario.
4. Probar como docente: entrar a "Mi grupo" y a un estudiante de otro grupo por URL directa (`?id=`) → debe dar 403.
5. Probar como psicólogo: cambiar `?conv=` en la URL del chat a un ID de conversación ajena → debe dar 403.
6. Revisar visualmente el menú en una pantalla angosta (o DevTools en modo móvil) para confirmar que el hamburguesa aparece y funciona.

## Lo que queda pendiente (fuera del alcance de esta fase)
- 12 de 13 controladores en `src/controladores/` siguen vacíos (decisión de diseño documentada en el propio código; no es un bug, pero sigue el patrón de duplicar lógica entre páginas de distintos roles).
- La capa de API en `public/api/` sigue desconectada del frontend (7 de 9 endpoints son placeholders).
- Recuperación de contraseña por correo: el enlace solo se escribe en el log del servidor, no se envía correo real (falta decidir proveedor SMTP).
- Código muerto documentado en la auditoría: `EstadoEmocional::actualizar()` (edición de emociones no expuesta en la UI), `Habito::actualizar()` y `historialMensual()`, estado `'vencida'` de metas nunca asignado, tablas `diario_imagenes`, `recordatorios`, `insignias`, `usuario_insignias` sin código que las use.
- Sin mecanismo de tareas programadas (cron), por lo que recordatorios y notificaciones de "no registraste hoy" siguen sin ser técnicamente posibles.
- Accesibilidad, contraste WCAG y cobertura responsive (Sección 6 de la auditoría) no se tocaron en esta fase.
