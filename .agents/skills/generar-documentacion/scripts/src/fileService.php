<?php
declare(strict_types=1);

namespace GenerarDocumentacion\FileService;

use RuntimeException;

function resolveOutputDir(?string $outputDir, string $repoRoot): string
{
    $candidate = trim((string) $outputDir);
    if ($candidate === '') {
        $candidate = 'docs';
    }

    $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);

    if (!isAbsolutePath($candidate)) {
        $candidate = rtrim($repoRoot, '\\/') . DIRECTORY_SEPARATOR . $candidate;
    }

    if (!is_dir($candidate) && !mkdir($candidate, 0777, true) && !is_dir($candidate)) {
        throw new RuntimeException(sprintf('No fue posible crear el directorio de salida: %s', $candidate));
    }

    $resolved = realpath($candidate);
    if ($resolved === false) {
        throw new RuntimeException(sprintf('No fue posible resolver el directorio de salida: %s', $candidate));
    }

    return rtrim($resolved, '\\/');
}

function buildDailyFilePath(string $outputDir, string $prefix, string $date): string
{
    return rtrim($outputDir, '\\/') . DIRECTORY_SEPARATOR . $prefix . '-' . $date . '.md';
}

function readIfExists(string $path): ?string
{
    if (!file_exists($path)) {
        return null;
    }

    if (!is_file($path)) {
        throw new RuntimeException(sprintf('La ruta existe pero no es un archivo: %s', $path));
    }

    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException(sprintf('No fue posible leer el archivo existente: %s', $path));
    }

    return $content;
}

function writeFile(string $path, string $content): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException(sprintf('No fue posible crear el directorio del archivo: %s', $directory));
    }

    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException(sprintf('No fue posible escribir el archivo: %s', $path));
    }
}

function isAbsolutePath(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
        return true;
    }

    if (str_starts_with($path, '\\\\')) {
        return true;
    }

    return str_starts_with($path, '/');
}

