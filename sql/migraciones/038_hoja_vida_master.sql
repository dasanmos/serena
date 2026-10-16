-- =========================================================
-- 038_hoja_vida_master.sql   (reejecutable: solo agrega lo que falte)
-- =========================================================
-- Hoja de vida del estudiante con la estructura del Sistema Académico Master2000.
-- Requiere 036_expediente.sql. Hacer respaldo antes. Funciona en MySQL y MariaDB.
--  · hoja_vida_datos          campos adicionales de Matrícula / Familia / Características / Salud + observaciones
--  · familiares               madre, padre y acudiente (datos de identificación y trabajo)
--  · establecimientos_previos relación de establecimientos donde ha estudiado
--  · desarrollo_estudiante    desarrollo por semestre (14 criterios, tratamiento familiar e institucional)
--  · aprobaciones_anticipadas promoción anticipada
--  · observador_revisiones    revisiones del observador por coordinación
--  · hoja_vida_evidencias     imágenes de evidencia de anotaciones y procesos
--  · columnas nuevas en observador, procesos_disciplinarios y apoyo_seguimientos
-- Datos SENSIBLES de menores: el acceso se controla por rol en src/modelos/HojaVida.php.
-- =========================================================

CREATE TABLE IF NOT EXISTS hoja_vida_datos (
    estudiante_id INT NOT NULL,
    clave VARCHAR(40) NOT NULL,
    valor TEXT NULL,
    actualizado_por INT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (estudiante_id, clave),
    CONSTRAINT fk_hvd_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS familiares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    parentesco VARCHAR(12) NOT NULL,                         -- madre | padre | acudiente
    vive CHAR(1) NULL,                                       -- S | N
    tipo_documento VARCHAR(5) NULL,
    numero_documento VARCHAR(20) NULL,
    lugar_expedicion VARCHAR(100) NULL,
    fecha_expedicion DATE NULL,
    nacionalidad VARCHAR(40) NULL,
    apellido1 VARCHAR(40) NULL,
    apellido2 VARCHAR(40) NULL,
    nombre1 VARCHAR(40) NULL,
    nombre2 VARCHAR(40) NULL,
    fecha_nacimiento DATE NULL,
    telefono VARCHAR(20) NULL,
    celular VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    direccion VARCHAR(200) NULL,
    municipio VARCHAR(100) NULL,
    escolaridad VARCHAR(60) NULL,
    profesion VARCHAR(80) NULL,
    empresa VARCHAR(100) NULL,
    cargo VARCHAR(80) NULL,
    telefono_trabajo VARCHAR(20) NULL,
    actualizado_por INT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY familiar_unico (estudiante_id, parentesco),
    CONSTRAINT fk_fam_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS establecimientos_previos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    grado VARCHAR(30) NULL,
    anio SMALLINT NULL,
    municipio VARCHAR(100) NULL,
    dane VARCHAR(20) NULL,
    nombre VARCHAR(150) NOT NULL,
    director_grupo VARCHAR(120) NULL,
    causa_cambio VARCHAR(150) NULL,
    causa_reprobacion VARCHAR(150) NULL,
    INDEX idx_ep_estudiante (estudiante_id),
    CONSTRAINT fk_ep_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS desarrollo_estudiante (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    semestre TINYINT NOT NULL,                               -- 1 | 2
    criterio VARCHAR(30) NOT NULL,
    valoracion VARCHAR(12) NULL,                             -- muy_bueno | bueno | regular | deficiente
    tratamiento_familiar VARCHAR(300) NULL,
    tratamiento_institucional VARCHAR(300) NULL,
    actualizado_por INT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY desarrollo_unico (estudiante_id, anio_lectivo_id, semestre, criterio),
    CONSTRAINT fk_de_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_de_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS aprobaciones_anticipadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    anio_lectivo_id INT NULL,
    grado_origen_id INT NULL,
    grado_destino_id INT NULL,
    fecha DATE NOT NULL,
    acta VARCHAR(60) NULL,
    motivo TEXT NULL,
    registrado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aa_estudiante (estudiante_id),
    CONSTRAINT fk_aa_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_aa_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_aa_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE SET NULL,
    CONSTRAINT fk_aa_origen FOREIGN KEY (grado_origen_id) REFERENCES grados(id) ON DELETE SET NULL,
    CONSTRAINT fk_aa_destino FOREIGN KEY (grado_destino_id) REFERENCES grados(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS observador_revisiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha DATE NOT NULL,
    observaciones TEXT NOT NULL,
    coordinador_id INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_or_estudiante (estudiante_id, fecha),
    CONSTRAINT fk_or_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_or_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_or_coordinador FOREIGN KEY (coordinador_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hoja_vida_evidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    origen VARCHAR(10) NOT NULL,                             -- observador | proceso
    origen_id INT NOT NULL,
    archivo VARCHAR(60) NOT NULL,                            -- nombre generado en src/uploads/evidencias/
    subido_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hve_origen (origen, origen_id),
    INDEX idx_hve_estudiante (estudiante_id),
    CONSTRAINT fk_hve_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_hve_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Columnas nuevas (solo si faltan) ----------

-- observador.folio
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE observador ADD COLUMN folio INT NULL', 'SELECT ''observador.folio ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'observador' AND COLUMN_NAME = 'folio');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- observador.situacion (I | II | III)
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE observador ADD COLUMN situacion VARCHAR(3) NULL', 'SELECT ''observador.situacion ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'observador' AND COLUMN_NAME = 'situacion');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- observador.tratamiento_inmediato
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE observador ADD COLUMN tratamiento_inmediato VARCHAR(500) NULL', 'SELECT ''observador.tratamiento_inmediato ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'observador' AND COLUMN_NAME = 'tratamiento_inmediato');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- observador.pasado_proceso_id
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE observador ADD COLUMN pasado_proceso_id INT NULL', 'SELECT ''observador.pasado_proceso_id ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'observador' AND COLUMN_NAME = 'pasado_proceso_id');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- procesos_disciplinarios.folio
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE procesos_disciplinarios ADD COLUMN folio INT NULL', 'SELECT ''procesos_disciplinarios.folio ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'procesos_disciplinarios' AND COLUMN_NAME = 'folio');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- procesos_disciplinarios.tratamiento_inmediato
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE procesos_disciplinarios ADD COLUMN tratamiento_inmediato VARCHAR(500) NULL', 'SELECT ''procesos_disciplinarios.tratamiento_inmediato ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'procesos_disciplinarios' AND COLUMN_NAME = 'tratamiento_inmediato');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- apoyo_seguimientos.observacion
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE apoyo_seguimientos ADD COLUMN observacion VARCHAR(500) NULL', 'SELECT ''apoyo_seguimientos.observacion ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apoyo_seguimientos' AND COLUMN_NAME = 'observacion');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- ---------- Folios de lo que ya existe (solo filas sin folio; las anécdotas no llevan folio) ----------
UPDATE observador o
   SET o.folio = (SELECT COUNT(*) FROM (SELECT id, estudiante_id FROM observador WHERE tipo <> 'anecdota') x
                   WHERE x.estudiante_id = o.estudiante_id AND x.id <= o.id)
 WHERE o.tipo <> 'anecdota' AND o.folio IS NULL;

UPDATE procesos_disciplinarios p
   SET p.folio = (SELECT COUNT(*) FROM (SELECT id, estudiante_id FROM procesos_disciplinarios) x
                   WHERE x.estudiante_id = p.estudiante_id AND x.id <= p.id)
 WHERE p.folio IS NULL;
