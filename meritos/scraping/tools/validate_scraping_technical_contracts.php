<?php
declare(strict_types=1);

/**
 * Validacion de contratos tecnicos internos del modulo meritos/scraping.
 * No valida contrato canonico ANECA de dominio.
 */

require_once __DIR__ . '/../src/JsonSchemaLiteValidator.php';
require_once __DIR__ . '/../src/CvProcessingJobService.php';
require_once __DIR__ . '/../src/ProcessingCache.php';
require_once __DIR__ . '/../src/ProcessingJobQueue.php';
require_once __DIR__ . '/../src/ProcessingJobWorker.php';

$repoRoot = dirname(__DIR__, 3);
$schemaDir = $repoRoot . '/docs/schemas';
$samplePdf = $repoRoot . '/vendor/smalot/pdfparser/samples/Document1_pdfcreator.pdf';
$runId = 'contracts_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$baseDir = __DIR__ . '/../output/smoke/' . $runId;
$examplesDir = $baseDir . '/examples';

$result = [
    'ok' => true,
    'run_id' => $runId,
    'schema_dir' => $schemaDir,
    'examples_dir' => $examplesDir,
    'checks' => [],
    'artifacts' => [],
];

try {
    $schemaFiles = [
        'pipeline-result-legacy.v1.schema.json',
        'processing-job.v1.schema.json',
        'processing-cache.v1.schema.json',
        'api-response.v1.schema.json',
    ];

    $schemas = [];
    foreach ($schemaFiles as $schemaFile) {
        $schemaPath = $schemaDir . '/' . $schemaFile;
        if (!is_file($schemaPath)) {
            throw new RuntimeException('Schema no encontrado: ' . $schemaPath);
        }

        $schemas[$schemaFile] = readJsonFileOrFail($schemaPath);
    }

    ensureDirectory($baseDir);
    ensureDirectory($examplesDir);

    if (!is_file($samplePdf)) {
        throw new RuntimeException('No se encontro PDF de muestra: ' . $samplePdf);
    }

    $fixtures = buildRealFixtures($samplePdf, $baseDir);
    $jobInitial = $fixtures['job_initial'];
    $jobFinal = $fixtures['job_final'];
    $cacheMeta = $fixtures['cache_meta'];
    $cacheLookup = $fixtures['cache_lookup'];
    $enqueued = $fixtures['enqueued'];

    $result['artifacts'] = [
        'sample_pdf' => $samplePdf,
        'job_initial_id' => (string)($jobInitial['id'] ?? ''),
        'job_final_id' => (string)($jobFinal['id'] ?? ''),
        'job_final_estado' => (string)($jobFinal['estado'] ?? ''),
        'cache_key' => (string)($cacheLookup['cache_key'] ?? ''),
        'cache_meta_path' => (string)($fixtures['cache_meta_path'] ?? ''),
        'cache_result_path' => (string)($cacheLookup['resultado_json_path'] ?? ''),
    ];

    $samples = [
        [
            'name' => 'pipeline_result_legacy_real',
            'schema' => 'pipeline-result-legacy.v1.schema.json',
            'payload' => (array)($jobFinal['resultado_json'] ?? []),
        ],
        [
            'name' => 'processing_job_real',
            'schema' => 'processing-job.v1.schema.json',
            'payload' => $jobFinal,
        ],
        [
            'name' => 'processing_cache_meta_real',
            'schema' => 'processing-cache.v1.schema.json',
            'payload' => $cacheMeta,
        ],
    ];

    foreach (buildApiSamples($jobInitial, $jobFinal, $enqueued) as $apiSample) {
        $samples[] = $apiSample;
    }

    foreach ($samples as $sample) {
        $name = (string)$sample['name'];
        $schemaName = (string)$sample['schema'];
        $payload = is_array($sample['payload']) ? $sample['payload'] : [];

        if (!isset($schemas[$schemaName]) || !is_array($schemas[$schemaName])) {
            throw new RuntimeException('Schema no cargado: ' . $schemaName);
        }

        writeJsonFile(
            $examplesDir . '/' . $name . '.json',
            $payload
        );

        $validator = new JsonSchemaLiteValidator($schemas[$schemaName]);
        $errors = $validator->validate($payload);
        $ok = empty($errors);

        if (!$ok) {
            $result['ok'] = false;
        }

        $result['checks'][] = [
            'name' => $name,
            'schema' => $schemaName,
            'ok' => $ok,
            'error_count' => count($errors),
            'errors' => $errors,
            'example_path' => $examplesDir . '/' . $name . '.json',
        ];
    }

    outputResult($result, $result['ok'] ? 0 : 1);
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['message'] = $e->getMessage();
    outputResult($result, 1);
}

/**
 * @return array{job_initial: array<string,mixed>, job_final: array<string,mixed>, cache_meta: array<string,mixed>, cache_lookup: array<string,mixed>, cache_meta_path: string, enqueued: array<string,mixed>}
 */
function buildRealFixtures(string $samplePdf, string $baseDir): array
{
    $jobsDir = $baseDir . '/jobs';
    $pdfDir = $baseDir . '/pdfs';
    $cacheDir = $baseDir . '/cache';

    ensureDirectory($jobsDir);
    ensureDirectory($pdfDir);
    ensureDirectory($cacheDir);

    $versions = [
        'version_pipeline' => 'contracts_v1_pipeline',
        'version_baremo' => 'contracts_v1_baremo',
        'version_schema' => 'contracts_v1_schema',
    ];

    $queue = new ProcessingJobQueue($jobsDir);
    $cache = new ProcessingCache($cacheDir, $versions);
    $service = new CvProcessingJobService($queue, $pdfDir, $cache);
    $worker = new ProcessingJobWorker($queue, $cache);

    $upload = createUploadFixture($samplePdf, $baseDir, 'contracts_sample.pdf');
    $enqueued = $service->enqueueFromUpload($upload, false);

    $jobInitial = (array)($enqueued['job'] ?? []);
    $jobId = (string)($jobInitial['id'] ?? '');
    if ($jobId === '') {
        throw new RuntimeException('No se pudo crear job inicial para validar contratos.');
    }

    $jobFinal = $jobInitial;
    $estadoInicial = (string)($jobInitial['estado'] ?? '');
    if (!in_array($estadoInicial, ['completado', 'error_parcial', 'error'], true)) {
        $jobFinal = $worker->processJobById($jobId);
    }

    if (!is_array($jobFinal)) {
        throw new RuntimeException('El worker no devolvio un job valido.');
    }

    $resultadoFinal = $jobFinal['resultado_json'] ?? null;
    if (!is_array($resultadoFinal)) {
        throw new RuntimeException('El job final no contiene resultado_json valido para validar contratos.');
    }

    $hashPdf = strtolower(trim((string)($jobFinal['hash_pdf'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $hashPdf) !== 1) {
        $pdfPath = (string)($jobFinal['pdf_path'] ?? '');
        if ($pdfPath === '' || !is_file($pdfPath)) {
            throw new RuntimeException('No se pudo resolver hash_pdf desde el job final.');
        }
        $hashPdf = $cache->calculatePdfHash($pdfPath);
    }

    $cacheLookup = $cache->resolveByHash(
        $hashPdf,
        (string)($jobFinal['archivo_original'] ?? $jobFinal['archivo_pdf'] ?? 'contracts_sample.pdf')
    );

    $cacheMetaPath = firstNonEmptyString([
        $jobFinal['cache_meta_path'] ?? null,
        $cacheLookup['meta_path'] ?? null,
    ]);

    if ($cacheMetaPath === null || !is_file($cacheMetaPath)) {
        throw new RuntimeException('No se encontro .meta.json de cache generado para el smoke de contratos.');
    }

    $cacheMeta = readJsonFileOrFail($cacheMetaPath);

    return [
        'job_initial' => $jobInitial,
        'job_final' => $jobFinal,
        'cache_meta' => $cacheMeta,
        'cache_lookup' => is_array($cacheLookup) ? $cacheLookup : [],
        'cache_meta_path' => $cacheMetaPath,
        'enqueued' => $enqueued,
    ];
}

/**
 * @param array<string,mixed> $jobInitial
 * @param array<string,mixed> $jobFinal
 * @param array<string,mixed> $enqueued
 * @return array<int, array{name: string, schema: string, payload: array<string,mixed>}>
 */
function buildApiSamples(array $jobInitial, array $jobFinal, array $enqueued): array
{
    $initialJobId = (string)($jobInitial['id'] ?? '');
    $finalJobId = (string)($jobFinal['id'] ?? $initialJobId);
    $initialEndpoints = buildEndpoints($initialJobId !== '' ? $initialJobId : $finalJobId);
    $finalEndpoints = buildEndpoints($finalJobId);

    $pendingState = (string)($jobInitial['estado'] ?? 'pendiente');
    if ($pendingState === '' || in_array($pendingState, ['completado', 'error_parcial', 'error'], true)) {
        $pendingState = 'procesando_pdf';
    }

    $initialAnecaPath = nullableString($jobInitial['aneca_canonical_path'] ?? null);
    $finalAnecaPath = nullableString($jobFinal['aneca_canonical_path'] ?? null);
    $finalAnecaPayload = is_string($finalAnecaPath) ? loadJsonIfExists($finalAnecaPath) : null;

    $apiCreate = [
        'ok' => true,
        'message' => 'Job creado en cola.',
        'job_id' => $initialJobId,
        'estado' => (string)($jobInitial['estado'] ?? 'pendiente'),
        'progreso_porcentaje' => (int)($jobInitial['progreso_porcentaje'] ?? 0),
        'fase_actual' => (string)($jobInitial['fase_actual'] ?? 'pendiente'),
        'es_pesado' => (bool)($enqueued['is_heavy'] ?? false),
        'aneca_canonical_path' => $initialAnecaPath,
        'aneca_canonical_ready' => (bool)($jobInitial['aneca_canonical_ready'] ?? false),
        'aneca_canonical_validation_status' => nullableString($jobInitial['aneca_canonical_validation_status'] ?? null),
        'endpoints' => $initialEndpoints,
    ];

    $subirQueued = [
        'ok' => true,
        'message' => 'CV encolado para procesamiento asincrono.',
        'job_id' => $initialJobId,
        'estado' => (string)($jobInitial['estado'] ?? 'pendiente'),
        'progreso_porcentaje' => (int)($jobInitial['progreso_porcentaje'] ?? 0),
        'fase_actual' => (string)($jobInitial['fase_actual'] ?? 'pendiente'),
        'es_pesado' => (bool)($enqueued['is_heavy'] ?? false),
        'umbral_pesado_bytes' => max(1, (int)($enqueued['heavy_threshold_bytes'] ?? 1)),
        'aneca_canonical_path' => $initialAnecaPath,
        'aneca_canonical_ready' => (bool)($jobInitial['aneca_canonical_ready'] ?? false),
        'aneca_canonical_validation_status' => nullableString($jobInitial['aneca_canonical_validation_status'] ?? null),
        'endpoints' => $initialEndpoints,
    ];

    $apiEstado = [
        'ok' => true,
        'job_id' => $finalJobId,
        'estado' => (string)($jobFinal['estado'] ?? 'completado'),
        'progreso_porcentaje' => (int)($jobFinal['progreso_porcentaje'] ?? 100),
        'fase_actual' => (string)($jobFinal['fase_actual'] ?? 'completado'),
        'error_parcial' => (bool)($jobFinal['error_parcial'] ?? false),
        'error_mensaje' => nullableString($jobFinal['error_mensaje'] ?? null),
        'fecha_creacion' => (string)($jobFinal['fecha_creacion'] ?? gmdate('c')),
        'fecha_inicio' => nullableString($jobFinal['fecha_inicio'] ?? null),
        'fecha_fin' => nullableString($jobFinal['fecha_fin'] ?? null),
        'tiempo_total_ms' => is_numeric($jobFinal['tiempo_total_ms'] ?? null) ? (float)$jobFinal['tiempo_total_ms'] : null,
        'trace_path' => nullableString($jobFinal['trace_path'] ?? null),
        'log_path' => nullableString($jobFinal['log_path'] ?? null),
        'pipeline_log_path' => nullableString($jobFinal['pipeline_log_path'] ?? null),
        'aneca_canonical_path' => $finalAnecaPath,
        'aneca_canonical_ready' => (bool)($jobFinal['aneca_canonical_ready'] ?? false),
        'aneca_canonical_validation_status' => nullableString($jobFinal['aneca_canonical_validation_status'] ?? null),
    ];

    $apiResultadoReady = [
        'ok' => true,
        'job_id' => $finalJobId,
        'estado' => (string)($jobFinal['estado'] ?? 'completado'),
        'error_parcial' => (bool)($jobFinal['error_parcial'] ?? false),
        'error_mensaje' => nullableString($jobFinal['error_mensaje'] ?? null),
        'resultado' => (array)($jobFinal['resultado_json'] ?? []),
        'trace_path' => nullableString($jobFinal['trace_path'] ?? null),
        'log_path' => nullableString($jobFinal['log_path'] ?? null),
        'pipeline_log_path' => nullableString($jobFinal['pipeline_log_path'] ?? null),
        'aneca_canonical_path' => $finalAnecaPath,
        'aneca_canonical_ready' => (bool)($jobFinal['aneca_canonical_ready'] ?? false),
        'aneca_canonical_validation_status' => nullableString($jobFinal['aneca_canonical_validation_status'] ?? null),
        'resultado_aneca_canonico' => $finalAnecaPayload,
        'resultado_preferente_formato' => !empty($jobFinal['aneca_canonical_ready']) && is_array($finalAnecaPayload)
            ? 'aneca'
            : 'legacy',
        'resultado_preferente' => !empty($jobFinal['aneca_canonical_ready']) && is_array($finalAnecaPayload)
            ? $finalAnecaPayload
            : (array)($jobFinal['resultado_json'] ?? []),
        'tiempo_total_ms' => is_numeric($jobFinal['tiempo_total_ms'] ?? null) ? (float)$jobFinal['tiempo_total_ms'] : null,
    ];

    $subirResultadoReady = [
        'ok' => true,
        'message' => 'CV procesado en linea (archivo no pesado).',
        'job_id' => $finalJobId,
        'estado' => (string)($jobFinal['estado'] ?? 'completado'),
        'progreso_porcentaje' => (int)($jobFinal['progreso_porcentaje'] ?? 100),
        'fase_actual' => (string)($jobFinal['fase_actual'] ?? 'completado'),
        'resultado' => (array)($jobFinal['resultado_json'] ?? []),
        'trace_path' => nullableString($jobFinal['trace_path'] ?? null),
        'log_path' => nullableString($jobFinal['log_path'] ?? null),
        'pipeline_log_path' => nullableString($jobFinal['pipeline_log_path'] ?? null),
        'aneca_canonical_path' => $finalAnecaPath,
        'aneca_canonical_ready' => (bool)($jobFinal['aneca_canonical_ready'] ?? false),
        'aneca_canonical_validation_status' => nullableString($jobFinal['aneca_canonical_validation_status'] ?? null),
        'resultado_aneca_canonico' => $finalAnecaPayload,
        'resultado_preferente_formato' => !empty($jobFinal['aneca_canonical_ready']) && is_array($finalAnecaPayload)
            ? 'aneca'
            : 'legacy',
        'resultado_preferente' => !empty($jobFinal['aneca_canonical_ready']) && is_array($finalAnecaPayload)
            ? $finalAnecaPayload
            : (array)($jobFinal['resultado_json'] ?? []),
        'tiempo_total_ms' => is_numeric($jobFinal['tiempo_total_ms'] ?? null) ? (float)$jobFinal['tiempo_total_ms'] : null,
        'endpoints' => $finalEndpoints,
    ];

    $apiResultadoPending = [
        'ok' => false,
        'job_id' => $initialJobId,
        'estado' => $pendingState,
        'message' => 'El resultado todavia no esta disponible.',
    ];

    $apiResultadoError = [
        'ok' => false,
        'job_id' => $finalJobId,
        'estado' => 'error',
        'message' => 'El job termino con error.',
        'error_mensaje' => 'error_simulado_para_schema',
    ];

    $subirValidationError = [
        'ok' => false,
        'message' => 'El procesamiento del CV finalizo con error de validacion.',
        'job_id' => $finalJobId,
        'estado' => 'error',
        'error_mensaje' => 'error_simulado_para_schema',
        'endpoints' => $finalEndpoints,
    ];

    $genericError = [
        'ok' => false,
        'message' => 'Ruta/metodo no soportado.',
    ];

    return [
        [
            'name' => 'api_create_success_real',
            'schema' => 'api-response.v1.schema.json',
            'payload' => $apiCreate,
        ],
        [
            'name' => 'subir_queue_success_real',
            'schema' => 'api-response.v1.schema.json',
            'payload' => $subirQueued,
        ],
        [
            'name' => 'api_estado_success_real',
            'schema' => 'api-response.v1.schema.json',
            'payload' => $apiEstado,
        ],
        [
            'name' => 'api_resultado_ready_real',
            'schema' => 'api-response.v1.schema.json',
            'payload' => $apiResultadoReady,
        ],
        [
            'name' => 'subir_resultado_ready_real',
            'schema' => 'api-response.v1.schema.json',
            'payload' => $subirResultadoReady,
        ],
        [
            'name' => 'api_resultado_pending_real',
            'schema' => 'api-response.v1.schema.json',
            'payload' => $apiResultadoPending,
        ],
        [
            'name' => 'api_resultado_error_example',
            'schema' => 'api-response.v1.schema.json',
            'payload' => $apiResultadoError,
        ],
        [
            'name' => 'subir_validation_error_example',
            'schema' => 'api-response.v1.schema.json',
            'payload' => $subirValidationError,
        ],
        [
            'name' => 'api_generic_error_example',
            'schema' => 'api-response.v1.schema.json',
            'payload' => $genericError,
        ],
    ];
}

/**
 * @return array{estado: string, resultado: string}
 */
function buildEndpoints(string $jobId): array
{
    return [
        'estado' => '/api/cv/procesar/' . $jobId . '/estado',
        'resultado' => '/api/cv/procesar/' . $jobId . '/resultado',
    ];
}

function createUploadFixture(string $sourcePdf, string $baseDir, string $originalName): array
{
    $tmpPath = $baseDir . '/upload_' . bin2hex(random_bytes(4)) . '.pdf';
    if (!copy($sourcePdf, $tmpPath)) {
        throw new RuntimeException('No se pudo crear upload temporal para smoke de contratos.');
    }

    $size = filesize($tmpPath);
    if (!is_int($size)) {
        $size = 0;
    }

    return [
        'name' => $originalName,
        'type' => 'application/pdf',
        'tmp_name' => $tmpPath,
        'error' => UPLOAD_ERR_OK,
        'size' => $size,
    ];
}

/**
 * @return array<string,mixed>
 */
function readJsonFileOrFail(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        throw new RuntimeException('No se pudo leer JSON: ' . $path);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('JSON invalido: ' . $path);
    }

    return $decoded;
}

/**
 * @param array<string,mixed> $payload
 */
function writeJsonFile(string $path, array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar ejemplo JSON: ' . $path);
    }

    if (@file_put_contents($path, $json) === false) {
        throw new RuntimeException('No se pudo escribir ejemplo JSON: ' . $path);
    }
}

function ensureDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('No se pudo crear directorio: ' . $path);
    }
}

/**
 * @param array<int,mixed> $values
 */
function firstNonEmptyString(array $values): ?string
{
    foreach ($values as $value) {
        if (!is_string($value)) {
            continue;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            continue;
        }

        return $trimmed;
    }

    return null;
}

/**
 * @param mixed $value
 */
function nullableString($value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

/**
 * @return array<string,mixed>|null
 */
function loadJsonIfExists(string $path): ?array
{
    $path = trim($path);
    if ($path === '' || !is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * @param array<string,mixed> $result
 */
function outputResult(array $result, int $exitCode): void
{
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"ok":false,"message":"Error serializando salida de validacion de contratos."}';
        $exitCode = 1;
    }

    echo $json . PHP_EOL;
    exit($exitCode);
}
