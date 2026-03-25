<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\UseCases;

use Acelerador\PanelBackend\Application\Interfaces\TransactionManagerInterface;
use Acelerador\PanelBackend\Domain\Interfaces\AssignmentEventPublisherInterface;
use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class RemoveProfesorFromTutoriaUseCase extends BaseTutoriaUseCase
{
    private AsignacionRepositoryInterface $asignacionRepository;
    private TransactionManagerInterface $transactionManager;
    private AssignmentEventPublisherInterface $eventPublisher;

    public function __construct(
        TutoriaRepositoryInterface $tutoriaRepository,
        ProfesorRepositoryInterface $profesorRepository,
        AsignacionRepositoryInterface $asignacionRepository,
        TransactionManagerInterface $transactionManager,
        AssignmentEventPublisherInterface $eventPublisher
    ) {
        parent::__construct($tutoriaRepository, $profesorRepository);
        $this->asignacionRepository = $asignacionRepository;
        $this->transactionManager = $transactionManager;
        $this->eventPublisher = $eventPublisher;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $tutorId, int $tutoriaId, int $profesorId): array
    {
        return $this->transactionManager->run(function () use ($tutorId, $tutoriaId, $profesorId): array {
            $this->requireOwnedTutoria($tutoriaId, $tutorId);
            if (!$this->profesorRepository->existsById($profesorId)) {
                throw new ApiException(404, 'PROFESOR_NOT_FOUND', 'El profesor no existe.');
            }

            $deleted = $this->asignacionRepository->deleteAssignment($tutoriaId, $profesorId);
            if (!$deleted) {
                throw new ApiException(
                    404,
                    'ASSIGNMENT_NOT_FOUND',
                    'La asignación no existe para esta tutoría.'
                );
            }

            $this->eventPublisher->profesorRemoved($tutoriaId, $profesorId);

            return [
                'tutoriaId' => $tutoriaId,
                'removedProfesorId' => $profesorId,
            ];
        });
    }
}

