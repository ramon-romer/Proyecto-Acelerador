<?php

require_once __DIR__ . '/../src/ProcessingJobQueue.php';
require_once __DIR__ . '/../src/ProcessingJobWorker.php';

$repoRoot = dirname(__DIR__, 3);
$samplePdf = $repoRoot . '/vendor/smalot/pdfparser/samples/Document1_pdfcreator.pdf';
$pdfTargetDir = __DIR__ . '/../pdfs';

$result = [
    'ok' => true,
    'checks' => [],
    'jobs' => [],
];

try {
    if (!is_dir($pdfTargetDir) && !mkdir($pdfTargetDir, 0777, true) && !is_dir($pdfTargetDir)) {
        throw new Exception('No se pudo crear directorio de PDFs para smoke test.');
    }

    if (!is_file($samplePdf)) {
        throw new Exception('No se encontro PDF de muestra para smoke test: ' . $samplePdf);
    }

    $queue = new ProcessingJobQueue();
    $worker = new ProcessingJobWorker($queue);

    $runId = 'smoke_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));

    $pdfOk = $pdfTargetDir . '/' . $runId . '_ok.pdf';
    if (!copy($samplePdf, $pdfOk)) {
        throw new Exception('No se pudo copiar PDF de muestra para escenario OK.');
    }

    $jobOk = $queue->createJobForPdfPath($pdfOk, ['smoke_test' => true, 'escenario' => 'ok']);
    $result['checks'][] = [
        'name' => 'crear_job',
        'ok' => (string)($jobOk['estado'] ?? '') === 'pendiente',
    ];

    $estadoInicial = $queue->getJob((string)$jobOk['id']);
    $result['checks'][] = [
        'name' => 'consultar_estado_inicial',
        'ok' => is_array($estadoInicial) && (string)($estadoInicial['estado'] ?? '') === 'pendiente',
    ];

    $jobOkFinal = $worker->processJobById((string)$jobOk['id']);
    $estadoOk = (string)($jobOkFinal['estado'] ?? '');
    $result['checks'][] = [
        'name' => 'procesar_job_worker',
        'ok' => in_array($estadoOk, ['completado', 'error_parcial'], true),
    ];

    $resultadoOk = $queue->getJob((string)$jobOk['id']);
    $result['checks'][] = [
        'name' => 'consultar_resultado',
        'ok' => is_array($resultadoOk)
            && in_array((string)($resultadoOk['estado'] ?? ''), ['completado', 'error_parcial'], true)
            && is_array($resultadoOk['resultado_json'] ?? null),
    ];

    $result['jobs'][] = [
        'id' => $jobOk['id'],
        'estado_final' => $estadoOk,
    ];

    $pdfError = $pdfTargetDir . '/' . $runId . '_error.pdf';
    if (!copy($samplePdf, $pdfError)) {
        throw new Exception('No se pudo copiar PDF de muestra para escenario error.');
    }

    $jobError = $queue->createJobForPdfPath($pdfError, ['smoke_test' => true, 'escenario' => 'error']);

    // Simula error: se elimina el PDF antes de procesar.
    @unlink($pdfError);

    $jobErrorFinal = $worker->processJobById((string)$jobError['id']);
    $result['checks'][] = [
        'name' => 'simular_error_controlado',
        'ok' => in_array((string)($jobErrorFinal['estado'] ?? ''), ['error', 'error_parcial'], true),
    ];

    $result['jobs'][] = [
        'id' => $jobError['id'],
        'estado_final' => $jobErrorFinal['estado'] ?? null,
        'error_mensaje' => $jobErrorFinal['error_mensaje'] ?? null,
    ];

    foreach ($result['checks'] as $check) {
        if (empty($check['ok'])) {
            $result['ok'] = false;
            break;
        }
    }

    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new Exception('No se pudo serializar salida del smoke test.');
    }

    echo $json . PHP_EOL;
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['message'] = $e->getMessage();

    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"ok":false,"message":"Error fatal en smoke test."}';
    }

    echo $json . PHP_EOL;
    exit(1);
}
