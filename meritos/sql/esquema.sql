-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-03-2026 a las 12:30:59
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
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_grupo`
--

CREATE TABLE `tbl_grupo` (
  `id_grupo` int(10) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `id_tutor` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_grupo_profesor`
--

CREATE TABLE `tbl_grupo_profesor` (
  `id` int(10) NOT NULL,
  `id_grupo` int(10) NOT NULL,
  `id_profesor` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_merito`
--

CREATE TABLE `tbl_merito` (
  `id_merito` int(10) NOT NULL,
  `ORCID_autor` varchar(19) NOT NULL,
  `id_publicacion` int(10) NOT NULL,
  `id_convocatoria` int(10) NOT NULL,
  `aportacion_personal` text NOT NULL,
  `valoracion` int(2) NOT NULL,
  `observaciones` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

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
  `perfil` varchar(100) NOT NULL,
  `facultad` varchar(100) NOT NULL,
  `departamento` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `rama` enum('SALUD','TECNICA','S Y J','HUMANIDADES','EXPERIMENTALES') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

--
-- Volcado de datos para la tabla `tbl_profesor`
--

INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES
(1, 'ramon', 'Romero Rodriguez', '1234', '12345678P', '', 653471245, 'Profesor', 'Ciencias', 'Polinecnica', 'ramon@gmail.com', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_publicacion`
--

CREATE TABLE `tbl_publicacion` (
  `id_publicación` int(10) NOT NULL,
  `DOI` varchar(255) NOT NULL,
  `ORCID_autor` varchar(19) NOT NULL,
  `autor` varchar(100) NOT NULL,
  `autor2` varchar(100) DEFAULT NULL,
  `autor3` varchar(100) DEFAULT NULL,
  `autor4` varchar(100) DEFAULT NULL,
  `titulo` varchar(100) NOT NULL,
  `fecha_publicacion` varchar(50) NOT NULL,
  `nombre_revista` varchar(100) NOT NULL,
  `numero_revista` int(100) NOT NULL,
  `documento` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_usuario`
--

CREATE TABLE `tbl_usuario` (
  `id_usuario` int(10) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

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
  ADD KEY `id_profesor` (`id_profesor`);

--
-- Indices de la tabla `tbl_publicacion`
--
ALTER TABLE `tbl_publicacion`
  ADD PRIMARY KEY (`id_publicación`),
  ADD KEY `FK_autor` (`ORCID_autor`);

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
  MODIFY `id_grupo` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_grupo_profesor`
--
ALTER TABLE `tbl_grupo_profesor`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tbl_profesor`
--
ALTER TABLE `tbl_profesor`
  MODIFY `id_profesor` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tbl_publicacion`
--
ALTER TABLE `tbl_publicacion`
  MODIFY `id_publicación` int(10) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `tbl_grupo`
--
ALTER TABLE `tbl_grupo`
  ADD CONSTRAINT `fk_tutor` FOREIGN KEY (`id_tutor`) REFERENCES `tbl_profesor` (`id_profesor`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_grupo_profesor`
--
ALTER TABLE `tbl_grupo_profesor`
  ADD CONSTRAINT `fk_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `tbl_grupo` (`id_grupo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `tbl_profesor` (`id_profesor`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_merito`
--
ALTER TABLE `tbl_merito`
  ADD CONSTRAINT `FK_convocatoria` FOREIGN KEY (`id_convocatoria`) REFERENCES `tbl_convocatoria` (`id_convocatoria`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_publicacion` FOREIGN KEY (`id_publicacion`) REFERENCES `tbl_publicacion` (`id_publicación`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_publicacion`
--
ALTER TABLE `tbl_publicacion`
  ADD CONSTRAINT `FK_autor` FOREIGN KEY (`ORCID_autor`) REFERENCES `tbl_profesor` (`ORCID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
