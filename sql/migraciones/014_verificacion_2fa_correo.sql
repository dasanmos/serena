-- 014_verificacion_2fa_correo.sql
-- Verificación en 2 pasos por correo electrónico: se envía un código de 6 dígitos
-- al correo de la cuenta, vigente unos minutos. Es un segundo factor alternativo
-- a Google Authenticator (public/seguridad.php) — ambos pueden convivir, pero un
-- usuario solo tiene UNO activo a la vez (el que activó de último desactiva
-- automáticamente el otro, para no complicar el login con dos challenges).
-- Reutiliza el mismo contador de intentos fallidos / bloqueo que TOTP
-- (intentos_2fa_fallidos, bloqueo_2fa_hasta en usuarios).
-- Aplicar sobre una base de datos que ya tiene 013_verificacion_2_pasos.sql cargado.

ALTER TABLE usuarios
    ADD COLUMN correo_2fa_activo TINYINT(1) NOT NULL DEFAULT 0 AFTER bloqueo_2fa_hasta,
    ADD COLUMN correo_2fa_activado_en DATETIME NULL AFTER correo_2fa_activo;

-- Un solo código vivo por usuario a la vez: cada envío nuevo reemplaza (no
-- acumula) la fila anterior. Se guarda solo el hash — igual que los códigos de
-- recuperación de TOTP — el código en claro nunca se persiste, solo va en el correo.
CREATE TABLE codigos_2fa_correo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    creado_en DATETIME NOT NULL,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_codigos_2fa_correo_usuario (usuario_id)
) ENGINE=InnoDB;
