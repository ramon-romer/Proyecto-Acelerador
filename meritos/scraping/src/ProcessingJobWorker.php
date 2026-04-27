<?php

require_once __DIR__ . '/Pipeline.php';
require_once __DIR__ . '/ProcessingJobQueue.php';
require_once __DIR__ . '/ProcessingCache.php';
require_once __DIR__ . '/LegacyPipelineResultValidator.php';
require_once __DIR__ . '/AnecaCanonicalResultValidator.php';
require_once __DIR__ . '/OperationalArtifactDecisionResolver.php';

class ProcessingJobWorker
{
    private $queue;
    private $cache;
    private $logsDir;
    private $legacyResultValidator;
    private $anecaCanonicalValidator;

    public function __construct(?ProcessingJobQueue $queue = null, ?ProcessingCache $cache = null)
    {
        $this->queue = $queue ?? new ProcessingJobQueue();
        $this->cache = $cache ?? new ProcessingCache();
        $this->logsDir = __DIR__ . '/../output/logs';
        $this->legacyResultValidator = new LegacyPipelineResultValidator();
        $this->anecaCanonicalValidator = new AnecaCanonicalResultValidator();
    }

    public function processNextPendingJob(): ?array
    {
        $claimed = $this->queue->claimNextPendingJob();
        if ($claimed === null) {
            return null;
        }

        $jobId = (string)($claimed['id'] ?? '');
        if ($jobId === '') {
            return null;
        }

        return $this->processJobById($jobId, true);
    }

    public function processJobById(string $jobId, bool $alreadyClaimed = false): array
    {
        $job = $this->queue->getJob($jobId);
        if (!is_array($job)) {
            throw new Exception('Job no encontrado: ' . $jobId);
        }

        $estadoActual = (string)($job['estado'] ?? '');
        if (in_array($estadoActual, ['completado', 'error', 'error_parcial'], true)) {
            return $job;
        }

        $pdfPath = (string)($job['pdf_path'] ?? '');
        if ($pdfPath === '' || !is_file($pdfPath)) {
            $this->queue->appendLog($jobId, 'pdf_no_encontrado path=' . $pdfPath);
            return $this->queue->markError(
                $jobId,
                'No se encontro el PDF del job para procesar.',
                null,
                null,
                $this->elapsedMsFromJobStart($job)
            );
        }

        $startedAt = microtime(true);
        $tracePath = null;
        $pipelineLogPath = null;
        $hashPdf = '';
        $cacheLookup = $this->defaultCacheLookup();
        $lastValidation = null;

        try {
            $hashPdf = $this->resolveHashFromJobOrFile($job, $pdfPath);
            $cacheLookup = $this->cache->resolveByHash(
                $hashPdf,
                (string)($job['archivo_original'] ?? $job['archivo_pdf'] ?? basename($pdfPath))
            );

            $this->applyCacheContextToJob($jobId, $hashPdf, $cacheLookup, false);

            if (!empty($cacheLookup['cache_hit']) && is_array($cacheLookup['resultado_json'] ?? null)) {
                $this->queue->appendLog(
                    $jobId,
                    'cache_hit cache_key=' . (string)($cacheLookup['cache_key'] ?? '')
                    . ' cache_estado=' . (string)($cacheLookup['cache_estado'] ?? '')
                    . ' resultado_principal_formato=' . (string)($cacheLookup['resultado_principal_formato'] ?? 'legacy')
                    . ' aneca_canonical_ready=' . (!empty($cacheLookup['aneca_canonical_ready']) ? 'true' : 'false')
                    . ' aneca_canonical_validation_status=' . (string)($cacheLookup['aneca_canonical_validation_status'] ?? '')
                );

                $this->queue->setPhase($jobId, 'validando_resultado', 98, 'cache_hit');

                $cachedResult = $cacheLookup['resultado_json'];
                $lastValidation = $this->validateLegacyResult($cachedResult);
                $this->applyValidationContextToJob($jobId, $lastValidation);
                $cacheDecision = $this->buildOperationalDecision(
                    [
                        'resultado_principal_formato' => $cacheLookup['resultado_principal_formato'] ?? null,
                        'resultado_principal_path' => $cacheLookup['resultado_principal_path'] ?? null,
                        'aneca_canonical_path' => $cacheLookup['aneca_canonical_path'] ?? null,
                        'aneca_canonical_ready' => !empty($cacheLookup['aneca_canonical_ready']),
                        'aneca_canonical_validation_status' => $cacheLookup['aneca_canonical_validation_status'] ?? null,
                    ],
                    $lastValidation
                );

                $this->queue->appendLog(
                    $jobId,
                    'decision_operativa_cache ' . $this->describeOperationalDecision($cacheDecision)
                );

                if (empty($cacheDecision['cacheable'])) {
                    $cacheLookup['cache_hit'] = false;
                    $cacheLookup['cache_estado'] = 'no_valida';
                    $cacheLookup['motivo_invalidation'] = $cacheDecision['invalidation_reason'] ?? 'resultado_no_utilizable';

                    $this->queue->appendLog(
                        $jobId,
                        'cache_hit_descartado motivo=' . (string)($cacheLookup['motivo_invalidation'] ?? 'resultado_no_utilizable')
                        . ' detalle=' . $this->describeOperationalDecision($cacheDecision)
                    );
                } else {
                    $meta = is_array($cacheLookup['metadata'] ?? null) ? $cacheLookup['metadata'] : [];
                    $tracePathCache = $this->safeString($meta['trace_path'] ?? null);
                    $pipelineLogPathCache = $this->safeString($meta['pipeline_log_path'] ?? $meta['log_path'] ?? null);
                    $hasPartialCache = !empty($meta['error_parcial']) || empty($cacheDecision['is_clean']);
                    $elapsedMsCache = round((microtime(true) - $startedAt) * 1000, 2);
                    $cacheMessage = $this->buildOperationalMessageForCompletion(
                        $lastValidation,
                        $cacheDecision,
                        'Resultado servido desde cache'
                    );

                    $this->queue->markCompleted(
                        $jobId,
                        $cachedResult,
                        $tracePathCache,
                        $pipelineLogPathCache,
                        $elapsedMsCache,
                        $hasPartialCache,
                        $hasPartialCache ? $cacheMessage : null
                    );

                    $jobFinalCache = $this->applyCacheContextToJob($jobId, $hashPdf, $cacheLookup, true);
                    $jobFinalCache = $this->applyValidationContextToJob($jobId, $lastValidation);

                    $this->queue->appendLog(
                        $jobId,
                        'fin_worker_cache estado=' . (string)($jobFinalCache['estado'] ?? '')
                        . ' tiempo_total_ms=' . $elapsedMsCache
                        . ' criterio_operativo=' . (string)($cacheDecision['criterion'] ?? 'legacy_fallback')
                        . ' formato_operativo=' . (string)($cacheDecision['artifact_format'] ?? 'legacy')
                    );

                    return $jobFinalCache;
                }
            } else {
                $cacheEstadoLookup = (string)($cacheLookup['cache_estado'] ?? 'miss');
                $cacheMotivoLookup = (string)($cacheLookup['motivo_invalidation'] ?? 'cache_no_encontrada');

                if ($cacheEstadoLookup === 'obsoleta' && strpos($cacheMotivoLookup, 'version_') === 0) {
                    $this->queue->appendLog(
                        $jobId,
                        'cache_obsoleta_por_cambio_version motivo=' . $cacheMotivoLookup
                    );
                    error_log(
                        '[meritos-scraping] cache_obsoleta_worker hash=' . $hashPdf
                        . ' motivo=' . $cacheMotivoLookup
                    );
                }

                $this->queue->appendLog(
                    $jobId,
                    'cache_miss cache_estado=' . $cacheEstadoLookup
                    . ' motivo=' . $cacheMotivoLookup
                );
            }

            if (!$alreadyClaimed || $estadoActual === 'pendiente') {
                $this->queue->setPhase($jobId, 'procesando_pdf', 5, 'procesando_pdf');
            }

            $this->queue->appendLog($jobId, 'inicio_worker hash_pdf=' . $hashPdf . ' pdf=' . basename($pdfPath));

            $pipeline = new Pipeline();
            $phaseCallback = function (string $estado, int $progreso, string $fase, array $context = []) use ($jobId): void {
                $this->queue->setPhase($jobId, $estado, $progreso, $fase);

                $detalle = '';
                if (!empty($context)) {
                    $detalleJson = json_encode($context, JSON_UNESCAPED_UNICODE);
                    if (is_string($detalleJson)) {
                        $detalle = ' contexto=' . $detalleJson;
                    }
                }

                $this->queue->appendLog(
                    $jobId,
                    'fase_callback estado=' . $estado . ' progreso=' . $progreso . ' fase=' . $fase . $detalle
                );
            };

            $resultado = $pipeline->procesar($pdfPath, $phaseCallback);

            $this->queue->setPhase($jobId, 'calculando_puntuacion', 90, 'calculando_puntuacion');
            $this->queue->appendLog($jobId, 'fase_calculando_puntuacion placeholder');

            $this->queue->setPhase($jobId, 'validando_resultado', 95, 'validando_resultado');

            $lastValidation = $this->validateLegacyResult($resultado);
            $this->queue->appendLog(
                $jobId,
                'validacion_resultado ' . $this->legacyResultValidator->summarize($lastValidation)
            );
            $this->applyValidationContextToJob($jobId, $lastValidation);

            $artifacts = $this->locatePipelineArtifacts($pdfPath, $startedAt);
            $tracePath = $artifacts['trace_path'];
            $pipelineLogPath = $artifacts['pipeline_log_path'];
            $canonicalContext = $this->resolveAnecaCanonicalContext($pdfPath, $tracePath, $startedAt);
            $runtimePreferredFormat = !empty($canonicalContext['aneca_canonical_ready'])
                && is_string($canonicalContext['aneca_canonical_path'] ?? null)
                && is_file((string)$canonicalContext['aneca_canonical_path'])
                ? 'aneca'
                : 'legacy';
            $runtimePreferredPath = $runtimePreferredFormat === 'aneca'
                ? $this->safeString($canonicalContext['aneca_canonical_path'] ?? null)
                : null;
            $runtimeDecision = $this->buildOperationalDecision(
                [
                    'resultado_principal_formato' => $runtimePreferredFormat,
                    'resultado_principal_path' => $runtimePreferredPath,
                    'aneca_canonical_path' => $canonicalContext['aneca_canonical_path'] ?? null,
                    'aneca_canonical_ready' => !empty($canonicalContext['aneca_canonical_ready']),
                    'aneca_canonical_validation_status' => $canonicalContext['aneca_canonical_validation_status'] ?? null,
                ],
                $lastValidation
            );

            $this->queue->appendLog(
                $jobId,
                'decision_operativa_runtime ' . $this->describeOperationalDecision($runtimeDecision)
            );

            if (empty($runtimeDecision['cacheable'])) {
                throw new Exception('Resultado no utilizable: ' . $this->describeOperationalDecision($runtimeDecision));
            }

            $hasPartialError = $this->detectPartialError($tracePath) || empty($runtimeDecision['is_clean']);
            $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);
            $completionMessage = $hasPartialError
                ? $this->buildOperationalMessageForCompletion(
                    $lastValidation,
                    $runtimeDecision,
                    'Proceso finalizado con observaciones'
                )
                : null;

            $cacheSaveInfo = $this->persistCacheForJob(
                $hashPdf,
                $job,
                $resultado,
                $tracePath,
                $pipelineLogPath,
                $elapsedMs,
                $hasPartialError,
                $lastValidation,
                $canonicalContext,
                $runtimeDecision
            );

            $jobFinal = $this->queue->markCompleted(
                $jobId,
                $resultado,
                $tracePath,
                $pipelineLogPath,
                $elapsedMs,
                $hasPartialError,
                $completionMessage
            );

            $jobFinal = $this->queue->updateJob(
                $jobId,
                function (array $current) use ($hashPdf, $cacheSaveInfo, $canonicalContext): array {
                    $current['hash_pdf'] = $hashPdf;
                    $current['cache_hit'] = false;
                    $current['cache_key'] = $cacheSaveInfo['cache_key'] ?? ($current['cache_key'] ?? null);
                    $current['cache_estado'] = $cacheSaveInfo['cache_estado'] ?? ($current['cache_estado'] ?? null);
                    $current['motivo_invalidation'] = $cacheSaveInfo['motivo_invalidation'] ?? null;
                    $current['cache_guardada'] = !empty($cacheSaveInfo['cache_saved']);
                    $current['cache_meta_path'] = $cacheSaveInfo['meta_path'] ?? null;
                    $current['cache_result_path'] = $cacheSaveInfo['result_path'] ?? null;
                    $current['cache_text_path'] = $cacheSaveInfo['text_path'] ?? null;
                    $current['aneca_canonical_path'] = $cacheSaveInfo['aneca_canonical_path']
                        ?? ($canonicalContext['aneca_canonical_path'] ?? ($current['aneca_canonical_path'] ?? null));
                    $current['aneca_canonical_ready'] = !empty($cacheSaveInfo['aneca_canonical_ready'])
                        || !empty($canonicalContext['aneca_canonical_ready']);
                    $current['aneca_canonical_validation_status'] = $cacheSaveInfo['aneca_canonical_validation_status']
                        ?? ($canonicalContext['aneca_canonical_validation_status']
                        ?? ($current['aneca_canonical_validation_status'] ?? null));
                    return $current;
                }
            );
            $jobFinal = $this->applyValidationContextToJob($jobId, $lastValidation);

            $this->queue->appendLog(
                $jobId,
                'fin_worker estado=' . (string)($jobFinal['estado'] ?? '')
                . ' tiempo_total_ms=' . $elapsedMs
                . ' cache_guardada=' . (!empty($cacheSaveInfo['cache_saved']) ? 'true' : 'false')
                . ' criterio_operativo=' . (string)($runtimeDecision['criterion'] ?? 'legacy_fallback')
                . ' formato_operativo=' . (string)($runtimeDecision['artifact_format'] ?? 'legacy')
            );

            return $jobFinal;
        } catch (Throwable $e) {
            $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);
            $normalizedValidation = is_array($lastValidation)
                ? $this->legacyResultValidator->normalizeValidation($lastValidation)
                : null;
            $validationStatus = is_array($normalizedValidation) ? ($normalizedValidation['validation_status'] ?? null) : null;
            $validationErrors = is_array($normalizedValidation) ? ($normalizedValidation['validation_errors'] ?? []) : [];
            $validationWarnings = is_array($normalizedValidation) ? ($normalizedValidation['validation_warnings'] ?? []) : [];

            $jobError = $this->queue->markError(
                $jobId,
                $e->getMessage(),
                $tracePath,
                $pipelineLogPath,
                $elapsedMs,
                false
            );

            $jobError = $this->queue->updateJob(
                $jobId,
                function (array $current) use (
                    $hashPdf,
                    $cacheLookup,
                    $validationStatus,
                    $validationErrors,
                    $validationWarnings
                ): array {
                    if ($hashPdf !== '') {
                        $current['hash_pdf'] = $hashPdf;
                    }

                    $current['cache_hit'] = false;
                    $current['cache_key'] = $cacheLookup['cache_key'] ?? ($current['cache_key'] ?? null);
                    $current['cache_estado'] = $cacheLookup['cache_estado'] ?? ($current['cache_estado'] ?? null);
                    $current['motivo_invalidation'] = $cacheLookup['motivo_invalidation'] ?? null;
                    $current['cache_guardada'] = false;
                    $current['aneca_canonical_path'] = $cacheLookup['aneca_canonical_path'] ?? ($current['aneca_canonical_path'] ?? null);
                    $current['aneca_canonical_ready'] = !empty($cacheLookup['aneca_canonical_ready']);
                    $current['aneca_canonical_validation_status'] = $cacheLookup['aneca_canonical_validation_status']
                        ?? ($current['aneca_canonical_validation_status'] ?? null);
                    $current['validation_status'] = $validationStatus ?? ($current['validation_status'] ?? null);
                    $current['validation_errors'] = is_array($validationErrors) ? $validationErrors : [];
                    $current['validation_warnings'] = is_array($validationWarnings) ? $validationWarnings : [];
                    return $current;
                }
            );

            $this->queue->appendLog($jobId, 'cache_no_guardada_por_error');
            $this->queue->appendLog($jobId, 'fin_worker_error mensaje=' . $e->getMessage());

            return $jobError;
        }
    }

    private function defaultCacheLookup(): array
    {
        return [
            'cache_hit' => false,
            'cache_key' => null,
            'cache_estado' => 'miss',
            'motivo_invalidation' => null,
            'metadata' => null,
            'resultado_json' => null,
            'texto_extraido' => null,
            'meta_path' => null,
            'resultado_json_path' => null,
            'texto_extraido_path' => null,
            'aneca_canonical_path' => null,
            'aneca_canonical_ready' => false,
            'aneca_canonical_validation_status' => null,
            'aneca_canonical_validation_errors' => [],
            'aneca_canonical_validation_warnings' => [],
            'aneca_canonical_json' => null,
            'resultado_principal_formato' => 'legacy',
            'resultado_principal_path' => null,
            'validation_status' => null,
            'validation_errors' => [],
            'validation_warnings' => [],
        ];
    }

    private function validateLegacyResult(array $resultado): array
    {
        return $this->legacyResultValidator->normalizeValidation(
            $this->legacyResultValidator->validate($resultado)
        );
    }

    private function locatePipelineArtifacts(string $pdfPath, float $startedAt): array
    {
        $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);

        $tracePattern = $this->logsDir . '/' . $baseName . '.*.trace.json';
        $pipelinePattern = $this->logsDir . '/' . $baseName . '.*.pipeline.log';

        return [
            'trace_path' => $this->pickLatestArtifact($tracePattern, $startedAt),
            'pipeline_log_path' => $this->pickLatestArtifact($pipelinePattern, $startedAt),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveAnecaCanonicalContext(string $pdfPath, ?string $tracePath, float $startedAt): array
    {
        $context = [
            'aneca_canonical_path' => null,
            'aneca_canonical_ready' => false,
            'aneca_canonical_validation_status' => null,
            'aneca_canonical_validation_errors' => [],
            'aneca_canonical_validation_warnings' => [],
            'aneca_canonical_json' => null,
        ];

        $normalizationFromTrace = null;
        if ($tracePath !== null && is_file($tracePath)) {
            $trace = $this->safeReadJsonObject($tracePath);
            if (is_array($trace)) {
                $normalizationFromTrace = is_array($trace['normalizacion_contrato_canonico_aneca'] ?? null)
                    ? $trace['normalizacion_contrato_canonico_aneca']
                    : null;
            }
        }

        $candidatePaths = [];
        if (is_array($normalizationFromTrace)) {
            $traceFile = $this->safeString($normalizationFromTrace['archivo_json_canonico'] ?? null);
            if ($traceFile !== null) {
                $candidatePaths[] = __DIR__ . '/../output/json/' . basename($traceFile);
            }
        }

        $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);
        $candidatePaths[] = __DIR__ . '/../output/json/' . $baseName . '.aneca.canonico.json';
        $candidatePaths = array_values(array_unique($candidatePaths));

        $threshold = (int)floor($startedAt) - 5;
        $selectedPath = null;
        foreach ($candidatePaths as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $mtime = @filemtime($candidate);
            if (is_int($mtime) && $mtime >= $threshold) {
                $selectedPath = $candidate;
                break;
            }
        }

        if ($selectedPath === null) {
            foreach ($candidatePaths as $candidate) {
                if (is_file($candidate)) {
                    $selectedPath = $candidate;
                    break;
                }
            }
        }

        if ($selectedPath !== null) {
            $validation = $this->anecaCanonicalValidator->validateFile($selectedPath);
            $context['aneca_canonical_path'] = $selectedPath;
            $context['aneca_canonical_ready'] = !empty($validation['aneca_canonical_ready']);
            $context['aneca_canonical_validation_status'] = $this->safeString(
                $validation['aneca_canonical_validation_status'] ?? null
            );
            $context['aneca_canonical_validation_errors'] = $this->safeStringList(
                $validation['aneca_canonical_validation_errors'] ?? []
            );
            $context['aneca_canonical_validation_warnings'] = $this->safeStringList(
                $validation['aneca_canonical_validation_warnings'] ?? []
            );
            $context['aneca_canonical_json'] = $this->safeReadJsonObject($selectedPath);

            return $context;
        }

        if (is_array($normalizationFromTrace)) {
            $traceStatus = $this->safeString($normalizationFromTrace['validation_status'] ?? null);
            $context['aneca_canonical_validation_status'] = $traceStatus;
            $context['aneca_canonical_validation_errors'] = $this->safeStringList(
                $normalizationFromTrace['validation_errors'] ?? []
            );
            $context['aneca_canonical_validation_warnings'] = $this->safeStringList(
                $normalizationFromTrace['validation_warnings'] ?? []
            );

            if (!empty($normalizationFromTrace['ready'])) {
                $context['aneca_canonical_ready'] = false;
                if ($traceStatus === null) {
                    $context['aneca_canonical_validation_status'] = 'invalido';
                }
                $context['aneca_canonical_validation_errors'][] = 'aneca_canonical_path_inexistente';
            }
        }

        return $context;
    }

    private function pickLatestArtifact(string $pattern, float $startedAt): ?string
    {
        $files = glob($pattern) ?: [];
        if (empty($files)) {
            return null;
        }

        $threshold = (int)floor($startedAt) - 5;
        $eligible = [];

        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if (!is_int($mtime)) {
                continue;
            }

            if ($mtime >= $threshold) {
                $eligible[$file] = $mtime;
            }
        }

        if (empty($eligible)) {
            foreach ($files as $file) {
                $mtime = @filemtime($file);
                if (is_int($mtime)) {
                    $eligible[$file] = $mtime;
                }
            }
        }

        if (empty($eligible)) {
            return null;
        }

        arsort($eligible, SORT_NUMERIC);
        return (string)array_key_first($eligible);
    }

    private function detectPartialError(?string $tracePath): bool
    {
        if ($tracePath === null || !is_file($tracePath)) {
            return false;
        }

        $json = @file_get_contents($tracePath);
        if (!is_string($json) || $json === '') {
            return false;
        }

        $trace = json_decode($json, true);
        if (!is_array($trace)) {
            return false;
        }

        foreach ((array)($trace['trazabilidad_paginas'] ?? []) as $pagina) {
            if (!empty($pagina['error_parcial'])) {
                return true;
            }
        }

        return false;
    }

    private function elapsedMsFromJobStart(array $job): float
    {
        $fechaInicio = (string)($job['fecha_inicio'] ?? '');
        if ($fechaInicio === '') {
            return 0.0;
        }

        $ts = strtotime($fechaInicio);
        if (!is_int($ts) || $ts <= 0) {
            return 0.0;
        }

        return round((microtime(true) - (float)$ts) * 1000, 2);
    }

    private function resolveHashFromJobOrFile(array $job, string $pdfPath): string
    {
        $hashPdf = strtolower(trim((string)($job['hash_pdf'] ?? '')));
        if (preg_match('/^[a-f0-9]{64}$/', $hashPdf) === 1) {
            return $hashPdf;
        }

        return $this->cache->calculatePdfHash($pdfPath);
    }

    private function applyCacheContextToJob(string $jobId, string $hashPdf, array $cacheLookup, bool $cacheHit): array
    {
        $versions = $this->cache->getVersions();
        $cacheMeta = is_array($cacheLookup['metadata'] ?? null) ? $cacheLookup['metadata'] : [];
        $validationStatus = $this->safeString(
            $cacheLookup['validation_status'] ?? ($cacheMeta['validation_status'] ?? null)
        );
        $validationErrors = $this->safeStringList(
            $cacheLookup['validation_errors'] ?? ($cacheMeta['validation_errors'] ?? [])
        );
        $validationWarnings = $this->safeStringList(
            $cacheLookup['validation_warnings'] ?? ($cacheMeta['validation_warnings'] ?? [])
        );
        $canonicalPath = $this->safeString(
            $cacheLookup['aneca_canonical_path'] ?? ($cacheMeta['aneca_canonical_path'] ?? null)
        );
        $canonicalReady = !empty($cacheLookup['aneca_canonical_ready']) || !empty($cacheMeta['aneca_canonical_ready']);
        $canonicalValidationStatus = $this->safeString(
            $cacheLookup['aneca_canonical_validation_status'] ?? ($cacheMeta['aneca_canonical_validation_status'] ?? null)
        );

        return $this->queue->updateJob(
            $jobId,
            function (array $current) use (
                $hashPdf,
                $cacheLookup,
                $cacheHit,
                $versions,
                $cacheMeta,
                $validationStatus,
                $validationErrors,
                $validationWarnings,
                $canonicalPath,
                $canonicalReady,
                $canonicalValidationStatus
            ): array {
                $current['hash_pdf'] = $hashPdf;
                $current['cache_hit'] = $cacheHit;
                $current['cache_key'] = $cacheLookup['cache_key'] ?? ($current['cache_key'] ?? null);
                $current['cache_estado'] = $cacheLookup['cache_estado'] ?? ($current['cache_estado'] ?? null);
                $current['motivo_invalidation'] = $cacheLookup['motivo_invalidation'] ?? null;

                $current['version_pipeline'] = $versions['version_pipeline'] ?? ($current['version_pipeline'] ?? null);
                $current['version_baremo'] = $versions['version_baremo'] ?? ($current['version_baremo'] ?? null);
                $current['version_schema'] = $versions['version_schema'] ?? ($current['version_schema'] ?? null);

                $current['cache_meta_path'] = $cacheLookup['meta_path'] ?? ($current['cache_meta_path'] ?? null);
                $current['cache_result_path'] = $cacheLookup['resultado_json_path'] ?? ($current['cache_result_path'] ?? null);
                $current['cache_text_path'] = $cacheLookup['texto_extraido_path'] ?? ($current['cache_text_path'] ?? null);
                $current['aneca_canonical_path'] = $canonicalPath ?? ($current['aneca_canonical_path'] ?? null);
                $current['aneca_canonical_ready'] = $canonicalReady;
                $current['aneca_canonical_validation_status'] = $canonicalValidationStatus
                    ?? ($current['aneca_canonical_validation_status'] ?? null);
                $current['validation_status'] = $validationStatus ?? ($current['validation_status'] ?? null);
                $current['validation_errors'] = $validationErrors;
                $current['validation_warnings'] = $validationWarnings;

                if ($cacheHit) {
                    $current['cache_guardada'] = true;
                    $tracePath = $this->safeString($cacheMeta['trace_path'] ?? null);
                    $pipelineLogPath = $this->safeString($cacheMeta['pipeline_log_path'] ?? $cacheMeta['log_path'] ?? null);

                    if ($tracePath !== null) {
                        $current['trace_path'] = $tracePath;
                    }

                    if ($pipelineLogPath !== null) {
                        $current['pipeline_log_path'] = $pipelineLogPath;
                    }
                }

                return $current;
            }
        );
    }

    private function persistCacheForJob(
        string $hashPdf,
        array $job,
        array $resultado,
        ?string $tracePath,
        ?string $pipelineLogPath,
        float $tiempoTotalMs,
        bool $hasPartialError,
        array $validation,
        array $canonicalContext,
        array $operationalDecision
    ): array {
        $archivoOriginal = (string)($job['archivo_original'] ?? $job['archivo_pdf'] ?? 'cv.pdf');
        $validation = $this->legacyResultValidator->normalizeValidation($validation);
        $cacheable = !empty($operationalDecision['cacheable']);
        $estadoCache = $cacheable ? 'valida' : 'no_valida';
        $motivoInvalidation = $cacheable ? null : ($operationalDecision['invalidation_reason'] ?? 'resultado_no_utilizable');
        $textoExtraido = $this->loadExtractedTextIfAvailable($resultado, $job);

        $metadata = [
            'trace_path' => $tracePath,
            'log_path' => $this->safeString($job['log_path'] ?? null),
            'pipeline_log_path' => $pipelineLogPath,
            'tiempo_total_ms' => $tiempoTotalMs,
            'estado_cache' => $estadoCache,
            'error_parcial' => $hasPartialError,
            'motivo_invalidation' => $motivoInvalidation,
            'aneca_canonical_path' => $canonicalContext['aneca_canonical_path'] ?? null,
            'aneca_canonical_ready' => !empty($canonicalContext['aneca_canonical_ready']),
            'aneca_canonical_validation_status' => $canonicalContext['aneca_canonical_validation_status'] ?? null,
            'aneca_canonical_validation_errors' => $canonicalContext['aneca_canonical_validation_errors'] ?? [],
            'aneca_canonical_validation_warnings' => $canonicalContext['aneca_canonical_validation_warnings'] ?? [],
            'validation_status' => $validation['validation_status'] ?? null,
            'validation_errors' => $validation['validation_errors'] ?? [],
            'validation_warnings' => $validation['validation_warnings'] ?? [],
        ];

        try {
            $saved = $this->cache->saveByHash(
                $hashPdf,
                $archivoOriginal,
                $resultado,
                $textoExtraido,
                $metadata
            );

            $cacheEstadoFinal = (string)($saved['cache_estado'] ?? $estadoCache);
            $this->queue->appendLog(
                (string)($job['id'] ?? ''),
                'cache_guardada_correctamente cache_key=' . (string)($saved['cache_key'] ?? '')
                . ' cache_estado=' . $cacheEstadoFinal
                . ' resultado_principal_formato=' . (string)($saved['resultado_principal_formato'] ?? 'legacy')
                . ' criterio_operativo=' . (string)($operationalDecision['criterion'] ?? 'legacy_fallback')
                . ' formato_operativo=' . (string)($operationalDecision['artifact_format'] ?? 'legacy')
                . ' validation_status=' . (string)($validation['validation_status'] ?? '')
                . ' aneca_canonical_ready=' . (!empty($saved['aneca_canonical_ready']) ? 'true' : 'false')
                . ' aneca_canonical_validation_status=' . (string)($saved['aneca_canonical_validation_status'] ?? '')
                . ' motivo_invalidation=' . (string)($saved['motivo_invalidation'] ?? $motivoInvalidation ?? '')
            );

            return [
                'cache_saved' => true,
                'cache_key' => $saved['cache_key'] ?? null,
                'cache_estado' => $cacheEstadoFinal,
                'motivo_invalidation' => $saved['motivo_invalidation'] ?? $motivoInvalidation,
                'meta_path' => $saved['meta_path'] ?? null,
                'result_path' => $saved['result_path'] ?? null,
                'text_path' => $saved['text_path'] ?? null,
                'aneca_canonical_path' => $saved['aneca_canonical_path'] ?? ($canonicalContext['aneca_canonical_path'] ?? null),
                'aneca_canonical_ready' => !empty($saved['aneca_canonical_ready']) || !empty($canonicalContext['aneca_canonical_ready']),
                'aneca_canonical_validation_status' => $saved['aneca_canonical_validation_status'] ?? ($canonicalContext['aneca_canonical_validation_status'] ?? null),
                'resultado_principal_formato' => $saved['resultado_principal_formato'] ?? 'legacy',
                'resultado_principal_path' => $saved['resultado_principal_path'] ?? null,
                'criterio_operativo' => $operationalDecision['criterion'] ?? 'legacy_fallback',
                'formato_operativo' => $operationalDecision['artifact_format'] ?? 'legacy',
            ];
        } catch (Throwable $e) {
            $cacheKey = null;
            try {
                $cacheKey = $this->cache->buildCacheKeyFromHash($hashPdf);
            } catch (Throwable $ignored) {
                $cacheKey = null;
            }

            $this->queue->appendLog(
                (string)($job['id'] ?? ''),
                'cache_no_guardada_por_error mensaje=' . $e->getMessage()
            );

            return [
                'cache_saved' => false,
                'cache_key' => $cacheKey,
                'cache_estado' => 'error',
                'motivo_invalidation' => 'cache_save_error',
                'meta_path' => null,
                'result_path' => null,
                'text_path' => null,
                'aneca_canonical_path' => $canonicalContext['aneca_canonical_path'] ?? null,
                'aneca_canonical_ready' => !empty($canonicalContext['aneca_canonical_ready']),
                'aneca_canonical_validation_status' => $canonicalContext['aneca_canonical_validation_status'] ?? null,
            ];
        }
    }

    private function loadExtractedTextIfAvailable(array $resultado, array $job): ?string
    {
        $txtGenerado = $this->safeString($resultado['txt_generado'] ?? null);
        if ($txtGenerado !== null) {
            $candidate = __DIR__ . '/../output/text/' . basename($txtGenerado);
            if (is_file($candidate)) {
                $text = @file_get_contents($candidate);
                if (is_string($text)) {
                    return $text;
                }
            }
        }

        $pdfPath = $this->safeString($job['pdf_path'] ?? null);
        if ($pdfPath !== null) {
            $fallback = __DIR__ . '/../output/text/' . pathinfo($pdfPath, PATHINFO_FILENAME) . '.txt';
            if (is_file($fallback)) {
                $text = @file_get_contents($fallback);
                if (is_string($text)) {
                    return $text;
                }
            }
        }

        return null;
    }

    private function applyValidationContextToJob(string $jobId, array $validation): array
    {
        $validation = $this->legacyResultValidator->normalizeValidation($validation);
        $status = $this->safeString($validation['validation_status'] ?? null);
        $errors = $this->safeStringList($validation['validation_errors'] ?? []);
        $warnings = $this->safeStringList($validation['validation_warnings'] ?? []);

        return $this->queue->updateJob(
            $jobId,
            function (array $current) use ($status, $errors, $warnings): array {
                $current['validation_status'] = $status;
                $current['validation_errors'] = $errors;
                $current['validation_warnings'] = $warnings;
                return $current;
            }
        );
    }

    /**
     * @param array<string,mixed> $artifactContext
     * @param array<string,mixed> $legacyValidation
     * @return array<string,mixed>
     */
    private function buildOperationalDecision(array $artifactContext, array $legacyValidation): array
    {
        $legacyValidation = $this->legacyResultValidator->normalizeValidation($legacyValidation);
        return OperationalArtifactDecisionResolver::decide($artifactContext, $legacyValidation);
    }

    /**
     * @param array<string,mixed> $decision
     */
    private function describeOperationalDecision(array $decision): string
    {
        return OperationalArtifactDecisionResolver::describe($decision);
    }

    /**
     * @param array<string,mixed> $validation
     * @param array<string,mixed> $decision
     */
    private function buildOperationalMessageForCompletion(array $validation, array $decision, string $prefix): string
    {
        $validation = $this->legacyResultValidator->normalizeValidation($validation);
        $status = (string)($validation['validation_status'] ?? '');
        $warnings = $this->safeStringList($validation['validation_warnings'] ?? []);
        $errors = $this->safeStringList($validation['validation_errors'] ?? []);

        $parts = [$prefix, 'status=' . $status];
        if (!empty($warnings)) {
            $parts[] = 'warnings=' . implode(',', array_slice($warnings, 0, 3));
        }
        if (!empty($errors)) {
            $parts[] = 'errors=' . implode(',', array_slice($errors, 0, 3));
        }
        $parts[] = 'criterio=' . (string)($decision['criterion'] ?? 'legacy_fallback');
        $parts[] = 'formato=' . (string)($decision['artifact_format'] ?? 'legacy');

        $fallbackReason = $this->safeString($decision['fallback_reason'] ?? null);
        if ($fallbackReason !== null) {
            $parts[] = 'fallback=' . $fallbackReason;
        }

        return implode(' ', $parts) . '.';
    }

    private function safeReadJsonObject(?string $path): ?array
    {
        if ($path === null || !is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function safeString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function safeStringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed === '') {
                continue;
            }

            $out[] = $trimmed;
        }

        return array_values(array_unique($out));
    }
}
