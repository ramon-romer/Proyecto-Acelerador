<?php

require_once __DIR__ . '/../src/OcrEnvironmentChecker.php';

$checker = new OcrEnvironmentChecker();
$resultado = $checker->check();

$json = json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "No se pudo serializar la salida JSON.\n");
    exit(1);
}

echo $json . PHP_EOL;

