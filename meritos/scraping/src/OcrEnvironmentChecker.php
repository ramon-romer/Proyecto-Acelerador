<?php

class OcrEnvironmentChecker
{
    private $warnings = [];
    private $misconfigured = false;

    public function check(): array
    {
        $this->warnings = [];
        $this->misconfigured = false;

        $pdftoppm = $this->checkTool(
            'pdftoppm',
            'PDFTOPPM_PATH',
            '-v'
        );

        $tesseract = $this->checkTool(
            'tesseract',
            'TESSERACT_PATH',
            '--version'
        );

        $ocrReady = (bool)$pdftoppm['available'] && (bool)$tesseract['available'];
        $mode = $this->resolveMode(
            $ocrReady,
            (bool)$pdftoppm['available'],
            (bool)$tesseract['available']
        );

        return [
            'pdf_tools' => [
                'pdftoppm' => $pdftoppm,
            ],
            'ocr_tools' => [
                'tesseract' => $tesseract,
            ],
            'ocr_ready' => $ocrReady,
            'mode' => $mode,
            'warnings' => array_values(array_unique($this->warnings)),
        ];
    }

    private function checkTool(string $toolName, string $envVar, string $versionArg): array
    {
        $path = null;
        $available = false;
        $version = null;

        $envRaw = getenv($envVar);
        $envValue = is_string($envRaw) ? trim($envRaw) : '';

        if ($envValue !== '') {
            $resolvedFromEnv = $this->resolveFromEnvironmentValue($envValue);

            if ($resolvedFromEnv === null) {
                $this->misconfigured = true;
                $this->warnings[] = $envVar . ' apunta a una ruta/comando no valido: ' . $envValue;
            } else {
                $path = $resolvedFromEnv;
                $available = true;
            }
        }

        if (!$available) {
            $pathFromPath = $this->findInPath($toolName);
            if ($pathFromPath !== null) {
                $path = $pathFromPath;
                $available = true;
            }
        }

        if ($available) {
            $version = $this->detectVersion((string)$path, $versionArg);
            if ($version === null) {
                $this->warnings[] = 'No se pudo detectar version de ' . $toolName . '.';
            }
        } else {
            $this->warnings[] = 'No se detecto ' . $toolName . ' en PATH ni via ' . $envVar . '.';
        }

        return [
            'available' => $available,
            'path' => $path,
            'version' => $version,
        ];
    }

    private function resolveFromEnvironmentValue(string $value): ?string
    {
        if ($this->isExecutablePath($value)) {
            return $value;
        }

        if ($this->looksLikeCommand($value)) {
            $fromPath = $this->findInPath($value);
            if ($fromPath !== null) {
                return $fromPath;
            }
        }

        return null;
    }

    private function resolveMode(bool $ocrReady, bool $pdftoppmAvailable, bool $tesseractAvailable): string
    {
        if ($this->misconfigured) {
            return 'misconfigured';
        }

        if ($ocrReady) {
            return 'hybrid_ready';
        }

        if (!$pdftoppmAvailable && !$tesseractAvailable) {
            return 'ocr_unavailable';
        }

        return 'native_only';
    }

    private function isExecutablePath(string $path): bool
    {
        if ($path === '' || !is_file($path)) {
            return false;
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
            return true;
        }

        return is_executable($path);
    }

    private function looksLikeCommand(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9._-]+$/', $value) === 1;
    }

    private function findInPath(string $command): ?string
    {
        $lookupCmd = stripos(PHP_OS_FAMILY, 'Windows') === 0
            ? 'where ' . escapeshellarg($command) . ' 2>NUL'
            : 'which ' . escapeshellarg($command) . ' 2>/dev/null';

        $output = [];
        $status = 0;
        exec($lookupCmd, $output, $status);

        if ($status !== 0 || empty($output)) {
            return null;
        }

        foreach ($output as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }

            if ($this->isExecutablePath($line) || $this->looksLikeCommand($line)) {
                return $line;
            }
        }

        return null;
    }

    private function detectVersion(string $pathOrCommand, string $versionArg): ?string
    {
        $cmd = $this->buildExecutableCommand($pathOrCommand) . ' ' . $versionArg . ' 2>&1';

        $output = [];
        $status = 0;
        exec($cmd, $output, $status);

        if (empty($output)) {
            return null;
        }

        foreach ($output as $line) {
            $line = trim((string)$line);
            if ($line !== '') {
                return $line;
            }
        }

        return $status === 0 ? '' : null;
    }

    private function buildExecutableCommand(string $pathOrCommand): string
    {
        if ($this->looksLikeCommand($pathOrCommand)) {
            return $pathOrCommand;
        }

        return '"' . str_replace('"', '\\"', $pathOrCommand) . '"';
    }
}

