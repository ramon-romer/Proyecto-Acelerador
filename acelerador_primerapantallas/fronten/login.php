<?php
    $host = 'localhost';
    $nom = 'root';
    $pass = '';
    $db = 'acelerador';

    $conn = mysqli_connect($host,$nom,$pass,$db);

    if (!$conn) {
        die('ERROR EN LA CONEXIÖN'. mysqli_connect_error());
    }
?>

