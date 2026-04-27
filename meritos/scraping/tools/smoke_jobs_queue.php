<?php

require_once __DIR__ . '/../src/CvProcessingJobService.php';
require_once __DIR__ . '/../src/ProcessingCache.php';
require_once __DIR__ . '/../src/ProcessingJobQueue.php';
require_once __DIR__ . '/../src/ProcessingJobWorker.php';
require_once __DIR__ . '/../src/LegacyPipelineResultValidator.php';
require_once __DIR__ . '/../src/PipelineResultValidator.php';
require_once __DIR__ . '/../src/AnecaCanonicalResultValidator.php';
require_once __DIR__ . '/../src/PreferredResultResolver.php';

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
    $legacyValidator = new LegacyPipelineResultValidator();

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
    registerCheck(
        $result,
        'job_expone_resultado_principal_interno',
        is_array($job1Final)
            && in_array((string)($job1Final['resultado_principal_formato'] ?? ''), ['aneca', 'legacy'], true)
            && array_key_exists('resultado_principal_path', $job1Final)
    );
    registerCheck(
        $result,
        'cache_expone_resultado_principal_interno',
        in_array((string)($lookupV1['resultado_principal_formato'] ?? ''), ['aneca', 'legacy'], true)
            && array_key_exists('resultado_principal_path', $lookupV1)
    );
    $expectedPrimaryFormatV1 = (
        !empty($lookupV1['aneca_canonical_ready'])
        && is_string($lookupV1['aneca_canonical_path'] ?? null)
        && is_file((string)$lookupV1['aneca_canonical_path'])
    ) ? 'aneca' : 'legacy';
    registerCheck(
        $result,
        'cache_selecciona_artefacto_principal_esperado',
        (string)($lookupV1['resultado_principal_formato'] ?? '') === $expectedPrimaryFormatV1
            && (
                $expectedPrimaryFormatV1 === 'aneca'
                    ? (string)($lookupV1['resultado_principal_path'] ?? '') === (string)($lookupV1['aneca_canonical_path'] ?? '')
                    : (string)($lookupV1['resultado_principal_path'] ?? '') === (string)($lookupV1['resultado_json_path'] ?? '')
            )
    );
    $anecaValidator = new AnecaCanonicalResultValidator();
    $job1AnecaPath = is_array($job1Final) ? (string)($job1Final['aneca_canonical_path'] ?? '') : '';
    $anecaValidation = ($job1AnecaPath !== '' && is_file($job1AnecaPath))
        ? $anecaValidator->validateFile($job1AnecaPath)
        : $anecaValidator->validate(buildAnecaPreferredFixture());
    registerCheck(
        $result,
        'validator_aneca_responde_con_status_controlado',
        in_array(
            (string)($anecaValidation['aneca_canonical_validation_status'] ?? ''),
            [
                AnecaCanonicalResultValidator::STATUS_VALIDO,
                AnecaCanonicalResultValidator::STATUS_VALIDO_CON_ADVERTENCIAS,
                AnecaCanonicalResultValidator::STATUS_INCOMPLETO,
                AnecaCanonicalResultValidator::STATUS_INVALIDO,
            ],
            true
        )
    );
    registerCheck(
        $result,
        'validator_aneca_expone_ready_booleano',
        array_key_exists('aneca_canonical_ready', $anecaValidation)
            && is_bool($anecaValidation['aneca_canonical_ready'])
    );
    registerCheck(
        $result,
        'validator_aneca_consistente_con_job_si_hay_artefacto',
        ($job1AnecaPath === '' || !is_file($job1AnecaPath))
            || (!empty($job1Final['aneca_canonical_ready']) === !empty($anecaValidation['aneca_canonical_ready']))
    );
    registerCheck(
        $result,
        'worker_log_decision_operativa_runtime_presente',
        jobLogContains($job1Final, 'decision_operativa_runtime')
    );
    registerCheck(
        $result,
        'worker_log_formato_operativo_runtime_consistente',
        $expectedPrimaryFormatV1 === 'aneca'
            ? jobLogContains($job1Final, 'decision_operativa_runtime criterio=aneca_operativo formato=aneca')
            : jobLogContains($job1Final, 'decision_operativa_runtime criterio=legacy_fallback formato=legacy')
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
    registerCheck(
        $result,
        'service_log_decision_operativa_cache_hit_presente',
        is_array($job2)
            && jobLogContains($job2, 'decision_operativa_service_cache_hit')
    );
    registerCheck(
        $result,
        'service_log_formato_operativo_cache_hit_consistente',
        is_array($job2)
            && (
                $expectedPrimaryFormatV1 === 'aneca'
                    ? jobLogContains($job2, 'decision_operativa_service_cache_hit criterio=aneca_operativo formato=aneca')
                    : jobLogContains($job2, 'decision_operativa_service_cache_hit criterio=legacy_fallback formato=legacy')
            )
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
    registerCheck(
        $result,
        'compatibilidad_resultado_legacy_mantenida',
        hasRequiredKeys($job2Result, $requiredKeys)
    );

    // 2.b) Salida preferente: validar primero resultado_preferente y formato.
    $legacyPayloadPreferente = !empty($job2Result) ? $job2Result : [
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
    $anecaPayloadFixture = buildAnecaPreferredFixture();

    $preferredWhenAnecaAvailable = PreferredResultResolver::resolvePreferredResult(
        $legacyPayloadPreferente,
        true,
        true,
        $anecaPayloadFixture
    );
    registerCheck(
        $result,
        'preferencia_aneca_activa_y_aneca_disponible',
        (string)($preferredWhenAnecaAvailable['resultado_preferente_formato'] ?? '') === PreferredResultResolver::FORMAT_ANECA
            && is_array($preferredWhenAnecaAvailable['resultado_preferente'] ?? null)
            && array_key_exists('bloque_1', (array)$preferredWhenAnecaAvailable['resultado_preferente'])
    );

    $preferredWhenAnecaMissing = PreferredResultResolver::resolvePreferredResult(
        $legacyPayloadPreferente,
        true,
        false,
        null
    );
    registerCheck(
        $result,
        'preferencia_aneca_activa_sin_aneca_disponible_fallback_legacy',
        (string)($preferredWhenAnecaMissing['resultado_preferente_formato'] ?? '') === PreferredResultResolver::FORMAT_LEGACY
            && hasRequiredKeys((array)($preferredWhenAnecaMissing['resultado_preferente'] ?? []), $requiredKeys)
    );

    $preferredWhenDisabled = PreferredResultResolver::resolvePreferredResult(
        $legacyPayloadPreferente,
        false,
        true,
        $anecaPayloadFixture
    );
    registerCheck(
        $result,
        'preferencia_aneca_desactivada',
        (string)($preferredWhenDisabled['resultado_preferente_formato'] ?? '') === PreferredResultResolver::FORMAT_LEGACY
            && hasRequiredKeys((array)($preferredWhenDisabled['resultado_preferente'] ?? []), $requiredKeys)
    );

    registerCheck(
        $result,
        'resultado_preferente_formato_usa_valores_estables',
        in_array(
            (string)($preferredWhenAnecaAvailable['resultado_preferente_formato'] ?? ''),
            [PreferredResultResolver::FORMAT_ANECA, PreferredResultResolver::FORMAT_LEGACY],
            true
        )
            && in_array(
                (string)($preferredWhenAnecaMissing['resultado_preferente_formato'] ?? ''),
                [PreferredResultResolver::FORMAT_ANECA, PreferredResultResolver::FORMAT_LEGACY],
                true
            )
            && in_array(
                (string)($preferredWhenDisabled['resultado_preferente_formato'] ?? ''),
                [PreferredResultResolver::FORMAT_ANECA, PreferredResultResolver::FORMAT_LEGACY],
                true
            )
    );

    // 2.c) Configuracion por entorno PREFER_ANECA_DEFAULT.
    $previousPreferDefault = getenv('PREFER_ANECA_DEFAULT');
    putenv('PREFER_ANECA_DEFAULT=1');
    $preferByDefaultEnabled = PreferredResultResolver::shouldPreferAneca(null);
    $preferExplicitDisabled = PreferredResultResolver::shouldPreferAneca('0');
    putenv('PREFER_ANECA_DEFAULT=0');
    $preferByDefaultDisabled = PreferredResultResolver::shouldPreferAneca(null);
    if ($previousPreferDefault === false) {
        putenv('PREFER_ANECA_DEFAULT');
    } else {
        putenv('PREFER_ANECA_DEFAULT=' . $previousPreferDefault);
    }

    registerCheck(
        $result,
        'config_preferencia_aneca_default_activa',
        $preferByDefaultEnabled && !$preferExplicitDisabled
    );
    registerCheck(
        $result,
        'config_preferencia_aneca_default_desactivada',
        !$preferByDefaultDisabled
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
    $validacionValida = $legacyValidator->validate($resultadoValido);
    registerCheck(
        $result,
        'validator_resultado_valido',
        (string)($validacionValida['validation_status'] ?? '') === LegacyPipelineResultValidator::STATUS_VALIDO
    );
    $legacyAliasValidator = new PipelineResultValidator();
    $validacionAlias = $legacyAliasValidator->validate($resultadoValido);
    registerCheck(
        $result,
        'pipeline_result_validator_alias_compatibilidad',
        (string)($validacionAlias['validation_status'] ?? '') === (string)($validacionValida['validation_status'] ?? '')
    );

    $resultadoConClavesFaltantes = $resultadoValido;
    unset($resultadoConClavesFaltantes['json_generado']);
    $validacionFaltantes = $legacyValidator->validate($resultadoConClavesFaltantes);
    registerCheck(
        $result,
        'validator_resultado_con_claves_faltantes',
        (string)($validacionFaltantes['validation_status'] ?? '') === LegacyPipelineResultValidator::STATUS_INVALIDO
            && in_array('json_generado', (array)($validacionFaltantes['required_missing_keys'] ?? []), true)
    );

    $resultadoIncompleto = $resultadoValido;
    foreach (['tipo_documento', 'numero', 'fecha', 'total_bi', 'iva', 'total_a_pagar'] as $campo) {
        $resultadoIncompleto[$campo] = null;
    }
    $validacionIncompleta = $legacyValidator->validate($resultadoIncompleto);
    registerCheck(
        $result,
        'validator_resultado_incompleto',
        (string)($validacionIncompleta['validation_status'] ?? '') === LegacyPipelineResultValidator::STATUS_INCOMPLETO
    );

    $validacionInvalida = $legacyValidator->validate('{invalid json');
    registerCheck(
        $result,
        'validator_resultado_invalido',
        (string)($validacionInvalida['validation_status'] ?? '') === LegacyPipelineResultValidator::STATUS_INVALIDO
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
            'validation_status' => LegacyPipelineResultValidator::STATUS_INVALIDO,
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

    // 7) Cache legacy-only sigue siendo valida y principal en fallback.
    $legacyOnlyCacheDir = $baseDir . '/cache_legacy_only';
    if (!is_dir($legacyOnlyCacheDir) && !mkdir($legacyOnlyCacheDir, 0777, true) && !is_dir($legacyOnlyCacheDir)) {
        throw new Exception('No se pudo crear cache de prueba legacy-only.');
    }
    $legacyOnlyCache = new ProcessingCache($legacyOnlyCacheDir, $versionsV1);
    $legacyOnlyHash = $legacyOnlyCache->calculatePdfHash($samplePdf);
    $legacyOnlySaved = $legacyOnlyCache->saveByHash(
        $legacyOnlyHash,
        'legacy_only.pdf',
        $resultadoValido,
        'texto legado',
        [
            'estado_cache' => 'valida',
            'aneca_canonical_path' => null,
            'aneca_canonical_ready' => false,
            'aneca_canonical_validation_status' => null,
            'validation_status' => LegacyPipelineResultValidator::STATUS_VALIDO,
            'validation_errors' => [],
            'validation_warnings' => [],
        ]
    );
    $legacyOnlyLookup = $legacyOnlyCache->resolveByHash($legacyOnlyHash, 'legacy_only.pdf');
    registerCheck(
        $result,
        'cache_legacy_only_mantiene_resultado_principal_legacy',
        !empty($legacyOnlyLookup['cache_hit'])
            && (string)($legacyOnlyLookup['resultado_principal_formato'] ?? '') === 'legacy'
            && (string)($legacyOnlyLookup['resultado_principal_path'] ?? '') === (string)($legacyOnlySaved['result_path'] ?? '')
    );

    // 8) Compatibilidad: metadatos antiguos sin resultado_principal_* degradan de forma segura.
    $legacyCompatMetaPath = (string)($legacyOnlySaved['meta_path'] ?? '');
    $legacyCompatEdited = false;
    if ($legacyCompatMetaPath !== '' && is_file($legacyCompatMetaPath)) {
        $legacyCompatMeta = json_decode((string)@file_get_contents($legacyCompatMetaPath), true);
        if (is_array($legacyCompatMeta)) {
            unset($legacyCompatMeta['resultado_principal_formato'], $legacyCompatMeta['resultado_principal_path']);
            $legacyCompatJson = json_encode($legacyCompatMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (is_string($legacyCompatJson) && @file_put_contents($legacyCompatMetaPath, $legacyCompatJson) !== false) {
                $legacyCompatEdited = true;
            }
        }
    }
    $legacyCompatLookup = $legacyOnlyCache->resolveByHash($legacyOnlyHash, 'legacy_only.pdf');
    registerCheck(
        $result,
        'cache_compatibilidad_sin_metadata_resultado_principal',
        $legacyCompatEdited
            && !empty($legacyCompatLookup['cache_hit'])
            && (string)($legacyCompatLookup['resultado_principal_formato'] ?? '') === 'legacy'
    );

    // 9) Cache meta ANECA utilizable no queda bloqueada por validation_status legacy invalido.
    $cacheAnecaOverLegacyInvalidDir = $baseDir . '/cache_aneca_over_legacy_invalid';
    if (!is_dir($cacheAnecaOverLegacyInvalidDir) && !mkdir($cacheAnecaOverLegacyInvalidDir, 0777, true) && !is_dir($cacheAnecaOverLegacyInvalidDir)) {
        throw new Exception('No se pudo crear cache de prueba ANECA sobre legacy invalido.');
    }
    $cacheAnecaOverLegacyInvalid = new ProcessingCache($cacheAnecaOverLegacyInvalidDir, $versionsV1);
    $hashAnecaOverLegacyInvalid = $cacheAnecaOverLegacyInvalid->calculatePdfHash($samplePdf);
    $anecaOverLegacyInvalidPath = $baseDir . '/aneca.cache.override.fixture.json';
    $anecaOverLegacyInvalidJson = json_encode(buildAnecaPreferredFixture(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($anecaOverLegacyInvalidJson) || @file_put_contents($anecaOverLegacyInvalidPath, $anecaOverLegacyInvalidJson) === false) {
        throw new Exception('No se pudo escribir fixture ANECA para cache override.');
    }
    $cacheAnecaOverLegacyInvalid->saveByHash(
        $hashAnecaOverLegacyInvalid,
        'aneca_override.pdf',
        $resultadoValido,
        'texto override',
        [
            'estado_cache' => 'valida',
            'aneca_canonical_path' => $anecaOverLegacyInvalidPath,
            'aneca_canonical_ready' => true,
            'aneca_canonical_validation_status' => AnecaCanonicalResultValidator::STATUS_VALIDO,
            'validation_status' => LegacyPipelineResultValidator::STATUS_INVALIDO,
            'validation_errors' => ['legacy_status_invalido'],
            'validation_warnings' => [],
        ]
    );
    $lookupAnecaOverLegacyInvalid = $cacheAnecaOverLegacyInvalid->resolveByHash($hashAnecaOverLegacyInvalid, 'aneca_override.pdf');
    registerCheck(
        $result,
        'cache_meta_aneca_utilizable_reutilizable_aunque_legacy_invalido',
        !empty($lookupAnecaOverLegacyInvalid['cache_hit'])
            && (string)($lookupAnecaOverLegacyInvalid['resultado_principal_formato'] ?? '') === 'aneca'
            && (string)($lookupAnecaOverLegacyInvalid['operational_criterion'] ?? '') === 'aneca_operativo'
            && (string)($lookupAnecaOverLegacyInvalid['operational_artifact_format'] ?? '') === 'aneca'
    );

    // 10) ANECA no utilizable hace fallback legacy sin invalidar cache si legacy es utilizable.
    $cacheAnecaInvalidLegacyValidDir = $baseDir . '/cache_aneca_invalid_legacy_valid';
    if (!is_dir($cacheAnecaInvalidLegacyValidDir) && !mkdir($cacheAnecaInvalidLegacyValidDir, 0777, true) && !is_dir($cacheAnecaInvalidLegacyValidDir)) {
        throw new Exception('No se pudo crear cache de prueba ANECA no utilizable con fallback legacy.');
    }
    $cacheAnecaInvalidLegacyValid = new ProcessingCache($cacheAnecaInvalidLegacyValidDir, $versionsV1);
    $hashAnecaInvalidLegacyValid = $cacheAnecaInvalidLegacyValid->calculatePdfHash($samplePdf);
    $cacheAnecaInvalidLegacyValid->saveByHash(
        $hashAnecaInvalidLegacyValid,
        'aneca_invalid_legacy_valid.pdf',
        $resultadoValido,
        'texto fallback legacy',
        [
            'estado_cache' => 'valida',
            'aneca_canonical_path' => $anecaOverLegacyInvalidPath,
            'aneca_canonical_ready' => true,
            'aneca_canonical_validation_status' => AnecaCanonicalResultValidator::STATUS_INVALIDO,
            'validation_status' => LegacyPipelineResultValidator::STATUS_VALIDO,
            'validation_errors' => [],
            'validation_warnings' => [],
        ]
    );
    $lookupAnecaInvalidLegacyValid = $cacheAnecaInvalidLegacyValid->resolveByHash($hashAnecaInvalidLegacyValid, 'aneca_invalid_legacy_valid.pdf');
    registerCheck(
        $result,
        'cache_meta_aneca_no_utilizable_hace_fallback_legacy',
        !empty($lookupAnecaInvalidLegacyValid['cache_hit'])
            && (string)($lookupAnecaInvalidLegacyValid['resultado_principal_formato'] ?? '') === 'legacy'
            && (string)($lookupAnecaInvalidLegacyValid['operational_criterion'] ?? '') === 'legacy_fallback'
            && strpos((string)($lookupAnecaInvalidLegacyValid['operational_fallback_reason'] ?? ''), 'aneca_status_no_utilizable') === 0
    );

    // 11) ANECA no utilizable + legacy invalido => invalidacion controlada.
    $cacheAnecaInvalidLegacyInvalidDir = $baseDir . '/cache_aneca_invalid_legacy_invalid';
    if (!is_dir($cacheAnecaInvalidLegacyInvalidDir) && !mkdir($cacheAnecaInvalidLegacyInvalidDir, 0777, true) && !is_dir($cacheAnecaInvalidLegacyInvalidDir)) {
        throw new Exception('No se pudo crear cache de prueba ANECA no utilizable + legacy invalido.');
    }
    $cacheAnecaInvalidLegacyInvalid = new ProcessingCache($cacheAnecaInvalidLegacyInvalidDir, $versionsV1);
    $hashAnecaInvalidLegacyInvalid = $cacheAnecaInvalidLegacyInvalid->calculatePdfHash($samplePdf);
    $cacheAnecaInvalidLegacyInvalid->saveByHash(
        $hashAnecaInvalidLegacyInvalid,
        'aneca_invalid_legacy_invalid.pdf',
        $resultadoValido,
        'texto invalido',
        [
            'estado_cache' => 'valida',
            'aneca_canonical_path' => $anecaOverLegacyInvalidPath,
            'aneca_canonical_ready' => true,
            'aneca_canonical_validation_status' => AnecaCanonicalResultValidator::STATUS_INVALIDO,
            'validation_status' => LegacyPipelineResultValidator::STATUS_INVALIDO,
            'validation_errors' => ['legacy_status_invalido'],
            'validation_warnings' => [],
        ]
    );
    $lookupAnecaInvalidLegacyInvalid = $cacheAnecaInvalidLegacyInvalid->resolveByHash($hashAnecaInvalidLegacyInvalid, 'aneca_invalid_legacy_invalid.pdf');
    registerCheck(
        $result,
        'cache_meta_aneca_no_utilizable_y_legacy_invalido_invalida_controlado',
        empty($lookupAnecaInvalidLegacyInvalid['cache_hit'])
            && (string)($lookupAnecaInvalidLegacyInvalid['cache_estado'] ?? '') === 'no_valida'
            && (string)($lookupAnecaInvalidLegacyInvalid['motivo_invalidation'] ?? '') === 'validation_status_invalido'
            && (string)($lookupAnecaInvalidLegacyInvalid['operational_criterion'] ?? '') === 'legacy_fallback'
    );

    // 12) Worker usa criterio operativo ANECA en cache cuando artefacto canonico esta listo y usable.
    $anecaWorkerCacheDir = $baseDir . '/cache_worker_aneca';
    if (!is_dir($anecaWorkerCacheDir) && !mkdir($anecaWorkerCacheDir, 0777, true) && !is_dir($anecaWorkerCacheDir)) {
        throw new Exception('No se pudo crear cache de prueba worker ANECA.');
    }
    $anecaWorkerCache = new ProcessingCache($anecaWorkerCacheDir, $versionsV1);
    $anecaWorkerHash = $anecaWorkerCache->calculatePdfHash($samplePdf);
    $anecaFixturePath = $baseDir . '/aneca.worker.fixture.json';
    $anecaFixtureJson = json_encode(buildAnecaPreferredFixture(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($anecaFixtureJson) || @file_put_contents($anecaFixturePath, $anecaFixtureJson) === false) {
        throw new Exception('No se pudo escribir fixture ANECA para smoke worker.');
    }
    $anecaWorkerCache->saveByHash(
        $anecaWorkerHash,
        'aneca_worker.pdf',
        $resultadoValido,
        'texto aneca worker',
        [
            'estado_cache' => 'valida',
            'aneca_canonical_path' => $anecaFixturePath,
            'aneca_canonical_ready' => true,
            'aneca_canonical_validation_status' => AnecaCanonicalResultValidator::STATUS_VALIDO,
            'validation_status' => LegacyPipelineResultValidator::STATUS_VALIDO,
            'validation_errors' => [],
            'validation_warnings' => [],
        ]
    );
    $anecaWorkerJobsDir = $baseDir . '/jobs_worker_aneca';
    if (!is_dir($anecaWorkerJobsDir) && !mkdir($anecaWorkerJobsDir, 0777, true) && !is_dir($anecaWorkerJobsDir)) {
        throw new Exception('No se pudo crear jobs de prueba worker ANECA.');
    }
    $anecaWorkerPdfPath = $baseDir . '/worker_aneca.pdf';
    if (!copy($samplePdf, $anecaWorkerPdfPath)) {
        throw new Exception('No se pudo preparar PDF para smoke worker ANECA.');
    }
    $anecaWorkerQueue = new ProcessingJobQueue($anecaWorkerJobsDir);
    $anecaWorkerJob = $anecaWorkerQueue->createJobForPdfPath(
        $anecaWorkerPdfPath,
        ['archivo_original' => 'aneca_worker.pdf']
    );
    $anecaWorker = new ProcessingJobWorker($anecaWorkerQueue, $anecaWorkerCache);
    $anecaWorkerFinal = $anecaWorker->processJobById((string)($anecaWorkerJob['id'] ?? ''));
    registerCheck(
        $result,
        'worker_cache_prioriza_aneca_cuando_lista',
        !empty($anecaWorkerFinal['cache_hit'])
            && (string)($anecaWorkerFinal['resultado_principal_formato'] ?? '') === 'aneca'
            && jobLogContains($anecaWorkerFinal, 'decision_operativa_cache criterio=aneca_operativo formato=aneca')
    );
    $result['jobs'][] = summarizeJob($anecaWorkerFinal);

    // 13) Worker fallback a legacy si ANECA no esta lista y mantiene compatibilidad con cache vieja.
    $legacyWorkerJobsDir = $baseDir . '/jobs_worker_legacy';
    if (!is_dir($legacyWorkerJobsDir) && !mkdir($legacyWorkerJobsDir, 0777, true) && !is_dir($legacyWorkerJobsDir)) {
        throw new Exception('No se pudo crear jobs de prueba worker legacy.');
    }
    $legacyWorkerPdfPath = $baseDir . '/worker_legacy.pdf';
    if (!copy($samplePdf, $legacyWorkerPdfPath)) {
        throw new Exception('No se pudo preparar PDF para smoke worker legacy.');
    }
    $legacyWorkerQueue = new ProcessingJobQueue($legacyWorkerJobsDir);
    $legacyWorkerJob = $legacyWorkerQueue->createJobForPdfPath(
        $legacyWorkerPdfPath,
        ['archivo_original' => 'legacy_only.pdf']
    );
    $legacyWorker = new ProcessingJobWorker($legacyWorkerQueue, $legacyOnlyCache);
    $legacyWorkerFinal = $legacyWorker->processJobById((string)($legacyWorkerJob['id'] ?? ''));
    registerCheck(
        $result,
        'worker_cache_fallback_legacy_si_aneca_no_lista',
        !empty($legacyWorkerFinal['cache_hit'])
            && (string)($legacyWorkerFinal['resultado_principal_formato'] ?? '') === 'legacy'
            && jobLogContains($legacyWorkerFinal, 'decision_operativa_cache criterio=legacy_fallback formato=legacy')
            && jobLogContains($legacyWorkerFinal, 'fallback=aneca_not_ready')
    );
    $result['jobs'][] = summarizeJob($legacyWorkerFinal);

    // 14) Service cache-hit ANECA utilizable: aplica aneca_operativo.
    $serviceAnecaCacheDir = $baseDir . '/cache_service_aneca';
    if (!is_dir($serviceAnecaCacheDir) && !mkdir($serviceAnecaCacheDir, 0777, true) && !is_dir($serviceAnecaCacheDir)) {
        throw new Exception('No se pudo crear cache de prueba service ANECA.');
    }
    $serviceAnecaCache = new ProcessingCache($serviceAnecaCacheDir, $versionsV1);
    $serviceAnecaHash = $serviceAnecaCache->calculatePdfHash($samplePdf);
    $serviceAnecaFixturePath = $baseDir . '/aneca.service.fixture.json';
    $serviceAnecaFixtureJson = json_encode(buildAnecaPreferredFixture(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($serviceAnecaFixtureJson) || @file_put_contents($serviceAnecaFixturePath, $serviceAnecaFixtureJson) === false) {
        throw new Exception('No se pudo escribir fixture ANECA para smoke service.');
    }
    $serviceAnecaCache->saveByHash(
        $serviceAnecaHash,
        'service_aneca.pdf',
        $resultadoValido,
        'texto service aneca',
        [
            'estado_cache' => 'valida',
            'aneca_canonical_path' => $serviceAnecaFixturePath,
            'aneca_canonical_ready' => true,
            'aneca_canonical_validation_status' => AnecaCanonicalResultValidator::STATUS_VALIDO,
            'validation_status' => LegacyPipelineResultValidator::STATUS_VALIDO,
            'validation_errors' => [],
            'validation_warnings' => [],
        ]
    );
    $serviceAnecaJobsDir = $baseDir . '/jobs_service_aneca';
    $serviceAnecaPdfDir = $baseDir . '/pdfs_service_aneca';
    foreach ([$serviceAnecaJobsDir, $serviceAnecaPdfDir] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new Exception('No se pudo crear directorio de prueba service ANECA: ' . $dir);
        }
    }
    $serviceAnecaQueue = new ProcessingJobQueue($serviceAnecaJobsDir);
    $serviceAneca = new CvProcessingJobService($serviceAnecaQueue, $serviceAnecaPdfDir, $serviceAnecaCache);
    $serviceAnecaUpload = createUploadFixture($samplePdf, $baseDir, 'service_aneca.pdf');
    $serviceAnecaEnqueued = $serviceAneca->enqueueFromUpload($serviceAnecaUpload, false);
    $serviceAnecaJob = is_array($serviceAnecaEnqueued['job'] ?? null) ? $serviceAnecaEnqueued['job'] : [];
    registerCheck(
        $result,
        'service_cache_hit_prioriza_aneca_cuando_lista',
        !empty($serviceAnecaEnqueued['cache_hit'])
            && !empty($serviceAnecaJob['cache_hit'])
            && (string)($serviceAnecaJob['resultado_principal_formato'] ?? '') === 'aneca'
            && jobLogContains($serviceAnecaJob, 'decision_operativa_service_cache_hit criterio=aneca_operativo formato=aneca')
    );
    if (!empty($serviceAnecaJob)) {
        $result['jobs'][] = summarizeJob($serviceAnecaJob);
    }

    // 15) Service cache-hit sin ANECA lista: fallback legacy.
    $serviceLegacyJobsDir = $baseDir . '/jobs_service_legacy';
    $serviceLegacyPdfDir = $baseDir . '/pdfs_service_legacy';
    foreach ([$serviceLegacyJobsDir, $serviceLegacyPdfDir] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new Exception('No se pudo crear directorio de prueba service legacy: ' . $dir);
        }
    }
    $serviceLegacyQueue = new ProcessingJobQueue($serviceLegacyJobsDir);
    $serviceLegacy = new CvProcessingJobService($serviceLegacyQueue, $serviceLegacyPdfDir, $legacyOnlyCache);
    $serviceLegacyUpload = createUploadFixture($samplePdf, $baseDir, 'legacy_only.pdf');
    $serviceLegacyEnqueued = $serviceLegacy->enqueueFromUpload($serviceLegacyUpload, false);
    $serviceLegacyJob = is_array($serviceLegacyEnqueued['job'] ?? null) ? $serviceLegacyEnqueued['job'] : [];
    registerCheck(
        $result,
        'service_cache_hit_fallback_legacy_si_aneca_no_lista',
        !empty($serviceLegacyEnqueued['cache_hit'])
            && !empty($serviceLegacyJob['cache_hit'])
            && (string)($serviceLegacyJob['resultado_principal_formato'] ?? '') === 'legacy'
            && jobLogContains($serviceLegacyJob, 'decision_operativa_service_cache_hit criterio=legacy_fallback formato=legacy')
            && jobLogContains($serviceLegacyJob, 'fallback=aneca_not_ready')
    );
    if (!empty($serviceLegacyJob)) {
        $result['jobs'][] = summarizeJob($serviceLegacyJob);
    }

    registerCheck(
        $result,
        'coherencia_basica_service_worker_en_fallback_legacy',
        jobLogContains($legacyWorkerFinal, 'decision_operativa_cache criterio=legacy_fallback formato=legacy')
            && !empty($serviceLegacyJob)
            && jobLogContains($serviceLegacyJob, 'decision_operativa_service_cache_hit criterio=legacy_fallback formato=legacy')
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

function jobLogContains(array $job, string $needle): bool
{
    $logPath = is_string($job['log_path'] ?? null) ? trim((string)$job['log_path']) : '';
    if ($logPath === '' || !is_file($logPath)) {
        return false;
    }

    $raw = @file_get_contents($logPath);
    if (!is_string($raw) || $raw === '') {
        return false;
    }

    return strpos($raw, $needle) !== false;
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
        'resultado_principal_formato' => $job['resultado_principal_formato'] ?? null,
        'resultado_principal_path' => $job['resultado_principal_path'] ?? null,
        'aneca_canonical_path' => $job['aneca_canonical_path'] ?? null,
        'aneca_canonical_ready' => $job['aneca_canonical_ready'] ?? null,
        'aneca_canonical_validation_status' => $job['aneca_canonical_validation_status'] ?? null,
        'tiempo_total_ms' => $job['tiempo_total_ms'] ?? null,
    ];
}

function buildAnecaPreferredFixture(): array
{
    return [
        'bloque_1' => [],
        'bloque_2' => [],
        'bloque_3' => [],
        'bloque_4' => [],
        'metadatos_extraccion' => [
            'comite' => 'SMOKE',
            'subcomite' => null,
            'archivo_pdf' => 'demo.pdf',
            'fecha_extraccion' => gmdate('c'),
            'version_esquema' => '1.0',
            'requiere_revision_manual' => false,
            'origen_adaptacion' => 'smoke_jobs_queue',
        ],
        'archivo_pdf' => 'demo.pdf',
        'json_generado' => 'demo.aneca.canonico.json',
        'texto_extraido' => 'contenido de prueba para salida preferente',
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
