<?php

// Contrato de este endpoint: tecnico local del modulo meritos/scraping.
// No define el contrato canonico oficial de dominio (ANECA).

require_once __DIR__ . '/../src/CvProcessingJobService.php';
require_once __DIR__ . '/../src/ProcessingJobQueue.php';
require_once __DIR__ . '/../src/PreferredResultResolver.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$route = resolveRoute();
$includeAnecaPayload = shouldIncludeAnecaCanonicalPayload();
$preferAnecaCanonical = shouldPreferAnecaCanonical();

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
                'aneca_canonical_ready' => (bool)($job['aneca_canonical_ready'] ?? false),
                'aneca_canonical_validation_status' => $job['aneca_canonical_validation_status'] ?? null,
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
                'error_mensaje' => sanitizePublicMessage($job['error_mensaje'] ?? null),
                'fecha_creacion' => $job['fecha_creacion'],
                'fecha_inicio' => $job['fecha_inicio'],
                'fecha_fin' => $job['fecha_fin'],
                'tiempo_total_ms' => $job['tiempo_total_ms'],
                'aneca_canonical_ready' => (bool)($job['aneca_canonical_ready'] ?? false),
                'aneca_canonical_validation_status' => $job['aneca_canonical_validation_status'] ?? null,
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
                    'error_mensaje' => sanitizePublicMessage($job['error_mensaje'] ?? null),
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

        $legacyPayload = is_array($job['resultado_json'] ?? null) ? $job['resultado_json'] : [];
        $anecaPayload = $includeAnecaPayload ? loadAnecaCanonicalPayloadFromJob($job) : null;
        $preferred = PreferredResultResolver::resolvePreferredResult(
            $legacyPayload,
            $preferAnecaCanonical,
            !empty($job['aneca_canonical_ready']),
            $anecaPayload
        );

        $response = [
            'ok' => true,
            'job_id' => $job['id'],
            'estado' => $estado,
            'error_parcial' => (bool)($job['error_parcial'] ?? false),
            'error_mensaje' => sanitizePublicMessage($job['error_mensaje'] ?? null),
            'resultado' => $legacyPayload,
            'aneca_canonical_ready' => (bool)($job['aneca_canonical_ready'] ?? false),
            'aneca_canonical_validation_status' => $job['aneca_canonical_validation_status'] ?? null,
            'resultado_preferente_formato' => $preferred['resultado_preferente_formato'],
            'resultado_preferente' => $preferred['resultado_preferente'],
            'tiempo_total_ms' => $job['tiempo_total_ms'],
        ];

        if ($includeAnecaPayload) {
            $response['resultado_aneca_canonico'] = $anecaPayload;
        }

        respondJson($response, 200);
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
            'message' => 'Error interno en API de procesamiento CV.',
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

function shouldIncludeAnecaCanonicalPayload(): bool
{
    $include = (string)($_GET['include_aneca'] ?? '');
    if ($include === '1') {
        return true;
    }

    return shouldPreferAnecaCanonical();
}

function shouldPreferAnecaCanonical(): bool
{
    $requestValue = array_key_exists('prefer_aneca', $_GET)
        ? (string)$_GET['prefer_aneca']
        : null;

    return PreferredResultResolver::shouldPreferAneca($requestValue);
}

function sanitizePublicMessage(mixed $message): ?string
{
    if (!is_string($message)) {
        return null;
    }

    $message = preg_replace('/[A-Za-z]:[\\\\\\/][^\\s<>"\']+/', '[ruta_interna]', $message);
    $message = preg_replace('~/(?:var|home|srv|opt|tmp|mnt|Users|www|app)(?:/[^\\s<>"\']*)?~', '[ruta_interna]', (string)$message);

    return $message === '' ? null : $message;
}

function loadAnecaCanonicalPayloadFromJob(array $job): ?array
{
    $path = isset($job['aneca_canonical_path']) && is_string($job['aneca_canonical_path'])
        ? trim((string)$job['aneca_canonical_path'])
        : '';
    if ($path === '' || !is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}
