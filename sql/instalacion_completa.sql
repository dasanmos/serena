-- =====================================================================
-- SERENA — instalación completa de la base de datos (una sola vez)
-- =====================================================================
-- Úsalo para una base de datos NUEVA: trae las 110 tablas de la versión
-- actual (equivale a schema.sql + todas las migraciones de sql/migraciones)
-- y los datos mínimos: 11 roles, insignias, un colegio inicial y sus grados.
--
-- Cómo usarlo (phpMyAdmin):
--   1. Crea una base de datos vacía (utf8mb4) y selecciónala.
--   2. Pestaña «Importar» → elige este archivo → Continuar.
--   3. Crea la cuenta maestra: tools/crear_maestro.php (ver docs/INSTALACION.md).
--
-- Si ya tienes Serena instalado NO uses este archivo: aplica solo las
-- migraciones que te falten, en el orden de sql/migraciones/ORDEN.md.
-- =====================================================================

/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `accesos_ficha`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accesos_ficha` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_acceso_estudiante` (`estudiante_id`,`fecha`),
  KEY `idx_acceso_usuario` (`usuario_id`,`fecha`),
  CONSTRAINT `accesos_ficha_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accesos_ficha_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `accesos_ficha` DISABLE KEYS */;
/*!40000 ALTER TABLE `accesos_ficha` ENABLE KEYS */;
DROP TABLE IF EXISTS `actividades_evaluacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `actividades_evaluacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asignacion_id` int(11) NOT NULL,
  `periodo_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `peso` decimal(5,2) NOT NULL DEFAULT 0.00,
  `componente_id` int(11) DEFAULT NULL,
  `logro_id` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_act_asignacion` (`asignacion_id`,`periodo_id`),
  KEY `fk_act_periodo` (`periodo_id`),
  KEY `fk_act_componente` (`componente_id`),
  KEY `fk_act_logro` (`logro_id`),
  CONSTRAINT `fk_act_asignacion` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones_academicas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_act_componente` FOREIGN KEY (`componente_id`) REFERENCES `componentes_evaluacion` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_act_logro` FOREIGN KEY (`logro_id`) REFERENCES `logros` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_act_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `actividades_evaluacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `actividades_evaluacion` ENABLE KEYS */;
DROP TABLE IF EXISTS `actuaciones_disciplinarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `actuaciones_disciplinarias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proceso_id` int(11) NOT NULL,
  `autor_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(22) NOT NULL DEFAULT 'otra',
  `detalle` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ad_proceso` (`proceso_id`),
  KEY `fk_ad_autor` (`autor_id`),
  CONSTRAINT `fk_ad_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ad_proceso` FOREIGN KEY (`proceso_id`) REFERENCES `procesos_disciplinarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `actuaciones_disciplinarias` DISABLE KEYS */;
/*!40000 ALTER TABLE `actuaciones_disciplinarias` ENABLE KEYS */;
DROP TABLE IF EXISTS `acudientes_estudiantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `acudientes_estudiantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `acudiente_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `parentesco` varchar(30) NOT NULL DEFAULT 'Acudiente',
  `principal` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `acudiente_estudiante_unico` (`acudiente_id`,`estudiante_id`),
  KEY `fk_ae_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_ae_acudiente` FOREIGN KEY (`acudiente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ae_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `acudientes_estudiantes` DISABLE KEYS */;
/*!40000 ALTER TABLE `acudientes_estudiantes` ENABLE KEYS */;
DROP TABLE IF EXISTS `alertas_tempranas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alertas_tempranas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `nivel_urgencia` enum('bajo','medio','alto') NOT NULL DEFAULT 'medio',
  `atendida` tinyint(1) NOT NULL DEFAULT 0,
  `atendida_por` int(11) DEFAULT NULL,
  `fecha_generada` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_atendida` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `atendida_por` (`atendida_por`),
  KEY `idx_alertas_usuario` (`usuario_id`,`atendida`),
  CONSTRAINT `alertas_tempranas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alertas_tempranas_ibfk_2` FOREIGN KEY (`atendida_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `alertas_tempranas` DISABLE KEYS */;
/*!40000 ALTER TABLE `alertas_tempranas` ENABLE KEYS */;
DROP TABLE IF EXISTS `analisis_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analisis_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `texto` text NOT NULL,
  `fecha_generado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_fecha_unico` (`usuario_id`,`fecha`),
  CONSTRAINT `analisis_cache_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `analisis_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `analisis_cache` ENABLE KEYS */;
DROP TABLE IF EXISTS `anios_lectivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `anios_lectivos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio` smallint(6) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `inscripciones_abiertas` tinyint(1) NOT NULL DEFAULT 0,
  `renovacion_abierta` tinyint(1) NOT NULL DEFAULT 0,
  `actual` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `anio_colegio_unico` (`colegio_id`,`anio`),
  CONSTRAINT `fk_anios_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `anios_lectivos` DISABLE KEYS */;
/*!40000 ALTER TABLE `anios_lectivos` ENABLE KEYS */;
DROP TABLE IF EXISTS `apoyo_seguimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `apoyo_seguimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apoyo_id` int(11) NOT NULL,
  `autor_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `nota` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `observacion` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_as_apoyo` (`apoyo_id`),
  KEY `fk_as_autor` (`autor_id`),
  CONSTRAINT `fk_as_apoyo` FOREIGN KEY (`apoyo_id`) REFERENCES `apoyos_pedagogicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_as_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `apoyo_seguimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `apoyo_seguimientos` ENABLE KEYS */;
DROP TABLE IF EXISTS `apoyos_pedagogicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `apoyos_pedagogicos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `estado` varchar(8) NOT NULL DEFAULT 'activo',
  `condicion` varchar(255) DEFAULT NULL,
  `barreras` text DEFAULT NULL,
  `ajustes` text DEFAULT NULL,
  `estrategias` text DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `piar_fecha` date DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `apoyo_estudiante_unico` (`estudiante_id`),
  KEY `idx_ap_colegio` (`colegio_id`,`estado`),
  KEY `fk_ap_responsable` (`responsable_id`),
  CONSTRAINT `fk_ap_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ap_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ap_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `apoyos_pedagogicos` DISABLE KEYS */;
/*!40000 ALTER TABLE `apoyos_pedagogicos` ENABLE KEYS */;
DROP TABLE IF EXISTS `aprobaciones_anticipadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `aprobaciones_anticipadas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) DEFAULT NULL,
  `grado_origen_id` int(11) DEFAULT NULL,
  `grado_destino_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `acta` varchar(60) DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aa_estudiante` (`estudiante_id`),
  KEY `fk_aa_colegio` (`colegio_id`),
  KEY `fk_aa_anio` (`anio_lectivo_id`),
  KEY `fk_aa_origen` (`grado_origen_id`),
  KEY `fk_aa_destino` (`grado_destino_id`),
  CONSTRAINT `fk_aa_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aa_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aa_destino` FOREIGN KEY (`grado_destino_id`) REFERENCES `grados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aa_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aa_origen` FOREIGN KEY (`grado_origen_id`) REFERENCES `grados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `aprobaciones_anticipadas` DISABLE KEYS */;
/*!40000 ALTER TABLE `aprobaciones_anticipadas` ENABLE KEYS */;
DROP TABLE IF EXISTS `asignaciones_academicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignaciones_academicas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `asignatura_id` int(11) NOT NULL,
  `docente_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asignacion_unica` (`anio_lectivo_id`,`grupo_id`,`asignatura_id`),
  KEY `idx_asignaciones_docente` (`docente_id`,`anio_lectivo_id`),
  KEY `fk_asgn_colegio` (`colegio_id`),
  KEY `fk_asgn_grupo` (`grupo_id`),
  KEY `fk_asgn_asignatura` (`asignatura_id`),
  CONSTRAINT `fk_asgn_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asgn_asignatura` FOREIGN KEY (`asignatura_id`) REFERENCES `asignaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asgn_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asgn_docente` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asgn_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asignaciones_academicas` DISABLE KEYS */;
/*!40000 ALTER TABLE `asignaciones_academicas` ENABLE KEYS */;
DROP TABLE IF EXISTS `asignaturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignaturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `area` varchar(80) DEFAULT NULL,
  `intensidad` tinyint(3) unsigned DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asignatura_unica` (`colegio_id`,`nombre`),
  CONSTRAINT `fk_asig_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asignaturas` DISABLE KEYS */;
/*!40000 ALTER TABLE `asignaturas` ENABLE KEYS */;
DROP TABLE IF EXISTS `asistencia_registros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asistencia_registros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `asignacion_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` varchar(10) NOT NULL DEFAULT 'presente',
  `observacion` varchar(255) DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `registrado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asistencia_unica` (`asignacion_id`,`fecha`,`estudiante_id`),
  KEY `idx_asistencia_estudiante` (`estudiante_id`,`anio_lectivo_id`,`fecha`),
  KEY `idx_asistencia_colegio_fecha` (`colegio_id`,`anio_lectivo_id`,`fecha`),
  KEY `fk_asist_anio` (`anio_lectivo_id`),
  KEY `fk_asist_registrador` (`registrado_por`),
  CONSTRAINT `fk_asist_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asist_asignacion` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones_academicas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asist_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asist_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asist_registrador` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `asistencia_registros` DISABLE KEYS */;
/*!40000 ALTER TABLE `asistencia_registros` ENABLE KEYS */;
DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `tabla_afectada` varchar(50) NOT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_anteriores`)),
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_nuevos`)),
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `colegio_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_auditoria_tabla` (`tabla_afectada`,`registro_id`),
  KEY `idx_auditoria_colegio` (`colegio_id`),
  CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
/*!40000 ALTER TABLE `auditoria` ENABLE KEYS */;
DROP TABLE IF EXISTS `calificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `calificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actividad_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `valor` decimal(5,2) DEFAULT NULL,
  `nivel_id` int(11) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `calificacion_unica` (`actividad_id`,`estudiante_id`),
  KEY `fk_cal_estudiante` (`estudiante_id`),
  KEY `fk_cal_nivel` (`nivel_id`),
  CONSTRAINT `fk_cal_actividad` FOREIGN KEY (`actividad_id`) REFERENCES `actividades_evaluacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cal_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cal_nivel` FOREIGN KEY (`nivel_id`) REFERENCES `escala_niveles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `calificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `calificaciones` ENABLE KEYS */;
DROP TABLE IF EXISTS `caracterizacion_estudiante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `caracterizacion_estudiante` (
  `estudiante_id` int(11) NOT NULL,
  `colegio_id` int(11) NOT NULL,
  `victima_conflicto` tinyint(1) NOT NULL DEFAULT 0,
  `hecho_victimizante` varchar(80) DEFAULT NULL,
  `poblacion_etnica` varchar(40) DEFAULT NULL,
  `sisben` varchar(10) DEFAULT NULL,
  `estrato` tinyint(3) unsigned DEFAULT NULL,
  `observacion` varchar(300) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`estudiante_id`),
  KEY `idx_caract_colegio` (`colegio_id`),
  CONSTRAINT `fk_caract_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_caract_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `caracterizacion_estudiante` DISABLE KEYS */;
/*!40000 ALTER TABLE `caracterizacion_estudiante` ENABLE KEYS */;
DROP TABLE IF EXISTS `cartera_cargos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cartera_cargos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `concepto` varchar(80) NOT NULL,
  `mes` tinyint(3) unsigned DEFAULT NULL,
  `valor` decimal(12,2) NOT NULL,
  `vence` date DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cartera_cargos` (`colegio_id`,`anio_lectivo_id`,`estudiante_id`),
  KEY `fk_carc_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_carc_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_carc_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `cartera_cargos` DISABLE KEYS */;
/*!40000 ALTER TABLE `cartera_cargos` ENABLE KEYS */;
DROP TABLE IF EXISTS `cartera_pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cartera_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `cargo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `fecha` date NOT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `recibo` varchar(30) DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cartera_pagos` (`cargo_id`),
  KEY `idx_cartera_pagos_colegio` (`colegio_id`,`estudiante_id`),
  CONSTRAINT `fk_carp_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cartera_cargos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_carp_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `cartera_pagos` DISABLE KEYS */;
/*!40000 ALTER TABLE `cartera_pagos` ENABLE KEYS */;
DROP TABLE IF EXISTS `certificados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `codigo` char(12) NOT NULL,
  `tipo` varchar(15) NOT NULL,
  `estudiante_id` int(11) DEFAULT NULL,
  `anio_lectivo_id` int(11) DEFAULT NULL,
  `datos` mediumtext NOT NULL,
  `huella` char(64) NOT NULL,
  `emitido_por` int(11) DEFAULT NULL,
  `emitido_en` datetime NOT NULL DEFAULT current_timestamp(),
  `vence_en` date DEFAULT NULL,
  `estado` varchar(8) NOT NULL DEFAULT 'vigente',
  `anulado_por` int(11) DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `anulado_motivo` varchar(255) DEFAULT NULL,
  `descargas` int(10) unsigned NOT NULL DEFAULT 0,
  `verificaciones` int(10) unsigned NOT NULL DEFAULT 0,
  `ultima_verificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificado_codigo_unico` (`codigo`),
  KEY `idx_cert_colegio` (`colegio_id`,`emitido_en`),
  KEY `idx_cert_estudiante` (`estudiante_id`),
  KEY `fk_cert_anio` (`anio_lectivo_id`),
  KEY `fk_cert_emisor` (`emitido_por`),
  KEY `fk_cert_anulador` (`anulado_por`),
  CONSTRAINT `fk_cert_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cert_anulador` FOREIGN KEY (`anulado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cert_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cert_emisor` FOREIGN KEY (`emitido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cert_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `certificados` DISABLE KEYS */;
/*!40000 ALTER TABLE `certificados` ENABLE KEYS */;
DROP TABLE IF EXISTS `citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `citas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesional_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `estado` enum('confirmada','cancelada','atendida') NOT NULL DEFAULT 'confirmada',
  `recordatorio_enviado` tinyint(1) NOT NULL DEFAULT 0,
  `cancelado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `franja_unica` (`profesional_id`,`fecha`,`hora_inicio`),
  KEY `cancelado_por` (`cancelado_por`),
  KEY `idx_citas_estudiante` (`estudiante_id`),
  KEY `idx_citas_profesional_fecha` (`profesional_id`,`fecha`),
  CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`profesional_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `citas_ibfk_3` FOREIGN KEY (`cancelado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `citas` DISABLE KEYS */;
/*!40000 ALTER TABLE `citas` ENABLE KEYS */;
DROP TABLE IF EXISTS `codigos_2fa_correo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `codigos_2fa_correo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `codigo_hash` varchar(255) NOT NULL,
  `creado_en` datetime NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_codigos_2fa_correo_usuario` (`usuario_id`),
  CONSTRAINT `codigos_2fa_correo_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `codigos_2fa_correo` DISABLE KEYS */;
/*!40000 ALTER TABLE `codigos_2fa_correo` ENABLE KEYS */;
DROP TABLE IF EXISTS `codigos_2fa_whatsapp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `codigos_2fa_whatsapp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `codigo_hash` varchar(255) NOT NULL,
  `creado_en` datetime NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_codigos_2fa_whatsapp_usuario` (`usuario_id`),
  CONSTRAINT `codigos_2fa_whatsapp_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `codigos_2fa_whatsapp` DISABLE KEYS */;
/*!40000 ALTER TABLE `codigos_2fa_whatsapp` ENABLE KEYS */;
DROP TABLE IF EXISTS `codigos_recuperacion_2fa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `codigos_recuperacion_2fa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `codigo_hash` varchar(255) NOT NULL,
  `creado_en` datetime NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_codigos_2fa_usuario` (`usuario_id`,`usado_en`,`expira_en`),
  CONSTRAINT `codigos_recuperacion_2fa_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `codigos_recuperacion_2fa` DISABLE KEYS */;
/*!40000 ALTER TABLE `codigos_recuperacion_2fa` ENABLE KEYS */;
DROP TABLE IF EXISTS `colegios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `colegios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `dane` varchar(12) NOT NULL,
  `municipio` varchar(80) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `latitud` decimal(9,6) DEFAULT NULL,
  `longitud` decimal(9,6) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `secretaria_id` int(11) DEFAULT NULL,
  `municipio_id` int(11) DEFAULT NULL,
  `nucleo_id` int(11) DEFAULT NULL,
  `zona_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `colegios_dane_unico` (`dane`),
  KEY `idx_colegios_secretaria` (`secretaria_id`),
  KEY `fk_colegios_municipio` (`municipio_id`),
  KEY `fk_colegios_nucleo` (`nucleo_id`),
  KEY `fk_colegios_zona` (`zona_id`),
  CONSTRAINT `fk_colegios_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_colegios_nucleo` FOREIGN KEY (`nucleo_id`) REFERENCES `nucleos_educativos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_colegios_secretaria` FOREIGN KEY (`secretaria_id`) REFERENCES `secretarias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_colegios_zona` FOREIGN KEY (`zona_id`) REFERENCES `zonas_municipio` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `colegios` DISABLE KEYS */;
INSERT INTO `colegios` VALUES
(1,'Colegio principal','000000000000','Medellín',NULL,NULL,NULL,1,'2026-10-19 05:16:26',1,NULL,NULL,NULL);
/*!40000 ALTER TABLE `colegios` ENABLE KEYS */;
DROP TABLE IF EXISTS `componentes_evaluacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `componentes_evaluacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `peso` decimal(5,2) NOT NULL DEFAULT 0.00,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_comp_colegio` (`colegio_id`),
  CONSTRAINT `fk_comp_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `componentes_evaluacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `componentes_evaluacion` ENABLE KEYS */;
DROP TABLE IF EXISTS `comunicado_envios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comunicado_envios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comunicado_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `colegio_id` int(11) DEFAULT NULL,
  `correo` varchar(150) NOT NULL,
  `estado` enum('pendiente','enviado','fallido') NOT NULL DEFAULT 'pendiente',
  `enviado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_envios_comunicado` (`comunicado_id`,`estado`),
  CONSTRAINT `comunicado_envios_ibfk_1` FOREIGN KEY (`comunicado_id`) REFERENCES `comunicados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comunicado_envios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `comunicado_envios` DISABLE KEYS */;
/*!40000 ALTER TABLE `comunicado_envios` ENABLE KEYS */;
DROP TABLE IF EXISTS `comunicados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comunicados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asunto` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `alcance` enum('todos','seleccion') NOT NULL DEFAULT 'todos',
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `comunicados_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `comunicados` DISABLE KEYS */;
/*!40000 ALTER TABLE `comunicados` ENABLE KEYS */;
DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion` (
  `colegio_id` int(11) NOT NULL DEFAULT 0,
  `clave` varchar(60) NOT NULL,
  `valor` text NOT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`colegio_id`,`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `configuracion` DISABLE KEYS */;
/*!40000 ALTER TABLE `configuracion` ENABLE KEYS */;
DROP TABLE IF EXISTS `configuracion_evaluacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_evaluacion` (
  `colegio_id` int(11) NOT NULL,
  `estructura` varchar(20) NOT NULL DEFAULT 'actividades',
  `escala_id` int(11) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`colegio_id`),
  KEY `fk_confeval_escala` (`escala_id`),
  CONSTRAINT `fk_confeval_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_confeval_escala` FOREIGN KEY (`escala_id`) REFERENCES `escalas_evaluacion` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `configuracion_evaluacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `configuracion_evaluacion` ENABLE KEYS */;
DROP TABLE IF EXISTS `contactos_emergencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contactos_emergencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estudiante_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `parentesco` varchar(40) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `autorizado_recoger` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ce_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_ce_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `contactos_emergencia` DISABLE KEYS */;
/*!40000 ALTER TABLE `contactos_emergencia` ENABLE KEYS */;
DROP TABLE IF EXISTS `conversaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_a_id` int(11) NOT NULL,
  `usuario_b_id` int(11) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversacion_unica` (`usuario_a_id`,`usuario_b_id`),
  KEY `usuario_b_id` (`usuario_b_id`),
  CONSTRAINT `conversaciones_ibfk_1` FOREIGN KEY (`usuario_a_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversaciones_ibfk_2` FOREIGN KEY (`usuario_b_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `conversaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversaciones` ENABLE KEYS */;
DROP TABLE IF EXISTS `desarrollo_estudiante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `desarrollo_estudiante` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estudiante_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `semestre` tinyint(4) NOT NULL,
  `criterio` varchar(30) NOT NULL,
  `valoracion` varchar(12) DEFAULT NULL,
  `tratamiento_familiar` varchar(300) DEFAULT NULL,
  `tratamiento_institucional` varchar(300) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `desarrollo_unico` (`estudiante_id`,`anio_lectivo_id`,`semestre`,`criterio`),
  KEY `fk_de_anio` (`anio_lectivo_id`),
  CONSTRAINT `fk_de_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_de_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `desarrollo_estudiante` DISABLE KEYS */;
/*!40000 ALTER TABLE `desarrollo_estudiante` ENABLE KEYS */;
DROP TABLE IF EXISTS `diario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `diario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `titulo` varchar(150) DEFAULT NULL,
  `contenido` text NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `editado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_diario_usuario_fecha` (`usuario_id`,`fecha`),
  CONSTRAINT `diario_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `diario` DISABLE KEYS */;
/*!40000 ALTER TABLE `diario` ENABLE KEYS */;
DROP TABLE IF EXISTS `diario_campo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `diario_campo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `asignacion_id` int(11) DEFAULT NULL,
  `planeacion_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `observaciones` text NOT NULL,
  `dificultades` text DEFAULT NULL,
  `ajustes` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dc_docente` (`docente_id`,`fecha`),
  KEY `fk_dc_colegio` (`colegio_id`),
  KEY `fk_dc_asignacion` (`asignacion_id`),
  KEY `fk_dc_planeacion` (`planeacion_id`),
  CONSTRAINT `fk_dc_asignacion` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones_academicas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dc_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dc_docente` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dc_planeacion` FOREIGN KEY (`planeacion_id`) REFERENCES `planeaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `diario_campo` DISABLE KEYS */;
/*!40000 ALTER TABLE `diario_campo` ENABLE KEYS */;
DROP TABLE IF EXISTS `diario_etiquetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `diario_etiquetas` (
  `diario_id` int(11) NOT NULL,
  `etiqueta_id` int(11) NOT NULL,
  PRIMARY KEY (`diario_id`,`etiqueta_id`),
  KEY `etiqueta_id` (`etiqueta_id`),
  CONSTRAINT `diario_etiquetas_ibfk_1` FOREIGN KEY (`diario_id`) REFERENCES `diario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `diario_etiquetas_ibfk_2` FOREIGN KEY (`etiqueta_id`) REFERENCES `etiquetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `diario_etiquetas` DISABLE KEYS */;
/*!40000 ALTER TABLE `diario_etiquetas` ENABLE KEYS */;
DROP TABLE IF EXISTS `diario_imagenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `diario_imagenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `diario_id` int(11) NOT NULL,
  `ruta_imagen` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `diario_id` (`diario_id`),
  CONSTRAINT `diario_imagenes_ibfk_1` FOREIGN KEY (`diario_id`) REFERENCES `diario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `diario_imagenes` DISABLE KEYS */;
/*!40000 ALTER TABLE `diario_imagenes` ENABLE KEYS */;
DROP TABLE IF EXISTS `disponibilidad_citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `disponibilidad_citas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesional_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `duracion_slot_minutos` smallint(5) unsigned NOT NULL DEFAULT 20,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_disponibilidad_profesional_fecha` (`profesional_id`,`fecha`),
  CONSTRAINT `disponibilidad_citas_ibfk_1` FOREIGN KEY (`profesional_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `disponibilidad_citas` DISABLE KEYS */;
/*!40000 ALTER TABLE `disponibilidad_citas` ENABLE KEYS */;
DROP TABLE IF EXISTS `documentos_emitidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_emitidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` char(12) NOT NULL,
  `colegio_id` int(11) DEFAULT NULL,
  `tipo` varchar(80) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `generado_por` int(11) DEFAULT NULL,
  `rol` varchar(40) DEFAULT NULL,
  `huella` char(64) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `verificaciones` int(10) unsigned NOT NULL DEFAULT 0,
  `ultima_verificacion` datetime DEFAULT NULL,
  `anulado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documentos_codigo` (`codigo`),
  KEY `ix_documentos_colegio` (`colegio_id`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `documentos_emitidos` DISABLE KEYS */;
/*!40000 ALTER TABLE `documentos_emitidos` ENABLE KEYS */;
DROP TABLE IF EXISTS `encuestas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `encuestas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creador_id` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_cierre` datetime DEFAULT NULL,
  `colegio_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `creador_id` (`creador_id`),
  KEY `grupo_id` (`grupo_id`),
  KEY `idx_encuestas_colegio` (`colegio_id`),
  CONSTRAINT `encuestas_ibfk_1` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `encuestas_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `encuestas` DISABLE KEYS */;
/*!40000 ALTER TABLE `encuestas` ENABLE KEYS */;
DROP TABLE IF EXISTS `escala_niveles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `escala_niveles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `escala_id` int(11) NOT NULL,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `nombre` varchar(40) NOT NULL,
  `abreviatura` varchar(6) NOT NULL,
  `desde` decimal(5,2) NOT NULL,
  `hasta` decimal(5,2) NOT NULL,
  `valor_equivalente` decimal(5,2) NOT NULL,
  `aprueba` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_niveles_escala` (`escala_id`),
  CONSTRAINT `fk_niveles_escala` FOREIGN KEY (`escala_id`) REFERENCES `escalas_evaluacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `escala_niveles` DISABLE KEYS */;
/*!40000 ALTER TABLE `escala_niveles` ENABLE KEYS */;
DROP TABLE IF EXISTS `escalas_evaluacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `escalas_evaluacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `tipo` varchar(12) NOT NULL DEFAULT 'numerica',
  `valor_min` decimal(5,2) NOT NULL DEFAULT 1.00,
  `valor_max` decimal(5,2) NOT NULL DEFAULT 5.00,
  `decimales` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_escalas_colegio` (`colegio_id`),
  CONSTRAINT `fk_escalas_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `escalas_evaluacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `escalas_evaluacion` ENABLE KEYS */;
DROP TABLE IF EXISTS `establecimientos_previos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `establecimientos_previos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estudiante_id` int(11) NOT NULL,
  `grado` varchar(30) DEFAULT NULL,
  `anio` smallint(6) DEFAULT NULL,
  `municipio` varchar(100) DEFAULT NULL,
  `dane` varchar(20) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `director_grupo` varchar(120) DEFAULT NULL,
  `causa_cambio` varchar(150) DEFAULT NULL,
  `causa_reprobacion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ep_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_ep_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `establecimientos_previos` DISABLE KEYS */;
/*!40000 ALTER TABLE `establecimientos_previos` ENABLE KEYS */;
DROP TABLE IF EXISTS `estado_emocional`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estado_emocional` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `emocion` varchar(40) NOT NULL,
  `nivel` tinyint(4) NOT NULL,
  `nota` text DEFAULT NULL,
  `momento_dia` enum('mañana','tarde','noche') NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `editado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_emocion_usuario_fecha` (`usuario_id`,`fecha_hora`),
  CONSTRAINT `estado_emocional_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_nivel_emocion` CHECK (`nivel` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `estado_emocional` DISABLE KEYS */;
/*!40000 ALTER TABLE `estado_emocional` ENABLE KEYS */;
DROP TABLE IF EXISTS `etiquetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `etiquetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `etiquetas` DISABLE KEYS */;
/*!40000 ALTER TABLE `etiquetas` ENABLE KEYS */;
DROP TABLE IF EXISTS `familiares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `familiares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estudiante_id` int(11) NOT NULL,
  `parentesco` varchar(12) NOT NULL,
  `vive` char(1) DEFAULT NULL,
  `tipo_documento` varchar(5) DEFAULT NULL,
  `numero_documento` varchar(20) DEFAULT NULL,
  `lugar_expedicion` varchar(100) DEFAULT NULL,
  `fecha_expedicion` date DEFAULT NULL,
  `nacionalidad` varchar(40) DEFAULT NULL,
  `apellido1` varchar(40) DEFAULT NULL,
  `apellido2` varchar(40) DEFAULT NULL,
  `nombre1` varchar(40) DEFAULT NULL,
  `nombre2` varchar(40) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `municipio` varchar(100) DEFAULT NULL,
  `escolaridad` varchar(60) DEFAULT NULL,
  `profesion` varchar(80) DEFAULT NULL,
  `empresa` varchar(100) DEFAULT NULL,
  `cargo` varchar(80) DEFAULT NULL,
  `telefono_trabajo` varchar(20) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `familiar_unico` (`estudiante_id`,`parentesco`),
  CONSTRAINT `fk_fam_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `familiares` DISABLE KEYS */;
/*!40000 ALTER TABLE `familiares` ENABLE KEYS */;
DROP TABLE IF EXISTS `grados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grado_colegio_nombre` (`colegio_id`,`nombre`),
  CONSTRAINT `fk_grados_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `grados` DISABLE KEYS */;
INSERT INTO `grados` VALUES
(5,1,'Décimo'),
(4,1,'Noveno'),
(3,1,'Octavo'),
(2,1,'Séptimo'),
(1,1,'Sexto'),
(6,1,'Undécimo');
/*!40000 ALTER TABLE `grados` ENABLE KEYS */;
DROP TABLE IF EXISTS `grupos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grado_id` int(11) NOT NULL,
  `nombre` varchar(10) NOT NULL,
  `docente_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grado_nombre_unico` (`grado_id`,`nombre`),
  KEY `fk_grupos_docente` (`docente_id`),
  CONSTRAINT `fk_grupos_docente` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `grupos_ibfk_1` FOREIGN KEY (`grado_id`) REFERENCES `grados` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `grupos` DISABLE KEYS */;
INSERT INTO `grupos` VALUES
(1,1,'A',NULL),
(2,1,'B',NULL),
(3,2,'A',NULL),
(4,2,'B',NULL),
(5,3,'A',NULL),
(6,4,'A',NULL),
(7,5,'A',NULL),
(8,6,'A',NULL);
/*!40000 ALTER TABLE `grupos` ENABLE KEYS */;
DROP TABLE IF EXISTS `habitos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `habitos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `frecuencia` enum('diaria','semanal','personalizada') NOT NULL,
  `dias_semana` set('lun','mar','mie','jue','vie','sab','dom') DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_habitos_usuario` (`usuario_id`),
  CONSTRAINT `habitos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `habitos` DISABLE KEYS */;
/*!40000 ALTER TABLE `habitos` ENABLE KEYS */;
DROP TABLE IF EXISTS `hoja_vida_datos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hoja_vida_datos` (
  `estudiante_id` int(11) NOT NULL,
  `clave` varchar(40) NOT NULL,
  `valor` text DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`estudiante_id`,`clave`),
  CONSTRAINT `fk_hvd_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `hoja_vida_datos` DISABLE KEYS */;
/*!40000 ALTER TABLE `hoja_vida_datos` ENABLE KEYS */;
DROP TABLE IF EXISTS `hoja_vida_evidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hoja_vida_evidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `origen` varchar(10) NOT NULL,
  `origen_id` int(11) NOT NULL,
  `archivo` varchar(60) NOT NULL,
  `subido_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hve_origen` (`origen`,`origen_id`),
  KEY `idx_hve_estudiante` (`estudiante_id`),
  KEY `fk_hve_colegio` (`colegio_id`),
  CONSTRAINT `fk_hve_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hve_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `hoja_vida_evidencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `hoja_vida_evidencias` ENABLE KEYS */;
DROP TABLE IF EXISTS `hojas_vida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hojas_vida` (
  `estudiante_id` int(11) NOT NULL,
  `colegio_id` int(11) NOT NULL,
  `tipo_documento` varchar(5) DEFAULT NULL,
  `numero_documento` varchar(20) DEFAULT NULL,
  `genero` varchar(20) DEFAULT NULL,
  `lugar_nacimiento` varchar(100) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `barrio` varchar(80) DEFAULT NULL,
  `telefono_alterno` varchar(20) DEFAULT NULL,
  `vive_con` varchar(120) DEFAULT NULL,
  `eps` varchar(80) DEFAULT NULL,
  `grupo_sanguineo` varchar(4) DEFAULT NULL,
  `alergias` varchar(255) DEFAULT NULL,
  `condiciones_salud` varchar(500) DEFAULT NULL,
  `medicamentos` varchar(255) DEFAULT NULL,
  `desarrollo` text DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`estudiante_id`),
  KEY `idx_hv_colegio` (`colegio_id`),
  CONSTRAINT `fk_hv_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hv_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `hojas_vida` DISABLE KEYS */;
/*!40000 ALTER TABLE `hojas_vida` ENABLE KEYS */;
DROP TABLE IF EXISTS `horario_bloques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `horario_bloques` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `asignacion_id` int(11) NOT NULL,
  `dia` tinyint(3) unsigned NOT NULL,
  `franja_id` int(11) NOT NULL,
  `salon` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bloque_unico` (`asignacion_id`,`dia`,`franja_id`),
  KEY `idx_horario_celda` (`colegio_id`,`anio_lectivo_id`,`dia`,`franja_id`),
  KEY `fk_bloques_anio` (`anio_lectivo_id`),
  KEY `fk_bloques_franja` (`franja_id`),
  CONSTRAINT `fk_bloques_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bloques_asignacion` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones_academicas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bloques_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bloques_franja` FOREIGN KEY (`franja_id`) REFERENCES `horario_franjas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `horario_bloques` DISABLE KEYS */;
/*!40000 ALTER TABLE `horario_bloques` ENABLE KEYS */;
DROP TABLE IF EXISTS `horario_franjas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `horario_franjas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `numero` tinyint(3) unsigned NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `es_descanso` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `franja_unica` (`colegio_id`,`numero`),
  CONSTRAINT `fk_franjas_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `horario_franjas` DISABLE KEYS */;
/*!40000 ALTER TABLE `horario_franjas` ENABLE KEYS */;
DROP TABLE IF EXISTS `horas_formativas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `horas_formativas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `tipo` varchar(15) NOT NULL,
  `fecha` date NOT NULL,
  `horas` decimal(5,1) NOT NULL,
  `actividad` varchar(200) NOT NULL,
  `entidad` varchar(120) DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hf_estudiante` (`estudiante_id`,`tipo`),
  KEY `fk_hf_colegio` (`colegio_id`),
  KEY `fk_hf_registro` (`registrado_por`),
  CONSTRAINT `fk_hf_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hf_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hf_registro` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `horas_formativas` DISABLE KEYS */;
/*!40000 ALTER TABLE `horas_formativas` ENABLE KEYS */;
DROP TABLE IF EXISTS `ia_registro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_registro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `tarea` varchar(40) NOT NULL,
  `modelo` varchar(120) NOT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT 0,
  `http_codigo` smallint(6) NOT NULL DEFAULT 0,
  `ms` int(11) NOT NULL DEFAULT 0,
  `error` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `ia_registro` DISABLE KEYS */;
/*!40000 ALTER TABLE `ia_registro` ENABLE KEYS */;
DROP TABLE IF EXISTS `inasistencias_periodo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inasistencias_periodo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asignacion_id` int(11) NOT NULL,
  `periodo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `ausencias` smallint(5) unsigned NOT NULL DEFAULT 0,
  `excusadas` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inasistencia_unica` (`asignacion_id`,`periodo_id`,`estudiante_id`),
  KEY `fk_ip_periodo` (`periodo_id`),
  KEY `fk_ip_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_ip_asignacion` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones_academicas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ip_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ip_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `inasistencias_periodo` DISABLE KEYS */;
/*!40000 ALTER TABLE `inasistencias_periodo` ENABLE KEYS */;
DROP TABLE IF EXISTS `informes_generados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `informes_generados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `rol` varchar(30) NOT NULL,
  `clave` varchar(40) NOT NULL,
  `formato` varchar(5) NOT NULL DEFAULT 'html',
  `filtros` text DEFAULT NULL,
  `filas` int(10) unsigned NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_informes_colegio` (`colegio_id`,`creado_en`),
  KEY `idx_informes_clave` (`clave`),
  CONSTRAINT `fk_informes_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `informes_generados` DISABLE KEYS */;
/*!40000 ALTER TABLE `informes_generados` ENABLE KEYS */;
DROP TABLE IF EXISTS `inscripciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inscripciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `grado_id` int(11) NOT NULL,
  `codigo` varchar(12) NOT NULL,
  `estado` varchar(15) NOT NULL DEFAULT 'pendiente',
  `nombres` varchar(80) NOT NULL,
  `apellidos` varchar(80) NOT NULL,
  `tipo_documento` varchar(5) NOT NULL,
  `numero_documento` varchar(20) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `correo_estudiante` varchar(150) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `colegio_anterior` varchar(150) DEFAULT NULL,
  `acudiente_nombre` varchar(120) NOT NULL,
  `acudiente_parentesco` varchar(30) NOT NULL,
  `acudiente_documento` varchar(20) NOT NULL,
  `acudiente_correo` varchar(150) NOT NULL,
  `acudiente_telefono` varchar(20) NOT NULL,
  `observaciones` varchar(500) DEFAULT NULL,
  `consentimiento` tinyint(1) NOT NULL DEFAULT 0,
  `consentimiento_en` datetime DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `nota_resolucion` varchar(255) DEFAULT NULL,
  `resuelto_por` int(11) DEFAULT NULL,
  `resuelto_en` datetime DEFAULT NULL,
  `estudiante_id` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `inscripcion_codigo_unico` (`codigo`),
  UNIQUE KEY `inscripcion_documento_anio` (`anio_lectivo_id`,`tipo_documento`,`numero_documento`),
  KEY `idx_inscripciones_colegio_estado` (`colegio_id`,`estado`),
  KEY `idx_inscripciones_ip` (`ip_hash`,`creado_en`),
  KEY `fk_insc_grado` (`grado_id`),
  KEY `fk_insc_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_insc_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_insc_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_insc_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_insc_grado` FOREIGN KEY (`grado_id`) REFERENCES `grados` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `inscripciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `inscripciones` ENABLE KEYS */;
DROP TABLE IF EXISTS `insignias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `insignias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `icono` varchar(255) DEFAULT NULL,
  `criterio` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `criterio_unico` (`criterio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `insignias` DISABLE KEYS */;
INSERT INTO `insignias` VALUES
(1,'Racha de 7 días','Cumpliste un hábito 7 días seguidos.','insignia_racha_7dias.png','racha_habito_7'),
(2,'Primer mes','Llevas un mes usando Serena.','insignia_primer_mes.png','primer_mes'),
(3,'Meta completada','Completaste tu primera meta.','insignia_meta_completada.png','primera_meta_completada'),
(7,'Racha de 30 días','Cumpliste un hábito 30 días seguidos.','insignia_racha_30dias.png','racha_habito_30'),
(8,'Primera entrada','Escribiste tu primera entrada en el diario.','insignia_primer_diario.png','primer_diario'),
(9,'Diez emociones','Registraste 10 veces cómo te sentías.','insignia_10_emociones.png','emociones_10'),
(10,'Siete noches','Registraste tu sueño 7 veces.','insignia_7_noches.png','sueno_7_registros'),
(11,'Cinco metas','Completaste 5 metas.','insignia_5_metas.png','metas_5'),
(12,'Tres meses','Llevas tres meses usando Serena.','insignia_tres_meses.png','tres_meses');
/*!40000 ALTER TABLE `insignias` ENABLE KEYS */;
DROP TABLE IF EXISTS `institucion_perfil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `institucion_perfil` (
  `colegio_id` int(11) NOT NULL,
  `nombre_oficial` varchar(200) DEFAULT NULL,
  `naturaleza` varchar(60) DEFAULT NULL,
  `caracter` varchar(80) DEFAULT NULL,
  `niveles` varchar(200) DEFAULT NULL,
  `jornadas` varchar(100) DEFAULT NULL,
  `calendario` varchar(10) DEFAULT NULL,
  `codigo_dane` varchar(12) DEFAULT NULL,
  `nit` varchar(20) DEFAULT NULL,
  `codigo_icfes` varchar(12) DEFAULT NULL,
  `resolucion_numero` varchar(60) DEFAULT NULL,
  `resolucion_fecha` date DEFAULT NULL,
  `resolucion_entidad` varchar(150) DEFAULT NULL,
  `resoluciones_adicionales` varchar(600) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `municipio` varchar(80) DEFAULT NULL,
  `departamento` varchar(60) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `sitio_web` varchar(150) DEFAULT NULL,
  `ciudad_expedicion` varchar(80) DEFAULT NULL,
  `rector_nombre` varchar(120) DEFAULT NULL,
  `rector_cargo` varchar(60) DEFAULT NULL,
  `secretaria_nombre` varchar(120) DEFAULT NULL,
  `secretaria_cargo` varchar(60) DEFAULT NULL,
  `color_primario` char(7) NOT NULL DEFAULT '#AD6B05',
  `dias_vigencia` smallint(5) unsigned DEFAULT NULL,
  `url_publica` varchar(200) DEFAULT NULL,
  `logo_b64` mediumtext DEFAULT NULL,
  `firma_rector_b64` mediumtext DEFAULT NULL,
  `firma_secretaria_b64` mediumtext DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`colegio_id`),
  CONSTRAINT `fk_instperfil_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `institucion_perfil` DISABLE KEYS */;
/*!40000 ALTER TABLE `institucion_perfil` ENABLE KEYS */;
DROP TABLE IF EXISTS `logros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `logros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asignacion_id` int(11) NOT NULL,
  `periodo_id` int(11) NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `peso` decimal(5,2) NOT NULL DEFAULT 0.00,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_logros_asignacion` (`asignacion_id`,`periodo_id`),
  KEY `fk_logros_periodo` (`periodo_id`),
  CONSTRAINT `fk_logros_asignacion` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones_academicas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_logros_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `logros` DISABLE KEYS */;
/*!40000 ALTER TABLE `logros` ENABLE KEYS */;
DROP TABLE IF EXISTS `lugares_apoyo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lugares_apoyo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo` varchar(30) NOT NULL DEFAULT 'otro',
  `direccion` varchar(200) DEFAULT NULL,
  `municipio` varchar(80) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `horario` varchar(120) DEFAULT NULL,
  `latitud` decimal(9,6) NOT NULL,
  `longitud` decimal(9,6) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lugares_colegio` (`colegio_id`),
  CONSTRAINT `fk_lugares_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `lugares_apoyo` DISABLE KEYS */;
/*!40000 ALTER TABLE `lugares_apoyo` ENABLE KEYS */;
DROP TABLE IF EXISTS `matriculas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `matriculas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `grado_id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `tipo` varchar(12) NOT NULL DEFAULT 'nueva',
  `estado` varchar(12) NOT NULL DEFAULT 'pendiente',
  `inscripcion_id` int(11) DEFAULT NULL,
  `solicitado_por` int(11) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `resuelto_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricula_unica` (`anio_lectivo_id`,`estudiante_id`),
  KEY `idx_matriculas_colegio_estado` (`colegio_id`,`estado`),
  KEY `fk_mat_estudiante` (`estudiante_id`),
  KEY `fk_mat_grado` (`grado_id`),
  KEY `fk_mat_grupo` (`grupo_id`),
  CONSTRAINT `fk_mat_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mat_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mat_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mat_grado` FOREIGN KEY (`grado_id`) REFERENCES `grados` (`id`),
  CONSTRAINT `fk_mat_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `matriculas` DISABLE KEYS */;
/*!40000 ALTER TABLE `matriculas` ENABLE KEYS */;
DROP TABLE IF EXISTS `mensajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mensajes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversacion_id` int(11) NOT NULL,
  `emisor_id` int(11) NOT NULL,
  `contenido` text NOT NULL,
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_envio` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `emisor_id` (`emisor_id`),
  KEY `idx_mensajes_conversacion` (`conversacion_id`,`fecha_envio`),
  CONSTRAINT `mensajes_ibfk_1` FOREIGN KEY (`conversacion_id`) REFERENCES `conversaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mensajes_ibfk_2` FOREIGN KEY (`emisor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `mensajes` DISABLE KEYS */;
/*!40000 ALTER TABLE `mensajes` ENABLE KEYS */;
DROP TABLE IF EXISTS `metas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `metas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `habito_id` int(11) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('corto_plazo','largo_plazo') NOT NULL,
  `fecha_limite` date DEFAULT NULL,
  `estado` enum('activa','completada','vencida') NOT NULL DEFAULT 'activa',
  `porcentaje_avance` tinyint(4) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `habito_id` (`habito_id`),
  KEY `idx_metas_usuario` (`usuario_id`),
  CONSTRAINT `metas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `metas_ibfk_2` FOREIGN KEY (`habito_id`) REFERENCES `habitos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `metas` DISABLE KEYS */;
/*!40000 ALTER TABLE `metas` ENABLE KEYS */;
DROP TABLE IF EXISTS `municipios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `municipios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `departamento` varchar(60) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `codigo_dane` varchar(5) DEFAULT NULL,
  `secretaria_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `municipios_unico` (`departamento`,`nombre`),
  KEY `idx_municipios_secretaria` (`secretaria_id`),
  CONSTRAINT `fk_municipios_secretaria` FOREIGN KEY (`secretaria_id`) REFERENCES `secretarias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `municipios` DISABLE KEYS */;
/*!40000 ALTER TABLE `municipios` ENABLE KEYS */;
DROP TABLE IF EXISTS `notas_periodo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notas_periodo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asignacion_id` int(11) NOT NULL,
  `periodo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `valor` decimal(5,2) DEFAULT NULL,
  `nivel_id` int(11) DEFAULT NULL,
  `observacion` varchar(500) DEFAULT NULL,
  `calculado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nota_periodo_unica` (`asignacion_id`,`periodo_id`,`estudiante_id`),
  KEY `idx_notas_estudiante` (`estudiante_id`,`periodo_id`),
  KEY `fk_np_periodo` (`periodo_id`),
  KEY `fk_np_nivel` (`nivel_id`),
  CONSTRAINT `fk_np_asignacion` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones_academicas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_np_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_np_nivel` FOREIGN KEY (`nivel_id`) REFERENCES `escala_niveles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_np_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `notas_periodo` DISABLE KEYS */;
/*!40000 ALTER TABLE `notas_periodo` ENABLE KEYS */;
DROP TABLE IF EXISTS `notas_sesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notas_sesion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `autor_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `contenido` text NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `autor_id` (`autor_id`),
  KEY `idx_notas_estudiante` (`estudiante_id`,`autor_id`),
  CONSTRAINT `notas_sesion_ibfk_1` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notas_sesion_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `notas_sesion` DISABLE KEYS */;
/*!40000 ALTER TABLE `notas_sesion` ENABLE KEYS */;
DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `mensaje` varchar(255) NOT NULL,
  `url_destino` varchar(255) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notificaciones_usuario` (`usuario_id`,`leida`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
DROP TABLE IF EXISTS `nucleos_educativos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleos_educativos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `secretaria_id` int(11) NOT NULL,
  `municipio_id` int(11) DEFAULT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nucleos_unico` (`secretaria_id`,`nombre`),
  KEY `idx_nucleos_municipio` (`municipio_id`),
  CONSTRAINT `fk_nucleos_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_nucleos_secretaria` FOREIGN KEY (`secretaria_id`) REFERENCES `secretarias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `nucleos_educativos` DISABLE KEYS */;
/*!40000 ALTER TABLE `nucleos_educativos` ENABLE KEYS */;
DROP TABLE IF EXISTS `observador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `observador` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `autor_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(12) NOT NULL DEFAULT 'anecdota',
  `descripcion` text NOT NULL,
  `compromiso` varchar(500) DEFAULT NULL,
  `visible_familia` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `folio` int(11) DEFAULT NULL,
  `situacion` varchar(3) DEFAULT NULL,
  `tratamiento_inmediato` varchar(500) DEFAULT NULL,
  `pasado_proceso_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_obs_estudiante` (`estudiante_id`,`fecha`),
  KEY `fk_obs_colegio` (`colegio_id`),
  KEY `fk_obs_autor` (`autor_id`),
  CONSTRAINT `fk_obs_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_obs_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_obs_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `observador` DISABLE KEYS */;
/*!40000 ALTER TABLE `observador` ENABLE KEYS */;
DROP TABLE IF EXISTS `observador_revisiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `observador_revisiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `observaciones` text NOT NULL,
  `coordinador_id` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_or_estudiante` (`estudiante_id`,`fecha`),
  KEY `fk_or_colegio` (`colegio_id`),
  KEY `fk_or_coordinador` (`coordinador_id`),
  CONSTRAINT `fk_or_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_or_coordinador` FOREIGN KEY (`coordinador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_or_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `observador_revisiones` DISABLE KEYS */;
/*!40000 ALTER TABLE `observador_revisiones` ENABLE KEYS */;
DROP TABLE IF EXISTS `opciones_pregunta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `opciones_pregunta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pregunta_id` int(11) NOT NULL,
  `texto` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pregunta_id` (`pregunta_id`),
  CONSTRAINT `opciones_pregunta_ibfk_1` FOREIGN KEY (`pregunta_id`) REFERENCES `preguntas_encuesta` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `opciones_pregunta` DISABLE KEYS */;
/*!40000 ALTER TABLE `opciones_pregunta` ENABLE KEYS */;
DROP TABLE IF EXISTS `periodos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `anio_lectivo_id` int(11) NOT NULL,
  `numero` tinyint(3) unsigned NOT NULL,
  `nombre` varchar(40) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `peso` decimal(5,2) NOT NULL DEFAULT 25.00,
  `boletin_publicado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `periodo_unico` (`anio_lectivo_id`,`numero`),
  CONSTRAINT `fk_periodos_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `periodos` DISABLE KEYS */;
/*!40000 ALTER TABLE `periodos` ENABLE KEYS */;
DROP TABLE IF EXISTS `personal_grupos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_grupos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `asignado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_grupo_unico` (`usuario_id`,`grupo_id`),
  KEY `grupo_id` (`grupo_id`),
  CONSTRAINT `personal_grupos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personal_grupos_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `personal_grupos` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_grupos` ENABLE KEYS */;
DROP TABLE IF EXISTS `planeaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planeaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `asignacion_id` int(11) NOT NULL,
  `periodo_id` int(11) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `tema` varchar(150) NOT NULL,
  `estandar` varchar(500) DEFAULT NULL,
  `objetivos` text DEFAULT NULL,
  `actividades` text DEFAULT NULL,
  `recursos` varchar(500) DEFAULT NULL,
  `evaluacion` varchar(500) DEFAULT NULL,
  `estado` varchar(9) NOT NULL DEFAULT 'borrador',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_plan_docente` (`docente_id`,`fecha_inicio`),
  KEY `fk_plan_colegio` (`colegio_id`),
  KEY `fk_plan_asignacion` (`asignacion_id`),
  KEY `fk_plan_periodo` (`periodo_id`),
  CONSTRAINT `fk_plan_asignacion` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones_academicas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plan_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plan_docente` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plan_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `planeaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `planeaciones` ENABLE KEYS */;
DROP TABLE IF EXISTS `planes_mejoramiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planes_mejoramiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `periodo_id` int(11) DEFAULT NULL,
  `estudiante_id` int(11) NOT NULL,
  `asignacion_id` int(11) DEFAULT NULL,
  `estado` varchar(10) NOT NULL DEFAULT 'activo',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `nota_inicial` decimal(5,2) DEFAULT NULL,
  `nota_final` decimal(5,2) DEFAULT NULL,
  `compromisos` text DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_planes_colegio` (`colegio_id`,`anio_lectivo_id`,`estado`),
  KEY `fk_planes_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_planes_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_planes_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `planes_mejoramiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `planes_mejoramiento` ENABLE KEYS */;
DROP TABLE IF EXISTS `planes_seguimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planes_seguimiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `autor_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `fecha_limite` date DEFAULT NULL,
  `estado` enum('pendiente','en_curso','completado') NOT NULL DEFAULT 'pendiente',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_completado` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plan_autor` (`autor_id`,`estado`),
  KEY `idx_plan_estudiante` (`estudiante_id`,`autor_id`),
  CONSTRAINT `planes_seguimiento_ibfk_1` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `planes_seguimiento_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `planes_seguimiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `planes_seguimiento` ENABLE KEYS */;
DROP TABLE IF EXISTS `plantillas_certificado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plantillas_certificado` (
  `colegio_id` int(11) NOT NULL,
  `tipo` varchar(15) NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `cuerpo` text NOT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`colegio_id`,`tipo`),
  CONSTRAINT `fk_plantcert_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `plantillas_certificado` DISABLE KEYS */;
/*!40000 ALTER TABLE `plantillas_certificado` ENABLE KEYS */;
DROP TABLE IF EXISTS `preguntas_encuesta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `preguntas_encuesta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `encuesta_id` int(11) NOT NULL,
  `texto` varchar(255) NOT NULL,
  `tipo` enum('opcion_multiple','texto_libre','escala') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `encuesta_id` (`encuesta_id`),
  CONSTRAINT `preguntas_encuesta_ibfk_1` FOREIGN KEY (`encuesta_id`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `preguntas_encuesta` DISABLE KEYS */;
/*!40000 ALTER TABLE `preguntas_encuesta` ENABLE KEYS */;
DROP TABLE IF EXISTS `procesos_disciplinarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `procesos_disciplinarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `abierto_por` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `situacion_tipo` varchar(3) NOT NULL DEFAULT 'I',
  `descripcion` text NOT NULL,
  `estado` varchar(12) NOT NULL DEFAULT 'abierto',
  `decision` text DEFAULT NULL,
  `cerrado_en` datetime DEFAULT NULL,
  `cerrado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `folio` int(11) DEFAULT NULL,
  `tratamiento_inmediato` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pd_estudiante` (`estudiante_id`),
  KEY `idx_pd_colegio_estado` (`colegio_id`,`estado`),
  KEY `fk_pd_abierto` (`abierto_por`),
  KEY `fk_pd_cerrado` (`cerrado_por`),
  CONSTRAINT `fk_pd_abierto` FOREIGN KEY (`abierto_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pd_cerrado` FOREIGN KEY (`cerrado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pd_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `procesos_disciplinarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `procesos_disciplinarios` ENABLE KEYS */;
DROP TABLE IF EXISTS `pulso_respuestas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pulso_respuestas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pulso_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `valor` tinyint(4) NOT NULL,
  `fecha_respuesta` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unico_pulso_estudiante` (`pulso_id`,`estudiante_id`),
  KEY `estudiante_id` (`estudiante_id`),
  CONSTRAINT `pulso_respuestas_ibfk_1` FOREIGN KEY (`pulso_id`) REFERENCES `pulsos_grupo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pulso_respuestas_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `pulso_respuestas` DISABLE KEYS */;
/*!40000 ALTER TABLE `pulso_respuestas` ENABLE KEYS */;
DROP TABLE IF EXISTS `pulsos_grupo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pulsos_grupo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `docente_id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `pregunta` varchar(255) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_cierre` datetime NOT NULL,
  `cerrado_manualmente` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `docente_id` (`docente_id`),
  KEY `idx_pulso_grupo` (`grupo_id`,`fecha_cierre`),
  CONSTRAINT `pulsos_grupo_ibfk_1` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pulsos_grupo_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `pulsos_grupo` DISABLE KEYS */;
/*!40000 ALTER TABLE `pulsos_grupo` ENABLE KEYS */;
DROP TABLE IF EXISTS `recordatorios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recordatorios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `hora` time NOT NULL,
  `dias_semana` set('lun','mar','mie','jue','vie','sab','dom') NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_envio` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `recordatorios_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `recordatorios` DISABLE KEYS */;
/*!40000 ALTER TABLE `recordatorios` ENABLE KEYS */;
DROP TABLE IF EXISTS `recursos_apoyo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recursos_apoyo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `tipo` enum('articulo','ejercicio','contacto') NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `colegio_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_recursos_colegio` (`colegio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `recursos_apoyo` DISABLE KEYS */;
/*!40000 ALTER TABLE `recursos_apoyo` ENABLE KEYS */;
DROP TABLE IF EXISTS `refuerzos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `refuerzos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `periodo_id` int(11) NOT NULL,
  `asignacion_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `nota_original` decimal(5,2) DEFAULT NULL,
  `fecha_limite` date DEFAULT NULL,
  `estado` varchar(10) NOT NULL DEFAULT 'pendiente',
  `nota_refuerzo` decimal(5,2) DEFAULT NULL,
  `fecha_presentacion` date DEFAULT NULL,
  `observacion` varchar(300) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `refuerzo_unico` (`asignacion_id`,`periodo_id`,`estudiante_id`),
  KEY `idx_refuerzos_colegio` (`colegio_id`,`anio_lectivo_id`,`estado`),
  KEY `fk_refuerzos_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_refuerzos_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_refuerzos_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `refuerzos` DISABLE KEYS */;
/*!40000 ALTER TABLE `refuerzos` ENABLE KEYS */;
DROP TABLE IF EXISTS `respuestas_encuesta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `respuestas_encuesta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pregunta_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `opcion_id` int(11) DEFAULT NULL,
  `respuesta_texto` text DEFAULT NULL,
  `fecha_respuesta` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `respuesta_unica` (`pregunta_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `opcion_id` (`opcion_id`),
  CONSTRAINT `respuestas_encuesta_ibfk_1` FOREIGN KEY (`pregunta_id`) REFERENCES `preguntas_encuesta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `respuestas_encuesta_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `respuestas_encuesta_ibfk_3` FOREIGN KEY (`opcion_id`) REFERENCES `opciones_pregunta` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `respuestas_encuesta` DISABLE KEYS */;
/*!40000 ALTER TABLE `respuestas_encuesta` ENABLE KEYS */;
DROP TABLE IF EXISTS `restaurante_asistencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_asistencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `asistio` tinyint(1) NOT NULL DEFAULT 1,
  `registrado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rest_asist_unico` (`fecha`,`estudiante_id`),
  KEY `idx_rest_asist_colegio` (`colegio_id`,`fecha`),
  KEY `fk_resta_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_resta_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resta_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `restaurante_asistencia` DISABLE KEYS */;
/*!40000 ALTER TABLE `restaurante_asistencia` ENABLE KEYS */;
DROP TABLE IF EXISTS `restaurante_beneficiarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_beneficiarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `jornada` varchar(20) NOT NULL DEFAULT 'almuerzo',
  `tipo` varchar(10) NOT NULL DEFAULT 'gratuito',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `observacion` varchar(200) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rest_benef_unico` (`anio_lectivo_id`,`estudiante_id`),
  KEY `idx_rest_benef_colegio` (`colegio_id`,`anio_lectivo_id`),
  KEY `fk_restb_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_restb_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restb_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `restaurante_beneficiarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `restaurante_beneficiarios` ENABLE KEYS */;
DROP TABLE IF EXISTS `restaurante_pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `mes` tinyint(3) unsigned NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `recibo` varchar(30) DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rest_pagos` (`colegio_id`,`anio_lectivo_id`,`mes`),
  KEY `fk_restp_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_restp_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_restp_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `restaurante_pagos` DISABLE KEYS */;
/*!40000 ALTER TABLE `restaurante_pagos` ENABLE KEYS */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES
(11,'acudiente'),
(7,'administrador'),
(5,'coordinador'),
(4,'docente'),
(1,'estudiante'),
(3,'orientador'),
(2,'psicologo'),
(6,'rector'),
(9,'secretaria'),
(10,'secretaria_academica'),
(8,'superadmin');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
DROP TABLE IF EXISTS `secretaria_municipios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `secretaria_municipios` (
  `municipio` varchar(80) NOT NULL,
  `secretaria_id` int(11) NOT NULL,
  PRIMARY KEY (`municipio`),
  KEY `secretaria_id` (`secretaria_id`),
  CONSTRAINT `secretaria_municipios_ibfk_1` FOREIGN KEY (`secretaria_id`) REFERENCES `secretarias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `secretaria_municipios` DISABLE KEYS */;
INSERT INTO `secretaria_municipios` VALUES
('Medellín',1);
/*!40000 ALTER TABLE `secretaria_municipios` ENABLE KEYS */;
DROP TABLE IF EXISTS `secretarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `secretarias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `creada_en` datetime NOT NULL DEFAULT current_timestamp(),
  `departamento` varchar(60) DEFAULT NULL,
  `tipo` varchar(12) NOT NULL DEFAULT 'municipal',
  PRIMARY KEY (`id`),
  UNIQUE KEY `secretarias_nombre_unico` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `secretarias` DISABLE KEYS */;
INSERT INTO `secretarias` VALUES
(1,'Secretaría de Educación de Medellín',1,'2026-10-19 05:16:27',NULL,'municipal');
/*!40000 ALTER TABLE `secretarias` ENABLE KEYS */;
DROP TABLE IF EXISTS `seguimiento_habitos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seguimiento_habitos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `habito_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `habito_fecha_unico` (`habito_id`,`fecha`),
  CONSTRAINT `seguimiento_habitos_ibfk_1` FOREIGN KEY (`habito_id`) REFERENCES `habitos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `seguimiento_habitos` DISABLE KEYS */;
/*!40000 ALTER TABLE `seguimiento_habitos` ENABLE KEYS */;
DROP TABLE IF EXISTS `sesiones_activas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sesiones_activas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `agente_usuario` varchar(255) DEFAULT NULL,
  `iniciada_en` datetime NOT NULL,
  `ultima_actividad` datetime NOT NULL,
  `revocada_en` datetime DEFAULT NULL,
  `es_actual_al_revocar` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sesiones_session_id` (`session_id`),
  KEY `idx_sesiones_usuario` (`usuario_id`,`revocada_en`),
  CONSTRAINT `sesiones_activas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `sesiones_activas` DISABLE KEYS */;
/*!40000 ALTER TABLE `sesiones_activas` ENABLE KEYS */;
DROP TABLE IF EXISTS `simat_datos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `simat_datos` (
  `estudiante_id` int(11) NOT NULL,
  `colegio_id` int(11) NOT NULL,
  `proviene_de` varchar(14) DEFAULT NULL,
  `subsidio_condicionado` tinyint(1) DEFAULT NULL,
  `internado` tinyint(1) DEFAULT NULL,
  `matricula_contratada` tinyint(1) DEFAULT NULL,
  `capacidad_excepcional` varchar(40) DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`estudiante_id`),
  KEY `idx_simat_datos_colegio` (`colegio_id`),
  CONSTRAINT `fk_simat_datos_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_simat_datos_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `simat_datos` DISABLE KEYS */;
/*!40000 ALTER TABLE `simat_datos` ENABLE KEYS */;
DROP TABLE IF EXISTS `simat_novedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `simat_novedades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `matricula_id` int(11) DEFAULT NULL,
  `tipo` varchar(12) NOT NULL,
  `fecha` date NOT NULL,
  `causa` varchar(150) DEFAULT NULL,
  `destino` varchar(150) DEFAULT NULL,
  `observacion` varchar(300) DEFAULT NULL,
  `estado` varchar(10) NOT NULL DEFAULT 'pendiente',
  `reportada_en` datetime DEFAULT NULL,
  `reportada_por` int(11) DEFAULT NULL,
  `origen` varchar(10) NOT NULL DEFAULT 'manual',
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_simat_nov_colegio` (`colegio_id`,`anio_lectivo_id`,`estado`),
  KEY `idx_simat_nov_estudiante` (`estudiante_id`),
  KEY `fk_simat_nov_anio` (`anio_lectivo_id`),
  CONSTRAINT `fk_simat_nov_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_simat_nov_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_simat_nov_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `simat_novedades` DISABLE KEYS */;
/*!40000 ALTER TABLE `simat_novedades` ENABLE KEYS */;
DROP TABLE IF EXISTS `simat_reportes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `simat_reportes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) DEFAULT NULL,
  `tipo` varchar(14) NOT NULL,
  `filas` int(10) unsigned NOT NULL DEFAULT 0,
  `errores` int(10) unsigned NOT NULL DEFAULT 0,
  `advertencias` int(10) unsigned NOT NULL DEFAULT 0,
  `detalle` varchar(300) DEFAULT NULL,
  `generado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_simat_rep_colegio` (`colegio_id`,`creado_en`),
  CONSTRAINT `fk_simat_rep_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `simat_reportes` DISABLE KEYS */;
/*!40000 ALTER TABLE `simat_reportes` ENABLE KEYS */;
DROP TABLE IF EXISTS `simpade_retiros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `simpade_retiros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `matricula_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `causa` varchar(30) NOT NULL,
  `detalle` varchar(300) DEFAULT NULL,
  `destino` varchar(14) NOT NULL DEFAULT 'desconocido',
  `familia_contactada` tinyint(1) NOT NULL DEFAULT 0,
  `estrategia` varchar(300) DEFAULT NULL,
  `reportado` tinyint(1) NOT NULL DEFAULT 0,
  `reportado_en` datetime DEFAULT NULL,
  `reportado_por` int(11) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `simpade_retiro_unico` (`anio_lectivo_id`,`estudiante_id`),
  KEY `idx_simpade_ret_colegio` (`colegio_id`,`anio_lectivo_id`),
  KEY `fk_simpade_ret_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_simpade_ret_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_simpade_ret_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_simpade_ret_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `simpade_retiros` DISABLE KEYS */;
/*!40000 ALTER TABLE `simpade_retiros` ENABLE KEYS */;
DROP TABLE IF EXISTS `simpade_riesgos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `simpade_riesgos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `colegio_id` int(11) NOT NULL,
  `anio_lectivo_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `nivel` varchar(5) NOT NULL DEFAULT 'medio',
  `estado` varchar(14) NOT NULL DEFAULT 'activo',
  `origen` varchar(10) NOT NULL DEFAULT 'manual',
  `puntaje` smallint(6) NOT NULL DEFAULT 0,
  `factores` varchar(500) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `fecha_deteccion` date NOT NULL,
  `cerrado_en` datetime DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `simpade_riesgo_unico` (`anio_lectivo_id`,`estudiante_id`),
  KEY `idx_simpade_riesgo_colegio` (`colegio_id`,`anio_lectivo_id`,`estado`),
  KEY `fk_simpade_r_estudiante` (`estudiante_id`),
  KEY `fk_simpade_r_responsable` (`responsable_id`),
  CONSTRAINT `fk_simpade_r_anio` FOREIGN KEY (`anio_lectivo_id`) REFERENCES `anios_lectivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_simpade_r_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_simpade_r_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_simpade_r_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `simpade_riesgos` DISABLE KEYS */;
/*!40000 ALTER TABLE `simpade_riesgos` ENABLE KEYS */;
DROP TABLE IF EXISTS `simpade_seguimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `simpade_seguimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `riesgo_id` int(11) NOT NULL,
  `autor_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(12) NOT NULL DEFAULT 'otro',
  `nota` text NOT NULL,
  `proxima_fecha` date DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_simpade_seg_riesgo` (`riesgo_id`,`fecha`),
  KEY `fk_simpade_seg_autor` (`autor_id`),
  CONSTRAINT `fk_simpade_seg_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_simpade_seg_riesgo` FOREIGN KEY (`riesgo_id`) REFERENCES `simpade_riesgos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `simpade_seguimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `simpade_seguimientos` ENABLE KEYS */;
DROP TABLE IF EXISTS `solicitudes_cambio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitudes_cambio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `campo` varchar(50) NOT NULL,
  `valor_anterior` varchar(255) DEFAULT NULL,
  `valor_nuevo` varchar(255) NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `revisado_por` int(11) DEFAULT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_revision` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `revisado_por` (`revisado_por`),
  CONSTRAINT `solicitudes_cambio_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solicitudes_cambio_ibfk_2` FOREIGN KEY (`revisado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `solicitudes_cambio` DISABLE KEYS */;
/*!40000 ALTER TABLE `solicitudes_cambio` ENABLE KEYS */;
DROP TABLE IF EXISTS `subtareas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subtareas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meta_id` int(11) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `completada` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `meta_id` (`meta_id`),
  CONSTRAINT `subtareas_ibfk_1` FOREIGN KEY (`meta_id`) REFERENCES `metas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `subtareas` DISABLE KEYS */;
/*!40000 ALTER TABLE `subtareas` ENABLE KEYS */;
DROP TABLE IF EXISTS `sueno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sueno` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `horas` decimal(3,1) NOT NULL,
  `calidad` enum('mala','regular','buena') NOT NULL,
  `hora_dormir` time DEFAULT NULL,
  `hora_despertar` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_fecha_unico` (`usuario_id`,`fecha`),
  CONSTRAINT `sueno_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `sueno` DISABLE KEYS */;
/*!40000 ALTER TABLE `sueno` ENABLE KEYS */;
DROP TABLE IF EXISTS `texto_ia_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `texto_ia_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(191) NOT NULL,
  `fecha` date NOT NULL,
  `texto` text NOT NULL,
  `fecha_generado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave_fecha_unico` (`clave`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `texto_ia_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `texto_ia_cache` ENABLE KEYS */;
DROP TABLE IF EXISTS `tokens_recuperacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tokens_recuperacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `tokens_recuperacion_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `tokens_recuperacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `tokens_recuperacion` ENABLE KEYS */;
DROP TABLE IF EXISTS `usuario_insignias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_insignias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `insignia_id` int(11) NOT NULL,
  `fecha_obtenida` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_insignia_unica` (`usuario_id`,`insignia_id`),
  KEY `insignia_id` (`insignia_id`),
  CONSTRAINT `usuario_insignias_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_insignias_ibfk_2` FOREIGN KEY (`insignia_id`) REFERENCES `insignias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `usuario_insignias` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_insignias` ENABLE KEYS */;
DROP TABLE IF EXISTS `usuario_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_rol_unico` (`usuario_id`,`rol_id`),
  KEY `fk_ur_rol` (`rol_id`),
  CONSTRAINT `fk_ur_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `usuario_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_roles` ENABLE KEYS */;
DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `colegio_id` int(11) DEFAULT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `consentimiento_datos` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_consentimiento` datetime DEFAULT NULL,
  `pin_diario_hash` varchar(255) DEFAULT NULL,
  `intentos_login_fallidos` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `login_bloqueado_hasta` datetime DEFAULT NULL,
  `intentos_pin_fallidos` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `pin_bloqueado_hasta` datetime DEFAULT NULL,
  `totp_secreto` varchar(255) DEFAULT NULL,
  `totp_activo` tinyint(1) NOT NULL DEFAULT 0,
  `totp_ultimo_paso` bigint(20) DEFAULT NULL,
  `totp_activado_en` datetime DEFAULT NULL,
  `intentos_2fa_fallidos` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `bloqueo_2fa_hasta` datetime DEFAULT NULL,
  `correo_2fa_activo` tinyint(1) NOT NULL DEFAULT 0,
  `correo_2fa_activado_en` datetime DEFAULT NULL,
  `whatsapp_2fa_numero` varchar(40) DEFAULT NULL,
  `whatsapp_2fa_apikey` varchar(255) DEFAULT NULL,
  `whatsapp_2fa_activo` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_2fa_activado_en` datetime DEFAULT NULL,
  `rostro_2fa_plantillas` text DEFAULT NULL,
  `rostro_2fa_activo` tinyint(1) NOT NULL DEFAULT 0,
  `rostro_2fa_activado_en` datetime DEFAULT NULL,
  `rostro_2fa_consentimiento_en` datetime DEFAULT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  `ultimo_resumen_semanal` datetime DEFAULT NULL,
  `resumen_semanal_activo` tinyint(1) NOT NULL DEFAULT 1,
  `tema_preferido` enum('claro','oscuro','sistema') NOT NULL DEFAULT 'sistema',
  `tamano_texto` enum('normal','grande','muy_grande') NOT NULL DEFAULT 'normal',
  `alto_contraste` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `secretaria_id` int(11) DEFAULT NULL,
  `es_maestro` tinyint(1) NOT NULL DEFAULT 0,
  `codigo_matricula` int(10) unsigned DEFAULT NULL,
  `documento` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`),
  UNIQUE KEY `uq_usuarios_codigo_matricula` (`colegio_id`,`codigo_matricula`),
  KEY `idx_usuarios_rol` (`rol_id`),
  KEY `idx_usuarios_grupo` (`grupo_id`),
  KEY `idx_usuarios_colegio` (`colegio_id`),
  KEY `idx_usuarios_secretaria` (`secretaria_id`),
  KEY `idx_usuarios_documento` (`documento`),
  CONSTRAINT `fk_usuarios_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`),
  CONSTRAINT `fk_usuarios_secretaria` FOREIGN KEY (`secretaria_id`) REFERENCES `secretarias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
DROP TABLE IF EXISTS `zonas_municipio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `zonas_municipio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `municipio_id` int(11) NOT NULL,
  `tipo` varchar(15) NOT NULL DEFAULT 'comuna',
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zonas_unico` (`municipio_id`,`tipo`,`nombre`),
  CONSTRAINT `fk_zonas_municipio` FOREIGN KEY (`municipio_id`) REFERENCES `municipios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `zonas_municipio` DISABLE KEYS */;
/*!40000 ALTER TABLE `zonas_municipio` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

