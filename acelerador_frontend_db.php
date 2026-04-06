<?php

if (!function_exists('acelerador_frontend_db_config')) {
    /**
     * @return array{host:string,user:string,pass:string,name:string,port:int,charset:string}
     */
    function acelerador_frontend_db_config()
    {
        $portRaw = getenv('ACELERADOR_DB_PORT');
        $port = is_string($portRaw) && $portRaw !== '' ? (int)$portRaw : 3306;
        if ($port <= 0) {
            $port = 3306;
        }

        $charsetRaw = getenv('ACELERADOR_DB_CHARSET');
        $charset = is_string($charsetRaw) && trim($charsetRaw) !== '' ? trim($charsetRaw) : 'utf8mb4';

        return [
            'host' => getenv('ACELERADOR_DB_HOST') ?: 'localhost',
            'user' => getenv('ACELERADOR_DB_USER') ?: 'root',
            'pass' => getenv('ACELERADOR_DB_PASS') ?: '',
            'name' => getenv('ACELERADOR_DB_NAME') ?: 'acelerador',
            'port' => $port,
            'charset' => $charset,
        ];
    }
}

if (!function_exists('acelerador_frontend_db_connect')) {
    /**
     * @return mysqli
     */
    function acelerador_frontend_db_connect()
    {
        $cfg = acelerador_frontend_db_config();
        $conn = mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port']);

        if (!$conn) {
            die('ERROR EN LA CONEXION' . mysqli_connect_error());
        }

        mysqli_set_charset($conn, $cfg['charset']);
        return $conn;
    }
}

