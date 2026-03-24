-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 24-03-2026 a las 11:02:20
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
  `perfil` enum('ADMIN','PROFESOR','TUTOR') NOT NULL,
  `facultad` varchar(100) NOT NULL,
  `departamento` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `rama` enum('SALUD','TECNICA','S Y J','HUMANIDADES','EXPERIMENTALES') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

--
-- Volcado de datos para la tabla `tbl_profesor`
--

INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES
(1, 'ramon', 'Romero Rodriguez', '1234', '12345678P', '', 653471245, '', 'Ciencias', 'Polinecnica', 'ramon@gmail.com', ''),
(3, 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-2254', 123456789, 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', ''),
(2, 'ADcfsd', 'dtrfyguyh', '12345678Y#', '12345678D', '0000-1111-2222-2544', 987654321, '', 'dsfgh', 'fcgevhbj', 'xcvds@rgg', 'HUMANIDADES'),
(10, 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-3000', 123456789, 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', 'SALUD'),
(11, 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-4000', 123456789, 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', ''),
(12, 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-5000', 123456789, 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', ''),
(17, 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-6000', 123456789, 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', 'HUMANIDADES'),
(18, 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-7000', 123456789, 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', 'EXPERIMENTALES'),
(20, 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-8000', 123456789, 'PROFESOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', 'EXPERIMENTALES');

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
-- Volcado de datos para la tabla `tbl_usuario`
--

INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES
(0, 'xcvds@rgg', '12345678Y#');

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
-- AUTO_INCREMENT de la tabla `tbl_profesor`
--
ALTER TABLE `tbl_profesor`
  MODIFY `id_profesor` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
