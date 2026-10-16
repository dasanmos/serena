-- =========================================================
-- 005_limpiar_todo.sql — Reset completo del sistema
-- =========================================================
-- Borra TODOS los datos de TODAS las tablas (estudiantes reales, demo, todo)
-- y deja la base como recién instalada: estructura intacta, contadores
-- AUTO_INCREMENT en 0, roles e insignias reseñados (son catálogo fijo que la
-- app necesita para funcionar, no "datos de prueba"), y UNA cuenta de
-- administrador nueva para que puedas volver a entrar.
--
-- ⚠️ ESTO NO SE PUEDE DESHACER. Si hay estudiantes reales usando el sistema,
-- exporta la base primero (phpMyAdmin → Exportar) antes de correr esto.
--
-- Úsalo para: limpiar los datos de demo antes de lanzar el piloto real de
-- septiembre, o para empezar de cero durante desarrollo.
-- =========================================================

USE serena;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE mensajes;
TRUNCATE TABLE conversaciones;
TRUNCATE TABLE respuestas_encuesta;
TRUNCATE TABLE opciones_pregunta;
TRUNCATE TABLE preguntas_encuesta;
TRUNCATE TABLE encuestas;
TRUNCATE TABLE alertas_tempranas;
TRUNCATE TABLE usuario_insignias;
TRUNCATE TABLE insignias;
TRUNCATE TABLE recordatorios;
TRUNCATE TABLE notificaciones;
TRUNCATE TABLE subtareas;
TRUNCATE TABLE metas;
TRUNCATE TABLE seguimiento_habitos;
TRUNCATE TABLE habitos;
TRUNCATE TABLE diario_etiquetas;
TRUNCATE TABLE etiquetas;
TRUNCATE TABLE diario_imagenes;
TRUNCATE TABLE diario;
TRUNCATE TABLE sueno;
TRUNCATE TABLE estado_emocional;
TRUNCATE TABLE recursos_apoyo;
TRUNCATE TABLE auditoria;
TRUNCATE TABLE solicitudes_cambio;
TRUNCATE TABLE tokens_recuperacion;
TRUNCATE TABLE usuarios;
TRUNCATE TABLE grupos;
TRUNCATE TABLE grados;
TRUNCATE TABLE roles;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------
-- Re-sembrar lo que la aplicación necesita para arrancar
-- (esto NO es "dato de prueba" — son catálogos fijos que el código referencia
-- por nombre en requiereRol(), en el criterio de insignias, etc.)
-- ---------------------------------------------------------
INSERT INTO roles (nombre) VALUES
    ('estudiante'), ('psicologo'), ('orientador'), ('docente'),
    ('coordinador'), ('rector'), ('administrador');

INSERT INTO insignias (nombre, descripcion, icono, criterio) VALUES
    ('Racha de 7 días', 'Cumpliste un hábito 7 días seguidos.', 'insignia_racha_7dias.png', 'racha_habito_7'),
    ('Primer mes', 'Llevas un mes usando Serena.', 'insignia_primer_mes.png', 'primer_mes'),
    ('Meta completada', 'Completaste tu primera meta.', 'insignia_meta_completada.png', 'primera_meta_completada');

-- ---------------------------------------------------------
-- Una cuenta de administrador para no quedar bloqueado del sistema.
-- Contraseña temporal: serena2024 — CÁMBIALA apenas entres, esta contraseña
-- va a quedar en este archivo si lo guardas o lo subes a algún lado.
-- ---------------------------------------------------------
INSERT INTO usuarios (nombre, correo, password_hash, rol_id, consentimiento_datos, fecha_consentimiento, fecha_registro)
VALUES (
    'Administrador',
    'admin@serena.edu',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    (SELECT id FROM roles WHERE nombre = 'administrador'),
    1, NOW(), NOW()
);

-- =========================================================
-- Listo. El sistema queda como recién instalado:
--   - 0 estudiantes, 0 grados, 0 grupos, 0 de todo lo demás
--   - 1 cuenta: admin@serena.edu / serena2024 (cámbiala ya)
--   - roles e insignias reseñados (necesarios para que la app funcione)
--
-- Siguiente paso normal: entrar como admin, crear grados/grupos reales,
-- e importar a los estudiantes reales desde administrador/importar_estudiantes.php.
-- =========================================================
