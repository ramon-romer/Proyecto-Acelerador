<?php
/**
 * REDIRECCIÓN AL NUEVO LOGIN CONSOLIDADO
 * El flujo de autenticación ahora reside en login.php
 */
header("Location: login.php" . (count($_GET) > 0 ? "?" . http_build_query($_GET) : ""));
exit();
?>