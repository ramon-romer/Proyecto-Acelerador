<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\Matching;

use Acelerador\PanelBackend\Application\DTO\MatchingRequestInput;

final class CandidateRecommendationService
{
    private MatchingOrchestrator $orchestrator;

    public function __construct(MatchingOrchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * @param array<string, mixed> $aggregated
     * @return array<string, mixed>
     */
    public function recommend(array $aggregated, MatchingRequestInput $input): array
    {
        $result = $this->orchestrator->recommend($aggregated, $input);

        return [
            'ok' => true,
            'version' => 'matching-multifuente-v0',
            'modo' => $input->mode,
            'grupo_objetivo' => [
                'id' => $aggregated['grupo_objetivo']['id'] ?? null,
                'nombre' => $aggregated['grupo_objetivo']['nombre'] ?? null,
                'lineas_investigacion' => [],
                'palabras_clave' => [],
            ],
            'fuentes_usadas' => $aggregated['fuentes_usadas'] ?? [],
            'candidatos' => $result['candidatos'] ?? [],
            'trazabilidad_interna' => $result['trace'] ?? [],
        ];
    }
}

