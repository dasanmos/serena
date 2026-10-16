-- Notas de sesión privadas del psicólogo/orientador sobre un estudiante — solo
-- las ve quien las escribió (autor_id), nunca se exponen a otros roles ni al
-- propio estudiante.
CREATE TABLE IF NOT EXISTS notas_sesion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    autor_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    contenido TEXT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_notas_estudiante (estudiante_id, autor_id)
) ENGINE=InnoDB;

-- Para el resumen semanal por correo: guarda cuándo se envió el último, así se
-- puede "revisar y enviar si toca" en cada inicio de sesión (truco de cron sin
-- cron — InfinityFree no permite tareas programadas en el plan gratuito).
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ultimo_resumen_semanal DATETIME NULL;
