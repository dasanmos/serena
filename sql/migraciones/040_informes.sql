-- =========================================================
-- 040_informes.sql   (reejecutable; antes 038_informes: renumerada porque 038 ya la usan codigo_matricula y hoja_vida_master)
-- =========================================================
-- Módulo de INFORMES (catálogo de 69 informes en 15 categorías). Requiere 034, 035, 036 y 037.
-- Esta migración solo agrega tablas NUEVAS; no toca ninguna existente.
--  · informes_generados: bitácora de cada informe que se genera (quién, cuál, con qué filtros).
--  · refuerzos / planes_mejoramiento: captura para los informes de refuerzos y planes de mejoramiento.
--  · caracterizacion_estudiante: población (víctimas del conflicto, etnia, SISBÉN, estrato).
--  · restaurante_*: beneficiarios, asistencia diaria y pagos del restaurante escolar.
--  · cartera_*: cargos (pensión, matrícula, otros) y pagos para estados de cuenta y saldos.
-- Los informes que dependen de estas tablas muestran un aviso si aún no se aplicó la migración.
-- =========================================================

CREATE TABLE IF NOT EXISTS informes_generados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    usuario_id INT NULL,
    rol VARCHAR(30) NOT NULL,
    clave VARCHAR(40) NOT NULL,
    formato VARCHAR(5) NOT NULL DEFAULT 'html',              -- html | pdf | csv
    filtros TEXT NULL,
    filas INT UNSIGNED NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_informes_colegio (colegio_id, creado_en),
    INDEX idx_informes_clave (clave),
    CONSTRAINT fk_informes_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS refuerzos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    periodo_id INT NOT NULL,
    asignacion_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    nota_original DECIMAL(5,2) NULL,
    fecha_limite DATE NULL,
    estado VARCHAR(10) NOT NULL DEFAULT 'pendiente',          -- pendiente | presentado | aprobado | reprobado
    nota_refuerzo DECIMAL(5,2) NULL,
    fecha_presentacion DATE NULL,
    observacion VARCHAR(300) NULL,
    creado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY refuerzo_unico (asignacion_id, periodo_id, estudiante_id),
    INDEX idx_refuerzos_colegio (colegio_id, anio_lectivo_id, estado),
    CONSTRAINT fk_refuerzos_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_refuerzos_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS planes_mejoramiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    periodo_id INT NULL,
    estudiante_id INT NOT NULL,
    asignacion_id INT NULL,
    estado VARCHAR(10) NOT NULL DEFAULT 'activo',             -- activo | cumplido | incumplido
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    nota_inicial DECIMAL(5,2) NULL,
    nota_final DECIMAL(5,2) NULL,
    compromisos TEXT NULL,
    responsable_id INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_planes_colegio (colegio_id, anio_lectivo_id, estado),
    CONSTRAINT fk_planes_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_planes_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS caracterizacion_estudiante (
    estudiante_id INT NOT NULL PRIMARY KEY,
    colegio_id INT NOT NULL,
    victima_conflicto TINYINT(1) NOT NULL DEFAULT 0,
    hecho_victimizante VARCHAR(80) NULL,
    poblacion_etnica VARCHAR(40) NULL,
    sisben VARCHAR(10) NULL,
    estrato TINYINT UNSIGNED NULL,
    observacion VARCHAR(300) NULL,
    actualizado_por INT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_caract_colegio (colegio_id),
    CONSTRAINT fk_caract_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_caract_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS restaurante_beneficiarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    jornada VARCHAR(20) NOT NULL DEFAULT 'almuerzo',          -- desayuno | almuerzo | refrigerio
    tipo VARCHAR(10) NOT NULL DEFAULT 'gratuito',             -- gratuito | pago
    activo TINYINT(1) NOT NULL DEFAULT 1,
    observacion VARCHAR(200) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY rest_benef_unico (anio_lectivo_id, estudiante_id),
    INDEX idx_rest_benef_colegio (colegio_id, anio_lectivo_id),
    CONSTRAINT fk_restb_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_restb_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS restaurante_asistencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    fecha DATE NOT NULL,
    estudiante_id INT NOT NULL,
    asistio TINYINT(1) NOT NULL DEFAULT 1,
    registrado_por INT NULL,
    UNIQUE KEY rest_asist_unico (fecha, estudiante_id),
    INDEX idx_rest_asist_colegio (colegio_id, fecha),
    CONSTRAINT fk_resta_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_resta_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS restaurante_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    fecha_pago DATE NOT NULL,
    metodo VARCHAR(20) NULL,
    recibo VARCHAR(30) NULL,
    registrado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rest_pagos (colegio_id, anio_lectivo_id, mes),
    CONSTRAINT fk_restp_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_restp_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cartera_cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    concepto VARCHAR(80) NOT NULL,                            -- Pensión, Matrícula, Transporte...
    mes TINYINT UNSIGNED NULL,                                -- 1-12 cuando es mensual
    valor DECIMAL(12,2) NOT NULL,
    vence DATE NULL,
    creado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cartera_cargos (colegio_id, anio_lectivo_id, estudiante_id),
    CONSTRAINT fk_carc_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_carc_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cartera_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    cargo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    fecha DATE NOT NULL,
    metodo VARCHAR(20) NULL,
    recibo VARCHAR(30) NULL,
    registrado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cartera_pagos (cargo_id),
    INDEX idx_cartera_pagos_colegio (colegio_id, estudiante_id),
    CONSTRAINT fk_carp_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_carp_cargo FOREIGN KEY (cargo_id) REFERENCES cartera_cargos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
