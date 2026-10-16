-- 002_limite_intentos.sql
-- Añade rate limiting a login y al PIN del diario personal.
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

ALTER TABLE usuarios
    ADD COLUMN intentos_login_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER pin_diario_hash,
    ADD COLUMN login_bloqueado_hasta DATETIME NULL AFTER intentos_login_fallidos,
    ADD COLUMN intentos_pin_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER login_bloqueado_hasta,
    ADD COLUMN pin_bloqueado_hasta DATETIME NULL AFTER intentos_pin_fallidos;
