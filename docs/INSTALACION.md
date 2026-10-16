# SERENA — Instalación en XAMPP

## Requisitos
- XAMPP con PHP 8.1+ y MySQL 8+
- Apache con mod_rewrite habilitado

## Pasos

### 1. Copiar el proyecto
Copia la carpeta `serena/` dentro de `C:\xampp\htdocs\` (Windows) o `/Applications/XAMPP/htdocs/` (Mac).

### 2. Configurar la base de datos
1. Abre XAMPP Control Panel → Start **Apache** y **MySQL**
2. Ve a `http://localhost/phpmyadmin`
3. Crea una base de datos llamada `serena` (o déjalo vacío, el script la crea)
4. Ve a la pestaña **SQL** y ejecuta el contenido de `sql/schema.sql`

### 3. Configurar credenciales
Edita `src/config/.env`:
```
DB_HOST=localhost
DB_NOMBRE=serena
DB_USUARIO=root
DB_PASSWORD=          ← tu contraseña de MySQL (vacío si es XAMPP por defecto)
OPENROUTER_API_KEY=   ← opcional (openrouter.ai/keys); si se deja vacío, el análisis usa solo las plantillas fijas
```
Si tu base de datos es nueva, `schema.sql` ya incluye la tabla `analisis_cache`. Si ya tenías el proyecto instalado antes de esta función, aplica también `sql/migraciones/010_analisis_cache.sql` y `sql/migraciones/042_ia_registro.sql` (bitácora de IA) en phpMyAdmin. Diagnóstico: Administrador → `administrador/prueba_ia.php`.

#### Verificación en 2 pasos (opcional para cada persona)
- Base de datos nueva: `schema.sql` ya trae todo. Si ya tenías el proyecto instalado, ejecuta `sql/migraciones/013_verificacion_2_pasos.sql` en phpMyAdmin (una sola vez).
- Cada persona la activa en **Seguridad** (barra lateral). Funciona con Google Authenticator, Microsoft Authenticator, Authy y 2FAS.
- Los secretos se guardan cifrados (AES-256-GCM). Lo ideal es fijar tu propia clave en `src/config/.env`:
  `SERENA_2FA_KEY=` seguida de 64 caracteres hexadecimales (0-9, a-f). Si la dejas vacía, Serena crea sola el archivo `src/config/.clave_2fa` la primera vez. **Haz copia de esa clave**: si se pierde, nadie podrá usar su verificación y habrá que desactivarla usuario por usuario desde *Editar usuario*.
- Códigos de recuperación: 10 por juego, de un solo uso, vencen a los 30 días. Al gastarse todos o vencer, se crea un juego nuevo en el siguiente inicio de sesión.
- Si alguien pierde el teléfono y los códigos: administrador → *Usuarios* → *Editar* → **Desactivar verificación en 2 pasos**.

#### Otros métodos de verificación (correo, WhatsApp, reconocimiento facial)
Además de la app autenticadora, cada persona puede elegir **un** método en **Seguridad** (solo uno activo a la vez).
- Base de datos nueva: `schema.sql` ya trae todo. Si ya tenías el proyecto instalado, ejecuta en phpMyAdmin, en orden y una sola vez cada una: `014_verificacion_2fa_correo.sql`, `015_verificacion_2fa_whatsapp.sql` y `016_verificacion_2fa_rostro.sql` (carpeta `sql/migraciones/`).
- **Correo:** necesita `BREVO_API_KEY` en `src/config/.env` (el mismo que usa la recuperación de contraseña).
- **WhatsApp (CallMeBot, gratis):** cada persona activa su propia apikey desde su teléfono (Seguridad → WhatsApp explica los 4 pasos). La apikey se guarda cifrada con la misma clave de `SERENA_2FA_KEY`. Es un servicio gratuito sin garantía de entrega.
- **Reconocimiento facial:** el análisis ocurre en el navegador con face-api.js (se descarga de cdn.jsdelivr.net al tocar "Encender cámara"; requiere https). El servidor solo guarda 3 descriptores numéricos cifrados, nunca fotos. No hay detección de "vida": no lo trates como protección fuerte. Son datos biométricos (datos sensibles, Ley 1581 de 2012): revisa con la institución la política de tratamiento de datos y la autorización de acudientes para menores.
- Si alguien no puede pasar su verificación (perdió el teléfono, no llegan los códigos, la cámara no lo reconoce): administrador → *Usuarios* → *Editar* → **Desactivar verificación en 2 pasos** (desactiva cualquiera de los cuatro métodos y, en el caso del rostro, borra sus datos faciales).

### 4. Configurar Apache Virtual Host (opcional pero recomendado)
En `C:\xampp\apache\conf\extra\httpd-vhosts.conf` agrega:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/serena/public"
    ServerName serena.local
    <Directory "C:/xampp/htdocs/serena/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Y en `C:\Windows\System32\drivers\etc\hosts` agrega:
```
127.0.0.1    serena.local
```
Reinicia Apache. Accede a `http://serena.local`

### 5. Sin Virtual Host (acceso directo)
Si no configuras el Virtual Host, accede por:
`http://localhost/serena/public/`

### 6. Usuarios de prueba
Todos usan la contraseña: **serena2024**

| Correo | Rol |
|--------|-----|
| admin@serena.edu | Administrador |
| psicologo@serena.edu | Psicólogo |
| orientador@serena.edu | Orientador |
| estudiante@serena.edu | Estudiante |

> **Nota:** Si el login con `serena2024` no funciona (por ejemplo en una base sembrada
> con `password_hash` vacío), corre esto en phpMyAdmin — es el hash verificado y
> confirmado con `password_verify()` para esa contraseña:
> ```sql
> UPDATE usuarios SET password_hash = '$2y$10$9mbBHAPTjWPT3cRpKXzqduAmzwpJVfU/OUYRnD2pJ7uI0xHg/xwUS' WHERE 1;
> ```
> O regístrate con una cuenta nueva desde `/registro.php`.

### 8. Verificar que funciona
1. `http://localhost/serena/public/login.php` → debe mostrar la pantalla de login
2. Inicia sesión con admin@serena.edu / serena2024
3. Crea grados y grupos desde el Panel → Grupos
4. Registra estudiantes desde Grupos → Importar, o pídeles que se registren en `/registro.php`
