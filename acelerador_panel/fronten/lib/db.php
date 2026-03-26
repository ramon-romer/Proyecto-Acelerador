<?php

if (!function_exists('acelerador_get_db_connection')) {
    /**
     * @return mysqli
     */
    function acelerador_get_db_connection()
    {
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $db = 'acelerador';
        $port = 3306;

        $conn = mysqli_connect($host, $user, $pass, $db, $port);
        if (!$conn) {
            die('ERROR EN LA CONEXION' . mysqli_connect_error());
        }

        mysqli_set_charset($conn, 'utf8');
        return $conn;
    }
}

