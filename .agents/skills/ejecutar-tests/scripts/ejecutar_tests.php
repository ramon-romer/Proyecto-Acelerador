<?php
declare(strict_types=1);

/**
 * Script de bateria de validacion reutilizable para la skill $ejecutar-tests.
 * Ejecuta checks reales y devuelve salida estructurada en JSON.
 */

const VALID_LEVELS = ['standard', 'medio', 'agresivo', 'extremo'];
const VALID_WINDOWS = ['15m', '30m', '45m', '1h', '6h', '12h', '24h'];

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

function windowToSeconds(string $window): int
{
    $map = [
        '15m' => 900,
        '30m' => 1800,
        '45m' => 2700,
        '1h' => 3600,
        '6h' => 21600,
        '12h' => 43200,
        '24h' => 86400,
    ];

    if (!isset($map[$window])) {
        throw new InvalidArgumentException('Ventana invalida para conversion a segundos.');
    }

    return $map[$window];
}

/**
 * @return array{anecaAggressive: bool, backendAggressive: bool, mcpWorker: bool, anecaUnit: bool}
 */
function detectIntensiveAvailability(string $repositoryRoot): array
{
    return [
        'anecaAggressive' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_evaluador_ANECA' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_aggressive_battery.php'),
        'backendAggressive' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_aggressive_battery.php'),
        'mcpWorker' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . 'worker_jobs.php'),
        'anecaUnit' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_evaluador_ANECA' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'unit_src.php'),
    ];
}

/**
 * @return array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int}>
 */
function buildBaseChecks(string $repositoryRoot, string $nivel, bool $hasAnecaUnit): array
{
    $checks = [
        [
            'id' => 'php-version',
            'name' => 'Version de PHP',
            'command' => escapeshellarg(PHP_BINARY) . ' -v',
            'optional' => false,
            'category' => 'base',
            'strategy' => 'single',
        ],
        [
            'id' => 'backend-smoke',
            'name' => 'Smoke backend tutorias',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_usecases_smoke.php'),
            'optional' => false,
            'category' => 'base',
            'strategy' => 'single',
        ],
        [
            'id' => 'mcp-unit',
            'name' => 'MCP unit extract_pdf',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'unit_extract_pdf.php'),
            'optional' => false,
            'category' => 'base',
            'strategy' => 'single',
        ],
    ];

    if ($hasAnecaUnit) {
        $checks[] = [
            'id' => 'aneca-unit-src',
            'name' => 'ANECA unit src',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_evaluador_ANECA' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'unit_src.php'),
            'optional' => false,
            'category' => 'base',
            'strategy' => 'single',
        ];
    }

    if ($nivel !== 'standard') {
        $checks[] = [
            'id' => 'inspect-schema',
            'name' => 'Inspect schema backend',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'inspect_schema.php'),
            'optional' => true,
            'category' => 'base',
            'strategy' => 'single',
        ];

        $checks[] = [
            'id' => 'contract-routes',
            'name' => 'Contrato de rutas criticas',
            'command' => 'rg -n -F "/api/tutorias" '
                . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . '02-api-rest-contratos.md')
                . ' && rg -n -F "createTutoria" '
                . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Presentation' . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'TutoriaRoutes.php'),
            'optional' => false,
            'category' => 'base',
            'strategy' => 'single',
        ];
    }

    return $checks;
}

/**
 * @param array{anecaAggressive: bool, backendAggressive: bool, mcpWorker: bool, anecaUnit: bool} $availability
 * @return array{weights: array<string,float>, critical: string, notes: array<int,string>}
 */
function resolveIntensiveWeights(string $nivel, array $availability): array
{
    $notes = [];

    if ($nivel === 'medio') {
        if ($availability['anecaAggressive']) {
            return [
                'weights' => ['aneca' => 1.0],
                'critical' => 'aneca',
                'notes' => $notes,
            ];
        }

        if ($availability['backendAggressive']) {
            $notes[] = 'No hay ANECA aggressive disponible; medio usa backend aggressive al 100%.';
            return [
                'weights' => ['backend' => 1.0],
                'critical' => 'backend',
                'notes' => $notes,
            ];
        }

        $notes[] = 'Nivel medio sin bateria intensiva disponible (faltan ANECA y backend aggressive).';
        return [
            'weights' => [],
            'critical' => 'none',
            'notes' => $notes,
        ];
    }

    if ($nivel === 'agresivo') {
        $raw = ['aneca' => 0.60, 'backend' => 0.30, 'mcp' => 0.10];
        return normalizeWeightsByAvailability($raw, $availability, 'aneca', $notes, $nivel);
    }

    if ($nivel === 'extremo') {
        $raw = ['aneca' => 0.45, 'backend' => 0.35, 'mcp' => 0.20];
        return normalizeWeightsByAvailability($raw, $availability, 'backend', $notes, $nivel);
    }

    return [
        'weights' => [],
        'critical' => 'none',
        'notes' => $notes,
    ];
}

/**
 * @param array<string,float> $raw
 * @param array{anecaAggressive: bool, backendAggressive: bool, mcpWorker: bool, anecaUnit: bool} $availability
 * @param array<int,string> $notes
 * @return array{weights: array<string,float>, critical: string, notes: array<int,string>}
 */
function normalizeWeightsByAvailability(array $raw, array $availability, string $preferredCritical, array $notes, string $nivel): array
{
    $availabilityByKey = [
        'aneca' => $availability['anecaAggressive'],
        'backend' => $availability['backendAggressive'],
        'mcp' => $availability['mcpWorker'],
    ];

    $filtered = [];
    foreach ($raw as $key => $weight) {
        if ($availabilityByKey[$key]) {
            $filtered[$key] = $weight;
        } else {
            $notes[] = sprintf('Redistribucion: bloque %s no disponible para nivel %s.', $key, $nivel);
        }
    }

    if ($filtered === []) {
        $notes[] = sprintf('Nivel %s sin bloques intensivos disponibles.', $nivel);
        return [
            'weights' => [],
            'critical' => 'none',
            'notes' => $notes,
        ];
    }

    $sum = array_sum($filtered);
    foreach ($filtered as $key => $weight) {
        $filtered[$key] = $weight / $sum;
    }

    $critical = pickCriticalBlock($preferredCritical, array_keys($filtered));

    return [
        'weights' => $filtered,
        'critical' => $critical,
        'notes' => $notes,
    ];
}

/**
 * @param array<int,string> $availableKeys
 */
function pickCriticalBlock(string $preferredCritical, array $availableKeys): string
{
    if (in_array($preferredCritical, $availableKeys, true)) {
        return $preferredCritical;
    }

    $priority = ['aneca', 'backend', 'mcp'];
    foreach ($priority as $key) {
        if (in_array($key, $availableKeys, true)) {
            return $key;
        }
    }

    return 'none';
}

/**
 * @param array<string,float> $weights
 * @return array{budgets: array<string,int>, notes: array<int,string>}
 */
function allocateIntensiveBudget(int $totalSeconds, array $weights, string $critical, string $nivel): array
{
    $notes = [];

    if ($weights === [] || $totalSeconds <= 0) {
        return ['budgets' => [], 'notes' => $notes];
    }

    $minimums = [
        'aneca' => 60,
        'backend' => 60,
        'mcp' => 30,
    ];

    $budgets = [];
    $assigned = 0;
    foreach ($weights as $key => $weight) {
        $value = (int) floor($totalSeconds * $weight);
        $budgets[$key] = $value;
        $assigned += $value;
    }

    $remainder = $totalSeconds - $assigned;
    if ($remainder > 0) {
        $receiver = $critical !== 'none' && isset($budgets[$critical]) ? $critical : array_key_first($budgets);
        $budgets[$receiver] += $remainder;
        $notes[] = sprintf('Resto de %ds asignado a bloque critico %s.', $remainder, (string) $receiver);
    }

    // Redistribuye bloques por debajo del minimo util.
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($budgets as $key => $seconds) {
            $min = $minimums[$key] ?? 30;
            if ($seconds > 0 && $seconds < $min) {
                $transfer = $seconds;
                unset($budgets[$key]);
                $changed = true;
                $notes[] = sprintf('Bloque %s con %ds (<%ds) se redistribuye por minimo util.', $key, $transfer, $min);

                if ($budgets === []) {
                    // Si era el unico bloque, se conserva para no perder toda la fase intensiva.
                    $budgets[$key] = $transfer;
                    $notes[] = sprintf('Se mantiene bloque %s al no existir alternativas.', $key);
                    $changed = false;
                    break;
                }

                $receiver = $critical !== 'none' && isset($budgets[$critical]) ? $critical : array_key_first($budgets);
                $budgets[$receiver] += $transfer;
                break;
            }
        }
    }

    // Extremo: mayor presion mediante repeticion implicita por bloques divididos.
    if ($nivel === 'extremo' && count($budgets) > 0) {
        $notes[] = 'Nivel extremo aplica repeticion por bloques para aumentar presion con el mismo presupuesto total.';
    }

    return ['budgets' => $budgets, 'notes' => $notes];
}

function progressIntervalForDuration(int $seconds): int
{
    if ($seconds <= 120) {
        return 5;
    }

    if ($seconds <= 900) {
        return 10;
    }

    return 30;
}

/**
 * @param array<string,int> $budgets
 * @return array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int}>
 */
function buildIntensiveChecks(string $repositoryRoot, string $nivel, array $budgets): array
{
    $checks = [];

    $anecaAggressivePath = $repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_evaluador_ANECA' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_aggressive_battery.php';
    $backendAggressivePath = $repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_aggressive_battery.php';
    $mcpWorkerPath = $repositoryRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . 'worker_jobs.php';

    if (isset($budgets['aneca']) && $budgets['aneca'] > 0 && is_file($anecaAggressivePath)) {
        $checks = array_merge($checks, buildBatteryChecksForComponent(
            'aneca',
            'ANECA bateria agresiva',
            $nivel,
            $budgets['aneca'],
            $anecaAggressivePath,
            'acelerador_aneca_aggressive'
        ));
    }

    if (isset($budgets['backend']) && $budgets['backend'] > 0 && is_file($backendAggressivePath)) {
        $checks = array_merge($checks, buildBatteryChecksForComponent(
            'backend',
            'Backend bateria agresiva',
            $nivel,
            $budgets['backend'],
            $backendAggressivePath,
            'acelerador_backend_aggressive'
        ));
    }

    if (isset($budgets['mcp']) && $budgets['mcp'] > 0 && is_file($mcpWorkerPath)) {
        $loopPauseMs = $nivel === 'extremo' ? 0 : 200;
        $checks[] = [
            'id' => 'mcp-worker-loop',
            'name' => sprintf('Worker MCP en bucle temporal (%ds)', $budgets['mcp']),
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($mcpWorkerPath) . ' --once',
            'optional' => true,
            'category' => 'intensivo',
            'strategy' => 'timed_loop',
            'runner' => 'mcp_loop',
            'loop_seconds' => $budgets['mcp'],
            'loop_pause_ms' => $loopPauseMs,
            'budget_seconds' => $budgets['mcp'],
        ];
    }

    return $checks;
}

/**
 * @return array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int}>
 */
function buildBatteryChecksForComponent(
    string $componentId,
    string $baseName,
    string $nivel,
    int $budgetSeconds,
    string $scriptPath,
    string $reportPrefix
): array {
    $checks = [];

    if ($nivel !== 'extremo') {
        $reportPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $reportPrefix . '_' . date('Ymd_His') . '.json';
        $checks[] = [
            'id' => $componentId . '-aggressive',
            'name' => sprintf('%s (%ds)', $baseName, $budgetSeconds),
            'command' => escapeshellarg(PHP_BINARY)
                . ' ' . escapeshellarg($scriptPath)
                . ' --duration-seconds=' . $budgetSeconds
                . ' --progress-interval=' . progressIntervalForDuration($budgetSeconds)
                . ' --report-file=' . escapeshellarg($reportPath),
            'optional' => false,
            'category' => 'intensivo',
            'strategy' => 'single_window_budget',
            'budget_seconds' => $budgetSeconds,
        ];

        return $checks;
    }

    // Extremo: divide en dos pasadas para forzar repeticion/ciclos con mismo presupuesto total.
    $pass1 = max((int) floor($budgetSeconds * 0.6), 60);
    $pass2 = $budgetSeconds - $pass1;

    if ($pass2 < 60) {
        $pass1 = $budgetSeconds;
        $pass2 = 0;
    }

    $reportPath1 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $reportPrefix . '_extremo_p1_' . date('Ymd_His') . '.json';
    $checks[] = [
        'id' => $componentId . '-extremo-pass1',
        'name' => sprintf('%s extremo pass1 (%ds)', $baseName, $pass1),
        'command' => escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg($scriptPath)
            . ' --duration-seconds=' . $pass1
            . ' --progress-interval=' . progressIntervalForDuration($pass1)
            . ' --report-file=' . escapeshellarg($reportPath1),
        'optional' => false,
        'category' => 'intensivo',
        'strategy' => 'extremo_repeated_cycles',
        'budget_seconds' => $pass1,
    ];

    if ($pass2 > 0) {
        $reportPath2 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $reportPrefix . '_extremo_p2_' . date('Ymd_His') . '.json';
        $checks[] = [
            'id' => $componentId . '-extremo-pass2',
            'name' => sprintf('%s extremo pass2 (%ds)', $baseName, $pass2),
            'command' => escapeshellarg(PHP_BINARY)
                . ' ' . escapeshellarg($scriptPath)
                . ' --duration-seconds=' . $pass2
                . ' --progress-interval=' . progressIntervalForDuration($pass2)
                . ' --report-file=' . escapeshellarg($reportPath2),
            'optional' => false,
            'category' => 'intensivo',
            'strategy' => 'extremo_repeated_cycles',
            'budget_seconds' => $pass2,
        ];
    }

    return $checks;
}

/**
 * @return array{checks: array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int}>, plan: array<string,mixed>}
 */
function buildChecks(string $repositoryRoot, string $nivel, string $ventana): array
{
    $availability = detectIntensiveAvailability($repositoryRoot);
    $windowSeconds = windowToSeconds($ventana);

    $baseChecks = buildBaseChecks($repositoryRoot, $nivel, $availability['anecaUnit']);

    if ($nivel === 'standard') {
        return [
            'checks' => $baseChecks,
            'plan' => [
                'nivel' => $nivel,
                'ventana' => $ventana,
                'windowSeconds' => $windowSeconds,
                'intensiveBudgetSeconds' => 0,
                'distribution' => [],
                'redistributions' => ['Nivel standard no ejecuta fase intensiva.'],
                'availability' => $availability,
            ],
        ];
    }

    $weightPlan = resolveIntensiveWeights($nivel, $availability);
    $allocation = allocateIntensiveBudget($windowSeconds, $weightPlan['weights'], $weightPlan['critical'], $nivel);
    $intensiveChecks = buildIntensiveChecks($repositoryRoot, $nivel, $allocation['budgets']);

    $distribution = [];
    foreach ($allocation['budgets'] as $key => $seconds) {
        $distribution[] = sprintf('%s=%ds', $key, $seconds);
    }

    return [
        'checks' => array_merge($baseChecks, $intensiveChecks),
        'plan' => [
            'nivel' => $nivel,
            'ventana' => $ventana,
            'windowSeconds' => $windowSeconds,
            'intensiveBudgetSeconds' => $windowSeconds,
            'distribution' => $distribution,
            'redistributions' => array_merge($weightPlan['notes'], $allocation['notes']),
            'availability' => $availability,
        ],
    ];
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
 * @param array{id: string, name: string, command: string, optional: bool, loop_seconds?: int, loop_pause_ms?: int} $check
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runTimedLoopCheck(array $check, string $workingDirectory): array
{
    $loopSeconds = (int) ($check['loop_seconds'] ?? 0);
    $pauseMs = (int) ($check['loop_pause_ms'] ?? 0);

    if ($loopSeconds <= 0) {
        return [
            'exit_code' => 1,
            'stdout' => '',
            'stderr' => 'Loop temporal sin presupuesto valido.',
        ];
    }

    $start = microtime(true);
    $iterations = 0;
    $failures = 0;
    $messages = [];

    while ((microtime(true) - $start) < $loopSeconds) {
        $execution = runCommand($check['command'], $workingDirectory);
        $iterations++;

        if ($execution['exit_code'] !== 0) {
            $failures++;
            if (count($messages) < 3) {
                $messages[] = firstLine(trim($execution['stderr'] . "\n" . $execution['stdout']));
            }
        }

        if ($pauseMs > 0) {
            usleep($pauseMs * 1000);
        }
    }

    $elapsed = (int) round(microtime(true) - $start);
    $stdout = sprintf(
        'Timed loop ejecutado: iteraciones=%d, fallos=%d, presupuesto=%ds, duracion_real=%ds.',
        $iterations,
        $failures,
        $loopSeconds,
        $elapsed
    );

    $stderr = $messages === [] ? '' : implode(' | ', $messages);

    return [
        'exit_code' => $failures === 0 ? 0 : 1,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/**
 * @param array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int}> $checks
 * @param array<string,mixed> $plan
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
function executeChecks(array $checks, string $workingDirectory, string $suiteName, bool $dryRun, array $plan): array
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
                'category' => $check['category'] ?? 'base',
                'strategy' => $check['strategy'] ?? 'single',
                'budgetSeconds' => (int) ($check['budget_seconds'] ?? 0),
            ];
            continue;
        }

        $execution = ($check['runner'] ?? '') === 'mcp_loop'
            ? runTimedLoopCheck($check, $workingDirectory)
            : runCommand($check['command'], $workingDirectory);

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
            'category' => $check['category'] ?? 'base',
            'strategy' => $check['strategy'] ?? 'single',
            'budgetSeconds' => (int) ($check['budget_seconds'] ?? 0),
        ];
    }

    if ($dryRun) {
        $summary = 'Dry run ejecutado. No se lanzaron comandos reales.';
    } elseif ($failed > 0) {
        $summary = sprintf('Se detectaron fallos en la bateria (%d/%d obligatorias superadas).', $passed, $mandatoryTotal);
    } elseif ($noVerificable > 0) {
        $summary = sprintf('Bateria completada con %d verificaciones no verificables.', $noVerificable);
    } else {
        $summary = 'Bateria completada sin fallos.';
    }

    $distribution = $plan['distribution'] === []
        ? 'sin fase intensiva'
        : implode(', ', $plan['distribution']);

    $redistributionNote = $plan['redistributions'] === []
        ? 'sin redistribuciones'
        : implode(' | ', $plan['redistributions']);

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
            'Nivel=%s; Ventana=%s; Presupuesto intensivo=%ds; Distribucion=%s; No verificables=%d; %s.',
            (string) $plan['nivel'],
            (string) $plan['ventana'],
            (int) $plan['intensiveBudgetSeconds'],
            $distribution,
            $noVerificable,
            $redistributionNote
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
  --nivel=standard|medio|agresivo|extremo
  --ventana=15m|30m|45m|1h|6h|12h|24h
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

        if (!in_array($options['nivel'], VALID_LEVELS, true)) {
            throw new InvalidArgumentException('Nivel invalido. Usa: standard, medio, agresivo o extremo.');
        }

        if (!in_array($options['ventana'], VALID_WINDOWS, true)) {
            throw new InvalidArgumentException('Ventana invalida. Usa: 15m, 30m, 45m, 1h, 6h, 12h o 24h.');
        }

        $repositoryRoot = dirname(__DIR__, 4);
        $suiteName = sprintf('ejecutar-tests:%s-%s', $options['nivel'], $options['ventana']);

        $plan = buildChecks($repositoryRoot, $options['nivel'], $options['ventana']);
        $results = executeChecks($plan['checks'], $repositoryRoot, $suiteName, $options['dry_run'], $plan['plan']);

        $results['nivel'] = $options['nivel'];
        $results['ventana'] = $options['ventana'];

        if ($options['json']) {
            fwrite(STDOUT, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
            return 0;
        }

        fwrite(STDOUT, sprintf("Suite: %s\n", $results['suiteName']));
        fwrite(STDOUT, sprintf("Resumen: %s\n", $results['summary']));
        fwrite(STDOUT, sprintf("Obligatorias -> Total: %d, Superadas: %d, Fallidas: %d\n", $results['total'], $results['passed'], $results['failed']));
        fwrite(STDOUT, sprintf("Observaciones: %s\n", $results['observations']));

        if (count($results['errors']) > 0) {
            fwrite(STDOUT, "Errores:\n");
            foreach ($results['errors'] as $error) {
                fwrite(STDOUT, '- ' . $error . PHP_EOL);
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
            'summary' => 'No se pudo ejecutar la bateria de tests.',
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
