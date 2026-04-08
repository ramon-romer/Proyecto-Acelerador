<?php
declare(strict_types=1);

/**
 * Configuración de conexión a MySQL mediante PDO.
 */

$servidor = '127.0.0.1';
$base_datos = 'evaluador_aneca_salud_v2';
$usuario_bd = 'root';
$contrasena_bd = '';
$juego_caracteres = 'utf8mb4';

$dsn = "mysql:host=$servidor;dbname=$base_datos;charset=$juego_caracteres";

$opciones = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $usuario_bd, $contrasena_bd, $opciones);
} catch (PDOException $e) {
    die('Error de conexión con la base de datos: ' . $e->getMessage());
}
