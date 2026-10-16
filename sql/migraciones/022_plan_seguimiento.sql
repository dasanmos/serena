-- 022_plan_seguimiento.sql
-- Plan de seguimiento del psicólogo/orientador: tareas de control por estudiante,
-- con fecha límite y estado (pendiente/en_curso/completado) — a diferencia de
-- notas_sesion, que es solo texto libre sin seguimiento de estado ni fecha.
-- Privado por autor, igual que notas_sesion: cada profesional ve únicamente sus
-- propias tareas sobre un estudiante, no las de sus colegas.
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

CREATE TABLE planes_seguimiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    autor_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NULL,
    fecha_limite DATE NULL,
    estado ENUM('pendiente', 'en_curso', 'completado') NOT NULL DEFAULT 'pendiente',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_completado DATETIME NULL,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_plan_autor (autor_id, estado),
    INDEX idx_plan_estudiante (estudiante_id, autor_id)
) ENGINE=InnoDB;
