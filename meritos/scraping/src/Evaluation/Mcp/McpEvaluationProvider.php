<?php

require_once __DIR__ . '/../EvaluationProviderInterface.php';
require_once __DIR__ . '/McpClientInterface.php';
require_once __DIR__ . '/McpClientException.php';
require_once __DIR__ . '/McpHttpClientInternalV1.php';
require_once __DIR__ . '/McpInternalHttpV1Contract.php';
require_once __DIR__ . '/McpInternalHttpV1Normalizer.php';
require_once __DIR__ . '/McpEvaluationProviderException.php';

class McpEvaluationProvider implements EvaluationProviderInterface
{
    private const PROVIDER_ID = 'internal_http_v1_experimental';

    private $client;
    private $normalizer;

    public function __construct(
        ?McpClientInterface $client = null,
        ?McpInternalHttpV1Normalizer $normalizer = null
    ) {
        $this->client = $client ?? new McpHttpClientInternalV1();
        $this->normalizer = $normalizer ?? new McpInternalHttpV1Normalizer();
    }

    public function evaluate(string $pdfPath, ?callable $phaseCallback = null): array
    {
        $this->emitPhase($phaseCallback, 'procesando_pdf', 5, 'mcp_only_inicio', [
            'provider' => self::PROVIDER_ID,
        ]);

        $payload = [
            'fuente' => [
                'tipo' => 'pdf',
                'ruta' => $pdfPath,
            ],
        ];

        try {
            $response = $this->client->extractData($payload);
        } catch (McpClientException $e) {
            throw new McpEvaluationProviderException(
                $this->normalizeReason($e->getReason()),
                'MCP mcp_only fallo en llamada extract-data: ' . $e->getMessage(),
                ['mcp_context' => $e->getContext()],
                0,
                $e
            );
        } catch (Throwable $e) {
            throw new McpEvaluationProviderException(
                'mcp_unknown_error',
                'MCP mcp_only fallo por error no controlado: ' . $e->getMessage(),
                [],
                0,
                $e
            );
        }

        $decoded = $response->getJson();
        if (!is_array($decoded)) {
            throw new McpEvaluationProviderException(
                'mcp_invalid_json',
                'MCP mcp_only devolvio respuesta no JSON objeto.',
                ['http_status' => $response->getStatusCode()]
            );
        }

        if (!empty($decoded['queued'])) {
            throw new McpEvaluationProviderException(
                'mcp_partial_extraction_only',
                'MCP mcp_only devolvio procesamiento asincrono (queued=true); no hay resultado runtime inmediato.',
                [
                    'http_status' => $response->getStatusCode(),
                    'job_id' => $decoded['job_id'] ?? null,
                ]
            );
        }

        if (isset($decoded['error'])) {
            throw new McpEvaluationProviderException(
                'mcp_unknown_error',
                'MCP mcp_only devolvio error: ' . (string)$decoded['error'],
                ['http_status' => $response->getStatusCode()]
            );
        }

        $normalization = $this->normalizer->normalize($decoded);
        if (!$normalization->canProduceRuntimeResult()) {
            $reason = $this->pickNormalizationReason($normalization->getCompatibilityReasons());
            throw new McpEvaluationProviderException(
                $reason,
                'Respuesta MCP incompatible con runtime local actual.',
                $normalization->toArray()
            );
        }

        $result = $normalization->getNormalizedPayload();
        if (!McpInternalHttpV1Contract::hasAllKeys($result, McpInternalHttpV1Contract::runtimeRequiredKeys())) {
            throw new McpEvaluationProviderException(
                'mcp_missing_required_fields',
                'MCP devolvio payload sin claves runtime minimas requeridas.',
                $normalization->toArray()
            );
        }

        $this->emitPhase($phaseCallback, 'calculando_puntuacion', 90, 'mcp_only_fin', [
            'provider' => self::PROVIDER_ID,
        ]);

        return $result;
    }

    public function getProviderId(): string
    {
        return self::PROVIDER_ID;
    }

    private function emitPhase(?callable $phaseCallback, string $estado, int $progreso, string $fase, array $context = []): void
    {
        if ($phaseCallback === null) {
            return;
        }

        $phaseCallback($estado, $progreso, $fase, $context);
    }

    private function pickNormalizationReason(array $reasons): string
    {
        $priority = [
            'mcp_missing_required_fields',
            'mcp_partial_extraction_only',
            'mcp_contract_legacy',
            'mcp_not_aneca_canonical',
        ];

        foreach ($priority as $candidate) {
            if (in_array($candidate, $reasons, true)) {
                return $candidate;
            }
        }

        return 'mcp_unknown_error';
    }

    private function normalizeReason(string $reason): string
    {
        $allowed = [
            'mcp_unavailable',
            'mcp_timeout',
            'mcp_connection_refused',
            'mcp_invalid_json',
            'mcp_empty_response',
            'mcp_contract_legacy',
            'mcp_missing_required_fields',
            'mcp_not_aneca_canonical',
            'mcp_partial_extraction_only',
            'mcp_unknown_error',
        ];

        if (in_array($reason, $allowed, true)) {
            return $reason;
        }

        return 'mcp_unknown_error';
    }
}
