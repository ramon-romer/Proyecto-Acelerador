<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\Matching;

use Acelerador\PanelBackend\Application\DTO\MatchingRequestInput;

final class MatchingOrchestrator
{
    private ResearchGroupMatchingService $baselineService;
    private McpMatchingAssistant $mcpAssistant;
    private MatchingPrivacyFilter $privacyFilter;

    public function __construct(
        ResearchGroupMatchingService $baselineService,
        McpMatchingAssistant $mcpAssistant,
        MatchingPrivacyFilter $privacyFilter
    ) {
        $this->baselineService = $baselineService;
        $this->mcpAssistant = $mcpAssistant;
        $this->privacyFilter = $privacyFilter;
    }

    /**
     * @param array<string, mixed> $aggregated
     * @return array<string, mixed>
     */
    public function recommend(array $aggregated, MatchingRequestInput $input): array
    {
        $baselineCandidates = $this->baselineService->buildBaseline($aggregated);
        $trace = [
            'provider_usado' => 'local_baseline',
            'fallback_activado' => false,
            'motivo_fallback' => null,
            'mcp_intentado' => false,
            'mcp_disponible' => null,
            'motivo_mcp' => null,
            'fuentes_faltantes' => $aggregated['fuentes_faltantes'] ?? [],
            'datos_minimizados' => true,
        ];

        if ($input->mode === MatchingRequestInput::MODE_LOCAL_ONLY) {
            return [
                'candidatos' => $baselineCandidates,
                'trace' => $trace,
            ];
        }

        $trace['mcp_intentado'] = true;
        $mcpPayload = $this->privacyFilter->buildMcpPayload($aggregated, $baselineCandidates);
        $mcpResult = $this->mcpAssistant->enrich($mcpPayload);

        $trace['mcp_disponible'] = $mcpResult['mcp_disponible'] ?? false;
        $trace['motivo_mcp'] = $mcpResult['motivo_mcp'] ?? null;
        $trace['provider_usado'] = !empty($trace['mcp_disponible'])
            ? 'local_baseline_plus_mcp_auxiliary'
            : 'local_baseline';
        if (empty($trace['mcp_disponible']) && $input->mode === MatchingRequestInput::MODE_AUTO) {
            $trace['fallback_activado'] = true;
            $trace['motivo_fallback'] = is_string($trace['motivo_mcp']) ? $trace['motivo_mcp'] : 'mcp_unavailable';
        }

        $annotations = is_array($mcpResult['candidate_annotations'] ?? null)
            ? $mcpResult['candidate_annotations']
            : [];
        $globalWarnings = is_array($mcpResult['global_warnings'] ?? null)
            ? $mcpResult['global_warnings']
            : [];

        $enriched = [];
        foreach ($baselineCandidates as $candidate) {
            $profesorId = (int)($candidate['profesor_id'] ?? 0);
            $annotation = is_array($annotations[$profesorId] ?? null) ? $annotations[$profesorId] : null;
            if ($annotation !== null) {
                $candidate['motivos'] = array_values(
                    array_unique(
                        array_merge(
                            is_array($candidate['motivos'] ?? null) ? $candidate['motivos'] : [],
                            is_array($annotation['motivos'] ?? null) ? $annotation['motivos'] : []
                        )
                    )
                );
                $candidate['advertencias'] = array_values(
                    array_unique(
                        array_merge(
                            is_array($candidate['advertencias'] ?? null) ? $candidate['advertencias'] : [],
                            is_array($annotation['advertencias'] ?? null) ? $annotation['advertencias'] : []
                        )
                    )
                );
                $candidate['score_mcp'] = $annotation['score_mcp'] ?? null;
            }

            if ($globalWarnings !== []) {
                $candidate['advertencias'] = array_values(
                    array_unique(
                        array_merge(
                            is_array($candidate['advertencias'] ?? null) ? $candidate['advertencias'] : [],
                            $globalWarnings
                        )
                    )
                );
            }

            // MCP nunca decide puntuacion final en MVP.
            $candidate['score_final'] = (int)($candidate['score_local'] ?? 0);
            $enriched[] = $candidate;
        }

        if ($input->mode === MatchingRequestInput::MODE_MCP) {
            $trace['motivo_mcp'] = !empty($trace['mcp_disponible'])
                ? 'mcp_forzado_activo'
                : (is_string($trace['motivo_mcp']) ? $trace['motivo_mcp'] : 'mcp_unavailable');
            $trace['mcp_required_failed'] = empty($trace['mcp_disponible']);
        }

        return [
            'candidatos' => $enriched,
            'trace' => $trace,
        ];
    }
}
