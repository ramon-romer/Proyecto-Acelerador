<?php
declare(strict_types=1);

const VALID_MODES = ['rapido', 'demo', 'completo'];

/**
 * @return array{
 *   modo: string,
 *   sin_tests_largos: bool,
 *   output_dir: string,
 *   help: bool
 * }
 */
function parseArguments(array $argv): array
{
    $parsed = [
        'modo' => 'rapido',
        'sin_tests_largos' => false,
        'output_dir' => 'docs/auditorias-mvp',
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $argument = (string) $argv[$i];

        if ($argument === '--help' || $argument === '-h') {
            $parsed['help'] = true;
            continue;
        }

        if ($argument === '--sin-tests-largos') {
            $parsed['sin_tests_largos'] = true;
            continue;
        }

        if ($argument === '--modo') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException('Falta valor para --modo.');
            }
            $parsed['modo'] = (string) $argv[++$i];
            continue;
        }

        if (str_starts_with($argument, '--modo=')) {
            $parsed['modo'] = substr($argument, strlen('--modo='));
            continue;
        }

        if ($argument === '--output-dir') {
            if (!isset($argv[$i + 1])) {
                throw new InvalidArgumentException('Falta valor para --output-dir.');
            }
            $parsed['output_dir'] = (string) $argv[++$i];
            continue;
        }

        if (str_starts_with($argument, '--output-dir=')) {
            $parsed['output_dir'] = substr($argument, strlen('--output-dir='));
            continue;
        }

        throw new InvalidArgumentException(sprintf('Argumento no reconocido: %s', $argument));
    }

    $parsed['modo'] = normalizeMode((string) $parsed['modo']);
    return $parsed;
}

function normalizeMode(string $mode): string
{
    $normalized = strtolower(trim($mode));
    if (!in_array($normalized, VALID_MODES, true)) {
        throw new InvalidArgumentException('Modo invalido. Usa: rapido, demo o completo.');
    }
    return $normalized;
}

function isAbsolutePath(string $path): bool
{
    return (bool) preg_match('/^[A-Za-z]:[\/\\\\]/', $path)
        || str_starts_with($path, '\\\\');
}

function resolveOutputDir(string $repositoryRoot, string $outputDir): string
{
    $candidate = trim($outputDir);
    if ($candidate === '') {
        $candidate = 'docs/auditorias-mvp';
    }

    if (!isAbsolutePath($candidate)) {
        $candidate = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
    }

    return $candidate;
}

function printUsage(): void
{
    $usage = <<<TXT
Uso:
  php .agents/skills/auditar-mvp/scripts/auditar_mvp.php [opciones]

Opciones:
  --modo=rapido|demo|completo
  --sin-tests-largos
  --output-dir=docs/auditorias-mvp
  --help
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

/**
 * @return array{status:string, output:string, exitCode:int, durationMs:int}
 */
function runCommand(string $command, string $cwd): array
{
    $originalCwd = getcwd();
    if ($originalCwd === false) {
        throw new RuntimeException('No se pudo obtener el directorio actual.');
    }

    if (!@chdir($cwd)) {
        return [
            'status' => 'WARN',
            'output' => 'No se pudo cambiar al directorio de trabajo: ' . $cwd,
            'exitCode' => 1,
            'durationMs' => 0,
        ];
    }

    $outputLines = [];
    $exitCode = 0;
    $start = microtime(true);
    exec($command . ' 2>&1', $outputLines, $exitCode);
    $durationMs = (int) round((microtime(true) - $start) * 1000);

    @chdir($originalCwd);

    $output = trim(implode(PHP_EOL, $outputLines));
    $status = $exitCode === 0 ? 'OK' : 'FAIL';

    if ($status === 'FAIL' && isLikelyUnverifiable($output)) {
        $status = 'WARN';
    }

    return [
        'status' => $status,
        'output' => $output,
        'exitCode' => $exitCode,
        'durationMs' => $durationMs,
    ];
}

function isLikelyUnverifiable(string $output): bool
{
    $patterns = [
        'No se encuentra',
        'not recognized',
        'No such file',
        'Permission denied',
        'Acceso denegado',
        'could not be opened',
        'not found',
    ];

    foreach ($patterns as $pattern) {
        if (stripos($output, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int,string> $lines
 * @return array<int,string>
 */
function nonEmpty(array $lines): array
{
    $result = [];
    foreach ($lines as $line) {
        $clean = trim($line);
        if ($clean !== '') {
            $result[] = $clean;
        }
    }
    return $result;
}

/**
 * @return array{exists: array<int,string>, missing: array<int,string>}
 */
function splitExistingPaths(string $repositoryRoot, array $relativePaths): array
{
    $exists = [];
    $missing = [];

    foreach ($relativePaths as $relativePath) {
        $fullPath = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (file_exists($fullPath)) {
            $exists[] = $relativePath;
        } else {
            $missing[] = $relativePath;
        }
    }

    return ['exists' => $exists, 'missing' => $missing];
}

/**
 * @return array<int, array<string,mixed>>
 */
function runAudit(string $repositoryRoot, array $options, array &$commandLog): array
{
    $results = [];
    $modo = (string) $options['modo'];
    $sinTestsLargos = (bool) $options['sin_tests_largos'];
    $phpBin = escapeshellarg(PHP_BINARY);

    $gitStatus = runTrackedCommand('git status --short', $repositoryRoot, $commandLog);
    $gitStatusLines = nonEmpty(explode(PHP_EOL, (string) $gitStatus['output']));
    $gitStatusResult = [
        'id' => 'git-status',
        'titulo' => 'Git status sin commitear',
        'status' => $gitStatus['status'],
        'impacto' => $gitStatus['status'] === 'FAIL' ? 'no_bloqueante' : 'info',
        'detalle' => $gitStatus['status'] === 'OK'
            ? ($gitStatusLines === [] ? ['Workspace limpio.'] : array_merge(['Cambios detectados en git status --short:'], $gitStatusLines))
            : ['No se pudo ejecutar git status --short.', trim((string) $gitStatus['output'])],
    ];
    if ($gitStatus['status'] === 'OK' && $gitStatusLines !== []) {
        $gitStatusResult['status'] = 'WARN';
        $gitStatusResult['impacto'] = 'no_bloqueante';
    }
    $results[] = $gitStatusResult;

    $keyPaths = [
        'acelerador_panel/fronten/panel_tutor.php',
        'acelerador_panel/fronten/panel_profesor.php',
        'acelerador_panel/backend/public/index.php',
        'acelerador_panel/backend/src/Presentation/Routes/TutoriaRoutes.php',
        'acelerador_panel/backend/src/Presentation/Controllers/TutoriaController.php',
        'mcp-server/server.php',
    ];
    $pathsCheck = splitExistingPaths($repositoryRoot, $keyPaths);
    $results[] = [
        'id' => 'rutas-pantallas-clave',
        'titulo' => 'Existencia de rutas/pantallas clave',
        'status' => $pathsCheck['missing'] === [] ? 'OK' : 'FAIL',
        'impacto' => $pathsCheck['missing'] === [] ? 'info' : 'bloqueante',
        'detalle' => $pathsCheck['missing'] === []
            ? array_merge(['Todas las rutas/pantallas clave existen.'], $pathsCheck['exists'])
            : array_merge(
                ['Faltan rutas/pantallas clave:'],
                $pathsCheck['missing'],
                ['Presentes:'],
                ($pathsCheck['exists'] === [] ? ['(ninguna de la lista base)'] : $pathsCheck['exists'])
            ),
    ];

    $lintFiles = [
        'acelerador_panel/backend/src/Presentation/Routes/TutoriaRoutes.php',
        'acelerador_panel/backend/src/Presentation/Controllers/TutoriaController.php',
        'acelerador_panel/backend/src/Application/Matching/MatchingOrchestrator.php',
        'acelerador_panel/backend/src/Application/Matching/McpMatchingAssistant.php',
        'mcp-server/server.php',
        'mcp-server/worker_jobs.php',
        'meritos/scraping/tools/smoke_evaluation_auto_auxiliary.php',
    ];
    $lintResults = runPhpLintChecks($repositoryRoot, $lintFiles, $commandLog);
    $results = array_merge($results, $lintResults);

    $results[] = runPhpScriptIfExists(
        $repositoryRoot,
        $commandLog,
        'smoke-backend-usecases',
        'Smoke backend principal (usecases)',
        'acelerador_panel/backend/tests/run_usecases_smoke.php',
        'bloqueante'
    );

    if ($modo === 'rapido') {
        return finalizeModeResults($results, $gitStatusLines, $repositoryRoot, $commandLog, false);
    }

    $flowPaths = [
        'acelerador_panel/fronten/panel_tutor.php',
        'acelerador_panel/fronten/panel_profesor.php',
        'acelerador_panel/fronten/lib/auth_tutor.php',
        'acelerador_panel/fronten/lib/tutor_grupos_service.php',
        'acelerador_panel/backend/src/Application/UseCases/GetTutoriaMatchingRecommendationsUseCase.php',
    ];
    $flowCheck = splitExistingPaths($repositoryRoot, $flowPaths);
    $results[] = [
        'id' => 'flujo-tutor-profesor',
        'titulo' => 'Flujo tutor/profesor visible a nivel de archivos',
        'status' => $flowCheck['missing'] === [] ? 'OK' : 'FAIL',
        'impacto' => $flowCheck['missing'] === [] ? 'info' : 'bloqueante',
        'detalle' => $flowCheck['missing'] === []
            ? ['Flujo base detectado por presencia de archivos.']
            : array_merge(['Faltan piezas del flujo tutor/profesor:'], $flowCheck['missing']),
    ];

    $results[] = checkBackendEndpoints($repositoryRoot);

    $results[] = runPhpScriptIfExists(
        $repositoryRoot,
        $commandLog,
        'smoke-matching-mcp',
        'Smoke matching/MCP auxiliar',
        'meritos/scraping/tools/smoke_mcp_evaluation_provider.php',
        'bloqueante'
    );

    $results[] = runPhpScriptIfExists(
        $repositoryRoot,
        $commandLog,
        'smoke-jobs-queue',
        'Smoke cola de jobs (matching/MCP)',
        'meritos/scraping/tools/smoke_jobs_queue.php',
        'bloqueante'
    );

    $results[] = runPhpScriptIfExists(
        $repositoryRoot,
        $commandLog,
        'smoke-evaluacion-cv',
        'Smoke evaluacion CV',
        'evaluador/tests/run_synthetic_cv_regression.php',
        'bloqueante'
    );

    $results[] = runPhpScriptIfExists(
        $repositoryRoot,
        $commandLog,
        'validate-aneca-adapter',
        'Validacion evaluacion CV / adaptador ANECA',
        'meritos/scraping/tools/validate_aneca_canonical_adapter.php',
        'no_bloqueante'
    );

    $results[] = checkGeneratedPendingFiles($gitStatusLines);
    $results[] = checkMinimumDemoDocs($repositoryRoot);

    if ($modo === 'demo') {
        return finalizeModeResults($results, $gitStatusLines, $repositoryRoot, $commandLog, false);
    }

    if ($sinTestsLargos) {
        $results[] = [
            'id' => 'bateria-agresiva-corta',
            'titulo' => 'Bateria agresiva corta',
            'status' => 'SKIP',
            'impacto' => 'no_bloqueante',
            'detalle' => ['Omitida por flag --sin-tests-largos.'],
        ];
    } else {
        $results[] = runPhpScriptIfExists(
            $repositoryRoot,
            $commandLog,
            'bateria-agresiva-corta',
            'Bateria agresiva corta',
            'acelerador_panel/backend/tests/run_aggressive_battery.php',
            'bloqueante',
            '--duration-seconds=30'
        );
    }

    $results[] = runPhpScriptIfExists(
        $repositoryRoot,
        $commandLog,
        'validate-contract-scraping',
        'Validacion contrato tecnico de scraping',
        'meritos/scraping/tools/validate_scraping_technical_contracts.php',
        'no_bloqueante'
    );

    $results[] = runPhpScriptIfExists(
        $repositoryRoot,
        $commandLog,
        'validate-json-contracts',
        'Validacion contratos JSON',
        'meritos/scraping/tools/validate_json_contracts.php',
        'no_bloqueante'
    );

    $results[] = runPhpScriptIfExists(
        $repositoryRoot,
        $commandLog,
        'validate-canonical-schema',
        'Validacion schema canonico',
        'evaluador/tests/validate_canonical_schema.php',
        'no_bloqueante'
    );

    $results[] = scanForHardcodedLocalPaths($repositoryRoot);
    $results[] = scanForPotentialCredentials($repositoryRoot);
    $results[] = checkUndesiredArtifactsInGitStatus($gitStatusLines);

    return finalizeModeResults($results, $gitStatusLines, $repositoryRoot, $commandLog, true);
}

/**
 * @return array<int, array<string,mixed>>
 */
function finalizeModeResults(array $results, array $gitStatusLines, string $repositoryRoot, array &$commandLog, bool $includeCompleteFindings): array
{
    if ($includeCompleteFindings && $gitStatusLines !== []) {
        $results[] = [
            'id' => 'git-dirty-warning',
            'titulo' => 'Cambios sin commitear detectados',
            'status' => 'WARN',
            'impacto' => 'no_bloqueante',
            'detalle' => ['Hay cambios en git status --short. Revisar antes de demo/entrega.'],
        ];
    }

    return $results;
}

function runTrackedCommand(string $command, string $cwd, array &$commandLog): array
{
    $result = runCommand($command, $cwd);
    $commandLog[] = [
        'command' => $command,
        'cwd' => $cwd,
        'status' => $result['status'],
        'exitCode' => $result['exitCode'],
        'durationMs' => $result['durationMs'],
        'outputPreview' => summarizeOutput((string) $result['output']),
    ];
    return $result;
}

function summarizeOutput(string $output): string
{
    $trimmed = trim($output);
    if ($trimmed === '') {
        return '(sin salida)';
    }

    $lines = explode(PHP_EOL, $trimmed);
    $preview = array_slice($lines, 0, 3);
    return implode(' | ', $preview);
}

/**
 * @return array<int, array<string,mixed>>
 */
function runPhpLintChecks(string $repositoryRoot, array $lintFiles, array &$commandLog): array
{
    $results = [];
    $phpBin = escapeshellarg(PHP_BINARY);

    foreach ($lintFiles as $relativePath) {
        $fullPath = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($fullPath)) {
            $results[] = [
                'id' => 'lint-' . md5($relativePath),
                'titulo' => 'php -l (archivo critico): ' . $relativePath,
                'status' => 'WARN',
                'impacto' => 'no_bloqueante',
                'detalle' => ['Archivo no encontrado. No verificable.'],
            ];
            continue;
        }

        $command = $phpBin . ' -l ' . escapeshellarg($fullPath);
        $execution = runTrackedCommand($command, $repositoryRoot, $commandLog);
        $results[] = [
            'id' => 'lint-' . md5($relativePath),
            'titulo' => 'php -l (archivo critico): ' . $relativePath,
            'status' => $execution['status'],
            'impacto' => $execution['status'] === 'FAIL' ? 'bloqueante' : 'info',
            'detalle' => $execution['status'] === 'OK'
                ? ['Sintaxis valida.']
                : ['Fallo de lint o no verificable.', trim((string) $execution['output'])],
        ];
    }

    return $results;
}

/**
 * @return array<string,mixed>
 */
function runPhpScriptIfExists(
    string $repositoryRoot,
    array &$commandLog,
    string $id,
    string $title,
    string $relativeScriptPath,
    string $impactoOnFail,
    string $args = ''
): array {
    $fullPath = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeScriptPath);
    if (!is_file($fullPath)) {
        return [
            'id' => $id,
            'titulo' => $title,
            'status' => 'WARN',
            'impacto' => 'no_bloqueante',
            'detalle' => ['Script no encontrado. Check no verificable: ' . $relativeScriptPath],
        ];
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fullPath);
    if (trim($args) !== '') {
        $command .= ' ' . trim($args);
    }

    $execution = runTrackedCommand($command, $repositoryRoot, $commandLog);
    return [
        'id' => $id,
        'titulo' => $title,
        'status' => $execution['status'],
        'impacto' => $execution['status'] === 'FAIL' ? $impactoOnFail : 'info',
        'detalle' => $execution['status'] === 'OK'
            ? ['Ejecucion correcta.']
            : ['No superado o no verificable.', trim((string) $execution['output'])],
    ];
}

/**
 * @return array<string,mixed>
 */
function checkBackendEndpoints(string $repositoryRoot): array
{
    $routesPath = $repositoryRoot . DIRECTORY_SEPARATOR . 'acelerador_panel/backend/src/Presentation/Routes/TutoriaRoutes.php';
    if (!is_file($routesPath)) {
        return [
            'id' => 'backend-endpoints',
            'titulo' => 'Endpoints backend relevantes',
            'status' => 'WARN',
            'impacto' => 'no_bloqueante',
            'detalle' => ['Archivo de rutas no encontrado. No verificable.'],
        ];
    }

    $content = (string) file_get_contents($routesPath);
    $patterns = [
        'tutoria' => '/tutoria/i',
        'profesor' => '/profesor/i',
        'matching' => '/matching/i',
    ];
    $missing = [];
    foreach ($patterns as $label => $regex) {
        if (!preg_match($regex, $content)) {
            $missing[] = $label;
        }
    }

    return [
        'id' => 'backend-endpoints',
        'titulo' => 'Endpoints backend relevantes',
        'status' => $missing === [] ? 'OK' : 'WARN',
        'impacto' => $missing === [] ? 'info' : 'no_bloqueante',
        'detalle' => $missing === []
            ? ['Se detectan referencias a rutas de tutoria/profesor/matching.']
            : array_merge(['Faltan indicios de endpoints esperados en rutas:'], $missing),
    ];
}

/**
 * @return array<string,mixed>
 */
function checkGeneratedPendingFiles(array $gitStatusLines): array
{
    if ($gitStatusLines === []) {
        return [
            'id' => 'git-generados-pendientes',
            'titulo' => 'Archivos generados pendientes en git',
            'status' => 'OK',
            'impacto' => 'info',
            'detalle' => ['No hay cambios pendientes en git status.'],
        ];
    }

    $patterns = [
        '#(^|[\\\\/])output[\\\\/]#i',
        '#(^|[\\\\/])resultados[\\\\/]#i',
        '#(^|[\\\\/])reports[\\\\/]#i',
        '/\.log$/i',
        '/\.tmp$/i',
        '/\.cache$/i',
    ];

    $matches = [];
    foreach ($gitStatusLines as $line) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                $matches[] = $line;
                break;
            }
        }
    }

    return [
        'id' => 'git-generados-pendientes',
        'titulo' => 'Archivos generados pendientes en git',
        'status' => $matches === [] ? 'OK' : 'WARN',
        'impacto' => $matches === [] ? 'info' : 'no_bloqueante',
        'detalle' => $matches === []
            ? ['No se detectan artefactos generados en cambios pendientes.']
            : array_merge(['Se detectan posibles artefactos generados pendientes:'], $matches),
    ];
}

/**
 * @return array<string,mixed>
 */
function checkMinimumDemoDocs(string $repositoryRoot): array
{
    $requiredCandidates = [
        'docs/cierre-integracion-mcp-auxiliar-matching-evaluacion-2026-05-06.md',
        'docs/cierre-pre-mvp-2026-04-29.md',
        'docs/estado-tecnico-mvp.md',
        'docs/control-versiones-estado-tecnico-mvp.md',
    ];

    $presence = splitExistingPaths($repositoryRoot, $requiredCandidates);
    return [
        'id' => 'docs-minimas-demo',
        'titulo' => 'Documentacion minima de demo/cierre',
        'status' => count($presence['exists']) >= 2 ? 'OK' : 'WARN',
        'impacto' => count($presence['exists']) >= 2 ? 'info' : 'no_bloqueante',
        'detalle' => array_merge(
            ['Documentos encontrados: ' . count($presence['exists']) . '/' . count($requiredCandidates)],
            ($presence['exists'] === [] ? ['(ninguno)'] : $presence['exists']),
            ($presence['missing'] === [] ? [] : array_merge(['No encontrados:'], $presence['missing']))
        ),
    ];
}

/**
 * @return array<string,mixed>
 */
function scanForHardcodedLocalPaths(string $repositoryRoot): array
{
    $hits = findPatternHits($repositoryRoot, [
        '/localhost(?::\d+)?/i',
        '/127\.0\.0\.1(?::\d+)?/i',
        '/[A-Za-z]:\\\\Users\\\\/i',
    ], 20);

    return [
        'id' => 'hardcode-localhost',
        'titulo' => 'Revision de localhost/hardcodeados',
        'status' => $hits === [] ? 'OK' : 'WARN',
        'impacto' => $hits === [] ? 'info' : 'no_bloqueante',
        'detalle' => $hits === []
            ? ['No se detectaron coincidencias en muestra revisada.']
            : array_merge(['Coincidencias detectadas (revisar contexto):'], $hits),
    ];
}

/**
 * @return array<string,mixed>
 */
function scanForPotentialCredentials(string $repositoryRoot): array
{
    $hits = findPatternHits($repositoryRoot, [
        '/AKIA[0-9A-Z]{16}/',
        '/AIza[0-9A-Za-z\-_]{35}/',
        '/-----BEGIN (RSA )?PRIVATE KEY-----/',
        '/(api[_-]?key|secret|token|password)\s*[:=]\s*[\'"][^\'"]{8,}[\'"]/i',
    ], 20);

    $impact = 'info';
    $status = 'OK';
    if ($hits !== []) {
        $status = 'WARN';
        $impact = 'no_bloqueante';
    }

    return [
        'id' => 'credenciales-potenciales',
        'titulo' => 'Revision de posibles credenciales/tokens',
        'status' => $status,
        'impacto' => $impact,
        'detalle' => $hits === []
            ? ['No se detectaron patrones de alta probabilidad en muestra revisada.']
            : array_merge(['Posibles secretos detectados (requiere revision manual):'], $hits),
    ];
}

/**
 * @return array<string,mixed>
 */
function checkUndesiredArtifactsInGitStatus(array $gitStatusLines): array
{
    if ($gitStatusLines === []) {
        return [
            'id' => 'git-artefactos-no-deseados',
            'titulo' => 'Logs/artefactos no deseados en git status',
            'status' => 'OK',
            'impacto' => 'info',
            'detalle' => ['Sin cambios pendientes.'],
        ];
    }

    $patterns = [
        '/\.log$/i',
        '/\.tmp$/i',
        '/\.sqlite$/i',
        '/\.db$/i',
        '#(^|[\\\\/])reports[\\\\/]#i',
        '#(^|[\\\\/])resultados[\\\\/]#i',
        '#(^|[\\\\/])output[\\\\/]#i',
    ];

    $matches = [];
    foreach ($gitStatusLines as $line) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                $matches[] = $line;
                break;
            }
        }
    }

    return [
        'id' => 'git-artefactos-no-deseados',
        'titulo' => 'Logs/artefactos no deseados en git status',
        'status' => $matches === [] ? 'OK' : 'WARN',
        'impacto' => $matches === [] ? 'info' : 'no_bloqueante',
        'detalle' => $matches === []
            ? ['No se detectan artefactos no deseados en cambios pendientes.']
            : array_merge(['Posibles artefactos/logs detectados:'], $matches),
    ];
}

/**
 * @param array<int,string> $patterns
 * @return array<int,string>
 */
function findPatternHits(string $repositoryRoot, array $patterns, int $maxHits): array
{
    $hits = [];
    $excludeDirs = [
        '.git',
        'vendor',
        'node_modules',
        'mcp-server/.tools',
        'mcp-server/resultados',
        'evaluador/output',
        'reports',
    ];
    $allowedExtensions = ['php', 'js', 'ts', 'json', 'md', 'yml', 'yaml', 'ini', 'env', 'txt', 'xml'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($repositoryRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $fullPath = (string) $fileInfo->getPathname();
        $relativePath = ltrim(str_replace(['\\', '/'], '/', substr($fullPath, strlen($repositoryRoot))), '/');

        if (shouldSkipPath($relativePath, $excludeDirs)) {
            continue;
        }

        $extension = strtolower((string) $fileInfo->getExtension());
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        if ($fileInfo->getSize() > 2_000_000) {
            continue;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false || $content === '') {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $hits[] = $relativePath;
                break;
            }
        }

        if (count($hits) >= $maxHits) {
            break;
        }
    }

    return $hits;
}

/**
 * @param array<int,string> $excludeDirs
 */
function shouldSkipPath(string $relativePath, array $excludeDirs): bool
{
    foreach ($excludeDirs as $exclude) {
        $normalizedExclude = trim(str_replace('\\', '/', $exclude), '/');
        if ($normalizedExclude !== '' && str_starts_with($relativePath, $normalizedExclude . '/')) {
            return true;
        }
        if ($relativePath === $normalizedExclude) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{
 *   bloqueantes: array<int,array<string,mixed>>,
 *   no_bloqueantes: array<int,array<string,mixed>>,
 *   mejoras: array<int,string>,
 *   veredicto: string
 * }
 */
function classifyResults(array $results, string $modo): array
{
    $bloqueantes = [];
    $noBloqueantes = [];

    foreach ($results as $result) {
        $status = (string) ($result['status'] ?? 'WARN');
        $impacto = (string) ($result['impacto'] ?? 'no_bloqueante');

        if ($status === 'OK' || $status === 'SKIP') {
            continue;
        }

        if ($impacto === 'bloqueante' && $status === 'FAIL') {
            $bloqueantes[] = $result;
            continue;
        }

        $noBloqueantes[] = $result;
    }

    $mejoras = [
        'Mantener un comando unico de pre-demo en CI con el modo demo.',
        'Reducir ruido de artefactos con reglas de limpieza/ignore antes de demo.',
        'Documentar excepciones aceptadas de hardcodes locales para evitar falsos positivos.',
    ];

    $veredicto = 'LISTO PARA DEMO';
    if (count($bloqueantes) >= 3) {
        $veredicto = 'NO APTO PARA DEMO';
    } elseif (count($bloqueantes) > 0) {
        $veredicto = 'NECESITA CORREGIR BLOQUEANTES';
    } elseif (count($noBloqueantes) > 0) {
        $veredicto = 'LISTO CON OBSERVACIONES';
    }

    if ($modo === 'rapido' && $veredicto === 'LISTO PARA DEMO') {
        $veredicto = 'LISTO CON OBSERVACIONES';
    }

    return [
        'bloqueantes' => $bloqueantes,
        'no_bloqueantes' => $noBloqueantes,
        'mejoras' => $mejoras,
        'veredicto' => $veredicto,
    ];
}

function renderReport(
    string $modo,
    DateTimeImmutable $now,
    array $commandLog,
    array $results,
    array $classified
): string {
    $lines = [];
    $lines[] = '# Auditoria MVP';
    $lines[] = '';
    $lines[] = '## Resumen ejecutivo';
    $lines[] = '- Veredicto: **' . $classified['veredicto'] . '**';
    $lines[] = '- Bloqueantes: ' . count($classified['bloqueantes']);
    $lines[] = '- Hallazgos no bloqueantes: ' . count($classified['no_bloqueantes']);
    $lines[] = '- Total de comprobaciones: ' . count($results);
    $lines[] = '';

    $lines[] = '## Modo ejecutado';
    $lines[] = '- `' . $modo . '`';
    $lines[] = '';

    $lines[] = '## Fecha y hora';
    $lines[] = '- ' . $now->format('Y-m-d H:i:s') . ' (' . $now->getTimezone()->getName() . ')';
    $lines[] = '';

    $lines[] = '## Comandos ejecutados';
    if ($commandLog === []) {
        $lines[] = '- No se ejecutaron comandos.';
    } else {
        foreach ($commandLog as $entry) {
            $lines[] = '- `' . $entry['command'] . '` -> ' . $entry['status'] . ' (exit=' . $entry['exitCode'] . ', ' . $entry['durationMs'] . 'ms)';
        }
    }
    $lines[] = '';

    $lines[] = '## Resultados';
    foreach ($results as $result) {
        $lines[] = '- [' . $result['status'] . '] ' . $result['titulo'];
        $details = isset($result['detalle']) && is_array($result['detalle']) ? $result['detalle'] : [];
        foreach (array_slice($details, 0, 5) as $detail) {
            $lines[] = '  - ' . $detail;
        }
    }
    $lines[] = '';

    $lines[] = '## Hallazgos bloqueantes';
    if ($classified['bloqueantes'] === []) {
        $lines[] = '- Ninguno detectado.';
    } else {
        foreach ($classified['bloqueantes'] as $finding) {
            $lines[] = '- ' . $finding['titulo'];
        }
    }
    $lines[] = '';

    $lines[] = '## Hallazgos no bloqueantes';
    if ($classified['no_bloqueantes'] === []) {
        $lines[] = '- Ninguno detectado.';
    } else {
        foreach ($classified['no_bloqueantes'] as $finding) {
            $lines[] = '- ' . $finding['titulo'] . ' [' . $finding['status'] . ']';
        }
    }
    $lines[] = '';

    $lines[] = '## Mejoras post-MVP';
    foreach ($classified['mejoras'] as $improvement) {
        $lines[] = '- ' . $improvement;
    }
    $lines[] = '';

    $okMap = [];
    foreach ($results as $result) {
        $okMap[(string) $result['id']] = ((string) $result['status'] === 'OK');
    }
    $lines[] = '## Checklist de demo';
    $lines[] = '- [' . checkbox($okMap['git-status'] ?? false) . '] Estado git revisado';
    $lines[] = '- [' . checkbox($okMap['rutas-pantallas-clave'] ?? false) . '] Rutas/pantallas clave presentes';
    $lines[] = '- [' . checkbox($okMap['backend-endpoints'] ?? false) . '] Endpoints backend relevantes revisados';
    $lines[] = '- [' . checkbox($okMap['smoke-backend-usecases'] ?? false) . '] Smoke backend principal';
    $lines[] = '- [' . checkbox($okMap['smoke-matching-mcp'] ?? false) . '] Smoke matching/MCP auxiliar';
    $lines[] = '- [' . checkbox($okMap['smoke-evaluacion-cv'] ?? false) . '] Smoke evaluacion CV';
    $lines[] = '- [' . checkbox($okMap['docs-minimas-demo'] ?? false) . '] Documentacion minima de cierre/demo';
    $lines[] = '';

    $lines[] = '## Veredicto final';
    $lines[] = '- **' . $classified['veredicto'] . '**';
    $lines[] = '';

    return implode(PHP_EOL, $lines);
}

function checkbox(bool $value): string
{
    return $value ? 'x' : ' ';
}

function main(array $argv): int
{
    try {
        $options = parseArguments($argv);
        if ((bool) $options['help']) {
            printUsage();
            return 0;
        }

        $repositoryRoot = dirname(__DIR__, 4);
        $outputDir = resolveOutputDir($repositoryRoot, (string) $options['output_dir']);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new RuntimeException('No se pudo crear output-dir: ' . $outputDir);
        }

        $tz = new DateTimeZone('Europe/Madrid');
        $now = new DateTimeImmutable('now', $tz);
        $filename = 'auditoria-mvp-' . $now->format('Y-m-d-Hi') . '.md';
        $reportPath = rtrim($outputDir, '\\/') . DIRECTORY_SEPARATOR . $filename;

        $commandLog = [];
        $results = runAudit($repositoryRoot, $options, $commandLog);
        $classified = classifyResults($results, (string) $options['modo']);
        $report = renderReport((string) $options['modo'], $now, $commandLog, $results, $classified);

        file_put_contents($reportPath, $report);

        $summary = [
            'status' => 'ok',
            'modo' => $options['modo'],
            'report_path' => $reportPath,
            'veredicto' => $classified['veredicto'],
            'bloqueantes' => count($classified['bloqueantes']),
            'no_bloqueantes' => count($classified['no_bloqueantes']),
            'checks' => count($results),
        ];

        fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        return 0;
    } catch (Throwable $exception) {
        $error = [
            'status' => 'error',
            'message' => $exception->getMessage(),
        ];
        fwrite(STDERR, json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        return 1;
    }
}

if (isset($argv[0]) && realpath((string) $argv[0]) === __FILE__) {
    exit(main($argv));
}
