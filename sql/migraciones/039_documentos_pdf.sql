-- =========================================================
-- 039_documentos_pdf.sql   (reejecutable)
-- =========================================================
-- Informes y reportes PDF con código QR de verificación.
-- Cada PDF "con QR" registra aquí SOLO metadatos (tipo, título, colegio, quién lo generó y cuándo) y una huella HMAC.
-- NUNCA se guarda el contenido del informe ni datos de estudiantes: la página pública verificar.php solo confirma
-- que el documento fue generado por Serena y por qué institución, sin mostrar su contenido.
-- =========================================================

CREATE TABLE IF NOT EXISTS documentos_emitidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo CHAR(12) NOT NULL,
    colegio_id INT NULL,
    tipo VARCHAR(80) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    generado_por INT NULL,
    rol VARCHAR(40) NULL,
    huella CHAR(64) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verificaciones INT UNSIGNED NOT NULL DEFAULT 0,
    ultima_verificacion DATETIME NULL,
    anulado TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_documentos_codigo (codigo),
    KEY ix_documentos_colegio (colegio_id, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
