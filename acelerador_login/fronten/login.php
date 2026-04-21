<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'acelerador';
$port = 3306;

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 3);

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port)) {
    die('ERROR MYSQL: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
?>