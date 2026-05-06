<?php

require_once __DIR__ . '/EvaluationProviderInterface.php';
require_once __DIR__ . '/LocalEvaluationProvider.php';
require_once __DIR__ . '/EvaluationExecutionMode.php';
require_once __DIR__ . '/EvaluationModeResolver.php';
require_once __DIR__ . '/Mcp/McpEvaluationProvider.php';

class EvaluationOrchestrator
{
    private const EXECUTION_LOCAL_PIPELINE = 'local_pipeline';
    private const EXECUTION_MCP_ONLY = 'mcp_only_internal_http_v1';
    private const EXECUTION_LOCAL_WITH_MCP_AUXILIARY = 'local_with_mcp_auxiliary';
    private const EXECUTION_NOT_IMPLEMENTED = 'mode_not_implemented';
    private const AUTO_MCP_AUXILIARY_TIMEOUT_MS = 1500;

    private $localProvider;
    private $mcpProvider;
    private $lastTrace;
    private $executionModeOverride;

    public function __construct(
        ?EvaluationProviderInterface $localProvider = null,
        ?string $executionModeOverride = null,
        ?EvaluationProviderInterface $mcpProvider = null
    )
    {
        $this->localProvider = $localProvider ?? new LocalEvaluationProvider();
        $this->mcpProvider = $mcpProvider;
        $this->executionModeOverride = $executionModeOverride;
        $this->lastTrace = $this->buildTraceTemplate(EvaluationExecutionMode::LOCAL_ONLY);
    }

    public function evaluate(string $pdfPath, ?callable $phaseCallback = null): array
    {
        $mode = $this->resolveExecutionMode();
        $this->lastTrace = $this->buildTraceTemplate($mode);

        if ($mode === EvaluationExecutionMode::LOCAL_ONLY) {
            $this->lastTrace['modo_ejecucion'] = self::EXECUTION_LOCAL_PIPELINE;
            $this->lastTrace['provider_usado'] = $this->localProvider->getProviderId();

            return $this->localProvider->evaluate($pdfPath, $phaseCallback);
        }

        if ($mode === EvaluationExecutionMode::MCP) {
            $provider = $this->resolveMcpProvider($mode);
            $this->lastTrace['modo_ejecucion'] = self::EXECUTION_MCP_ONLY;
            $this->lastTrace['provider_usado'] = $provider->getProviderId();

            return $provider->evaluate($pdfPath, $phaseCallback);
        }

        if ($mode === EvaluationExecutionMode::AUTO) {
            $this->lastTrace['modo_ejecucion'] = self::EXECUTION_LOCAL_WITH_MCP_AUXILIARY;
            $this->lastTrace['provider_usado'] = $this->localProvider->getProviderId();

            try {
                $localResult = $this->localProvider->evaluate($pdfPath, $phaseCallback);
                $this->lastTrace['local_ok'] = true;
            } catch (Throwable $e) {
                $this->lastTrace['local_ok'] = false;
                $this->lastTrace['mcp_reason'] = 'local_provider_error';
                throw $e;
            }

            $this->runMcpAuxiliaryBestEffort($pdfPath, $mode);
            return $localResult;
        }

        $this->lastTrace['modo_ejecucion'] = self::EXECUTION_NOT_IMPLEMENTED;
        $this->lastTrace['provider_usado'] = null;

        throw new RuntimeException(
            'Modo de ejecucion "' . $mode . '" reconocido, pero no implementado todavia.'
            . ' Fase actual soporta "' . EvaluationExecutionMode::LOCAL_ONLY . '" y "'
            . EvaluationExecutionMode::MCP . '" experimental.'
        );
    }

    public function getLastTrace(): array
    {
        return $this->lastTrace;
    }

    private function resolveExecutionMode(): string
    {
        if ($this->executionModeOverride !== null) {
            return EvaluationModeResolver::resolveFromRaw($this->executionModeOverride);
        }

        return EvaluationModeResolver::resolveFromEnvironment();
    }

    private function buildTraceTemplate(string $mode): array
    {
        return [
            'modo_solicitado' => $mode,
            'modo_ejecucion' => self::EXECUTION_LOCAL_PIPELINE,
            'provider_usado' => self::EXECUTION_LOCAL_PIPELINE,
            'fallback_activado' => false,
            'motivo_fallback' => null,
            'local_ok' => null,
            'mcp_intentado' => false,
            'mcp_disponible' => false,
            'mcp_reason' => null,
            'mcp_can_produce_runtime_result' => null,
            'mcp_contract_type' => null,
            'mcp_warnings' => [],
        ];
    }

    private function resolveMcpProvider(string $mode): EvaluationProviderInterface
    {
        if ($this->mcpProvider === null) {
            if ($mode === EvaluationExecutionMode::AUTO) {
                $this->mcpProvider = new McpEvaluationProvider(
                    new McpHttpClientInternalV1(null, self::AUTO_MCP_AUXILIARY_TIMEOUT_MS)
                );
            } else {
                $this->mcpProvider = new McpEvaluationProvider();
            }
        }

        return $this->mcpProvider;
    }

    private function runMcpAuxiliaryBestEffort(string $pdfPath, string $mode): void
    {
        $this->lastTrace['mcp_intentado'] = true;
        $provider = $this->resolveMcpProvider($mode);

        try {
            $provider->evaluate($pdfPath, null);
            $this->lastTrace['mcp_disponible'] = true;
            $this->lastTrace['mcp_reason'] = 'mcp_auxiliary_ok';
            $this->lastTrace['mcp_can_produce_runtime_result'] = true;
            $this->lastTrace['mcp_contract_type'] = 'runtime_compatible';
            $this->lastTrace['mcp_warnings'] = [
                'mcp_auxiliary_result_ignored_public_response_keeps_local',
            ];
            return;
        } catch (McpEvaluationProviderException $e) {
            $this->lastTrace['mcp_disponible'] = false;
            $this->lastTrace['mcp_reason'] = $e->getReason();
            $this->lastTrace['fallback_activado'] = true;
            $this->lastTrace['motivo_fallback'] = $e->getReason();
            $this->hydrateMcpTraceFromContext($e->getContext());
            return;
        } catch (Throwable $e) {
            $this->lastTrace['mcp_disponible'] = false;
            $this->lastTrace['mcp_reason'] = 'mcp_unknown_error';
            $this->lastTrace['fallback_activado'] = true;
            $this->lastTrace['motivo_fallback'] = 'mcp_unknown_error';
            $this->lastTrace['mcp_warnings'] = [
                'mcp_auxiliary_exception: ' . $e->getMessage(),
            ];
            return;
        }
    }

    private function hydrateMcpTraceFromContext(array $context): void
    {
        $canProduce = $context['can_produce_runtime_result'] ?? null;
        if (is_bool($canProduce)) {
            $this->lastTrace['mcp_can_produce_runtime_result'] = $canProduce;
        } else {
            $this->lastTrace['mcp_can_produce_runtime_result'] = false;
        }

        $contractType = $this->inferMcpContractType($context);
        $this->lastTrace['mcp_contract_type'] = $contractType;

        $warnings = [];
        $compatibilityReasons = $context['compatibility_reasons'] ?? [];
        if (is_array($compatibilityReasons)) {
            foreach ($compatibilityReasons as $reason) {
                if (is_string($reason) && trim($reason) !== '') {
                    $warnings[] = trim($reason);
                }
            }
        }

        $notes = $context['notes'] ?? [];
        if (is_array($notes)) {
            foreach ($notes as $note) {
                if (is_string($note) && trim($note) !== '') {
                    $warnings[] = trim($note);
                }
            }
        }

        $this->lastTrace['mcp_warnings'] = array_values(array_unique($warnings));
    }

    private function inferMcpContractType(array $context): string
    {
        $responseKind = $context['response_kind'] ?? null;
        if (is_string($responseKind) && trim($responseKind) !== '') {
            return trim($responseKind);
        }

        if (!empty($context['missing_runtime_required_fields']) && is_array($context['missing_runtime_required_fields'])) {
            return 'runtime_incomplete';
        }

        if (!empty($context['missing_legacy_required_fields']) && is_array($context['missing_legacy_required_fields'])) {
            return 'legacy_technical_incomplete';
        }

        return 'unknown_or_unavailable';
    }
}
