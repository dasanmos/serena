-- =========================================================
-- 033_territorio.sql   (reejecutable: solo agrega lo que falte)
-- =========================================================
-- Organización territorial de los colegios:
--   Secretaría (ETC)  →  Municipio  →  Núcleo educativo  →  Colegio
--                                  →  Zona (comuna / corregimiento / localidad / vereda)  →  Colegio
--
--  · secretarias.departamento / tipo (departamental | municipal | distrital).
--  · municipios: catálogo (departamento + nombre [+ código DANE]) y la secretaría que lo administra.
--  · nucleos_educativos: pertenecen a una secretaría (y opcionalmente a un municipio).
--  · zonas_municipio: comunas, corregimientos, localidades y veredas de un municipio.
--  · colegios.municipio_id / nucleo_id / zona_id (el texto colegios.municipio se conserva).
-- Ejecutar DESPUÉS de 029 (secretarias). Funciona en MySQL y MariaDB. Hacer respaldo antes.
-- =========================================================

CREATE TABLE IF NOT EXISTS municipios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    departamento VARCHAR(60) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    codigo_dane VARCHAR(5) NULL,
    secretaria_id INT NULL,
    UNIQUE KEY municipios_unico (departamento, nombre),
    INDEX idx_municipios_secretaria (secretaria_id),
    CONSTRAINT fk_municipios_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nucleos_educativos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    secretaria_id INT NOT NULL,
    municipio_id INT NULL,
    codigo VARCHAR(20) NULL,
    nombre VARCHAR(120) NOT NULL,
    UNIQUE KEY nucleos_unico (secretaria_id, nombre),
    INDEX idx_nucleos_municipio (municipio_id),
    CONSTRAINT fk_nucleos_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE CASCADE,
    CONSTRAINT fk_nucleos_municipio FOREIGN KEY (municipio_id) REFERENCES municipios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zonas_municipio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    municipio_id INT NOT NULL,
    tipo VARCHAR(15) NOT NULL DEFAULT 'comuna',      -- comuna | corregimiento | localidad | vereda
    nombre VARCHAR(100) NOT NULL,
    UNIQUE KEY zonas_unico (municipio_id, tipo, nombre),
    CONSTRAINT fk_zonas_municipio FOREIGN KEY (municipio_id) REFERENCES municipios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE secretarias ADD COLUMN departamento VARCHAR(60) NULL',
    'SELECT ''secretarias.departamento ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'secretarias' AND COLUMN_NAME = 'departamento');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE secretarias ADD COLUMN tipo VARCHAR(12) NOT NULL DEFAULT ''municipal''',
    'SELECT ''secretarias.tipo ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'secretarias' AND COLUMN_NAME = 'tipo');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE colegios ADD COLUMN municipio_id INT NULL',
    'SELECT ''colegios.municipio_id ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND COLUMN_NAME = 'municipio_id');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE colegios ADD COLUMN nucleo_id INT NULL',
    'SELECT ''colegios.nucleo_id ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND COLUMN_NAME = 'nucleo_id');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE colegios ADD COLUMN zona_id INT NULL',
    'SELECT ''colegios.zona_id ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND COLUMN_NAME = 'zona_id');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE colegios ADD CONSTRAINT fk_colegios_municipio FOREIGN KEY (municipio_id) REFERENCES municipios(id) ON DELETE SET NULL',
    'SELECT ''fk_colegios_municipio ya existe: se omite'' AS aviso')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND CONSTRAINT_NAME = 'fk_colegios_municipio');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE colegios ADD CONSTRAINT fk_colegios_nucleo FOREIGN KEY (nucleo_id) REFERENCES nucleos_educativos(id) ON DELETE SET NULL',
    'SELECT ''fk_colegios_nucleo ya existe: se omite'' AS aviso')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND CONSTRAINT_NAME = 'fk_colegios_nucleo');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE colegios ADD CONSTRAINT fk_colegios_zona FOREIGN KEY (zona_id) REFERENCES zonas_municipio(id) ON DELETE SET NULL',
    'SELECT ''fk_colegios_zona ya existe: se omite'' AS aviso')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND CONSTRAINT_NAME = 'fk_colegios_zona');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;
