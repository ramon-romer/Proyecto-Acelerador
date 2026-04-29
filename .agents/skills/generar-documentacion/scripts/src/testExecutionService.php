<?php
declare(strict_types=1);

namespace GenerarDocumentacion\TestExecutionService;

use RuntimeException;

/**
 * @return array{
 *   executed: bool,
 *   suiteName: string,
 *   total: ?int,
 *   passed: ?int,
 *   failed: ?int,
 *   errors: array<int, string>,
 *   summary: string,
 *   timestamp: string,
 *   observations: string
 * }
 */
function runTestsIfRequested(bool $shouldRunTests, string $repositoryRoot, array $options = []): array
{
    if (!$shouldRunTests) {
        return [
            'executed' => false,
            'suiteName' => 'ejecutar-tests',
            'total' => null,
            'passed' => null,
            'failed' => null,
            'errors' => [],
            'summary' => 'No se ejecutaron tests en esta ejecución.',
            'timestamp' => date('Y-m-d H:i:s'),
            'observations' => '',
        ];
    }

    $providedResult = $options['provided_result'] ?? null;
    if (is_array($providedResult)) {
        $providedResult['executed'] = true;
        return normalizeTestResponse($providedResult);
    }

    $nivel = isset($options['nivel']) ? (string) $options['nivel'] : 'standard';
    $ventana = isset($options['ventana']) ? (string) $options['ventana'] : '15m';
    $runnerScript = isset($options['runner_script'])
        ? (string) $options['runner_script']
        : $repositoryRoot . DIRECTORY_SEPARATOR . '.agents' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . 'ejecutar-tests' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ejecutar_tests.php';

    if (!is_file($runnerScript)) {
        return [
            'executed' => true,
            'suiteName' => 'ejecutar-tests',
            'total' => null,
            'passed' => null,
            'failed' => null,
            'errors' => ['No se encontro el script de ejecutar-tests.'],
            'summary' => 'No fue posible ejecutar la batería de tests.',
            'timestamp' => date('Y-m-d H:i:s'),
            'observations' => 'Falta runner: ' . $runnerScript,
        ];
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' .
        escapeshellarg($runnerScript)
        . ' --json'
        . ' --nivel=' . escapeshellarg($nivel)
        . ' --ventana=' . escapeshellarg($ventana)
        . ' --scope=toda-app'
        . ' --intensiva=auto';

    try {
        $execution = runCommand($command, $repositoryRoot);
    } catch (RuntimeException $exception) {
        return [
            'executed' => true,
            'suiteName' => sprintf('ejecutar-tests:%s-%s', $nivel, $ventana),
            'total' => null,
            'passed' => null,
            'failed' => null,
            'errors' => [$exception->getMessage()],
            'summary' => 'No fue posible ejecutar la batería de tests.',
            'timestamp' => date('Y-m-d H:i:s'),
            'observations' => 'Error al lanzar ejecutar-tests.',
        ];
    }

    if ($execution['exit_code'] !== 0) {
        return [
            'executed' => true,
            'suiteName' => sprintf('ejecutar-tests:%s-%s', $nivel, $ventana),
            'total' => null,
            'passed' => null,
            'failed' => null,
            'errors' => [trim($execution['stderr'] !== '' ? $execution['stderr'] : $execution['stdout'])],
            'summary' => 'La batería de tests terminó con error de ejecución.',
            'timestamp' => date('Y-m-d H:i:s'),
            'observations' => 'Exit code de ejecutar-tests: ' . $execution['exit_code'],
        ];
    }

    $decoded = json_decode($execution['stdout'], true);
    if (!is_array($decoded)) {
        return [
            'executed' => true,
            'suiteName' => sprintf('ejecutar-tests:%s-%s', $nivel, $ventana),
            'total' => null,
            'passed' => null,
            'failed' => null,
            'errors' => ['La salida de ejecutar-tests no fue JSON válido.'],
            'summary' => 'No fue posible interpretar la salida de la batería.',
            'timestamp' => date('Y-m-d H:i:s'),
            'observations' => firstLine(trim($execution['stdout'])),
        ];
    }

    return normalizeTestResponse($decoded);
}

/**
 * @param array<string, mixed> $rawResult
 * @return array{
 *   executed: bool,
 *   suiteName: string,
 *   total: ?int,
 *   passed: ?int,
 *   failed: ?int,
 *   errors: array<int, string>,
 *   summary: string,
 *   timestamp: string,
 *   observations: string
 * }
 */
function normalizeTestResponse(array $rawResult): array
{
    $errors = [];
    if (isset($rawResult['errors']) && is_array($rawResult['errors'])) {
        foreach ($rawResult['errors'] as $error) {
            $cleanError = trim((string) $error);
            if ($cleanError !== '') {
                $errors[] = $cleanError;
            }
        }
    }

    $summary = trim((string) ($rawResult['summary'] ?? 'Sin resumen de batería.'));
    if ($summary === '') {
        $summary = 'Sin resumen de batería.';
    }

    return [
        'executed' => (bool) ($rawResult['executed'] ?? true),
        'suiteName' => trim((string) ($rawResult['suiteName'] ?? 'ejecutar-tests')),
        'total' => toNullableInt($rawResult['total'] ?? null),
        'passed' => toNullableInt($rawResult['passed'] ?? null),
        'failed' => toNullableInt($rawResult['failed'] ?? null),
        'errors' => $errors,
        'summary' => $summary,
        'timestamp' => trim((string) ($rawResult['timestamp'] ?? date('Y-m-d H:i:s'))),
        'observations' => trim((string) ($rawResult['observations'] ?? '')),
    ];
}

function shouldExecuteTestsFromAnswer(string $answer): bool
{
    $cleanAnswer = normalizeAnswer($answer);
    return in_array($cleanAnswer, ['s', 'si', 'sí', 'y', 'yes'], true);
}

function normalizeAnswer(string $answer): string
{
    $normalized = trim($answer);
    $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;
    return toLower($normalized);
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
        'stdout' => trim($stdout === false ? '' : $stdout),
        'stderr' => trim($stderr === false ? '' : $stderr),
    ];
}

function toNullableInt($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_int($value)) {
        return $value;
    }

    if (is_numeric((string) $value)) {
        return (int) $value;
    }

    return null;
}

function firstLine(string $value): string
{
    $lines = preg_split('/\R/u', trim($value)) ?: [];
    return $lines[0] ?? '';
}

function toLower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

