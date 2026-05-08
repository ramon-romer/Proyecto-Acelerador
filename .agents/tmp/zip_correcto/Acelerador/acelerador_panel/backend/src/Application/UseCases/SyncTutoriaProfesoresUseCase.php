<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\UseCases;

use Acelerador\PanelBackend\Application\DTO\ProfesorIdsInput;
use Acelerador\PanelBackend\Application\Interfaces\TransactionManagerInterface;
use Acelerador\PanelBackend\Domain\Interfaces\AssignmentEventPublisherInterface;
use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;

final class SyncTutoriaProfesoresUseCase extends BaseTutoriaUseCase
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

            $targetIds = array_values(array_unique($input->profesorIds));
            $this->ensureProfesoresExist($targetIds);

            $currentIds = $this->asignacionRepository->getAssignedProfesorIds($tutoriaId);
            $added = array_values(array_diff($targetIds, $currentIds));
            $removed = array_values(array_diff($currentIds, $targetIds));
            $unchanged = array_values(array_intersect($targetIds, $currentIds));

            if ($added !== []) {
                $this->asignacionRepository->insertAssignments($tutoriaId, $added);
            }
            if ($removed !== []) {
                $this->asignacionRepository->deleteAssignments($tutoriaId, $removed);
            }

            $this->eventPublisher->profesoresSynced($tutoriaId, $added, $removed, $unchanged);

            return [
                'tutoriaId' => $tutoriaId,
                'added' => $added,
                'removed' => $removed,
                'unchanged' => $unchanged,
            ];
        });
    }
}

