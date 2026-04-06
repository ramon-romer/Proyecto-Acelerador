<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Acelerador\\PanelBackend\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

    if (is_file($fullPath)) {
        require $fullPath;
    }
});

