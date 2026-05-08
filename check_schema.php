<?php
require_once 'acelerador_frontend_db.php';
$conn = acelerador_frontend_db_connect();

$tables = ['tbl_usuario', 'tbl_profesor', 'tbl_tarea_entrega'];

foreach ($tables as $table) {
    echo "Table: $table\n";
    $res = mysqli_query($conn, "DESCRIBE $table");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo " - {$row['Field']}\n";
        }
    } else {
        echo " - Error: " . mysqli_error($conn) . "\n";
    }
}
