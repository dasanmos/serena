-- =========================================================
-- 041_simat_simpade.sql   (reejecutable: solo crea lo que falte)
-- =========================================================
-- Módulos SIMAT (matrícula) y SIMPADE (permanencia y deserción). Requiere 034, 036, 038 y 040.
-- Hacer respaldo antes. Funciona en MySQL y MariaDB. Solo agrega tablas NUEVAS; no modifica ninguna existente.
--
--  SIMAT
--   · simat_datos        datos que el SIMAT pide y Serena no tenía (procedencia, subsidio, internado, matrícula contratada).
--   · simat_novedades    retiros, traslados, ingresos y cambios de grupo/grado por reportar o ya reportados.
--   · simat_reportes     bitácora de cada exportación, validación o conciliación que se hace.
--  SIMPADE
--   · simpade_riesgos        un caso de riesgo de deserción por estudiante y año (nivel, factores, responsable).
--   · simpade_seguimientos   acciones de permanencia: llamadas, visitas, reuniones, remisiones, compromisos.
--   · simpade_retiros        causa de cada retiro/deserción y si ya se reportó.
--
-- IMPORTANTE: los catálogos de códigos (tipos de documento, códigos de grado, causas de retiro, factores de riesgo)
-- viven en src/modelos/Simat.php y src/modelos/Simpade.php y son ORIENTATIVOS: deben verificarse contra la guía y la
-- plantilla vigentes que entrega la Secretaría de Educación / el MEN antes de cada cargue oficial.
-- =========================================================

CREATE TABLE IF NOT EXISTS simat_datos (
    estudiante_id INT NOT NULL PRIMARY KEY,
    colegio_id INT NOT NULL,
    proviene_de VARCHAR(14) NULL,                            -- oficial | no_oficial | otro_municipio | ninguno
    subsidio_condicionado TINYINT(1) NULL,                   -- 1 = recibe subsidio condicionado (p. ej. Familias en Acción)
    internado TINYINT(1) NULL,
    matricula_contratada TINYINT(1) NULL,
    capacidad_excepcional VARCHAR(40) NULL,
    actualizado_por INT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_simat_datos_colegio (colegio_id),
    CONSTRAINT fk_simat_datos_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_simat_datos_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS simat_novedades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    matricula_id INT NULL,
    tipo VARCHAR(12) NOT NULL,                               -- retiro | traslado | ingreso | cambio_grupo | cambio_grado | otra
    fecha DATE NOT NULL,
    causa VARCHAR(150) NULL,
    destino VARCHAR(150) NULL,                               -- establecimiento al que se traslada, si se sabe
    observacion VARCHAR(300) NULL,
    estado VARCHAR(10) NOT NULL DEFAULT 'pendiente',         -- pendiente | reportada
    reportada_en DATETIME NULL,
    reportada_por INT NULL,
    origen VARCHAR(10) NOT NULL DEFAULT 'manual',            -- manual | automatico
    creado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_simat_nov_colegio (colegio_id, anio_lectivo_id, estado),
    INDEX idx_simat_nov_estudiante (estudiante_id),
    CONSTRAINT fk_simat_nov_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_simat_nov_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE,
    CONSTRAINT fk_simat_nov_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS simat_reportes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NULL,
    tipo VARCHAR(14) NOT NULL,                               -- matricula | novedades | conciliacion | validacion
    filas INT UNSIGNED NOT NULL DEFAULT 0,
    errores INT UNSIGNED NOT NULL DEFAULT 0,
    advertencias INT UNSIGNED NOT NULL DEFAULT 0,
    detalle VARCHAR(300) NULL,
    generado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_simat_rep_colegio (colegio_id, creado_en),
    CONSTRAINT fk_simat_rep_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS simpade_riesgos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    nivel VARCHAR(5) NOT NULL DEFAULT 'medio',               -- bajo | medio | alto
    estado VARCHAR(14) NOT NULL DEFAULT 'activo',            -- activo | en_seguimiento | superado | retirado
    origen VARCHAR(10) NOT NULL DEFAULT 'manual',            -- manual | sugerido (lo propuso el sistema y una persona lo confirmó)
    puntaje SMALLINT NOT NULL DEFAULT 0,
    factores VARCHAR(500) NULL,                              -- claves separadas por coma (catálogo en Simpade::FACTORES)
    observacion TEXT NULL,
    responsable_id INT NULL,
    fecha_deteccion DATE NOT NULL,
    cerrado_en DATETIME NULL,
    creado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY simpade_riesgo_unico (anio_lectivo_id, estudiante_id),
    INDEX idx_simpade_riesgo_colegio (colegio_id, anio_lectivo_id, estado),
    CONSTRAINT fk_simpade_r_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_simpade_r_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE,
    CONSTRAINT fk_simpade_r_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_simpade_r_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS simpade_seguimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    riesgo_id INT NOT NULL,
    autor_id INT NULL,
    fecha DATE NOT NULL,
    tipo VARCHAR(12) NOT NULL DEFAULT 'otro',                -- llamada | visita | reunion | remision | compromiso | otro
    nota TEXT NOT NULL,
    proxima_fecha DATE NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_simpade_seg_riesgo (riesgo_id, fecha),
    CONSTRAINT fk_simpade_seg_riesgo FOREIGN KEY (riesgo_id) REFERENCES simpade_riesgos(id) ON DELETE CASCADE,
    CONSTRAINT fk_simpade_seg_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS simpade_retiros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colegio_id INT NOT NULL,
    anio_lectivo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    matricula_id INT NULL,
    fecha DATE NOT NULL,
    causa VARCHAR(30) NOT NULL,                              -- catálogo en Simpade::CAUSAS_RETIRO
    detalle VARCHAR(300) NULL,
    destino VARCHAR(14) NOT NULL DEFAULT 'desconocido',      -- otra_ie | trabajo | hogar | otro_municipio | desconocido
    familia_contactada TINYINT(1) NOT NULL DEFAULT 0,
    estrategia VARCHAR(300) NULL,                            -- qué se intentó para que permaneciera
    reportado TINYINT(1) NOT NULL DEFAULT 0,                 -- 1 = ya se reportó a la secretaría / SIMPADE
    reportado_en DATETIME NULL,
    reportado_por INT NULL,
    creado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY simpade_retiro_unico (anio_lectivo_id, estudiante_id),
    INDEX idx_simpade_ret_colegio (colegio_id, anio_lectivo_id),
    CONSTRAINT fk_simpade_ret_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    CONSTRAINT fk_simpade_ret_anio FOREIGN KEY (anio_lectivo_id) REFERENCES anios_lectivos(id) ON DELETE CASCADE,
    CONSTRAINT fk_simpade_ret_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
