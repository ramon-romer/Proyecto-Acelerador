<?php

require_once __DIR__ . '/ProcessingJobQueue.php';
require_once __DIR__ . '/ProcessingCache.php';

class CvProcessingJobService
{
    private $queue;
    private $cache;
    private $pdfDir;
    private $heavyThresholdBytes;

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

        if (!is_dir($this->pdfDir) && !mkdir($this->pdfDir, 0777, true) && !is_dir($this->pdfDir)) {
            throw new Exception('No se pudo crear el directorio de PDFs: ' . $this->pdfDir);
        }
    }

    public function enqueueFromUpload(array $uploadedFile, bool $alwaysQueue = false): array
    {
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
            error_log(
                '[meritos-scraping] cache_hit_subida hash=' . $hashPdf
                . ' cache_key=' . (string)($cacheLookup['cache_key'] ?? '')
            );

            return [
                'job' => null,
                'queued_only' => false,
                'is_heavy' => $isHeavy,
                'heavy_threshold_bytes' => $this->heavyThresholdBytes,
                'stored_pdf_path' => null,
                'cache_hit' => true,
                'cache' => $cacheLookup,
                'cached_result' => $cacheLookup['resultado_json'],
            ];
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
}
