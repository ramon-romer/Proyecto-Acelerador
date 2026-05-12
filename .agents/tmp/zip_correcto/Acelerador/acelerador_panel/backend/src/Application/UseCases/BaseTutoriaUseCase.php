<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\UseCases;

use Acelerador\PanelBackend\Domain\Entities\Tutoria;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

abstract class BaseTutoriaUseCase
{
    protected TutoriaRepositoryInterface $tutoriaRepository;
    protected ProfesorRepositoryInterface $profesorRepository;

    public function __construct(
        TutoriaRepositoryInterface $tutoriaRepository,
        ProfesorRepositoryInterface $profesorRepository
    ) {
        $this->tutoriaRepository = $tutoriaRepository;
        $this->profesorRepository = $profesorRepository;
    }

    protected function requireOwnedTutoria(int $tutoriaId, int $tutorId): Tutoria
    {
        $tutoria = $this->tutoriaRepository->findByIdForTutor($tutoriaId, $tutorId);
        if ($tutoria === null) {
            throw new ApiException(404, 'TUTORIA_NOT_FOUND', 'La tutoría no existe o no pertenece al tutor.');
        }
        return $tutoria;
    }

    /**
     * @param array<int, int> $profesorIds
     */
    protected function ensureProfesoresExist(array $profesorIds): void
    {
        $missing = [];
        foreach ($profesorIds as $profesorId) {
            if (!$this->profesorRepository->existsById($profesorId)) {
                $missing[] = $profesorId;
            }
        }

        if ($missing !== []) {
            throw new ApiException(
                404,
                'PROFESOR_NOT_FOUND',
                'Uno o más profesores no existen.',
                $missing
            );
        }
    }
}

