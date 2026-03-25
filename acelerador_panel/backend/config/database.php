<?php
declare(strict_types=1);

return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'name' => getenv('DB_NAME') ?: 'acelerador',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
];

