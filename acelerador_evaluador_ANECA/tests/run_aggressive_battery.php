<?php
declare(strict_types=1);

/**
 * Bateria agresiva para acelerador_evaluador_ANECA.
 *
 * Incluye:
 * - lint sintactico del modulo
 * - unit tests de src
 * - estres por areas (tecnicas, salud, humanidades, csyj, experimentales)
 */

/**
 * @return array<string,mixed>
 */
function parseArgs(array $argv): array
{
    $opts = [
        'duration-seconds' => 300,
        'progress-interval' => 10,
        'seed' => random_int(1, PHP_INT_MAX),
        'report-file' => __DIR__ . '/results/aggressive_battery_' . date('Ymd_His') . '.json',
    ];

    foreach ($argv as $arg) {
        if (!str_starts_with((string) $arg, '--')) {
            continue;
        }
        $parts = explode('=', substr((string) $arg, 2), 2);
        $key = $parts[0];
        $value = $parts[1] ?? '1';
        $opts[$key] = $value;
    }

    $opts['duration-seconds'] = max(15, (int) $opts['duration-seconds']);
    $opts['progress-interval'] = max(2, (int) $opts['progress-interval']);
    $opts['seed'] = (int) $opts['seed'];
    $opts['report-file'] = (string) $opts['report-file'];

    return $opts;
}

/**
 * @return array{exit_code:int,stdout:string,stderr:string}
 */
function runCommand(string $command, string $cwd): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar comando: ' . $command);
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'exit_code' => (int) $exitCode,
        'stdout' => trim($stdout === false ? '' : $stdout),
        'stderr' => trim($stderr === false ? '' : $stderr),
    ];
}

/**
 * @return array{checked:int,failed:int,errors:array<int,string>}
 */
function lintModule(string $moduleRoot): array
{
    $errors = [];
    $checked = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }

        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $checked++;
        $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
        $result = runCommand($cmd, $moduleRoot);
        if ($result['exit_code'] !== 0) {
            $firstLine = explode("\n", trim($result['stderr'] . "\n" . $result['stdout']))[0] ?? 'Error desconocido';
            $errors[] = $file->getPathname() . ' => ' . $firstLine;
        }
    }

    return [
        'checked' => $checked,
        'failed' => count($errors),
        'errors' => $errors,
    ];
}

/**
 * @return array{ok:bool,summary:array<string,mixed>,raw:string}
 */
function parseJsonStdoutResult(array $commandResult): array
{
    $raw = trim($commandResult['stdout']);
    if ($commandResult['exit_code'] !== 0) {
        return [
            'ok' => false,
            'summary' => [
                'exit_code' => $commandResult['exit_code'],
                'stderr' => $commandResult['stderr'],
                'stdout' => $commandResult['stdout'],
            ],
            'raw' => $raw,
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'summary' => [
                'exit_code' => $commandResult['exit_code'],
                'error' => 'Salida no JSON',
                'stdout' => $commandResult['stdout'],
            ],
            'raw' => $raw,
        ];
    }

    return [
        'ok' => true,
        'summary' => $decoded,
        'raw' => $raw,
    ];
}

function main(array $argv): int
{
    $opts = parseArgs($argv);
    mt_srand((int) $opts['seed']);

    $moduleRoot = dirname(__DIR__);
    $reportFile = (string) $opts['report-file'];
    $reportDir = dirname($reportFile);
    if (!is_dir($reportDir)) {
        mkdir($reportDir, 0777, true);
    }

    $stats = [
        'startedAt' => date(DATE_ATOM),
        'seed' => (int) $opts['seed'],
        'targetDurationSeconds' => (int) $opts['duration-seconds'],
        'lint' => [
            'checked' => 0,
            'failed' => 0,
            'errors' => [],
        ],
        'unitSrc' => [
            'ok' => false,
            'exit_code' => null,
            'stdout' => '',
            'stderr' => '',
        ],
        'areaStress' => [
            'runs' => 0,
            'ok' => 0,
            'failed' => 0,
            'byArea' => [
                'tecnicas' => ['ok' => 0, 'failed' => 0],
                'salud' => ['ok' => 0, 'failed' => 0],
                'humanidades' => ['ok' => 0, 'failed' => 0],
                'csyj' => ['ok' => 0, 'failed' => 0],
                'experimentales' => ['ok' => 0, 'failed' => 0],
            ],
            'failSamples' => [],
        ],
        'finalStatus' => 'FAIL',
    ];

    try {
        echo 'Running lint for acelerador_evaluador_ANECA...' . PHP_EOL;
        $lint = lintModule($moduleRoot);
        $stats['lint'] = $lint;
        if ($lint['failed'] > 0) {
            $stats['finalStatus'] = 'FAIL';
            $stats['endedAt'] = date(DATE_ATOM);
            file_put_contents($reportFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            echo 'Lint failed. report=' . $reportFile . PHP_EOL;
            return 1;
        }

        echo 'Running unit_src.php...' . PHP_EOL;
        $unitCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/unit_src.php');
        $unitResult = runCommand($unitCmd, $moduleRoot);
        $stats['unitSrc'] = [
            'ok' => $unitResult['exit_code'] === 0,
            'exit_code' => $unitResult['exit_code'],
            'stdout' => $unitResult['stdout'],
            'stderr' => $unitResult['stderr'],
        ];
        if ($unitResult['exit_code'] !== 0) {
            $stats['finalStatus'] = 'FAIL';
            $stats['endedAt'] = date(DATE_ATOM);
            file_put_contents($reportFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            echo 'unit_src failed. report=' . $reportFile . PHP_EOL;
            return 1;
        }

        $areas = ['tecnicas', 'salud', 'humanidades', 'csyj', 'experimentales'];
        $durationSeconds = (int) $opts['duration-seconds'];
        $progressInterval = (int) $opts['progress-interval'];
        $start = microtime(true);
        $deadline = $start + $durationSeconds;
        $nextProgress = $start + $progressInterval;
        $round = 0;

        echo 'Starting area stress loop...' . PHP_EOL;
        while (microtime(true) < $deadline) {
            $area = $areas[$round % count($areas)];
            $round++;
            $iterations = mt_rand(20, 80);
            $seed = mt_rand(1, PHP_INT_MAX);

            $cmd = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(__DIR__ . '/area_probe.php')
                . ' --area=' . escapeshellarg($area)
                . ' --iterations=' . $iterations
                . ' --seed=' . $seed;

            $run = runCommand($cmd, $moduleRoot);
            $parsed = parseJsonStdoutResult($run);
            $stats['areaStress']['runs']++;

            if ($parsed['ok'] === true) {
                $stats['areaStress']['ok']++;
                $stats['areaStress']['byArea'][$area]['ok']++;
            } else {
                $stats['areaStress']['failed']++;
                $stats['areaStress']['byArea'][$area]['failed']++;
                if (count($stats['areaStress']['failSamples']) < 25) {
                    $stats['areaStress']['failSamples'][] = [
                        'area' => $area,
                        'iterations' => $iterations,
                        'seed' => $seed,
                        'details' => $parsed['summary'],
                    ];
                }
            }

            $now = microtime(true);
            if ($now >= $nextProgress) {
                $elapsed = (int) floor($now - $start);
                $ops = (int) $stats['areaStress']['runs'];
                $opsPerSec = $elapsed > 0 ? round($ops / $elapsed, 2) : 0.0;
                echo '[progress] elapsed=' . $elapsed . 's runs=' . $ops . ' ok=' . $stats['areaStress']['ok'] . ' failed=' . $stats['areaStress']['failed'] . ' runs/s=' . $opsPerSec . PHP_EOL;
                $nextProgress = $now + $progressInterval;
            }
        }

        $failed = (int) $stats['areaStress']['failed'];
        $stats['actualDurationSeconds'] = round(microtime(true) - $start, 3);
        $stats['finalStatus'] = $failed === 0 ? 'PASS' : 'FAIL';
        $stats['endedAt'] = date(DATE_ATOM);

        file_put_contents($reportFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        echo 'Aggressive battery finished. status=' . $stats['finalStatus'] . ' report=' . $reportFile . PHP_EOL;
        return $stats['finalStatus'] === 'PASS' ? 0 : 1;
    } catch (Throwable $e) {
        $stats['endedAt'] = date(DATE_ATOM);
        $stats['fatalError'] = $e->getMessage();
        $stats['finalStatus'] = 'FAIL';
        file_put_contents($reportFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fwrite(STDERR, 'Fatal error: ' . $e->getMessage() . PHP_EOL);
        return 1;
    }
}

if (isset($argv[0]) && realpath((string) $argv[0]) === __FILE__) {
    exit(main($argv));
}

