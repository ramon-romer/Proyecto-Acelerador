<?php
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function obtenerConexion(): mysqli
{
    $conexion = new mysqli('127.0.0.1', 'root', '', 'evaluador_aneca_experimentales_v2', 3306);
    $conexion->set_charset('utf8mb4');
    return $conexion;
}
