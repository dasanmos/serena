-- =========================================================
-- 009_citas.sql
-- =========================================================
-- Módulo de citas para pedir hora con psicólogo u orientador.
--
-- Cómo funciona (definido con David): el profesional (psicólogo u orientador)
-- publica bloques de disponibilidad puntuales — "el lunes 8 de septiembre estoy
-- libre de 10:30 a 12:00, en turnos de 20 minutos" — y eso se parte automáticamente
-- en franjas reservables. El estudiante ve esas franjas y toma la que le sirva;
-- una vez tomada, desaparece para los demás. No es un horario fijo que se repite
-- solo: cada bloque se agrega a mano cuando el profesional sabe que va a estar
-- disponible, así que la agenda siempre refleja lo que el profesional puso.
-- =========================================================

USE serena;

CREATE TABLE disponibilidad_citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesional_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    duracion_slot_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profesional_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_disponibilidad_profesional_fecha (profesional_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesional_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    motivo VARCHAR(255) NULL,
    estado ENUM('confirmada', 'cancelada', 'atendida') NOT NULL DEFAULT 'confirmada',
    cancelado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profesional_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    -- Evita que dos estudiantes reserven la misma franja del mismo profesional aunque
    -- lleguen al mismo tiempo (la validación en PHP no alcanza a cubrir eso sola).
    UNIQUE KEY franja_unica (profesional_id, fecha, hora_inicio),
    INDEX idx_citas_estudiante (estudiante_id),
    INDEX idx_citas_profesional_fecha (profesional_id, fecha)
) ENGINE=InnoDB;
