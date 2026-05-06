<?php

require_once __DIR__ . '/../src/Evaluation/EvaluationProviderInterface.php';
require_once __DIR__ . '/../src/Evaluation/EvaluationExecutionMode.php';
require_once __DIR__ . '/../src/Evaluation/EvaluationModeResolver.php';
require_once __DIR__ . '/../src/Evaluation/EvaluationOrchestrator.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpClientInterface.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpClientException.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpHttpResponse.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpInternalHttpV1Normalizer.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpEvaluationProviderException.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpEvaluationProvider.php';

class SpyEvalProviderForOrchestrator implements EvaluationProviderInterface
{
    public $calls = 0;
    public $payload = ['resultado' => 'spy'];
    private $providerId;

    public function __construct(string $providerId)
    {
        $this->providerId = $providerId;
    }

    public function evaluate(string $pdfPath, ?callable $phaseCallback = null): array
    {
        $this->calls++;
        return $this->payload;
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }
}

class FakeMcpClientUnavailable implements McpClientInterface
{
    public $calls = 0;

    public function getBaseUrl(): string
    {
        return 'http://127.0.0.1:5000';
    }

    public function getTimeoutMs(): int
    {
        return 5000;
    }

    public function diagnoseAvailability(): array
    {
        return ['ok' => false, 'reason' => 'mcp_unavailable'];
    }

    public function getJob(string $jobId): McpHttpResponse
    {
        throw new McpClientException('mcp_unavailable', 'No disponible');
    }

    public function extractData(array $payload): McpHttpResponse
    {
        $this->calls++;
        throw new McpClientException('mcp_unavailable', 'No disponible');
    }
}

class FakeMcpClientLegacyPayload implements McpClientInterface
{
    public $calls = 0;

    public function getBaseUrl(): string
    {
        return 'http://127.0.0.1:5000';
    }

    public function getTimeoutMs(): int
    {
        return 5000;
    }

    public function diagnoseAvailability(): array
    {
        return ['ok' => true];
    }

    public function getJob(string $jobId): McpHttpResponse
    {
        return new McpHttpResponse(404, [], '{"error":"no usado"}', ['error' => 'no usado']);
    }

    public function extractData(array $payload): McpHttpResponse
    {
        $this->calls++;
        $json = [
            'ok' => true,
            'queued' => false,
            'resultado' => [
                'tipo_documento' => 'FACTURA',
                'numero' => 'INV-1',
                'fecha' => '2026-01-01',
                'total_bi' => '100,00 EUR',
                'iva' => '21,00 EUR',
                'total_a_pagar' => '121,00 EUR',
                'texto_preview' => 'demo',
            ],
        ];

        return new McpHttpResponse(200, [], json_encode($json), $json);
    }
}

function registerCheck(array &$result, string $name, bool $ok): void
{
    $result['checks'][] = ['name' => $name, 'ok' => $ok];
}

function outputResult(array $result, int $exitCode): void
{
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        $json = '{"ok":false,"reason":"json_encode_error"}';
        $exitCode = 1;
    }

    echo $json . PHP_EOL;
    exit($exitCode);
}

$result = ['ok' => true, 'checks' => []];
$previousMode = getenv(EvaluationModeResolver::ENV_EXECUTION_MODE);

try {
    // 1) local_only no llama MCP.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=local_only');
    $localSpy = new SpyEvalProviderForOrchestrator('local_spy');
    $mcpSpy = new SpyEvalProviderForOrchestrator('mcp_spy');
    $orchestratorLocal = new EvaluationOrchestrator($localSpy, null, $mcpSpy);
    $orchestratorLocal->evaluate('fake.pdf');
    registerCheck(
        $result,
        'local_only_no_llama_mcp',
        $localSpy->calls === 1 && $mcpSpy->calls === 0
    );

    // 2) auto implementado: local obligatorio + MCP auxiliar.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=auto');
    $autoLocalSpy = new SpyEvalProviderForOrchestrator('local_spy');
    $autoMcpSpy = new SpyEvalProviderForOrchestrator('mcp_spy');
    $autoLocalSpy->payload = ['resultado' => 'local_final', 'score' => 10];
    $autoMcpSpy->payload = ['resultado' => 'mcp_aux', 'score' => 999];
    $orchestratorAuto = new EvaluationOrchestrator($autoLocalSpy, null, $autoMcpSpy);
    $autoOutput = $orchestratorAuto->evaluate('fake.pdf');
    $autoTrace = $orchestratorAuto->getLastTrace();
    registerCheck(
        $result,
        'auto_local_obligatorio_mcp_auxiliar',
        $autoLocalSpy->calls === 1
            && $autoMcpSpy->calls === 1
            && $autoOutput === $autoLocalSpy->payload
            && (string)($autoTrace['modo_ejecucion'] ?? '') === 'local_with_mcp_auxiliary'
            && (string)($autoTrace['mcp_reason'] ?? '') === 'mcp_auxiliary_ok'
    );

    // 3) mcp con MCP no levantado falla controlado y sin fallback local.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=mcp');
    $localNoFallbackSpy = new SpyEvalProviderForOrchestrator('local_spy');
    $clientUnavailable = new FakeMcpClientUnavailable();
    $mcpProviderUnavailable = new McpEvaluationProvider($clientUnavailable, new McpInternalHttpV1Normalizer());
    $orchestratorMcpUnavailable = new EvaluationOrchestrator($localNoFallbackSpy, null, $mcpProviderUnavailable);

    $unavailableControlled = false;
    try {
        $orchestratorMcpUnavailable->evaluate('fake.pdf');
    } catch (McpEvaluationProviderException $e) {
        $unavailableControlled = $e->getReason() === 'mcp_unavailable';
    }
    registerCheck(
        $result,
        'mcp_unavailable_error_controlado_sin_fallback',
        $unavailableControlled && $clientUnavailable->calls === 1 && $localNoFallbackSpy->calls === 0
    );

    // 4) payload legacy de 7 claves no produce runtime compatible ni inventa 4 claves.
    $clientLegacy = new FakeMcpClientLegacyPayload();
    $mcpProviderLegacy = new McpEvaluationProvider($clientLegacy, new McpInternalHttpV1Normalizer());
    $legacyControlled = false;
    $legacyMissingRuntimeKeys = false;
    try {
        $mcpProviderLegacy->evaluate('fake.pdf');
    } catch (McpEvaluationProviderException $e) {
        $legacyControlled = in_array($e->getReason(), ['mcp_contract_legacy', 'mcp_missing_required_fields'], true);
        $ctx = $e->getContext();
        $missing = is_array($ctx['missing_runtime_required_fields'] ?? null)
            ? $ctx['missing_runtime_required_fields']
            : [];
        $legacyMissingRuntimeKeys = in_array('archivo_pdf', $missing, true)
            && in_array('paginas_detectadas', $missing, true)
            && in_array('txt_generado', $missing, true)
            && in_array('json_generado', $missing, true);
    }
    registerCheck(
        $result,
        'legacy_7_claves_no_produce_runtime_ni_inventa_campos',
        $legacyControlled && $legacyMissingRuntimeKeys && $clientLegacy->calls === 1
    );

    foreach ($result['checks'] as $check) {
        if (empty($check['ok'])) {
            $result['ok'] = false;
            break;
        }
    }

    outputResult($result, $result['ok'] ? 0 : 1);
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['reason'] = 'unexpected_error';
    $result['message'] = $e->getMessage();
    outputResult($result, 1);
} finally {
    if ($previousMode === false) {
        putenv(EvaluationModeResolver::ENV_EXECUTION_MODE);
    } else {
        putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=' . $previousMode);
    }
}
