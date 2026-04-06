<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\UseCases;

use Acelerador\PanelBackend\Application\DTO\ListProfesoresInput;
use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;

final class ListAssignedProfesoresUseCase extends BaseTutoriaUseCase
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
    public function execute(int $tutorId, int $tutoriaId, ListProfesoresInput $input): array
    {
        $this->requireOwnedTutoria($tutoriaId, $tutorId);
        $result = $this->asignacionRepository->listByTutoria(
            $tutoriaId,
            $input->page,
            $input->pageSize,
            $input->search
        );

        return [
            'items' => $result['items'],
            'pagination' => [
                'page' => $input->page,
                'pageSize' => $input->pageSize,
                'total' => $result['total'],
            ],
        ];
    }
}

