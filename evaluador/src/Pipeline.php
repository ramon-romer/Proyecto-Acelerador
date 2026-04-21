<?php

declare(strict_types=1);

require_once __DIR__ . '/TextCleaner.php';
require_once __DIR__ . '/AnecaExtractor.php';
require_once __DIR__ . '/OcrProcessor.php';

class Pipeline
{
    private string $pdftotext;
    private OcrProcessor $ocrProcessor;

    public function __construct(?OcrProcessor $ocrProcessor = null, ?string $pdftotextPath = null)
    {
        $this->pdftotext = $this->resolverRutaPdftotext($pdftotextPath);
        $this->ocrProcessor = $ocrProcessor ?? new OcrProcessor();
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

        $resultadoExtraccion = $this->extraerTextoConFallback($pdfPath);
        $texto = $resultadoExtraccion['texto'];

        if (trim($texto) === '') {
            throw new Exception($resultadoExtraccion['detalle_extraccion_texto']);
        }

        $cleaner = new TextCleaner();
        $textoLimpio = $cleaner->limpiar($texto);

        if (trim($textoLimpio) === '') {
            throw new Exception('Se extrajo contenido, pero quedó vacío tras la limpieza del texto.');
        }

        $extractor = new AnecaExtractor();
        $datos = $extractor->extraer($textoLimpio);

        $datos['archivo_pdf'] = basename($pdfPath);
        $datos['json_generado'] = basename($jsonFile);
        $datos['texto_extraido'] = $textoLimpio;

        if (!isset($datos['metadatos_extraccion']) || !is_array($datos['metadatos_extraccion'])) {
            $datos['metadatos_extraccion'] = [];
        }

        $datos['metadatos_extraccion'] = array_merge(
            $datos['metadatos_extraccion'],
            [
                'archivo_pdf' => basename($pdfPath),
                'fecha_extraccion' => date('c'),
                'fallback_ocr_activado' => $resultadoExtraccion['fallback_ocr_activado'],
                'ocr_disponible' => $resultadoExtraccion['ocr_disponible'],
                'modo_extraccion_texto' => $resultadoExtraccion['modo_extraccion_texto'],
                'detalle_extraccion_texto' => $resultadoExtraccion['detalle_extraccion_texto'],
                'caracteres_texto_extraido' => mb_strlen($textoLimpio, 'UTF-8'),
            ]
        );

        $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new Exception('Error al convertir los datos a JSON.');
        }

        if (file_put_contents($jsonFile, $json) === false) {
            throw new Exception('No se pudo guardar el archivo JSON.');
        }

        return $datos;
    }

    private function extraerTextoConFallback(string $pdfPath): array
    {
        $textoPdftotext = '';
        $errorPdftotext = null;

        try {
            $textoPdftotext = $this->extraerTextoPDF($pdfPath);
        } catch (Throwable $e) {
            $errorPdftotext = $e->getMessage();
        }

        $ocrDisponible = $this->ocrProcessor->estaDisponible();
        $detallePdftotext = $errorPdftotext !== null
            ? 'pdftotext falló: ' . $errorPdftotext
            : 'pdftotext devolvió ' . mb_strlen(trim($textoPdftotext), 'UTF-8') . ' caracteres.';

        if ($this->textoTieneContenidoUtil($textoPdftotext)) {
            return [
                'texto' => $textoPdftotext,
                'fallback_ocr_activado' => false,
                'ocr_disponible' => $ocrDisponible,
                'modo_extraccion_texto' => 'pdftotext',
                'detalle_extraccion_texto' => $detallePdftotext,
            ];
        }

        if ($ocrDisponible) {
            try {
                $textoOcr = $this->ocrProcessor->extraerTextoDesdePdf($pdfPath);

                if ($this->textoTieneContenidoUtil($textoOcr)) {
                    return [
                        'texto' => $textoOcr,
                        'fallback_ocr_activado' => true,
                        'ocr_disponible' => true,
                        'modo_extraccion_texto' => 'ocr_fallback',
                        'detalle_extraccion_texto' => $detallePdftotext . ' Se activó OCR porque el PDF no tenía texto nativo utilizable.',
                    ];
                }

                throw new Exception('OCR ejecutado, pero también devolvió contenido vacío o insuficiente.');
            } catch (Throwable $e) {
                throw new Exception(
                    $detallePdftotext . ' Además, el fallback OCR falló: ' . $e->getMessage()
                );
            }
        }

        throw new Exception(
            $detallePdftotext . ' El PDF parece escaneado o sin texto seleccionable y OCR no está disponible. ' .
            'Instala/configura pdftoppm y Tesseract para procesar este tipo de PDF.'
        );
    }

    private function extraerTextoPDF(string $pdfPath): string
    {
        if (!$this->rutaEjecutableValida($this->pdftotext)) {
            throw new Exception('No se encontró pdftotext.exe en la ruta configurada: ' . $this->pdftotext);
        }

        $cmd = $this->construirComandoEjecutable($this->pdftotext)
            . ' '
            . escapeshellarg($pdfPath)
            . ' - 2>&1';

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

    private function textoTieneContenidoUtil(string $texto): bool
    {
        $texto = trim($texto);
        if ($texto === '') {
            return false;
        }

        $soloEspacios = preg_replace('/\s+/u', '', $texto) ?? '';
        if (mb_strlen($soloEspacios, 'UTF-8') < 40) {
            return false;
        }

        preg_match_all('/\p{L}/u', $texto, $matches);
        return count($matches[0]) >= 20;
    }

    private function resolverRutaPdftotext(?string $pdftotextPath): string
    {
        $repoRoot = dirname(__DIR__, 2);

        $candidatas = array_filter([
            $pdftotextPath,
            getenv('PDFTOTEXT_PATH') ?: null,
            $repoRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . '.tools' . DIRECTORY_SEPARATOR . 'poppler' . DIRECTORY_SEPARATOR . 'poppler-25.07.0' . DIRECTORY_SEPARATOR . 'Library' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'pdftotext.exe',
            'C:\\poppler-25.12.0\\Library\\bin\\pdftotext.exe',
            'C:\\poppler\\Library\\bin\\pdftotext.exe',
            'C:\\poppler\\bin\\pdftotext.exe',
            'pdftotext',
        ]);

        foreach ($candidatas as $ruta) {
            if (!is_string($ruta) || trim($ruta) === '') {
                continue;
            }

            $ruta = trim($ruta);
            if ($this->rutaEjecutableValida($ruta)) {
                return $ruta;
            }
        }

        return 'C:\\poppler-25.12.0\\Library\\bin\\pdftotext.exe';
    }

    private function rutaEjecutableValida(string $ruta): bool
    {
        if (is_file($ruta)) {
            return true;
        }

        if (preg_match('/^[A-Za-z0-9._-]+$/', $ruta) === 1) {
            $searchCmd = stripos(PHP_OS_FAMILY, 'Windows') === 0
                ? 'where ' . escapeshellarg($ruta) . ' 2>NUL'
                : 'command -v ' . escapeshellarg($ruta) . ' 2>/dev/null';

            $output = [];
            $status = 0;
            exec($searchCmd, $output, $status);

            return $status === 0 && !empty($output);
        }

        return false;
    }

    private function construirComandoEjecutable(string $pathOrCommand): string
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/', $pathOrCommand) === 1) {
            return $pathOrCommand;
        }

        return '"' . str_replace('"', '\\"', $pathOrCommand) . '"';
    }
}
