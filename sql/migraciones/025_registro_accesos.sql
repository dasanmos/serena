-- 025_registro_accesos.sql
-- Registro de quién abre la ficha de cada estudiante (psicólogo/orientador), o
-- exporta su caso en PDF. Deliberadamente separado de `auditoria` (que registra
-- cambios, no consultas): mezclar cientos de vistas rutinarias con las acciones
-- sensibles que ya vigila auditoria.php le quitaría utilidad a esa bitácora. Este
-- registro se muestra directamente en la propia ficha del estudiante ("Quién ha
-- visto esta ficha"), que es donde de verdad sirve para trazabilidad. Ver
-- src/modelos/AccesoFicha.php.
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

CREATE TABLE accesos_ficha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_acceso_estudiante (estudiante_id, fecha),
    INDEX idx_acceso_usuario (usuario_id, fecha)
) ENGINE=InnoDB;
