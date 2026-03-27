<?php
declare(strict_types=1);

/**
 * Script de bateria de validacion reutilizable para la skill $ejecutar-tests.
 * Ejecuta checks reales y devuelve salida estructurada en JSON.
 */

/**
 * @return array{nivel: string, ventana: string, json: bool, dry_run: bool, help: bool}
 */
function parseArguments(array $argv): array
{
    $parsed = [
        'nivel' => 'standard',
        'ventana' => '15m',
        'json' => false,
        'dry_run' => false,
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $argument = $argv[$i];

        if ($argument === '--help' || $argument === '-h') {
            $parsed['help'] = true;
            continue;
        }

        if ($argument === '--json') {
            $parsed['json'] = true;
            continue;
        }

        if ($argument === '--dry-run') {
            $parsed['dry_run'] = true;
            continue;
        }

        if ($argument === '--nivel') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException('Falta valor para --nivel.');
            }
            $parsed['nivel'] = (string) $argv[++$i];
            continue;
        }

        if (str_starts_with($argument, '--nivel=')) {
            $parsed['nivel'] = substr($argument, strlen('--nivel='));
            continue;
        }

        if ($argument === '--ventana') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException('Falta valor para --ventana.');
            }
            $parsed['ventana'] = (string) $argv[++$i];
            continue;
        }

        if (str_starts_with($argument, '--ventana=')) {
            $parsed['ventana'] = substr($argument, strlen('--ventana='));
            continue;
        }

        throw new InvalidArgumentException(sprintf('Argumento no reconocido: %s', $argument));
    }

    return $parsed;
}

/**
 * @return array<int, array{id: string, name: string, command: string, optional: bool}>
 */
function buildChecks(string $repositoryRoot, string $nivel): array
{
    $checks = [
        [
            'id' => 'php-version',
            'name' => 'Version de PHP',
            'command' => escapeshellarg(PHP_BINARY) . ' -v',
            'optional' => false,
        ],
        [
            'id' => 'backend-smoke',
            'name' => 'Smoke backend tutorias',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_usecases_smoke.php'),
            'optional' => false,
        ],
        [
            'id' => 'mcp-unit',
            'name' => 'MCP unit extract_pdf',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'unit_extract_pdf.php'),
            'optional' => false,
        ],
    ];

    if ($nivel === 'medio' || $nivel === 'agresivo') {
        $checks[] = [
            'id' => 'inspect-schema',
            'name' => 'Inspect schema backend',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'inspect_schema.php'),
            'optional' => true,
        ];
        $checks[] = [
            'id' => 'contract-routes',
            'name' => 'Contrato de rutas criticas',
            'command' => 'rg -n -F "POST /api/tutorias" ' .
                escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . '02-api-rest-contratos.md') . ' ' .
                escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Presentation' . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'TutoriaRoutes.php'),
            'optional' => false,
        ];
    }

    if ($nivel === 'agresivo') {
        $reportPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'acelerador_aggressive_codex_' . date('Ymd_His') . '.json';
        $checks[] = [
            'id' => 'aggressive-battery',
            'name' => 'Bateria agresiva backend (60s)',
            'command' => escapeshellarg(PHP_BINARY) . ' ' .
                escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_aggressive_battery.php') .
                ' --duration-seconds=60 --progress-interval=5 --report-file=' . escapeshellarg($reportPath),
            'optional' => true,
        ];
        $checks[] = [
            'id' => 'mcp-worker-once',
            'name' => 'Worker MCP --once',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . 'worker_jobs.php') . ' --once',
            'optional' => true,
        ];
    }

    return $checks;
}

/**
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runCommand(string $command, string $workingDirectory): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $workingDirectory);
    if (!is_resource($process)) {
        throw new RuntimeException(sprintf('No se pudo iniciar el comando: %s', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'exit_code' => (int) $exitCode,
        'stdout' => $stdout === false ? '' : trim($stdout),
        'stderr' => $stderr === false ? '' : trim($stderr),
    ];
}

/**
 * @param array<int, array{id: string, name: string, command: string, optional: bool}> $checks
 * @return array{
 *   executed: bool,
 *   suiteName: string,
 *   total: int,
 *   passed: int,
 *   failed: int,
 *   errors: array<int, string>,
 *   summary: string,
 *   timestamp: string,
 *   observations: string,
 *   checks: array<int, array<string, mixed>>
 * }
 */
function executeChecks(array $checks, string $workingDirectory, string $suiteName, bool $dryRun): array
{
    $checkResults = [];
    $errors = [];
    $mandatoryTotal = 0;
    $passed = 0;
    $failed = 0;
    $noVerificable = 0;

    foreach ($checks as $check) {
        if ($dryRun) {
            $checkResults[] = [
                'id' => $check['id'],
                'name' => $check['name'],
                'command' => $check['command'],
                'optional' => $check['optional'],
                'status' => 'dry_run',
                'exitCode' => 0,
                'output' => 'Dry run habilitado. No se ejecutaron comandos reales.',
            ];
            continue;
        }

        $execution = runCommand($check['command'], $workingDirectory);
        $success = $execution['exit_code'] === 0;

        $status = 'passed';
        if (!$success && $check['optional']) {
            $status = 'no_verificable';
            $noVerificable++;
        } elseif (!$success) {
            $status = 'failed';
        }

        if (!$check['optional']) {
            $mandatoryTotal++;
            if ($success) {
                $passed++;
            } else {
                $failed++;
            }
        }

        if (!$success) {
            $message = sprintf(
                '[%s] %s (exit=%d)',
                $check['id'],
                $check['name'],
                $execution['exit_code']
            );
            $output = trim($execution['stderr'] . "\n" . $execution['stdout']);
            if ($output !== '') {
                $message .= ': ' . firstLine($output);
            }
            $errors[] = $message;
        }

        $checkResults[] = [
            'id' => $check['id'],
            'name' => $check['name'],
            'command' => $check['command'],
            'optional' => $check['optional'],
            'status' => $status,
            'exitCode' => $execution['exit_code'],
            'output' => trim($execution['stdout'] . "\n" . $execution['stderr']),
        ];
    }

    if ($dryRun) {
        $summary = 'Dry run ejecutado. No se lanzaron comandos reales.';
    } elseif ($failed > 0) {
        $summary = sprintf('Se detectaron fallos en la batería (%d/%d obligatorias superadas).', $passed, $mandatoryTotal);
    } elseif ($noVerificable > 0) {
        $summary = sprintf('Batería completada con %d verificaciones no verificables.', $noVerificable);
    } else {
        $summary = 'Batería completada sin fallos.';
    }

    return [
        'executed' => !$dryRun,
        'suiteName' => $suiteName,
        'total' => $mandatoryTotal,
        'passed' => $passed,
        'failed' => $failed,
        'errors' => $errors,
        'summary' => $summary,
        'timestamp' => date('Y-m-d H:i:s'),
        'observations' => sprintf(
            'Total checks definidos: %d. Verificaciones no verificables: %d.',
            count($checks),
            $noVerificable
        ),
        'checks' => $checkResults,
    ];
}

function firstLine(string $value): string
{
    $lines = preg_split('/\R/u', trim($value)) ?: [];
    return $lines[0] ?? '';
}

function printUsage(): void
{
    $usage = <<<TXT
Uso:
  php .agents/skills/ejecutar-tests/scripts/ejecutar_tests.php [opciones]

Opciones:
  --nivel=standard|medio|agresivo
  --ventana=15m|30m|45m|1h|6h
  --json
  --dry-run
  --help
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

function main(array $argv): int
{
    try {
        $options = parseArguments($argv);
        if ($options['help']) {
            printUsage();
            return 0;
        }

        $niveles = ['standard', 'medio', 'agresivo'];
        if (!in_array($options['nivel'], $niveles, true)) {
            throw new InvalidArgumentException('Nivel invalido. Usa: standard, medio o agresivo.');
        }

        $ventanas = ['15m', '30m', '45m', '1h', '6h'];
        if (!in_array($options['ventana'], $ventanas, true)) {
            throw new InvalidArgumentException('Ventana invalida. Usa: 15m, 30m, 45m, 1h o 6h.');
        }

        $repositoryRoot = dirname(__DIR__, 4);
        $suiteName = sprintf('ejecutar-tests:%s-%s', $options['nivel'], $options['ventana']);
        $checks = buildChecks($repositoryRoot, $options['nivel']);
        $results = executeChecks($checks, $repositoryRoot, $suiteName, $options['dry_run']);

        $results['nivel'] = $options['nivel'];
        $results['ventana'] = $options['ventana'];

        if ($options['json']) {
            fwrite(STDOUT, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
            return 0;
        }

        fwrite(STDOUT, sprintf("Suite: %s\n", $results['suiteName']));
        fwrite(STDOUT, sprintf("Resumen: %s\n", $results['summary']));
        fwrite(STDOUT, sprintf("Obligatorias -> Total: %d, Superadas: %d, Fallidas: %d\n", $results['total'], $results['passed'], $results['failed']));
        if (count($results['errors']) > 0) {
            fwrite(STDOUT, "Errores:\n");
            foreach ($results['errors'] as $error) {
                fwrite(STDOUT, "- " . $error . PHP_EOL);
            }
        }

        return 0;
    } catch (Throwable $exception) {
        $error = [
            'executed' => false,
            'suiteName' => 'ejecutar-tests:error',
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'errors' => [$exception->getMessage()],
            'summary' => 'No se pudo ejecutar la batería de tests.',
            'timestamp' => date('Y-m-d H:i:s'),
            'observations' => 'Fallo de inicializacion.',
        ];

        fwrite(STDERR, json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        return 1;
    }
}

if (isset($argv[0]) && realpath((string) $argv[0]) === __FILE__) {
    exit(main($argv));
}

