-- 003_semilla_insignias.sql
-- Semilla de las 3 insignias definidas en assets.md. El campo `criterio` es una
-- clave que Insignia::evaluarParaUsuario() interpreta en código PHP, no se evalúa en SQL.

ALTER TABLE insignias ADD CONSTRAINT criterio_unico UNIQUE (criterio);

INSERT INTO insignias (nombre, descripcion, icono, criterio) VALUES
    ('Racha de 7 días', 'Cumpliste un hábito 7 días seguidos.', 'insignia_racha_7dias.png', 'racha_habito_7'),
    ('Primer mes', 'Llevas un mes usando Serena.', 'insignia_primer_mes.png', 'primer_mes'),
    ('Meta completada', 'Completaste tu primera meta.', 'insignia_meta_completada.png', 'primera_meta_completada')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);
