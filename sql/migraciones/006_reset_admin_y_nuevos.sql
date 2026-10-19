-- =========================================================
-- 006_reset_admin_y_nuevos.sql
-- =========================================================
-- 1) Desbloquea y restablece la cuenta admin@serena.edu (por si el problema
--    era el rate-limiting de login, o la clave nunca se había fijado bien).
-- 2) Crea/actualiza DOS cuentas de administrador nuevas, una para cada correo
--    real que diste, con la misma clave temporal.
--
-- CONTRASEÑA TEMPORAL para las 3 cuentas: serena2024
--
-- ⚠️ No pude generar un hash bcrypt distinto para cada cuenta nueva — este
-- entorno no tiene PHP ni acceso a internet para instalar una librería de
-- hashing, así que reutilicé el único hash que sé con certeza que funciona
-- (viene documentado del propio proyecto). Es temporal a propósito:
-- apenas entres, ve a "Resetear contraseña" en el panel de administrador,
-- selecciona tu propio nombre y ponte una clave personal. Esa sí la genera
-- PHP real en el momento, sin depender de mí.
-- =========================================================

-- ⚠️ CORRECCIÓN DE SEGURIDAD: antes este archivo traía un hash fijo que en realidad
-- correspondía a la clave «password» (no «serena2024» como decía el comentario).
-- Ahora trae un marcador que NO sirve para entrar. Antes de ejecutarlo, genera tu
-- propio hash y reemplaza REEMPLAZAR_CON_HASH_GENERADO (3 veces):
--   php -r "echo password_hash('TU_CLAVE_TEMPORAL', PASSWORD_DEFAULT), PHP_EOL;"
-- Mejor aún: usa «¿Olvidaste tu contraseña?» en el login si el correo (Brevo) funciona.

USE serena;

-- 1) Desbloquear/restablecer admin@serena.edu
UPDATE usuarios SET
    password_hash = 'REEMPLAZAR_CON_HASH_GENERADO',
    intentos_login_fallidos = 0,
    login_bloqueado_hasta = NULL,
    activo = 1
WHERE correo = 'admin@serena.edu';

-- 2) Dos cuentas de administrador nuevas, una por correo real
INSERT INTO usuarios (nombre, correo, password_hash, rol_id, consentimiento_datos, fecha_consentimiento, fecha_registro)
VALUES
    ('Sofía', 'anitta.sofia88@gmail.com',
     'REEMPLAZAR_CON_HASH_GENERADO',
     (SELECT id FROM roles WHERE nombre = 'administrador'), 1, NOW(), NOW()),
    ('Administrador', 'dasanmos@gmail.com',
     'REEMPLAZAR_CON_HASH_GENERADO',
     (SELECT id FROM roles WHERE nombre = 'administrador'), 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    intentos_login_fallidos = 0,
    login_bloqueado_hasta = NULL,
    activo = 1;

-- =========================================================
-- Cuentas listas, todas con la clave temporal cuyo hash pusiste arriba:
--   admin@serena.edu
--   anitta.sofia88@gmail.com   (nombre puesto como "Sofía" — cámbialo si
--                                el nombre real es otro, vía Usuarios > Editar)
--   dasanmos@gmail.com
--
-- SIGUIENTE PASO OBLIGATORIO: entra con cualquiera de las 3, ve a
-- administrador/resetear_password.php, elige tu propio nombre en la lista,
-- y ponte una clave personal real. No hay forma de hacerlo más segura desde
-- mi lado sin PHP corriendo de verdad.
-- =========================================================
