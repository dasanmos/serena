-- 026_citas_recordatorio.sql
-- Marca de envío del recordatorio por correo de una cita (un día antes), para no
-- reenviarlo cada vez que corre el cron — mismo patrón que
-- recordatorios.ultimo_envio (migración 019). Reprogramar una cita resetea este
-- campo a 0, para que el recordatorio salga de nuevo con la fecha nueva.
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

ALTER TABLE citas
    ADD COLUMN recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;
