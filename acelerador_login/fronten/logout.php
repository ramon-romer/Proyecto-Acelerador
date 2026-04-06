<?php
require_once __DIR__ . '/lib/session_security.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

acelerador_send_no_cache_headers();

$_SESSION = [];
session_unset();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? false)
    );
}

session_destroy();
header('Location: index.php');
exit();
?>
