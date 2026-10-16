-- =========================================================
-- 038_codigo_matricula.sql   (reejecutable)
-- =========================================================
-- Numero de matricula del estudiante (columna "Codigo" de Seguimiento): un consecutivo por colegio, fijo para toda
-- la vida del estudiante en el colegio. Se asigna solo (en orden de id) al matricular y, para los que ya existen,
-- la primera vez que alguien abre Seguimiento (Academico::asegurarCodigosMatricula).
-- =========================================================

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE usuarios ADD COLUMN codigo_matricula INT UNSIGNED NULL',
    'SELECT ''usuarios.codigo_matricula ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'codigo_matricula');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE usuarios ADD UNIQUE INDEX uq_usuarios_codigo_matricula (colegio_id, codigo_matricula)',
    'SELECT ''uq_usuarios_codigo_matricula ya existe: se omite'' AS aviso')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'uq_usuarios_codigo_matricula');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;
