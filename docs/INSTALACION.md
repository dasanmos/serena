# Instalación de Serena

Guía completa para instalar Serena en tu computador (Laragon o XAMPP) o publicarla en un hosting gratuito (InfinityFree).

- [Requisitos](#requisitos)
- [1. Instalación local](#1-instalación-local)
- [2. Crear la cuenta maestra](#2-crear-la-cuenta-maestra)
- [3. Primeros pasos dentro de Serena](#3-primeros-pasos-dentro-de-serena)
- [4. Publicar en InfinityFree](#4-publicar-en-infinityfree)
- [5. Variables de `.env`](#5-variables-de-env)
- [6. Tareas programadas](#6-tareas-programadas)
- [7. Verificación en 2 pasos](#7-verificación-en-2-pasos)
- [8. Actualizar una instalación existente](#8-actualizar-una-instalación-existente)
- [9. Problemas frecuentes](#9-problemas-frecuentes)

---

## Requisitos

| | Mínimo |
|---|---|
| PHP | 8.1 o superior, con `pdo_mysql`, `mbstring`, `openssl` y `gd` |
| Base de datos | MySQL 8 o MariaDB 10.6 o superior |
| Servidor web | Apache con `mod_rewrite` (los `.htaccess` del proyecto lo usan) |
| Opcional | Cuenta de [Brevo](https://www.brevo.com) para correos, claves de reCAPTCHA v2 y de [OpenRouter](https://openrouter.ai) para la IA |

---

## 1. Instalación local

1. **Copia el proyecto** a la carpeta web: `C:\laragon\www\serena` (Laragon) o `C:\xampp\htdocs\serena` (XAMPP).
2. **Crea la base de datos.** En phpMyAdmin crea una base vacía llamada `serena`, con cotejamiento `utf8mb4_unicode_ci`.
3. **Importa la estructura.** Con la base seleccionada, ve a **Importar** y elige **`sql/instalacion_completa.sql`**. Trae las 110 tablas de la versión actual y los datos mínimos: 11 roles, insignias y un colegio inicial con sus grados.

   > `sql/schema.sql` es solo la base original del proyecto. Para una instalación nueva usa siempre `instalacion_completa.sql`.

4. **Configura las credenciales.** Copia `src/config/.env.example` como `src/config/.env` y completa al menos la conexión:

   ```ini
   DB_HOST=localhost
   DB_NOMBRE=serena
   DB_USUARIO=root
   DB_PASSWORD=
   APP_URL=http://localhost/serena
   ```

5. **Abre el sitio** en `http://localhost/serena/`: la raíz redirige sola a `public/`.

---

## 2. Crear la cuenta maestra

La cuenta maestra es un superadministrador que puede entrar con cualquier rol y usar el modo demostración. Es el primer usuario de toda instalación nueva.

```bash
php tools/crear_maestro.php tu-correo@dominio.com "Tu Nombre" "Contraseña#Segura1"
```

La contraseña debe tener de 8 a 64 caracteres, con mayúscula, minúscula, número y carácter especial. Si el correo ya existe, el script actualiza esa cuenta.

**¿Hosting sin consola?** Ejecuta el comando en tu computador con el `.env` apuntando a la base remota. Otra opción es generar el hash localmente (`php -r "echo password_hash('TuClave#1', PASSWORD_DEFAULT);"`) e insertarlo desde phpMyAdmin:

```sql
INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, consentimiento_datos,
                      fecha_consentimiento, fecha_registro, es_maestro, activo)
VALUES ('Tu Nombre', 'tu-correo@dominio.com', 'PEGA_AQUI_EL_HASH',
        (SELECT id FROM roles WHERE nombre = 'superadmin'), NULL, 1, NOW(), NOW(), 1, 1);
```

---

## 3. Primeros pasos dentro de Serena

1. Entra por `public/login.php` con la cuenta maestra y elige **Superadministrador**.
2. **Crea tu colegio** en *Colegios*, con su código DANE de 12 dígitos, o edita el «Colegio principal» que viene incluido.
3. **Crea el administrador del colegio** en *Personal*. Desde su cuenta se crean grados, grupos, docentes y estudiantes; los estudiantes también pueden registrarse solos con el código DANE.
4. **¿Quieres verlo todo funcionando primero?** Ve a *Modo demostración* → **Preparar datos de demostración**. Se crea un colegio ficticio con 24 estudiantes, notas, asistencia y registros de bienestar, y puedes entrar con cualquiera de los 11 roles. Se restablece en un clic y no toca los datos reales.

---

## 4. Publicar en InfinityFree

1. Crea la cuenta y el sitio. Anota los datos de **MySQL** y de **FTP** (en el panel: *MySQL Databases* y *FTP Details*).
2. En **phpMyAdmin** del hosting, importa `sql/instalacion_completa.sql` en tu base (`if0_…`).
3. Sube por FTP (FileZilla: servidor `ftpupload.net`, puerto 21) **todo el proyecto** dentro de `htdocs/`. Deben quedar `htdocs/index.php`, `htdocs/public/`, `htdocs/src/`, etc.
4. Crea `htdocs/src/config/.env` con los datos de MySQL del hosting y:

   ```ini
   APP_URL=https://tu-sitio.infinityfreeapp.com
   ```

   `APP_URL` es **obligatorio en producción**: los enlaces de los correos y los QR de los certificados se arman con él (sin `/public`).
5. Crea la cuenta maestra (paso 2) y entra a `https://tu-sitio.infinityfreeapp.com`.

> InfinityFree no deja cambiar la raíz del sitio a `public/`. Por eso el `index.php` de la raíz redirige allí, y el `.htaccess` bloquea `src/`, `sql/`, `tools/`, `docs/` y los archivos `.env`, `.md`, `.sql` y `.txt`.

---

## 5. Variables de `.env`

| Variable | Obligatoria | Para qué sirve |
|---|:---:|---|
| `DB_HOST`, `DB_NOMBRE`, `DB_USUARIO`, `DB_PASSWORD` | ✅ | Conexión a la base de datos |
| `APP_URL` | ✅ en producción | Dominio público, sin `/public`, para enlaces de correos y QR |
| `BREVO_API_KEY`, `SMTP_DESDE`, `SMTP_DESDE_NOMBRE` | — | Correos: recuperar contraseña, verificación por correo, alertas, credenciales |
| `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY` | — | reCAPTCHA v2 en el inicio de sesión |
| `OPENROUTER_API_KEY` y `OPENROUTER_*` | — | IA: «Pregúntale a Serena» y análisis de informes. Sin clave se usan textos fijos |
| `SERENA_2FA_KEY` | Recomendada | 64 caracteres hexadecimales para cifrar la verificación en 2 pasos |
| `RECORDATORIOS_CRON_SECRETO`, `RESPALDO_CRON_SECRETO` | — | Secretos de las tareas programadas |

Sin las opcionales Serena funciona igual; solo se apagan esas funciones. **Nunca subas `.env` a GitHub**: el `.gitignore` ya lo excluye.

---

## 6. Tareas programadas

Con un servicio gratuito como [cron-job.org](https://cron-job.org):

| Tarea | URL | Frecuencia |
|---|---|---|
| Recordatorios a estudiantes | `https://tu-sitio/public/tareas/recordatorios.php?secreto=TU_SECRETO` | Cada 15 minutos |
| Respaldo automático | `https://tu-sitio/public/tareas/respaldo_automatico.php?secreto=TU_SECRETO` | Una vez al día |

Sin el secreto correcto, las dos responden 403 y no hacen nada.

---

## 7. Verificación en 2 pasos

- Cada persona la activa en **Seguridad**. Hay cuatro métodos, y solo uno activo a la vez:
  - **App autenticadora:** Google o Microsoft Authenticator, Authy, 2FAS.
  - **Correo:** requiere Brevo.
  - **WhatsApp:** CallMeBot, gratuito y sin garantía de entrega.
  - **Reconocimiento facial:** el análisis ocurre en el navegador y el servidor guarda solo descriptores cifrados, nunca fotos.
- Los secretos se cifran con AES-256-GCM usando `SERENA_2FA_KEY`. Si la dejas vacía, Serena crea `src/config/.clave_2fa`. **Haz copia de esa clave:** si se pierde, nadie podrá validar su verificación.
- Si alguien pierde el acceso: *Administrador → Usuarios → Editar → Desactivar verificación en 2 pasos*.
- El rostro es un dato biométrico, es decir, un dato sensible según la Ley 1581. Revisa la autorización con la institución antes de usarlo con menores.

---

## 8. Actualizar una instalación existente

1. **Haz un respaldo** (Superadmin → *Respaldos y ajustes*).
2. Reemplaza los archivos del proyecto, conservando tu `src/config/.env`.
3. En phpMyAdmin aplica **solo las migraciones que te falten**, en orden numérico. Algunos números están repetidos a propósito: [`sql/migraciones/ORDEN.md`](../sql/migraciones/ORDEN.md) dice cómo ejecutarlos.
4. Las más recientes:
   - `044_multirol.sql`: cuentas con varios roles. Quien es docente y padre, por ejemplo, entra con una sola cuenta y elige el rol.
   - `043_documento_login.sql`: inicio de sesión con número de documento.

> `004_datos_demo.sql`, `005_limpiar_todo.sql` y `006_reset_admin_y_nuevos.sql` **no** son migraciones de estructura: son scripts de demostración, limpieza y rescate. Nunca los ejecutes en producción sin leerlos.

---

## 9. Problemas frecuentes

| Síntoma | Solución |
|---|---|
| «No se pudo cargar esta sección» | Falta una migración: revisa *Superadmin → Salud del sistema*, que dice cuál. |
| Los enlaces de los correos apuntan a otro dominio | Falta `APP_URL` en `.env`. |
| No llegan los correos | Revisa `BREVO_API_KEY` y que el remitente (`SMTP_DESDE`) esté verificado en Brevo. |
| La IA no responde | Diagnóstico en *Superadmin → Diagnóstico de IA*. Sin clave, Serena usa textos fijos. |
| Página en blanco o error 500 | Revisa la versión de PHP (8.1 o superior) y que `src/config/.env` exista. |
| Olvidé la contraseña de la cuenta maestra | Vuelve a ejecutar `tools/crear_maestro.php` con el mismo correo. |
