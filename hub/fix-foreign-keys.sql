-- Script para solucionar problemas de foreign keys en Replanta Hub
-- Ejecutar en la base de datos replanta_sitios

-- Paso 1: Desactivar verificación de foreign keys
SET FOREIGN_KEY_CHECKS = 0;

-- Paso 2: Obtener y eliminar todas las foreign keys existentes (ejecutar una por una si es necesario)
ALTER TABLE sites_rphub_activities DROP FOREIGN KEY sites_rphub_activities_ibfk_1;
ALTER TABLE sites_rphub_backups DROP FOREIGN KEY sites_rphub_backups_ibfk_1;
ALTER TABLE sites_rphub_notifications DROP FOREIGN KEY sites_rphub_notifications_ibfk_1;

-- Paso 3: Si las foreign keys no se pueden eliminar, eliminar las tablas completamente
-- EJECUTAR SOLO SI EL PASO 2 NO FUNCIONA:
-- DROP TABLE IF EXISTS sites_rphub_activities;
-- DROP TABLE IF EXISTS sites_rphub_backups;
-- DROP TABLE IF EXISTS sites_rphub_notifications;
-- DROP TABLE IF EXISTS sites_rphub_sites;
-- DROP TABLE IF EXISTS sites_rphub_plans;
-- DROP TABLE IF EXISTS sites_rphub_plan_features;
-- DROP TABLE IF EXISTS sites_rphub_site_plans;

-- Paso 4: Reactivar verificación de foreign keys
SET FOREIGN_KEY_CHECKS = 1;

-- Paso 5: Después de ejecutar este script, ve al admin de WordPress y ejecuta "Crear Tablas de Base de Datos"

-- ========================================
-- ALTERNATIVA RADICAL SI TODO FALLA:
-- ========================================
-- Si nada funciona, ejecuta estas líneas una por una:

-- SET FOREIGN_KEY_CHECKS = 0;
-- DROP TABLE sites_rphub_activities;
-- DROP TABLE sites_rphub_backups;
-- DROP TABLE sites_rphub_notifications;
-- DROP TABLE sites_rphub_sites;
-- DROP TABLE sites_rphub_plans;
-- DROP TABLE sites_rphub_plan_features;
-- DROP TABLE sites_rphub_site_plans;
-- SET FOREIGN_KEY_CHECKS = 1;
