-- Caché diaria del párrafo de "Análisis de datos" por usuario. Un registro por
-- usuario_id + fecha: si ya existe uno para hoy, se reutiliza en vez de volver a
-- calcular/llamar a la API de Gemini en cada carga de página — imprescindible
-- para no agotar la cuota gratuita diaria con 1000+ estudiantes.
CREATE TABLE IF NOT EXISTS analisis_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    texto TEXT NOT NULL,
    fecha_generado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY usuario_fecha_unico (usuario_id, fecha)
) ENGINE=InnoDB;
