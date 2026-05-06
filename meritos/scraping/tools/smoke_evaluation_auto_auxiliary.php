<?php

require_once __DIR__ . '/../src/Evaluation/EvaluationProviderInterface.php';
require_once __DIR__ . '/../src/Evaluation/EvaluationExecutionMode.php';
require_once __DIR__ . '/../src/Evaluation/EvaluationModeResolver.php';
require_once __DIR__ . '/../src/Evaluation/EvaluationOrchestrator.php';
require_once __DIR__ . '/../src/Evaluation/Mcp/McpEvaluationProviderException.php';

class SpyProviderAuto implements EvaluationProviderInterface
{
    public $calls = 0;
    public $payload = [];
    private $providerId;

    public function __construct(string $providerId, array $payload = [])
    {
        $this->providerId = $providerId;
        $this->payload = $payload;
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

class ThrowingProviderAuto implements EvaluationProviderInterface
{
    public $calls = 0;
    private $providerId;
    private $reason;

    public function __construct(string $providerId, string $reason = 'provider_error')
    {
        $this->providerId = $providerId;
        $this->reason = $reason;
    }

    public function evaluate(string $pdfPath, ?callable $phaseCallback = null): array
    {
        $this->calls++;
        throw new RuntimeException('failure reason=' . $this->reason);
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }
}

class ThrowingMcpAuxProvider implements EvaluationProviderInterface
{
    public $calls = 0;
    private $reason;

    public function __construct(string $reason = 'mcp_timeout')
    {
        $this->reason = $reason;
    }

    public function evaluate(string $pdfPath, ?callable $phaseCallback = null): array
    {
        $this->calls++;
        throw new McpEvaluationProviderException(
            $this->reason,
            'mcp_aux_failure',
            [
                'can_produce_runtime_result' => false,
                'compatibility_reasons' => [$this->reason],
                'response_kind' => 'legacy_extract_payload',
            ]
        );
    }

    public function getProviderId(): string
    {
        return 'mcp_aux_throwing';
    }
}

function registerCheck(array &$result, string $name, bool $ok): void
{
    $result['checks'][] = [
        'name' => $name,
        'ok' => $ok,
    ];
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

$result = [
    'ok' => true,
    'checks' => [],
];

$previousMode = getenv(EvaluationModeResolver::ENV_EXECUTION_MODE);

try {
    $localPayload = [
        'ok' => true,
        'score_total' => 77,
        'origen' => 'local_aneca',
        'bloques' => ['A' => 10, 'B' => 20],
    ];

    // 1) auto devuelve resultado local intacto y llama MCP auxiliar.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=auto');
    $localSpy = new SpyProviderAuto('local_spy', $localPayload);
    $mcpSpy = new SpyProviderAuto('mcp_spy', [
        'ok' => true,
        'score_total' => 999,
        'origen' => 'mcp',
        'campo_extra_mcp' => 'aux',
    ]);
    $orchestratorAuto = new EvaluationOrchestrator($localSpy, null, $mcpSpy);
    $autoOutput = $orchestratorAuto->evaluate('fake.pdf');
    $autoTrace = $orchestratorAuto->getLastTrace();

    registerCheck(
        $result,
        'auto_devuelve_local_intacto_e_intenta_mcp',
        $localSpy->calls === 1
            && $mcpSpy->calls === 1
            && $autoOutput === $localPayload
            && (string)($autoTrace['modo_ejecucion'] ?? '') === 'local_with_mcp_auxiliary'
            && (bool)($autoTrace['local_ok'] ?? false) === true
            && (bool)($autoTrace['mcp_intentado'] ?? false) === true
            && (bool)($autoTrace['mcp_disponible'] ?? false) === true
            && (string)($autoTrace['mcp_reason'] ?? '') === 'mcp_auxiliary_ok'
    );

    registerCheck(
        $result,
        'auto_no_expone_campos_publicos_nuevos',
        array_keys($autoOutput) === array_keys($localPayload)
            && !array_key_exists('mcp_reason', $autoOutput)
            && !array_key_exists('mcp_disponible', $autoOutput)
            && !array_key_exists('mcp_warnings', $autoOutput)
    );

    registerCheck(
        $result,
        'mcp_no_evalua_ni_decide_puntuacion_ni_rellena_campos_finales',
        (int)$autoOutput['score_total'] === 77
            && (string)$autoOutput['origen'] === 'local_aneca'
            && !array_key_exists('campo_extra_mcp', $autoOutput)
    );

    // 2) si MCP falla, auto continua con local.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=auto');
    $localSpyMcpFail = new SpyProviderAuto('local_spy', $localPayload);
    $mcpFailProvider = new ThrowingMcpAuxProvider('mcp_timeout');
    $orchestratorAutoMcpFail = new EvaluationOrchestrator($localSpyMcpFail, null, $mcpFailProvider);
    $outputMcpFail = $orchestratorAutoMcpFail->evaluate('fake.pdf');
    $traceMcpFail = $orchestratorAutoMcpFail->getLastTrace();
    registerCheck(
        $result,
        'auto_no_falla_si_mcp_falla',
        $localSpyMcpFail->calls === 1
            && $mcpFailProvider->calls === 1
            && $outputMcpFail === $localPayload
            && (bool)($traceMcpFail['mcp_intentado'] ?? false) === true
            && (bool)($traceMcpFail['mcp_disponible'] ?? true) === false
            && (bool)($traceMcpFail['fallback_activado'] ?? false) === true
            && (string)($traceMcpFail['motivo_fallback'] ?? '') === 'mcp_timeout'
            && (string)($traceMcpFail['mcp_reason'] ?? '') === 'mcp_timeout'
    );

    // 3) si local falla, auto falla y no intenta MCP.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=auto');
    $localFail = new ThrowingProviderAuto('local_throw', 'local_failure');
    $mcpNever = new SpyProviderAuto('mcp_spy', ['ok' => true]);
    $orchestratorAutoLocalFail = new EvaluationOrchestrator($localFail, null, $mcpNever);
    $autoLocalFails = false;
    try {
        $orchestratorAutoLocalFail->evaluate('fake.pdf');
    } catch (RuntimeException $e) {
        $autoLocalFails = strpos($e->getMessage(), 'local_failure') !== false;
    }
    registerCheck(
        $result,
        'auto_falla_si_local_falla',
        $autoLocalFails && $localFail->calls === 1 && $mcpNever->calls === 0
    );

    // 4) local_only no llama MCP.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=local_only');
    $localOnlySpy = new SpyProviderAuto('local_spy', $localPayload);
    $mcpLocalOnlySpy = new SpyProviderAuto('mcp_spy', ['ok' => true]);
    $orchestratorLocalOnly = new EvaluationOrchestrator($localOnlySpy, null, $mcpLocalOnlySpy);
    $localOnlyOutput = $orchestratorLocalOnly->evaluate('fake.pdf');
    registerCheck(
        $result,
        'local_only_no_llama_mcp',
        $localOnlySpy->calls === 1 && $mcpLocalOnlySpy->calls === 0 && $localOnlyOutput === $localPayload
    );

    // 5) mcp sigue diagnostico y no hace fallback local.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=mcp');
    $localNotUsed = new SpyProviderAuto('local_spy', $localPayload);
    $mcpOnlyFail = new ThrowingProviderAuto('mcp_throw', 'mcp_diagnostic_failure');
    $orchestratorMcpOnly = new EvaluationOrchestrator($localNotUsed, null, $mcpOnlyFail);
    $mcpOnlyFails = false;
    try {
        $orchestratorMcpOnly->evaluate('fake.pdf');
    } catch (RuntimeException $e) {
        $mcpOnlyFails = strpos($e->getMessage(), 'mcp_diagnostic_failure') !== false;
    }
    registerCheck(
        $result,
        'mcp_diagnostico_sin_fallback_local',
        $mcpOnlyFails && $localNotUsed->calls === 0 && $mcpOnlyFail->calls === 1
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
