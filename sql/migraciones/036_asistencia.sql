-- =========================================================
-- 036_asistencia.sql   (reejecutable)
-- =========================================================
-- Asistencia (EDUCORE · Tanda 1). Requiere 034 y 035 (matrículas y asignaciones académicas).
--  · asistencia_registros: una fila por estudiante, clase (asignación académica) y fecha.
--      estado: presente | tarde | ausente (sin justificar) | excusa (ausencia justificada) | permiso (salida autorizada)
--  · El docente toma lista de SUS asignaciones; coordinación, rectoría, secretaría académica y administración
--    consultan reportes del colegio; estudiantes y acudientes ven la asistencia propia.
-- =========================================================

CREATE TABLE IF NOT EXISTS asistencia_registros (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    asignacion_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha DATE NOT NULL,
    estado VARCHAR(10) NOT NULL DEFAULT 'presente',          -- presente | tarde | ausente | excusa | permiso
    observacion VARCHAR(255) NULL,
    registrado_por INT NULL,
    registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY asistencia_unica (asignacion_id, fecha, estudiante_id),
    INDEX idx_asistencia_estudiante (estudiante_id, anio_lectivo_id, fecha),
    INDEX idx_asistencia_colegio_fecha (colegio_id, anio_lectivo_id, fecha),
    CONSTRAINT fk_asist_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_asist_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE,
    CONSTRAINT fk_asist_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_asist_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_asist_registrador FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
