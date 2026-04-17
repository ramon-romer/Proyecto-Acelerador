<?php

require_once __DIR__ . '/../src/ProcessingJobWorker.php';
require_once __DIR__ . '/../src/ProcessingJobQueue.php';

$options = parseArgs($argv);

$queue = new ProcessingJobQueue();
$worker = new ProcessingJobWorker($queue);

$result = [
    'ok' => true,
    'mode' => $options['loop'] ? 'loop' : 'once',
    'job_id' => $options['job_id'],
    'processed_jobs' => [],
    'processed_count' => 0,
];

try {
    if ($options['job_id'] !== null) {
        $processed = $worker->processJobById($options['job_id']);
        $result['processed_jobs'][] = summarizeJob($processed);
        $result['processed_count'] = 1;
        outputJson($result);
        exit(0);
    }

    if (!$options['loop']) {
        $processed = $worker->processNextPendingJob();

        if ($processed !== null) {
            $result['processed_jobs'][] = summarizeJob($processed);
            $result['processed_count'] = 1;
        }

        outputJson($result);
        exit(0);
    }

    $maxJobs = $options['max_jobs'];
    $sleepSeconds = $options['sleep_seconds'];

    while (true) {
        $processed = $worker->processNextPendingJob();

        if ($processed !== null) {
            $result['processed_jobs'][] = summarizeJob($processed);
            $result['processed_count']++;
        } else {
            if ($maxJobs !== null && $result['processed_count'] >= $maxJobs) {
                break;
            }

            sleep($sleepSeconds);
        }

        if ($maxJobs !== null && $result['processed_count'] >= $maxJobs) {
            break;
        }
    }

    outputJson($result);
    exit(0);
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['message'] = $e->getMessage();
    outputJson($result);
    exit(1);
}

function parseArgs(array $argv): array
{
    $out = [
        'loop' => false,
        'sleep_seconds' => 5,
        'max_jobs' => 1,
        'job_id' => null,
    ];

    foreach ($argv as $arg) {
        if ($arg === '--loop') {
            $out['loop'] = true;
            $out['max_jobs'] = null;
            continue;
        }

        if ($arg === '--once') {
            $out['loop'] = false;
            continue;
        }

        if (strpos($arg, '--sleep=') === 0) {
            $value = (int)substr($arg, 8);
            $out['sleep_seconds'] = max(1, min(300, $value));
            continue;
        }

        if (strpos($arg, '--max-jobs=') === 0) {
            $value = (int)substr($arg, 11);
            $out['max_jobs'] = max(1, min(10000, $value));
            continue;
        }

        if (strpos($arg, '--job-id=') === 0) {
            $value = trim(substr($arg, 9));
            if ($value !== '') {
                $out['job_id'] = $value;
            }
            continue;
        }
    }

    return $out;
}

function summarizeJob(array $job): array
{
    return [
        'id' => $job['id'] ?? null,
        'estado' => $job['estado'] ?? null,
        'fase_actual' => $job['fase_actual'] ?? null,
        'progreso_porcentaje' => $job['progreso_porcentaje'] ?? null,
        'error_mensaje' => $job['error_mensaje'] ?? null,
        'tiempo_total_ms' => $job['tiempo_total_ms'] ?? null,
    ];
}

function outputJson(array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"ok":false,"message":"Error serializando salida."}';
    }

    echo $json . PHP_EOL;
}
