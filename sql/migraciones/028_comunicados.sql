-- 028 · Comunicados del superadmin a los administradores/rectores de los colegios.
-- Cada comunicado guarda su lista de destinatarios y el estado de envío de cada uno,
-- para poder enviarlos por lotes (el hosting corta los scripts largos) y reintentar fallidos.

CREATE TABLE IF NOT EXISTS comunicados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asunto VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    alcance ENUM('todos','seleccion') NOT NULL DEFAULT 'todos',
    creado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comunicado_envios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comunicado_id INT NOT NULL,
    usuario_id INT NULL,
    colegio_id INT NULL,
    correo VARCHAR(150) NOT NULL,
    estado ENUM('pendiente','enviado','fallido') NOT NULL DEFAULT 'pendiente',
    enviado_en DATETIME NULL,
    FOREIGN KEY (comunicado_id) REFERENCES comunicados(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_envios_comunicado (comunicado_id, estado)
) ENGINE=InnoDB;
