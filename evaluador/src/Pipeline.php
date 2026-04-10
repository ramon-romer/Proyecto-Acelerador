<?php

require_once __DIR__ . '/TextCleaner.php';
require_once __DIR__ . '/AnecaExtractor.php';
require_once __DIR__ . '/OcrProcessor.php';

class Pipeline
{
    private ?string $pdftotextPath;
    private OcrProcessor $ocrProcessor;
    private int $nativeMinChars;
    private float $nativeMinAlnumRatio;

    public function __construct(?OcrProcessor $ocrProcessor = null)
    {
        $repoRoot = dirname(__DIR__, 2);

        $this->pdftotextPath = $this->resolveExecutablePath(
            'PDFTOTEXT_PATH',
            [
                $repoRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . '.tools' . DIRECTORY_SEPARATOR . 'poppler' . DIRECTORY_SEPARATOR . 'poppler-25.07.0' . DIRECTORY_SEPARATOR . 'Library' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'pdftotext.exe',
                'C:\\poppler-25.12.0\\Library\\bin\\pdftotext.exe',
                'C:\\poppler\\Library\\bin\\pdftotext.exe',
                'C:\\poppler\\bin\\pdftotext.exe',
            ],
            'pdftotext'
        );

        $this->ocrProcessor = $ocrProcessor ?? new OcrProcessor();
        $this->nativeMinChars = $this->readEnvInt('ANECA_NATIVE_MIN_CHARS', 120, 20, 5000);
        $this->nativeMinAlnumRatio = $this->readEnvFloat('ANECA_NATIVE_MIN_ALNUM_RATIO', 0.45, 0.10, 0.95);
    }

    public function procesar(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new Exception("El archivo PDF no existe: " . $pdfPath);
        }

        $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);
        $jsonDir = __DIR__ . '/../output/json/';

        if (!is_dir($jsonDir) && !mkdir($jsonDir, 0777, true) && !is_dir($jsonDir)) {
            throw new Exception("No se pudo crear la carpeta de salida JSON.");
        }

        $jsonFile = $jsonDir . $baseName . '.json';

        $extraccion = $this->extraerTextoPDF($pdfPath);
        $texto = $extraccion['texto'];

        if (trim($texto) === '') {
            throw new Exception("No se pudo extraer texto util del PDF.");
        }

        $cleaner = new TextCleaner();
        $textoLimpio = $cleaner->limpiar($texto);

        $extractor = new AnecaExtractor();
        $datos = $extractor->extraer($textoLimpio);

        if (!isset($datos['metadatos_extraccion']) || !is_array($datos['metadatos_extraccion'])) {
            $datos['metadatos_extraccion'] = [];
        }

        $datos['metadatos_extraccion']['modo_extraccion_texto'] = $extraccion['modo'];
        $datos['metadatos_extraccion']['fallback_ocr_activado'] = $extraccion['fallback_ocr_activado'];
        $datos['metadatos_extraccion']['detalle_extraccion_texto'] = $extraccion['detalle'];
        $datos['metadatos_extraccion']['ocr_disponible'] = $extraccion['ocr_disponible'];

        $datos['archivo_pdf'] = basename($pdfPath);
        $datos['json_generado'] = basename($jsonFile);
        $datos['texto_extraido'] = $textoLimpio;

        $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception("Error al convertir los datos a JSON.");
        }

        if (file_put_contents($jsonFile, $json) === false) {
            throw new Exception("No se pudo guardar el archivo JSON.");
        }

        return $datos;
    }

    private function extraerTextoPDF(string $pdfPath): array
    {
        $textoNativo = '';
        $detalleNativo = 'pdftotext_no_ejecutado';

        if ($this->pdftotextPath !== null) {
            try {
                $textoNativo = $this->extraerTextoConPdftotext($pdfPath);
                $detalleNativo = 'pdftotext_ok';
            } catch (Throwable $e) {
                $detalleNativo = 'pdftotext_error: ' . $e->getMessage();
                $textoNativo = '';
            }
        } else {
            $detalleNativo = 'pdftotext_no_disponible';
        }

        $textoNativo = $this->normalizarTexto($textoNativo);
        $nativoSuficiente = $this->esTextoNativoSuficiente($textoNativo);
        $ocrDisponible = $this->ocrProcessor->estaDisponible();

        if ($nativoSuficiente) {
            return [
                'texto' => $textoNativo,
                'modo' => 'pdftotext',
                'fallback_ocr_activado' => false,
                'detalle' => 'texto_nativo_suficiente',
                'ocr_disponible' => $ocrDisponible,
            ];
        }

        if (!$ocrDisponible) {
            if (trim($textoNativo) !== '') {
                return [
                    'texto' => $textoNativo,
                    'modo' => 'pdftotext_degradado',
                    'fallback_ocr_activado' => false,
                    'detalle' => $detalleNativo . ';ocr_no_disponible',
                    'ocr_disponible' => false,
                ];
            }

            throw new Exception(
                "No se pudo extraer texto con pdftotext y OCR no esta disponible. " .
                "Detalle: " . $detalleNativo
            );
        }

        try {
            $textoOcr = $this->normalizarTexto($this->ocrProcessor->extraerTextoDesdePdf($pdfPath));
        } catch (Throwable $e) {
            if (trim($textoNativo) !== '') {
                return [
                    'texto' => $textoNativo,
                    'modo' => 'pdftotext_degradado',
                    'fallback_ocr_activado' => false,
                    'detalle' => $detalleNativo . ';ocr_error:' . $e->getMessage(),
                    'ocr_disponible' => true,
                ];
            }

            throw new Exception(
                "Fallo OCR en fallback y no hay texto nativo util. " . $e->getMessage()
            );
        }

        if (trim($textoOcr) === '') {
            if (trim($textoNativo) !== '') {
                return [
                    'texto' => $textoNativo,
                    'modo' => 'pdftotext_degradado',
                    'fallback_ocr_activado' => false,
                    'detalle' => $detalleNativo . ';ocr_vacio',
                    'ocr_disponible' => true,
                ];
            }

            throw new Exception("OCR no devolvio texto util.");
        }

        if (trim($textoNativo) === '') {
            return [
                'texto' => $textoOcr,
                'modo' => 'ocr',
                'fallback_ocr_activado' => true,
                'detalle' => $detalleNativo . ';fallback_ocr_por_texto_vacio',
                'ocr_disponible' => true,
            ];
        }

        return [
            'texto' => $this->combinarTextoNativoYOcr($textoNativo, $textoOcr),
            'modo' => 'hibrido',
            'fallback_ocr_activado' => true,
            'detalle' => $detalleNativo . ';fallback_ocr_por_texto_insuficiente',
            'ocr_disponible' => true,
        ];
    }

    private function extraerTextoConPdftotext(string $pdfPath): string
    {
        $cmd = $this->buildExecutableCommand((string)$this->pdftotextPath)
            . ' '
            . escapeshellarg($pdfPath)
            . ' - 2>&1';

        $output = [];
        $status = 0;
        exec($cmd, $output, $status);

        if ($status !== 0) {
            throw new Exception(
                "Error al extraer texto con pdftotext.\nComando: " . $cmd . "\nSalida: " . implode("\n", $output)
            );
        }

        return implode("\n", $output);
    }

    private function esTextoNativoSuficiente(string $texto): bool
    {
        $textoSinEspacios = preg_replace('/\s+/u', '', trim($texto)) ?? '';
        if ($textoSinEspacios === '') {
            return false;
        }

        $total = mb_strlen($textoSinEspacios);
        if ($total < $this->nativeMinChars) {
            return false;
        }

        $alnum = preg_match_all('/[\p{L}\p{N}]/u', $textoSinEspacios);
        if ($alnum === false) {
            return false;
        }

        $ratio = $alnum / max(1, $total);
        return $ratio >= $this->nativeMinAlnumRatio;
    }

    private function combinarTextoNativoYOcr(string $textoNativo, string $textoOcr): string
    {
        $resultado = [];
        $vistos = [];

        foreach ([$textoNativo, $textoOcr] as $bloque) {
            $lineas = preg_split('/\R/u', $bloque) ?: [];
            foreach ($lineas as $linea) {
                $linea = trim($linea);
                if ($linea === '') {
                    continue;
                }

                $key = mb_strtolower(preg_replace('/\s+/u', ' ', $linea) ?? $linea, 'UTF-8');
                if (isset($vistos[$key])) {
                    continue;
                }

                $vistos[$key] = true;
                $resultado[] = $linea;
            }
        }

        return implode("\n", $resultado);
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;
        return trim($texto);
    }

    private function resolveExecutablePath(string $envVar, array $candidates, string $commandName): ?string
    {
        $envPath = getenv($envVar);
        if (is_string($envPath) && trim($envPath) !== '' && is_file(trim($envPath))) {
            return trim($envPath);
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        if ($this->existsInPath($commandName)) {
            return $commandName;
        }

        return null;
    }

    private function existsInPath(string $commandName): bool
    {
        $searchCmd = stripos(PHP_OS_FAMILY, 'Windows') === 0
            ? 'where ' . escapeshellarg($commandName) . ' 2>NUL'
            : 'command -v ' . escapeshellarg($commandName) . ' 2>/dev/null';

        $output = [];
        $status = 0;
        exec($searchCmd, $output, $status);

        return $status === 0 && !empty($output);
    }

    private function buildExecutableCommand(string $pathOrCommand): string
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/', $pathOrCommand) === 1) {
            return $pathOrCommand;
        }

        return '"' . str_replace('"', '\\"', $pathOrCommand) . '"';
    }

    private function readEnvInt(string $envVar, int $default, int $min, int $max): int
    {
        $raw = getenv($envVar);
        if (!is_string($raw) || trim($raw) === '') {
            return $default;
        }

        $value = (int)trim($raw);
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private function readEnvFloat(string $envVar, float $default, float $min, float $max): float
    {
        $raw = getenv($envVar);
        if (!is_string($raw) || trim($raw) === '' || !is_numeric(trim($raw))) {
            return $default;
        }

        $value = (float)trim($raw);
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
