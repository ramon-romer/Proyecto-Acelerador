<?php

class PdfToImage
{
    private $pdftoppmPath;
    private $dpi;

    public function __construct(?string $pdftoppmPath = null, int $dpi = 300)
    {
        $this->pdftoppmPath = $pdftoppmPath ?? $this->resolveExecutablePath(
            'PDFTOPPM_PATH',
            [
                'C:\\poppler\\Library\\bin\\pdftoppm.exe',
                'C:\\poppler\\bin\\pdftoppm.exe',
            ],
            'pdftoppm'
        );
        $this->dpi = $this->limitInt($dpi, 72, 600);
    }

    public function estaDisponible(): bool
    {
        return $this->pdftoppmPath !== null;
    }

    public function convertir(string $pdfPath, string $outputPrefix): array
    {
        $this->assertDisponible();

        $cmd = $this->buildExecutableCommand((string)$this->pdftoppmPath)
            . ' -r ' . $this->dpi
            . ' -png '
            . escapeshellarg($pdfPath)
            . ' '
            . escapeshellarg($outputPrefix)
            . ' 2>&1';

        $output = [];
        $status = 0;
        exec($cmd, $output, $status);

        if ($status !== 0) {
            throw new Exception(
                "Error al ejecutar pdftoppm para convertir PDF completo."
                . "\nComando: " . $cmd
                . "\nSalida: " . implode("\n", $output)
            );
        }

        $imagenes = glob($outputPrefix . "-*.png") ?: [];
        natsort($imagenes);
        $imagenes = array_values($imagenes);

        return $imagenes;
    }

    public function convertirPaginas(string $pdfPath, string $outputPrefix, array $paginas): array
    {
        $this->assertDisponible();

        $paginas = $this->normalizarPaginas($paginas);
        $imagenesPorPagina = [];

        foreach ($paginas as $pagina) {
            $prefixPagina = $outputPrefix . '-p' . str_pad((string)$pagina, 4, '0', STR_PAD_LEFT);

            $cmd = $this->buildExecutableCommand((string)$this->pdftoppmPath)
                . ' -r ' . $this->dpi
                . ' -f ' . $pagina
                . ' -l ' . $pagina
                . ' -singlefile -png '
                . escapeshellarg($pdfPath)
                . ' '
                . escapeshellarg($prefixPagina)
                . ' 2>&1';

            $output = [];
            $status = 0;
            exec($cmd, $output, $status);

            if ($status !== 0) {
                throw new Exception(
                    "Error al convertir la pagina " . $pagina . " a imagen."
                    . "\nComando: " . $cmd
                    . "\nSalida: " . implode("\n", $output)
                );
            }

            $imagenPath = $prefixPagina . '.png';
            if (!is_file($imagenPath)) {
                throw new Exception(
                    "pdftoppm no genero imagen para la pagina " . $pagina . "."
                );
            }

            $imagenesPorPagina[$pagina] = $imagenPath;
        }

        ksort($imagenesPorPagina, SORT_NUMERIC);

        return $imagenesPorPagina;
    }

    private function assertDisponible(): void
    {
        if ($this->pdftoppmPath === null) {
            throw new Exception("pdftoppm no esta disponible para convertir PDF a imagen.");
        }
    }

    private function normalizarPaginas(array $paginas): array
    {
        $normalizadas = [];

        foreach ($paginas as $pagina) {
            $pagina = (int)$pagina;
            if ($pagina < 1) {
                continue;
            }
            $normalizadas[$pagina] = $pagina;
        }

        ksort($normalizadas, SORT_NUMERIC);

        return array_values($normalizadas);
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

    private function limitInt(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
