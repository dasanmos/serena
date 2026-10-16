-- 016_verificacion_2fa_rostro.sql
-- Verificación en 2 pasos por reconocimiento facial. Igual que correo y
-- WhatsApp, es un método ALTERNATIVO: un usuario solo tiene UNO activo a la
-- vez, y comparte el contador de intentos fallidos / bloqueo
-- (intentos_2fa_fallidos, bloqueo_2fa_hasta en usuarios).
--
-- Qué se guarda: SOLO los descriptores faciales (3 vectores de 128 números que
-- calcula el navegador con face-api.js), cifrados con AES-256-GCM. Nunca se
-- guarda ni se envía una foto o video. Al desactivar el método, o al cambiar a
-- otro, los descriptores se borran.
--
-- Los datos biométricos son datos sensibles (Ley 1581 de 2012, Colombia):
-- rostro_2fa_consentimiento_en deja constancia de cuándo aceptó la persona.
--
-- Aplicar sobre una base de datos que ya tiene 015_verificacion_2fa_whatsapp.sql cargado.

ALTER TABLE usuarios
    ADD COLUMN rostro_2fa_plantillas TEXT NULL AFTER whatsapp_2fa_activado_en,
    ADD COLUMN rostro_2fa_activo TINYINT(1) NOT NULL DEFAULT 0 AFTER rostro_2fa_plantillas,
    ADD COLUMN rostro_2fa_activado_en DATETIME NULL AFTER rostro_2fa_activo,
    ADD COLUMN rostro_2fa_consentimiento_en DATETIME NULL AFTER rostro_2fa_activado_en;
