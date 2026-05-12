<?php

require_once dirname(__DIR__, 3) . '/acelerador_frontend_db.php';

if (!function_exists('acelerador_get_db_connection')) {
    /**
     * @return mysqli
     */
    function acelerador_get_db_connection()
    {
        return acelerador_frontend_db_connect();
    }
}

