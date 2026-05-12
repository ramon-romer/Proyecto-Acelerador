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
     * Apply anti-cache response headers and BFCache protection.
     *
     * @return void
     */
    function acelerador_apply_protected_page_session_guards()
    {
        acelerador_send_no_cache_headers();
        acelerador_register_bfcache_guard();
    }
}
