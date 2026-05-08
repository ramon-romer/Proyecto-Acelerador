<?php
// Script para enviar solicitudes al servidor MCP

$pdfPath = "../mcp-server/pdf/prueba.pdf"; // Cambia esta ruta al archivo PDF que deseas procesar

if (!file_exists($pdfPath)) {
    die("El archivo PDF no existe: " . $pdfPath);
}

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:5000/extract-pdf");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'file' => new CURLFile($pdfPath)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("Error en cURL: " . curl_error($ch));
}

curl_close($ch);

$result = json_decode($response, true);

if (isset($result['text'])) {
    echo "Texto extraído: " . $result['text'];
} else {
    echo "Error: " . ($result['error'] ?? 'Respuesta desconocida');
}
