<?php

require_once __DIR__ . '/ProcessingJobQueue.php';
require_once __DIR__ . '/ProcessingCache.php';
require_once __DIR__ . '/LegacyPipelineResultValidator.php';
require_once __DIR__ . '/OperationalArtifactDecisionResolver.php';

class CvProcessingJobService
{
    private $queue;
    private $cache;
    private $pdfDir;
    private $heavyThresholdBytes;
    private $legacyResultValidator;

    public function __construct(
        ?ProcessingJobQueue $queue = null,
        ?string $pdfDir = null,
        ?ProcessingCache $cache = null
    )
    {
        $this->queue = $queue ?? new ProcessingJobQueue();
        $this->cache = $cache ?? new ProcessingCache();
        $this->pdfDir = $pdfDir ?? (__DIR__ . '/../pdfs');
        $this->heavyThresholdBytes = $this->readEnvInt('SCRAPING_HEAVY_PDF_BYTES', 3 * 1024 * 1024, 128 * 1024, 500 * 1024 * 1024);
        $this->legacyResultValidator = new LegacyPipelineResultValidator();

        if (!is_dir($this->pdfDir) && !mkdir($this->pdfDir, 0777, true) && !is_dir($this->pdfDir)) {
            throw new Exception('No se pudo crear el directorio de PDFs: ' . $this->pdfDir);
        }
    }

    public function enqueueFromUpload(array $uploadedFile, bool $alwaysQueue = false): array
    {
        $requestStartedAt = microtime(true);
        $this->validateUploadArray($uploadedFile);

        $tmpPath = (string)$uploadedFile['tmp_name'];
        $sizeBytes = (int)$uploadedFile['size'];
        $originalName = (string)($uploadedFile['name'] ?? 'cv.pdf');
        $isHeavy = $sizeBytes >= $this->heavyThresholdBytes;

        $mimeType = $this->detectMimeType($tmpPath);
        if ($mimeType !== 'application/pdf') {
            throw new Exception('El archivo no es un PDF valido. MIME detectado: ' . $mimeType);
        }

        $hashPdf = $this->cache->calculatePdfHash($tmpPath);
        $cacheLookup = $this->cache->resolveByHash($hashPdf, $originalName);

        if (!empty($cacheLookup['cache_hit']) && is_array($cacheLookup['resultado_json'] ?? null)) {
            $validation = $this->legacyResultValidator->normalizeValidation(
                $this->legacyResultValidator->validate((array)$cacheLookup['resultado_json'])
            );
            $cacheLookup['validation_status'] = $validation['validation_status'] ?? null;
            $cacheLookup['validation_errors'] = $validation['validation_errors'] ?? [];
            $cacheLookup['validation_warnings'] = $validation['validation_warnings'] ?? [];
            $cacheDecision = $this->resolveOperationalDecision($cacheLookup, $validation);

            if (empty($cacheDecision['cacheable'])) {
                $cacheLookup['cache_hit'] = false;
                $cacheLookup['cache_estado'] = 'no_valida';
                $cacheLookup['motivo_invalidation'] = $cacheDecision['invalidation_reason'] ?? 'resultado_no_utilizable';

                error_log(
                    '[meritos-scraping] cache_hit_descartado_subida hash=' . $hashPdf
                    . ' motivo=' . (string)($cacheLookup['motivo_invalidation'] ?? 'resultado_no_utilizable')
                    . ' detalle=' . $this->describeOperationalDecision($cacheDecision)
                );
            } else {
                error_log(
                    '[meritos-scraping] cache_hit_decision_subida hash=' . $hashPdf
                    . ' detalle=' . $this->describeOperationalDecision($cacheDecision)
                );
                return $this->createCompletedJobFromCache(
                    $tmpPath,
                    $originalName,
                    $sizeBytes,
                    $mimeType,
                    $isHeavy,
                    $hashPdf,
                    $cacheLookup,
                    $requestStartedAt,
                    $validation,
                    $cacheDecision
                );
            }
        }

        $safeName = $this->buildStoredFileName($originalName);
        $targetPath = $this->pdfDir . DIRECTORY_SEPARATOR . $safeName;

        if (!$this->moveUploadedFile($tmpPath, $targetPath)) {
            throw new Exception('No se pudo mover el archivo subido al destino final.');
        }

        $job = $this->queue->createJobForPdfPath(
            $targetPath,
            [
                'archivo_original' => $originalName,
                'tamano_bytes' => $sizeBytes,
                'mime_type' => $mimeType,
                'es_pesado' => $isHeavy,
                'umbral_pesado_bytes' => $this->heavyThresholdBytes,
                'hash_pdf' => $hashPdf,
                'cache_hit' => false,
                'cache_key' => $cacheLookup['cache_key'] ?? null,
                'cache_estado' => $cacheLookup['cache_estado'] ?? 'miss',
                'motivo_invalidation' => $cacheLookup['motivo_invalidation'] ?? null,
                'version_pipeline' => $this->cache->getVersions()['version_pipeline'] ?? null,
                'version_baremo' => $this->cache->getVersions()['version_baremo'] ?? null,
                'version_schema' => $this->cache->getVersions()['version_schema'] ?? null,
                'validation_status' => $cacheLookup['validation_status'] ?? null,
                'validation_errors' => is_array($cacheLookup['validation_errors'] ?? null) ? $cacheLookup['validation_errors'] : [],
                'validation_warnings' => is_array($cacheLookup['validation_warnings'] ?? null) ? $cacheLookup['validation_warnings'] : [],
                'aneca_canonical_path' => $cacheLookup['aneca_canonical_path'] ?? null,
                'aneca_canonical_ready' => !empty($cacheLookup['aneca_canonical_ready']),
                'aneca_canonical_validation_status' => $cacheLookup['aneca_canonical_validation_status'] ?? null,
                'resultado_principal_formato' => $cacheLookup['resultado_principal_formato'] ?? 'legacy',
                'resultado_principal_path' => $cacheLookup['resultado_principal_path'] ?? null,
            ]
        );

        $queuedOnly = $alwaysQueue || $isHeavy;
        $cacheEstado = (string)($cacheLookup['cache_estado'] ?? 'miss');
        $cacheMotivo = (string)($cacheLookup['motivo_invalidation'] ?? '');

        $this->queue->appendLog(
            (string)$job['id'],
            'upload_recibido archivo_original=' . $originalName
            . ' tamano_bytes=' . $sizeBytes
            . ' es_pesado=' . ($isHeavy ? 'true' : 'false')
            . ' queued_only=' . ($queuedOnly ? 'true' : 'false')
            . ' cache_hit=false'
            . ' cache_estado=' . $cacheEstado
            . ' resultado_principal_formato=' . (string)($cacheLookup['resultado_principal_formato'] ?? 'legacy')
            . ' aneca_canonical_ready=' . (!empty($cacheLookup['aneca_canonical_ready']) ? 'true' : 'false')
            . ' aneca_canonical_validation_status=' . (string)($cacheLookup['aneca_canonical_validation_status'] ?? '')
            . ($cacheMotivo !== '' ? ' motivo_invalidation=' . $cacheMotivo : '')
        );

        if ($cacheEstado === 'obsoleta') {
            error_log(
                '[meritos-scraping] cache_obsoleta_subida hash=' . $hashPdf
                . ' motivo=' . $cacheMotivo
            );
        } else {
            error_log(
                '[meritos-scraping] cache_miss_subida hash=' . $hashPdf
                . ' cache_estado=' . $cacheEstado
            );
        }

        return [
            'job' => $job,
            'queued_only' => $queuedOnly,
            'is_heavy' => $isHeavy,
            'heavy_threshold_bytes' => $this->heavyThresholdBytes,
            'stored_pdf_path' => $targetPath,
            'cache_hit' => false,
            'cache' => $cacheLookup,
            'cached_result' => null,
        ];
    }

    private function createCompletedJobFromCache(
        string $tmpPath,
        string $originalName,
        int $sizeBytes,
        string $mimeType,
        bool $isHeavy,
        string $hashPdf,
        array $cacheLookup,
        float $requestStartedAt,
        array $validation,
        array $operationalDecision
    ): array {
        $safeName = $this->buildStoredFileName($originalName);
        $targetPath = $this->pdfDir . DIRECTORY_SEPARATOR . $safeName;

        if (!$this->moveUploadedFile($tmpPath, $targetPath)) {
            throw new Exception('No se pudo mover el archivo subido al destino final (cache hit).');
        }

        $versions = $this->cache->getVersions();
        $validation = $this->legacyResultValidator->normalizeValidation($validation);

        $job = $this->queue->createJobForPdfPath(
            $targetPath,
            [
                'archivo_original' => $originalName,
                'tamano_bytes' => $sizeBytes,
                'mime_type' => $mimeType,
                'es_pesado' => $isHeavy,
                'umbral_pesado_bytes' => $this->heavyThresholdBytes,
                'hash_pdf' => $hashPdf,
                'cache_hit' => true,
                'cache_key' => $cacheLookup['cache_key'] ?? null,
                'cache_estado' => $cacheLookup['cache_estado'] ?? 'hit',
                'motivo_invalidation' => null,
                'version_pipeline' => $versions['version_pipeline'] ?? null,
                'version_baremo' => $versions['version_baremo'] ?? null,
                'version_schema' => $versions['version_schema'] ?? null,
                'cache_meta_path' => $cacheLookup['meta_path'] ?? null,
                'cache_result_path' => $cacheLookup['resultado_json_path'] ?? null,
                'cache_text_path' => $cacheLookup['texto_extraido_path'] ?? null,
                'cache_guardada' => true,
                'validation_status' => $validation['validation_status'] ?? null,
                'validation_errors' => $validation['validation_errors'] ?? [],
                'validation_warnings' => $validation['validation_warnings'] ?? [],
                'aneca_canonical_path' => $cacheLookup['aneca_canonical_path'] ?? null,
                'aneca_canonical_ready' => !empty($cacheLookup['aneca_canonical_ready']),
                'aneca_canonical_validation_status' => $cacheLookup['aneca_canonical_validation_status'] ?? null,
                'resultado_principal_formato' => $cacheLookup['resultado_principal_formato'] ?? 'legacy',
                'resultado_principal_path' => $cacheLookup['resultado_principal_path'] ?? null,
            ]
        );

        $cacheMeta = is_array($cacheLookup['metadata'] ?? null) ? $cacheLookup['metadata'] : [];
        $tracePath = $this->safeString($cacheMeta['trace_path'] ?? null);
        $pipelineLogPath = $this->safeString($cacheMeta['pipeline_log_path'] ?? $cacheMeta['log_path'] ?? null);
        $errorParcial = !empty($cacheMeta['error_parcial']) || empty($operationalDecision['is_clean']);
        $elapsedMs = round((microtime(true) - $requestStartedAt) * 1000, 2);
        $completionMessage = $errorParcial
            ? $this->buildValidationMessageForCompletion(
                $validation,
                $operationalDecision,
                'Resultado servido desde cache'
            )
            : null;

        $job = $this->queue->markCompleted(
            (string)$job['id'],
            (array)$cacheLookup['resultado_json'],
            $tracePath,
            $pipelineLogPath,
            $elapsedMs,
            $errorParcial,
            $completionMessage
        );

        $job = $this->queue->updateJob(
            (string)$job['id'],
            function (array $current) use ($hashPdf, $cacheLookup, $versions, $validation): array {
                $current['hash_pdf'] = $hashPdf;
                $current['cache_hit'] = true;
                $current['cache_key'] = $cacheLookup['cache_key'] ?? ($current['cache_key'] ?? null);
                $current['cache_estado'] = $cacheLookup['cache_estado'] ?? 'hit';
                $current['motivo_invalidation'] = null;
                $current['cache_guardada'] = true;
                $current['version_pipeline'] = $versions['version_pipeline'] ?? ($current['version_pipeline'] ?? null);
                $current['version_baremo'] = $versions['version_baremo'] ?? ($current['version_baremo'] ?? null);
                $current['version_schema'] = $versions['version_schema'] ?? ($current['version_schema'] ?? null);
                $current['cache_meta_path'] = $cacheLookup['meta_path'] ?? ($current['cache_meta_path'] ?? null);
                $current['cache_result_path'] = $cacheLookup['resultado_json_path'] ?? ($current['cache_result_path'] ?? null);
                $current['cache_text_path'] = $cacheLookup['texto_extraido_path'] ?? ($current['cache_text_path'] ?? null);
                $current['aneca_canonical_path'] = $cacheLookup['aneca_canonical_path'] ?? ($current['aneca_canonical_path'] ?? null);
                $current['aneca_canonical_ready'] = !empty($cacheLookup['aneca_canonical_ready']);
                $current['aneca_canonical_validation_status'] = $cacheLookup['aneca_canonical_validation_status']
                    ?? ($current['aneca_canonical_validation_status'] ?? null);
                $current['resultado_principal_formato'] = $cacheLookup['resultado_principal_formato']
                    ?? ($current['resultado_principal_formato'] ?? 'legacy');
                $current['resultado_principal_path'] = $cacheLookup['resultado_principal_path']
                    ?? ($current['resultado_principal_path'] ?? null);
                $current['validation_status'] = $validation['validation_status'] ?? ($current['validation_status'] ?? null);
                $current['validation_errors'] = is_array($validation['validation_errors'] ?? null) ? $validation['validation_errors'] : [];
                $current['validation_warnings'] = is_array($validation['validation_warnings'] ?? null) ? $validation['validation_warnings'] : [];
                return $current;
            }
        );

        $this->queue->appendLog(
            (string)$job['id'],
            'decision_operativa_service_cache_hit ' . $this->describeOperationalDecision($operationalDecision)
        );
        $this->queue->appendLog(
            (string)$job['id'],
            'cache_hit_subida cache_key=' . (string)($cacheLookup['cache_key'] ?? '')
            . ' cache_estado=' . (string)($cacheLookup['cache_estado'] ?? 'hit')
            . ' resultado_principal_formato=' . (string)($cacheLookup['resultado_principal_formato'] ?? 'legacy')
            . ' criterio_operativo=' . (string)($operationalDecision['criterion'] ?? 'legacy_fallback')
            . ' formato_operativo=' . (string)($operationalDecision['artifact_format'] ?? 'legacy')
            . ' validation_status=' . (string)($validation['validation_status'] ?? '')
            . ' aneca_canonical_ready=' . (!empty($cacheLookup['aneca_canonical_ready']) ? 'true' : 'false')
            . ' aneca_canonical_validation_status=' . (string)($cacheLookup['aneca_canonical_validation_status'] ?? '')
            . ' sin_reprocesar=true'
        );
        $this->queue->appendLog(
            (string)$job['id'],
            'job_finalizado_desde_cache tiempo_total_ms=' . $elapsedMs
        );

        error_log(
            '[meritos-scraping] cache_hit_subida hash=' . $hashPdf
            . ' cache_key=' . (string)($cacheLookup['cache_key'] ?? '')
            . ' resultado_principal_formato=' . (string)($cacheLookup['resultado_principal_formato'] ?? 'legacy')
            . ' criterio_operativo=' . (string)($operationalDecision['criterion'] ?? 'legacy_fallback')
            . ' formato_operativo=' . (string)($operationalDecision['artifact_format'] ?? 'legacy')
            . ' validation_status=' . (string)($validation['validation_status'] ?? '')
            . ' aneca_canonical_ready=' . (!empty($cacheLookup['aneca_canonical_ready']) ? 'true' : 'false')
            . ' aneca_canonical_validation_status=' . (string)($cacheLookup['aneca_canonical_validation_status'] ?? '')
            . ' sin_reprocesar=true'
        );

        return [
            'job' => $job,
            'queued_only' => false,
            'is_heavy' => $isHeavy,
            'heavy_threshold_bytes' => $this->heavyThresholdBytes,
            'stored_pdf_path' => $targetPath,
            'cache_hit' => true,
            'cache' => $cacheLookup,
            'cached_result' => $cacheLookup['resultado_json'],
        ];
    }

    private function validateUploadArray(array $uploadedFile): void
    {
        if (!isset($uploadedFile['error'], $uploadedFile['tmp_name'])) {
            throw new Exception('No se recibio un archivo PDF en el campo esperado.');
        }

        $error = (int)$uploadedFile['error'];
        if ($error !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir archivo PDF. codigo=' . $error);
        }

        $tmpPath = (string)$uploadedFile['tmp_name'];
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new Exception('Ruta temporal del upload invalida.');
        }
    }

    private function detectMimeType(string $filePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new Exception('No se pudo inicializar finfo para validar MIME.');
        }

        try {
            $mime = finfo_file($finfo, $filePath);
        } finally {
            finfo_close($finfo);
        }

        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    private function moveUploadedFile(string $tmpPath, string $targetPath): bool
    {
        if (move_uploaded_file($tmpPath, $targetPath)) {
            return true;
        }

        // Soporte adicional para pruebas CLI/smoke donde no existe contexto HTTP de upload.
        if (is_file($tmpPath) && @rename($tmpPath, $targetPath)) {
            return true;
        }

        return false;
    }

    private function buildStoredFileName(string $originalName): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$base) ?? 'cv';
        $base = trim($base, '._-');
        if ($base === '') {
            $base = 'cv';
        }

        return $base
            . '_'
            . gmdate('Ymd_His')
            . '_'
            . bin2hex(random_bytes(4))
            . '.pdf';
    }

    private function readEnvInt(string $envVar, int $default, int $min, int $max): int
    {
        $raw = getenv($envVar);
        if (!is_string($raw) || trim($raw) === '' || !preg_match('/^-?\d+$/', trim($raw))) {
            return $default;
        }

        $value = (int)trim($raw);
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private function buildValidationMessageForCompletion(array $validation, array $decision, string $prefix): string
    {
        $validation = $this->legacyResultValidator->normalizeValidation($validation);
        $status = (string)($validation['validation_status'] ?? '');
        $warnings = is_array($validation['validation_warnings'] ?? null) ? $validation['validation_warnings'] : [];
        $errors = is_array($validation['validation_errors'] ?? null) ? $validation['validation_errors'] : [];

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

    /**
     * @param array<string,mixed> $cacheLookup
     * @param array<string,mixed> $legacyValidation
     * @return array<string,mixed>
     */
    private function resolveOperationalDecision(array $cacheLookup, array $legacyValidation): array
    {
        $legacyValidation = $this->legacyResultValidator->normalizeValidation($legacyValidation);
        return OperationalArtifactDecisionResolver::decide(
            [
                'resultado_principal_formato' => $cacheLookup['resultado_principal_formato'] ?? null,
                'resultado_principal_path' => $cacheLookup['resultado_principal_path'] ?? null,
                'aneca_canonical_path' => $cacheLookup['aneca_canonical_path'] ?? null,
                'aneca_canonical_ready' => !empty($cacheLookup['aneca_canonical_ready']),
                'aneca_canonical_validation_status' => $cacheLookup['aneca_canonical_validation_status'] ?? null,
            ],
            $legacyValidation
        );
    }

    /**
     * @param array<string,mixed> $decision
     */
    private function describeOperationalDecision(array $decision): string
    {
        return OperationalArtifactDecisionResolver::describe($decision);
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
