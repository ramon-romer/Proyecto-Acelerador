<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\UseCases;

use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class GetAssignedProfesorDetailUseCase extends BaseTutoriaUseCase
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
    public function execute(int $tutorId, int $tutoriaId, int $profesorId): array
    {
        $this->requireOwnedTutoria($tutoriaId, $tutorId);
        $profesor = $this->asignacionRepository->findAssignedProfesor($tutoriaId, $profesorId);
        if ($profesor === null) {
            throw new ApiException(
                404,
                'ASSIGNMENT_NOT_FOUND',
                'El profesor no está asignado a esta tutoría.'
            );
        }

        return [
            'profesor' => $profesor,
        ];
    }
}

