<?php
declare(strict_types=1);

require __DIR__ . '/extract_pdf.php';

$mode = $argv[1] ?? '--once';
$sleepSeconds = isset($argv[2]) ? max(1, (int)$argv[2]) : 2;
$jobsDir = leerJobsDir();

if ($mode === '--once') {
    $processed = procesarPendientes($jobsDir, 1);
    fwrite(STDOUT, "Procesados: {$processed}" . PHP_EOL);
    exit(0);
}

if ($mode === '--loop') {
    fwrite(STDOUT, "Worker en loop. jobs_dir={$jobsDir} sleep={$sleepSeconds}s" . PHP_EOL);
    while (true) {
        procesarPendientes($jobsDir, 10);
        sleep($sleepSeconds);
    }
}

fwrite(STDERR, "Uso: php mcp-server/worker_jobs.php [--once|--loop] [sleep_seconds]" . PHP_EOL);
exit(1);

function procesarPendientes(string $jobsDir, int $maxJobs): int
{
    if (!is_dir($jobsDir)) {
        return 0;
    }

    $items = scandir($jobsDir);
    if (!is_array($items)) {
        return 0;
    }

    $processed = 0;
    foreach ($items as $item) {
        if ($processed >= $maxJobs) {
            break;
        }
        if ($item === '.' || $item === '..') {
            continue;
        }

        $jobPath = $jobsDir . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($jobPath)) {
            continue;
        }

        $metaPath = $jobPath . DIRECTORY_SEPARATOR . 'meta.json';
        if (!is_file($metaPath)) {
            continue;
        }

        $meta = leerJson($metaPath);
        if (!is_array($meta)) {
            continue;
        }
        if (($meta['status'] ?? '') !== 'pending') {
            continue;
        }

        $meta['status'] = 'processing';
        $meta['updated_at'] = date(DATE_ATOM);
        guardarJson($metaPath, $meta);

        try {
            $inputPath = $jobPath . DIRECTORY_SEPARATOR . 'input.pdf';
            if (!is_file($inputPath)) {
                throw new RuntimeException('No existe input.pdf');
            }

            $processor = new PdfProcessor();
            $result = $processor->procesarPdf($inputPath);
            guardarJson($jobPath . DIRECTORY_SEPARATOR . 'result.json', $result);

            $meta['status'] = 'done';
            $meta['updated_at'] = date(DATE_ATOM);
            guardarJson($metaPath, $meta);
        } catch (Throwable $e) {
            guardarJson($jobPath . DIRECTORY_SEPARATOR . 'error.json', [
                'message' => $e->getMessage(),
                'at' => date(DATE_ATOM),
            ]);
            $meta['status'] = 'error';
            $meta['updated_at'] = date(DATE_ATOM);
            guardarJson($metaPath, $meta);
        }

        $processed++;
    }

    return $processed;
}

function leerJobsDir(): string
{
    $raw = getenv('MCP_JOBS_DIR');
    if (is_string($raw) && trim($raw) !== '') {
        return trim($raw);
    }
    return __DIR__ . DIRECTORY_SEPARATOR . 'resultados' . DIRECTORY_SEPARATOR . 'jobs';
}

function guardarJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar JSON para ' . $path);
    }
    if (file_put_contents($path, $json) === false) {
        throw new RuntimeException('No se pudo escribir ' . $path);
    }
}

function leerJson(string $path)
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    return json_decode($raw, true);
}
