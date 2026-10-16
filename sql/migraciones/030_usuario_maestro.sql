-- =========================================================
-- 030_usuario_maestro.sql
-- =========================================================
-- Marca a un usuario superadmin como "maestro": al iniciar sesión se le
-- pregunta con qué rol quiere entrar (public/elegir_rol.php).
-- Aplicar UNA vez, con respaldo previo.
-- =========================================================
ALTER TABLE usuarios
    ADD COLUMN es_maestro TINYINT(1) NOT NULL DEFAULT 0;
