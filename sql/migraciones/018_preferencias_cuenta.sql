-- 018_preferencias_cuenta.sql
-- Preferencias de cuenta que cada persona controla desde public/preferencias.php:
--   - resumen_semanal_activo: si se le sigue enviando el resumen semanal por
--     correo (ver src/helpers/resumen_semanal.php). Antes de esta migración
--     se enviaba siempre a todo el mundo, sin forma de desactivarlo.
--   - tema_preferido: claro/oscuro/sistema. Antes vivía solo en localStorage
--     del navegador (serena_tema) y no viajaba entre dispositivos; ahora se
--     guarda también en el servidor y localStorage queda como caché rápida
--     para evitar parpadeo antes de que cargue la página.
-- Aplicar sobre una base de datos que ya tiene sql/schema.sql cargado.

ALTER TABLE usuarios
    ADD COLUMN resumen_semanal_activo TINYINT(1) NOT NULL DEFAULT 1 AFTER ultimo_resumen_semanal,
    ADD COLUMN tema_preferido ENUM('claro', 'oscuro', 'sistema') NOT NULL DEFAULT 'sistema' AFTER resumen_semanal_activo;
