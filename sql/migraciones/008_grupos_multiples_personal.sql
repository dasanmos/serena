-- =========================================================
-- 008_grupos_multiples_personal.sql
-- =========================================================
-- Hasta ahora, docente y orientador tenían exactamente el mismo mecanismo de
-- asignación que un estudiante: un solo usuarios.grupo_id. Eso encaja bien
-- para un docente director de grupo (normalmente 1 solo grupo), pero no para
-- un orientador, que en la vida real suele cubrir varios grupos o un grado
-- completo — con un solo grupo_id no había forma de reflejar eso.
--
-- Esta tabla intermedia permite asignar CERO O MÁS grupos a cualquier miembro
-- del personal (orientador, docente, etc.), sin tocar usuarios.grupo_id (que
-- se sigue usando tal cual para los estudiantes: su único grupo de clase).
-- =========================================================

USE serena;

CREATE TABLE personal_grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    grupo_id INT NOT NULL,
    asignado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    UNIQUE KEY usuario_grupo_unico (usuario_id, grupo_id)
) ENGINE=InnoDB;

-- Migra las asignaciones existentes: cualquier docente u orientador que ya
-- tuviera un usuarios.grupo_id queda con ese mismo grupo en la tabla nueva,
-- para no perder nada de lo que ya estaba asignado.
INSERT INTO personal_grupos (usuario_id, grupo_id)
SELECT u.id, u.grupo_id
FROM usuarios u
INNER JOIN roles r ON r.id = u.rol_id
WHERE r.nombre IN ('docente', 'orientador') AND u.grupo_id IS NOT NULL;
