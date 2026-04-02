<?php

class OcrProcessor
{
    public function procesarImagenes(array $imagenes): string
    {
        $textoCompleto = '';

        $tesseract = '"C:\\Program Files\\Tesseract-OCR\\tesseract.exe"';

        foreach ($imagenes as $img) {

            // 👇 salida a stdout (clave: usar "stdout")
            $cmd = $tesseract . ' ' . escapeshellarg($img) . ' stdout -l spa 2>&1';

            $output = [];
            $status = 0;

            exec($cmd, $output, $status);

            if ($status !== 0) {
                throw new Exception(
                    "Error OCR en: " . basename($img) .
                    "\n\nSalida: " . implode("\n", $output)
                );
            }

            $textoCompleto .= implode("\n", $output) . "\n\n";
        }

        // 👇 devolver JSON directamente
        return json_encode([
            'texto' => $textoCompleto
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}