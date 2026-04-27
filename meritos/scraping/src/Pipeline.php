<?php

require_once __DIR__ . '/PdfNativeTextExtractor.php';
require_once __DIR__ . '/PdfToImage.php';
require_once __DIR__ . '/OcrProcessor.php';
require_once __DIR__ . '/OcrEnvironmentChecker.php';
require_once __DIR__ . '/TextCleaner.php';
require_once __DIR__ . '/AnecaExtractor.php';
require_once __DIR__ . '/AnecaCanonicalAdapter.php';
require_once __DIR__ . '/AnecaCanonicalResultValidator.php';
require_once __DIR__ . '/ProcessingCache.php';
require_once __DIR__ . '/LegacyPipelineResultValidator.php';

class Pipeline
{
    public function procesar(string $pdfPath, ?callable $phaseCallback = null): array
    {
        if (!is_file($pdfPath)) {
            throw new Exception('El PDF no existe: ' . $pdfPath);
        }

        $this->reportPhase($phaseCallback, 'procesando_pdf', 5, 'inicio_pipeline');

        $inicioTotal = microtime(true);
        $baseName = pathinfo($pdfPath, PATHINFO_FILENAME);
        $runId = gmdate('Ymd_His');

        $imageDir = __DIR__ . '/../output/images/';
        $textDir = __DIR__ . '/../output/text/';
        $jsonDir = __DIR__ . '/../output/json/';
        $logsDir = __DIR__ . '/../output/logs/';
        $cacheDir = __DIR__ . '/../output/cache/';

        $this->ensureDirectories([$imageDir, $textDir, $jsonDir, $logsDir, $cacheDir]);

        $imagePrefix = $imageDir . $baseName;
        $textFile = $textDir . $baseName . '.txt';
        $jsonFile = $jsonDir . $baseName . '.json';
        $traceFile = $logsDir . $baseName . '.' . $runId . '.trace.json';
        $logFile = $logsDir . $baseName . '.' . $runId . '.pipeline.log';

        $timings = [
            'total_ms' => 0.0,
            'extraccion_nativa_ms' => 0.0,
            'ocr_ms' => 0.0,
            'limpieza_ms' => 0.0,
            'extraccion_semantica_ms' => 0.0,
        ];

        $eventos = [];
        $paginasDetectadas = 0;
        $paginasCandidatasOcr = [];
        $trazabilidadPaginas = [];
        $textoPorPagina = [];
        $textoNativoPorPagina = [];
        $fallbackLegacyActivado = false;
        $environmentCheck = $this->checkEnvironmentSafely();
        $eventos[] = 'ocr_env_mode:' . (string)($environmentCheck['mode'] ?? 'unknown');

        foreach ((array)($environmentCheck['warnings'] ?? []) as $warning) {
            $warning = trim((string)$warning);
            if ($warning !== '') {
                $eventos[] = 'ocr_env_warning:' . $warning;
            }
        }

        $cache = new ProcessingCache($cacheDir);
        $cacheContext = $this->prepararBaseCache($pdfPath, $cache);
        $pdfToImage = new PdfToImage();
        $ocr = new OcrProcessor();

        $inicioNativo = microtime(true);
        $nativo = ['page_count' => 0, 'pages' => []];
        $this->reportPhase($phaseCallback, 'procesando_pdf', 18, 'extraccion_nativa');

        try {
            $nativeExtractor = new PdfNativeTextExtractor();
            $nativo = $nativeExtractor->extraerPorPagina($pdfPath);
            $paginasDetectadas = (int)($nativo['page_count'] ?? 0);
            $eventos[] = 'extraccion_nativa_ok';
        } catch (Throwable $e) {
            $eventos[] = 'extraccion_nativa_error:' . $e->getMessage();
        }

        $timings['extraccion_nativa_ms'] = $this->elapsedMs($inicioNativo);

        if ($paginasDetectadas > 0) {
            foreach (($nativo['pages'] ?? []) as $paginaInfo) {
                $numeroPagina = (int)($paginaInfo['page_number'] ?? 0);
                if ($numeroPagina < 1) {
                    continue;
                }

                $textoNativo = (string)($paginaInfo['text'] ?? '');
                $textoNativoPorPagina[$numeroPagina] = $textoNativo;

                $esUtil = (bool)($paginaInfo['is_usable'] ?? false);
                $tiempoNativoPagina = round((float)($paginaInfo['time_ms'] ?? 0.0), 2);
                $errorParcial = !empty($paginaInfo['error']);

                $trazabilidadPaginas[$numeroPagina] = [
                    'numero_pagina' => $numeroPagina,
                    'metodo' => $esUtil ? 'texto_nativo' : 'ocr',
                    'tiempo_procesamiento_ms' => $tiempoNativoPagina,
                    'error_parcial' => $errorParcial,
                ];

                if ($esUtil && trim($textoNativo) !== '') {
                    $textoPorPagina[$numeroPagina] = $textoNativo;
                    continue;
                }

                $paginasCandidatasOcr[] = $numeroPagina;

                $razon = (string)($paginaInfo['quality']['reason'] ?? 'insuficiente');
                $eventos[] = 'pagina_' . $numeroPagina . '_candidata_ocr:' . $razon;
            }

            $paginasCandidatasOcr = array_values(array_unique($paginasCandidatasOcr));
            sort($paginasCandidatasOcr);
        }

        if (!empty($paginasCandidatasOcr)) {
            $inicioOcrSelectivo = microtime(true);
            $this->reportPhase(
                $phaseCallback,
                'procesando_ocr',
                42,
                'ocr_selectivo',
                ['paginas_candidatas' => $paginasCandidatasOcr]
            );

            if (!$pdfToImage->estaDisponible() || !$ocr->estaDisponible()) {
                $eventos[] = 'ocr_selectivo_no_disponible';

                foreach ($paginasCandidatasOcr as $pagina) {
                    $textoNativo = trim((string)($textoNativoPorPagina[$pagina] ?? ''));
                    if ($textoNativo !== '') {
                        $textoPorPagina[$pagina] = $textoNativo;
                        $trazabilidadPaginas[$pagina]['metodo'] = 'texto_nativo';
                    }
                    $trazabilidadPaginas[$pagina]['error_parcial'] = true;
                }
            } else {
                $imagenesPorPagina = [];

                try {
                    $imagenesPorPagina = $pdfToImage->convertirPaginas($pdfPath, $imagePrefix, $paginasCandidatasOcr);
                } catch (Throwable $e) {
                    $eventos[] = 'ocr_selectivo_conversion_error:' . $e->getMessage();
                }

                foreach ($paginasCandidatasOcr as $pagina) {
                    if (!isset($trazabilidadPaginas[$pagina])) {
                        $trazabilidadPaginas[$pagina] = [
                            'numero_pagina' => $pagina,
                            'metodo' => 'ocr',
                            'tiempo_procesamiento_ms' => 0.0,
                            'error_parcial' => false,
                        ];
                    }

                    $textoNativo = (string)($textoNativoPorPagina[$pagina] ?? '');
                    $metodo = 'ocr';
                    $errorParcial = (bool)$trazabilidadPaginas[$pagina]['error_parcial'];

                    if (!isset($imagenesPorPagina[$pagina])) {
                        $errorParcial = true;
                        if (trim($textoNativo) !== '') {
                            $textoPorPagina[$pagina] = $textoNativo;
                            $metodo = 'texto_nativo';
                        }
                        $eventos[] = 'pagina_' . $pagina . '_sin_imagen_ocr';
                    } else {
                        $detalleOcr = $ocr->procesarImagen($imagenesPorPagina[$pagina], false);

                        $trazabilidadPaginas[$pagina]['tiempo_procesamiento_ms'] = round(
                            (float)$trazabilidadPaginas[$pagina]['tiempo_procesamiento_ms']
                            + (float)($detalleOcr['tiempo_ms'] ?? 0.0),
                            2
                        );

                        if (($detalleOcr['error'] ?? null) !== null) {
                            $errorParcial = true;
                            if (trim($textoNativo) !== '') {
                                $textoPorPagina[$pagina] = $textoNativo;
                                $metodo = 'texto_nativo';
                            }
                            $eventos[] = 'pagina_' . $pagina . '_ocr_error:' . (string)$detalleOcr['error'];
                        } else {
                            $textoOcr = $this->normalizarTexto((string)($detalleOcr['texto'] ?? ''));

                            if ($textoOcr === '') {
                                $errorParcial = true;
                                if (trim($textoNativo) !== '') {
                                    $textoPorPagina[$pagina] = $textoNativo;
                                    $metodo = 'texto_nativo';
                                }
                                $eventos[] = 'pagina_' . $pagina . '_ocr_vacio';
                            } else {
                                if (trim($textoNativo) !== '') {
                                    $textoPorPagina[$pagina] = $this->combinarTextoNativoYOcr($textoNativo, $textoOcr);
                                    $metodo = 'mixto';
                                } else {
                                    $textoPorPagina[$pagina] = $textoOcr;
                                    $metodo = 'ocr';
                                }
                            }
                        }
                    }

                    $trazabilidadPaginas[$pagina]['metodo'] = $metodo;
                    $trazabilidadPaginas[$pagina]['error_parcial'] = $errorParcial;
                }
            }

            $timings['ocr_ms'] += $this->elapsedMs($inicioOcrSelectivo);
        }

        $textoConsolidado = $this->consolidarTextoPorPagina($textoPorPagina);

        if (trim($textoConsolidado) === '') {
            $fallbackLegacyActivado = true;
            $eventos[] = 'activando_fallback_legacy';
            $this->reportPhase($phaseCallback, 'procesando_ocr', 58, 'fallback_ocr_legacy');

            $inicioLegacy = microtime(true);

            if (!$pdfToImage->estaDisponible() || !$ocr->estaDisponible()) {
                $eventos[] = 'fallback_legacy_no_disponible';
            } else {
                try {
                    $legacy = $this->procesarConFlujoLegacy($pdfPath, $imagePrefix . '-legacy', $pdfToImage, $ocr);
                    $textoPorPagina = $legacy['texto_por_pagina'];
                    $trazabilidadPaginas = $legacy['trazabilidad_paginas'];
                    $paginasDetectadas = $legacy['paginas_detectadas'];
                    $textoConsolidado = $this->consolidarTextoPorPagina($textoPorPagina);
                    $eventos = array_merge($eventos, $legacy['eventos']);
                } catch (Throwable $e) {
                    $eventos[] = 'fallback_legacy_error:' . $e->getMessage();
                }
            }

            $timings['ocr_ms'] += $this->elapsedMs($inicioLegacy);
        }

        if ($paginasDetectadas === 0) {
            $paginasDetectadas = count($trazabilidadPaginas);
        }

        if (file_put_contents($textFile, $textoConsolidado) === false) {
            throw new Exception('No se pudo guardar el TXT generado: ' . $textFile);
        }

        $this->reportPhase($phaseCallback, 'extrayendo_meritos', 72, 'limpieza_texto');
        $inicioLimpieza = microtime(true);
        $cleaner = new TextCleaner();
        $textoLimpio = $cleaner->limpiar($textoConsolidado);
        $timings['limpieza_ms'] = $this->elapsedMs($inicioLimpieza);

        $this->reportPhase($phaseCallback, 'extrayendo_meritos', 82, 'extraccion_semantica');
        $inicioSemantica = microtime(true);
        $extractor = new AnecaExtractor();
        $datos = $extractor->extraer($textoLimpio);
        $timings['extraccion_semantica_ms'] = $this->elapsedMs($inicioSemantica);
        $this->reportPhase($phaseCallback, 'calculando_puntuacion', 90, 'calculando_puntuacion');
        $this->reportPhase($phaseCallback, 'validando_resultado', 95, 'validando_resultado');

        $datos['archivo_pdf'] = basename($pdfPath);
        $datos['paginas_detectadas'] = $paginasDetectadas;
        $datos['txt_generado'] = basename($textFile);
        $datos['json_generado'] = basename($jsonFile);

        $legacyResultValidator = new LegacyPipelineResultValidator();
        $validation = $legacyResultValidator->validate($datos);
        if ((string)($validation['validation_status'] ?? '') === LegacyPipelineResultValidator::STATUS_INVALIDO) {
            throw new Exception(
                'Resultado legacy del pipeline invalido: ' . $legacyResultValidator->summarize($validation)
            );
        }

        $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('No se pudo serializar el JSON final.');
        }

        if (file_put_contents($jsonFile, $json) === false) {
            throw new Exception('No se pudo guardar JSON: ' . $jsonFile);
        }

        $canonicalNormalization = [
            'enabled' => true,
            'schema' => 'contrato-canonico-aneca-v1.schema.json',
            'status' => 'no_generado',
            'archivo_json_canonico' => null,
            'ready' => false,
            'validation_status' => null,
            'validation_errors' => [],
            'validation_warnings' => [],
            'validation_metrics' => [],
            'error' => null,
        ];

        try {
            $canonicalAdapter = new AnecaCanonicalAdapter();
            $canonicalValidator = new AnecaCanonicalResultValidator();
            $canonicalPayload = $canonicalAdapter->adaptFromLegacyPipelineResult(
                $datos,
                [
                    'archivo_pdf' => basename($pdfPath),
                    'json_generado' => basename($jsonFile),
                    'texto_extraido' => $textoConsolidado,
                    'fecha_extraccion' => gmdate('c'),
                    'version_esquema' => '1.0',
                    'requiere_revision_manual' => true,
                ]
            );

            $canonicalJsonFile = $jsonDir . $baseName . '.aneca.canonico.json';
            $canonicalJson = json_encode($canonicalPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($canonicalJson === false) {
                throw new Exception('No se pudo serializar JSON canonico ANECA.');
            }

            if (file_put_contents($canonicalJsonFile, $canonicalJson) === false) {
                throw new Exception('No se pudo guardar JSON canonico ANECA: ' . $canonicalJsonFile);
            }

            $canonicalValidation = $canonicalValidator->validate($canonicalPayload);
            $canonicalReady = !empty($canonicalValidation['aneca_canonical_ready']);
            $canonicalStatus = (string)($canonicalValidation['aneca_canonical_validation_status'] ?? '');

            $canonicalNormalization['status'] = $canonicalReady ? 'ok' : 'generado_no_listo';
            $canonicalNormalization['archivo_json_canonico'] = basename($canonicalJsonFile);
            $canonicalNormalization['ready'] = $canonicalReady;
            $canonicalNormalization['validation_status'] = $canonicalStatus !== '' ? $canonicalStatus : null;
            $canonicalNormalization['validation_errors'] = is_array($canonicalValidation['aneca_canonical_validation_errors'] ?? null)
                ? $canonicalValidation['aneca_canonical_validation_errors']
                : [];
            $canonicalNormalization['validation_warnings'] = is_array($canonicalValidation['aneca_canonical_validation_warnings'] ?? null)
                ? $canonicalValidation['aneca_canonical_validation_warnings']
                : [];
            $canonicalNormalization['validation_metrics'] = is_array($canonicalValidation['aneca_canonical_metrics'] ?? null)
                ? $canonicalValidation['aneca_canonical_metrics']
                : [];

            if ($canonicalReady) {
                $eventos[] = 'normalizacion_aneca_ok';
            } else {
                $eventos[] = 'normalizacion_aneca_no_lista:' . ($canonicalStatus !== '' ? $canonicalStatus : 'desconocido');
            }
        } catch (Throwable $e) {
            $canonicalNormalization['status'] = 'error';
            $canonicalNormalization['error'] = $e->getMessage();
            $eventos[] = 'normalizacion_aneca_error:' . $e->getMessage();
        }

        $timings['total_ms'] = $this->elapsedMs($inicioTotal);

        ksort($trazabilidadPaginas, SORT_NUMERIC);
        $trazabilidadPaginas = array_values($trazabilidadPaginas);

        $trace = [
            'pipeline' => 'hibrido_por_pagina_v1',
            'run_id' => $runId,
            'archivo_pdf' => basename($pdfPath),
            'paginas_detectadas' => $paginasDetectadas,
            'paginas_candidatas_ocr' => $paginasCandidatasOcr,
            'fallback_legacy_activado' => $fallbackLegacyActivado,
            'ocr_environment' => $environmentCheck,
            'timings_ms' => $this->roundTimingArray($timings),
            'cache' => $cacheContext,
            'validation_status' => $validation['validation_status'] ?? null,
            'validation_errors' => $validation['validation_errors'] ?? [],
            'validation_warnings' => $validation['validation_warnings'] ?? [],
            'normalizacion_contrato_canonico_aneca' => $canonicalNormalization,
            'trazabilidad_paginas' => $trazabilidadPaginas,
            'eventos' => $eventos,
            'artefactos' => [
                'txt_generado' => basename($textFile),
                'json_generado' => basename($jsonFile),
                'json_canonico_aneca' => $canonicalNormalization['archivo_json_canonico'],
            ],
        ];

        $this->guardarArtefactosTrazabilidad($traceFile, $logFile, $trace);
        $this->reportPhase($phaseCallback, 'validando_resultado', 100, 'pipeline_finalizado');

        return $datos;
    }

    private function procesarConFlujoLegacy(string $pdfPath, string $imagePrefix, PdfToImage $pdfToImage, OcrProcessor $ocr): array
    {
        $imagenes = $pdfToImage->convertir($pdfPath, $imagePrefix);
        if (empty($imagenes)) {
            throw new Exception('No se generaron imagenes del PDF en fallback legacy.');
        }

        $textoPorPagina = [];
        $trazabilidadPaginas = [];
        $eventos = [];

        foreach ($imagenes as $index => $imagenPath) {
            $numeroPagina = $this->inferirNumeroPaginaDesdeImagen($imagenPath, $index + 1);
            $detalleOcr = $ocr->procesarImagen($imagenPath, false);

            $textoPagina = $this->normalizarTexto((string)($detalleOcr['texto'] ?? ''));
            $error = (string)($detalleOcr['error'] ?? '');

            if ($error === '' && $textoPagina !== '') {
                $textoPorPagina[$numeroPagina] = $textoPagina;
                $errorParcial = false;
            } else {
                $errorParcial = true;
                $eventos[] = 'legacy_pagina_' . $numeroPagina . '_error:' . ($error !== '' ? $error : 'ocr_vacio');
            }

            $trazabilidadPaginas[$numeroPagina] = [
                'numero_pagina' => $numeroPagina,
                'metodo' => 'ocr',
                'tiempo_procesamiento_ms' => round((float)($detalleOcr['tiempo_ms'] ?? 0.0), 2),
                'error_parcial' => $errorParcial,
            ];
        }

        ksort($textoPorPagina, SORT_NUMERIC);
        ksort($trazabilidadPaginas, SORT_NUMERIC);

        return [
            'texto_por_pagina' => $textoPorPagina,
            'trazabilidad_paginas' => $trazabilidadPaginas,
            'paginas_detectadas' => count($imagenes),
            'eventos' => $eventos,
        ];
    }

    private function inferirNumeroPaginaDesdeImagen(string $imagePath, int $fallback): int
    {
        $fileName = basename($imagePath);

        if (preg_match('/-(\d+)\.png$/i', $fileName, $m) === 1) {
            return max(1, (int)$m[1]);
        }

        if (preg_match('/-p(\d{1,})\.png$/i', $fileName, $m) === 1) {
            return max(1, (int)$m[1]);
        }

        return max(1, $fallback);
    }

    private function prepararBaseCache(string $pdfPath, ProcessingCache $cache): array
    {
        try {
            $hashPdf = $cache->calculatePdfHash($pdfPath);
            $cacheKey = $cache->buildCacheKeyFromHash($hashPdf);
            $versions = $cache->getVersions();

            return [
                // Campos legacy conservados para compatibilidad en trazas historicas.
                'pdf_hash_sha256' => $hashPdf,
                'cache_ready' => true,
                'cache_result_file' => $cacheKey . '.result.json',
                'cache_trace_file' => null,
                'cache_meta_file' => $cacheKey . '.meta.json',
                // Campos nuevos de trazabilidad de cache.
                'hash_pdf' => $hashPdf,
                'cache_key' => $cacheKey,
                'cache_hit' => false,
                'cache_estado' => 'miss',
                'motivo_invalidation' => null,
                'version_pipeline' => $versions['version_pipeline'] ?? null,
                'version_baremo' => $versions['version_baremo'] ?? null,
                'version_schema' => $versions['version_schema'] ?? null,
                'cache_text_file' => $cacheKey . '.text.txt',
            ];
        } catch (Throwable $e) {
            return [
                'pdf_hash_sha256' => null,
                'cache_ready' => false,
                'cache_result_file' => null,
                'cache_trace_file' => null,
                'cache_meta_file' => null,
                'hash_pdf' => null,
                'cache_key' => null,
                'cache_hit' => false,
                'cache_estado' => 'error',
                'motivo_invalidation' => 'cache_context_error',
                'version_pipeline' => null,
                'version_baremo' => null,
                'version_schema' => null,
                'cache_text_file' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function ensureDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                continue;
            }

            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new Exception('No se pudo crear el directorio: ' . $directory);
            }
        }
    }

    private function consolidarTextoPorPagina(array $textoPorPagina): string
    {
        if (empty($textoPorPagina)) {
            return '';
        }

        ksort($textoPorPagina, SORT_NUMERIC);

        $bloques = [];
        foreach ($textoPorPagina as $texto) {
            $texto = $this->normalizarTexto((string)$texto);
            if ($texto !== '') {
                $bloques[] = $texto;
            }
        }

        return implode("\n\n", $bloques);
    }

    private function combinarTextoNativoYOcr(string $textoNativo, string $textoOcr): string
    {
        $resultado = [];
        $vistos = [];

        foreach ([$textoNativo, $textoOcr] as $bloque) {
            $lineas = preg_split('/\R/u', $bloque) ?: [];
            foreach ($lineas as $linea) {
                $linea = trim($linea);
                if ($linea === '') {
                    continue;
                }

                $normalizada = preg_replace('/\s+/u', ' ', $linea) ?? $linea;
                $key = function_exists('mb_strtolower')
                    ? mb_strtolower($normalizada, 'UTF-8')
                    : strtolower($normalizada);

                if (isset($vistos[$key])) {
                    continue;
                }

                $vistos[$key] = true;
                $resultado[] = $linea;
            }
        }

        return implode("\n", $resultado);
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;
        return trim($texto);
    }

    private function guardarArtefactosTrazabilidad(string $traceFile, string $logFile, array $trace): void
    {
        $traceJson = json_encode($trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($traceJson !== false) {
            @file_put_contents($traceFile, $traceJson);
        }

        $metodos = [
            'texto_nativo' => 0,
            'ocr' => 0,
            'mixto' => 0,
            'otros' => 0,
        ];
        $erroresParciales = 0;

        foreach (($trace['trazabilidad_paginas'] ?? []) as $pagina) {
            $metodo = (string)($pagina['metodo'] ?? 'otros');
            if (!isset($metodos[$metodo])) {
                $metodos['otros']++;
            } else {
                $metodos[$metodo]++;
            }

            if (!empty($pagina['error_parcial'])) {
                $erroresParciales++;
            }
        }

        $timings = $trace['timings_ms'] ?? [];
        $ocrEnvironment = is_array($trace['ocr_environment'] ?? null)
            ? $trace['ocr_environment']
            : [];
        $validationStatus = (string)($trace['validation_status'] ?? '');
        $validationErrors = is_array($trace['validation_errors'] ?? null) ? $trace['validation_errors'] : [];
        $validationWarnings = is_array($trace['validation_warnings'] ?? null) ? $trace['validation_warnings'] : [];
        $canonicalNormalization = is_array($trace['normalizacion_contrato_canonico_aneca'] ?? null)
            ? $trace['normalizacion_contrato_canonico_aneca']
            : [];
        $canonicalReady = !empty($canonicalNormalization['ready']);
        $canonicalValidationStatus = (string)($canonicalNormalization['validation_status'] ?? '');
        $canonicalValidationErrors = is_array($canonicalNormalization['validation_errors'] ?? null)
            ? $canonicalNormalization['validation_errors']
            : [];
        $canonicalValidationWarnings = is_array($canonicalNormalization['validation_warnings'] ?? null)
            ? $canonicalNormalization['validation_warnings']
            : [];
        $canonicalFile = (string)($canonicalNormalization['archivo_json_canonico'] ?? '');
        $pdftoppmInfo = is_array($ocrEnvironment['pdf_tools']['pdftoppm'] ?? null)
            ? $ocrEnvironment['pdf_tools']['pdftoppm']
            : [];
        $tesseractInfo = is_array($ocrEnvironment['ocr_tools']['tesseract'] ?? null)
            ? $ocrEnvironment['ocr_tools']['tesseract']
            : [];

        $lineas = [
            'run_id=' . (string)($trace['run_id'] ?? ''),
            'pipeline=' . (string)($trace['pipeline'] ?? ''),
            'archivo_pdf=' . (string)($trace['archivo_pdf'] ?? ''),
            'paginas_detectadas=' . (int)($trace['paginas_detectadas'] ?? 0),
            'paginas_candidatas_ocr=' . implode(',', (array)($trace['paginas_candidatas_ocr'] ?? [])),
            'fallback_legacy_activado=' . (!empty($trace['fallback_legacy_activado']) ? 'true' : 'false'),
            'ocr_env_mode=' . (string)($ocrEnvironment['mode'] ?? ''),
            'ocr_ready=' . (!empty($ocrEnvironment['ocr_ready']) ? 'true' : 'false'),
            'pdftoppm_available=' . (!empty($pdftoppmInfo['available']) ? 'true' : 'false'),
            'pdftoppm_path=' . (string)($pdftoppmInfo['path'] ?? ''),
            'pdftoppm_version=' . (string)($pdftoppmInfo['version'] ?? ''),
            'tesseract_available=' . (!empty($tesseractInfo['available']) ? 'true' : 'false'),
            'tesseract_path=' . (string)($tesseractInfo['path'] ?? ''),
            'tesseract_version=' . (string)($tesseractInfo['version'] ?? ''),
            'ocr_env_warnings=' . implode(' || ', (array)($ocrEnvironment['warnings'] ?? [])),
            'timing_total_ms=' . (float)($timings['total_ms'] ?? 0.0),
            'timing_extraccion_nativa_ms=' . (float)($timings['extraccion_nativa_ms'] ?? 0.0),
            'timing_ocr_ms=' . (float)($timings['ocr_ms'] ?? 0.0),
            'timing_limpieza_ms=' . (float)($timings['limpieza_ms'] ?? 0.0),
            'timing_extraccion_semantica_ms=' . (float)($timings['extraccion_semantica_ms'] ?? 0.0),
            'validation_status=' . $validationStatus,
            'validation_errors=' . implode(' || ', $validationErrors),
            'validation_warnings=' . implode(' || ', $validationWarnings),
            'aneca_canonical_file=' . $canonicalFile,
            'aneca_canonical_ready=' . ($canonicalReady ? 'true' : 'false'),
            'aneca_canonical_validation_status=' . $canonicalValidationStatus,
            'aneca_canonical_validation_errors=' . implode(' || ', $canonicalValidationErrors),
            'aneca_canonical_validation_warnings=' . implode(' || ', $canonicalValidationWarnings),
            'paginas_texto_nativo=' . $metodos['texto_nativo'],
            'paginas_ocr=' . $metodos['ocr'],
            'paginas_mixto=' . $metodos['mixto'],
            'paginas_otras=' . $metodos['otros'],
            'paginas_con_error_parcial=' . $erroresParciales,
            'eventos=' . implode(' | ', (array)($trace['eventos'] ?? [])),
        ];

        @file_put_contents($logFile, implode(PHP_EOL, $lineas) . PHP_EOL);

        error_log('[meritos-scraping] run=' . (string)($trace['run_id'] ?? '')
            . ' total_ms=' . (string)($timings['total_ms'] ?? 0.0)
            . ' nativo_ms=' . (string)($timings['extraccion_nativa_ms'] ?? 0.0)
            . ' ocr_ms=' . (string)($timings['ocr_ms'] ?? 0.0)
            . ' paginas=' . (string)($trace['paginas_detectadas'] ?? 0)
        );
    }

    private function roundTimingArray(array $timings): array
    {
        foreach ($timings as $key => $value) {
            $timings[$key] = round((float)$value, 2);
        }

        return $timings;
    }

    private function elapsedMs(float $inicio): float
    {
        return round((microtime(true) - $inicio) * 1000, 2);
    }

    private function reportPhase(
        ?callable $phaseCallback,
        string $estado,
        int $progreso,
        string $fase,
        array $context = []
    ): void {
        if ($phaseCallback === null) {
            return;
        }

        $progreso = max(0, min(100, $progreso));

        try {
            $phaseCallback($estado, $progreso, $fase, $context);
        } catch (Throwable $e) {
            // El callback se usa solo para observabilidad y no debe romper la ejecución principal.
            error_log('[meritos-scraping] phase_callback_error=' . $e->getMessage());
        }
    }

    private function checkEnvironmentSafely(): array
    {
        try {
            $checker = new OcrEnvironmentChecker();
            return $checker->check();
        } catch (Throwable $e) {
            return [
                'pdf_tools' => [
                    'pdftoppm' => [
                        'available' => false,
                        'path' => null,
                        'version' => null,
                    ],
                ],
                'ocr_tools' => [
                    'tesseract' => [
                        'available' => false,
                        'path' => null,
                        'version' => null,
                    ],
                ],
                'ocr_ready' => false,
                'mode' => 'native_only',
                'warnings' => [
                    'No se pudo completar el check de entorno OCR: ' . $e->getMessage(),
                ],
            ];
        }
    }
}
