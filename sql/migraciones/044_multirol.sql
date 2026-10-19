-- ============================================================
-- 044 — Cuentas con varios roles (multirol)
-- Una misma persona (mismo correo / documento) puede tener más de un rol en su colegio:
-- por ejemplo, un docente que también es padre de familia, o una coordinadora que dicta clase.
-- usuarios.rol_id sigue siendo el rol PRINCIPAL; aquí van los roles ADICIONALES.
-- Al iniciar sesión, quien tiene más de un rol elige con cuál entrar (ingresar_como.php).
-- Reglas (src/modelos/RolesUsuario.php): nunca estudiante, superadmin ni secretaría de educación.
-- ============================================================
CREATE TABLE IF NOT EXISTS usuario_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    rol_id INT NOT NULL,
    creado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY usuario_rol_unico (usuario_id, rol_id),
    CONSTRAINT fk_ur_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
