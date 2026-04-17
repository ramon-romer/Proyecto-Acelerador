<?php

require_once __DIR__ . '/Pipeline.php';
require_once __DIR__ . '/ProcessingJobQueue.php';
require_once __DIR__ . '/ProcessingCache.php';

class ProcessingJobWorker
{
    private $queue;
    private $cache;
    private $logsDir;

    public function __construct(?ProcessingJobQueue $queue = null, ?ProcessingCache $cache = null)
    {
        $this->queue = $queue ?? new ProcessingJobQueue();
        $this->cache = $cache ?? new ProcessingCache();
        $this->logsDir = __DIR__ . '/../output/logs';
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
                );

                $this->queue->setPhase($jobId, 'validando_resultado', 98, 'cache_hit');

                $cachedResult = $cacheLookup['resultado_json'];
                $validationError = $this->validateResult($cachedResult);
                if ($validationError !== null) {
                    $this->queue->appendLog($jobId, 'cache_hit_descartado motivo=' . $validationError);
                } else {
                    $meta = is_array($cacheLookup['metadata'] ?? null) ? $cacheLookup['metadata'] : [];
                    $tracePathCache = $this->safeString($meta['trace_path'] ?? null);
                    $pipelineLogPathCache = $this->safeString($meta['pipeline_log_path'] ?? $meta['log_path'] ?? null);
                    $hasPartialCache = !empty($meta['error_parcial']);
                    $elapsedMsCache = round((microtime(true) - $startedAt) * 1000, 2);

                    $this->queue->markCompleted(
                        $jobId,
                        $cachedResult,
                        $tracePathCache,
                        $pipelineLogPathCache,
                        $elapsedMsCache,
                        $hasPartialCache,
                        $hasPartialCache ? 'Resultado servido desde cache con error parcial previo.' : null
                    );

                    $jobFinalCache = $this->applyCacheContextToJob($jobId, $hashPdf, $cacheLookup, true);

                    $this->queue->appendLog(
                        $jobId,
                        'fin_worker_cache estado=' . (string)($jobFinalCache['estado'] ?? '')
                        . ' tiempo_total_ms=' . $elapsedMsCache
                    );

                    return $jobFinalCache;
                }
            } else {
                $this->queue->appendLog(
                    $jobId,
                    'cache_miss cache_estado=' . (string)($cacheLookup['cache_estado'] ?? 'miss')
                    . ' motivo=' . (string)($cacheLookup['motivo_invalidation'] ?? 'cache_no_encontrada')
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

            $validationError = $this->validateResult($resultado);
            if ($validationError !== null) {
                throw new Exception($validationError);
            }

            $artifacts = $this->locatePipelineArtifacts($pdfPath, $startedAt);
            $tracePath = $artifacts['trace_path'];
            $pipelineLogPath = $artifacts['pipeline_log_path'];

            $hasPartialError = $this->detectPartialError($tracePath);
            $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);

            $cacheSaveInfo = $this->persistCacheForJob(
                $hashPdf,
                $job,
                $resultado,
                $tracePath,
                $pipelineLogPath,
                $elapsedMs,
                $hasPartialError
            );

            $jobFinal = $this->queue->markCompleted(
                $jobId,
                $resultado,
                $tracePath,
                $pipelineLogPath,
                $elapsedMs,
                $hasPartialError,
                $hasPartialError ? 'Proceso finalizado con errores parciales detectados por pagina.' : null
            );

            $jobFinal = $this->queue->updateJob(
                $jobId,
                function (array $current) use ($hashPdf, $cacheSaveInfo): array {
                    $current['hash_pdf'] = $hashPdf;
                    $current['cache_hit'] = false;
                    $current['cache_key'] = $cacheSaveInfo['cache_key'] ?? ($current['cache_key'] ?? null);
                    $current['cache_estado'] = $cacheSaveInfo['cache_estado'] ?? ($current['cache_estado'] ?? null);
                    $current['motivo_invalidation'] = $cacheSaveInfo['motivo_invalidation'] ?? null;
                    $current['cache_guardada'] = !empty($cacheSaveInfo['cache_saved']);
                    $current['cache_meta_path'] = $cacheSaveInfo['meta_path'] ?? null;
                    $current['cache_result_path'] = $cacheSaveInfo['result_path'] ?? null;
                    $current['cache_text_path'] = $cacheSaveInfo['text_path'] ?? null;
                    return $current;
                }
            );

            $this->queue->appendLog(
                $jobId,
                'fin_worker estado=' . (string)($jobFinal['estado'] ?? '')
                . ' tiempo_total_ms=' . $elapsedMs
                . ' cache_guardada=' . (!empty($cacheSaveInfo['cache_saved']) ? 'true' : 'false')
            );

            return $jobFinal;
        } catch (Throwable $e) {
            $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);

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
                function (array $current) use ($hashPdf, $cacheLookup): array {
                    if ($hashPdf !== '') {
                        $current['hash_pdf'] = $hashPdf;
                    }

                    $current['cache_hit'] = false;
                    $current['cache_key'] = $cacheLookup['cache_key'] ?? ($current['cache_key'] ?? null);
                    $current['cache_estado'] = $cacheLookup['cache_estado'] ?? ($current['cache_estado'] ?? null);
                    $current['motivo_invalidation'] = $cacheLookup['motivo_invalidation'] ?? null;
                    $current['cache_guardada'] = false;
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
        ];
    }

    private function validateResult(array $resultado): ?string
    {
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

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $resultado)) {
                return 'Resultado sin clave requerida: ' . $key;
            }
        }

        return null;
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

        return $this->queue->updateJob(
            $jobId,
            function (array $current) use ($hashPdf, $cacheLookup, $cacheHit, $versions, $cacheMeta): array {
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
        bool $hasPartialError
    ): array {
        $archivoOriginal = (string)($job['archivo_original'] ?? $job['archivo_pdf'] ?? 'cv.pdf');
        $estadoCache = $hasPartialError ? 'parcial' : 'valida';
        $textoExtraido = $this->loadExtractedTextIfAvailable($resultado, $job);

        $metadata = [
            'trace_path' => $tracePath,
            'log_path' => $this->safeString($job['log_path'] ?? null),
            'pipeline_log_path' => $pipelineLogPath,
            'tiempo_total_ms' => $tiempoTotalMs,
            'estado_cache' => $estadoCache,
            'error_parcial' => $hasPartialError,
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
            );

            return [
                'cache_saved' => true,
                'cache_key' => $saved['cache_key'] ?? null,
                'cache_estado' => $cacheEstadoFinal,
                'motivo_invalidation' => null,
                'meta_path' => $saved['meta_path'] ?? null,
                'result_path' => $saved['result_path'] ?? null,
                'text_path' => $saved['text_path'] ?? null,
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

    private function safeString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
