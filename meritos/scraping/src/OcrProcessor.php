<?php

class OcrProcessor
{
    public function procesarImagenes(array $imagenes): string
    {
        $textoCompleto = '';

        $tesseract = '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe"';

        foreach ($imagenes as $img) {
            $base = str_replace('.png', '', $img);

            $cmd = $tesseract . ' ' . escapeshellarg($img) . ' ' . escapeshellarg($base) . ' -l spa 2>&1';

            $output = [];
            $status = 0;

            exec($cmd, $output, $status);

            echo "<pre>";
            echo "COMANDO:\n" . htmlspecialchars($cmd) . "\n\n";
            echo "STATUS:\n" . htmlspecialchars((string)$status) . "\n\n";
            echo "SALIDA:\n" . htmlspecialchars(implode("\n", $output));
            echo "</pre>";

            if ($status !== 0) {
                throw new Exception(
                    "Error al ejecutar Tesseract sobre: " . basename($img) .
                    "\n\nComando: " . $cmd .
                    "\n\nSalida: " . implode("\n", $output)
                );
            }

            $txtFile = $base . '.txt';

            if (!file_exists($txtFile)) {
                throw new Exception("Tesseract no generó el archivo TXT para: " . basename($img));
            }

            $textoCompleto .= file_get_contents($txtFile) . "\n\n";
        }

        return $textoCompleto;
    }
}