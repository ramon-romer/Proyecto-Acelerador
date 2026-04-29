<?php

// Contrato de este endpoint: tecnico local del modulo meritos/scraping.
// No define el contrato canonico oficial de dominio (ANECA).

require_once __DIR__ . '/../src/CvProcessingJobService.php';
require_once __DIR__ . '/../src/ProcessingJobWorker.php';
require_once __DIR__ . '/../src/PreferredResultResolver.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    responderError('Metodo no permitido. Usa POST.', 405);
}

if (!isset($_FILES['pdf']) || !is_array($_FILES['pdf'])) {
    responderError('No se recibio el archivo PDF en el campo pdf.', 400);
}

$forceQueue = isset($_POST['force_queue']) && (string)$_POST['force_queue'] === '1';
$alwaysQueue = isset($_POST['always_queue']) && (string)$_POST['always_queue'] === '1';
$includeAnecaPayload = shouldIncludeAnecaCanonicalPayload();
$preferAnecaCanonical = shouldPreferAnecaCanonical();

try {
    $queue = new ProcessingJobQueue();
    $service = new CvProcessingJobService($queue);
    $worker = new ProcessingJobWorker($queue);

    $enqueued = $service->enqueueFromUpload($_FILES['pdf'], $alwaysQueue || $forceQueue);
    $job = $enqueued['job'];
    $queuedOnly = (bool)$enqueued['queued_only'] || $forceQueue;

    if ($queuedOnly) {
        $payload = [
            'ok' => true,
            'message' => 'CV encolado para procesamiento asincrono.',
            'job_id' => $job['id'],
            'estado' => $job['estado'],
                'progreso_porcentaje' => $job['progreso_porcentaje'],
                'fase_actual' => $job['fase_actual'],
                'es_pesado' => $enqueued['is_heavy'],
                'umbral_pesado_bytes' => $enqueued['heavy_threshold_bytes'],
                'aneca_canonical_ready' => (bool)($job['aneca_canonical_ready'] ?? false),
                'aneca_canonical_validation_status' => $job['aneca_canonical_validation_status'] ?? null,
                'endpoints' => [
                    'estado' => '/api/cv/procesar/' . $job['id'] . '/estado',
                    'resultado' => '/api/cv/procesar/' . $job['id'] . '/resultado',
                ],
            ];

        responder($payload, 202);
    }

    $jobProcesado = $worker->processJobById((string)$job['id']);
    $estadoFinal = (string)($jobProcesado['estado'] ?? '');

    if ($estadoFinal === 'error') {
        responder(
            [
                'ok' => false,
                'message' => 'El procesamiento del CV finalizo con error de validacion.',
                'job_id' => $jobProcesado['id'],
                'estado' => $estadoFinal,
                'error_mensaje' => sanitizePublicMessage($jobProcesado['error_mensaje'] ?? null),
                'endpoints' => [
                    'estado' => '/api/cv/procesar/' . $jobProcesado['id'] . '/estado',
                    'resultado' => '/api/cv/procesar/' . $jobProcesado['id'] . '/resultado',
                ],
            ],
            422
        );
    }

    $legacyPayload = is_array($jobProcesado['resultado_json'] ?? null) ? $jobProcesado['resultado_json'] : [];
    $anecaPayload = $includeAnecaPayload ? loadAnecaCanonicalPayloadFromJob($jobProcesado) : null;
    $preferred = PreferredResultResolver::resolvePreferredResult(
        $legacyPayload,
        $preferAnecaCanonical,
        !empty($jobProcesado['aneca_canonical_ready']),
        $anecaPayload
    );

    $payload = [
        'ok' => true,
        'message' => 'CV procesado en linea (archivo no pesado).',
        'job_id' => $jobProcesado['id'],
        'estado' => $jobProcesado['estado'],
        'progreso_porcentaje' => $jobProcesado['progreso_porcentaje'],
        'fase_actual' => $jobProcesado['fase_actual'],
        'resultado' => $legacyPayload,
        'aneca_canonical_ready' => (bool)($jobProcesado['aneca_canonical_ready'] ?? false),
        'aneca_canonical_validation_status' => $jobProcesado['aneca_canonical_validation_status'] ?? null,
        'resultado_preferente_formato' => $preferred['resultado_preferente_formato'],
        'resultado_preferente' => $preferred['resultado_preferente'],
        'tiempo_total_ms' => $jobProcesado['tiempo_total_ms'] ?? null,
        'endpoints' => [
            'estado' => '/api/cv/procesar/' . $jobProcesado['id'] . '/estado',
            'resultado' => '/api/cv/procesar/' . $jobProcesado['id'] . '/resultado',
        ],
    ];

    if ($includeAnecaPayload) {
        $payload['resultado_aneca_canonico'] = $anecaPayload;
    }

    responder($payload, 200);
} catch (Throwable $e) {
    responderError('Error interno al subir/procesar CV.', 500);
}

function responder(array $payload, int $statusCode): void
{
    if (quiereJson()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');

    echo '<h2>Resultado</h2>';
    echo '<pre>';
    echo htmlspecialchars((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    echo '</pre>';
    exit;
}

function responderError(string $message, int $statusCode): void
{
    responder(
        [
            'ok' => false,
            'message' => $message,
        ],
        $statusCode
    );
}

function quiereJson(): bool
{
    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    $format = (string)($_GET['format'] ?? '');
    $xhr = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

    if (stripos($accept, 'application/json') !== false) {
        return true;
    }

    if (strtolower($format) === 'json') {
        return true;
    }

    if (strtolower($xhr) === 'xmlhttprequest') {
        return true;
    }

    return false;
}

function shouldIncludeAnecaCanonicalPayload(): bool
{
    if ((string)($_POST['include_aneca'] ?? '') === '1') {
        return true;
    }

    if ((string)($_GET['include_aneca'] ?? '') === '1') {
        return true;
    }

    return shouldPreferAnecaCanonical();
}

function shouldPreferAnecaCanonical(): bool
{
    $requestValue = null;
    if (array_key_exists('prefer_aneca', $_POST)) {
        $requestValue = (string)$_POST['prefer_aneca'];
    } elseif (array_key_exists('prefer_aneca', $_GET)) {
        $requestValue = (string)$_GET['prefer_aneca'];
    }

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
