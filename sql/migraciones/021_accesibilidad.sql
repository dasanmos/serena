-- 021_accesibilidad.sql
-- Preferencias de accesibilidad, controladas desde public/preferencias.php
-- igual que tema_preferido (ver 018_preferencias_cuenta.sql):
--   - tamano_texto: normal/grande/muy_grande. Escala la tipografía de toda la
--     plataforma (todo el CSS usa rem sobre --texto-*, así que basta con
--     cambiar el font-size de <html> — ver html[data-tamano] en base.css).
--   - alto_contraste: activa una paleta de mayor contraste (ver
--     html[data-contraste="alto"] en base.css) para quienes necesiten
--     distinguir mejor el texto y los bordes.
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

ALTER TABLE usuarios
    ADD COLUMN tamano_texto ENUM('normal', 'grande', 'muy_grande') NOT NULL DEFAULT 'normal' AFTER tema_preferido,
    ADD COLUMN alto_contraste TINYINT(1) NOT NULL DEFAULT 0 AFTER tamano_texto;
