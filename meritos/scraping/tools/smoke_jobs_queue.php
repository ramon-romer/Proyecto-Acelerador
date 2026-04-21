<?php

require_once __DIR__ . '/../src/CvProcessingJobService.php';
require_once __DIR__ . '/../src/ProcessingCache.php';
require_once __DIR__ . '/../src/ProcessingJobQueue.php';
require_once __DIR__ . '/../src/ProcessingJobWorker.php';
require_once __DIR__ . '/../src/PipelineResultValidator.php';

$repoRoot = dirname(__DIR__, 3);
$samplePdf = $repoRoot . '/vendor/smalot/pdfparser/samples/Document1_pdfcreator.pdf';
$runId = 'smoke_cache_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$baseDir = __DIR__ . '/../output/smoke/' . $runId;
$jobsDir = $baseDir . '/jobs';
$pdfDir = $baseDir . '/pdfs';
$cacheDir = $baseDir . '/cache';

$result = [
    'ok' => true,
    'run_id' => $runId,
    'checks' => [],
    'jobs' => [],
    'cache' => [],
];

try {
    foreach ([$baseDir, $jobsDir, $pdfDir, $cacheDir] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new Exception('No se pudo crear directorio para smoke test: ' . $dir);
        }
    }

    if (!is_file($samplePdf)) {
        throw new Exception('No se encontro PDF de muestra para smoke test: ' . $samplePdf);
    }

    $queue = new ProcessingJobQueue($jobsDir);
    $validator = new PipelineResultValidator();

    $versionsV1 = [
        'version_pipeline' => 'hibrido_por_pagina_v1',
        'version_baremo' => 'baremo_smoke_v1',
        'version_schema' => 'schema_smoke_v1',
    ];

    $cacheV1 = new ProcessingCache($cacheDir, $versionsV1);
    $serviceV1 = new CvProcessingJobService($queue, $pdfDir, $cacheV1);
    $workerV1 = new ProcessingJobWorker($queue, $cacheV1);

    // 1) Primer procesamiento: debe generar cache valida.
    $upload1 = createUploadFixture($samplePdf, $baseDir, 'cv_cache_smoke.pdf');
    $enqueued1 = $serviceV1->enqueueFromUpload($upload1, false);
    $job1 = $enqueued1['job'] ?? null;
    registerCheck(
        $result,
        'primer_procesamiento_crea_job',
        is_array($job1) && (string)($job1['estado'] ?? '') === 'pendiente'
    );
    registerCheck(
        $result,
        'primer_procesamiento_cache_miss',
        empty($enqueued1['cache_hit'])
    );

    $job1Final = $workerV1->processJobById((string)($job1['id'] ?? ''));
    $hashPdf = $cacheV1->calculatePdfHash((string)($job1Final['pdf_path'] ?? ''));
    $lookupV1 = $cacheV1->resolveByHash($hashPdf, 'cv_cache_smoke.pdf');

    registerCheck(
        $result,
        'primer_procesamiento_genera_cache',
        !empty($lookupV1['cache_hit']) && is_file((string)($lookupV1['meta_path'] ?? '')) && is_file((string)($lookupV1['resultado_json_path'] ?? ''))
    );
    registerCheck(
        $result,
        'primer_procesamiento_guarda_texto_si_disponible',
        isset($lookupV1['texto_extraido_path']) && is_string($lookupV1['texto_extraido_path']) && $lookupV1['texto_extraido_path'] !== '' && is_file($lookupV1['texto_extraido_path'])
    );
    registerCheck(
        $result,
        'primer_procesamiento_job_traza_aneca',
        array_key_exists('aneca_canonical_path', $job1Final)
            && array_key_exists('aneca_canonical_ready', $job1Final)
            && array_key_exists('aneca_canonical_validation_status', $job1Final)
    );
    registerCheck(
        $result,
        'primer_procesamiento_cache_traza_aneca',
        array_key_exists('aneca_canonical_path', $lookupV1)
            && array_key_exists('aneca_canonical_ready', $lookupV1)
            && array_key_exists('aneca_canonical_validation_status', $lookupV1)
    );

    $result['jobs'][] = summarizeJob($job1Final);

    // 2) Segundo procesamiento del mismo PDF: debe usar cache.
    $upload2 = createUploadFixture($samplePdf, $baseDir, 'cv_cache_smoke.pdf');
    $enqueued2 = $serviceV1->enqueueFromUpload($upload2, false);
    $job2 = $enqueued2['job'] ?? null;

    registerCheck(
        $result,
        'segundo_procesamiento_usa_cache',
        !empty($enqueued2['cache_hit']) && is_array($job2) && !empty($job2['cache_hit'])
    );
    registerCheck(
        $result,
        'segundo_procesamiento_arrastra_traza_aneca',
        is_array($job2)
            && array_key_exists('aneca_canonical_path', $job2)
            && array_key_exists('aneca_canonical_ready', $job2)
            && array_key_exists('aneca_canonical_validation_status', $job2)
    );

    $requiredKeys = [
        'tipo_documento',
        'numero',
        'fecha',
        'total_bi',
        'iva',
        'total_a_pagar',
        'texto_preview',
        'archivo_pdf',
        'paginas_detectadas',
        'txt_generado',
        'json_generado',
    ];

    $job2Result = is_array($job2) ? (array)($job2['resultado_json'] ?? []) : [];
    registerCheck(
        $result,
        'resultado_cache_mantiene_claves_esperadas',
        hasRequiredKeys($job2Result, $requiredKeys)
    );

    if (is_array($job2)) {
        $result['jobs'][] = summarizeJob($job2);
    }

    // 3) Cambio de version_pipeline invalida cache.
    $cacheV2Pipeline = new ProcessingCache(
        $cacheDir,
        [
            'version_pipeline' => 'hibrido_por_pagina_v2',
            'version_baremo' => 'baremo_smoke_v1',
            'version_schema' => 'schema_smoke_v1',
        ]
    );
    $serviceV2Pipeline = new CvProcessingJobService($queue, $pdfDir, $cacheV2Pipeline);
    $workerV2Pipeline = new ProcessingJobWorker($queue, $cacheV2Pipeline);

    $lookupPipelineChanged = $cacheV2Pipeline->resolveByHash($hashPdf, 'cv_cache_smoke.pdf');
    registerCheck(
        $result,
        'cambio_version_pipeline_invalida_cache',
        empty($lookupPipelineChanged['cache_hit'])
            && (string)($lookupPipelineChanged['cache_estado'] ?? '') === 'obsoleta'
            && (string)($lookupPipelineChanged['motivo_invalidation'] ?? '') === 'version_pipeline_changed'
    );

    $upload3 = createUploadFixture($samplePdf, $baseDir, 'cv_cache_smoke.pdf');
    $enqueued3 = $serviceV2Pipeline->enqueueFromUpload($upload3, false);
    $job3 = $enqueued3['job'] ?? null;
    $job3Final = $workerV2Pipeline->processJobById((string)($job3['id'] ?? ''));
    $lookupV2Pipeline = $cacheV2Pipeline->resolveByHash($hashPdf, 'cv_cache_smoke.pdf');

    registerCheck(
        $result,
        'cache_regenerada_despues_cambio_pipeline',
        !empty($lookupV2Pipeline['cache_hit']) && empty($job3Final['cache_hit'])
    );
    $result['jobs'][] = summarizeJob($job3Final);

    // 4) Cambio de version_baremo invalida cache.
    $cacheV2Baremo = new ProcessingCache(
        $cacheDir,
        [
            'version_pipeline' => 'hibrido_por_pagina_v2',
            'version_baremo' => 'baremo_smoke_v2',
            'version_schema' => 'schema_smoke_v1',
        ]
    );
    $serviceV2Baremo = new CvProcessingJobService($queue, $pdfDir, $cacheV2Baremo);
    $workerV2Baremo = new ProcessingJobWorker($queue, $cacheV2Baremo);

    $lookupBaremoChanged = $cacheV2Baremo->resolveByHash($hashPdf, 'cv_cache_smoke.pdf');
    registerCheck(
        $result,
        'cambio_version_baremo_invalida_cache',
        empty($lookupBaremoChanged['cache_hit'])
            && (string)($lookupBaremoChanged['cache_estado'] ?? '') === 'obsoleta'
            && (string)($lookupBaremoChanged['motivo_invalidation'] ?? '') === 'version_baremo_changed'
    );

    $upload4 = createUploadFixture($samplePdf, $baseDir, 'cv_cache_smoke.pdf');
    $enqueued4 = $serviceV2Baremo->enqueueFromUpload($upload4, false);
    $job4 = $enqueued4['job'] ?? null;
    $job4Final = $workerV2Baremo->processJobById((string)($job4['id'] ?? ''));
    $lookupV2Baremo = $cacheV2Baremo->resolveByHash($hashPdf, 'cv_cache_smoke.pdf');

    registerCheck(
        $result,
        'cache_regenerada_despues_cambio_baremo',
        !empty($lookupV2Baremo['cache_hit']) && empty($job4Final['cache_hit'])
    );
    $result['jobs'][] = summarizeJob($job4Final);

    $result['cache'] = [
        'hash_pdf' => $hashPdf,
        'cache_v1_key' => $lookupV1['cache_key'] ?? null,
        'cache_v2_pipeline_key' => $lookupV2Pipeline['cache_key'] ?? null,
        'cache_v2_baremo_key' => $lookupV2Baremo['cache_key'] ?? null,
    ];

    // 5) Smoke de estados de validacion.
    $resultadoValido = [
        'tipo_documento' => 'informe',
        'numero' => 'ABC-123',
        'fecha' => '2026-04-21',
        'total_bi' => '10',
        'iva' => '2.1',
        'total_a_pagar' => '12.1',
        'texto_preview' => 'Texto con contenido suficiente',
        'archivo_pdf' => 'demo.pdf',
        'paginas_detectadas' => 1,
        'txt_generado' => 'demo.txt',
        'json_generado' => 'demo.json',
    ];
    $validacionValida = $validator->validate($resultadoValido);
    registerCheck(
        $result,
        'validator_resultado_valido',
        (string)($validacionValida['validation_status'] ?? '') === PipelineResultValidator::STATUS_VALIDO
    );

    $resultadoConClavesFaltantes = $resultadoValido;
    unset($resultadoConClavesFaltantes['json_generado']);
    $validacionFaltantes = $validator->validate($resultadoConClavesFaltantes);
    registerCheck(
        $result,
        'validator_resultado_con_claves_faltantes',
        (string)($validacionFaltantes['validation_status'] ?? '') === PipelineResultValidator::STATUS_INVALIDO
            && in_array('json_generado', (array)($validacionFaltantes['required_missing_keys'] ?? []), true)
    );

    $resultadoIncompleto = $resultadoValido;
    foreach (['tipo_documento', 'numero', 'fecha', 'total_bi', 'iva', 'total_a_pagar'] as $campo) {
        $resultadoIncompleto[$campo] = null;
    }
    $validacionIncompleta = $validator->validate($resultadoIncompleto);
    registerCheck(
        $result,
        'validator_resultado_incompleto',
        (string)($validacionIncompleta['validation_status'] ?? '') === PipelineResultValidator::STATUS_INCOMPLETO
    );

    $validacionInvalida = $validator->validate('{invalid json');
    registerCheck(
        $result,
        'validator_resultado_invalido',
        (string)($validacionInvalida['validation_status'] ?? '') === PipelineResultValidator::STATUS_INVALIDO
    );

    // 6) No cachear como valida una entrada corrupta.
    $corruptCacheDir = $baseDir . '/cache_corrupt';
    if (!is_dir($corruptCacheDir) && !mkdir($corruptCacheDir, 0777, true) && !is_dir($corruptCacheDir)) {
        throw new Exception('No se pudo crear cache de prueba corrupta.');
    }
    $corruptCache = new ProcessingCache($corruptCacheDir, $versionsV1);
    $corruptHash = $corruptCache->calculatePdfHash($samplePdf);
    $corruptCache->saveByHash(
        $corruptHash,
        'corrupt.pdf',
        $resultadoValido,
        'texto',
        [
            'estado_cache' => 'valida',
            'validation_status' => PipelineResultValidator::STATUS_INVALIDO,
            'validation_errors' => ['json_invalido'],
            'validation_warnings' => [],
        ]
    );
    $lookupCorrupt = $corruptCache->resolveByHash($corruptHash, 'corrupt.pdf');
    registerCheck(
        $result,
        'cache_corrupta_no_reutilizable_como_valida',
        empty($lookupCorrupt['cache_hit'])
            && (string)($lookupCorrupt['cache_estado'] ?? '') === 'no_valida'
            && (string)($lookupCorrupt['motivo_invalidation'] ?? '') === 'validation_status_invalido'
    );

    foreach ($result['checks'] as $check) {
        if (empty($check['ok'])) {
            $result['ok'] = false;
            break;
        }
    }

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
        throw new Exception('No se pudo preparar upload temporal para smoke test.');
    }

    return [
        'name' => $originalName,
        'type' => 'application/pdf',
        'tmp_name' => $tmpPath,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tmpPath),
    ];
}

function hasRequiredKeys(array $payload, array $requiredKeys): bool
{
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $payload)) {
            return false;
        }
    }

    return true;
}

function summarizeJob(array $job): array
{
    return [
        'id' => $job['id'] ?? null,
        'estado' => $job['estado'] ?? null,
        'cache_hit' => $job['cache_hit'] ?? null,
        'cache_key' => $job['cache_key'] ?? null,
        'cache_estado' => $job['cache_estado'] ?? null,
        'motivo_invalidation' => $job['motivo_invalidation'] ?? null,
        'aneca_canonical_path' => $job['aneca_canonical_path'] ?? null,
        'aneca_canonical_ready' => $job['aneca_canonical_ready'] ?? null,
        'aneca_canonical_validation_status' => $job['aneca_canonical_validation_status'] ?? null,
        'tiempo_total_ms' => $job['tiempo_total_ms'] ?? null,
    ];
}

function registerCheck(array &$result, string $name, bool $ok, array $context = []): void
{
    $check = [
        'name' => $name,
        'ok' => $ok,
    ];

    if (!empty($context)) {
        $check['context'] = $context;
    }

    $result['checks'][] = $check;
}

function outputResult(array $result, int $exitCode): void
{
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"ok":false,"message":"Error serializando salida de smoke test."}';
        $exitCode = 1;
    }

    echo $json . PHP_EOL;
    exit($exitCode);
}
