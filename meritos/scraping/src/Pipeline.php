<?php

require_once __DIR__ . '/PdfToImage.php';
require_once __DIR__ . '/OcrProcessor.php';
require_once __DIR__ . '/TextCleaner.php';
require_once __DIR__ . '/AnecaExtractor.php';

class Pipeline
{
    public function procesar(string $pdfPath): array
    {
        $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);

        $imageDir = __DIR__ . '/../output/images/';
        $textDir  = __DIR__ . '/../output/text/';
        $jsonDir  = __DIR__ . '/../output/json/';

        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0777, true);
        }

        if (!is_dir($textDir)) {
            mkdir($textDir, 0777, true);
        }

        if (!is_dir($jsonDir)) {
            mkdir($jsonDir, 0777, true);
        }

        $imagePrefix = $imageDir . $baseName;
        $textFile = $textDir . $baseName . '.txt';
        $jsonFile = $jsonDir . $baseName . '.json';

        $pdfToImage = new PdfToImage();
        $imagenes = $pdfToImage->convertir($pdfPath, $imagePrefix);

        if (empty($imagenes)) {
            throw new Exception("No se generaron imágenes del PDF");
        }

        $ocr = new OcrProcessor();
        $texto = $ocr->procesarImagenes($imagenes);

        file_put_contents($textFile, $texto);

        $cleaner = new TextCleaner();
        $textoLimpio = $cleaner->limpiar($texto);

        $extractor = new AnecaExtractor();
        $datos = $extractor->extraer($textoLimpio);

        $datos['archivo_pdf'] = basename($pdfPath);
        $datos['paginas_detectadas'] = count($imagenes);
        $datos['txt_generado'] = basename($textFile);
        $datos['json_generado'] = basename($jsonFile);

        file_put_contents(
            $jsonFile,
            json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $datos;
    }
}