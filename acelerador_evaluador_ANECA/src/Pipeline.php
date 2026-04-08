<?php

require_once __DIR__ . '/TextCleaner.php';
require_once __DIR__ . '/AnecaExtractor.php';

class Pipeline
{
    /**
     * Cambia esta ruta si tu pdftotext.exe está en otra carpeta
     */
private string $pdftotext = 'C:\\poppler\\Library\\bin\\pdftotext.exe';

    public function procesar(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new Exception("El archivo PDF no existe: " . $pdfPath);
        }

        $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);
        $jsonDir  = __DIR__ . '/../output/json/';

        if (!is_dir($jsonDir) && !mkdir($jsonDir, 0777, true) && !is_dir($jsonDir)) {
            throw new Exception("No se pudo crear la carpeta de salida JSON.");
        }

        $jsonFile = $jsonDir . $baseName . '.json';

        // 1. EXTRAER TEXTO DIRECTAMENTE DEL PDF
        $texto = $this->extraerTextoPDF($pdfPath);

        if (trim($texto) === '') {
            throw new Exception("No se pudo extraer texto del PDF o el contenido está vacío.");
        }

        // 2. LIMPIAR TEXTO
        $cleaner = new TextCleaner();
        $textoLimpio = $cleaner->limpiar($texto);

        // 3. EXTRAER DATOS ESTRUCTURADOS
        $extractor = new AnecaExtractor();
        $datos = $extractor->extraer($textoLimpio);

        // 4. METADATOS
        $datos['archivo_pdf'] = basename($pdfPath);
        $datos['json_generado'] = basename($jsonFile);

        // Opcional: guarda también el texto limpio dentro del JSON
        $datos['texto_extraido'] = $textoLimpio;

        // 5. GUARDAR JSON
        $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new Exception("Error al convertir los datos a JSON.");
        }

        if (file_put_contents($jsonFile, $json) === false) {
            throw new Exception("No se pudo guardar el archivo JSON.");
        }

        return $datos;
    }

    private function extraerTextoPDF(string $pdfPath): string
    {
        if (!file_exists($this->pdftotext)) {
            throw new Exception(
                "No se encontró pdftotext.exe en la ruta configurada: " . $this->pdftotext
            );
        }

        $cmd = '"' . $this->pdftotext . '" ' . escapeshellarg($pdfPath) . ' - 2>&1';

        $output = [];
        $status = 0;

        exec($cmd, $output, $status);

        if ($status !== 0) {
            throw new Exception(
                "Error al extraer texto del PDF con pdftotext.\n" .
                "Comando: " . $cmd . "\n" .
                "Salida: " . implode("\n", $output)
            );
        }

        return implode("\n", $output);
    }
}