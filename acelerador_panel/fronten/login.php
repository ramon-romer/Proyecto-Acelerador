<?php
require_once __DIR__ . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/acelerador_login/fronten/lib/session_security.php';

acelerador_apply_protected_page_session_guards();

$conn = acelerador_get_db_connection();
?>
