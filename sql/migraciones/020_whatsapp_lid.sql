-- 020_whatsapp_lid.sql
-- CallMeBot ahora identifica algunas cuentas de WhatsApp por un "lid"
-- (identificador de usuario, formato "NUMEROLARGO@lid") en vez de por número de
-- teléfono — pasa con cuentas que muestran nombre de usuario de WhatsApp en
-- lugar de su número. whatsapp_2fa_numero pasa de VARCHAR(20) a VARCHAR(40)
-- para dar margen a esos identificadores (algunos más largos que un E.164).
-- Ver src/helpers/whatsapp.php (whatsappEsIdentificadorLid, whatsappNumeroValido).
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

ALTER TABLE usuarios
    MODIFY COLUMN whatsapp_2fa_numero VARCHAR(40) NULL;
