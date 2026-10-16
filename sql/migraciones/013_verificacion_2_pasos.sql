-- 013_verificacion_2_pasos.sql
-- Verificación en 2 pasos (TOTP: Google Authenticator, Authy, 2FAS, etc.) más
-- códigos de recuperación de un solo uso. Es opcional: cada persona la activa
-- desde "Seguridad" (public/seguridad.php).
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

ALTER TABLE usuarios
    ADD COLUMN totp_secreto VARCHAR(255) NULL AFTER pin_bloqueado_hasta,
    ADD COLUMN totp_activo TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secreto,
    ADD COLUMN totp_ultimo_paso BIGINT NULL AFTER totp_activo,
    ADD COLUMN totp_activado_en DATETIME NULL AFTER totp_ultimo_paso,
    ADD COLUMN intentos_2fa_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER totp_activado_en,
    ADD COLUMN bloqueo_2fa_hasta DATETIME NULL AFTER intentos_2fa_fallidos;

-- Un "lote" son los códigos creados a la vez (10). Todos comparten expira_en
-- (30 días). Solo se guarda el hash bcrypt: el código en claro se muestra una
-- única vez, al generarse. Cuando se gastan todos o vencen, el sistema crea un
-- lote nuevo en el siguiente inicio de sesión.
CREATE TABLE codigos_recuperacion_2fa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    creado_en DATETIME NOT NULL,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_codigos_2fa_usuario (usuario_id, usado_en, expira_en)
) ENGINE=InnoDB;
