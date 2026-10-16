-- 015_verificacion_2fa_whatsapp.sql
-- Verificación en 2 pasos por WhatsApp, usando la API gratuita de CallMeBot
-- (https://www.callmebot.com). Igual que el 2FA por correo, es un método
-- ALTERNATIVO a los otros dos (TOTP, correo): un usuario solo tiene UNO activo
-- a la vez. Reutiliza el mismo contador de intentos fallidos / bloqueo
-- (intentos_2fa_fallidos, bloqueo_2fa_hasta en usuarios).
--
-- Particularidad de CallMeBot: NO es un servicio multiusuario "de una vez para
-- todos" — cada persona debe activar su PROPIA apikey enviando un WhatsApp
-- desde su propio teléfono al número de CallMeBot (ver Seguridad → WhatsApp en seguridad.php). Por eso
-- guardamos el número y la apikey de cada usuario, no una credencial global
-- como con Brevo (que sí es una sola cuenta para toda la plataforma).
--
-- Aplicar sobre una base de datos que ya tiene 014_verificacion_2fa_correo.sql cargado.

ALTER TABLE usuarios
    ADD COLUMN whatsapp_2fa_numero VARCHAR(20) NULL AFTER correo_2fa_activado_en,
    -- La apikey se guarda cifrada (mismo cifrado que el secreto TOTP), por eso 255.
    ADD COLUMN whatsapp_2fa_apikey VARCHAR(255) NULL AFTER whatsapp_2fa_numero,
    ADD COLUMN whatsapp_2fa_activo TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp_2fa_apikey,
    ADD COLUMN whatsapp_2fa_activado_en DATETIME NULL AFTER whatsapp_2fa_activo;

-- Igual que codigos_2fa_correo: un solo código vivo por usuario, se reemplaza
-- (no se acumula) en cada envío nuevo. Solo se guarda el hash.
CREATE TABLE codigos_2fa_whatsapp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    creado_en DATETIME NOT NULL,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_codigos_2fa_whatsapp_usuario (usuario_id)
) ENGINE=InnoDB;
