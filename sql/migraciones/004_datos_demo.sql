-- =========================================================
-- 004_datos_demo.sql — Datos de demostración completos
-- =========================================================
-- Deja el sistema como si llevara semanas en uso: ~12 estudiantes en 3 grupos,
-- personal completo (docente, coordinador, rector además de admin/psicólogo/
-- orientador ya sembrados), ~30 días de historial emocional y de sueño, hábitos
-- con rachas reales, metas en los 3 estados (activa/completada/vencida), diario,
-- chat, una encuesta con respuestas, una alerta temprana, una solicitud de cambio
-- pendiente, insignias otorgadas, y recursos de apoyo.
--
-- CONTRASEÑA para TODAS las cuentas de esta semilla: serena2024
--
-- ⚠️ CUÁNDO CORRER ESTO: justo después de sql/schema.sql, en una base recién
-- creada para pruebas/demo. NO lo corras contra una base que ya tenga datos
-- reales de estudiantes — mezclaría cuentas ficticias con reales.
-- Es seguro volver a correrlo (usa ON DUPLICATE KEY UPDATE en los usuarios),
-- pero el historial de emociones/sueño/hábitos SÍ se duplicaría si lo corres
-- dos veces — bórralo primero si necesitas regenerarlo (ver sección final).
-- =========================================================

USE serena;

-- ---------------------------------------------------------
-- 1. Arreglar los 4 usuarios semilla de schema.sql (traían password_hash vacío)
-- ---------------------------------------------------------
UPDATE usuarios SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE correo IN ('admin@serena.edu','psicologo@serena.edu','orientador@serena.edu','estudiante@serena.edu');

-- El orientador y el estudiante demo necesitan grupo_id (schema.sql no lo asignaba)
UPDATE usuarios SET grupo_id = (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='A')
WHERE correo = 'estudiante@serena.edu';
UPDATE usuarios SET grupo_id = (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='A')
WHERE correo = 'orientador@serena.edu';
-- Cuenta demo con fecha de registro de hace 40 días, para que la insignia "primer mes" aplique de verdad
UPDATE usuarios SET fecha_registro = NOW() - INTERVAL 40 DAY WHERE correo = 'estudiante@serena.edu';

-- ---------------------------------------------------------
-- 2. Personal nuevo: docente, coordinador, rector
-- ---------------------------------------------------------
INSERT INTO usuarios (nombre, correo, password_hash, rol_id, grupo_id, consentimiento_datos, fecha_consentimiento, fecha_registro) VALUES
('Prof. Ana Ríos', 'docente@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='docente'),
 (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='A'),
 1, NOW(), NOW() - INTERVAL 60 DAY),
('Lic. Pedro Salas', 'coordinador@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='coordinador'), NULL, 1, NOW(), NOW() - INTERVAL 60 DAY),
('Rectora Elena Vidal', 'rector@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='rector'), NULL, 1, NOW(), NOW() - INTERVAL 60 DAY)
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), grupo_id = VALUES(grupo_id);

-- Asignar la docente a su grupo (columna docente_id en grupos)
UPDATE grupos g
INNER JOIN grados gr ON gr.id = g.grado_id
SET g.docente_id = (SELECT id FROM usuarios WHERE correo = 'docente@serena.edu')
WHERE gr.nombre = 'Sexto' AND g.nombre = 'A';

-- ---------------------------------------------------------
-- 3. Estudiantes (11 nuevos + la cuenta demo ya existente = 12 en total)
-- ---------------------------------------------------------
INSERT INTO usuarios (nombre, correo, password_hash, rol_id, grupo_id, fecha_nacimiento, consentimiento_datos, fecha_consentimiento, fecha_registro) VALUES
('Mariana Torres', 'mariana.torres@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='A'),
 '2014-03-12', 1, NOW(), NOW() - INTERVAL 25 DAY),
('Juan Pablo Gómez', 'juanpablo.gomez@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='A'),
 '2014-07-22', 1, NOW(), NOW() - INTERVAL 25 DAY),
('Valentina Ruiz', 'valentina.ruiz@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='A'),
 '2014-01-30', 1, NOW(), NOW() - INTERVAL 20 DAY),
('Mateo Londoño', 'mateo.londono@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='A'),
 '2014-05-18', 1, NOW(), NOW() - INTERVAL 18 DAY),
('Samuel Herrera', 'samuel.herrera@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='B'),
 '2014-09-05', 1, NOW(), NOW() - INTERVAL 22 DAY),
('Isabella Castro', 'isabella.castro@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='B'),
 '2014-02-14', 1, NOW(), NOW() - INTERVAL 15 DAY),
('Camila Rojas', 'camila.rojas@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='B'),
 '2014-11-27', 1, NOW(), NOW() - INTERVAL 12 DAY),
('Santiago Vargas', 'santiago.vargas@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Séptimo' AND g.nombre='A'),
 '2013-04-09', 1, NOW(), NOW() - INTERVAL 30 DAY),
('Sofía Mendoza', 'sofia.mendoza@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Séptimo' AND g.nombre='A'),
 '2013-08-16', 1, NOW(), NOW() - INTERVAL 28 DAY),
('Nicolás Peña', 'nicolas.pena@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Séptimo' AND g.nombre='A'),
 '2013-06-03', 1, NOW(), NOW() - INTERVAL 10 DAY),
('Luciana Ospina', 'luciana.ospina@serena.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 (SELECT id FROM roles WHERE nombre='estudiante'), (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Séptimo' AND g.nombre='A'),
 '2013-12-21', 1, NOW(), NOW() - INTERVAL 8 DAY)
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash);

-- ---------------------------------------------------------
-- 4. Estado emocional — últimos 30 días, todos los estudiantes
-- ---------------------------------------------------------
-- No todos los días tienen registro (RAND()<0.75) — más realista que 100% de cumplimiento.
INSERT INTO estado_emocional (usuario_id, emocion, nivel, nota, momento_dia, fecha_hora)
WITH RECURSIVE fechas AS (
    SELECT CURDATE() - INTERVAL 29 DAY AS fecha
    UNION ALL
    SELECT fecha + INTERVAL 1 DAY FROM fechas WHERE fecha < CURDATE()
)
SELECT
    u.id,
    ELT(FLOOR(1+RAND()*10), 'feliz','tranquilo','motivado','ansioso','triste','cansado','estresado','enojado','confundido','agradecido'),
    FLOOR(2+RAND()*4), -- nivel entre 2 y 5 (la mayoría, ver Mateo Londoño abajo para el caso de alerta)
    NULL,
    ELT(FLOOR(1+RAND()*3), 'mañana','tarde','noche'),
    TIMESTAMP(f.fecha, ELT(FLOOR(1+RAND()*3), '08:00:00','14:30:00','20:00:00'))
FROM fechas f
CROSS JOIN usuarios u
INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
WHERE RAND() < 0.75;

-- Caso de alerta temprana: Mateo Londoño con 6 días seguidos de ánimo muy bajo
-- (esto es lo que hace real la alerta que se inserta más abajo, no es solo un
-- registro suelto en la tabla de alertas).
INSERT INTO estado_emocional (usuario_id, emocion, nivel, nota, momento_dia, fecha_hora)
SELECT
    (SELECT id FROM usuarios WHERE correo = 'mateo.londono@serena.edu'),
    ELT(FLOOR(1+RAND()*4), 'triste','ansioso','estresado','cansado'),
    FLOOR(1+RAND()*2), -- nivel 1 o 2
    ELT(FLOOR(1+RAND()*3), 'No tengo ganas de nada.', 'Difícil concentrarme en clase.', NULL),
    'noche',
    TIMESTAMP(CURDATE() - INTERVAL n DAY, '20:00:00')
FROM (SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) dias;

-- ---------------------------------------------------------
-- 5. Sueño — últimos 21 días, todos los estudiantes
-- ---------------------------------------------------------
INSERT INTO sueno (usuario_id, fecha, horas, calidad, hora_dormir, hora_despertar)
WITH RECURSIVE fechas AS (
    SELECT CURDATE() - INTERVAL 20 DAY AS fecha
    UNION ALL
    SELECT fecha + INTERVAL 1 DAY FROM fechas WHERE fecha < CURDATE()
)
SELECT
    u.id,
    f.fecha,
    ROUND(5 + RAND()*4, 1), -- entre 5.0 y 9.0 horas
    ELT(FLOOR(1+RAND()*3), 'mala','regular','buena'),
    ELT(FLOOR(1+RAND()*3), '21:30:00','22:15:00','23:00:00'),
    '06:30:00'
FROM fechas f
CROSS JOIN usuarios u
INNER JOIN roles r ON r.id = u.rol_id AND r.nombre = 'estudiante'
WHERE RAND() < 0.8
ON DUPLICATE KEY UPDATE horas = VALUES(horas);

-- ---------------------------------------------------------
-- 6. Hábitos
-- ---------------------------------------------------------
INSERT INTO habitos (usuario_id, nombre, categoria, frecuencia, fecha_creacion) VALUES
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Beber 2 litros de agua', 'salud', 'diaria', NOW() - INTERVAL 35 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Dormir antes de las 10pm', 'salud', 'diaria', NOW() - INTERVAL 35 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Leer 20 minutos', 'estudio', 'diaria', NOW() - INTERVAL 30 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Meditar 5 minutos', 'mental', 'diaria', NOW() - INTERVAL 20 DAY),
((SELECT id FROM usuarios WHERE correo='mariana.torres@serena.edu'), 'Hacer ejercicio', 'salud', 'semanal', NOW() - INTERVAL 20 DAY),
((SELECT id FROM usuarios WHERE correo='mariana.torres@serena.edu'), 'Estudiar sin celular', 'estudio', 'diaria', NOW() - INTERVAL 20 DAY),
((SELECT id FROM usuarios WHERE correo='juanpablo.gomez@serena.edu'), 'Ordenar el cuarto', 'salud', 'semanal', NOW() - INTERVAL 18 DAY),
((SELECT id FROM usuarios WHERE correo='juanpablo.gomez@serena.edu'), 'Leer 20 minutos', 'estudio', 'diaria', NOW() - INTERVAL 18 DAY),
((SELECT id FROM usuarios WHERE correo='valentina.ruiz@serena.edu'), 'Beber 2 litros de agua', 'salud', 'diaria', NOW() - INTERVAL 15 DAY),
((SELECT id FROM usuarios WHERE correo='mateo.londono@serena.edu'), 'Meditar 5 minutos', 'mental', 'diaria', NOW() - INTERVAL 15 DAY),
((SELECT id FROM usuarios WHERE correo='samuel.herrera@serena.edu'), 'Hacer ejercicio', 'salud', 'diaria', NOW() - INTERVAL 18 DAY),
((SELECT id FROM usuarios WHERE correo='isabella.castro@serena.edu'), 'Leer 20 minutos', 'estudio', 'diaria', NOW() - INTERVAL 12 DAY),
((SELECT id FROM usuarios WHERE correo='camila.rojas@serena.edu'), 'Dormir antes de las 10pm', 'salud', 'diaria', NOW() - INTERVAL 10 DAY),
((SELECT id FROM usuarios WHERE correo='santiago.vargas@serena.edu'), 'Estudiar sin celular', 'estudio', 'diaria', NOW() - INTERVAL 25 DAY),
((SELECT id FROM usuarios WHERE correo='sofia.mendoza@serena.edu'), 'Hacer ejercicio', 'salud', 'semanal', NOW() - INTERVAL 22 DAY),
((SELECT id FROM usuarios WHERE correo='nicolas.pena@serena.edu'), 'Beber 2 litros de agua', 'salud', 'diaria', NOW() - INTERVAL 8 DAY),
((SELECT id FROM usuarios WHERE correo='luciana.ospina@serena.edu'), 'Meditar 5 minutos', 'mental', 'diaria', NOW() - INTERVAL 6 DAY);

-- Seguimiento — cumplimiento aleatorio (60%) para todos los hábitos, últimos 21 días
INSERT INTO seguimiento_habitos (habito_id, fecha, completado)
WITH RECURSIVE fechas AS (
    SELECT CURDATE() - INTERVAL 20 DAY AS fecha
    UNION ALL
    SELECT fecha + INTERVAL 1 DAY FROM fechas WHERE fecha < CURDATE()
)
SELECT h.id, f.fecha, IF(RAND() < 0.6, 1, 0)
FROM fechas f
CROSS JOIN habitos h
WHERE h.fecha_creacion <= f.fecha
ON DUPLICATE KEY UPDATE completado = VALUES(completado);

-- Racha garantizada de 10 días para "Beber 2 litros de agua" de la cuenta demo —
-- así la insignia "Racha de 7 días" y el indicador 🔥 se ven reales desde el primer login.
INSERT INTO seguimiento_habitos (habito_id, fecha, completado)
SELECT
    (SELECT h.id FROM habitos h WHERE h.usuario_id = (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu')
     AND h.nombre = 'Beber 2 litros de agua' LIMIT 1),
    CURDATE() - INTERVAL n DAY,
    1
FROM (SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) dias
ON DUPLICATE KEY UPDATE completado = 1;

-- ---------------------------------------------------------
-- 7. Metas — activa, completada y vencida (para probar los 3 flujos de la UI)
-- ---------------------------------------------------------
INSERT INTO metas (usuario_id, titulo, descripcion, tipo, fecha_limite, estado, porcentaje_avance, fecha_creacion) VALUES
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Terminar el proyecto de ciencias', 'Investigación sobre reciclaje para la feria escolar', 'corto_plazo', CURDATE() + INTERVAL 10 DAY, 'activa', 60, NOW() - INTERVAL 15 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Leer 5 libros este semestre', 'Meta personal de lectura', 'largo_plazo', CURDATE() + INTERVAL 60 DAY, 'activa', 40, NOW() - INTERVAL 30 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Aprender a tocar 3 canciones en guitarra', NULL, 'corto_plazo', CURDATE() - INTERVAL 5 DAY, 'vencida', 30, NOW() - INTERVAL 40 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Mejorar mi promedio de matemáticas', 'Pasar de 3.2 a 4.0', 'corto_plazo', CURDATE() - INTERVAL 20 DAY, 'completada', 100, NOW() - INTERVAL 45 DAY),
((SELECT id FROM usuarios WHERE correo='mariana.torres@serena.edu'), 'Correr 5km sin parar', NULL, 'corto_plazo', CURDATE() + INTERVAL 20 DAY, 'activa', 25, NOW() - INTERVAL 10 DAY),
((SELECT id FROM usuarios WHERE correo='juanpablo.gomez@serena.edu'), 'Organizar mi horario de estudio', NULL, 'corto_plazo', CURDATE() + INTERVAL 5 DAY, 'activa', 80, NOW() - INTERVAL 12 DAY),
((SELECT id FROM usuarios WHERE correo='santiago.vargas@serena.edu'), 'Ahorrar para un regalo', NULL, 'largo_plazo', CURDATE() + INTERVAL 45 DAY, 'activa', 50, NOW() - INTERVAL 20 DAY),
((SELECT id FROM usuarios WHERE correo='sofia.mendoza@serena.edu'), 'Terminar el álbum de dibujos', NULL, 'largo_plazo', CURDATE() - INTERVAL 3 DAY, 'completada', 100, NOW() - INTERVAL 50 DAY);

-- Subtareas de la meta activa "Terminar el proyecto de ciencias"
INSERT INTO subtareas (meta_id, descripcion, completada)
SELECT id, 'Investigar el tema', 1 FROM metas WHERE titulo = 'Terminar el proyecto de ciencias' AND usuario_id = (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu')
UNION ALL
SELECT id, 'Escribir el informe', 1 FROM metas WHERE titulo = 'Terminar el proyecto de ciencias' AND usuario_id = (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu')
UNION ALL
SELECT id, 'Hacer las diapositivas', 0 FROM metas WHERE titulo = 'Terminar el proyecto de ciencias' AND usuario_id = (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu')
UNION ALL
SELECT id, 'Ensayar la presentación', 0 FROM metas WHERE titulo = 'Terminar el proyecto de ciencias' AND usuario_id = (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu');

-- Subtareas de la meta vencida (para que el flujo "continuar/reiniciar" tenga contexto real)
INSERT INTO subtareas (meta_id, descripcion, completada)
SELECT id, 'Aprender los acordes básicos', 1 FROM metas WHERE titulo = 'Aprender a tocar 3 canciones en guitarra'
UNION ALL
SELECT id, 'Practicar la primera canción', 0 FROM metas WHERE titulo = 'Aprender a tocar 3 canciones en guitarra';

-- ---------------------------------------------------------
-- 8. Diario personal (solo la cuenta demo, con etiquetas). Sin imágenes:
-- una fila en diario_imagenes sin el archivo real en disco rompería la
-- vista (diario_imagen.php respondería 404) — se deja para subir por la UI.
-- ---------------------------------------------------------
INSERT INTO diario (usuario_id, titulo, contenido, fecha) VALUES
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Un buen día', 'Hoy me fue bien en el examen de matemáticas. Me sentí orgullosa de mí misma.', NOW() - INTERVAL 12 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), NULL, 'Discutí con mi mejor amiga. No sé cómo arreglarlo todavía.', NOW() - INTERVAL 9 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Ensayo de banda', 'Practicamos 2 horas para el festival del colegio. Muy cansada pero contenta.', NOW() - INTERVAL 6 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), NULL, 'Día tranquilo, nada especial. A veces está bien que no pase nada.', NOW() - INTERVAL 3 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Reflexión', 'Hablé con el psicólogo hoy. Me ayudó a organizar mis ideas sobre la universidad.', NOW() - INTERVAL 1 DAY);

INSERT INTO etiquetas (nombre) VALUES ('familia'),('estudio'),('amigos'),('logros')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT INTO diario_etiquetas (diario_id, etiqueta_id)
SELECT d.id, e.id FROM diario d, etiquetas e WHERE d.titulo='Un buen día' AND e.nombre='logros'
UNION ALL
SELECT d.id, e.id FROM diario d, etiquetas e WHERE d.titulo='Un buen día' AND e.nombre='estudio'
UNION ALL
SELECT d.id, e.id FROM diario d, etiquetas e WHERE d.contenido LIKE 'Discutí%' AND e.nombre='amigos'
UNION ALL
SELECT d.id, e.id FROM diario d, etiquetas e WHERE d.titulo='Reflexión' AND e.nombre='familia';

-- ---------------------------------------------------------
-- 9. Notificaciones (mezcla de leídas y no leídas)
-- ---------------------------------------------------------
INSERT INTO notificaciones (usuario_id, tipo, mensaje, url_destino, leida, fecha_creacion) VALUES
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'logro', '¡Nueva insignia desbloqueada: Racha de 7 días! 🏅', '/estudiante/perfil.php', 0, NOW() - INTERVAL 1 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'logro', '¡Nueva insignia desbloqueada: Meta completada! 🏅', '/estudiante/perfil.php', 0, NOW() - INTERVAL 20 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'recordatorio', 'No has registrado tu emoción hoy.', '/estudiante/emociones.php', 1, NOW() - INTERVAL 5 DAY),
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'chat', 'Tienes un mensaje nuevo de Dra. García.', '/estudiante/chat.php', 0, NOW() - INTERVAL 2 HOUR),
((SELECT id FROM usuarios WHERE correo='mariana.torres@serena.edu'), 'recordatorio', 'No has registrado tu emoción hoy.', '/estudiante/emociones.php', 1, NOW() - INTERVAL 3 DAY);

-- ---------------------------------------------------------
-- 10. Chat: conversación entre la cuenta demo y la psicóloga
-- ---------------------------------------------------------
INSERT INTO conversaciones (usuario_a_id, usuario_b_id, fecha_creacion) VALUES
((SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), (SELECT id FROM usuarios WHERE correo='psicologo@serena.edu'), NOW() - INTERVAL 10 DAY)
ON DUPLICATE KEY UPDATE fecha_creacion = VALUES(fecha_creacion);

INSERT INTO mensajes (conversacion_id, emisor_id, contenido, leido, fecha_envio)
SELECT c.id, (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Hola, ¿tiene un momento para hablar esta semana?', 1, NOW() - INTERVAL 10 DAY
FROM conversaciones c WHERE c.usuario_a_id=(SELECT id FROM usuarios WHERE correo='estudiante@serena.edu')
UNION ALL
SELECT c.id, (SELECT id FROM usuarios WHERE correo='psicologo@serena.edu'), 'Claro, ¿el jueves a las 2pm te sirve?', 1, NOW() - INTERVAL 10 DAY + INTERVAL 3 HOUR
FROM conversaciones c WHERE c.usuario_a_id=(SELECT id FROM usuarios WHERE correo='estudiante@serena.edu')
UNION ALL
SELECT c.id, (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Sí, perfecto. Gracias.', 1, NOW() - INTERVAL 10 DAY + INTERVAL 4 HOUR
FROM conversaciones c WHERE c.usuario_a_id=(SELECT id FROM usuarios WHERE correo='estudiante@serena.edu')
UNION ALL
SELECT c.id, (SELECT id FROM usuarios WHERE correo='psicologo@serena.edu'), 'Antes de la cita: ¿cómo te has sentido esta semana?', 1, NOW() - INTERVAL 2 DAY
FROM conversaciones c WHERE c.usuario_a_id=(SELECT id FROM usuarios WHERE correo='estudiante@serena.edu')
UNION ALL
SELECT c.id, (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), 'Mejor que la semana pasada, gracias por preguntar.', 1, NOW() - INTERVAL 2 DAY + INTERVAL 1 HOUR
FROM conversaciones c WHERE c.usuario_a_id=(SELECT id FROM usuarios WHERE correo='estudiante@serena.edu')
UNION ALL
SELECT c.id, (SELECT id FROM usuarios WHERE correo='psicologo@serena.edu'), 'Me alegra leer eso. Nos vemos el jueves entonces.', 0, NOW() - INTERVAL 2 HOUR
FROM conversaciones c WHERE c.usuario_a_id=(SELECT id FROM usuarios WHERE correo='estudiante@serena.edu');

-- ---------------------------------------------------------
-- 11. Encuesta con preguntas de los 3 tipos + respuestas de varios estudiantes
-- ---------------------------------------------------------
INSERT INTO encuestas (creador_id, titulo, descripcion, grupo_id, fecha_creacion, fecha_cierre) VALUES
((SELECT id FROM usuarios WHERE correo='psicologo@serena.edu'), 'Bienestar del mes',
 'Encuesta corta para conocer cómo se sienten en general.',
 (SELECT g.id FROM grupos g INNER JOIN grados gr ON gr.id=g.grado_id WHERE gr.nombre='Sexto' AND g.nombre='A'),
 NOW() - INTERVAL 8 DAY, NOW() + INTERVAL 20 DAY);

INSERT INTO preguntas_encuesta (encuesta_id, texto, tipo) VALUES
((SELECT id FROM encuestas WHERE titulo='Bienestar del mes'), '¿Cómo describirías tu semana en general?', 'opcion_multiple'),
((SELECT id FROM encuestas WHERE titulo='Bienestar del mes'), 'Del 1 al 10, ¿qué tan apoyado te sientes por tus compañeros?', 'escala'),
((SELECT id FROM encuestas WHERE titulo='Bienestar del mes'), '¿Hay algo que te gustaría que supiéramos?', 'texto_libre');

INSERT INTO opciones_pregunta (pregunta_id, texto)
SELECT id, 'Muy buena' FROM preguntas_encuesta WHERE texto='¿Cómo describirías tu semana en general?'
UNION ALL SELECT id, 'Buena' FROM preguntas_encuesta WHERE texto='¿Cómo describirías tu semana en general?'
UNION ALL SELECT id, 'Regular' FROM preguntas_encuesta WHERE texto='¿Cómo describirías tu semana en general?'
UNION ALL SELECT id, 'Difícil' FROM preguntas_encuesta WHERE texto='¿Cómo describirías tu semana en general?';

-- Respuestas de 4 estudiantes del grupo Sexto A a la pregunta de opción múltiple
INSERT INTO respuestas_encuesta (pregunta_id, usuario_id, opcion_id, fecha_respuesta)
SELECT p.id, u.id, o.id, NOW() - INTERVAL FLOOR(1+RAND()*5) DAY
FROM preguntas_encuesta p
JOIN opciones_pregunta o ON o.pregunta_id = p.id AND o.texto = 'Buena'
JOIN usuarios u ON u.correo = 'mariana.torres@serena.edu'
WHERE p.texto = '¿Cómo describirías tu semana en general?'
UNION ALL
SELECT p.id, u.id, o.id, NOW() - INTERVAL FLOOR(1+RAND()*5) DAY
FROM preguntas_encuesta p
JOIN opciones_pregunta o ON o.pregunta_id = p.id AND o.texto = 'Muy buena'
JOIN usuarios u ON u.correo = 'juanpablo.gomez@serena.edu'
WHERE p.texto = '¿Cómo describirías tu semana en general?'
UNION ALL
SELECT p.id, u.id, o.id, NOW() - INTERVAL FLOOR(1+RAND()*5) DAY
FROM preguntas_encuesta p
JOIN opciones_pregunta o ON o.pregunta_id = p.id AND o.texto = 'Regular'
JOIN usuarios u ON u.correo = 'valentina.ruiz@serena.edu'
WHERE p.texto = '¿Cómo describirías tu semana en general?';

-- Respuesta de texto libre de la cuenta demo
INSERT INTO respuestas_encuesta (pregunta_id, usuario_id, respuesta_texto, fecha_respuesta)
SELECT p.id, (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'),
       'Me gustaría que hubiera más espacios para hablar de música en el colegio.', NOW() - INTERVAL 3 DAY
FROM preguntas_encuesta p WHERE p.texto = '¿Hay algo que te gustaría que supiéramos?';

-- ---------------------------------------------------------
-- 12. Alerta temprana — Mateo Londoño, basada en sus 6 días de ánimo bajo reales
-- ---------------------------------------------------------
INSERT INTO alertas_tempranas (usuario_id, tipo, descripcion, nivel_urgencia, atendida, fecha_generada) VALUES
((SELECT id FROM usuarios WHERE correo='mateo.londono@serena.edu'), 'emocional_bajo',
 'Varios días consecutivos con niveles emocionales bajos (1-2).', 'alto', 0, NOW() - INTERVAL 1 DAY);

-- ---------------------------------------------------------
-- 13. Solicitud de cambio de datos pendiente — para probar administrador/aprobaciones.php
-- ---------------------------------------------------------
INSERT INTO solicitudes_cambio (usuario_id, campo, valor_anterior, valor_nuevo, estado, fecha_solicitud) VALUES
((SELECT id FROM usuarios WHERE correo='mariana.torres@serena.edu'), 'correo', 'mariana.torres@serena.edu', 'mariana.torres2@gmail.com', 'pendiente', NOW() - INTERVAL 2 DAY),
((SELECT id FROM usuarios WHERE correo='santiago.vargas@serena.edu'), 'telefono', NULL, '3001234567', 'pendiente', NOW() - INTERVAL 1 DAY);

-- ---------------------------------------------------------
-- 14. Bitácora de auditoría — entradas ilustrativas (no reemplazan el rastro real,
-- que se genera solo con el uso normal del sistema desde Fase 1)
-- ---------------------------------------------------------
INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos, fecha) VALUES
((SELECT id FROM usuarios WHERE correo='admin@serena.edu'), 'importar_estudiantes_csv', 'usuarios', 0,
 JSON_OBJECT('importados', 12), NOW() - INTERVAL 25 DAY),
((SELECT id FROM usuarios WHERE correo='admin@serena.edu'), 'activar_usuario', 'usuarios',
 (SELECT id FROM usuarios WHERE correo='camila.rojas@serena.edu'), JSON_OBJECT('activo', 1), NOW() - INTERVAL 12 DAY);

-- ---------------------------------------------------------
-- 15. Recursos de apoyo adicionales (más allá del contenido fijo de recursos_apoyo.php)
-- ---------------------------------------------------------
INSERT INTO recursos_apoyo (titulo, descripcion, tipo, categoria, url) VALUES
('¿Qué es la ansiedad y cómo reconocerla?', 'Artículo breve sobre señales comunes de ansiedad en adolescentes y cuándo pedir ayuda.', 'articulo', 'ansiedad', NULL),
('Ejercicio: escaneo corporal de 3 minutos', 'Guía paso a paso para relajar el cuerpo antes de dormir o antes de un examen.', 'ejercicio', 'relajacion', NULL),
('Línea de Nacer de Nuevo (duelo y pérdidas)', 'Acompañamiento gratuito en procesos de duelo.', 'contacto', 'duelo', 'https://nacerdenuevo.org'),
('Cómo hablar con tus papás de lo que sientes', 'Consejos prácticos para abrir esa conversación sin que se sienta forzada.', 'articulo', 'familia', NULL);

-- ---------------------------------------------------------
-- 16. Insignias otorgadas directamente — la base de datos ya sostiene estos logros
-- (racha real de 10 días, meta completada real, cuenta con 40 días de antigüedad),
-- así que dejarlas otorgadas de una vez ahorra tener que visitar cada página para
-- que evaluarInsignias() las detecte. No son insignias "de mentira": los datos que
-- las justifican están sembrados arriba.
-- ---------------------------------------------------------
INSERT INTO usuario_insignias (usuario_id, insignia_id, fecha_obtenida)
SELECT (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), id, NOW() - INTERVAL 1 DAY FROM insignias WHERE criterio='racha_habito_7'
UNION ALL
SELECT (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), id, NOW() - INTERVAL 5 DAY FROM insignias WHERE criterio='primer_mes'
UNION ALL
SELECT (SELECT id FROM usuarios WHERE correo='estudiante@serena.edu'), id, NOW() - INTERVAL 20 DAY FROM insignias WHERE criterio='primera_meta_completada'
ON DUPLICATE KEY UPDATE fecha_obtenida = VALUES(fecha_obtenida);

-- =========================================================
-- Fin de datos de demo.
--
-- Cuentas para probar (todas con contraseña serena2024):
--   admin@serena.edu          — Administrador
--   psicologo@serena.edu      — Dra. García (psicóloga)
--   orientador@serena.edu     — Prof. Martínez (orientador, grupo Sexto A)
--   docente@serena.edu        — Prof. Ana Ríos (docente, grupo Sexto A)
--   coordinador@serena.edu    — Lic. Pedro Salas
--   rector@serena.edu         — Rectora Elena Vidal
--   estudiante@serena.edu     — cuenta demo con TODO poblado (insignias, metas
--                                en los 3 estados, racha, diario, chat)
--   mateo.londono@serena.edu  — estudiante con alerta temprana activa
--   + 10 estudiantes más repartidos en Sexto A, Sexto B y Séptimo A
--
-- Para regenerar el historial (emociones/sueño/hábitos/chat) sin duplicar,
-- borra primero en este orden y vuelve a correr el script:
--   DELETE FROM mensajes; DELETE FROM conversaciones;
--   DELETE FROM seguimiento_habitos; DELETE FROM estado_emocional; DELETE FROM sueno;
-- =========================================================
