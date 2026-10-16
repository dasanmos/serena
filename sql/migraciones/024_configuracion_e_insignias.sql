-- 024_configuracion_e_insignias.sql
-- 1) Tabla clave/valor para ajustes editables por el administrador desde la
--    plataforma (hoy: el lema y el pie de los correos — ver src/helpers/configuracion.php).
-- 2) Seis insignias nuevas. El campo `criterio` lo interpreta evaluarInsignias()
--    en src/helpers/gamificacion.php (no se evalúa en SQL).
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(60) NOT NULL PRIMARY KEY,
    valor TEXT NOT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO insignias (nombre, descripcion, icono, criterio) VALUES
    ('Racha de 30 días', 'Cumpliste un hábito 30 días seguidos.', 'insignia_racha_30dias.png', 'racha_habito_30'),
    ('Primera entrada', 'Escribiste tu primera entrada en el diario.', 'insignia_primer_diario.png', 'primer_diario'),
    ('Diez emociones', 'Registraste 10 veces cómo te sentías.', 'insignia_10_emociones.png', 'emociones_10'),
    ('Siete noches', 'Registraste tu sueño 7 veces.', 'insignia_7_noches.png', 'sueno_7_registros'),
    ('Cinco metas', 'Completaste 5 metas.', 'insignia_5_metas.png', 'metas_5'),
    ('Tres meses', 'Llevas tres meses usando Serena.', 'insignia_tres_meses.png', 'tres_meses')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);
