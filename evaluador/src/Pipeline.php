<?php

declare(strict_types=1);

require_once __DIR__ . '/compat_mbstring.php';
require_once __DIR__ . '/TextCleaner.php';
require_once __DIR__ . '/AnecaExtractor.php';
require_once __DIR__ . '/OcrProcessor.php';

class Pipeline
{
    private string $pdftotext;
    private object $extractor;

    public function __construct(?object $extractor = null)
    {
        $this->pdftotext = $this->resolverPdftotext();
        $this->extractor = $extractor ?? new AnecaExtractor();
    }

    public function procesar(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new Exception('El archivo PDF no existe: ' . $pdfPath);
        }

        $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);
        $jsonDir  = __DIR__ . '/../output/json/';

        if (!is_dir($jsonDir) && !mkdir($jsonDir, 0777, true) && !is_dir($jsonDir)) {
            throw new Exception('No se pudo crear la carpeta de salida JSON.');
        }

        $jsonFile = $jsonDir . $baseName . '.json';

        $texto = '';
        $metodoExtraccion = 'pdftotext';

        try {
            $texto = $this->extraerTextoPDF($pdfPath);
        } catch (Throwable $e) {
            $texto = '';
        }

        if (trim($texto) === '') {
            $ocr = new OcrProcessor();

            if ($ocr->estaDisponible()) {
                $texto = $ocr->extraerTextoDesdePdf($pdfPath);
                $metodoExtraccion = 'ocr';
            } else {
                $diag = $ocr->diagnosticoDisponibilidad();

                throw new Exception(
                    'No se pudo extraer texto del PDF con pdftotext y el OCR no está disponible. ' .
                    'Instala/configura Tesseract y pdftoppm. ' .
                    'pdftoppm_disponible=' . ($diag['pdftoppm_disponible'] ? 'true' : 'false') . ', ' .
                    'tesseract_disponible=' . ($diag['tesseract_disponible'] ? 'true' : 'false')
                );
            }
        }

        if (trim($texto) === '') {
            throw new Exception('No se pudo extraer texto del PDF ni mediante pdftotext ni mediante OCR.');
        }

        $cleaner = new TextCleaner();
        $textoLimpio = $cleaner->limpiar($texto);

        $datos = $this->extractor->extraer($textoLimpio);

        $datos['archivo_pdf'] = basename($pdfPath);
        $datos['json_generado'] = basename($jsonFile);
        $datos['texto_extraido'] = $textoLimpio;

        $metaDir = __DIR__ . '/../output/meta/';
        if (!is_dir($metaDir) && !mkdir($metaDir, 0777, true) && !is_dir($metaDir)) {
            throw new Exception('No se pudo crear la carpeta de metadatos.');
        }

        $metadata = [
            'archivo_pdf' => basename($pdfPath),
            'json_generado' => basename($jsonFile),
            'texto_extraido_preview' => mb_substr($textoLimpio, 0, 5000, 'UTF-8'),
            'longitud_texto_extraido' => mb_strlen($textoLimpio, 'UTF-8'),
            'metodo_extraccion' => $metodoExtraccion,
        ];

        $metadataJson = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($metadataJson === false) {
            throw new Exception('Error al convertir los metadatos a JSON.');
        }

        if (file_put_contents($metaDir . $baseName . '.meta.json', $metadataJson) === false) {
            throw new Exception('No se pudo guardar el archivo de metadatos.');
        }

        $txtDir = __DIR__ . '/../output/txt/';
        if (!is_dir($txtDir) && !mkdir($txtDir, 0777, true) && !is_dir($txtDir)) {
            throw new Exception('No se pudo crear la carpeta de salida TXT.');
        }

        if (file_put_contents($txtDir . $baseName . '.txt', $textoLimpio) === false) {
            throw new Exception('No se pudo guardar el TXT extraído.');
        }

        $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('Error al convertir los datos a JSON.');
        }

        if (file_put_contents($jsonFile, $json) === false) {
            throw new Exception('No se pudo guardar el archivo JSON.');
        }

        return $datos;
    }

    private function resolverPdftotext(): string
    {
        $envPath = getenv('PDFTOTEXT_PATH');
        if (is_string($envPath) && trim($envPath) !== '') {
            return trim($envPath);
        }

        $candidatos = [
            'C:\\poppler-25.12.0\\Library\\bin\\pdftotext.exe',
            'C:\\poppler\\Library\\bin\\pdftotext.exe',
            'C:\\poppler\\bin\\pdftotext.exe',
            'pdftotext',
        ];

        foreach ($candidatos as $candidato) {
            if ($candidato === 'pdftotext') {
                $status = 1;
                $output = [];
                $cmd = stripos(PHP_OS_FAMILY, 'Windows') === 0
                    ? 'where pdftotext 2>NUL'
                    : 'command -v pdftotext 2>/dev/null';
                exec($cmd, $output, $status);
                if ($status === 0 && !empty($output)) {
                    return 'pdftotext';
                }
                continue;
            }

            if (is_file($candidato)) {
                return $candidato;
            }
        }

        return 'pdftotext';
    }

    private function extraerTextoPDF(string $pdfPath): string
    {
        if ($this->pdftotext !== 'pdftotext' && !file_exists($this->pdftotext)) {
            throw new Exception('No se encontró pdftotext en la ruta configurada: ' . $this->pdftotext);
        }

        $cmd = (preg_match('/^[A-Za-z0-9._-]+$/', $this->pdftotext) === 1
                ? $this->pdftotext
                : '"' . str_replace('"', '\\"', $this->pdftotext) . '"')
            . ' ' . escapeshellarg($pdfPath) . ' - 2>&1';

        $output = [];
        $status = 0;
        exec($cmd, $output, $status);

        if ($status !== 0) {
            throw new Exception(
                "Error al extraer texto del PDF con pdftotext.\n" .
                'Comando: ' . $cmd . "\n" .
                'Salida: ' . implode("\n", $output)
            );
        }

        return implode("\n", $output);
    }
}
