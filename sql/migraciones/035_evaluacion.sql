-- =========================================================
-- 035_evaluacion.sql   (reejecutable)
-- =========================================================
-- Evaluación (Tanda 2). Requiere 034 (años lectivos y períodos).
--  · configuracion_evaluacion: estructura de la planilla del colegio:
--      actividades | procesos | logros | procesos_logros | logros_procesos
--  · escalas_evaluacion + escala_niveles: numérica (1.0-5.0, 0-100...) o descriptiva (Superior/Alto/Básico/Bajo).
--  · componentes_evaluacion: «procesos» con su peso (cognitivo, procedimental, actitudinal...).
--  · asignaturas y asignaciones_academicas (docente + asignatura + grupo + año).
--  · logros, actividades_evaluacion y calificaciones (la planilla de notas).
--  · notas_periodo: nota calculada por estudiante, asignación y período (+ observación del docente).
--  · periodos.boletin_publicado: hasta que no se publique, estudiantes y acudientes NO ven las notas.
-- =========================================================

CREATE TABLE IF NOT EXISTS escalas_evaluacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    tipo VARCHAR(12) NOT NULL DEFAULT 'numerica',            -- numerica | descriptiva
    valor_min DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    valor_max DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    decimales TINYINT UNSIGNED NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_escalas_colegio (colegio_id),
    CONSTRAINT fk_escalas_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS escala_niveles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escala_id INT NOT NULL,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
    nombre VARCHAR(40) NOT NULL,
    abreviatura VARCHAR(6) NOT NULL,
    desde DECIMAL(5,2) NOT NULL,
    hasta DECIMAL(5,2) NOT NULL,
    valor_equivalente DECIMAL(5,2) NOT NULL,                  -- valor numérico para promediar (en escalas descriptivas)
    aprueba TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_niveles_escala FOREIGN KEY (escala_id) REFERENCES escalas_evaluacion(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS configuracion_evaluacion (
    colegio_id INT NOT NULL PRIMARY KEY,
    estructura VARCHAR(20) NOT NULL DEFAULT 'actividades',
    escala_id INT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_confeval_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_confeval_escala FOREIGN KEY (escala_id) REFERENCES escalas_evaluacion(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS componentes_evaluacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    peso DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
    INDEX idx_comp_colegio (colegio_id),
    CONSTRAINT fk_comp_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asignaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    area VARCHAR(80) NULL,
    intensidad TINYINT UNSIGNED NULL,                         -- horas semanales (peso en el promedio general si todas la tienen)
    activa TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY asignatura_unica (colegio_id, nombre),
    CONSTRAINT fk_asig_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asignaciones_academicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    grupo_id INT NOT NULL,
    asignatura_id INT NOT NULL,
    docente_id INT NULL,
    UNIQUE KEY asignacion_unica (anio_lectivo_id, grupo_id, asignatura_id),
    INDEX idx_asignaciones_docente (docente_id, anio_lectivo_id),
    CONSTRAINT fk_asgn_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_asgn_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE,
    CONSTRAINT fk_asgn_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    CONSTRAINT fk_asgn_asignatura FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE CASCADE,
    CONSTRAINT fk_asgn_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS logros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asignacion_id INT NOT NULL,
    periodo_id INT NOT NULL,
    descripcion VARCHAR(500) NOT NULL,
    peso DECIMAL(5,2) NOT NULL DEFAULT 0.00,                  -- 0 = todos los logros pesan igual
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
    INDEX idx_logros_asignacion (asignacion_id, periodo_id),
    CONSTRAINT fk_logros_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_logros_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS actividades_evaluacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asignacion_id INT NOT NULL,
    periodo_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    peso DECIMAL(5,2) NOT NULL DEFAULT 0.00,                  -- 0 = todas pesan igual
    componente_id INT NULL,
    logro_id INT NULL,
    fecha DATE NULL,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
    INDEX idx_act_asignacion (asignacion_id, periodo_id),
    CONSTRAINT fk_act_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_act_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
    CONSTRAINT fk_act_componente FOREIGN KEY (componente_id) REFERENCES componentes_evaluacion(id) ON DELETE SET NULL,
    CONSTRAINT fk_act_logro FOREIGN KEY (logro_id) REFERENCES logros(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS calificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    valor DECIMAL(5,2) NULL,
    nivel_id INT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY calificacion_unica (actividad_id, estudiante_id),
    CONSTRAINT fk_cal_actividad FOREIGN KEY (actividad_id) REFERENCES actividades_evaluacion(id) ON DELETE CASCADE,
    CONSTRAINT fk_cal_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_cal_nivel FOREIGN KEY (nivel_id) REFERENCES escala_niveles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notas_periodo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asignacion_id INT NOT NULL,
    periodo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    valor DECIMAL(5,2) NULL,
    nivel_id INT NULL,
    observacion VARCHAR(500) NULL,
    calculado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY nota_periodo_unica (asignacion_id, periodo_id, estudiante_id),
    INDEX idx_notas_estudiante (estudiante_id, periodo_id),
    CONSTRAINT fk_np_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_np_periodo FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE,
    CONSTRAINT fk_np_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_np_nivel FOREIGN KEY (nivel_id) REFERENCES escala_niveles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- periodos.boletin_publicado (solo si falta)
SET @sql = (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE periodos ADD COLUMN boletin_publicado TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT ''periodos.boletin_publicado ya existe: se omite'' AS aviso')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'periodos' AND COLUMN_NAME = 'boletin_publicado');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;
