<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/JsonSchemaLiteValidator.php';
require_once __DIR__ . '/../src/AnecaCanonicalResultValidator.php';
require_once __DIR__ . '/../src/CvProcessingJobService.php';
require_once __DIR__ . '/../src/ProcessingCache.php';
require_once __DIR__ . '/../src/ProcessingJobQueue.php';
require_once __DIR__ . '/../src/ProcessingJobWorker.php';

$repoRoot = dirname(__DIR__, 3);
$canonicalSchemaPath = $repoRoot . '/docs/schemas/contrato-canonico-aneca-v1.schema.json';
$samplePdf = $repoRoot . '/vendor/smalot/pdfparser/samples/Document1_pdfcreator.pdf';
$runId = 'aneca_adapter_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$baseDir = __DIR__ . '/../output/smoke/' . $runId;
$jobsDir = $baseDir . '/jobs';
$pdfDir = $baseDir . '/pdfs';
$cacheDir = $baseDir . '/cache';

$result = [
    'ok' => true,
    'run_id' => $runId,
    'checks' => [],
    'artifacts' => [],
];

try {
    if (!is_file($canonicalSchemaPath)) {
        throw new RuntimeException('Schema canonico ANECA no encontrado: ' . $canonicalSchemaPath);
    }
    if (!is_file($samplePdf)) {
        throw new RuntimeException('PDF de muestra no encontrado: ' . $samplePdf);
    }

    foreach ([$baseDir, $jobsDir, $pdfDir, $cacheDir] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear directorio: ' . $dir);
        }
    }

    $schema = readJsonFileOrFail($canonicalSchemaPath);
    $validator = new JsonSchemaLiteValidator($schema);
    $canonicalQualityValidator = new AnecaCanonicalResultValidator($canonicalSchemaPath);

    $queue = new ProcessingJobQueue($jobsDir);
    $cache = new ProcessingCache(
        $cacheDir,
        [
            'version_pipeline' => 'adapter_smoke_v1',
            'version_baremo' => 'adapter_smoke_v1',
            'version_schema' => 'adapter_smoke_v1',
        ]
    );
    $service = new CvProcessingJobService($queue, $pdfDir, $cache);
    $worker = new ProcessingJobWorker($queue, $cache);

    $upload = createUploadFixture($samplePdf, $baseDir, 'adapter_sample.pdf');
    $enqueued = $service->enqueueFromUpload($upload, false);
    $job = is_array($enqueued['job'] ?? null) ? $enqueued['job'] : null;
    if (!is_array($job)) {
        throw new RuntimeException('No se pudo crear job para validar adaptador ANECA.');
    }

    $estado = (string)($job['estado'] ?? '');
    if (!in_array($estado, ['completado', 'error_parcial', 'error'], true)) {
        $job = $worker->processJobById((string)($job['id'] ?? ''));
    }

    $archivoPdf = (string)($job['archivo_pdf'] ?? '');
    if ($archivoPdf === '') {
        throw new RuntimeException('El job no contiene archivo_pdf para buscar artefacto canonico.');
    }

    $baseName = pathinfo($archivoPdf, PATHINFO_FILENAME);
    $canonicalJsonPath = __DIR__ . '/../output/json/' . $baseName . '.aneca.canonico.json';
    if (!is_file($canonicalJsonPath)) {
        throw new RuntimeException(
            'No se genero artefacto canonico ANECA esperado: ' . $canonicalJsonPath
        );
    }

    $hashPdf = $cache->calculatePdfHash((string)($job['pdf_path'] ?? ''));
    $canonicalPayload = readJsonFileOrFail($canonicalJsonPath);
    $errors = $validator->validate($canonicalPayload);
    $schemaOk = empty($errors);
    $quality = $canonicalQualityValidator->validateFile($canonicalJsonPath);
    $qualityReady = !empty($quality['aneca_canonical_ready']);
    $qualityStatus = (string)($quality['aneca_canonical_validation_status'] ?? '');

    $result['checks'][] = [
        'name' => 'adaptador_genera_json_aneca_schema_ok',
        'ok' => $schemaOk,
        'error_count' => count($errors),
        'errors' => $errors,
    ];
    $result['checks'][] = [
        'name' => 'adaptador_genera_json_aneca_util',
        'ok' => $qualityReady && in_array(
            $qualityStatus,
            ['valido', 'valido_con_advertencias'],
            true
        ),
        'quality_status' => $qualityStatus,
        'quality_errors' => $quality['aneca_canonical_validation_errors'] ?? [],
        'quality_warnings' => $quality['aneca_canonical_validation_warnings'] ?? [],
    ];

    $jobCanonicalFieldsOk =
        array_key_exists('aneca_canonical_path', $job)
        && array_key_exists('aneca_canonical_ready', $job)
        && array_key_exists('aneca_canonical_validation_status', $job)
        && is_string((string)($job['aneca_canonical_path'] ?? ''))
        && (string)($job['aneca_canonical_path'] ?? '') !== '';

    $result['checks'][] = [
        'name' => 'job_incluye_trazabilidad_aneca',
        'ok' => $jobCanonicalFieldsOk,
        'job_aneca_canonical_path' => $job['aneca_canonical_path'] ?? null,
        'job_aneca_canonical_ready' => $job['aneca_canonical_ready'] ?? null,
        'job_aneca_canonical_validation_status' => $job['aneca_canonical_validation_status'] ?? null,
    ];

    $lookup = $cache->resolveByHash($hashPdf, 'adapter_sample.pdf');
    $cacheCanonicalFieldsOk =
        !empty($lookup['cache_hit'])
        && array_key_exists('aneca_canonical_path', $lookup)
        && array_key_exists('aneca_canonical_ready', $lookup)
        && array_key_exists('aneca_canonical_validation_status', $lookup);
    $result['checks'][] = [
        'name' => 'cache_persiste_trazabilidad_aneca',
        'ok' => $cacheCanonicalFieldsOk,
        'cache_aneca_canonical_path' => $lookup['aneca_canonical_path'] ?? null,
        'cache_aneca_canonical_ready' => $lookup['aneca_canonical_ready'] ?? null,
        'cache_aneca_canonical_validation_status' => $lookup['aneca_canonical_validation_status'] ?? null,
    ];

    $uploadFromCache = createUploadFixture($samplePdf, $baseDir, 'adapter_sample.pdf');
    $enqueuedFromCache = $service->enqueueFromUpload($uploadFromCache, false);
    $jobFromCache = is_array($enqueuedFromCache['job'] ?? null) ? $enqueuedFromCache['job'] : [];
    $cacheHitOk = !empty($enqueuedFromCache['cache_hit']) && !empty($jobFromCache['cache_hit']);

    $result['checks'][] = [
        'name' => 'segundo_flujo_usa_cache_y_arrastra_aneca',
        'ok' => $cacheHitOk
            && !empty($jobFromCache['aneca_canonical_path'])
            && array_key_exists('aneca_canonical_ready', $jobFromCache)
            && array_key_exists('aneca_canonical_validation_status', $jobFromCache),
        'cache_hit' => $enqueuedFromCache['cache_hit'] ?? false,
        'job_cache_hit' => $jobFromCache['cache_hit'] ?? false,
        'job_aneca_canonical_path' => $jobFromCache['aneca_canonical_path'] ?? null,
        'job_aneca_canonical_ready' => $jobFromCache['aneca_canonical_ready'] ?? null,
        'job_aneca_canonical_validation_status' => $jobFromCache['aneca_canonical_validation_status'] ?? null,
    ];

    $legacyPayload = is_array($jobFromCache['resultado_json'] ?? null) ? $jobFromCache['resultado_json'] : [];
    $canonicalForConsumer = loadCanonicalPayloadFromPath((string)($jobFromCache['aneca_canonical_path'] ?? ''));
    $preferCanonical = !empty($jobFromCache['aneca_canonical_ready']) && is_array($canonicalForConsumer);
    $consumoPayload = $preferCanonical ? $canonicalForConsumer : $legacyPayload;
    $consumoFormato = $preferCanonical ? 'aneca' : 'legacy';
    $consumoOk = is_array($consumoPayload)
        && (
            $consumoFormato === 'legacy'
            || array_key_exists('bloque_1', $consumoPayload)
        );

    $result['checks'][] = [
        'name' => 'consumo_prioriza_aneca_sin_romper_legacy',
        'ok' => $consumoOk,
        'resultado_preferente_formato' => $consumoFormato,
        'aneca_ready' => $jobFromCache['aneca_canonical_ready'] ?? null,
    ];

    foreach ($result['checks'] as $check) {
        if (empty($check['ok'])) {
            $result['ok'] = false;
            break;
        }
    }

    $result['artifacts'] = [
        'schema_canonico' => $canonicalSchemaPath,
        'canonical_json_path' => $canonicalJsonPath,
        'job_id' => (string)($job['id'] ?? ''),
        'job_estado' => (string)($job['estado'] ?? ''),
        'hash_pdf' => $hashPdf,
        'cache_key' => (string)($lookup['cache_key'] ?? ''),
        'quality_status' => $qualityStatus,
    ];

    outputResult($result, $result['ok'] ? 0 : 1);
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['message'] = $e->getMessage();
    outputResult($result, 1);
}

function createUploadFixture(string $sourcePdf, string $baseDir, string $originalName): array
{
    $tmpPath = $baseDir . '/upload_' . bin2hex(random_bytes(4)) . '.pdf';
    if (!copy($sourcePdf, $tmpPath)) {
        throw new RuntimeException('No se pudo preparar upload temporal.');
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

function loadCanonicalPayloadFromPath(string $path): ?array
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
        $json = '{"ok":false,"message":"Error serializando salida del smoke ANECA."}';
        $exitCode = 1;
    }

    echo $json . PHP_EOL;
    exit($exitCode);
}
