<?php
declare(strict_types=1);

/**
 * ============================================================
 * CONFIGURACIÓN DE BASE DE DATOS
 * ============================================================
 *
 * Este archivo crea la conexión con MySQL mediante PDO.
 * Se incluye desde el resto de archivos con:
 *
 *   require __DIR__ . '/config.php';
 *
 * La variable resultante es:
 *   $pdo
 */

// Servidor MySQL
$servidor = '127.0.0.1';

// Nombre de la base de datos
$base_datos = 'evaluador_aneca_csyj';

// Usuario de MySQL
$usuario_bd = 'root';

// Contraseña de MySQL
$contrasena_bd = '';

// Juego de caracteres recomendado
$juego_caracteres = 'utf8mb4';

// Cadena DSN de conexión
$dsn = "mysql:host=$servidor;dbname=$base_datos;charset=$juego_caracteres";

// Opciones PDO
$opciones = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    // Crear conexión
    $pdo = new PDO($dsn, $usuario_bd, $contrasena_bd, $opciones);
} catch (PDOException $e) {
    die('Error de conexión con la base de datos: ' . $e->getMessage());
}
