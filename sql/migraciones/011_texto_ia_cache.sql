-- Caché genérica por clave de texto (no por usuario): usada por el resumen de
-- encuestas y por los reportes institucionales, que se cachean por
-- pregunta/filtro en vez de por usuario. Ver src/helpers/texto_ia_cache.php.
CREATE TABLE IF NOT EXISTS texto_ia_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(191) NOT NULL,
    fecha DATE NOT NULL,
    texto TEXT NOT NULL,
    fecha_generado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY clave_fecha_unico (clave, fecha)
) ENGINE=InnoDB;
