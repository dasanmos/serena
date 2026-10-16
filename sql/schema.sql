-- Serena — Esquema de base de datos
-- MySQL 8+ / InnoDB / utf8mb4

CREATE DATABASE IF NOT EXISTS serena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE serena;

-- =========================================================
-- ROLES Y ESTRUCTURA INSTITUCIONAL
-- =========================================================

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO roles (nombre) VALUES
    ('estudiante'), ('psicologo'), ('orientador'), ('docente'),
    ('coordinador'), ('rector'), ('administrador');

CREATE TABLE grados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grado_id INT NOT NULL,
    nombre VARCHAR(10) NOT NULL,
    docente_id INT NULL,
    FOREIGN KEY (grado_id) REFERENCES grados(id) ON DELETE RESTRICT,
    UNIQUE KEY grado_nombre_unico (grado_id, nombre)
) ENGINE=InnoDB;

-- =========================================================
-- USUARIOS
-- =========================================================

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    grupo_id INT NULL,
    fecha_nacimiento DATE NULL,
    foto VARCHAR(255) NULL,
    telefono VARCHAR(20) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    consentimiento_datos TINYINT(1) NOT NULL DEFAULT 0,
    fecha_consentimiento DATETIME NULL,
    pin_diario_hash VARCHAR(255) NULL,
    intentos_login_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    login_bloqueado_hasta DATETIME NULL,
    intentos_pin_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    pin_bloqueado_hasta DATETIME NULL,
    totp_secreto VARCHAR(255) NULL,
    totp_activo TINYINT(1) NOT NULL DEFAULT 0,
    totp_ultimo_paso BIGINT NULL,
    totp_activado_en DATETIME NULL,
    intentos_2fa_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueo_2fa_hasta DATETIME NULL,
    correo_2fa_activo TINYINT(1) NOT NULL DEFAULT 0,
    correo_2fa_activado_en DATETIME NULL,
    whatsapp_2fa_numero VARCHAR(40) NULL, -- número E.164 o identificador "lid" de WhatsApp (ver src/helpers/whatsapp.php)
    whatsapp_2fa_apikey VARCHAR(255) NULL, -- cifrada (AES-256-GCM), nunca en claro
    whatsapp_2fa_activo TINYINT(1) NOT NULL DEFAULT 0,
    whatsapp_2fa_activado_en DATETIME NULL,
    rostro_2fa_plantillas TEXT NULL, -- descriptores faciales cifrados (AES-256-GCM); nunca se guarda la foto
    rostro_2fa_activo TINYINT(1) NOT NULL DEFAULT 0,
    rostro_2fa_activado_en DATETIME NULL,
    rostro_2fa_consentimiento_en DATETIME NULL,
    ultimo_acceso DATETIME NULL,
    ultimo_resumen_semanal DATETIME NULL,
    resumen_semanal_activo TINYINT(1) NOT NULL DEFAULT 1,
    tema_preferido ENUM('claro', 'oscuro', 'sistema') NOT NULL DEFAULT 'sistema',
    tamano_texto ENUM('normal', 'grande', 'muy_grande') NOT NULL DEFAULT 'normal',
    alto_contraste TINYINT(1) NOT NULL DEFAULT 0,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE SET NULL,
    INDEX idx_usuarios_rol (rol_id),
    INDEX idx_usuarios_grupo (grupo_id)
) ENGINE=InnoDB;

ALTER TABLE grupos
    ADD CONSTRAINT fk_grupos_docente FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Asignación de grupos a personal (docente, orientador, etc.) que puede cubrir más de
-- uno — a diferencia de usuarios.grupo_id (que para un estudiante es SU único grupo de
-- clase, y para un docente director de grupo normalmente también es solo uno). Un
-- orientador típicamente necesita varios grupos o un grado completo, de ahí esta tabla
-- aparte en vez de forzar todo a un solo grupo_id.
CREATE TABLE personal_grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    grupo_id INT NOT NULL,
    asignado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    UNIQUE KEY usuario_grupo_unico (usuario_id, grupo_id)
) ENGINE=InnoDB;

-- Módulo de citas: el profesional (psicólogo u orientador) publica bloques de
-- disponibilidad puntuales, que se parten en franjas reservables del tamaño que él
-- defina; el estudiante toma una franja libre y eso genera la cita.
CREATE TABLE disponibilidad_citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesional_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    duracion_slot_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profesional_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_disponibilidad_profesional_fecha (profesional_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profesional_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    motivo VARCHAR(255) NULL,
    estado ENUM('confirmada', 'cancelada', 'atendida') NOT NULL DEFAULT 'confirmada',
    recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0,
    cancelado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profesional_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (cancelado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY franja_unica (profesional_id, fecha, hora_inicio),
    INDEX idx_citas_estudiante (estudiante_id),
    INDEX idx_citas_profesional_fecha (profesional_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE tokens_recuperacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expira_en DATETIME NOT NULL,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE solicitudes_cambio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    campo VARCHAR(50) NOT NULL,
    valor_anterior VARCHAR(255) NULL,
    valor_nuevo VARCHAR(255) NOT NULL,
    estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    revisado_por INT NULL,
    fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_revision DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- REGISTRO EMOCIONAL Y SUEÑO
-- =========================================================

CREATE TABLE estado_emocional (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    emocion VARCHAR(40) NOT NULL,
    nivel TINYINT NOT NULL,
    nota TEXT NULL,
    momento_dia ENUM('mañana','tarde','noche') NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    editado_en DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_emocion_usuario_fecha (usuario_id, fecha_hora),
    CONSTRAINT chk_nivel_emocion CHECK (nivel BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE sueno (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    horas DECIMAL(3,1) NOT NULL,
    calidad ENUM('mala','regular','buena') NOT NULL,
    hora_dormir TIME NULL,
    hora_despertar TIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY usuario_fecha_unico (usuario_id, fecha)
) ENGINE=InnoDB;

-- =========================================================
-- DIARIO PERSONAL
-- =========================================================

CREATE TABLE diario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(150) NULL,
    contenido TEXT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    editado_en DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_diario_usuario_fecha (usuario_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE diario_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diario_id INT NOT NULL,
    ruta_imagen VARCHAR(255) NOT NULL,
    FOREIGN KEY (diario_id) REFERENCES diario(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(40) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE diario_etiquetas (
    diario_id INT NOT NULL,
    etiqueta_id INT NOT NULL,
    PRIMARY KEY (diario_id, etiqueta_id),
    FOREIGN KEY (diario_id) REFERENCES diario(id) ON DELETE CASCADE,
    FOREIGN KEY (etiqueta_id) REFERENCES etiquetas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- HÁBITOS
-- =========================================================

CREATE TABLE habitos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    frecuencia ENUM('diaria','semanal','personalizada') NOT NULL,
    dias_semana SET('lun','mar','mie','jue','vie','sab','dom') NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_habitos_usuario (usuario_id)
) ENGINE=InnoDB;

CREATE TABLE seguimiento_habitos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    habito_id INT NOT NULL,
    fecha DATE NOT NULL,
    completado TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (habito_id) REFERENCES habitos(id) ON DELETE CASCADE,
    UNIQUE KEY habito_fecha_unico (habito_id, fecha)
) ENGINE=InnoDB;

-- =========================================================
-- METAS
-- =========================================================

CREATE TABLE metas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    habito_id INT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    tipo ENUM('corto_plazo','largo_plazo') NOT NULL,
    fecha_limite DATE NULL,
    estado ENUM('activa','completada','vencida') NOT NULL DEFAULT 'activa',
    porcentaje_avance TINYINT NOT NULL DEFAULT 0,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (habito_id) REFERENCES habitos(id) ON DELETE SET NULL,
    INDEX idx_metas_usuario (usuario_id)
) ENGINE=InnoDB;

CREATE TABLE subtareas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meta_id INT NOT NULL,
    descripcion VARCHAR(200) NOT NULL,
    completada TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (meta_id) REFERENCES metas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- NOTIFICACIONES Y RECORDATORIOS
-- =========================================================

CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    mensaje VARCHAR(255) NOT NULL,
    url_destino VARCHAR(255) NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_notificaciones_usuario (usuario_id, leida)
) ENGINE=InnoDB;

CREATE TABLE recordatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    hora TIME NOT NULL,
    dias_semana SET('lun','mar','mie','jue','vie','sab','dom') NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_envio DATE NULL, -- evita reenviar el mismo día cuando el cron corre cada 15 min
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- CHAT INTERNO
-- =========================================================

CREATE TABLE conversaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_a_id INT NOT NULL,
    usuario_b_id INT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_a_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_b_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY conversacion_unica (usuario_a_id, usuario_b_id)
) ENGINE=InnoDB;

CREATE TABLE mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversacion_id INT NOT NULL,
    emisor_id INT NOT NULL,
    contenido TEXT NOT NULL,
    leido TINYINT(1) NOT NULL DEFAULT 0,
    fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (emisor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_mensajes_conversacion (conversacion_id, fecha_envio)
) ENGINE=InnoDB;

-- =========================================================
-- ENCUESTAS
-- =========================================================

CREATE TABLE encuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creador_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    grupo_id INT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NULL,
    FOREIGN KEY (creador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE preguntas_encuesta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encuesta_id INT NOT NULL,
    texto VARCHAR(255) NOT NULL,
    tipo ENUM('opcion_multiple','texto_libre','escala') NOT NULL,
    FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE opciones_pregunta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    texto VARCHAR(150) NOT NULL,
    FOREIGN KEY (pregunta_id) REFERENCES preguntas_encuesta(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE respuestas_encuesta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    opcion_id INT NULL,
    respuesta_texto TEXT NULL,
    fecha_respuesta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pregunta_id) REFERENCES preguntas_encuesta(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (opcion_id) REFERENCES opciones_pregunta(id) ON DELETE SET NULL,
    UNIQUE KEY respuesta_unica (pregunta_id, usuario_id)
) ENGINE=InnoDB;

-- =========================================================
-- ALERTAS TEMPRANAS
-- =========================================================

CREATE TABLE alertas_tempranas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    nivel_urgencia ENUM('bajo','medio','alto') NOT NULL DEFAULT 'medio',
    atendida TINYINT(1) NOT NULL DEFAULT 0,
    atendida_por INT NULL,
    fecha_generada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_atendida DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (atendida_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_alertas_usuario (usuario_id, atendida)
) ENGINE=InnoDB;

-- =========================================================
-- GAMIFICACIÓN LIGERA
-- =========================================================

CREATE TABLE insignias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    descripcion VARCHAR(200) NOT NULL,
    icono VARCHAR(255) NULL,
    criterio VARCHAR(100) NOT NULL,
    UNIQUE KEY criterio_unico (criterio)
) ENGINE=InnoDB;

INSERT INTO insignias (nombre, descripcion, icono, criterio) VALUES
    ('Racha de 7 días', 'Cumpliste un hábito 7 días seguidos.', 'insignia_racha_7dias.png', 'racha_habito_7'),
    ('Primer mes', 'Llevas un mes usando Serena.', 'insignia_primer_mes.png', 'primer_mes'),
    ('Meta completada', 'Completaste tu primera meta.', 'insignia_meta_completada.png', 'primera_meta_completada');

CREATE TABLE usuario_insignias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    insignia_id INT NOT NULL,
    fecha_obtenida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (insignia_id) REFERENCES insignias(id) ON DELETE CASCADE,
    UNIQUE KEY usuario_insignia_unica (usuario_id, insignia_id)
) ENGINE=InnoDB;

-- =========================================================
-- RECURSOS DE APOYO
-- =========================================================

CREATE TABLE recursos_apoyo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo ENUM('articulo','ejercicio','contacto') NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    url VARCHAR(255) NULL
) ENGINE=InnoDB;

-- =========================================================
-- AUDITORÍA
-- =========================================================

CREATE TABLE auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    accion VARCHAR(50) NOT NULL,
    tabla_afectada VARCHAR(50) NOT NULL,
    registro_id INT NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_auditoria_tabla (tabla_afectada, registro_id)
) ENGINE=InnoDB;

-- Caché diaria del párrafo de "Análisis de datos" por usuario (uno por día).
-- Ver sql/migraciones/010_analisis_cache.sql para el detalle de por qué existe.
CREATE TABLE analisis_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    texto TEXT NOT NULL,
    fecha_generado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY usuario_fecha_unico (usuario_id, fecha)
) ENGINE=InnoDB;

-- Caché genérica por clave (resumen de encuestas, reportes institucionales).
-- Ver sql/migraciones/011_texto_ia_cache.sql.
CREATE TABLE texto_ia_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(191) NOT NULL,
    fecha DATE NOT NULL,
    texto TEXT NOT NULL,
    fecha_generado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY clave_fecha_unico (clave, fecha)
) ENGINE=InnoDB;

-- Notas de sesión privadas del psicólogo/orientador. Ver migración 012.
CREATE TABLE notas_sesion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    autor_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    contenido TEXT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_notas_estudiante (estudiante_id, autor_id)
) ENGINE=InnoDB;

-- Plan de seguimiento del psicólogo/orientador: tareas de control con fecha límite y
-- estado, a diferencia de notas_sesion (que es solo texto libre sin seguimiento de
-- estado). Privado por autor, igual que notas_sesion. Ver migración 022.
CREATE TABLE planes_seguimiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    autor_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NULL,
    fecha_limite DATE NULL,
    estado ENUM('pendiente', 'en_curso', 'completado') NOT NULL DEFAULT 'pendiente',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_completado DATETIME NULL,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_plan_autor (autor_id, estado),
    INDEX idx_plan_estudiante (estudiante_id, autor_id)
) ENGINE=InnoDB;

-- Pulso de grupo: check-in semanal del docente a todo su curso (una pregunta,
-- respuesta de 1 a 5, ~10 segundos). Deliberadamente NO es información emocional
-- individual — el docente solo ve el agregado (promedio y distribución), nunca
-- quién respondió qué; por eso pulso_respuestas no tiene forma de unir la
-- respuesta a un estudiante desde las pantallas del docente (el modelo sí
-- guarda estudiante_id, solo para impedir que alguien responda dos veces). Ver
-- migración 023 y src/modelos/Pulso.php.
CREATE TABLE pulsos_grupo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    grupo_id INT NOT NULL,
    pregunta VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NOT NULL,
    cerrado_manualmente TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    INDEX idx_pulso_grupo (grupo_id, fecha_cierre)
) ENGINE=InnoDB;

CREATE TABLE pulso_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pulso_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    valor TINYINT NOT NULL,
    fecha_respuesta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pulso_id) REFERENCES pulsos_grupo(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unico_pulso_estudiante (pulso_id, estudiante_id)
) ENGINE=InnoDB;

-- Ajustes editables por el administrador (lema y pie de los correos). Ver migración 024.
CREATE TABLE configuracion (
    clave VARCHAR(60) NOT NULL PRIMARY KEY,
    valor TEXT NOT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Registro de quién abre la ficha de cada estudiante o exporta su caso — separado de
-- `auditoria` (que registra cambios, no consultas). Ver migración 025.
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

-- =========================================================
-- DATOS DE PRUEBA
-- Contraseña para todos: serena2024
-- =========================================================
INSERT INTO grados (nombre) VALUES ('Sexto'),('Séptimo'),('Octavo'),('Noveno'),('Décimo'),('Undécimo');
INSERT INTO grupos (grado_id, nombre) VALUES (1,'A'),(1,'B'),(2,'A'),(2,'B'),(3,'A'),(4,'A'),(5,'A'),(6,'A');

-- Admin principal: admin@serena.edu / serena2024
INSERT INTO usuarios (nombre, correo, password_hash, rol_id, consentimiento_datos, fecha_consentimiento, fecha_registro)
VALUES
('Administrador', 'admin@serena.edu', '', 7, 1, NOW(), NOW()),
('Dra. García', 'psicologo@serena.edu', '', 2, 1, NOW(), NOW()),
('Prof. Martínez', 'orientador@serena.edu', '', 3, 1, NOW(), NOW()),
('Estudiante Demo', 'estudiante@serena.edu', '', 1, 1, NOW(), NOW());

-- Verificación en 2 pasos: códigos de recuperación de un solo uso (10 por lote,
-- vencen a los 30 días). Ver sql/migraciones/013_verificacion_2_pasos.sql.
CREATE TABLE codigos_recuperacion_2fa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    creado_en DATETIME NOT NULL,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_codigos_2fa_usuario (usuario_id, usado_en, expira_en)
) ENGINE=InnoDB;

-- Verificación en 2 pasos por correo electrónico: un código de 6 dígitos vigente
-- unos minutos. Ver sql/migraciones/014_verificacion_2fa_correo.sql.
CREATE TABLE codigos_2fa_correo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    creado_en DATETIME NOT NULL,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_codigos_2fa_correo_usuario (usuario_id)
) ENGINE=InnoDB;

-- Verificación en 2 pasos por WhatsApp (API gratuita de CallMeBot: cada usuario
-- activa su propia apikey desde Seguridad). Ver
-- sql/migraciones/015_verificacion_2fa_whatsapp.sql y 016_verificacion_2fa_rostro.sql.
CREATE TABLE codigos_2fa_whatsapp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    creado_en DATETIME NOT NULL,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_codigos_2fa_whatsapp_usuario (usuario_id)
) ENGINE=InnoDB;

-- Registro de sesiones activas por usuario (ver otros dispositivos y cerrar
-- sesión remota). Ver sql/migraciones/017_sesiones_activas.sql.
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
