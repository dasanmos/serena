-- 023_pulso_grupo.sql
-- Pulso de grupo: check-in semanal del docente a todo su curso (una pregunta,
-- respuesta de 1 a 5, ~10 segundos). Deliberadamente NO es información emocional
-- individual — el docente solo ve el agregado (promedio y distribución), nunca
-- quién respondió qué. Ver src/modelos/Pulso.php.
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

CREATE TABLE pulsos_grupo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    grupo_id INT NOT NULL,
    pregunta VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NOT NULL,
    cerrado_manualmente TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    INDEX idx_pulso_grupo (grupo_id, fecha_cierre)
) ENGINE=InnoDB;

CREATE TABLE pulso_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pulso_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    valor TINYINT NOT NULL,
    fecha_respuesta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pulso_id) REFERENCES pulsos_grupo(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unico_pulso_estudiante (pulso_id, estudiante_id)
) ENGINE=InnoDB;
