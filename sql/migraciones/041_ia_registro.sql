-- Bitácora del ecosistema IA (OpenRouter). Guarda qué modelo respondió, cuánto
-- tardó y el error si falló. NO guarda prompts ni respuestas (datos de menores).
-- Ver src/helpers/ia.php y public/administrador/prueba_ia.php.
CREATE TABLE IF NOT EXISTS ia_registro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tarea VARCHAR(40) NOT NULL,
    modelo VARCHAR(120) NOT NULL,
    ok TINYINT(1) NOT NULL DEFAULT 0,
    http_codigo SMALLINT NOT NULL DEFAULT 0,
    ms INT NOT NULL DEFAULT 0,
    error VARCHAR(255) NOT NULL DEFAULT '',
    INDEX idx_fecha (fecha_hora)
) ENGINE=InnoDB;
