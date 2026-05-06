<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\Matching;

use Acelerador\PanelBackend\Application\DTO\MatchingRequestInput;
use Acelerador\PanelBackend\Domain\Entities\Tutoria;
use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Infrastructure\Persistence\AnecaEvaluationSignalLookup;

final class ResearchProfileAggregator
{
    private ProfesorRepositoryInterface $profesorRepository;
    private AsignacionRepositoryInterface $asignacionRepository;
    private AnecaEvaluationSignalLookup $evaluationSignalLookup;

    public function __construct(
        ProfesorRepositoryInterface $profesorRepository,
        AsignacionRepositoryInterface $asignacionRepository,
        AnecaEvaluationSignalLookup $evaluationSignalLookup
    ) {
        $this->profesorRepository = $profesorRepository;
        $this->asignacionRepository = $asignacionRepository;
        $this->evaluationSignalLookup = $evaluationSignalLookup;
    }

    /**
     * @return array<string, mixed>
     */
    public function aggregate(Tutoria $tutoria, int $tutorId, MatchingRequestInput $input): array
    {
        $assignedIds = $this->asignacionRepository->getAssignedProfesorIds($tutoria->id);
        $assignedIdSet = array_fill_keys($assignedIds, true);
        $profesores = $this->profesorRepository->listForMatching($input->limit, $input->search);

        $profiles = [];
        foreach ($profesores as $profesor) {
            if ($profesor->id === $tutorId) {
                continue;
            }

            $yaAsignado = isset($assignedIdSet[$profesor->id]);
            if ($yaAsignado && !$input->includeAssigned) {
                continue;
            }

            $anecaSignals = $this->evaluationSignalLookup->getSignalsByOrcid($profesor->orcid);

            $profiles[] = [
                'profesor_id' => $profesor->id,
                'nombre_mostrable' => $profesor->nombreCompleto(),
                'orcid' => $profesor->orcid,
                'departamento' => $profesor->departamento,
                'ya_asignado_grupo' => $yaAsignado,
                'aneca_evaluaciones_count' => $anecaSignals['count'],
                'aneca_ultimo_resultado' => $anecaSignals['latest_result'],
                'aneca_signal_available' => $anecaSignals['available'],
            ];
        }

        $availability = $this->evaluationSignalLookup->getAvailability();
        $fuentesFaltantes = [];
        if (!$availability['available'] || !$availability['has_orcid']) {
            $fuentesFaltantes[] = 'evaluaciones_aneca_por_orcid_no_disponible';
        }

        return [
            'grupo_objetivo' => [
                'id' => $tutoria->id,
                'nombre' => $tutoria->nombre,
                'descripcion' => $tutoria->descripcion,
            ],
            'fuentes_usadas' => [
                'perfil_profesor' => true,
                'grupos' => true,
                'evaluacion_aneca' => $availability['available'] && $availability['has_orcid'],
                'cv_procesado' => false,
                'publicaciones' => false,
                'fuentes_externas' => false,
            ],
            'fuentes_faltantes' => $fuentesFaltantes,
            'profiles' => $profiles,
        ];
    }
}

