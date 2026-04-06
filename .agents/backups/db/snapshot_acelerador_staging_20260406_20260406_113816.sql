-- Snapshot lógico generado automáticamente
-- Run ID: F4C_20260406_113816
-- Database: acelerador_staging_20260406
-- Generated at: 2026-04-06T11:38:16+02:00

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `tbl_convocatoria`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_convocatoria`;
CREATE TABLE `tbl_convocatoria` (
  `id_convocatoria` int(10) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `fecha_convocatoria` varchar(50) NOT NULL,
  `tipo` enum('CONCURSO DE MERITOS','OPOSICION') NOT NULL,
  `organismo` enum('PUBLICO','PRIVADO') NOT NULL,
  PRIMARY KEY (`id_convocatoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Records of `tbl_convocatoria`
-- ----------------------------

-- ----------------------------
-- Table structure for `tbl_grupo`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_grupo`;
CREATE TABLE `tbl_grupo` (
  `id_grupo` int(10) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `id_tutor` int(10) NOT NULL,
  PRIMARY KEY (`id_grupo`),
  KEY `fk_tutor` (`id_tutor`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Records of `tbl_grupo`
-- ----------------------------
INSERT INTO `tbl_grupo` (`id_grupo`, `nombre`, `id_tutor`) VALUES ('1', 'Grupo 1', '47');
INSERT INTO `tbl_grupo` (`id_grupo`, `nombre`, `id_tutor`) VALUES ('2', 'grupo 1', '48');
INSERT INTO `tbl_grupo` (`id_grupo`, `nombre`, `id_tutor`) VALUES ('3', 'grupo2', '48');
INSERT INTO `tbl_grupo` (`id_grupo`, `nombre`, `id_tutor`) VALUES ('4', 'afperez', '49');

-- ----------------------------
-- Table structure for `tbl_grupo_profesor`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_grupo_profesor`;
CREATE TABLE `tbl_grupo_profesor` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `id_grupo` int(10) NOT NULL,
  `id_profesor` int(10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_grupo` (`id_grupo`),
  KEY `fk_profesor` (`id_profesor`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Records of `tbl_grupo_profesor`
-- ----------------------------
INSERT INTO `tbl_grupo_profesor` (`id`, `id_grupo`, `id_profesor`) VALUES ('1', '0', '27');
INSERT INTO `tbl_grupo_profesor` (`id`, `id_grupo`, `id_profesor`) VALUES ('2', '2', '27');

-- ----------------------------
-- Table structure for `tbl_merito`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_merito`;
CREATE TABLE `tbl_merito` (
  `id_merito` int(10) NOT NULL,
  `ORCID_autor` varchar(19) NOT NULL,
  `id_publicacion` int(10) NOT NULL,
  `id_convocatoria` int(10) NOT NULL,
  `aportacion_personal` mediumtext NOT NULL,
  `valoracion` int(2) NOT NULL,
  `observaciones` mediumtext NOT NULL,
  PRIMARY KEY (`id_merito`),
  KEY `FK_convocatoria` (`id_convocatoria`),
  KEY `FK_publicacion` (`id_publicacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Records of `tbl_merito`
-- ----------------------------

-- ----------------------------
-- Table structure for `tbl_profesor`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_profesor`;
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
  `rama` enum('SALUD','TECNICA','S Y J','HUMANIDADES','EXPERIMENTALES') NOT NULL,
  PRIMARY KEY (`ORCID`),
  UNIQUE KEY `id_profesor` (`id_profesor`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Records of `tbl_profesor`
-- ----------------------------
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('1', 'ramon', 'Romero Rodriguez', '1234', '12345678P', '', '653471245', '', 'Ciencias', 'Polinecnica', 'ramon@gmail.com', '');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('51', 'Ram?n', 'Romero Rodr?guez', 'Milka2005.', '12345679P', '0000-0000-0000-0000', '673494145', 'PROFESOR', 'historia', 'Tecnologia', 'ramonelhermano@gmail.com', 'SALUD');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('35', 'sdafg', 'acvb', '$2y$10$pfec5giWkNWMpx5IkScnveRY16h2bfZKK8ZZZIN/5JDb6V.ice07G', '12345678D', '0000-1111-2222-2090', '987654321', 'TUTOR', 'cvbn', 'qwge', 'n@gmail.com', 'SALUD');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('34', 'sdafg', 'acvb', '$2y$10$A34hAX6b80ncvE86DY6UjOAbI0Bhqg221DXS4ced7G0eKKYFCyf7u', '12345678D', '0000-1111-2222-2098', '987654321', 'TUTOR', 'cvbn', 'qwge', 'lui@gmail.com', 'SALUD');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('27', 'lui', 'dtrfyguyh', '12345678Y#', '12345678D', '0000-1111-2222-2225', '987654321', 'PROFESOR', 'cvbn', 'asdfgchdg', 'pruebacompleta@ceuandalucia.es', 'TECNICA');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('33', 'sdafg', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-2228', '987654321', 'PROFESOR', 'fdgedrg', 'qwgeth', 'lui@gmail.com', 'SALUD');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('3', 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-2254', '123456789', 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', '');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('38', 'sdafg', 'dtrfyguyh', '$2y$10$ZqX1LDETTJP2X301MtHvn.QgLp8UnRJk8wGKLG54oATI4nS2VpQCm', '12345678Z', '0000-1111-2222-2260', '987654323', 'TUTOR', 'fdgedrg', 'cwgevbj', 'as@gmail.com', 'TECNICA');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('2', 'ADcfsd', 'dtrfyguyh', '12345678Y#', '12345678D', '0000-1111-2222-2544', '987654321', '', 'dsfgh', 'fcgevhbj', 'xcvds@rgg', 'HUMANIDADES');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('10', 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-3000', '123456789', 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', 'SALUD');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('11', 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-4000', '123456789', 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', '');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('12', 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-5000', '123456789', 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', '');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('21', 'fqcgwvheb', 'qwer', '12345678Y#', '12345678D', '0000-1111-2222-5001', '987654321', 'PROFESOR', 'wthevyjbtr', 'afsgh', 'ads@gmail.com', 'HUMANIDADES');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('25', 'fqcgwvheb', 'qwer', '123456789Y#', '12345678D', '0000-1111-2222-5010', '987654321', 'PROFESOR', 'wthevyjbtr', 'afsgh', 'ads@gmail.com', 'HUMANIDADES');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('26', 'fqcgwvheb', 'qwer', '12345678Y#', '12345678D', '0000-1111-2222-5020', '987654321', '', 'wthevyjbtr', 'afsgh', 'ads322@gmail.com', 'HUMANIDADES');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('17', 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-6000', '123456789', 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', 'HUMANIDADES');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('18', 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-7000', '123456789', 'TUTOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', 'EXPERIMENTALES');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('20', 'lui', 'qdcgfvh', '12345678Y#', '12345678D', '0000-1111-2222-8000', '123456789', 'PROFESOR', 'qwcvhbj', 'cwgevbj', 'lui@gmail.com', 'EXPERIMENTALES');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('49', 'nacho', 'ipuerta', '$2y$10$fM0.5AA/RK7X/kkXTLPtBOOwXyBxyCyqqT6SVftTga/uaHmWYWMn2', '12345668D', '0100-1111-2202-2225', '987544441', 'PROFESOR', 'dsfgh', 'fdagshgd', 'nacho.puemar@gmail.com', 'TECNICA');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('52', 'pep?', 'p?co p?rras', 'M123456789.', '12345679P', '1111-1111-1111-1111', '632854725', '', 'historia', 'Tecnologia', 'pepe@gmail.com', 'SALUD');
INSERT INTO `tbl_profesor` (`id_profesor`, `nombre`, `apellidos`, `password`, `DNI`, `ORCID`, `telefono`, `perfil`, `facultad`, `departamento`, `correo`, `rama`) VALUES ('39', 'agsdhfj', 'agsdh', '$2y$10$hqR8PGeykIclMQr2sYlDVuja6k4kdloMGYAb2MR/vTCC2YiXkKpEu', '34769834R', '1222-2345-6789-1234', '123456876', 'PROFESOR', 'dsfgh', 'dfsegrthjky', 'pruebacompleta2@ceuandalucia.es', 'HUMANIDADES');

-- ----------------------------
-- Table structure for `tbl_publicacion`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_publicacion`;
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
  `documento` varchar(255) NOT NULL,
  PRIMARY KEY (`id_publicación`),
  KEY `FK_autor` (`ORCID_autor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Records of `tbl_publicacion`
-- ----------------------------

-- ----------------------------
-- Table structure for `tbl_usuario`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_usuario`;
CREATE TABLE `tbl_usuario` (
  `id_usuario` int(10) NOT NULL AUTO_INCREMENT,
  `correo` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Records of `tbl_usuario`
-- ----------------------------
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('1', 'xcvds@rgg', '12345678Y#');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('2', 'ads322@gmail.com', '12345678Y#');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('3', 'pruebacompleta@ceuandalucia.es', '12345678Y#');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('5', 'lui@gmail.com', '$2y$10$A34hAX6b80ncvE86DY6UjOAbI0Bhqg221DXS4ced7G0eKKYFCyf7u');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('6', 'n@gmail.com', '$2y$10$pfec5giWkNWMpx5IkScnveRY16h2bfZKK8ZZZIN/5JDb6V.ice07G');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('7', 'as@gmail.com', '$2y$10$ZqX1LDETTJP2X301MtHvn.QgLp8UnRJk8wGKLG54oATI4nS2VpQCm');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('8', 'pruebacompleta2@ceuandalucia.es', '$2y$10$hqR8PGeykIclMQr2sYlDVuja6k4kdloMGYAb2MR/vTCC2YiXkKpEu');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('18', 'nacho.puemar@gmail.com', '$2y$10$fM0.5AA/RK7X/kkXTLPtBOOwXyBxyCyqqT6SVftTga/uaHmWYWMn2');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('19', 'salvador.barua.espejo@gmail.com', '$2y$10$5qgIb/W89ZTKmgi93HwHwuvKvqLK9l60/jxg5pWtpLrELLDDlnmCG');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('20', 'ramonelhermano@gmail.com', 'Milka2005.');
INSERT INTO `tbl_usuario` (`id_usuario`, `correo`, `password`) VALUES ('21', 'pepe@gmail.com', 'M123456789.');

SET FOREIGN_KEY_CHECKS=1;
