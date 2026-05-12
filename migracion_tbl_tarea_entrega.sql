-- ============================================================
-- Migración: Crear tabla tbl_tarea_entrega
-- Base de datos: acelerador
-- Fecha: 2026-05-05
-- ============================================================

USE `acelerador`;

CREATE TABLE IF NOT EXISTS `tbl_tarea_entrega` (
  `id`                INT(10)      NOT NULL AUTO_INCREMENT,
  `id_grupo`          INT(10)      NOT NULL,
  `id_profesor`       INT(10)      NOT NULL,
  `id_tutor`          INT(10)      NOT NULL,
  `titulo_tarea`      VARCHAR(255) NOT NULL,
  `descripcion_tarea` TEXT         DEFAULT NULL,
  `num_entregas`      INT(3)       NOT NULL DEFAULT 1,
  `fechas_entregas`   JSON         DEFAULT NULL COMMENT 'Array JSON de fechas: ["2026-06-01","2026-06-15"]',
  `fecha_creacion`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_profesor`  (`id_profesor`),
  KEY `idx_tutor`     (`id_tutor`),
  KEY `idx_grupo`     (`id_grupo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
