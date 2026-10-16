-- =========================================================
-- 043_documento_login.sql   (reejecutable)
-- =========================================================
-- Inicio de sesión con número de documento para todos los roles.
-- Agrega usuarios.documento (solo letras y números, en mayúscula, sin puntos ni guiones) y la llena
-- con lo que Serena ya tiene: la hoja de vida del estudiante y su inscripción. Docentes y administrativos
-- se completan al crear/editar el usuario (administrador > Usuarios) o con la columna opcional «documento» del CSV.
-- Requiere las migraciones 034 y 036 (hojas_vida e inscripciones).
-- No es único a propósito: la misma persona puede estar en dos colegios; el login lo resuelve con la contraseña.
-- =========================================================

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE usuarios ADD COLUMN documento VARCHAR(20) NULL',
    'SELECT ''usuarios.documento ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'documento');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE usuarios ADD INDEX idx_usuarios_documento (documento)',
    'SELECT ''idx_usuarios_documento ya existe: se omite'' AS aviso')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_documento');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- Estudiantes: desde la hoja de vida
UPDATE usuarios u
INNER JOIN hojas_vida h ON h.estudiante_id = u.id
SET u.documento = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(h.numero_documento), '.', ''), ' ', ''), '-', ''), ',', ''))
WHERE u.documento IS NULL AND h.numero_documento IS NOT NULL
  AND LENGTH(TRIM(h.numero_documento)) BETWEEN 4 AND 20;

-- Estudiantes matriculados por inscripción: desde la inscripción
UPDATE usuarios u
INNER JOIN inscripciones i ON i.estudiante_id = u.id
SET u.documento = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(i.numero_documento), '.', ''), ' ', ''), '-', ''), ',', ''))
WHERE u.documento IS NULL AND i.numero_documento IS NOT NULL
  AND LENGTH(TRIM(i.numero_documento)) BETWEEN 4 AND 20;
