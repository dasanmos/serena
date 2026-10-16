-- =========================================================
-- 036_expediente.sql   (reejecutable)
-- =========================================================
-- Expediente del estudiante y gestión docente (Tanda 3). Requiere 034 y 035.
--  · hojas_vida + contactos_emergencia: datos, salud y desarrollo del estudiante.
--  · observador: anotaciones (anecdotario, académicas, convivencia, seguimiento).
--  · procesos_disciplinarios + actuaciones_disciplinarias: situaciones tipo I, II, III (Ley 1620 de 2013).
--  · horas_formativas: servicio social (80 h) y horas de Constitución Política (50 h).
--  · apoyos_pedagogicos + apoyo_seguimientos: aula de apoyo / PIAR (Decreto 1421 de 2017).
--  · asistencias + inasistencias_periodo: asistencia diaria o resumen al final del período.
--  · planeaciones + diario_campo: planeador y diario de campo del docente.
-- Datos SENSIBLES de menores: el acceso se controla por rol en la aplicación (src/modelos/Expediente.php).
-- =========================================================

CREATE TABLE IF NOT EXISTS hojas_vida (
    estudiante_id INT NOT NULL PRIMARY KEY,
    colegio_id INT NOT NULL,
    tipo_documento VARCHAR(5) NULL,
    numero_documento VARCHAR(20) NULL,
    genero VARCHAR(20) NULL,
    lugar_nacimiento VARCHAR(100) NULL,
    direccion VARCHAR(200) NULL,
    barrio VARCHAR(80) NULL,
    telefono_alterno VARCHAR(20) NULL,
    vive_con VARCHAR(120) NULL,
    eps VARCHAR(80) NULL,
    grupo_sanguineo VARCHAR(4) NULL,
    alergias VARCHAR(255) NULL,
    condiciones_salud VARCHAR(500) NULL,
    medicamentos VARCHAR(255) NULL,
    desarrollo TEXT NULL,
    actualizado_por INT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hv_colegio (colegio_id),
    CONSTRAINT fk_hv_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_hv_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contactos_emergencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    parentesco VARCHAR(40) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    autorizado_recoger TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_ce_estudiante (estudiante_id),
    CONSTRAINT fk_ce_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS observador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    autor_id INT NULL,
    fecha DATE NOT NULL,
    tipo VARCHAR(12) NOT NULL DEFAULT 'anecdota',            -- anecdota | academica | convivencia | seguimiento
    descripcion TEXT NOT NULL,
    compromiso VARCHAR(500) NULL,
    visible_familia TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_obs_estudiante (estudiante_id, fecha),
    CONSTRAINT fk_obs_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_obs_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_obs_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS procesos_disciplinarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    abierto_por INT NULL,
    fecha DATE NOT NULL,
    situacion_tipo VARCHAR(3) NOT NULL DEFAULT 'I',          -- I | II | III
    descripcion TEXT NOT NULL,
    estado VARCHAR(12) NOT NULL DEFAULT 'abierto',           -- reportado | abierto | seguimiento | cerrado
    decision TEXT NULL,
    cerrado_en DATETIME NULL,
    cerrado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pd_estudiante (estudiante_id),
    INDEX idx_pd_colegio_estado (colegio_id, estado),
    CONSTRAINT fk_pd_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_abierto FOREIGN KEY (abierto_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_pd_cerrado FOREIGN KEY (cerrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS actuaciones_disciplinarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    autor_id INT NULL,
    fecha DATE NOT NULL,
    tipo VARCHAR(22) NOT NULL DEFAULT 'otra',
    detalle TEXT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ad_proceso (proceso_id),
    CONSTRAINT fk_ad_proceso FOREIGN KEY (proceso_id) REFERENCES procesos_disciplinarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS horas_formativas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    tipo VARCHAR(15) NOT NULL,                               -- servicio_social | constitucion
    fecha DATE NOT NULL,
    horas DECIMAL(5,1) NOT NULL,
    actividad VARCHAR(200) NOT NULL,
    entidad VARCHAR(120) NULL,
    registrado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hf_estudiante (estudiante_id, tipo),
    CONSTRAINT fk_hf_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_hf_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_hf_registro FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS apoyos_pedagogicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    estado VARCHAR(8) NOT NULL DEFAULT 'activo',             -- activo | cerrado
    condicion VARCHAR(255) NULL,                             -- diagnóstico / condición: SOLO orientación y directivos
    barreras TEXT NULL,
    ajustes TEXT NULL,                                       -- ajustes razonables: los ve el docente del estudiante
    estrategias TEXT NULL,
    responsable_id INT NULL,
    piar_fecha DATE NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY apoyo_estudiante_unico (estudiante_id),
    INDEX idx_ap_colegio (colegio_id, estado),
    CONSTRAINT fk_ap_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ap_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ap_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS apoyo_seguimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apoyo_id INT NOT NULL,
    autor_id INT NULL,
    fecha DATE NOT NULL,
    nota TEXT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_as_apoyo (apoyo_id),
    CONSTRAINT fk_as_apoyo FOREIGN KEY (apoyo_id) REFERENCES apoyos_pedagogicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_as_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asistencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asignacion_id INT NOT NULL,
    fecha DATE NOT NULL,
    estudiante_id INT NOT NULL,
    estado VARCHAR(8) NOT NULL DEFAULT 'presente',           -- presente | ausente | tarde | excusa
    observacion VARCHAR(150) NULL,
    registrado_por INT NULL,
    UNIQUE KEY asistencia_unica (asignacion_id, fecha, estudiante_id),
    INDEX idx_asist_estudiante (estudiante_id, fecha),
    CONSTRAINT fk_asist_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_asist_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_asist_registro FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inasistencias_periodo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asignacion_id INT NOT NULL,
    periodo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    ausencias SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    excusadas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY inasistencia_unica (asignacion_id, periodo_id, estudiante_id),
    CONSTRAINT fk_ip_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ip_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
    CONSTRAINT fk_ip_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS planeaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    docente_id INT NOT NULL,
    asignacion_id INT NOT NULL,
    periodo_id INT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    tema VARCHAR(150) NOT NULL,
    estandar VARCHAR(500) NULL,
    objetivos TEXT NULL,
    actividades TEXT NULL,
    recursos VARCHAR(500) NULL,
    evaluacion VARCHAR(500) NULL,
    estado VARCHAR(9) NOT NULL DEFAULT 'borrador',           -- borrador | listo | ejecutado
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plan_docente (docente_id, fecha_inicio),
    CONSTRAINT fk_plan_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_plan_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_plan_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_plan_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS diario_campo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    docente_id INT NOT NULL,
    asignacion_id INT NULL,
    planeacion_id INT NULL,
    fecha DATE NOT NULL,
    observaciones TEXT NOT NULL,
    dificultades TEXT NULL,
    ajustes TEXT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dc_docente (docente_id, fecha),
    CONSTRAINT fk_dc_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_dc_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_dc_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_academicas(id) ON DELETE SET NULL,
    CONSTRAINT fk_dc_planeacion FOREIGN KEY (planeacion_id) REFERENCES planeaciones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
