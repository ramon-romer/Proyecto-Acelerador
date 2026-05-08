-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 17-03-2026 a las 12:19:26
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
-- Estructura de tabla para la tabla `tbl_profesor`
--

CREATE TABLE `tbl_profesor` (
  `id_profesor` int(10) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `apellidos` varchar(200) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `DNI` varchar(9) DEFAULT NULL,
  `DOI` varchar(15) DEFAULT NULL,
  `telefono` int(9) DEFAULT NULL,
  `perfil` varchar(100) DEFAULT NULL,
  `facultad` varchar(100) DEFAULT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `numero_personal` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_usuario`
--

CREATE TABLE `tbl_usuario` (
  `id_usuario` int(10) NOT NULL,
  `correo` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=armscii8 COLLATE=armscii8_bin;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `tbl_profesor`
--
ALTER TABLE `tbl_profesor`
  ADD PRIMARY KEY (`id_profesor`);

--
-- Indices de la tabla `tbl_usuario`
--
ALTER TABLE `tbl_usuario`
  ADD PRIMARY KEY (`id_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `tbl_profesor`
--
ALTER TABLE `tbl_profesor`
  MODIFY `id_profesor` int(10) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
