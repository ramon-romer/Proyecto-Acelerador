CREATE DATABASE IF NOT EXISTS acelerador CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE acelerador;

CREATE TABLE IF NOT EXISTS tbl_profesor (
  id_profesor INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) DEFAULT NULL,
  apellidos VARCHAR(200) DEFAULT NULL,
  ORCID VARCHAR(32) DEFAULT NULL,
  departamento VARCHAR(150) DEFAULT NULL,
  correo VARCHAR(255) DEFAULT NULL,
  perfil VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (id_profesor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_grupo (
  id_grupo INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(200) NOT NULL,
  descripcion TEXT DEFAULT NULL,
  id_tutor INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id_grupo),
  KEY idx_tbl_grupo_tutor (id_tutor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_grupo_profesor (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_grupo INT UNSIGNED NOT NULL,
  id_profesor INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tbl_grupo_profesor (id_grupo, id_profesor),
  KEY idx_tbl_grupo_profesor_profesor (id_profesor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;