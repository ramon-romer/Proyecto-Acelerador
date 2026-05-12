<?php

if (!function_exists('acelerador_send_no_cache_headers')) {
    /**
     * Prevent browsers from reusing protected pages after logout.
     *
     * @return void
     */
    function acelerador_send_no_cache_headers()
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

if (!function_exists('acelerador_register_bfcache_guard')) {
    /**
     * Reload persisted pages restored from browser BFCache.
     *
     * @return void
     */
    function acelerador_register_bfcache_guard()
    {
        static $registered = false;
        if ($registered || PHP_SAPI === 'cli') {
            return;
        }
        $registered = true;

        ob_start(function ($buffer) {
            if (stripos($buffer, '<html') === false) {
                return $buffer;
            }

            $script = '<script>window.addEventListener("pageshow",function(e){if(e.persisted){window.location.reload();}});</script>';

            if (stripos($buffer, '</body>') !== false) {
                return preg_replace('/<\/body>/i', $script . '</body>', $buffer, 1);
            }

            return $buffer . $script;
        });
    }
}

if (!function_exists('acelerador_apply_protected_page_session_guards')) {
    /**
     * Apply anti-cache response headers, BFCache protection,
     * and validate that the logged-in user still has active credentials.
     *
     * @return void
     */
    function acelerador_apply_protected_page_session_guards()
    {
        acelerador_send_no_cache_headers();
        acelerador_register_bfcache_guard();
        acelerador_validate_active_credentials();
    }
}

if (!function_exists('acelerador_validate_active_credentials')) {
    /**
     * Check that the currently logged-in user still exists in tbl_usuario.
     * If their account was deleted by an admin while they were browsing,
     * destroy the session and redirect to login immediately.
     *
     * @return void
     */
    function acelerador_validate_active_credentials()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }

        // Only check if there is an active session
        if (!isset($_SESSION['nombredelusuario']) || $_SESSION['nombredelusuario'] === '') {
            return;
        }

        $correo = $_SESSION['nombredelusuario'];

        // Use the same DB connection factory
        if (function_exists('acelerador_get_db_connection')) {
            $conn = acelerador_get_db_connection();
        } elseif (function_exists('acelerador_frontend_db_connect')) {
            $conn = acelerador_frontend_db_connect();
        } else {
            return; // Cannot check without DB — fail open
        }

        $correo_esc = mysqli_real_escape_string($conn, $correo);
        $result = mysqli_query($conn, "SELECT correo FROM tbl_usuario WHERE correo = '$correo_esc' LIMIT 1");

        if (!$result || mysqli_num_rows($result) === 0) {
            // User no longer has valid credentials — force logout
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }
            session_destroy();

            // Redirect to login
            header("Location: ../../acelerador_login/fronten/index.php?msg=cuenta_eliminada");
            exit();
        }
    }
}
