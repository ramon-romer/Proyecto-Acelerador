<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\UseCases;

use Acelerador\PanelBackend\Application\DTO\CreateTutoriaInput;
use Acelerador\PanelBackend\Application\Interfaces\TransactionManagerInterface;
use Acelerador\PanelBackend\Domain\Interfaces\AssignmentEventPublisherInterface;
use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;

final class CreateTutoriaUseCase extends BaseTutoriaUseCase
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
    public function execute(int $tutorId, CreateTutoriaInput $input): array
    {
        return $this->transactionManager->run(function () use ($tutorId, $input): array {
            $profesorIds = array_values(array_unique($input->profesorIds));
            $this->ensureProfesoresExist($profesorIds);

            $tutoria = $this->tutoriaRepository->createForTutor($tutorId, $input->nombre, $input->descripcion);
            if ($profesorIds !== []) {
                $this->asignacionRepository->insertAssignments($tutoria->id, $profesorIds);
            }

            $this->eventPublisher->tutoriaCreated($tutoria, $profesorIds);

            return [
                'tutoria' => $tutoria,
                'assignedProfesorIds' => $profesorIds,
                'assignedCount' => count($profesorIds),
            ];
        });
    }
}

