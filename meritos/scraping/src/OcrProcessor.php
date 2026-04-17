<?php

class OcrProcessor
{
    private $tesseractPath;
    private $idioma;

    public function __construct(?string $tesseractPath = null, string $idioma = 'spa')
    {
        $this->tesseractPath = $tesseractPath ?? $this->resolveExecutablePath(
            'TESSERACT_PATH',
            [
                'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            ],
            'tesseract'
        );
        $this->idioma = preg_match('/^[A-Za-z0-9_+.-]+$/', $idioma) === 1 ? $idioma : 'spa';
    }

    public function estaDisponible(): bool
    {
        return $this->tesseractPath !== null;
    }

    public function procesarImagen(string $imagenPath, bool $estricto = false): array
    {
        $inicio = microtime(true);
        $resultado = [
            'texto' => '',
            'tiempo_ms' => 0.0,
            'status' => -1,
            'error' => null,
            'salida_comando' => [],
        ];

        try {
            if ($this->tesseractPath === null) {
                throw new Exception('Tesseract no esta disponible.');
            }

            if (!is_file($imagenPath)) {
                throw new Exception('No existe la imagen para OCR: ' . $imagenPath);
            }

            $basePath = pathinfo($imagenPath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . pathinfo($imagenPath, PATHINFO_FILENAME);
            $txtFile = $basePath . '.txt';

            if (is_file($txtFile)) {
                @unlink($txtFile);
            }

            $cmd = $this->buildExecutableCommand((string)$this->tesseractPath)
                . ' '
                . escapeshellarg($imagenPath)
                . ' '
                . escapeshellarg($basePath)
                . ' -l ' . $this->idioma
                . ' 2>&1';

            $output = [];
            $status = 0;
            exec($cmd, $output, $status);

            $resultado['status'] = $status;
            $resultado['salida_comando'] = $output;

            if ($status !== 0) {
                throw new Exception(
                    'Error OCR en ' . basename($imagenPath)
                    . '. Comando: ' . $cmd
                    . '. Salida: ' . implode("\n", $output)
                );
            }

            if (!is_file($txtFile)) {
                throw new Exception('Tesseract no genero TXT para ' . basename($imagenPath));
            }

            $contenido = file_get_contents($txtFile);
            if ($contenido === false) {
                throw new Exception('No se pudo leer TXT OCR para ' . basename($imagenPath));
            }

            $resultado['texto'] = $contenido;
        } catch (Throwable $e) {
            $resultado['error'] = $e->getMessage();
            if ($estricto) {
                throw new Exception($resultado['error']);
            }
        } finally {
            $resultado['tiempo_ms'] = $this->elapsedMs($inicio);
        }

        return $resultado;
    }

    public function procesarImagenesDetallado(array $imagenes, bool $estricto = false): array
    {
        $textoCompleto = '';
        $detalles = [];

        foreach ($imagenes as $index => $img) {
            $detalle = $this->procesarImagen((string)$img, $estricto);
            $detalle['indice'] = $index;
            $detalle['imagen'] = (string)$img;
            $detalles[] = $detalle;

            if (($detalle['error'] ?? null) === null) {
                $textoCompleto .= (string)$detalle['texto'] . "\n\n";
            }
        }

        return [
            'texto' => $textoCompleto,
            'paginas' => $detalles,
        ];
    }

    public function procesarImagenes(array $imagenes): string
    {
        $resultado = $this->procesarImagenesDetallado($imagenes, true);
        return (string)$resultado['texto'];
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

    private function elapsedMs(float $inicio): float
    {
        return round((microtime(true) - $inicio) * 1000, 2);
    }
}
