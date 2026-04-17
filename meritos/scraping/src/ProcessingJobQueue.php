<?php

class ProcessingJobQueue
{
    private $jobsDir;
    private $jobLogsDir;

    public function __construct(?string $jobsDir = null)
    {
        $this->jobsDir = $jobsDir ?? (__DIR__ . '/../output/jobs');
        $this->jobLogsDir = $this->jobsDir . '/logs';
        $this->ensureDirectories();
    }

    public function createJobForPdfPath(string $pdfPath, array $metadata = []): array
    {
        if (!is_file($pdfPath)) {
            throw new Exception('No existe el PDF para crear job: ' . $pdfPath);
        }

        $jobId = $this->generateJobId();
        $createdAt = gmdate('c');
        $pdfHash = hash_file('sha256', $pdfPath);

        if (!is_string($pdfHash) || $pdfHash === '') {
            $pdfHash = '';
        }

        $jobLogPath = $this->jobLogsDir . '/' . $jobId . '.log';
        $job = [
            'id' => $jobId,
            'archivo_pdf' => basename($pdfPath),
            'pdf_path' => $pdfPath,
            'hash_pdf' => $pdfHash,
            'estado' => 'pendiente',
            'progreso_porcentaje' => 0,
            'fase_actual' => 'pendiente',
            'resultado_json' => null,
            'error_mensaje' => null,
            'cache_hit' => false,
            'cache_key' => null,
            'cache_estado' => null,
            'motivo_invalidation' => null,
            'cache_guardada' => false,
            'fecha_creacion' => $createdAt,
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'tiempo_total_ms' => null,
            'trace_path' => null,
            'log_path' => $jobLogPath,
            'error_parcial' => false,
            'fecha_actualizacion' => $createdAt,
        ];

        foreach ($metadata as $key => $value) {
            $job[$key] = $value;
        }

        $this->writeJob($job);
        $this->appendLog($jobId, 'job_creado estado=pendiente archivo=' . basename($pdfPath));

        return $job;
    }

    public function getJob(string $jobId): ?array
    {
        $jobPath = $this->jobFilePath($jobId);
        if (!is_file($jobPath)) {
            return null;
        }

        $json = file_get_contents($jobPath);
        if ($json === false) {
            throw new Exception('No se pudo leer el job: ' . $jobId);
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new Exception('JSON de job invalido: ' . $jobId);
        }

        return $data;
    }

    public function listJobs(array $states = [], int $limit = 50): array
    {
        $limit = $this->limitInt($limit, 1, 1000);
        $files = glob($this->jobsDir . '/*.json') ?: [];
        $jobs = [];

        foreach ($files as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }

            $job = json_decode($json, true);
            if (!is_array($job)) {
                continue;
            }

            if (!empty($states) && !in_array((string)($job['estado'] ?? ''), $states, true)) {
                continue;
            }

            $jobs[] = $job;
        }

        usort(
            $jobs,
            function (array $a, array $b): int {
                return strcmp((string)($a['fecha_creacion'] ?? ''), (string)($b['fecha_creacion'] ?? ''));
            }
        );

        if (count($jobs) > $limit) {
            $jobs = array_slice($jobs, 0, $limit);
        }

        return $jobs;
    }

    public function listPendingJobs(int $limit = 10): array
    {
        return $this->listJobs(['pendiente'], $limit);
    }

    public function claimNextPendingJob(): ?array
    {
        $pending = $this->listPendingJobs(100);

        foreach ($pending as $job) {
            $jobId = (string)($job['id'] ?? '');
            if ($jobId === '') {
                continue;
            }

            try {
                $claimed = $this->updateJob(
                    $jobId,
                    function (array $current): array {
                        if ((string)($current['estado'] ?? '') !== 'pendiente') {
                            return $current;
                        }

                        $now = gmdate('c');
                        $current['estado'] = 'procesando_pdf';
                        $current['progreso_porcentaje'] = max(5, (int)($current['progreso_porcentaje'] ?? 0));
                        $current['fase_actual'] = 'procesando_pdf';
                        if (($current['fecha_inicio'] ?? null) === null) {
                            $current['fecha_inicio'] = $now;
                        }
                        $current['fecha_actualizacion'] = $now;

                        return $current;
                    }
                );
            } catch (Throwable $e) {
                continue;
            }

            if ((string)($claimed['estado'] ?? '') === 'procesando_pdf') {
                $this->appendLog($jobId, 'job_reclamado worker estado=procesando_pdf');
                return $claimed;
            }
        }

        return null;
    }

    public function setPhase(string $jobId, string $estado, int $progreso, string $faseActual): array
    {
        $progreso = $this->limitInt($progreso, 0, 100);

        $job = $this->updateJob(
            $jobId,
            function (array $current) use ($estado, $progreso, $faseActual): array {
                $current['estado'] = $estado;
                $current['progreso_porcentaje'] = $progreso;
                $current['fase_actual'] = $faseActual;

                if (($current['fecha_inicio'] ?? null) === null) {
                    $current['fecha_inicio'] = gmdate('c');
                }

                $current['fecha_actualizacion'] = gmdate('c');

                return $current;
            }
        );

        $this->appendLog(
            $jobId,
            'fase=' . $faseActual . ' estado=' . $estado . ' progreso=' . $progreso
        );

        return $job;
    }

    public function markCompleted(
        string $jobId,
        array $resultadoJson,
        ?string $tracePath,
        ?string $pipelineLogPath,
        float $tiempoTotalMs,
        bool $errorParcial = false,
        ?string $errorMensaje = null
    ): array {
        $estadoFinal = $errorParcial ? 'error_parcial' : 'completado';

        $job = $this->updateJob(
            $jobId,
            function (array $current) use (
                $resultadoJson,
                $tracePath,
                $pipelineLogPath,
                $tiempoTotalMs,
                $estadoFinal,
                $errorParcial,
                $errorMensaje
            ): array {
                $now = gmdate('c');

                $current['estado'] = $estadoFinal;
                $current['progreso_porcentaje'] = 100;
                $current['fase_actual'] = $estadoFinal;
                $current['resultado_json'] = $resultadoJson;
                $current['error_parcial'] = $errorParcial;
                $current['error_mensaje'] = $errorMensaje;
                $current['fecha_fin'] = $now;
                $current['fecha_actualizacion'] = $now;
                $current['tiempo_total_ms'] = round($tiempoTotalMs, 2);
                $current['trace_path'] = $tracePath;
                $current['log_path'] = $current['log_path'] ?? $pipelineLogPath;
                $current['pipeline_log_path'] = $pipelineLogPath;

                if (($current['fecha_inicio'] ?? null) === null) {
                    $current['fecha_inicio'] = $now;
                }

                return $current;
            }
        );

        $this->appendLog(
            $jobId,
            'job_finalizado estado=' . $estadoFinal
            . ' tiempo_total_ms=' . round($tiempoTotalMs, 2)
            . ($errorParcial ? ' error_parcial=true' : '')
        );

        return $job;
    }

    public function markError(
        string $jobId,
        string $mensaje,
        ?string $tracePath,
        ?string $pipelineLogPath,
        float $tiempoTotalMs,
        bool $errorParcial = false
    ): array {
        $estadoFinal = $errorParcial ? 'error_parcial' : 'error';

        $job = $this->updateJob(
            $jobId,
            function (array $current) use (
                $mensaje,
                $tracePath,
                $pipelineLogPath,
                $tiempoTotalMs,
                $estadoFinal,
                $errorParcial
            ): array {
                $now = gmdate('c');

                $current['estado'] = $estadoFinal;
                $current['fase_actual'] = $estadoFinal;
                $current['error_mensaje'] = $mensaje;
                $current['error_parcial'] = $errorParcial;
                $current['fecha_fin'] = $now;
                $current['fecha_actualizacion'] = $now;
                $current['tiempo_total_ms'] = round($tiempoTotalMs, 2);
                $current['trace_path'] = $tracePath;
                $current['pipeline_log_path'] = $pipelineLogPath;

                if (($current['fecha_inicio'] ?? null) === null) {
                    $current['fecha_inicio'] = $now;
                }

                return $current;
            }
        );

        $this->appendLog(
            $jobId,
            'job_error estado=' . $estadoFinal . ' mensaje=' . $mensaje
        );

        return $job;
    }

    public function appendLog(string $jobId, string $message): void
    {
        $jobId = $this->normalizeJobId($jobId);
        $path = $this->jobLogsDir . '/' . $jobId . '.log';
        $line = '[' . gmdate('c') . '] ' . $message . PHP_EOL;
        @file_put_contents($path, $line, FILE_APPEND);
    }

    public function updateJob(string $jobId, callable $mutator): array
    {
        $jobId = $this->normalizeJobId($jobId);
        $lockHandle = $this->openLock($jobId);

        try {
            $job = $this->readJobOrFail($jobId);
            $updated = $mutator($job);

            if (!is_array($updated)) {
                throw new Exception('Mutator invalido para job: ' . $jobId);
            }

            $updated['id'] = $jobId;
            $updated['fecha_actualizacion'] = gmdate('c');

            $this->writeJob($updated);

            return $updated;
        } finally {
            $this->unlock($lockHandle);
        }
    }

    private function ensureDirectories(): void
    {
        foreach ([$this->jobsDir, $this->jobLogsDir] as $dir) {
            if (is_dir($dir)) {
                continue;
            }

            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new Exception('No se pudo crear directorio de jobs: ' . $dir);
            }
        }
    }

    private function readJobOrFail(string $jobId): array
    {
        $job = $this->getJob($jobId);
        if (!is_array($job)) {
            throw new Exception('Job no encontrado: ' . $jobId);
        }

        return $job;
    }

    private function writeJob(array $job): void
    {
        $jobId = $this->normalizeJobId((string)($job['id'] ?? ''));
        $path = $this->jobFilePath($jobId);
        $json = json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new Exception('No se pudo serializar el job: ' . $jobId);
        }

        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $json) === false) {
            throw new Exception('No se pudo escribir temporal de job: ' . $jobId);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new Exception('No se pudo guardar job atomico: ' . $jobId);
        }
    }

    private function jobFilePath(string $jobId): string
    {
        $jobId = $this->normalizeJobId($jobId);
        return $this->jobsDir . '/' . $jobId . '.json';
    }

    private function lockFilePath(string $jobId): string
    {
        $jobId = $this->normalizeJobId($jobId);
        return $this->jobsDir . '/' . $jobId . '.lock';
    }

    private function openLock(string $jobId)
    {
        $lockPath = $this->lockFilePath($jobId);
        $handle = @fopen($lockPath, 'c+');

        if ($handle === false) {
            throw new Exception('No se pudo abrir lock de job: ' . $jobId);
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new Exception('No se pudo bloquear job: ' . $jobId);
        }

        return $handle;
    }

    private function unlock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function normalizeJobId(string $jobId): string
    {
        if ($jobId === '' || preg_match('/^[A-Za-z0-9._-]+$/', $jobId) !== 1) {
            throw new Exception('job_id invalido');
        }

        return $jobId;
    }

    private function generateJobId(): string
    {
        return 'job_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }

    private function limitInt(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
