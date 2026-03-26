<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\UseCases;

use Acelerador\PanelBackend\Application\DTO\ProfesorIdsInput;
use Acelerador\PanelBackend\Application\Interfaces\TransactionManagerInterface;
use Acelerador\PanelBackend\Domain\Interfaces\AssignmentEventPublisherInterface;
use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class AddProfesoresToTutoriaUseCase extends BaseTutoriaUseCase
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
    public function execute(int $tutorId, int $tutoriaId, ProfesorIdsInput $input): array
    {
        return $this->transactionManager->run(function () use ($tutorId, $tutoriaId, $input): array {
            $this->requireOwnedTutoria($tutoriaId, $tutorId);
            $profesorIds = array_values(array_unique($input->profesorIds));
            $this->ensureProfesoresExist($profesorIds);

            $currentIds = $this->asignacionRepository->getAssignedProfesorIds($tutoriaId);
            $duplicates = array_values(array_intersect($profesorIds, $currentIds));
            if ($duplicates !== []) {
                throw new ApiException(
                    409,
                    'ASSIGNMENT_DUPLICATE',
                    'Uno o más profesores ya están asignados a la tutoría.',
                    $duplicates
                );
            }

            $this->asignacionRepository->insertAssignments($tutoriaId, $profesorIds);
            $this->eventPublisher->profesoresAdded($tutoriaId, $profesorIds);

            return [
                'tutoriaId' => $tutoriaId,
                'addedProfesorIds' => $profesorIds,
                'addedCount' => count($profesorIds),
            ];
        });
    }
}

