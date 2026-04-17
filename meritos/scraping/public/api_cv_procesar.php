<?php

require_once __DIR__ . '/../src/CvProcessingJobService.php';
require_once __DIR__ . '/../src/ProcessingJobQueue.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$route = resolveRoute();

try {
    $queue = new ProcessingJobQueue();

    if ($method === 'POST' && $route['action'] === 'create') {
        if (!isset($_FILES['pdf']) || !is_array($_FILES['pdf'])) {
            respondJson(['ok' => false, 'message' => 'Falta archivo PDF en campo pdf.'], 400);
        }

        $service = new CvProcessingJobService($queue);
        $enqueued = $service->enqueueFromUpload($_FILES['pdf'], true);
        $job = $enqueued['job'];

        respondJson(
            [
                'ok' => true,
                'message' => 'Job creado en cola.',
                'job_id' => $job['id'],
                'estado' => $job['estado'],
                'progreso_porcentaje' => $job['progreso_porcentaje'],
                'fase_actual' => $job['fase_actual'],
                'es_pesado' => $enqueued['is_heavy'],
                'endpoints' => [
                    'estado' => '/api/cv/procesar/' . $job['id'] . '/estado',
                    'resultado' => '/api/cv/procesar/' . $job['id'] . '/resultado',
                ],
            ],
            202
        );
    }

    if ($method === 'GET' && $route['action'] === 'estado') {
        $job = loadJobOr404($queue, (string)$route['job_id']);

        respondJson(
            [
                'ok' => true,
                'job_id' => $job['id'],
                'estado' => $job['estado'],
                'progreso_porcentaje' => $job['progreso_porcentaje'],
                'fase_actual' => $job['fase_actual'],
                'error_parcial' => (bool)($job['error_parcial'] ?? false),
                'error_mensaje' => $job['error_mensaje'],
                'fecha_creacion' => $job['fecha_creacion'],
                'fecha_inicio' => $job['fecha_inicio'],
                'fecha_fin' => $job['fecha_fin'],
                'tiempo_total_ms' => $job['tiempo_total_ms'],
                'trace_path' => $job['trace_path'],
                'log_path' => $job['log_path'],
                'pipeline_log_path' => $job['pipeline_log_path'] ?? null,
            ],
            200
        );
    }

    if ($method === 'GET' && $route['action'] === 'resultado') {
        $job = loadJobOr404($queue, (string)$route['job_id']);

        $estado = (string)($job['estado'] ?? '');

        if ($estado === 'error') {
            respondJson(
                [
                    'ok' => false,
                    'job_id' => $job['id'],
                    'estado' => $estado,
                    'message' => 'El job termino con error.',
                    'error_mensaje' => $job['error_mensaje'],
                ],
                409
            );
        }

        if (!in_array($estado, ['completado', 'error_parcial'], true)) {
            respondJson(
                [
                    'ok' => false,
                    'job_id' => $job['id'],
                    'estado' => $estado,
                    'message' => 'El resultado todavia no esta disponible.',
                ],
                202
            );
        }

        respondJson(
            [
                'ok' => true,
                'job_id' => $job['id'],
                'estado' => $estado,
                'error_parcial' => (bool)($job['error_parcial'] ?? false),
                'error_mensaje' => $job['error_mensaje'],
                'resultado' => $job['resultado_json'],
                'trace_path' => $job['trace_path'],
                'log_path' => $job['log_path'],
                'pipeline_log_path' => $job['pipeline_log_path'] ?? null,
                'tiempo_total_ms' => $job['tiempo_total_ms'],
            ],
            200
        );
    }

    respondJson(
        [
            'ok' => false,
            'message' => 'Ruta/metodo no soportado. Usa POST /api/cv/procesar o GET /api/cv/procesar/{job_id}/estado|resultado',
        ],
        404
    );
} catch (Throwable $e) {
    respondJson(
        [
            'ok' => false,
            'message' => 'Error en API de procesamiento CV: ' . $e->getMessage(),
        ],
        500
    );
}

function resolveRoute(): array
{
    $queryAction = (string)($_GET['accion'] ?? '');
    $queryJobId = (string)($_GET['job_id'] ?? '');

    if ($queryAction !== '') {
        if ($queryAction === 'estado') {
            return ['action' => 'estado', 'job_id' => $queryJobId];
        }

        if ($queryAction === 'resultado') {
            return ['action' => 'resultado', 'job_id' => $queryJobId];
        }

        if ($queryAction === 'create') {
            return ['action' => 'create', 'job_id' => null];
        }
    }

    $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');

    if (preg_match('~/api/cv/procesar/?$~', $path) === 1) {
        return ['action' => 'create', 'job_id' => null];
    }

    if (preg_match('~/api/cv/procesar/([A-Za-z0-9._-]+)/estado/?$~', $path, $m) === 1) {
        return ['action' => 'estado', 'job_id' => $m[1]];
    }

    if (preg_match('~/api/cv/procesar/([A-Za-z0-9._-]+)/resultado/?$~', $path, $m) === 1) {
        return ['action' => 'resultado', 'job_id' => $m[1]];
    }

    return ['action' => 'unknown', 'job_id' => null];
}

function loadJobOr404(ProcessingJobQueue $queue, string $jobId): array
{
    if ($jobId === '' || preg_match('/^[A-Za-z0-9._-]+$/', $jobId) !== 1) {
        respondJson(['ok' => false, 'message' => 'job_id invalido.'], 400);
    }

    $job = $queue->getJob($jobId);
    if (!is_array($job)) {
        respondJson(['ok' => false, 'message' => 'Job no encontrado.'], 404);
    }

    return $job;
}

function respondJson(array $payload, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"ok":false,"message":"Error serializando JSON."}';
    }

    echo $json;
    exit;
}
