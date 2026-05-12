<?php
$conn = mysqli_connect('127.0.0.1', 'root', '', 'acelerador_db');
if (!$conn) {
    // maybe db port is different, use the same as the project
    $dbPort = getenv('DB_PORT') ?: 3306;
    $conn = mysqli_connect('127.0.0.1', 'root', '', 'acelerador_db', $dbPort);
}
if (!$conn) {
    $conn = mysqli_connect('127.0.0.1', 'root', 'root', 'acelerador_db');
}
if (!$conn) {
    echo "Connection failed.\n";
    exit;
}

$res = mysqli_query($conn, "DESCRIBE tbl_tarea_entrega");
if ($res) {
    while($row = mysqli_fetch_assoc($res)) {
        print_r($row);
    }
} else {
    echo mysqli_error($conn);
}
