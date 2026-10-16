-- =========================================================
-- 032_mapas.sql   (reejecutable: solo agrega lo que falte)
-- =========================================================
-- 1) Ubicación de los colegios (mapa para superadmin y secretaría): direccion, latitud, longitud.
--    Si "direccion" ya existe en tu base, se conserva tal cual (y sus datos) y se omite.
-- 2) Lugares de ayuda para el mapa del estudiante (Recursos de apoyo):
--    colegio_id NULL = lugar global (lo carga el superadmin); con valor = propio del colegio.
-- Funciona en MySQL y MariaDB. Hacer respaldo antes.
-- =========================================================

-- direccion
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE colegios ADD COLUMN direccion VARCHAR(200) NULL AFTER municipio',
    'SELECT ''direccion ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND COLUMN_NAME = 'direccion');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- latitud
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE colegios ADD COLUMN latitud DECIMAL(9,6) NULL AFTER direccion',
    'SELECT ''latitud ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND COLUMN_NAME = 'latitud');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- longitud
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE colegios ADD COLUMN longitud DECIMAL(9,6) NULL AFTER latitud',
    'SELECT ''longitud ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND COLUMN_NAME = 'longitud');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- Lugares de ayuda
CREATE TABLE IF NOT EXISTS lugares_apoyo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(30) NOT NULL DEFAULT 'otro',      -- salud_mental | hospital | casa_justicia | icbf | comisaria | otro
    direccion VARCHAR(200) NULL,
    municipio VARCHAR(80) NULL,
    telefono VARCHAR(40) NULL,
    horario VARCHAR(120) NULL,
    latitud  DECIMAL(9,6) NOT NULL,
    longitud DECIMAL(9,6) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lugares_colegio (colegio_id),
    CONSTRAINT fk_lugares_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
