-- =========================================================
-- 037_horarios.sql   (reejecutable)
-- =========================================================
-- Horarios (EDUCORE · Tanda 2). Requiere 034 y 035 (años lectivos y asignaciones académicas).
--  · horario_franjas: las horas de timbre del colegio (1.ª hora 07:00-07:55, Descanso, ...).
--  · horario_bloques: una clase (asignación académica) en un día y una franja, con salón opcional.
--  Cruces que se impiden al guardar: mismo docente o mismo salón en dos grupos a la misma hora,
--  y clases en una franja de descanso. Un grupo solo puede tener una clase por celda (día × franja).
-- =========================================================

CREATE TABLE IF NOT EXISTS horario_franjas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    numero TINYINT UNSIGNED NOT NULL,                        -- orden dentro del día: 1, 2, 3...
    nombre VARCHAR(30) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    es_descanso TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY franja_unica (colegio_id, numero),
    CONSTRAINT fk_franjas_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS horario_bloques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    asignacion_id INT NOT NULL,
    dia TINYINT UNSIGNED NOT NULL,                           -- 1 lunes ... 6 sábado
    franja_id INT NOT NULL,
    salon VARCHAR(30) NULL,
    UNIQUE KEY bloque_unico (asignacion_id, dia, franja_id),
    INDEX idx_horario_celda (colegio_id, anio_lectivo_id, dia, franja_id),
    CONSTRAINT fk_bloques_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_bloques_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE,
    CONSTRAINT fk_bloques_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_bloques_franja FOREIGN KEY (franja_id) REFERENCES horario_franjas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
