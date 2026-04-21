-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-04-2026 a las 09:36:03
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `acelerador`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_convocatoria`
--

CREATE TABLE `tbl_convocatoria` (
  `id_convocatoria` int(10) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `fecha_convocatoria` varchar(50) NOT NULL,
  `tipo` enum('CONCURSO DE MERITOS','OPOSICION') NOT NULL,
  `organismo` enum('PUBLICO','PRIVADO') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_grupo`
--

CREATE TABLE `tbl_grupo` (
  `id_grupo` int(10) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `id_tutor` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_grupo_profesor`
--

CREATE TABLE `tbl_grupo_profesor` (
  `id` int(10) NOT NULL,
  `id_grupo` int(10) NOT NULL,
  `id_profesor` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_merito`
--

CREATE TABLE `tbl_merito` (
  `id_merito` int(10) NOT NULL,
  `ORCID_autor` varchar(19) NOT NULL,
  `id_publicacion` int(10) NOT NULL,
  `id_convocatoria` int(10) NOT NULL,
  `aportacion_personal` mediumtext NOT NULL,
  `valoracion` int(2) NOT NULL,
  `observaciones` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_profesor`
--

CREATE TABLE `tbl_profesor` (
  `id_profesor` int(10) NOT NULL,
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
  `rama` enum('SALUD','TECNICA','CSYJ','HUMANIDADES','EXPERIMENTALES') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_publicacion`
--

CREATE TABLE `tbl_publicacion` (
  `id_publicación` int(10) NOT NULL,
  `DOI` varchar(255) NOT NULL,
  `ORCID_autor` varchar(19) NOT NULL,
  `autor` varchar(100) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `fecha_publicacion` varchar(50) NOT NULL,
  `nombre_revista` varchar(100) NOT NULL,
  `numero_revista` int(100) NOT NULL,
  `documento` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_publicacion_profesor`
--

CREATE TABLE `tbl_publicacion_profesor` (
  `id` int(10) NOT NULL,
  `id_publicacion` int(10) NOT NULL,
  `orcid_profesor` varchar(19) NOT NULL,
  `orden_autoria` int(10) NOT NULL,
  `aportacion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_usuario`
--

CREATE TABLE `tbl_usuario` (
  `id_usuario` int(10) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `tbl_convocatoria`
--
ALTER TABLE `tbl_convocatoria`
  ADD PRIMARY KEY (`id_convocatoria`);

--
-- Indices de la tabla `tbl_grupo`
--
ALTER TABLE `tbl_grupo`
  ADD PRIMARY KEY (`id_grupo`),
  ADD KEY `fk_tutor` (`id_tutor`);

--
-- Indices de la tabla `tbl_grupo_profesor`
--
ALTER TABLE `tbl_grupo_profesor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_grupo` (`id_grupo`),
  ADD KEY `fk_profesor` (`id_profesor`);

--
-- Indices de la tabla `tbl_merito`
--
ALTER TABLE `tbl_merito`
  ADD PRIMARY KEY (`id_merito`),
  ADD KEY `FK_convocatoria` (`id_convocatoria`),
  ADD KEY `FK_publicacion` (`id_publicacion`);

--
-- Indices de la tabla `tbl_profesor`
--
ALTER TABLE `tbl_profesor`
  ADD PRIMARY KEY (`ORCID`),
  ADD UNIQUE KEY `id_profesor` (`id_profesor`);

--
-- Indices de la tabla `tbl_publicacion`
--
ALTER TABLE `tbl_publicacion`
  ADD PRIMARY KEY (`id_publicación`),
  ADD KEY `FK_autor` (`ORCID_autor`);

--
-- Indices de la tabla `tbl_publicacion_profesor`
--
ALTER TABLE `tbl_publicacion_profesor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_publicacion` (`id_publicacion`),
  ADD KEY `fk_profesor` (`orcid_profesor`);

--
-- Indices de la tabla `tbl_usuario`
--
ALTER TABLE `tbl_usuario`
  ADD PRIMARY KEY (`id_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `tbl_convocatoria`
--
ALTER TABLE `tbl_convocatoria`
  MODIFY `id_convocatoria` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_grupo`
--
ALTER TABLE `tbl_grupo`
  MODIFY `id_grupo` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `tbl_grupo_profesor`
--
ALTER TABLE `tbl_grupo_profesor`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `tbl_profesor`
--
ALTER TABLE `tbl_profesor`
  MODIFY `id_profesor` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT de la tabla `tbl_publicacion_profesor`
--
ALTER TABLE `tbl_publicacion_profesor`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_usuario`
--
ALTER TABLE `tbl_usuario`
  MODIFY `id_usuario` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `tbl_publicacion_profesor`
--
ALTER TABLE `tbl_publicacion_profesor`
  ADD CONSTRAINT `fk_profesor` FOREIGN KEY (`orcid_profesor`) REFERENCES `tbl_profesor` (`ORCID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_publicacion` FOREIGN KEY (`id_publicacion`) REFERENCES `tbl_publicacion` (`id_publicación`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
