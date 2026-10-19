-- =========================================================
-- 031_maestro_secretaria_medellin.sql
-- =========================================================
-- Ejecutar DESPUÉS de crear_maestro.php (el usuario debe existir).
-- Deja a dasanmos@gmail.com asociado a la Secretaría de Educación de Medellín,
-- para poder entrar con el rol "secretaria" desde el selector de rol.
-- Se puede ejecutar más de una vez.
-- =========================================================
INSERT INTO secretarias (nombre)
SELECT 'Secretaría de Educación de Medellín' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM secretarias WHERE nombre LIKE '%Medell%');

SET @sid := (SELECT id FROM secretarias WHERE nombre LIKE '%Medell%' ORDER BY id LIMIT 1);

INSERT IGNORE INTO secretaria_municipios (municipio, secretaria_id) VALUES ('Medellín', @sid);

UPDATE colegios SET secretaria_id = @sid
WHERE municipio = 'Medellín' AND secretaria_id IS NULL;

UPDATE usuarios SET secretaria_id = @sid WHERE correo = 'dasanmos@gmail.com';
