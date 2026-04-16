SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

-- ========================================================
-- 1. CREACIÓN DE BASES DE DATOS
-- ========================================================
CREATE DATABASE IF NOT EXISTS `acelerador`;
CREATE DATABASE IF NOT EXISTS `evaluador_aneca_tecnicas`;
CREATE DATABASE IF NOT EXISTS `evaluador_aneca_salud`;
CREATE DATABASE IF NOT EXISTS `evaluador_aneca_humanidades`;
CREATE DATABASE IF NOT EXISTS `evaluador_aneca_csyj`;
CREATE DATABASE IF NOT EXISTS `evaluador_aneca_experimentales`;

-- ========================================================
-- 2. USUARIO Y PERMISOS
-- ========================================================
CREATE USER IF NOT EXISTS 'usuario_web'@'%' IDENTIFIED BY 'password_segura';
GRANT ALL PRIVILEGES ON *.* TO 'usuario_web'@'%';
FLUSH PRIVILEGES;

-- ========================================================
-- 3. ESTRUCTURA COMPLETA: acelerador
-- ========================================================
USE `acelerador`;

DROP TABLE IF EXISTS `tbl_publicacion_profesor`;
DROP TABLE IF EXISTS `tbl_merito`;
DROP TABLE IF EXISTS `tbl_publicacion`;
DROP TABLE IF EXISTS `tbl_grupo_profesor`;
DROP TABLE IF EXISTS `tbl_grupo`;
DROP TABLE IF EXISTS `tbl_profesor`;
DROP TABLE IF EXISTS `tbl_convocatoria`;
DROP TABLE IF EXISTS `tbl_usuario`;

CREATE TABLE `tbl_convocatoria` (
  `id_convocatoria` int(10) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `fecha_convocatoria` varchar(50) NOT NULL,
  `tipo` enum('CONCURSO DE MERITOS','OPOSICION') NOT NULL,
  `organismo` enum('PUBLICO','PRIVADO') NOT NULL,
  PRIMARY KEY (`id_convocatoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_profesor` (
  `id_profesor` int(10) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(200) NOT NULL,
  `password` varchar(255) NOT NULL,
  `DNI` varchar(9) NOT NULL,
  `ORCID` varchar(19) NOT NULL,
  `telefono` int(9) NOT NULL,
  `perfil` enum('ADMIN','PROFESOR','TUTOR') NOT NULL,
  `facultad` varchar(100) NOT NULL,
  `departamento` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `rama` enum('SALUD','TECNICA','CSYJ','HUMANIDADES','EXPERIMENTALES') NOT NULL,
  PRIMARY KEY (`ORCID`),
  UNIQUE KEY `id_profesor` (`id_profesor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_grupo` (
  `id_grupo` int(10) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `id_tutor` int(10) NOT NULL,
  PRIMARY KEY (`id_grupo`),
  KEY `fk_tutor` (`id_tutor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_grupo_profesor` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `id_grupo` int(10) NOT NULL,
  `id_profesor` int(10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_grupo` (`id_grupo`),
  KEY `fk_profesor_grupo` (`id_profesor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_publicacion` (
  `id_publicación` int(10) NOT NULL AUTO_INCREMENT,
  `DOI` varchar(255) NOT NULL,
  `ORCID_autor` varchar(19) NOT NULL,
  `autor` varchar(100) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `fecha_publicacion` varchar(50) NOT NULL,
  `nombre_revista` varchar(100) NOT NULL,
  `numero_revista` int(100) NOT NULL,
  `documento` varchar(255) NOT NULL,
  PRIMARY KEY (`id_publicación`),
  KEY `FK_autor` (`ORCID_autor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_publicacion_profesor` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `id_publicacion` int(10) NOT NULL,
  `orcid_profesor` varchar(19) NOT NULL,
  `orden_autoria` int(10) NOT NULL,
  `aportacion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_publicacion` (`id_publicacion`),
  KEY `fk_profesor` (`orcid_profesor`),
  CONSTRAINT `fk_profesor` FOREIGN KEY (`orcid_profesor`) REFERENCES `tbl_profesor` (`ORCID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_publicacion` FOREIGN KEY (`id_publicacion`) REFERENCES `tbl_publicacion` (`id_publicación`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_merito` (
  `id_merito` int(10) NOT NULL AUTO_INCREMENT,
  `ORCID_autor` varchar(19) NOT NULL,
  `id_publicacion` int(10) NOT NULL,
  `id_convocatoria` int(10) NOT NULL,
  `aportacion_personal` mediumtext NOT NULL,
  `valoracion` int(2) NOT NULL,
  `observaciones` mediumtext NOT NULL,
  PRIMARY KEY (`id_merito`),
  KEY `FK_convocatoria` (`id_convocatoria`),
  KEY `FK_publicacion_merito` (`id_publicacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_usuario` (
  `id_usuario` int(10) NOT NULL AUTO_INCREMENT,
  `correo` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbl_usuario` (`correo`, `password`) VALUES ('admin@admin.com', '1234');


-- ========================================================
-- 4. ESTRUCTURA COMÚN PARA LOS EVALUADORES
-- ========================================================

-- TÉCNICAS
USE `evaluador_aneca_tecnicas`;
DROP TABLE IF EXISTS `evaluaciones`;
CREATE TABLE `evaluaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_candidato` varchar(150) NOT NULL,
  `area` varchar(100) NOT NULL DEFAULT 'Técnicas',
  `categoria` varchar(50) NOT NULL DEFAULT 'PCD/PUP',
  `json_entrada` longtext NOT NULL,
  `puntuacion_1a` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_1b` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_1c` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_1d` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_1e` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_1f` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_1g` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bloque_1` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_2a` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_2b` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_2c` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_2d` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bloque_2` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_3a` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_3b` decimal(10,2) NOT NULL DEFAULT 0.00,
  `puntuacion_4` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bloque_3` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bloque_4` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_b1_b2` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_final` decimal(10,2) NOT NULL DEFAULT 0.00,
  `resultado` varchar(50) NOT NULL,
  `cumple_regla_1` tinyint(1) NOT NULL DEFAULT 0,
  `cumple_regla_2` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CIENCIAS SOCIALES Y JURÍDICAS
USE `evaluador_aneca_csyj`;
DROP TABLE IF EXISTS `evaluaciones`;
CREATE TABLE `evaluaciones` LIKE `evaluador_aneca_tecnicas`.`evaluaciones`;
ALTER TABLE `evaluaciones` MODIFY `area` varchar(100) NOT NULL DEFAULT 'Ciencias Sociales y Jurídicas';

-- SALUD
USE `evaluador_aneca_salud`;
DROP TABLE IF EXISTS `evaluaciones`;
CREATE TABLE `evaluaciones` LIKE `evaluador_aneca_tecnicas`.`evaluaciones`;
ALTER TABLE `evaluaciones` MODIFY `area` varchar(100) NOT NULL DEFAULT 'Salud';

-- HUMANIDADES
USE `evaluador_aneca_humanidades`;
DROP TABLE IF EXISTS `evaluaciones`;
CREATE TABLE `evaluaciones` LIKE `evaluador_aneca_tecnicas`.`evaluaciones`;
ALTER TABLE `evaluaciones` MODIFY `area` varchar(100) NOT NULL DEFAULT 'Humanidades';

-- EXPERIMENTALES
USE `evaluador_aneca_experimentales`;
DROP TABLE IF EXISTS `evaluaciones`;
CREATE TABLE `evaluaciones` LIKE `evaluador_aneca_tecnicas`.`evaluaciones`;
ALTER TABLE `evaluaciones` MODIFY `area` varchar(100) NOT NULL DEFAULT 'Experimentales';

COMMIT;
