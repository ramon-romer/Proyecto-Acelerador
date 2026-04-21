<?php

if (!function_exists('acelerador_frontend_read_env')) {
    /**
     * @param array<int,string> $keys
     * @param string $default
     * @return string
     */
    function acelerador_frontend_read_env(array $keys, $default = '')
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }
}

if (!function_exists('acelerador_frontend_db_config')) {
    /**
     * @return array{host:string,user:string,pass:string,name:string,name_candidates:array<int,string>,port:int,charset:string}
     */
    function acelerador_frontend_db_config()
    {
        $portRaw = acelerador_frontend_read_env(['ACELERADOR_DB_PORT', 'DB_PORT'], '3306');
        $port = (int)$portRaw;
        if ($port <= 0) {
            $port = 3306;
        }

        $charset = acelerador_frontend_read_env(['ACELERADOR_DB_CHARSET', 'DB_CHARSET'], 'utf8mb4');
        $dbNameFromEnv = acelerador_frontend_read_env(['ACELERADOR_DB_NAME', 'DB_NAME'], '');

        $nameCandidates = [];
        $rawCandidates = [$dbNameFromEnv, 'Acelerador', 'acelerador'];
        foreach ($rawCandidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);
            if ($candidate === '' || in_array($candidate, $nameCandidates, true)) {
                continue;
            }

            $nameCandidates[] = $candidate;
        }

        if ($nameCandidates === []) {
            $nameCandidates[] = 'Acelerador';
        }

        return [
            'host' => acelerador_frontend_read_env(['ACELERADOR_DB_HOST', 'DB_HOST'], 'localhost'),
            'user' => acelerador_frontend_read_env(['ACELERADOR_DB_USER', 'DB_USER'], 'root'),
            'pass' => acelerador_frontend_read_env(['ACELERADOR_DB_PASS', 'DB_PASS'], ''),
            'name' => $nameCandidates[0],
            'name_candidates' => $nameCandidates,
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

        $lastError = '';
        foreach ($cfg['name_candidates'] as $databaseName) {
            $conn = @mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $databaseName, $cfg['port']);
            if ($conn) {
                mysqli_set_charset($conn, $cfg['charset']);
                return $conn;
            }

            $lastError = mysqli_connect_error();
        }

        die('ERROR EN LA CONEXION' . $lastError);
    }
}