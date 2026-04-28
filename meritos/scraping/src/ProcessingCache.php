<?php

require_once __DIR__ . '/InternalPreferredArtifactResolver.php';
require_once __DIR__ . '/OperationalArtifactDecisionResolver.php';

class ProcessingCache
{
    private $cacheDir;
    private $versions;

    public function __construct(?string $cacheDir = null, ?array $versions = null)
    {
        $this->cacheDir = $cacheDir ?? (__DIR__ . '/../output/cache');
        $this->versions = $this->normalizeVersions($versions ?? []);

        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0777, true) && !is_dir($this->cacheDir)) {
            throw new Exception('No se pudo crear directorio de cache: ' . $this->cacheDir);
        }
    }

    public function getVersions(): array
    {
        return $this->versions;
    }

    public function calculatePdfHash(string $pdfPath): string
    {
        if (!is_file($pdfPath)) {
            throw new Exception('No existe PDF para calcular hash: ' . $pdfPath);
        }

        $hash = hash_file('sha256', $pdfPath);
        if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new Exception('No se pudo calcular hash SHA-256 del PDF.');
        }

        return $hash;
    }

    public function buildCacheKeyFromHash(string $hashPdf): string
    {
        $hashPdf = strtolower(trim($hashPdf));
        if (preg_match('/^[a-f0-9]{64}$/', $hashPdf) !== 1) {
            throw new Exception('hash_pdf invalido para cache_key.');
        }

        $versionToken = $this->versions['version_pipeline']
            . '|' . $this->versions['version_baremo']
            . '|' . (string)($this->versions['version_schema'] ?? '');

        $fingerprint = hash('sha256', $hashPdf . '|' . $versionToken);

        return 'cache_' . substr($hashPdf, 0, 16) . '_' . substr((string)$fingerprint, 0, 16);
    }

    public function resolveByPdfPath(string $pdfPath, ?string $archivoOriginal = null): array
    {
        $hashPdf = $this->calculatePdfHash($pdfPath);
        $archivoOriginal = $archivoOriginal ?? basename($pdfPath);

        return $this->resolveByHash($hashPdf, $archivoOriginal);
    }

    public function resolveByHash(string $hashPdf, ?string $archivoOriginal = null): array
    {
        $hashPdf = strtolower(trim($hashPdf));
        if (preg_match('/^[a-f0-9]{64}$/', $hashPdf) !== 1) {
            throw new Exception('hash_pdf invalido al resolver cache.');
        }

        $cacheKey = $this->buildCacheKeyFromHash($hashPdf);
        $paths = $this->pathsForKey($cacheKey);

        $response = [
            'cache_hit' => false,
            'cache_key' => $cacheKey,
            'cache_estado' => 'miss',
            'motivo_invalidation' => null,
            'hash_pdf' => $hashPdf,
            'archivo_original' => $archivoOriginal,
            'metadata' => null,
            'resultado_json' => null,
            'texto_extraido' => null,
            'meta_path' => $paths['meta_path'],
            'resultado_json_path' => $paths['result_path'],
            'texto_extraido_path' => $paths['text_path'],
            'trace_path' => null,
            'log_path' => null,
            'aneca_canonical_path' => null,
            'aneca_canonical_ready' => false,
            'aneca_canonical_validation_status' => null,
            'aneca_canonical_validation_errors' => [],
            'aneca_canonical_validation_warnings' => [],
            'aneca_canonical_json' => null,
            'resultado_principal_formato' => InternalPreferredArtifactResolver::FORMAT_LEGACY,
            'resultado_principal_path' => $paths['result_path'],
            'validation_status' => null,
            'validation_errors' => [],
            'validation_warnings' => [],
            'operational_criterion' => null,
            'operational_artifact_format' => null,
            'operational_fallback_reason' => null,
        ];

        $meta = $this->safeReadJsonFile($paths['meta_path']);
        if (is_array($meta)) {
            $validation = $this->validateCurrentMeta($meta, $hashPdf, $cacheKey, $paths);

            if ($validation['valid']) {
                $resultPayload = $this->safeReadJsonFile((string)$validation['result_path']);
                if (is_array($resultPayload)) {
                    $response['cache_hit'] = true;
                    $response['cache_estado'] = 'hit';
                    $response['metadata'] = $meta;
                    $response['meta_path'] = $paths['meta_path'];
                    $response['resultado_json'] = $resultPayload;
                    $response['resultado_json_path'] = (string)$validation['result_path'];
                    $response['texto_extraido_path'] = $validation['text_path'];
                    $response['texto_extraido'] = $this->safeReadTextFile($validation['text_path']);
                    $response['trace_path'] = $this->safeString($meta['trace_path'] ?? null);
                    $response['log_path'] = $this->safeString($meta['log_path'] ?? null);
                    $canonicalPath = $this->resolvePathFromMeta($meta['aneca_canonical_path'] ?? null, null);
                    $canonicalValidationStatus = $this->safeString($meta['aneca_canonical_validation_status'] ?? null);
                    $canonicalValidationErrors = $this->safeStringList($meta['aneca_canonical_validation_errors'] ?? []);
                    $canonicalValidationWarnings = $this->safeStringList($meta['aneca_canonical_validation_warnings'] ?? []);
                    $canonicalReady = !empty($meta['aneca_canonical_ready']);

                    if ($canonicalPath === null || !is_file($canonicalPath)) {
                        $canonicalReady = false;
                        if ($canonicalValidationStatus === null && !empty($meta['aneca_canonical_ready'])) {
                            $canonicalValidationStatus = 'invalido';
                        }
                        if (empty($canonicalValidationErrors) && !empty($meta['aneca_canonical_ready'])) {
                            $canonicalValidationErrors[] = 'aneca_canonical_path_inexistente';
                        }
                    }

                    $response['aneca_canonical_path'] = $canonicalPath;
                    $response['aneca_canonical_ready'] = $canonicalReady;
                    $response['aneca_canonical_validation_status'] = $canonicalValidationStatus;
                    $response['aneca_canonical_validation_errors'] = $canonicalValidationErrors;
                    $response['aneca_canonical_validation_warnings'] = $canonicalValidationWarnings;
                    $response['aneca_canonical_json'] = $canonicalPath !== null && is_file($canonicalPath)
                        ? $this->safeReadJsonFile($canonicalPath)
                        : null;
                    $operationalFormat = $this->safeString($validation['operational_artifact_format'] ?? null);
                    $operationalPath = $this->safeString($validation['operational_artifact_path'] ?? null);
                    if (!in_array($operationalFormat, ['aneca', 'legacy'], true)) {
                        $preferredArtifact = InternalPreferredArtifactResolver::resolvePreferredArtifact(
                            (string)$validation['result_path'],
                            $canonicalPath,
                            $canonicalReady
                        );
                        $operationalFormat = (string)($preferredArtifact['resultado_principal_formato'] ?? 'legacy');
                        $operationalPath = $this->safeString($preferredArtifact['resultado_principal_path'] ?? null);
                    }
                    if ($operationalPath === null) {
                        $operationalPath = $operationalFormat === 'aneca'
                            ? $canonicalPath
                            : (string)$validation['result_path'];
                    }

                    $response['resultado_principal_formato'] = $operationalFormat;
                    $response['resultado_principal_path'] = $operationalPath;
                    $response['validation_status'] = $this->safeString($meta['validation_status'] ?? null);
                    $response['validation_errors'] = $this->safeStringList($meta['validation_errors'] ?? []);
                    $response['validation_warnings'] = $this->safeStringList($meta['validation_warnings'] ?? []);
                    $response['operational_criterion'] = $this->safeString($validation['operational_criterion'] ?? null);
                    $response['operational_artifact_format'] = $this->safeString(
                        $validation['operational_artifact_format'] ?? null
                    );
                    $response['operational_fallback_reason'] = $this->safeString(
                        $validation['operational_fallback_reason'] ?? null
                    );

                    return $response;
                }

                $response['cache_estado'] = 'corrupta';
                $response['motivo_invalidation'] = 'resultado_json_corrupto';
            } else {
                $response['cache_estado'] = $validation['state'];
                $response['motivo_invalidation'] = $validation['reason'];
                $response['operational_criterion'] = $this->safeString($validation['operational_criterion'] ?? null);
                $response['operational_artifact_format'] = $this->safeString(
                    $validation['operational_artifact_format'] ?? null
                );
                $response['operational_fallback_reason'] = $this->safeString(
                    $validation['operational_fallback_reason'] ?? null
                );
            }
        }

        $history = $this->findMetaEntriesByHash($hashPdf);
        $historyReason = $this->deriveHistoryInvalidationReason($history, $cacheKey);

        if ($historyReason !== null) {
            $response['motivo_invalidation'] = $historyReason;

            if (strpos($historyReason, 'version_') === 0) {
                $response['cache_estado'] = 'obsoleta';
                $this->markHistoryObsolete($history, $cacheKey, $historyReason);
            }
        }

        if ($response['motivo_invalidation'] === null) {
            if (!is_file($paths['meta_path'])) {
                $response['motivo_invalidation'] = 'cache_no_encontrada';
            } else {
                $response['motivo_invalidation'] = 'cache_invalida';
            }
        }

        return $response;
    }

    public function saveByPdfPath(
        string $pdfPath,
        array $resultadoJson,
        ?string $textoExtraido = null,
        array $metadata = []
    ): array {
        $hashPdf = $this->calculatePdfHash($pdfPath);
        $archivoOriginal = basename($pdfPath);

        return $this->saveByHash($hashPdf, $archivoOriginal, $resultadoJson, $textoExtraido, $metadata);
    }

    public function saveByHash(
        string $hashPdf,
        string $archivoOriginal,
        array $resultadoJson,
        ?string $textoExtraido = null,
        array $metadata = []
    ): array {
        $hashPdf = strtolower(trim($hashPdf));
        if (preg_match('/^[a-f0-9]{64}$/', $hashPdf) !== 1) {
            throw new Exception('hash_pdf invalido al guardar cache.');
        }

        $cacheKey = $this->buildCacheKeyFromHash($hashPdf);
        $paths = $this->pathsForKey($cacheKey);

        $estadoCache = $this->safeString($metadata['estado_cache'] ?? 'valida');
        if ($estadoCache === '') {
            $estadoCache = 'valida';
        }

        $resultJson = json_encode($resultadoJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($resultJson === false) {
            throw new Exception('No se pudo serializar resultado_json para cache.');
        }

        $this->atomicWriteFile($paths['result_path'], $resultJson);

        $textoPath = null;
        if ($textoExtraido !== null) {
            $this->atomicWriteFile($paths['text_path'], $textoExtraido);
            $textoPath = $paths['text_path'];
        }

        $meta = [
            'hash_pdf' => $hashPdf,
            'cache_key' => $cacheKey,
            'archivo_original' => $archivoOriginal,
            'fecha_procesamiento' => gmdate('c'),
            'version_pipeline' => $this->versions['version_pipeline'],
            'version_baremo' => $this->versions['version_baremo'],
            'version_schema' => $this->versions['version_schema'],
            'resultado_json_path' => $paths['result_path'],
            'texto_extraido_path' => $textoPath,
            'trace_path' => $this->safeString($metadata['trace_path'] ?? null),
            'log_path' => $this->safeString($metadata['log_path'] ?? null),
            'aneca_canonical_path' => $this->safeString($metadata['aneca_canonical_path'] ?? null),
            'aneca_canonical_ready' => !empty($metadata['aneca_canonical_ready']),
            'aneca_canonical_validation_status' => $this->safeString($metadata['aneca_canonical_validation_status'] ?? null),
            'aneca_canonical_validation_errors' => $this->safeStringList($metadata['aneca_canonical_validation_errors'] ?? []),
            'aneca_canonical_validation_warnings' => $this->safeStringList($metadata['aneca_canonical_validation_warnings'] ?? []),
            'tiempo_total_ms' => isset($metadata['tiempo_total_ms']) ? round((float)$metadata['tiempo_total_ms'], 2) : null,
            'estado_cache' => $estadoCache,
            'error_parcial' => !empty($metadata['error_parcial']),
            'pipeline_log_path' => $this->safeString($metadata['pipeline_log_path'] ?? null),
            'motivo_invalidation' => $this->safeString($metadata['motivo_invalidation'] ?? null),
            'validation_status' => $this->safeString($metadata['validation_status'] ?? null),
            'validation_errors' => $this->safeStringList($metadata['validation_errors'] ?? []),
            'validation_warnings' => $this->safeStringList($metadata['validation_warnings'] ?? []),
        ];
        $canonicalPathResolved = $this->resolvePathFromMeta($meta['aneca_canonical_path'] ?? null, null);
        $canonicalReadyResolved = !empty($meta['aneca_canonical_ready']) && $canonicalPathResolved !== null && is_file($canonicalPathResolved);
        $canonicalStatusResolved = $this->safeString($meta['aneca_canonical_validation_status'] ?? null);
        if (!empty($meta['aneca_canonical_ready']) && !$canonicalReadyResolved && $canonicalStatusResolved === null) {
            $canonicalStatusResolved = 'invalido';
        }

        $legacyStatus = $this->normalizeLegacyStatusFromMeta($meta);
        $technicalPreferred = InternalPreferredArtifactResolver::resolvePreferredArtifact(
            $paths['result_path'],
            $canonicalPathResolved,
            $canonicalReadyResolved
        );
        $operationalDecision = OperationalArtifactDecisionResolver::decide(
            [
                'resultado_principal_formato' => $technicalPreferred['resultado_principal_formato'] ?? null,
                'resultado_principal_path' => $technicalPreferred['resultado_principal_path'] ?? null,
                'aneca_canonical_path' => $canonicalPathResolved,
                'aneca_canonical_ready' => $canonicalReadyResolved,
                'aneca_canonical_validation_status' => $canonicalStatusResolved,
            ],
            [
                'validation_status' => $legacyStatus,
            ]
        );

        $meta['aneca_canonical_path'] = $canonicalPathResolved;
        $meta['aneca_canonical_ready'] = $canonicalReadyResolved;
        $meta['aneca_canonical_validation_status'] = $canonicalStatusResolved;
        $meta['resultado_principal_formato'] = in_array(
            (string)($operationalDecision['artifact_format'] ?? ''),
            ['aneca', 'legacy'],
            true
        )
            ? (string)$operationalDecision['artifact_format']
            : (string)($technicalPreferred['resultado_principal_formato'] ?? 'legacy');
        $meta['resultado_principal_path'] = $this->safeString($operationalDecision['artifact_path'] ?? null)
            ?? ($meta['resultado_principal_formato'] === 'aneca'
                ? $canonicalPathResolved
                : $paths['result_path']);

        $metaJson = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($metaJson === false) {
            throw new Exception('No se pudo serializar metadatos de cache.');
        }

        $this->atomicWriteFile($paths['meta_path'], $metaJson);

        return [
            'cache_key' => $cacheKey,
            'cache_estado' => $estadoCache,
            'meta_path' => $paths['meta_path'],
            'result_path' => $paths['result_path'],
            'text_path' => $textoPath,
            'aneca_canonical_path' => $meta['aneca_canonical_path'],
            'aneca_canonical_ready' => $meta['aneca_canonical_ready'],
            'aneca_canonical_validation_status' => $meta['aneca_canonical_validation_status'],
            'aneca_canonical_validation_errors' => $meta['aneca_canonical_validation_errors'],
            'aneca_canonical_validation_warnings' => $meta['aneca_canonical_validation_warnings'],
            'resultado_principal_formato' => $meta['resultado_principal_formato'],
            'resultado_principal_path' => $meta['resultado_principal_path'],
            'validation_status' => $meta['validation_status'],
            'validation_errors' => $meta['validation_errors'],
            'validation_warnings' => $meta['validation_warnings'],
            'metadata' => $meta,
        ];
    }

    private function validateCurrentMeta(array $meta, string $hashPdf, string $cacheKey, array $paths): array
    {
        $metaHash = strtolower(trim((string)($meta['hash_pdf'] ?? '')));
        if ($metaHash !== $hashPdf) {
            return ['valid' => false, 'state' => 'corrupta', 'reason' => 'hash_pdf_mismatch'];
        }

        $metaKey = (string)($meta['cache_key'] ?? '');
        if ($metaKey !== $cacheKey) {
            return ['valid' => false, 'state' => 'corrupta', 'reason' => 'cache_key_mismatch'];
        }

        $versionReason = $this->compareVersionReason($meta, $this->versions);
        if ($versionReason !== null) {
            return ['valid' => false, 'state' => 'obsoleta', 'reason' => $versionReason];
        }

        $estadoCache = strtolower(trim((string)($meta['estado_cache'] ?? '')));
        if ($estadoCache !== 'valida') {
            return ['valid' => false, 'state' => 'no_valida', 'reason' => 'estado_cache_' . ($estadoCache === '' ? 'desconocido' : $estadoCache)];
        }

        $resultPath = $this->resolvePathFromMeta($meta['resultado_json_path'] ?? null, $paths['result_path']);
        if (!is_file($resultPath)) {
            return ['valid' => false, 'state' => 'corrupta', 'reason' => 'resultado_json_inexistente'];
        }

        $canonicalPath = $this->resolvePathFromMeta($meta['aneca_canonical_path'] ?? null, null);
        $canonicalReady = !empty($meta['aneca_canonical_ready']);
        $canonicalStatus = $this->safeString($meta['aneca_canonical_validation_status'] ?? null);
        if ($canonicalPath === null || !is_file($canonicalPath)) {
            $canonicalPath = null;
            $canonicalReady = false;
            if (!empty($meta['aneca_canonical_ready']) && $canonicalStatus === null) {
                $canonicalStatus = 'invalido';
            }
        }

        $preferredArtifact = InternalPreferredArtifactResolver::resolvePreferredArtifact(
            $resultPath,
            $canonicalPath,
            $canonicalReady
        );
        $metaPrimaryFormat = $this->safeString($meta['resultado_principal_formato'] ?? null);
        if (!in_array($metaPrimaryFormat, ['aneca', 'legacy'], true)) {
            $metaPrimaryFormat = (string)($preferredArtifact['resultado_principal_formato'] ?? 'legacy');
        }

        $metaPrimaryPath = $this->resolvePathFromMeta($meta['resultado_principal_path'] ?? null, null);
        if ($metaPrimaryPath === null || !is_file($metaPrimaryPath)) {
            $metaPrimaryPath = $this->safeString($preferredArtifact['resultado_principal_path'] ?? null);
        }

        $legacyStatus = $this->normalizeLegacyStatusFromMeta($meta);
        $decision = OperationalArtifactDecisionResolver::decide(
            [
                'resultado_principal_formato' => $metaPrimaryFormat,
                'resultado_principal_path' => $metaPrimaryPath,
                'aneca_canonical_path' => $canonicalPath,
                'aneca_canonical_ready' => $canonicalReady,
                'aneca_canonical_validation_status' => $canonicalStatus,
            ],
            [
                'validation_status' => $legacyStatus,
            ]
        );

        if (empty($decision['cacheable'])) {
            return [
                'valid' => false,
                'state' => 'no_valida',
                'reason' => $this->normalizeInvalidationReasonFromOperationalDecision($decision),
                'operational_criterion' => $decision['criterion'] ?? null,
                'operational_artifact_format' => $decision['artifact_format'] ?? null,
                'operational_artifact_path' => $decision['artifact_path'] ?? null,
                'operational_fallback_reason' => $decision['fallback_reason'] ?? null,
            ];
        }

        $textPathRaw = $this->resolvePathFromMeta($meta['texto_extraido_path'] ?? null, $paths['text_path']);
        $textPath = null;
        if ($textPathRaw !== null && is_file($textPathRaw)) {
            $textPath = $textPathRaw;
        }

        return [
            'valid' => true,
            'state' => 'valida',
            'reason' => null,
            'result_path' => $resultPath,
            'text_path' => $textPath,
            'operational_criterion' => $decision['criterion'] ?? null,
            'operational_artifact_format' => $decision['artifact_format'] ?? null,
            'operational_artifact_path' => $decision['artifact_path'] ?? null,
            'operational_fallback_reason' => $decision['fallback_reason'] ?? null,
        ];
    }

    private function normalizeLegacyStatusFromMeta(array $meta): string
    {
        $rawStatus = strtolower(trim((string)($meta['validation_status'] ?? '')));
        if ($rawStatus === '') {
            // Compatibilidad con metas antiguas que no trazaban validation_status.
            return 'valido_con_advertencias';
        }

        if (in_array($rawStatus, ['valido', 'valido_con_advertencias', 'incompleto', 'invalido'], true)) {
            return $rawStatus;
        }

        return 'invalido';
    }

    /**
     * @param array<string,mixed> $decision
     */
    private function normalizeInvalidationReasonFromOperationalDecision(array $decision): string
    {
        $reason = (string)($decision['invalidation_reason'] ?? 'resultado_no_utilizable');
        $legacyPrefix = 'legacy_validation_status_';
        if (strpos($reason, $legacyPrefix) === 0) {
            $status = trim((string)substr($reason, strlen($legacyPrefix)));
            if ($status === '') {
                $status = 'desconocido';
            }
            return 'validation_status_' . $status;
        }

        return $reason;
    }

    private function compareVersionReason(array $meta, array $currentVersions): ?string
    {
        $metaPipeline = (string)($meta['version_pipeline'] ?? '');
        $metaBaremo = (string)($meta['version_baremo'] ?? '');
        $metaSchema = $this->safeString($meta['version_schema'] ?? null);

        if ($metaPipeline !== (string)$currentVersions['version_pipeline']) {
            return 'version_pipeline_changed';
        }

        if ($metaBaremo !== (string)$currentVersions['version_baremo']) {
            return 'version_baremo_changed';
        }

        if ((string)($metaSchema ?? '') !== (string)($currentVersions['version_schema'] ?? '')) {
            return 'version_schema_changed';
        }

        return null;
    }

    private function findMetaEntriesByHash(string $hashPdf): array
    {
        $files = glob($this->cacheDir . '/*.meta.json') ?: [];
        $entries = [];

        foreach ($files as $metaPath) {
            $meta = $this->safeReadJsonFile($metaPath);
            if (!is_array($meta)) {
                continue;
            }

            $metaHash = strtolower(trim((string)($meta['hash_pdf'] ?? '')));
            if ($metaHash !== $hashPdf) {
                continue;
            }

            $entries[] = [
                'meta_path' => $metaPath,
                'meta' => $meta,
            ];
        }

        return $entries;
    }

    private function deriveHistoryInvalidationReason(array $history, string $currentCacheKey): ?string
    {
        $latestReason = null;

        foreach ($history as $entry) {
            $meta = (array)($entry['meta'] ?? []);
            $metaKey = (string)($meta['cache_key'] ?? '');

            if ($metaKey === $currentCacheKey) {
                continue;
            }

            $versionReason = $this->compareVersionReason($meta, $this->versions);
            if ($versionReason !== null) {
                return $versionReason;
            }

            if ($latestReason === null) {
                $latestReason = 'cache_obsoleta';
            }
        }

        return $latestReason;
    }

    private function markHistoryObsolete(array $history, string $currentCacheKey, string $reason): void
    {
        foreach ($history as $entry) {
            $metaPath = (string)($entry['meta_path'] ?? '');
            $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : null;

            if ($metaPath === '' || !is_array($meta)) {
                continue;
            }

            $metaKey = (string)($meta['cache_key'] ?? '');
            if ($metaKey === $currentCacheKey) {
                continue;
            }

            $meta['estado_cache'] = 'obsoleta';
            $meta['motivo_invalidation'] = $reason;
            $meta['fecha_invalidacion'] = gmdate('c');

            $metaJson = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($metaJson === false) {
                continue;
            }

            try {
                $this->atomicWriteFile($metaPath, $metaJson);
            } catch (Throwable $e) {
                // Si no se puede actualizar, no bloqueamos el flujo de lectura.
            }
        }
    }

    private function pathsForKey(string $cacheKey): array
    {
        $base = rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . $cacheKey;

        return [
            'meta_path' => $base . '.meta.json',
            'result_path' => $base . '.result.json',
            'text_path' => $base . '.text.txt',
        ];
    }

    private function normalizeVersions(array $overrides): array
    {
        $defaults = [
            'version_pipeline' => $this->readEnvString('CACHE_VERSION_PIPELINE', $this->readEnvString('PIPELINE_VERSION', 'hibrido_por_pagina_v1')),
            'version_baremo' => $this->readEnvString('CACHE_VERSION_BAREMO', $this->readEnvString('BAREMO_VERSION', 'v1')),
            'version_schema' => $this->readEnvOptionalString('CACHE_VERSION_SCHEMA', $this->readEnvOptionalString('SCHEMA_VERSION', null)),
        ];

        foreach (['version_pipeline', 'version_baremo'] as $key) {
            if (array_key_exists($key, $overrides) && is_string($overrides[$key]) && trim($overrides[$key]) !== '') {
                $defaults[$key] = trim($overrides[$key]);
            }
        }

        if (array_key_exists('version_schema', $overrides)) {
            $schema = $this->safeString($overrides['version_schema']);
            $defaults['version_schema'] = $schema;
        }

        return $defaults;
    }

    private function readEnvString(string $envVar, string $default): string
    {
        $raw = getenv($envVar);
        if (!is_string($raw) || trim($raw) === '') {
            return $default;
        }

        return trim($raw);
    }

    private function readEnvOptionalString(string $envVar, ?string $default): ?string
    {
        $raw = getenv($envVar);
        if (!is_string($raw) || trim($raw) === '') {
            return $default;
        }

        return trim($raw);
    }

    private function safeReadJsonFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function safeReadTextFile(?string $path): ?string
    {
        if ($path === null || !is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            return null;
        }

        return $raw;
    }

    private function resolvePathFromMeta($value, ?string $fallback): ?string
    {
        $value = $this->safeString($value);
        if ($value === null || $value === '') {
            return $fallback;
        }

        if ($this->isAbsolutePath($value)) {
            return $value;
        }

        return rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($value, '/\\');
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (
            strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/')
        ) {
            return true;
        }

        return strpos($path, '/') === 0 || strpos($path, '\\') === 0;
    }

    private function safeString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
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

    private function atomicWriteFile(string $path, string $content): void
    {
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));

        if (@file_put_contents($tmp, $content) === false) {
            throw new Exception('No se pudo escribir archivo temporal de cache: ' . $path);
        }

        if (@is_file($path) && !@unlink($path)) {
            @unlink($tmp);
            throw new Exception('No se pudo reemplazar archivo de cache existente: ' . $path);
        }

        if (!@rename($tmp, $path)) {
            if (@copy($tmp, $path)) {
                @unlink($tmp);
                return;
            }

            @unlink($tmp);
            throw new Exception('No se pudo guardar archivo de cache: ' . $path);
        }
    }
}
