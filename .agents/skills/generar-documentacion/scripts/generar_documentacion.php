<?php
declare(strict_types=1);

require_once __DIR__ . '/src/inputHandler.php';
require_once __DIR__ . '/src/dateService.php';
require_once __DIR__ . '/src/fileService.php';
require_once __DIR__ . '/src/mergeService.php';
require_once __DIR__ . '/src/documentBuilder.php';
require_once __DIR__ . '/src/formatter.php';
require_once __DIR__ . '/src/sessionContextService.php';
require_once __DIR__ . '/src/testExecutionService.php';
require_once __DIR__ . '/src/validationService.php';

use function GenerarDocumentacion\DateService\getTodayDate as serviceGetTodayDate;
use function GenerarDocumentacion\DocumentBuilder\buildSectionMap;
use function GenerarDocumentacion\DocumentBuilder\createDocumentState;
use function GenerarDocumentacion\DocumentBuilder\dailyDefinition;
use function GenerarDocumentacion\DocumentBuilder\technicalDefinition;
use function GenerarDocumentacion\FileService\buildDailyFilePath;
use function GenerarDocumentacion\FileService\readIfExists as serviceReadIfExists;
use function GenerarDocumentacion\FileService\resolveOutputDir;
use function GenerarDocumentacion\FileService\writeFile as serviceWriteFile;
use function GenerarDocumentacion\Formatter\formatDocument;
use function GenerarDocumentacion\InputHandler\normalizeUpdates;
use function GenerarDocumentacion\InputHandler\resolveAuthor as serviceResolveAuthor;
use function GenerarDocumentacion\InputHandler\resolveRole as serviceResolveRole;
use function GenerarDocumentacion\InputHandler\validateHasNewData;
use function GenerarDocumentacion\MergeService\mergeContent as serviceMergeContent;
use function GenerarDocumentacion\SessionContextService\buildAutomaticUpdates;
use function GenerarDocumentacion\TestExecutionService\normalizeTestResponse;
use function GenerarDocumentacion\TestExecutionService\runTestsIfRequested;
use function GenerarDocumentacion\TestExecutionService\shouldExecuteTestsFromAnswer;
use function GenerarDocumentacion\ValidationService\getLatestValidationSnapshot;
use function GenerarDocumentacion\ValidationService\updateValidationSection;

/**
 * @param array<string, mixed> $payload
 */
function createOrUpdateTechnicalDoc(
    array $payload,
    string $date,
    string $author,
    string $role,
    string $outputDir
): string {
    $definition = technicalDefinition();
    $sectionMap = buildSectionMap($definition['sections']);
    $updates = normalizeUpdates($payload['technical_updates'] ?? [], $sectionMap);

    $state = createDocumentState($definition, $date, $author, $role, $updates);
    $path = buildDailyFilePath($outputDir, $definition['file_prefix'], $date);

    $existingContent = readIfExists($path);
    $mergedState = mergeContent($existingContent, $state, $definition, $author, $role);
    $formatted = formatDocument($definition, $mergedState);

    writeFile($path, $formatted);
    return $path;
}

/**
 * @param array<string, mixed> $payload
 */
function createOrUpdateDailyLog(
    array $payload,
    string $date,
    string $author,
    string $role,
    string $outputDir
): string {
    $definition = dailyDefinition();
    $sectionMap = buildSectionMap($definition['sections']);
    $updates = normalizeUpdates($payload['daily_updates'] ?? [], $sectionMap);

    $state = createDocumentState($definition, $date, $author, $role, $updates);
    $path = buildDailyFilePath($outputDir, $definition['file_prefix'], $date);

    $existingContent = readIfExists($path);
    $mergedState = mergeContent($existingContent, $state, $definition, $author, $role);
    $formatted = formatDocument($definition, $mergedState);

    writeFile($path, $formatted);
    return $path;
}

function resolveAuthor(?string $author): string
{
    return serviceResolveAuthor($author);
}

function resolveRole(string $author, ?string $role): string
{
    return serviceResolveRole($author, $role);
}

function getTodayDate(): string
{
    return serviceGetTodayDate('Europe/Madrid');
}

function readIfExists(string $path): ?string
{
    return serviceReadIfExists($path);
}

/**
 * @param array{
 *   meta: array{project: string, document: string, date: string, author: string, role: string, status: string},
 *   sections: array<string, array<int, string>>
 * } $incomingState
 * @param array{title: string, sections: array<int, string>, replace_sections?: array<int, string>} $definition
 * @return array{
 *   meta: array{project: string, document: string, date: string, author: string, role: string, status: string},
 *   sections: array<string, array<int, string>>
 * }
 */
function mergeContent(
    ?string $existingContent,
    array $incomingState,
    array $definition,
    string $author,
    string $role
): array {
    return serviceMergeContent($existingContent, $incomingState, $definition, $author, $role);
}

function writeFile(string $path, string $content): void
{
    serviceWriteFile($path, $content);
}

/**
 * @return array{
 *   payload_json: ?string,
 *   payload_file: ?string,
 *   stdin: bool,
 *   help: bool,
 *   non_interactive: bool
 * }
 */
function parseArguments(array $argv): array
{
    $parsed = [
        'payload_json' => null,
        'payload_file' => null,
        'stdin' => false,
        'help' => false,
        'non_interactive' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $argument = $argv[$i];

        if ($argument === '--help' || $argument === '-h') {
            $parsed['help'] = true;
            continue;
        }

        if ($argument === '--stdin') {
            $parsed['stdin'] = true;
            continue;
        }

        if ($argument === '--non-interactive') {
            $parsed['non_interactive'] = true;
            continue;
        }

        if ($argument === '--payload-json') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException('Falta el valor para --payload-json.');
            }
            $parsed['payload_json'] = $argv[++$i];
            continue;
        }

        if (str_starts_with($argument, '--payload-json=')) {
            $parsed['payload_json'] = substr($argument, strlen('--payload-json='));
            continue;
        }

        if ($argument === '--payload-file') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException('Falta el valor para --payload-file.');
            }
            $parsed['payload_file'] = $argv[++$i];
            continue;
        }

        if (str_starts_with($argument, '--payload-file=')) {
            $parsed['payload_file'] = substr($argument, strlen('--payload-file='));
            continue;
        }

        throw new InvalidArgumentException(sprintf('Argumento no reconocido: %s', $argument));
    }

    return $parsed;
}

/**
 * @param array{
 *   payload_json: ?string,
 *   payload_file: ?string,
 *   stdin: bool,
 *   help: bool,
 *   non_interactive: bool
 * } $parsedArgs
 */
function isStructuredMode(array $parsedArgs): bool
{
    return $parsedArgs['payload_json'] !== null
        || $parsedArgs['payload_file'] !== null
        || $parsedArgs['stdin'];
}

/**
 * @param array{
 *   payload_json: ?string,
 *   payload_file: ?string,
 *   stdin: bool,
 *   help: bool,
 *   non_interactive: bool
 * } $parsedArgs
 * @return array<string, mixed>
 */
function loadStructuredPayload(array $parsedArgs): array
{
    $modes = 0;
    $modes += $parsedArgs['payload_json'] !== null ? 1 : 0;
    $modes += $parsedArgs['payload_file'] !== null ? 1 : 0;
    $modes += $parsedArgs['stdin'] ? 1 : 0;

    if ($modes > 1) {
        throw new InvalidArgumentException(
            'Usa solo un modo de entrada: --payload-json, --payload-file o --stdin.'
        );
    }

    if ($modes === 0) {
        return [];
    }

    if ($parsedArgs['payload_json'] !== null) {
        return decodeJsonPayload($parsedArgs['payload_json'], 'argumento --payload-json');
    }

    if ($parsedArgs['payload_file'] !== null) {
        $path = $parsedArgs['payload_file'];
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException(sprintf('No fue posible leer el archivo de payload: %s', $path));
        }
        return decodeJsonPayload($json, sprintf('archivo %s', $path));
    }

    $json = stream_get_contents(STDIN);
    if ($json === false) {
        throw new RuntimeException('No fue posible leer payload desde STDIN.');
    }
    return decodeJsonPayload($json, 'STDIN');
}

/**
 * @param array<string, mixed> $payload
 * @return array{author: string, role: string}
 */
function resolveAuthorAndRole(array $payload, bool $interactiveMode): array
{
    if ($interactiveMode) {
        $authorInput = prompt('Autor de la documentación [Basilio Lagares]:');
        $author = resolveAuthor($authorInput === '' ? null : $authorInput);

        if ($author === 'Basilio Lagares') {
            $roleInput = prompt('Rol [Desarrollo backend]:');
        } else {
            $roleInput = '';
            while ($roleInput === '') {
                $roleInput = prompt('Indica el rol del autor:');
                if ($roleInput === '') {
                    fwrite(STDERR, "El rol es obligatorio para autores distintos de Basilio Lagares.\n");
                }
            }
        }

        $role = resolveRole($author, $roleInput);
        return ['author' => $author, 'role' => $role];
    }

    $author = resolveAuthor(isset($payload['author']) ? (string) $payload['author'] : null);
    $role = resolveRole($author, isset($payload['role']) ? (string) $payload['role'] : null);
    return ['author' => $author, 'role' => $role];
}

function prompt(string $message): string
{
    fwrite(STDOUT, $message . ' ');
    $line = fgets(STDIN);
    if ($line === false) {
        throw new RuntimeException(
            'No fue posible leer entrada interactiva. Usa --non-interactive con payload explícito si ejecutas en modo automatizado.'
        );
    }
    return trim($line);
}

/**
 * @return array<string, mixed>
 */
function decodeJsonPayload(string $rawJson, string $source): array
{
    $normalizedJson = trim($rawJson);
    $normalizedJson = preg_replace('/^\x{FEFF}/u', '', $normalizedJson) ?? $normalizedJson;

    try {
        $decoded = json_decode($normalizedJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $exception) {
        throw new InvalidArgumentException(
            sprintf('El payload JSON en %s no es válido: %s', $source, $exception->getMessage()),
            0,
            $exception
        );
    }

    if (!is_array($decoded)) {
        throw new InvalidArgumentException(
            sprintf('El payload JSON en %s debe ser un objeto JSON válido.', $source)
        );
    }

    return $decoded;
}

function printUsage(): void
{
    $usage = <<<TXT
Uso:
  php .agents/skills/generar-documentacion/scripts/generar_documentacion.php [modo]

Modo normal (interfaz humana, por defecto):
  Sin argumentos. Pregunta solo autor, rol y ejecución de tests.
  El contenido técnico/diario se detecta automáticamente desde el contexto de sesión.

Modo estructurado (automatización):
  --payload-json '<JSON>'
  --payload-file <ruta-json>
  --stdin
  --non-interactive (recomendado para automatización)
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

/**
 * @param array<string, mixed> $payload
 */
function promptForTestExecution(array $payload, bool $interactiveMode): bool
{
    if ($interactiveMode) {
        $answer = prompt('¿Quieres ejecutar la batería de tests ahora? [s/N]:');
        return shouldExecuteTestsFromAnswer($answer);
    }

    if (array_key_exists('run_tests', $payload)) {
        $value = $payload['run_tests'];
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null) {
            return false;
        }
        return shouldExecuteTestsFromAnswer((string) $value);
    }

    return false;
}

/**
 * @param array<string, mixed> $payload
 * @param array{
 *   suiteName: string,
 *   total: ?int,
 *   passed: ?int,
 *   failed: ?int,
 *   errors: string,
 *   summary: string,
 *   timestamp: string,
 *   observations: string
 * }|null $previousValidation
 * @return array{result: array<string, mixed>, technical_items: array<int, string>, daily_items: array<int, string>}
 */
function executeTestsAndBuildValidation(
    array $payload,
    bool $shouldRunTests,
    string $repositoryRoot,
    ?array $previousValidation
): array {
    $providedResult = isset($payload['test_results']) && is_array($payload['test_results'])
        ? $payload['test_results']
        : null;

    $options = [
        'provided_result' => $providedResult,
        'nivel' => isset($payload['test_nivel']) ? (string) $payload['test_nivel'] : 'standard',
        'ventana' => isset($payload['test_ventana']) ? (string) $payload['test_ventana'] : '15m',
    ];

    $testResult = runTestsIfRequested($shouldRunTests, $repositoryRoot, $options);
    $normalizedResult = normalizeTestResponse($testResult);

    return [
        'result' => $normalizedResult,
        'technical_items' => updateValidationSection($normalizedResult, $previousValidation, 'technical'),
        'daily_items' => updateValidationSection($normalizedResult, $previousValidation, 'daily'),
    ];
}

function main(array $argv): int
{
    try {
        $parsedArgs = parseArguments($argv);
        if ($parsedArgs['help']) {
            printUsage();
            return 0;
        }

        $structuredMode = isStructuredMode($parsedArgs);
        if ($parsedArgs['non_interactive'] && !$structuredMode) {
            throw new InvalidArgumentException(
                'El modo --non-interactive requiere payload explícito (--payload-json, --payload-file o --stdin).'
            );
        }

        $repositoryRoot = dirname(__DIR__, 4);
        $payload = $structuredMode ? loadStructuredPayload($parsedArgs) : [];
        if (!$structuredMode) {
            $automaticUpdates = buildAutomaticUpdates($repositoryRoot);
            $payload['technical_updates'] = $automaticUpdates['technical_updates'];
            $payload['daily_updates'] = $automaticUpdates['daily_updates'];
        }

        $identity = resolveAuthorAndRole($payload, !$structuredMode);
        $author = $identity['author'];
        $role = $identity['role'];
        $date = getTodayDate();
        $outputDir = resolveOutputDir(isset($payload['output_dir']) ? (string) $payload['output_dir'] : 'docs', $repositoryRoot);

        $technicalDefinition = technicalDefinition();
        $dailyDefinition = dailyDefinition();

        $technicalPath = buildDailyFilePath($outputDir, $technicalDefinition['file_prefix'], $date);
        $previousValidation = getLatestValidationSnapshot(readIfExists($technicalPath), $technicalDefinition);

        $technicalUpdates = normalizeUpdates($payload['technical_updates'] ?? [], buildSectionMap($technicalDefinition['sections']));
        $dailyUpdates = normalizeUpdates($payload['daily_updates'] ?? [], buildSectionMap($dailyDefinition['sections']));
        validateHasNewData($technicalUpdates, $dailyUpdates);

        $normalizedPayload = $payload;
        $normalizedPayload['technical_updates'] = $technicalUpdates;
        $normalizedPayload['daily_updates'] = $dailyUpdates;

        createOrUpdateTechnicalDoc($normalizedPayload, $date, $author, $role, $outputDir);
        createOrUpdateDailyLog($normalizedPayload, $date, $author, $role, $outputDir);

        $shouldRunTests = promptForTestExecution($payload, !$structuredMode);
        $validation = executeTestsAndBuildValidation($payload, $shouldRunTests, $repositoryRoot, $previousValidation);

        $technicalValidationHeading = $technicalDefinition['sections'][7];
        $dailyValidationHeading = $dailyDefinition['sections'][7];

        $normalizedPayload['technical_updates'][$technicalValidationHeading] = $validation['technical_items'];
        $normalizedPayload['daily_updates'][$dailyValidationHeading] = $validation['daily_items'];

        $technicalPath = createOrUpdateTechnicalDoc($normalizedPayload, $date, $author, $role, $outputDir);
        $dailyPath = createOrUpdateDailyLog($normalizedPayload, $date, $author, $role, $outputDir);

        fwrite(
            STDOUT,
            json_encode(
                [
                    'status' => 'ok',
                    'date' => $date,
                    'technical_doc' => $technicalPath,
                    'daily_log' => $dailyPath,
                    'tests' => $validation['result'],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ) . PHP_EOL
        );

        return 0;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
        return 1;
    }
}

if (isset($argv[0]) && realpath((string) $argv[0]) === __FILE__) {
    exit(main($argv));
}
