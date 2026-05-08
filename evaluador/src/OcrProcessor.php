<?php

class OcrProcessor
{
    private ?string $pdftoppmPath;
    private ?string $tesseractPath;
    private ?string $tessdataPrefix;
    private int $maxOcrPages;
    private int $ocrDpi;
    private string $ocrLanguage;
    private string $cacheDir;

    public function __construct()
    {
        $repoRoot = dirname(__DIR__, 2);

        $this->pdftoppmPath = $this->resolveExecutablePath(
            'PDFTOPPM_PATH',
            [
                $repoRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . '.tools' . DIRECTORY_SEPARATOR . 'poppler' . DIRECTORY_SEPARATOR . 'poppler-25.07.0' . DIRECTORY_SEPARATOR . 'Library' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'pdftoppm.exe',
                'C:\\poppler-25.12.0\\Library\\bin\\pdftoppm.exe',
                'C:\\poppler\\Library\\bin\\pdftoppm.exe',
                'C:\\poppler\\bin\\pdftoppm.exe',
            ],
            'pdftoppm'
        );

        $this->tesseractPath = $this->resolveExecutablePath(
            'TESSERACT_PATH',
            [
                $repoRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . '.tools' . DIRECTORY_SEPARATOR . 'tesseract' . DIRECTORY_SEPARATOR . 'tesseract.exe',
                'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            ],
            'tesseract'
        );

        $this->tessdataPrefix = $this->resolveDirectoryPath(
            'TESSDATA_PREFIX',
            [
                $repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . '.tools' . DIRECTORY_SEPARATOR . 'tessdata',
                $repoRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'tessdata',
                'C:\\Program Files\\Tesseract-OCR\\tessdata',
                'C:\\Program Files (x86)\\Tesseract-OCR\\tessdata',
            ]
        );

        $this->maxOcrPages = $this->readEnvInt('OCR_MAX_PAGES', 25, 1, 2000);
        $this->ocrDpi = $this->readEnvInt('OCR_DPI', 140, 72, 600);

        $lang = getenv('OCR_LANG');
        $this->ocrLanguage = (is_string($lang) && trim($lang) !== '') ? trim($lang) : 'spa';

        $cacheFromEnv = getenv('OCR_CACHE_DIR');
        $this->cacheDir = (is_string($cacheFromEnv) && trim($cacheFromEnv) !== '')
            ? trim($cacheFromEnv)
            : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aneca_ocr_cache';
    }

    public function estaDisponible(): bool
    {
        return $this->pdftoppmPath !== null && $this->tesseractPath !== null;
    }

    public function diagnosticoDisponibilidad(): array
    {
        return [
            'pdftoppm_disponible' => $this->pdftoppmPath !== null,
            'tesseract_disponible' => $this->tesseractPath !== null,
            'pdftoppm_path' => $this->pdftoppmPath,
            'tesseract_path' => $this->tesseractPath,
            'tessdata_prefix' => $this->tessdataPrefix,
            'ocr_max_pages' => $this->maxOcrPages,
            'ocr_dpi' => $this->ocrDpi,
            'ocr_language' => $this->ocrLanguage,
            'ocr_cache_dir' => $this->cacheDir,
        ];
    }

    public function extraerTextoDesdePdf(string $pdfPath): string
    {
        if (!is_file($pdfPath)) {
            throw new Exception("PDF no encontrado para OCR: " . $pdfPath);
        }

        if (!$this->estaDisponible()) {
            $d = $this->diagnosticoDisponibilidad();
            throw new Exception(
                "OCR no disponible. " .
                "pdftoppm_disponible=" . ($d['pdftoppm_disponible'] ? 'true' : 'false') . ", " .
                "tesseract_disponible=" . ($d['tesseract_disponible'] ? 'true' : 'false')
            );
        }

        if ($this->tessdataPrefix !== null) {
            putenv('TESSDATA_PREFIX=' . $this->tessdataPrefix);
        }

        $cacheFile = $this->getCacheFilePath($pdfPath);
        $cachedText = $this->readCache($cacheFile);
        if ($cachedText !== null) {
            return $cachedText;
        }

        $tempDir = $this->createTempDir();

        try {
            $prefix = $tempDir . DIRECTORY_SEPARATOR . 'ocr_page';
            $images = $this->convertirPdfAImagenes($pdfPath, $prefix);

            if ($images === []) {
                throw new Exception("No se generaron imágenes para OCR.");
            }

            $textoCompleto = '';

            foreach ($images as $img) {
                $fragmento = $this->ocrImagen($img);

                if (trim($fragmento) !== '') {
                    $textoCompleto .= $fragmento . "\n\n";
                }
            }

            $textoCompleto = $this->normalizarTexto($textoCompleto);

            if ($textoCompleto === '') {
                throw new Exception("El OCR no devolvió texto útil.");
            }

            $this->writeCache($cacheFile, $textoCompleto);

            return $textoCompleto;
        } finally {
            $this->deleteDirectoryRecursive($tempDir);
        }
    }

    private function convertirPdfAImagenes(string $pdfPath, string $prefix): array
    {
        $paginaInicial = 1;
        $paginaFinal = $this->maxOcrPages;

        $cmd = $this->buildExecutableCommand((string)$this->pdftoppmPath)
            . ' -f ' . (int)$paginaInicial
            . ' -l ' . (int)$paginaFinal
            . ' -r ' . (int)$this->ocrDpi
            . ' -gray -jpeg'
            . ' -jpegopt quality=60,progressive=n,optimize=n'
            . ' '
            . escapeshellarg($pdfPath)
            . ' '
            . escapeshellarg($prefix)
            . ' 2>&1';

        $output = [];
        $status = 0;
        exec($cmd, $output, $status);

        if ($status !== 0) {
            throw new Exception(
                "Error al ejecutar pdftoppm para OCR.\nComando: " . $cmd . "\nSalida: " . implode("\n", $output)
            );
        }

        $images = glob($prefix . '-*.jpg') ?: [];
        natsort($images);

        return array_values($images);
    }

    private function ocrImagen(string $imgPath): string
    {
        $cmd = $this->buildExecutableCommand((string)$this->tesseractPath)
            . ' '
            . escapeshellarg($imgPath)
            . ' stdout -l ' . escapeshellarg($this->ocrLanguage)
            . ' --oem 1 --psm 6 quiet 2>&1';

        $output = [];
        $status = 0;
        exec($cmd, $output, $status);

        if ($status !== 0) {
            throw new Exception(
                "Error OCR en: " . basename($imgPath) .
                "\nComando: " . $cmd .
                "\nSalida: " . implode("\n", $output)
            );
        }

        return implode("\n", $output);
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

    private function resolveDirectoryPath(string $envVar, array $candidates): ?string
    {
        $envPath = getenv($envVar);
        if (is_string($envPath) && trim($envPath) !== '' && is_dir(trim($envPath))) {
            return trim($envPath);
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_dir($candidate)) {
                return $candidate;
            }
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

    private function createTempDir(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aneca_ocr_' . uniqid('', true);

        if (!mkdir($path, 0700, true) && !is_dir($path)) {
            throw new Exception("No se pudo crear directorio temporal para OCR.");
        }

        return $path;
    }

    private function deleteDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
                continue;
            }

            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function normalizarTexto(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        @mkdir($dir, 0777, true);
    }

    private function getCacheFilePath(string $pdfPath): string
    {
        $this->ensureDirectoryExists($this->cacheDir);

        $realPath = realpath($pdfPath);
        $base = ($realPath !== false ? $realPath : $pdfPath)
            . '|'
            . (string)@filesize($pdfPath)
            . '|'
            . (string)@filemtime($pdfPath)
            . '|'
            . $this->ocrDpi
            . '|'
            . $this->maxOcrPages
            . '|'
            . $this->ocrLanguage;

        $hash = sha1($base);

        return $this->cacheDir . DIRECTORY_SEPARATOR . $hash . '.txt';
    }

    private function readCache(string $cacheFile): ?string
    {
        if (!is_file($cacheFile)) {
            return null;
        }

        $content = @file_get_contents($cacheFile);
        if (!is_string($content)) {
            return null;
        }

        $content = $this->normalizarTexto($content);

        return $content !== '' ? $content : null;
    }

    private function writeCache(string $cacheFile, string $content): void
    {
        $this->ensureDirectoryExists(dirname($cacheFile));
        @file_put_contents($cacheFile, $content);
    }
}