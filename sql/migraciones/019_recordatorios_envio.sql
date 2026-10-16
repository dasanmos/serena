-- 019_recordatorios_envio.sql
-- El endpoint de cron público/tareas/recordatorios.php se llama cada 15 minutos
-- desde un servicio externo (cron-job.org). Sin esta columna no hay forma de saber
-- si un recordatorio ya se envió en esta ejecución de hoy, y como la hora guardada
-- en "hora" no cae siempre exacto en el minuto de la corrida (el cron pasa cada 15
-- min, la hora del usuario puede ser cualquier minuto), se compara por ventana y se
-- necesita marcar "ya se envió hoy" para no duplicar en la siguiente corrida.
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

ALTER TABLE recordatorios
    ADD COLUMN ultimo_envio DATE NULL AFTER activo;
