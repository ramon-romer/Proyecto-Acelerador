<?php
require_once 'acelerador_frontend_db.php';
$conn = acelerador_frontend_db_connect();

echo "--- COMPROBACIÓN DE ADMINISTRADOR ---\n";

$res1 = mysqli_query($conn, "SELECT correo FROM tbl_usuario WHERE correo LIKE '%admin%'");
echo "Usuarios con 'admin' en tbl_usuario:\n";
while($r = mysqli_fetch_assoc($res1)) echo "- " . $r['correo'] . "\n";

$res2 = mysqli_query($conn, "SELECT correo, perfil FROM tbl_profesor WHERE perfil LIKE '%ADMIN%'");
echo "\nUsuarios con perfil 'ADMIN' en tbl_profesor:\n";
while($r = mysqli_fetch_assoc($res2)) echo "- " . $r['correo'] . " (" . $r['perfil'] . ")\n";

mysqli_close($conn);
