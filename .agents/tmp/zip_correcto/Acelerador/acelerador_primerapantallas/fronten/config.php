<?php
// Configuración del sistema de méritos

// Parámetros de conexión a la base de datos
define('DB_HOST', 'localhost');      // Host del servidor
define('DB_USER', 'root');           // Usuario de base de datos
define('DB_PASS', '');               // Contraseña
define('DB_NAME', 'Acelerador');     // Nombre de la base de datos
define('DB_PORT', 3306);             // Puerto (por defecto 3306 para MySQL)

// Crear conexión
try {
    $conn =  mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    // Verificar conexión
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    // Establecer charset
    $conn->set_charset("utf8");
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>