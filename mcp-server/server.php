<?php
// Servidor MCP en PHP para procesar archivos PDF

// Configuración del servidor
$host = '127.0.0.1';
$port = 5000;

// Crear un servidor HTTP
$server = stream_socket_server("tcp://$host:$port", $errno, $errstr);

if (!$server) {
    die("Error al iniciar el servidor: $errstr ($errno)\n");
}

echo "Servidor MCP corriendo en http://$host:$port\n";

while ($conn = stream_socket_accept($server)) {
    // Leer la solicitud HTTP
    $request = fread($conn, 1024);

    // Procesar la solicitud
    if (preg_match('/POST \/extract-pdf HTTP\//', $request)) {
        // Leer el contenido del archivo PDF
        $boundary = substr($request, strpos($request, "boundary=") + 9);
        $boundary = trim(explode("\n", $boundary)[0]);

        $data = explode("--$boundary", $request);
        $fileContent = null;

        foreach ($data as $part) {
            if (strpos($part, 'Content-Disposition: form-data; name="file"') !== false) {
                $fileContent = substr($part, strpos($part, "\r\n\r\n") + 4);
                $fileContent = trim($fileContent);
                break;
            }
        }

        if ($fileContent) {
            // Guardar el archivo temporalmente
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
            file_put_contents($tempFile, $fileContent);

            // Extraer texto del PDF (requiere librería como TCPDF o FPDF)
            $text = "Texto extraído del PDF (implementación pendiente)";

            // Eliminar el archivo temporal
            unlink($tempFile);

            // Responder con el texto extraído
            $response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n" . json_encode(["text" => $text]);
        } else {
            $response = "HTTP/1.1 400 Bad Request\r\nContent-Type: application/json\r\n\r\n" . json_encode(["error" => "No se proporcionó un archivo PDF válido"]);
        }
    } else {
        $response = "HTTP/1.1 404 Not Found\r\nContent-Type: text/plain\r\n\r\nRuta no encontrada";
    }

    // Enviar la respuesta
    fwrite($conn, $response);
    fclose($conn);
}

fclose($server);
