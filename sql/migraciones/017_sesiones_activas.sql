-- 017_sesiones_activas.sql
-- Registro de sesiones activas por usuario: permite que cada persona vea desde
-- qué dispositivos/navegadores tiene sesión iniciada y cierre cualquiera de
-- ellas de forma remota (por ejemplo, si olvidó cerrar sesión en un computador
-- compartido). El PHP_SESSION nativo no se puede enumerar ni invalidar desde
-- otra sesión por sí solo — esta tabla es lo que lo hace posible.
--
-- No se guarda ninguna contraseña ni cookie real: solo el ID de sesión de PHP
-- (session_id(), un identificador aleatorio que ya usa el navegador — no es un
-- secreto adicional, es el mismo valor no reversible con el que ya opera la
-- sesión) y metadatos (IP, user-agent, fechas). Revocar una fila no borra la
-- cookie del otro dispositivo: hace que auth.php la rechace en su próxima
-- petición y lo mande a login.php.

CREATE TABLE sesiones_activas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip VARCHAR(45) NULL,
    agente_usuario VARCHAR(255) NULL,
    iniciada_en DATETIME NOT NULL,
    ultima_actividad DATETIME NOT NULL,
    revocada_en DATETIME NULL,
    es_actual_al_revocar TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_sesiones_session_id (session_id),
    INDEX idx_sesiones_usuario (usuario_id, revocada_en)
) ENGINE=InnoDB;
