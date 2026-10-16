-- =========================================================
-- 027_multicolegio.sql  (Fase 3: multi-colegio)
-- =========================================================
-- Convierte Serena en una plataforma con varios colegios (tenants) sobre una
-- sola base de datos. Reglas:
--   * Todo usuario pertenece a UN colegio (usuarios.colegio_id). Solo el rol
--     nuevo "superadmin" (equipo de Serena / Secretaría) tiene colegio_id NULL.
--   * grados.colegio_id aísla la estructura académica; los grupos heredan el
--     colegio por su grado. Los datos de estudiantes (emociones, diario, etc.)
--     ya cuelgan de usuario_id, así que quedan aislados vía usuarios.
--   * Se migra todo lo existente al colegio 1 (el colegio que ya usa Serena).
-- Aplicar UNA vez, con respaldo previo, sobre una base con schema.sql + 026.
-- =========================================================

CREATE TABLE IF NOT EXISTS colegios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    dane VARCHAR(12) NOT NULL,                   -- código DANE: identifica al colegio Y es el código de registro de estudiantes (lo asigna el superadmin)
    municipio VARCHAR(80) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY colegios_dane_unico (dane)
) ENGINE=InnoDB;

-- Colegio 1 = el que ya existe. Renombrarlo luego desde el panel superadmin.
-- OJO: el DANE de abajo (12 ceros) es un marcador. El superadmin debe poner el DANE real
-- desde Superadmin -> Colegios apenas termine la migración.
INSERT INTO colegios (id, nombre, dane, municipio)
VALUES (1, "Colegio principal (migrado)", "000000000000", "Medellín")
ON DUPLICATE KEY UPDATE nombre = nombre;

-- Rol nuevo
INSERT INTO roles (nombre)
SELECT "superadmin" FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = "superadmin");

-- usuarios
ALTER TABLE usuarios
    ADD COLUMN colegio_id INT NULL AFTER rol_id,
    ADD INDEX idx_usuarios_colegio (colegio_id),
    ADD CONSTRAINT fk_usuarios_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE RESTRICT;
UPDATE usuarios SET colegio_id = 1 WHERE colegio_id IS NULL;

-- grados (los grupos heredan el colegio por grado_id)
ALTER TABLE grados
    ADD COLUMN colegio_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE grados ALTER COLUMN colegio_id DROP DEFAULT;
ALTER TABLE grados
    DROP INDEX nombre,
    ADD UNIQUE KEY grado_colegio_nombre (colegio_id, nombre),
    ADD CONSTRAINT fk_grados_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE RESTRICT;

-- encuestas: pertenecen al colegio de quien las crea
ALTER TABLE encuestas
    ADD COLUMN colegio_id INT NOT NULL DEFAULT 1,
    ADD INDEX idx_encuestas_colegio (colegio_id);
ALTER TABLE encuestas ALTER COLUMN colegio_id DROP DEFAULT;

-- auditoria: bitácora separada por colegio (NULL = acciones del superadmin)
ALTER TABLE auditoria
    ADD COLUMN colegio_id INT NULL,
    ADD INDEX idx_auditoria_colegio (colegio_id);
UPDATE auditoria SET colegio_id = 1 WHERE colegio_id IS NULL;

-- configuracion: ajustes por colegio (colegio_id = 0 -> valor global por defecto)
ALTER TABLE configuracion
    ADD COLUMN colegio_id INT NOT NULL DEFAULT 0 FIRST,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (colegio_id, clave);
UPDATE configuracion SET colegio_id = 1 WHERE colegio_id = 0;

-- recursos_apoyo: NULL = recurso global (líneas nacionales); con valor = propio del colegio
ALTER TABLE recursos_apoyo
    ADD COLUMN colegio_id INT NULL,
    ADD INDEX idx_recursos_colegio (colegio_id);
