CREATE DATABASE IF NOT EXISTS evaluador_aneca_humanidades CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE evaluador_aneca_humanidades;

CREATE TABLE IF NOT EXISTS evaluaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_candidato VARCHAR(150) NOT NULL,
    orcid_candidato VARCHAR(19) NULL DEFAULT NULL,
    area VARCHAR(100) NOT NULL DEFAULT 'Humanidades',
    categoria VARCHAR(50) NOT NULL DEFAULT 'PCD/PUP',
    json_entrada LONGTEXT NOT NULL,

    puntuacion_1a DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_1b DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_1c DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_1d DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_1e DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_1f DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_1g DECIMAL(10,2) NOT NULL DEFAULT 0,
    bloque_1 DECIMAL(10,2) NOT NULL DEFAULT 0,

    puntuacion_2a DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_2b DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_2c DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_2d DECIMAL(10,2) NOT NULL DEFAULT 0,
    bloque_2 DECIMAL(10,2) NOT NULL DEFAULT 0,

    puntuacion_3a DECIMAL(10,2) NOT NULL DEFAULT 0,
    puntuacion_3b DECIMAL(10,2) NOT NULL DEFAULT 0,
    bloque_3 DECIMAL(10,2) NOT NULL DEFAULT 0,

    bloque_4 DECIMAL(10,2) NOT NULL DEFAULT 0,

    total_b1_b2 DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_final DECIMAL(10,2) NOT NULL DEFAULT 0,

    resultado VARCHAR(50) NOT NULL,
    cumple_regla_1 TINYINT(1) NOT NULL DEFAULT 0,
    cumple_regla_2 TINYINT(1) NOT NULL DEFAULT 0,

    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_evaluaciones_orcid_candidato_fecha (orcid_candidato, fecha_creacion)
);
