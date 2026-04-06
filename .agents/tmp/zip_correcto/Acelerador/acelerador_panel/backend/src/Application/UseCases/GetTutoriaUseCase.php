<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\UseCases;

use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;

final class GetTutoriaUseCase extends BaseTutoriaUseCase
{
    private AsignacionRepositoryInterface $asignacionRepository;

    public function __construct(
        TutoriaRepositoryInterface $tutoriaRepository,
        ProfesorRepositoryInterface $profesorRepository,
        AsignacionRepositoryInterface $asignacionRepository
    ) {
        parent::__construct($tutoriaRepository, $profesorRepository);
        $this->asignacionRepository = $asignacionRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $tutorId, int $tutoriaId): array
    {
        $tutoria = $this->requireOwnedTutoria($tutoriaId, $tutorId);
        $assignedCount = $this->asignacionRepository->countByTutoria($tutoriaId);

        return [
            'tutoria' => $tutoria,
            'assignedCount' => $assignedCount,
        ];
    }
}

