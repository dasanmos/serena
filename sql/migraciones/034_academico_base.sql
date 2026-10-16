-- =========================================================
-- 034_academico_base.sql   (reejecutable)
-- =========================================================
-- Base académica (Tanda 1):
--   · Roles nuevos: secretaria_academica (secretaría DEL COLEGIO) y acudiente (padre/madre/cuidador).
--     (rector y orientador ya existían; "secretaria" sigue siendo la Secretaría de EDUCACIÓN.)
--   · anios_lectivos y periodos (por colegio).
--   · inscripciones: solicitudes que llegan por el formulario web público.
--   · matriculas: nueva o renovación, por año lectivo.
--   · acudientes_estudiantes: vínculo acudiente ↔ estudiante.
-- Ejecutar DESPUÉS de 027 (multicolegio). Hacer respaldo antes.
-- =========================================================

INSERT IGNORE INTO roles (nombre) VALUES ('secretaria_academica'), ('acudiente');

CREATE TABLE IF NOT EXISTS anios_lectivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio SMALLINT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    inscripciones_abiertas TINYINT(1) NOT NULL DEFAULT 0,   -- el formulario público de inscripción acepta solicitudes
    renovacion_abierta TINYINT(1) NOT NULL DEFAULT 0,       -- los acudientes pueden solicitar la renovación de matrícula
    actual TINYINT(1) NOT NULL DEFAULT 0,                   -- año en curso del colegio (solo uno)
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY anio_colegio_unico (colegio_id, anio),
    CONSTRAINT fk_anios_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS periodos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anio_lectivo_id INT NOT NULL,
    numero TINYINT UNSIGNED NOT NULL,
    nombre VARCHAR(40) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    peso DECIMAL(5,2) NOT NULL DEFAULT 25.00,               -- % del año; se usará al calcular la nota final
    UNIQUE KEY periodo_unico (anio_lectivo_id, numero),
    CONSTRAINT fk_periodos_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    grado_id INT NOT NULL,                                  -- grado al que aspira
    codigo VARCHAR(12) NOT NULL,                            -- código de seguimiento que recibe el acudiente
    estado VARCHAR(15) NOT NULL DEFAULT 'pendiente',        -- pendiente | en_revision | admitida | rechazada | matriculada
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    tipo_documento VARCHAR(5) NOT NULL,                     -- RC | TI | CC | CE | PPT | NUIP
    numero_documento VARCHAR(20) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    correo_estudiante VARCHAR(150) NULL,
    direccion VARCHAR(200) NULL,
    colegio_anterior VARCHAR(150) NULL,
    acudiente_nombre VARCHAR(120) NOT NULL,
    acudiente_parentesco VARCHAR(30) NOT NULL,
    acudiente_documento VARCHAR(20) NOT NULL,
    acudiente_correo VARCHAR(150) NOT NULL,
    acudiente_telefono VARCHAR(20) NOT NULL,
    observaciones VARCHAR(500) NULL,
    consentimiento TINYINT(1) NOT NULL DEFAULT 0,
    consentimiento_en DATETIME NULL,
    ip_hash CHAR(64) NULL,
    nota_resolucion VARCHAR(255) NULL,
    resuelto_por INT NULL,
    resuelto_en DATETIME NULL,
    estudiante_id INT NULL,                                 -- usuario creado al matricular
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY inscripcion_codigo_unico (codigo),
    UNIQUE KEY inscripcion_documento_anio (anio_lectivo_id, tipo_documento, numero_documento),
    INDEX idx_inscripciones_colegio_estado (colegio_id, estado),
    INDEX idx_inscripciones_ip (ip_hash, creado_en),
    CONSTRAINT fk_insc_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_insc_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE,
    CONSTRAINT fk_insc_grado FOREIGN KEY (grado_id) REFERENCES grados(id) ON DELETE RESTRICT,
    CONSTRAINT fk_insc_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS matriculas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    grado_id INT NOT NULL,
    grupo_id INT NULL,
    tipo VARCHAR(12) NOT NULL DEFAULT 'nueva',              -- nueva | renovacion
    estado VARCHAR(12) NOT NULL DEFAULT 'pendiente',        -- pendiente | activa | rechazada | retirada
    inscripcion_id INT NULL,
    solicitado_por INT NULL,
    observaciones VARCHAR(255) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resuelto_en DATETIME NULL,
    UNIQUE KEY matricula_unica (anio_lectivo_id, estudiante_id),
    INDEX idx_matriculas_colegio_estado (colegio_id, estado),
    CONSTRAINT fk_mat_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_mat_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE,
    CONSTRAINT fk_mat_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_mat_grado FOREIGN KEY (grado_id) REFERENCES grados(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mat_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acudientes_estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    acudiente_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    parentesco VARCHAR(30) NOT NULL DEFAULT 'Acudiente',
    principal TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY acudiente_estudiante_unico (acudiente_id, estudiante_id),
    CONSTRAINT fk_ae_acudiente FOREIGN KEY (acudiente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ae_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
