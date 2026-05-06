<?php

require_once __DIR__ . '/../src/Evaluation/EvaluationProviderInterface.php';
require_once __DIR__ . '/../src/Evaluation/LocalEvaluationProvider.php';
require_once __DIR__ . '/../src/Evaluation/EvaluationOrchestrator.php';

class SpyEvaluationProvider implements EvaluationProviderInterface
{
    public $calls = 0;
    public $payload = ['resultado' => 'spy_local'];

    public function evaluate(string $pdfPath, ?callable $phaseCallback = null): array
    {
        $this->calls++;
        return $this->payload;
    }

    public function getProviderId(): string
    {
        return 'spy_local_provider';
    }
}

class ThrowingEvaluationProvider implements EvaluationProviderInterface
{
    public $calls = 0;
    public $reason = 'mcp_unavailable';

    public function evaluate(string $pdfPath, ?callable $phaseCallback = null): array
    {
        $this->calls++;
        throw new RuntimeException('provider_error reason=' . $this->reason);
    }

    public function getProviderId(): string
    {
        return 'throwing_provider';
    }
}

class FakePipeline extends Pipeline
{
    public $calls = 0;
    public $lastPdfPath = null;
    public $payload = ['resultado' => 'fake_pipeline'];

    public function procesar(string $pdfPath, ?callable $phaseCallback = null): array
    {
        $this->calls++;
        $this->lastPdfPath = $pdfPath;

        if ($phaseCallback !== null) {
            $phaseCallback('procesando_pdf', 5, 'fake_pipeline', ['source' => 'fake']);
        }

        return $this->payload;
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
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"ok":false,"message":"Error serializando smoke."}';
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
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE);

    // 1) Default cuando no existe variable: local_only.
    $spyDefault = new SpyEvaluationProvider();
    $orchestratorDefault = new EvaluationOrchestrator($spyDefault);
    $outputDefault = $orchestratorDefault->evaluate('fake.pdf');
    $traceDefault = $orchestratorDefault->getLastTrace();

    registerCheck(
        $result,
        'default_sin_env_es_local_only',
        $spyDefault->calls === 1
            && $outputDefault === $spyDefault->payload
            && (string)($traceDefault['modo_solicitado'] ?? '') === EvaluationExecutionMode::LOCAL_ONLY
            && (string)($traceDefault['modo_ejecucion'] ?? '') === 'local_pipeline'
            && (string)($traceDefault['provider_usado'] ?? '') === $spyDefault->getProviderId()
    );

    // 2) local_only explicito.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=local_only');
    $spyLocalOnly = new SpyEvaluationProvider();
    $orchestratorLocalOnly = new EvaluationOrchestrator($spyLocalOnly);
    $outputLocalOnly = $orchestratorLocalOnly->evaluate('fake.pdf');
    registerCheck(
        $result,
        'env_local_only_operativo',
        $spyLocalOnly->calls === 1 && $outputLocalOnly === $spyLocalOnly->payload
    );

    // 3) mcp operativo y sin fallback local.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=mcp');
    $spyLocalMcpOnly = new SpyEvaluationProvider();
    $spyMcpOnly = new SpyEvaluationProvider();
    $spyMcpOnly->payload = ['resultado' => 'spy_mcp'];
    $orchestratorMcpOnly = new EvaluationOrchestrator($spyLocalMcpOnly, null, $spyMcpOnly);
    $outputMcpOnly = $orchestratorMcpOnly->evaluate('fake.pdf');
    $traceMcpOnly = $orchestratorMcpOnly->getLastTrace();
    registerCheck(
        $result,
        'env_mcp_operativo_experimental',
        $spyLocalMcpOnly->calls === 0
            && $spyMcpOnly->calls === 1
            && $outputMcpOnly === $spyMcpOnly->payload
            && (string)($traceMcpOnly['modo_ejecucion'] ?? '') === 'mcp_only_internal_http_v1'
            && (string)($traceMcpOnly['provider_usado'] ?? '') === $spyMcpOnly->getProviderId()
    );

    // 4) auto implementado como local obligatorio + MCP auxiliar best-effort.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=auto');
    $spyAutoLocal = new SpyEvaluationProvider();
    $spyAutoMcp = new SpyEvaluationProvider();
    $spyAutoLocal->payload = [
        'resultado' => 'local_final',
        'score' => 7,
        'fuente' => 'local',
    ];
    $spyAutoMcp->payload = [
        'resultado' => 'mcp_aux',
        'score' => 999,
        'fuente' => 'mcp',
    ];
    $orchestratorAuto = new EvaluationOrchestrator($spyAutoLocal, null, $spyAutoMcp);
    $outputAuto = $orchestratorAuto->evaluate('fake.pdf');
    $traceAuto = $orchestratorAuto->getLastTrace();
    registerCheck(
        $result,
        'env_auto_local_obligatorio_mcp_auxiliar',
        $spyAutoLocal->calls === 1
            && $spyAutoMcp->calls === 1
            && $outputAuto === $spyAutoLocal->payload
            && (string)($traceAuto['modo_ejecucion'] ?? '') === 'local_with_mcp_auxiliary'
            && (bool)($traceAuto['local_ok'] ?? false) === true
            && (bool)($traceAuto['mcp_intentado'] ?? false) === true
            && (bool)($traceAuto['mcp_disponible'] ?? false) === true
            && (string)($traceAuto['mcp_reason'] ?? '') === 'mcp_auxiliary_ok'
    );

    // 5) valor invalido en env.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=valor_raro');
    $spyInvalid = new SpyEvaluationProvider();
    $orchestratorInvalid = new EvaluationOrchestrator($spyInvalid);
    $invalidThrows = false;
    try {
        $orchestratorInvalid->evaluate('fake.pdf');
    } catch (InvalidArgumentException $e) {
        $invalidThrows = strpos($e->getMessage(), 'Valor invalido') !== false;
    }
    registerCheck(
        $result,
        'env_invalido_falla_claro',
        $invalidThrows && $spyInvalid->calls === 0
    );

    // 6) mcp sin fallback local cuando falla MCP.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=mcp');
    $spyLocalNoFallback = new SpyEvaluationProvider();
    $throwingMcp = new ThrowingEvaluationProvider();
    $orchestratorNoFallback = new EvaluationOrchestrator($spyLocalNoFallback, null, $throwingMcp);
    $mcpFailControlled = false;
    try {
        $orchestratorNoFallback->evaluate('fake.pdf');
    } catch (RuntimeException $e) {
        $mcpFailControlled = strpos($e->getMessage(), 'reason=') !== false;
    }
    registerCheck(
        $result,
        'mcp_sin_fallback_local_si_mcp_falla',
        $mcpFailControlled && $throwingMcp->calls === 1 && $spyLocalNoFallback->calls === 0
    );

    // 7) auto falla si local falla (MCP no se intenta).
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=auto');
    $throwingLocalAuto = new ThrowingEvaluationProvider();
    $spyMcpNotCalled = new SpyEvaluationProvider();
    $orchestratorAutoLocalFail = new EvaluationOrchestrator($throwingLocalAuto, null, $spyMcpNotCalled);
    $autoLocalFailControlled = false;
    try {
        $orchestratorAutoLocalFail->evaluate('fake.pdf');
    } catch (RuntimeException $e) {
        $autoLocalFailControlled = strpos($e->getMessage(), 'reason=') !== false;
    }
    registerCheck(
        $result,
        'auto_si_local_falla_entonces_falla',
        $autoLocalFailControlled && $throwingLocalAuto->calls === 1 && $spyMcpNotCalled->calls === 0
    );

    // 8) LocalEvaluationProvider delega en Pipeline sin transformar.
    putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=local_only');
    $fakePipeline = new FakePipeline();
    $localProvider = new LocalEvaluationProvider($fakePipeline);
    $callbackReceived = false;
    $providerOutput = $localProvider->evaluate(
        'delegation.pdf',
        function (string $estado, int $progreso, string $fase, array $context = []) use (&$callbackReceived): void {
            if ($estado === 'procesando_pdf' && $progreso === 5 && $fase === 'fake_pipeline') {
                $callbackReceived = true;
            }
        }
    );
    registerCheck(
        $result,
        'local_provider_delega_pipeline',
        $fakePipeline->calls === 1
            && $fakePipeline->lastPdfPath === 'delegation.pdf'
            && $callbackReceived
            && $providerOutput === $fakePipeline->payload
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
    $result['message'] = $e->getMessage();
    outputResult($result, 1);
} finally {
    if ($previousMode === false) {
        putenv(EvaluationModeResolver::ENV_EXECUTION_MODE);
    } else {
        putenv(EvaluationModeResolver::ENV_EXECUTION_MODE . '=' . $previousMode);
    }
}
