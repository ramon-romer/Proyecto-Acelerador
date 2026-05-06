<?php

require_once __DIR__ . '/../src/Evaluation/Mcp/McpClientException.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpHttpResponse.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpClientInterface.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpHttpClientInternalV1.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpInternalHttpV1Contract.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpInternalHttpV1NormalizationResult.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpInternalHttpV1Normalizer.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpInternalHttpV1ContractInspector.php';

$inspectContractMode = in_array('--inspect-contract', $argv, true);

if ($inspectContractMode) {
    runContractInspection();
}

$result = [
    'ok' => true,
    'provider' => 'internal_http_v1',
    'checks' => [],
    'detected_routes' => [
        'POST /extract-pdf',
        'POST /extract-data',
        'GET /jobs/{id}',
    ],
];

try {
    $client = new McpHttpClientInternalV1();

    registerCheck(
        $result,
        'default_base_url',
        $client->getBaseUrl() === McpHttpClientInternalV1::DEFAULT_BASE_URL,
        ['value' => $client->getBaseUrl()]
    );
    registerCheck(
        $result,
        'default_timeout_ms',
        $client->getTimeoutMs() === McpHttpClientInternalV1::DEFAULT_TIMEOUT_MS,
        ['value' => $client->getTimeoutMs()]
    );

    $diagnostic = $client->diagnoseAvailability();
    $result['diagnostic'] = $diagnostic;

    if (empty($diagnostic['ok'])) {
        $result['ok'] = false;
        $result['reason'] = (string)($diagnostic['reason'] ?? 'mcp_unavailable');
        $result['message'] = 'MCP internal_http_v1 no disponible o no alcanzable en modo diagnostico.';
        outputResult($result, 1);
    }

    $probeResponse = $client->getJob('diagnostic_probe_job');
    $statusCode = $probeResponse->getStatusCode();
    $json = $probeResponse->getJson();
    $acceptedStatus = in_array($statusCode, [200, 404], true);
    registerCheck(
        $result,
        'safe_probe_get_job_non_destructive',
        $acceptedStatus && is_array($json),
        [
            'http_status' => $statusCode,
            'response_keys' => is_array($json) ? array_keys($json) : [],
        ]
    );

    foreach ($result['checks'] as $check) {
        if (empty($check['ok'])) {
            $result['ok'] = false;
            break;
        }
    }

    outputResult($result, $result['ok'] ? 0 : 1);
} catch (McpClientException $e) {
    $result['ok'] = false;
    $result['reason'] = $e->getReason();
    $result['message'] = $e->getMessage();
    $result['error_context'] = $e->getContext();
    outputResult($result, 1);
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['reason'] = 'unexpected_error';
    $result['message'] = $e->getMessage();
    outputResult($result, 1);
}

function registerCheck(array &$result, string $name, bool $ok, array $context = []): void
{
    $row = [
        'name' => $name,
        'ok' => $ok,
    ];
    if (!empty($context)) {
        $row['context'] = $context;
    }

    $result['checks'][] = $row;
}

function outputResult(array $result, int $exitCode): void
{
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = '{"ok":false,"reason":"json_encode_error","message":"No se pudo serializar salida."}';
        $exitCode = 1;
    }

    echo $json . PHP_EOL;
    exit($exitCode);
}

function runContractInspection(): void
{
    $normalizer = new McpInternalHttpV1Normalizer();

    $sampleEnvelope = [
        'ok' => true,
        'queued' => false,
        'diagnostico' => ['recommended_mode' => 'sync'],
        'faltantes' => ['numero'],
        'resultado' => [
            'tipo_documento' => 'FACTURA',
            'numero' => null,
            'fecha' => '2026-03-20',
            'total_bi' => '100,00 EUR',
            'iva' => '21,00 EUR',
            'total_a_pagar' => '121,00 EUR',
            'texto_preview' => '...',
        ],
    ];

    $sampleDoneJob = [
        'ok' => true,
        'job_id' => 'job_demo',
        'status' => 'done',
        'created_at' => '2026-03-23T11:08:34+01:00',
        'updated_at' => '2026-03-23T11:08:45+01:00',
        'diagnostico' => ['recommended_mode' => 'async'],
        'resultado' => [
            'tipo_documento' => null,
            'numero' => null,
            'fecha' => null,
            'total_bi' => null,
            'iva' => null,
            'total_a_pagar' => null,
            'texto_preview' => '...',
        ],
    ];

    $inspection = [
        'ok' => true,
        'provider' => 'internal_http_v1',
        'contract' => McpInternalHttpV1ContractInspector::inspect(),
        'normalization_samples' => [
            'extract_data_sync_envelope' => $normalizer->normalize($sampleEnvelope)->toArray(),
            'job_done_envelope' => $normalizer->normalize($sampleDoneJob)->toArray(),
        ],
    ];

    outputResult($inspection, 0);
}
