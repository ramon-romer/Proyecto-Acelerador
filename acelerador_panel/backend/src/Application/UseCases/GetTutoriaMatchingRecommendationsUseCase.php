<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\UseCases;

use Acelerador\PanelBackend\Application\DTO\MatchingRequestInput;
use Acelerador\PanelBackend\Application\Matching\CandidateRecommendationService;
use Acelerador\PanelBackend\Application\Matching\ResearchProfileAggregator;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class GetTutoriaMatchingRecommendationsUseCase extends BaseTutoriaUseCase
{
    private ResearchProfileAggregator $profileAggregator;
    private CandidateRecommendationService $recommendationService;

    public function __construct(
        TutoriaRepositoryInterface $tutoriaRepository,
        ProfesorRepositoryInterface $profesorRepository,
        ResearchProfileAggregator $profileAggregator,
        CandidateRecommendationService $recommendationService
    ) {
        parent::__construct($tutoriaRepository, $profesorRepository);
        $this->profileAggregator = $profileAggregator;
        $this->recommendationService = $recommendationService;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $tutorId, int $tutoriaId, MatchingRequestInput $input): array
    {
        $tutoria = $this->requireOwnedTutoria($tutoriaId, $tutorId);
        $aggregated = $this->profileAggregator->aggregate($tutoria, $tutorId, $input);
        $result = $this->recommendationService->recommend($aggregated, $input);

        if (
            $input->mode === MatchingRequestInput::MODE_MCP
            && !empty($result['trazabilidad_interna']['mcp_required_failed'])
        ) {
            $reason = $result['trazabilidad_interna']['motivo_mcp'] ?? 'mcp_unavailable';
            throw new ApiException(
                503,
                'MCP_UNAVAILABLE',
                'MCP no disponible en modo mcp.',
                [is_scalar($reason) ? (string)$reason : 'mcp_unavailable']
            );
        }

        return $result;
    }
}
