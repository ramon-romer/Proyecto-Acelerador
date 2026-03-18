<?php
// Script para extraer texto de un archivo PDF

require __DIR__ . '/../vendor/autoload.php'; // Asegúrate de que el autoload de Composer esté incluido
use Smalot\PdfParser\Parser;

function extractTextFromPDF($filePath) {
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        return $text;
    } catch (Exception $e) {
        return "Error al procesar el PDF: " . $e->getMessage();
    }
}

// Ejemplo de uso
if (isset($argv[1])) {
    $filePath = $argv[1];

    if (!file_exists($filePath)) {
        echo json_encode(["error" => "El archivo no existe"]);
        exit;
    }

    $text = extractTextFromPDF($filePath);
    echo json_encode(["text" => $text]);
} else {
    echo json_encode(["error" => "No se proporcionó la ruta del archivo"]);
}

// Crear la carpeta "resultados" si no existe
$resultadosDir = __DIR__ . '/resultados';
if (!is_dir($resultadosDir)) {
    mkdir($resultadosDir, 0777, true);
}

// Guardar el resultado en un archivo JSON
$resultFilePath = $resultadosDir . '/resultados.json';
file_put_contents($resultFilePath, json_encode(["text" => $text], JSON_PRETTY_PRINT));

// Mostrar mensaje de éxito
echo json_encode(["message" => "Resultado guardado en 'resultados/resultados.json'"]);