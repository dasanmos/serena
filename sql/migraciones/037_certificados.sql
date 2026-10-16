-- =========================================================
-- 037_certificados.sql   (reejecutable)
-- =========================================================
-- Certificados verificables con QR (Tanda 4). Requiere 034 y 035 (y 036 para el certificado de horas).
--  · institucion_perfil: configurador de la institución (DANE, NIT, resoluciones, rector, logo y firmas).
--    Las imágenes se guardan como base64 (JPEG ya reducido) para no depender de carpetas del hosting.
--  · plantillas_certificado: textos editables por colegio y tipo (con {{variables}}).
--  · certificados: cada certificado emitido guarda una COPIA INMUTABLE de lo que dice (datos JSON, con la
--    institución y las firmas de ese momento) y una huella HMAC para detectar alteraciones.
--    El código público (12 caracteres al azar) es el que va en el QR.
-- =========================================================

CREATE TABLE IF NOT EXISTS institucion_perfil (
    colegio_id INT NOT NULL PRIMARY KEY,
    nombre_oficial VARCHAR(200) NULL,
    naturaleza VARCHAR(60) NULL,
    caracter VARCHAR(80) NULL,
    niveles VARCHAR(200) NULL,
    jornadas VARCHAR(100) NULL,
    calendario VARCHAR(10) NULL,
    codigo_dane VARCHAR(12) NULL,
    nit VARCHAR(20) NULL,
    codigo_icfes VARCHAR(12) NULL,
    resolucion_numero VARCHAR(60) NULL,
    resolucion_fecha DATE NULL,
    resolucion_entidad VARCHAR(150) NULL,
    resoluciones_adicionales VARCHAR(600) NULL,
    direccion VARCHAR(200) NULL,
    municipio VARCHAR(80) NULL,
    departamento VARCHAR(60) NULL,
    telefono VARCHAR(40) NULL,
    correo VARCHAR(150) NULL,
    sitio_web VARCHAR(150) NULL,
    ciudad_expedicion VARCHAR(80) NULL,
    rector_nombre VARCHAR(120) NULL,
    rector_cargo VARCHAR(60) NULL,
    secretaria_nombre VARCHAR(120) NULL,
    secretaria_cargo VARCHAR(60) NULL,
    color_primario CHAR(7) NOT NULL DEFAULT '#AD6B05',
    dias_vigencia SMALLINT UNSIGNED NULL,
    url_publica VARCHAR(200) NULL,                            -- dirección pública del sitio: es la que se imprime en el QR
    logo_b64 MEDIUMTEXT NULL,
    firma_rector_b64 MEDIUMTEXT NULL,
    firma_secretaria_b64 MEDIUMTEXT NULL,
    actualizado_por INT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_instperfil_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plantillas_certificado (
    colegio_id INT NOT NULL,
    tipo VARCHAR(15) NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    cuerpo TEXT NOT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (colegio_id, tipo),
    CONSTRAINT fk_plantcert_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS certificados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    codigo CHAR(12) NOT NULL,
    tipo VARCHAR(15) NOT NULL,                                -- matricula | estudio | notas | horas
    estudiante_id INT NULL,
    anio_lectivo_id INT NULL,
    datos MEDIUMTEXT NOT NULL,
    huella CHAR(64) NOT NULL,
    emitido_por INT NULL,
    emitido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vence_en DATE NULL,
    estado VARCHAR(8) NOT NULL DEFAULT 'vigente',             -- vigente | anulado
    anulado_por INT NULL,
    anulado_en DATETIME NULL,
    anulado_motivo VARCHAR(255) NULL,
    descargas INT UNSIGNED NOT NULL DEFAULT 0,
    verificaciones INT UNSIGNED NOT NULL DEFAULT 0,
    ultima_verificacion DATETIME NULL,
    UNIQUE KEY certificado_codigo_unico (codigo),
    INDEX idx_cert_colegio (colegio_id, emitido_en),
    INDEX idx_cert_estudiante (estudiante_id),
    CONSTRAINT fk_cert_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_cert_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_cert_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE SET NULL,
    CONSTRAINT fk_cert_emisor FOREIGN KEY (emitido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_cert_anulador FOREIGN KEY (anulado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
