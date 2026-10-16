-- =========================================================
-- 007_chat_personal.sql
-- =========================================================
-- Generaliza conversaciones de "solo estudiante+profesional" a "cualquier
-- par de usuarios" para permitir chat entre todo el personal (psicólogo,
-- orientador, docente, coordinador, rector, administrador) entre sí.
--
-- Los estudiantes SIGUEN hablando solo con psicólogo/orientador — eso no
-- cambia, se sigue validando en el código (Mensaje::iniciarConversacion).
-- Nunca estudiante con estudiante, ni estudiante con docente/coordinador/
-- rector/administrador (romperia la privacidad de datos emocionales).
-- =========================================================

USE serena;

ALTER TABLE conversaciones
    CHANGE COLUMN estudiante_id usuario_a_id INT NOT NULL,
    CHANGE COLUMN profesional_id usuario_b_id INT NOT NULL;

-- El UNIQUE KEY viejo quedó con el nombre de las columnas anteriores; se recrea.
ALTER TABLE conversaciones DROP INDEX conversacion_unica;
ALTER TABLE conversaciones ADD UNIQUE KEY conversacion_unica (usuario_a_id, usuario_b_id);
