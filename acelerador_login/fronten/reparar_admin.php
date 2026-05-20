<?php
/**
 * ACELERADOR - SCRIPT DE REPARACIÓN DE ADMINISTRADOR
 * Ejecuta este script una vez para asegurar que el admin tenga perfil en todas las tablas.
 */
require_once dirname(__DIR__, 2) . '/acelerador_frontend_db.php';
$conn = acelerador_frontend_db_connect();

echo "<h2>Reparando acceso de Administrador...</h2>";

$admins = [
    ['correo' => 'admin@admin.com', 'pass' => '1234'],
    ['correo' => 'admin_acelerador', 'pass' => '12345678Y#']
];

foreach ($admins as $a) {
    $mail = $a['correo'];
    $pass = $a['pass'];
    
    // 1. Asegurar en tbl_usuario
    $res = mysqli_query($conn, "SELECT id_usuario FROM tbl_usuario WHERE correo = '$mail'");
    if (mysqli_num_rows($res) == 0) {
        mysqli_query($conn, "INSERT INTO tbl_usuario (correo, password) VALUES ('$mail', '$pass')");
        echo "Creado '$mail' en tbl_usuario.<br>";
    }

    // 2. Asegurar en tbl_profesor (Requerido por el sistema de perfiles)
    $res2 = mysqli_query($conn, "SELECT id_profesor FROM tbl_profesor WHERE correo = '$mail'");
    if (mysqli_num_rows($res2) == 0) {
        $sql = "INSERT INTO tbl_profesor (nombre, apellidos, password, DNI, ORCID, telefono, perfil, facultad, departamento, correo, rama) 
                VALUES ('Admin', 'Sistema', '$pass', '00000000A', '0000-0000-0000-0000', 0, 'ADMIN', 'ADMIN', 'ADMIN', '$mail', 'TECNICA')";
        if (mysqli_query($conn, $sql)) {
            echo "Perfil ADMIN creado para '$mail' en tbl_profesor.<br>";
        } else {
            echo "Error creando perfil para '$mail': " . mysqli_error($conn) . "<br>";
        }
    }
}

echo "<br><b>Hecho. Ahora puedes usar cualquiera de las dos cuentas en el login normal.</b>";
mysqli_close($conn);
?>
