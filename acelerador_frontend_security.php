<?php
/**
 * ACELERADOR - UTILIDADES DE SEGURIDAD TRANSVERSALES
 * Implementa protección CSRF y utilidades de saneamiento.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Genera o recupera un token CSRF para la sesión actual.
 * @return string
 */
function acelerador_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Genera el campo input oculto para formularios.
 * @return string
 */
function acelerador_csrf_field() {
    $token = acelerador_get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verifica si el token CSRF recibido es válido.
 * @return bool
 */
function acelerador_verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            return false;
        }
    }
    return true;
}

/**
 * Aborta la ejecución si falla el CSRF.
 */
function acelerador_require_csrf() {
    if (!acelerador_verify_csrf()) {
        header('HTTP/1.1 403 Forbidden');
        die('Error de seguridad: Token CSRF inválido o ausente.');
    }
}
