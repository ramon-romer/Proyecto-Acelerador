<?php
require_once __DIR__ . '/../acelerador_frontend_db.php';
$conn = acelerador_frontend_db_connect();

$queries = [
    "CREATE TABLE IF NOT EXISTS `tbl_tareas` (
      `id_tarea` int(10) NOT NULL AUTO_INCREMENT,
      `id_tutor` int(10) NOT NULL,
      `id_profesor` int(10) NOT NULL,
      `titulo` varchar(255) NOT NULL,
      `descripcion` text DEFAULT NULL,
      `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id_tarea`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `tbl_entregas` (
      `id_entrega` int(10) NOT NULL AUTO_INCREMENT,
      `id_tarea` int(10) NOT NULL,
      `titulo_entrega` varchar(255) NOT NULL,
      `fecha_limite` date NOT NULL,
      `estado` enum('PENDIENTE','COMPLETADA') NOT NULL DEFAULT 'PENDIENTE',
      PRIMARY KEY (`id_entrega`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `tbl_notificaciones` (
      `id_notificacion` int(10) NOT NULL AUTO_INCREMENT,
      `id_usuario` varchar(19) NOT NULL,
      `mensaje` varchar(500) NOT NULL,
      `leida` tinyint(1) NOT NULL DEFAULT 0,
      `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `tipo` varchar(50) NOT NULL,
      PRIMARY KEY (`id_notificacion`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($queries as $q) {
    if (mysqli_query($conn, $q)) {
        echo "Query executed successfully.\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
}
echo "Done.\n";
