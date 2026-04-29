<?php
declare(strict_types=1);

/**
 * Script de bateria de validacion reutilizable para la skill $ejecutar-tests.
 * Ejecuta checks reales y devuelve salida estructurada en JSON.
 */

const VALID_LEVELS = ['standard', 'medio', 'agresivo', 'extremo'];
const PRESET_WINDOWS = ['15m', '30m', '45m', '1h', '6h', '12h', '24h'];
const VALID_SCOPES = ['backend', 'frontend', 'evaluador', 'aneca', 'mcp', 'contratos', 'toda-app'];
const OUTPUT_MODES = ['console', 'json', 'both'];
const INTENSIVE_MODES = ['auto', 'si', 'no'];

/**
 * @return array{
 *   nivel: ?string,
 *   ventana: ?string,
 *   scope: ?string,
 *   output_mode: string,
 *   intensive_mode: string,
 *   json: bool,
 *   dry_run: bool,
 *   help: bool,
 *   interactive: bool,
 *   auto_confirm: bool,
 *   provided: array{
 *     nivel: bool,
 *     ventana: bool,
 *     scope: bool,
 *     output_mode: bool,
 *     intensive_mode: bool
 *   }
 * }
 */
function parseArguments(array $argv): array
{
    $parsed = [
        'nivel' => null,
        'ventana' => null,
        'scope' => null,
        'output_mode' => 'console',
        'intensive_mode' => 'auto',
        'json' => false,
        'dry_run' => false,
        'help' => false,
        'interactive' => false,
        'auto_confirm' => false,
        'provided' => [
            'nivel' => false,
            'ventana' => false,
            'scope' => false,
            'output_mode' => false,
            'intensive_mode' => false,
        ],
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $argument = $argv[$i];

        if ($argument === '--help' || $argument === '-h') {
            $parsed['help'] = true;
            continue;
        }

        if ($argument === '--interactive') {
            $parsed['interactive'] = true;
            continue;
        }

        if ($argument === '--yes') {
            $parsed['auto_confirm'] = true;
            continue;
        }

        if ($argument === '--json') {
            $parsed['json'] = true;
            $parsed['output_mode'] = 'json';
            $parsed['provided']['output_mode'] = true;
            continue;
        }

        if ($argument === '--both') {
            $parsed['output_mode'] = 'both';
            $parsed['provided']['output_mode'] = true;
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
            $parsed['provided']['nivel'] = true;
            continue;
        }

        if (str_starts_with($argument, '--nivel=')) {
            $parsed['nivel'] = substr($argument, strlen('--nivel='));
            $parsed['provided']['nivel'] = true;
            continue;
        }

        if ($argument === '--ventana') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException('Falta valor para --ventana.');
            }
            $parsed['ventana'] = (string) $argv[++$i];
            $parsed['provided']['ventana'] = true;
            continue;
        }

        if (str_starts_with($argument, '--ventana=')) {
            $parsed['ventana'] = substr($argument, strlen('--ventana='));
            $parsed['provided']['ventana'] = true;
            continue;
        }

        if ($argument === '--scope') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException('Falta valor para --scope.');
            }
            $parsed['scope'] = (string) $argv[++$i];
            $parsed['provided']['scope'] = true;
            continue;
        }

        if (str_starts_with($argument, '--scope=')) {
            $parsed['scope'] = substr($argument, strlen('--scope='));
            $parsed['provided']['scope'] = true;
            continue;
        }

        if ($argument === '--output') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException('Falta valor para --output.');
            }
            $parsed['output_mode'] = (string) $argv[++$i];
            $parsed['provided']['output_mode'] = true;
            continue;
        }

        if (str_starts_with($argument, '--output=')) {
            $parsed['output_mode'] = substr($argument, strlen('--output='));
            $parsed['provided']['output_mode'] = true;
            continue;
        }

        if ($argument === '--intensiva' || $argument === '--fase-intensiva') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException(sprintf('Falta valor para %s.', $argument));
            }
            $parsed['intensive_mode'] = (string) $argv[++$i];
            $parsed['provided']['intensive_mode'] = true;
            continue;
        }

        if (str_starts_with($argument, '--intensiva=')) {
            $parsed['intensive_mode'] = substr($argument, strlen('--intensiva='));
            $parsed['provided']['intensive_mode'] = true;
            continue;
        }

        if (str_starts_with($argument, '--fase-intensiva=')) {
            $parsed['intensive_mode'] = substr($argument, strlen('--fase-intensiva='));
            $parsed['provided']['intensive_mode'] = true;
            continue;
        }

        throw new InvalidArgumentException(sprintf('Argumento no reconocido: %s', $argument));
    }

    return $parsed;
}

/**
 * @param array{
 *   nivel: ?string,
 *   ventana: ?string,
 *   scope: ?string,
 *   output_mode: string,
 *   intensive_mode: string,
 *   json: bool,
 *   dry_run: bool,
 *   help: bool,
 *   interactive: bool,
 *   auto_confirm: bool,
 *   provided: array{
 *     nivel: bool,
 *     ventana: bool,
 *     scope: bool,
 *     output_mode: bool,
 *     intensive_mode: bool
 *   }
 * } $options
 * @return array{
 *   nivel: string,
 *   ventana: string,
 *   scope: string,
 *   output_mode: string,
 *   intensive_mode: string,
 *   json: bool,
 *   dry_run: bool,
 *   help: bool,
 *   interactive: bool,
 *   auto_confirm: bool,
 *   provided: array{
 *     nivel: bool,
 *     ventana: bool,
 *     scope: bool,
 *     output_mode: bool,
 *     intensive_mode: bool
 *   }
 * }
 */
function resolveExecutionOptions(array $options): array
{
    $missing = missingRequiredInputs($options);

    if ($options['interactive']) {
        $options = runInteractiveWizard($options);
        $missing = missingRequiredInputs($options);
    } elseif ($missing !== []) {
        if (!isInteractiveTerminal()) {
            throw new InvalidArgumentException(
                'Faltan parametros requeridos (' . implode(', ', $missing)
                . '). Usa --interactive o define --nivel, --ventana y --scope.'
            );
        }

        fwrite(
            STDOUT,
            "Se detecto una invocacion incompleta. Se activa el modo interactivo para evitar supuestos.\n"
        );
        $options['interactive'] = true;
        $options = runInteractiveWizard($options);
        $missing = missingRequiredInputs($options);
    }

    if ($missing !== []) {
        throw new InvalidArgumentException(
            'No se pudo resolver toda la configuracion requerida: ' . implode(', ', $missing) . '.'
        );
    }

    /** @var string $nivel */
    $nivel = normalizeLevel((string) $options['nivel']);
    /** @var string $ventana */
    $ventana = normalizeWindow((string) $options['ventana']);
    /** @var string $scope */
    $scope = normalizeScope((string) $options['scope']);
    /** @var string $outputMode */
    $outputMode = normalizeOutputMode((string) $options['output_mode']);
    /** @var string $intensiveMode */
    $intensiveMode = normalizeIntensiveMode((string) $options['intensive_mode']);

    $resolved = $options;
    $resolved['nivel'] = $nivel;
    $resolved['ventana'] = $ventana;
    $resolved['scope'] = $scope;
    $resolved['output_mode'] = $outputMode;
    $resolved['intensive_mode'] = $intensiveMode;
    $resolved['json'] = $outputMode === 'json' || $outputMode === 'both';

    return $resolved;
}

/**
 * @param array{
 *   nivel: ?string,
 *   ventana: ?string,
 *   scope: ?string,
 *   output_mode: string,
 *   intensive_mode: string,
 *   json: bool,
 *   dry_run: bool,
 *   help: bool,
 *   interactive: bool,
 *   auto_confirm: bool,
 *   provided: array{
 *     nivel: bool,
 *     ventana: bool,
 *     scope: bool,
 *     output_mode: bool,
 *     intensive_mode: bool
 *   }
 * } $options
 * @return array<int,string>
 */
function missingRequiredInputs(array $options): array
{
    $missing = [];

    if (!$options['provided']['nivel'] && trim((string) ($options['nivel'] ?? '')) === '') {
        $missing[] = 'nivel';
    }

    if (!$options['provided']['ventana'] && trim((string) ($options['ventana'] ?? '')) === '') {
        $missing[] = 'ventana';
    }

    if (!$options['provided']['scope'] && trim((string) ($options['scope'] ?? '')) === '') {
        $missing[] = 'scope';
    }

    return $missing;
}

function normalizeLevel(string $value): string
{
    $normalized = toLower(trim($value));
    $normalized = str_replace(['á', 'à', 'ä'], 'a', $normalized);

    $aliases = [
        'standard' => 'standard',
        'basico' => 'standard',
        'basic' => 'standard',
        'medio' => 'medio',
        'agresivo' => 'agresivo',
        'extremo' => 'extremo',
    ];

    if (!isset($aliases[$normalized])) {
        throw new InvalidArgumentException('Nivel invalido. Usa: basico, medio, agresivo o extremo.');
    }

    return $aliases[$normalized];
}

function normalizeWindow(string $value): string
{
    $normalized = toLower(trim($value));
    $normalized = str_replace(' ', '', $normalized);

    if (in_array($normalized, PRESET_WINDOWS, true)) {
        return $normalized;
    }

    if (!preg_match('/^([1-9][0-9]*)(m|h)$/', $normalized, $matches)) {
        throw new InvalidArgumentException(
            'Ventana invalida. Usa: 15m, 30m, 45m, 1h, 6h, 12h, 24h o una personalizada como 90m/2h.'
        );
    }

    $quantity = (int) $matches[1];
    $unit = $matches[2];
    if ($unit === 'm' && $quantity < 1) {
        throw new InvalidArgumentException('Ventana personalizada invalida: minutos debe ser >= 1.');
    }

    if ($unit === 'h' && $quantity < 1) {
        throw new InvalidArgumentException('Ventana personalizada invalida: horas debe ser >= 1.');
    }

    return sprintf('%d%s', $quantity, $unit);
}

function normalizeScope(string $value): string
{
    $normalized = toLower(trim($value));
    $normalized = str_replace(['á', 'à', 'ä'], 'a', $normalized);
    $normalized = str_replace([' ', '_'], '-', $normalized);

    $aliases = [
        'backend' => 'backend',
        'frontend' => 'frontend',
        'evaluador' => 'evaluador',
        'aneca' => 'aneca',
        'mcp' => 'mcp',
        'contratos' => 'contratos',
        'toda-app' => 'toda-app',
        'toda' => 'toda-app',
        'app' => 'toda-app',
        'toda-la-app' => 'toda-app',
    ];

    if (!isset($aliases[$normalized])) {
        throw new InvalidArgumentException(
            'Scope invalido. Usa: backend, frontend, evaluador, ANECA, MCP, contratos o toda la app.'
        );
    }

    return $aliases[$normalized];
}

function normalizeOutputMode(string $value): string
{
    $normalized = toLower(trim($value));
    $normalized = str_replace(['á', 'à', 'ä'], 'a', $normalized);
    $normalized = str_replace(' ', '-', $normalized);

    $aliases = [
        'console' => 'console',
        'consola' => 'console',
        'legible' => 'console',
        'humana' => 'console',
        'json' => 'json',
        'both' => 'both',
        'ambas' => 'both',
        'ambos' => 'both',
    ];

    if (!isset($aliases[$normalized])) {
        throw new InvalidArgumentException('Modo de salida invalido. Usa: consola, json o ambas.');
    }

    return $aliases[$normalized];
}

function normalizeIntensiveMode(string $value): string
{
    $normalized = toLower(trim($value));
    $normalized = str_replace(['í', 'ì', 'ï'], 'i', $normalized);
    $normalized = str_replace(['á', 'à', 'ä'], 'a', $normalized);

    $aliases = [
        'auto' => 'auto',
        'automatica' => 'auto',
        'automatico' => 'auto',
        'si' => 'si',
        's' => 'si',
        'yes' => 'si',
        'no' => 'no',
        'n' => 'no',
    ];

    if (!isset($aliases[$normalized])) {
        throw new InvalidArgumentException('Modo intensivo invalido. Usa: si, no o auto.');
    }

    return $aliases[$normalized];
}

function shouldRunIntensive(string $nivel, string $intensiveMode): bool
{
    if ($intensiveMode === 'si') {
        return true;
    }

    if ($intensiveMode === 'no') {
        return false;
    }

    return $nivel !== 'standard';
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

    if (isset($map[$window])) {
        return $map[$window];
    }

    if (!preg_match('/^([1-9][0-9]*)(m|h)$/', $window, $matches)) {
        throw new InvalidArgumentException('Ventana invalida para conversion a segundos.');
    }

    $quantity = (int) $matches[1];
    $unit = $matches[2];

    return $unit === 'h' ? $quantity * 3600 : $quantity * 60;
}

function isInteractiveTerminal(): bool
{
    if (PHP_SAPI !== 'cli') {
        return false;
    }

    if (!defined('STDIN') || !defined('STDOUT')) {
        return false;
    }

    if (function_exists('stream_isatty')) {
        return @stream_isatty(STDIN) && @stream_isatty(STDOUT);
    }

    if (function_exists('posix_isatty')) {
        return @posix_isatty(STDIN) && @posix_isatty(STDOUT);
    }

    return true;
}

/**
 * @return array{
 *   anecaAggressive: bool,
 *   backendAggressive: bool,
 *   mcpWorker: bool,
 *   anecaUnit: bool,
 *   backendSmoke: bool,
 *   mcpUnit: bool,
 *   contractDoc: bool,
 *   contractRoute: bool,
 *   frontendPath: bool
 * }
 */
function detectIntensiveAvailability(string $repositoryRoot): array
{
    $anecaAggressivePath = $repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_evaluador_ANECA' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_aggressive_battery.php';
    $anecaUnitPath = $repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_evaluador_ANECA' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'unit_src.php';

    return [
        'anecaAggressive' => is_file($anecaAggressivePath),
        'backendAggressive' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_aggressive_battery.php'),
        'mcpWorker' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . 'worker_jobs.php'),
        'anecaUnit' => is_file($anecaUnitPath),
        'backendSmoke' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_usecases_smoke.php'),
        'mcpUnit' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'unit_extract_pdf.php'),
        'contractDoc' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . '02-api-rest-contratos.md'),
        'contractRoute' => is_file($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Presentation' . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'TutoriaRoutes.php'),
        'frontendPath' => is_dir($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'frontend'),
    ];
}

/**
 * @return array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int, scopes?: array<int,string>}>
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
            'scopes' => ['global'],
        ],
        [
            'id' => 'backend-smoke',
            'name' => 'Smoke backend tutorias',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run_usecases_smoke.php'),
            'optional' => false,
            'category' => 'base',
            'strategy' => 'single',
            'scopes' => ['backend'],
        ],
        [
            'id' => 'mcp-unit',
            'name' => 'MCP unit extract_pdf',
            'command' => escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repositoryRoot . DIRECTORY_SEPARATOR . 'mcp-server' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'unit_extract_pdf.php'),
            'optional' => false,
            'category' => 'base',
            'strategy' => 'single',
            'scopes' => ['mcp'],
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
            'scopes' => ['aneca', 'evaluador'],
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
            'scopes' => ['backend'],
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
            'scopes' => ['contratos', 'backend'],
        ];
    }

    return $checks;
}

/**
 * @param array{
 *   anecaAggressive: bool,
 *   backendAggressive: bool,
 *   mcpWorker: bool,
 *   anecaUnit: bool,
 *   backendSmoke: bool,
 *   mcpUnit: bool,
 *   contractDoc: bool,
 *   contractRoute: bool,
 *   frontendPath: bool
 * } $availability
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
 * @param array{
 *   anecaAggressive: bool,
 *   backendAggressive: bool,
 *   mcpWorker: bool,
 *   anecaUnit: bool,
 *   backendSmoke: bool,
 *   mcpUnit: bool,
 *   contractDoc: bool,
 *   contractRoute: bool,
 *   frontendPath: bool
 * } $availability
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
 * @return array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int, scopes?: array<int,string>}>
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
            'scopes' => ['mcp'],
        ];
    }

    return $checks;
}

/**
 * @return array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int, scopes?: array<int,string>}>
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
            'scopes' => $componentId === 'backend' ? ['backend'] : ['aneca', 'evaluador'],
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
        'scopes' => $componentId === 'backend' ? ['backend'] : ['aneca', 'evaluador'],
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
            'scopes' => $componentId === 'backend' ? ['backend'] : ['aneca', 'evaluador'],
        ];
    }

    return $checks;
}

/**
 * @return array{checks: array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int, scopes?: array<int,string>}>, plan: array<string,mixed>}
 */
function buildChecks(
    string $repositoryRoot,
    string $nivel,
    string $ventana,
    string $scope,
    string $intensiveMode,
    string $outputMode
): array {
    $availability = detectIntensiveAvailability($repositoryRoot);
    $windowSeconds = windowToSeconds($ventana);

    $notes = [];
    $baseChecks = buildBaseChecks($repositoryRoot, $nivel, $availability['anecaUnit']);

    $intensiveEnabled = shouldRunIntensive($nivel, $intensiveMode);
    $intensivePolicyLevel = $nivel;
    if ($intensiveEnabled && $nivel === 'standard') {
        $intensivePolicyLevel = 'medio';
        $notes[] = 'Fase intensiva forzada con nivel basico; se aplica politica intensiva de nivel medio.';
    }

    $distribution = [];
    $intensiveBudgetSeconds = 0;
    $intensiveChecks = [];

    if ($intensiveEnabled) {
        $weightPlan = resolveIntensiveWeights($intensivePolicyLevel, $availability);
        $intensiveBudgetSeconds = $windowSeconds;
        $allocation = allocateIntensiveBudget($windowSeconds, $weightPlan['weights'], $weightPlan['critical'], $intensivePolicyLevel);
        $scopeBudget = alignBudgetsToScope($allocation['budgets'], $scope, $availability, $windowSeconds);
        $intensiveChecks = buildIntensiveChecks($repositoryRoot, $intensivePolicyLevel, $scopeBudget['budgets']);

        foreach ($scopeBudget['budgets'] as $key => $seconds) {
            $distribution[] = sprintf('%s=%ds', $key, $seconds);
        }

        $notes = array_merge($notes, $weightPlan['notes'], $allocation['notes'], $scopeBudget['notes']);
    } else {
        $notes[] = 'Fase intensiva deshabilitada para esta ejecucion.';
    }

    $allChecks = array_merge($baseChecks, $intensiveChecks);
    $filtered = filterChecksByScope($allChecks, $scope);
    $notes = array_merge($notes, $filtered['notes']);

    $scopeSpecificChecks = (int) $filtered['specific_checks'];
    $scopeAvailabilityNotes = buildScopeAvailabilityNotes($scope, $availability, $scopeSpecificChecks);
    $notes = array_merge($notes, $scopeAvailabilityNotes);

    if ($scope === 'toda-app') {
        if (!$availability['anecaUnit'] && !$availability['anecaAggressive']) {
            $notes[] = 'Bloque ANECA/evaluador no disponible en esta copia del repositorio.';
        }

        if (!$availability['frontendPath']) {
            $notes[] = 'Bloque frontend sin ruta esperada (acelerador_panel/frontend) o sin bateria definida.';
        }
    }

    return [
        'checks' => $filtered['checks'],
        'plan' => [
            'nivel' => $nivel,
            'ventana' => $ventana,
            'scope' => $scope,
            'outputMode' => $outputMode,
            'intensiveMode' => $intensiveMode,
            'intensiveEnabled' => $intensiveEnabled,
            'windowSeconds' => $windowSeconds,
            'intensiveBudgetSeconds' => $intensiveBudgetSeconds,
            'distribution' => $distribution,
            'redistributions' => $notes,
            'availability' => $availability,
            'filteredOutByScope' => $filtered['filtered_out'],
            'scopeSpecificChecks' => $scopeSpecificChecks,
        ],
    ];
}

/**
 * @param array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int, scopes?: array<int,string>}> $checks
 * @return array{
 *   checks: array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int, scopes?: array<int,string>}>,
 *   notes: array<int,string>,
 *   filtered_out: int,
 *   specific_checks: int
 * }
 */
function filterChecksByScope(array $checks, string $scope): array
{
    if ($scope === 'toda-app') {
        $specific = 0;
        foreach ($checks as $check) {
            $scopes = isset($check['scopes']) && is_array($check['scopes']) ? $check['scopes'] : ['global'];
            if (!in_array('global', $scopes, true)) {
                $specific++;
            }
        }

        return [
            'checks' => $checks,
            'notes' => [],
            'filtered_out' => 0,
            'specific_checks' => $specific,
        ];
    }

    $filtered = [];
    $filteredOut = 0;
    $specificChecks = 0;

    foreach ($checks as $check) {
        $scopes = isset($check['scopes']) && is_array($check['scopes']) ? $check['scopes'] : ['global'];
        $matchesSpecificScope = in_array($scope, $scopes, true);
        $isGlobal = in_array('global', $scopes, true);

        if ($matchesSpecificScope || $isGlobal) {
            $filtered[] = $check;
            if ($matchesSpecificScope) {
                $specificChecks++;
            }
            continue;
        }

        $filteredOut++;
    }

    $notes = [];
    if ($specificChecks === 0) {
        $notes[] = sprintf('No se detectaron checks especificos para el alcance %s.', $scope);
    }

    if ($filteredOut > 0) {
        $notes[] = sprintf('Scope aplicado (%s): %d checks filtrados por alcance.', $scope, $filteredOut);
    }

    return [
        'checks' => $filtered,
        'notes' => $notes,
        'filtered_out' => $filteredOut,
        'specific_checks' => $specificChecks,
    ];
}

/**
 * @param array{
 *   anecaAggressive: bool,
 *   backendAggressive: bool,
 *   mcpWorker: bool,
 *   anecaUnit: bool,
 *   backendSmoke: bool,
 *   mcpUnit: bool,
 *   contractDoc: bool,
 *   contractRoute: bool,
 *   frontendPath: bool
 * } $availability
 * @return array<int,string>
 */
function buildScopeAvailabilityNotes(string $scope, array $availability, int $specificChecks): array
{
    $notes = [];

    if ($scope === 'backend' && !$availability['backendSmoke']) {
        $notes[] = 'Alcance backend sin smoke disponible (run_usecases_smoke.php no encontrado).';
    }

    if ($scope === 'mcp' && !$availability['mcpUnit'] && !$availability['mcpWorker']) {
        $notes[] = 'Alcance MCP sin pruebas disponibles (unit_extract_pdf y worker_jobs ausentes).';
    }

    if (($scope === 'aneca' || $scope === 'evaluador') && !$availability['anecaUnit'] && !$availability['anecaAggressive']) {
        $notes[] = 'Alcance ANECA/evaluador sin baterias detectadas.';
    }

    if ($scope === 'contratos' && (!$availability['contractDoc'] || !$availability['contractRoute'])) {
        $notes[] = 'Alcance contratos incompleto: falta documentacion o rutas backend para validar contrato.';
    }

    if ($scope === 'frontend') {
        if (!$availability['frontendPath']) {
            $notes[] = 'Alcance frontend sin ruta estandar detectada (acelerador_panel/frontend).';
        }

        if ($specificChecks === 0) {
            $notes[] = 'Alcance frontend sin checks automatizados definidos en esta skill.';
        }
    }

    return $notes;
}

/**
 * @param array<string,int> $budgets
 * @param array{
 *   anecaAggressive: bool,
 *   backendAggressive: bool,
 *   mcpWorker: bool,
 *   anecaUnit: bool,
 *   backendSmoke: bool,
 *   mcpUnit: bool,
 *   contractDoc: bool,
 *   contractRoute: bool,
 *   frontendPath: bool
 * } $availability
 * @return array{budgets: array<string,int>, notes: array<int,string>}
 */
function alignBudgetsToScope(array $budgets, string $scope, array $availability, int $windowSeconds): array
{
    if ($scope === 'toda-app') {
        return ['budgets' => $budgets, 'notes' => []];
    }

    $targetByScope = [
        'backend' => 'backend',
        'aneca' => 'aneca',
        'evaluador' => 'aneca',
        'mcp' => 'mcp',
    ];

    if (!isset($targetByScope[$scope])) {
        if ($budgets !== []) {
            return [
                'budgets' => [],
                'notes' => [sprintf('Alcance %s no usa fase intensiva dedicada; se omiten bloques intensivos.', $scope)],
            ];
        }

        return ['budgets' => [], 'notes' => []];
    }

    $target = $targetByScope[$scope];
    $notes = [];
    $filtered = [];

    foreach ($budgets as $key => $seconds) {
        if ($key === $target) {
            $filtered[$key] = $seconds;
        } else {
            $notes[] = sprintf('Redistribucion por alcance: bloque intensivo %s omitido (scope=%s).', $key, $scope);
        }
    }

    if ($filtered === []) {
        $isAvailable = match ($target) {
            'backend' => $availability['backendAggressive'],
            'aneca' => $availability['anecaAggressive'],
            'mcp' => $availability['mcpWorker'],
            default => false,
        };

        if ($isAvailable && $windowSeconds > 0) {
            $filtered[$target] = $windowSeconds;
            $notes[] = sprintf(
                'Se reasigna el 100%% del presupuesto intensivo al bloque %s por alcance %s.',
                $target,
                $scope
            );
        } else {
            $notes[] = sprintf(
                'Sin bloque intensivo disponible para alcance %s (objetivo=%s).',
                $scope,
                $target
            );
        }
    } else {
        $allocated = array_sum($filtered);
        if ($allocated < $windowSeconds && $windowSeconds > 0) {
            $remainder = $windowSeconds - $allocated;
            $filtered[$target] += $remainder;
            $notes[] = sprintf(
                'Resto de %ds reasignado al bloque %s para respetar alcance %s.',
                $remainder,
                $target,
                $scope
            );
        }
    }

    return [
        'budgets' => $filtered,
        'notes' => $notes,
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
 * @param array<int, array{id: string, name: string, command: string, optional: bool, budget_seconds?: int, category?: string, strategy?: string, runner?: string, loop_seconds?: int, loop_pause_ms?: int, scopes?: array<int,string>}> $checks
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
 *   checks: array<int, array<string, mixed>>,
 *   noVerificable: int,
 *   checkStats: array<string,mixed>,
 *   executionTimeMs: int,
 *   executionPlan: array<string,mixed>
 * }
 */
function executeChecks(array $checks, string $workingDirectory, string $suiteName, bool $dryRun, array $plan): array
{
    $suiteStart = microtime(true);
    $checkResults = [];
    $errors = [];
    $mandatoryTotal = 0;
    $passed = 0;
    $failed = 0;
    $noVerificable = 0;
    $statusCounts = [
        'passed' => 0,
        'failed' => 0,
        'no_verificable' => 0,
        'dry_run' => 0,
    ];
    $categoryCounts = [];

    foreach ($checks as $check) {
        $checkStart = microtime(true);

        if ($dryRun) {
            $statusCounts['dry_run']++;
            $category = (string) ($check['category'] ?? 'base');
            if (!isset($categoryCounts[$category])) {
                $categoryCounts[$category] = 0;
            }
            $categoryCounts[$category]++;

            $checkResults[] = [
                'id' => $check['id'],
                'name' => $check['name'],
                'command' => $check['command'],
                'optional' => $check['optional'],
                'status' => 'dry_run',
                'exitCode' => 0,
                'output' => 'Dry run habilitado. No se ejecutaron comandos reales.',
                'category' => $category,
                'strategy' => $check['strategy'] ?? 'single',
                'budgetSeconds' => (int) ($check['budget_seconds'] ?? 0),
                'scopes' => $check['scopes'] ?? [],
                'durationMs' => 0,
            ];
            continue;
        }

        $execution = ($check['runner'] ?? '') === 'mcp_loop'
            ? runTimedLoopCheck($check, $workingDirectory)
            : runCommand($check['command'], $workingDirectory);

        $durationMs = (int) round((microtime(true) - $checkStart) * 1000);
        $success = $execution['exit_code'] === 0;

        $status = 'passed';
        if (!$success && $check['optional']) {
            $status = 'no_verificable';
            $noVerificable++;
        } elseif (!$success) {
            $status = 'failed';
        }

        if (isset($statusCounts[$status])) {
            $statusCounts[$status]++;
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

        $category = (string) ($check['category'] ?? 'base');
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = 0;
        }
        $categoryCounts[$category]++;

        $checkResults[] = [
            'id' => $check['id'],
            'name' => $check['name'],
            'command' => $check['command'],
            'optional' => $check['optional'],
            'status' => $status,
            'exitCode' => $execution['exit_code'],
            'output' => trim($execution['stdout'] . "\n" . $execution['stderr']),
            'category' => $category,
            'strategy' => $check['strategy'] ?? 'single',
            'budgetSeconds' => (int) ($check['budget_seconds'] ?? 0),
            'scopes' => $check['scopes'] ?? [],
            'durationMs' => $durationMs,
        ];
    }

    $executionTimeMs = (int) round((microtime(true) - $suiteStart) * 1000);

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
        ? 'sin observaciones adicionales'
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
            'Nivel=%s; Ventana=%s; Alcance=%s; Salida=%s; FaseIntensiva=%s; Presupuesto intensivo=%ds; Distribucion=%s; No verificables=%d; %s.',
            (string) $plan['nivel'],
            (string) $plan['ventana'],
            (string) ($plan['scope'] ?? 'toda-app'),
            (string) ($plan['outputMode'] ?? 'console'),
            (string) ($plan['intensiveMode'] ?? 'auto'),
            (int) $plan['intensiveBudgetSeconds'],
            $distribution,
            $noVerificable,
            $redistributionNote
        ),
        'checks' => $checkResults,
        'noVerificable' => $noVerificable,
        'checkStats' => [
            'totalChecks' => count($checkResults),
            'mandatoryTotal' => $mandatoryTotal,
            'mandatoryPassed' => $passed,
            'mandatoryFailed' => $failed,
            'statusCounts' => $statusCounts,
            'categoryCounts' => $categoryCounts,
            'filteredOutByScope' => (int) ($plan['filteredOutByScope'] ?? 0),
            'scopeSpecificChecks' => (int) ($plan['scopeSpecificChecks'] ?? 0),
        ],
        'executionTimeMs' => $executionTimeMs,
        'executionPlan' => [
            'nivel' => (string) $plan['nivel'],
            'ventana' => (string) $plan['ventana'],
            'scope' => (string) ($plan['scope'] ?? 'toda-app'),
            'outputMode' => (string) ($plan['outputMode'] ?? 'console'),
            'intensiveMode' => (string) ($plan['intensiveMode'] ?? 'auto'),
            'intensiveEnabled' => (bool) ($plan['intensiveEnabled'] ?? false),
            'intensiveBudgetSeconds' => (int) ($plan['intensiveBudgetSeconds'] ?? 0),
            'distribution' => $plan['distribution'] ?? [],
            'availability' => $plan['availability'] ?? [],
            'notes' => $plan['redistributions'] ?? [],
        ],
    ];
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

function normalizeToken(string $value): string
{
    $normalized = toLower(trim($value));
    $normalized = str_replace(
        ['á', 'à', 'ä', 'é', 'è', 'ë', 'í', 'ì', 'ï', 'ó', 'ò', 'ö', 'ú', 'ù', 'ü'],
        ['a', 'a', 'a', 'e', 'e', 'e', 'i', 'i', 'i', 'o', 'o', 'o', 'u', 'u', 'u'],
        $normalized
    );
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

    return trim($normalized);
}

/**
 * @param array{
 *   nivel: ?string,
 *   ventana: ?string,
 *   scope: ?string,
 *   output_mode: string,
 *   intensive_mode: string,
 *   json: bool,
 *   dry_run: bool,
 *   help: bool,
 *   interactive: bool,
 *   auto_confirm: bool,
 *   provided: array{
 *     nivel: bool,
 *     ventana: bool,
 *     scope: bool,
 *     output_mode: bool,
 *     intensive_mode: bool
 *   }
 * } $options
 * @return array{
 *   nivel: ?string,
 *   ventana: ?string,
 *   scope: ?string,
 *   output_mode: string,
 *   intensive_mode: string,
 *   json: bool,
 *   dry_run: bool,
 *   help: bool,
 *   interactive: bool,
 *   auto_confirm: bool,
 *   provided: array{
 *     nivel: bool,
 *     ventana: bool,
 *     scope: bool,
 *     output_mode: bool,
 *     intensive_mode: bool
 *   }
 * }
 */
function runInteractiveWizard(array $options): array
{
    fwrite(STDOUT, "Modo interactivo real de ejecutar-tests.\n");
    fwrite(STDOUT, "No se ejecutara nada hasta confirmar al final.\n\n");

    if (!$options['provided']['nivel']) {
        fwrite(STDOUT, "1) Nivel de dureza\n");
        $levelChoice = askInteractiveChoice([
            'basico' => ['label' => 'basico', 'aliases' => ['1', 'standard']],
            'medio' => ['label' => 'medio', 'aliases' => ['2']],
            'agresivo' => ['label' => 'agresivo', 'aliases' => ['3']],
            'extremo' => ['label' => 'extremo', 'aliases' => ['4']],
        ]);
        $options['nivel'] = $levelChoice;
        $options['provided']['nivel'] = true;
        fwrite(STDOUT, PHP_EOL);
    }

    if (!$options['provided']['ventana']) {
        fwrite(STDOUT, "2) Ventana de ejecucion\n");
        $windowChoice = askInteractiveChoice([
            '15m' => ['label' => '15m', 'aliases' => ['1']],
            '30m' => ['label' => '30m', 'aliases' => ['2']],
            '45m' => ['label' => '45m', 'aliases' => ['3']],
            '1h' => ['label' => '1h', 'aliases' => ['4']],
            '6h' => ['label' => '6h', 'aliases' => ['5']],
            'custom' => ['label' => 'personalizado', 'aliases' => ['6', 'personalizado']],
        ]);

        if ($windowChoice === 'custom') {
            $options['ventana'] = askCustomWindow();
        } else {
            $options['ventana'] = $windowChoice;
        }
        $options['provided']['ventana'] = true;
        fwrite(STDOUT, PHP_EOL);
    }

    if (!$options['provided']['scope']) {
        fwrite(STDOUT, "3) Alcance\n");
        $scopeChoice = askInteractiveChoice([
            'backend' => ['label' => 'backend', 'aliases' => ['1']],
            'frontend' => ['label' => 'frontend', 'aliases' => ['2']],
            'evaluador' => ['label' => 'evaluador', 'aliases' => ['3']],
            'aneca' => ['label' => 'ANECA', 'aliases' => ['4']],
            'mcp' => ['label' => 'MCP', 'aliases' => ['5']],
            'contratos' => ['label' => 'contratos', 'aliases' => ['6']],
            'toda-app' => ['label' => 'toda la app', 'aliases' => ['7', 'toda', 'app']],
        ]);
        $options['scope'] = $scopeChoice;
        $options['provided']['scope'] = true;
        fwrite(STDOUT, PHP_EOL);
    }

    if (!$options['provided']['output_mode']) {
        fwrite(STDOUT, "4) Tipo de salida\n");
        $outputChoice = askInteractiveChoice([
            'console' => ['label' => 'consola legible', 'aliases' => ['1', 'consola', 'legible']],
            'json' => ['label' => 'JSON', 'aliases' => ['2']],
            'both' => ['label' => 'ambas', 'aliases' => ['3', 'ambas']],
        ]);
        $options['output_mode'] = $outputChoice;
        $options['provided']['output_mode'] = true;
        fwrite(STDOUT, PHP_EOL);
    }

    if (!$options['provided']['intensive_mode']) {
        fwrite(STDOUT, "5) Fase intensiva\n");
        $intensiveChoice = askInteractiveChoice([
            'si' => ['label' => 'si', 'aliases' => ['1', 's', 'yes']],
            'no' => ['label' => 'no', 'aliases' => ['2', 'n']],
            'auto' => ['label' => 'automatica segun nivel', 'aliases' => ['3', 'auto']],
        ]);
        $options['intensive_mode'] = $intensiveChoice;
        $options['provided']['intensive_mode'] = true;
        fwrite(STDOUT, PHP_EOL);
    }

    $resolvedLevel = normalizeLevel((string) $options['nivel']);
    $resolvedWindow = normalizeWindow((string) $options['ventana']);
    $resolvedScope = normalizeScope((string) $options['scope']);
    $resolvedOutput = normalizeOutputMode((string) $options['output_mode']);
    $resolvedIntensive = normalizeIntensiveMode((string) $options['intensive_mode']);

    fwrite(STDOUT, "6) Confirmacion final\n");
    fwrite(STDOUT, 'Nivel seleccionado: ' . labelForLevel($resolvedLevel) . PHP_EOL);
    fwrite(STDOUT, 'Ventana seleccionada: ' . $resolvedWindow . PHP_EOL);
    fwrite(STDOUT, 'Alcance: ' . labelForScope($resolvedScope) . PHP_EOL);
    fwrite(STDOUT, 'Salida: ' . labelForOutputMode($resolvedOutput) . PHP_EOL);
    fwrite(STDOUT, 'Fase intensiva: ' . labelForIntensiveMode($resolvedIntensive) . PHP_EOL);

    if (!$options['auto_confirm']) {
        if (!askYesNo('¿Ejecutar ahora? si/no: ')) {
            throw new RuntimeException('Ejecucion cancelada por el usuario en modo interactivo.');
        }
    }

    $options['nivel'] = $resolvedLevel;
    $options['ventana'] = $resolvedWindow;
    $options['scope'] = $resolvedScope;
    $options['output_mode'] = $resolvedOutput;
    $options['intensive_mode'] = $resolvedIntensive;
    $options['json'] = $resolvedOutput === 'json' || $resolvedOutput === 'both';

    return $options;
}

/**
 * @param array<string, array{label: string, aliases: array<int,string>}> $choices
 */
function askInteractiveChoice(array $choices): string
{
    $orderedKeys = array_keys($choices);
    foreach ($orderedKeys as $index => $key) {
        $number = $index + 1;
        fwrite(STDOUT, sprintf("  %d) %s\n", $number, $choices[$key]['label']));
    }

    while (true) {
        fwrite(STDOUT, '> ');
        $rawInput = fgets(STDIN);
        if ($rawInput === false) {
            throw new RuntimeException('No se pudo leer entrada interactiva.');
        }

        $input = normalizeToken($rawInput);
        if ($input === '') {
            fwrite(STDOUT, "Valor vacio. Intenta de nuevo.\n");
            continue;
        }

        if (preg_match('/^[1-9][0-9]*$/', $input)) {
            $position = (int) $input - 1;
            if (isset($orderedKeys[$position])) {
                return $orderedKeys[$position];
            }
        }

        foreach ($choices as $key => $definition) {
            if ($input === normalizeToken($key) || $input === normalizeToken($definition['label'])) {
                return $key;
            }

            foreach ($definition['aliases'] as $alias) {
                if ($input === normalizeToken($alias)) {
                    return $key;
                }
            }
        }

        fwrite(STDOUT, "Opcion invalida. Intenta de nuevo.\n");
    }
}

function askCustomWindow(): string
{
    while (true) {
        fwrite(STDOUT, "Indica la ventana personalizada (ej: 90m o 2h): ");
        $rawInput = fgets(STDIN);
        if ($rawInput === false) {
            throw new RuntimeException('No se pudo leer ventana personalizada.');
        }

        $candidate = trim($rawInput);
        try {
            return normalizeWindow($candidate);
        } catch (InvalidArgumentException $exception) {
            fwrite(STDOUT, $exception->getMessage() . PHP_EOL);
        }
    }
}

function askYesNo(string $prompt): bool
{
    while (true) {
        fwrite(STDOUT, $prompt);
        $rawInput = fgets(STDIN);
        if ($rawInput === false) {
            throw new RuntimeException('No se pudo leer confirmacion de ejecucion.');
        }

        $input = normalizeToken($rawInput);
        if (in_array($input, ['s', 'si', 'y', 'yes'], true)) {
            return true;
        }

        if (in_array($input, ['n', 'no'], true)) {
            return false;
        }

        fwrite(STDOUT, "Respuesta invalida. Usa si o no.\n");
    }
}

function labelForLevel(string $level): string
{
    return match ($level) {
        'standard' => 'basico',
        'medio' => 'medio',
        'agresivo' => 'agresivo',
        'extremo' => 'extremo',
        default => $level,
    };
}

function labelForScope(string $scope): string
{
    return match ($scope) {
        'toda-app' => 'toda la app',
        default => $scope,
    };
}

function labelForOutputMode(string $outputMode): string
{
    return match ($outputMode) {
        'console' => 'consola legible',
        'json' => 'JSON',
        'both' => 'consola legible + JSON',
        default => $outputMode,
    };
}

function labelForIntensiveMode(string $mode): string
{
    return match ($mode) {
        'si' => 'si',
        'no' => 'no',
        'auto' => 'automatica segun nivel',
        default => $mode,
    };
}

function formatDurationMs(int $durationMs): string
{
    if ($durationMs < 1000) {
        return sprintf('%dms', $durationMs);
    }

    return sprintf('%.2fs', $durationMs / 1000);
}

/**
 * @param array<string,mixed> $result
 */
function printTechnicalReport(array $result): void
{
    $plan = isset($result['executionPlan']) && is_array($result['executionPlan']) ? $result['executionPlan'] : [];
    $stats = isset($result['checkStats']) && is_array($result['checkStats']) ? $result['checkStats'] : [];
    $checks = isset($result['checks']) && is_array($result['checks']) ? $result['checks'] : [];
    $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [];
    $notes = isset($plan['notes']) && is_array($plan['notes']) ? $plan['notes'] : [];
    $availability = isset($plan['availability']) && is_array($plan['availability']) ? $plan['availability'] : [];

    fwrite(STDOUT, "Mini-acta tecnica de ejecucion\n");
    fwrite(STDOUT, "================================\n");
    fwrite(STDOUT, 'Suite: ' . (string) ($result['suiteName'] ?? 'ejecutar-tests') . PHP_EOL);
    fwrite(STDOUT, 'Timestamp: ' . (string) ($result['timestamp'] ?? date('Y-m-d H:i:s')) . PHP_EOL);
    fwrite(STDOUT, 'Duracion total: ' . formatDurationMs((int) ($result['executionTimeMs'] ?? 0)) . PHP_EOL);
    fwrite(STDOUT, 'Resumen: ' . (string) ($result['summary'] ?? '') . PHP_EOL);
    fwrite(STDOUT, PHP_EOL);

    fwrite(STDOUT, "Configuracion\n");
    fwrite(STDOUT, "-------------\n");
    fwrite(STDOUT, 'Nivel: ' . labelForLevel((string) ($plan['nivel'] ?? 'standard')) . PHP_EOL);
    fwrite(STDOUT, 'Ventana: ' . (string) ($plan['ventana'] ?? '15m') . PHP_EOL);
    fwrite(STDOUT, 'Alcance: ' . labelForScope((string) ($plan['scope'] ?? 'toda-app')) . PHP_EOL);
    fwrite(STDOUT, 'Salida: ' . labelForOutputMode((string) ($plan['outputMode'] ?? 'console')) . PHP_EOL);
    fwrite(STDOUT, 'Fase intensiva: ' . labelForIntensiveMode((string) ($plan['intensiveMode'] ?? 'auto')) . PHP_EOL);
    fwrite(STDOUT, 'Presupuesto intensivo: ' . (int) ($plan['intensiveBudgetSeconds'] ?? 0) . "s\n");
    $distribution = isset($plan['distribution']) && is_array($plan['distribution']) && $plan['distribution'] !== []
        ? implode(', ', $plan['distribution'])
        : 'sin fase intensiva';
    fwrite(STDOUT, 'Distribucion: ' . $distribution . PHP_EOL);
    fwrite(STDOUT, PHP_EOL);

    fwrite(STDOUT, "Disponibilidad detectada\n");
    fwrite(STDOUT, "------------------------\n");
    foreach ($availability as $key => $value) {
        $flag = $value ? 'si' : 'no';
        fwrite(STDOUT, sprintf("- %s: %s\n", (string) $key, $flag));
    }
    if ($availability === []) {
        fwrite(STDOUT, "- sin datos de disponibilidad\n");
    }
    fwrite(STDOUT, PHP_EOL);

    fwrite(STDOUT, "Metricas\n");
    fwrite(STDOUT, "--------\n");
    fwrite(STDOUT, 'Obligatorias: '
        . (int) ($result['passed'] ?? 0) . '/' . (int) ($result['total'] ?? 0)
        . ' superadas'
        . PHP_EOL
    );
    fwrite(STDOUT, 'No verificables: ' . (int) ($result['noVerificable'] ?? 0) . PHP_EOL);
    fwrite(STDOUT, 'Checks totales ejecutados: ' . (int) ($stats['totalChecks'] ?? 0) . PHP_EOL);
    if (isset($stats['statusCounts']) && is_array($stats['statusCounts'])) {
        fwrite(STDOUT, 'Estado checks: ' . json_encode($stats['statusCounts'], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }
    if (isset($stats['categoryCounts']) && is_array($stats['categoryCounts'])) {
        fwrite(STDOUT, 'Categorias: ' . json_encode($stats['categoryCounts'], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }
    fwrite(STDOUT, PHP_EOL);

    fwrite(STDOUT, "Checks detallados\n");
    fwrite(STDOUT, "-----------------\n");
    foreach ($checks as $check) {
        $status = (string) ($check['status'] ?? 'unknown');
        $duration = formatDurationMs((int) ($check['durationMs'] ?? 0));
        $optional = ((bool) ($check['optional'] ?? false)) ? 'opcional' : 'obligatorio';
        $category = (string) ($check['category'] ?? 'base');
        $line = sprintf(
            "- [%s] %s | %s | %s | %s | %s",
            (string) ($check['id'] ?? '?'),
            (string) ($check['name'] ?? ''),
            $status,
            $optional,
            $category,
            $duration
        );
        fwrite(STDOUT, $line . PHP_EOL);

        $snippet = firstLine((string) ($check['output'] ?? ''));
        if ($snippet !== '') {
            fwrite(STDOUT, '  salida: ' . $snippet . PHP_EOL);
        }
    }
    if ($checks === []) {
        fwrite(STDOUT, "- no se ejecutaron checks\n");
    }
    fwrite(STDOUT, PHP_EOL);

    if ($notes !== []) {
        fwrite(STDOUT, "Observaciones y notas\n");
        fwrite(STDOUT, "---------------------\n");
        foreach ($notes as $note) {
            fwrite(STDOUT, '- ' . (string) $note . PHP_EOL);
        }
        fwrite(STDOUT, PHP_EOL);
    }

    if ($errors !== []) {
        fwrite(STDOUT, "Errores detectados\n");
        fwrite(STDOUT, "------------------\n");
        foreach ($errors as $error) {
            fwrite(STDOUT, '- ' . (string) $error . PHP_EOL);
        }
        fwrite(STDOUT, PHP_EOL);
    }
}

function printUsage(): void
{
    $usage = <<<TXT
Uso:
  php .agents/skills/ejecutar-tests/scripts/ejecutar_tests.php [opciones]

Opciones:
  --interactive
  --nivel=basico|medio|agresivo|extremo
  --ventana=15m|30m|45m|1h|6h|12h|24h|<custom> (ej: 90m, 2h)
  --scope=backend|frontend|evaluador|aneca|mcp|contratos|toda-app
  --output=console|json|both
  --json (atajo para --output=json)
  --both (atajo para --output=both)
  --intensiva=auto|si|no
  --fase-intensiva=auto|si|no
  --yes (omite confirmacion final en interactivo)
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

        $options = resolveExecutionOptions($options);

        $repositoryRoot = dirname(__DIR__, 4);
        $suiteName = sprintf('ejecutar-tests:%s-%s', (string) $options['nivel'], (string) $options['ventana']);
        if ((string) $options['scope'] !== 'toda-app') {
            $suiteName .= ':' . (string) $options['scope'];
        }

        $plan = buildChecks(
            $repositoryRoot,
            (string) $options['nivel'],
            (string) $options['ventana'],
            (string) $options['scope'],
            (string) $options['intensive_mode'],
            (string) $options['output_mode']
        );

        $results = executeChecks($plan['checks'], $repositoryRoot, $suiteName, (bool) $options['dry_run'], $plan['plan']);
        $results['nivel'] = $options['nivel'];
        $results['ventana'] = $options['ventana'];
        $results['scope'] = $options['scope'];
        $results['outputMode'] = $options['output_mode'];
        $results['intensiveMode'] = $options['intensive_mode'];

        $outputMode = (string) $options['output_mode'];
        if ($outputMode === 'console' || $outputMode === 'both') {
            printTechnicalReport($results);
        }

        if ($outputMode === 'both') {
            fwrite(STDOUT, "JSON\n");
            fwrite(STDOUT, "----\n");
        }

        if ($outputMode === 'json' || $outputMode === 'both') {
            fwrite(STDOUT, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        }

        return 0;
    } catch (Throwable $exception) {
        if (isInteractiveTerminal()) {
            fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
        }

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
