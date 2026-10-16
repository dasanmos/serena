-- 029 · Secretarías de Educación (rol "secretaria"), separadas del superadmin.
--
--   superadmin  = equipo de Serena: administra TODA la plataforma y crea las secretarías.
--   secretaria  = usuarios de una Secretaría de Educación: ven y gestionan SOLO los colegios
--                 asignados a su secretaría (por municipio y/o colegio individual).
--
-- Una secretaría puede tener varios usuarios. Cada colegio pertenece a UNA secretaría
-- (colegios.secretaria_id, NULL = sin asignar, solo lo ve el superadmin).
-- secretaria_municipios guarda los municipios asignados: al asignar un municipio se
-- asignan todos sus colegios, y los colegios nuevos de ese municipio heredan la secretaría.
--
-- Ejecutar UNA sola vez (los ALTER no son idempotentes), después de 027 y 028.

INSERT INTO roles (nombre)
SELECT 'secretaria' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'secretaria');

CREATE TABLE IF NOT EXISTS secretarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY secretarias_nombre_unico (nombre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS secretaria_municipios (
    municipio VARCHAR(80) NOT NULL PRIMARY KEY,
    secretaria_id INT NOT NULL,
    FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE colegios
    ADD COLUMN secretaria_id INT NULL,
    ADD INDEX idx_colegios_secretaria (secretaria_id),
    ADD CONSTRAINT fk_colegios_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE SET NULL;

ALTER TABLE usuarios
    ADD COLUMN secretaria_id INT NULL,
    ADD INDEX idx_usuarios_secretaria (secretaria_id),
    ADD CONSTRAINT fk_usuarios_secretaria FOREIGN KEY (secretaria_id) REFERENCES secretarias(id) ON DELETE SET NULL;
