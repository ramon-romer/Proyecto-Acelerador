<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Presentation\Controllers;

use Acelerador\PanelBackend\Application\Mappers\ProfesorOutputMapper;
use Acelerador\PanelBackend\Application\Mappers\TutoriaOutputMapper;
use Acelerador\PanelBackend\Application\UseCases\AddProfesoresToTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\CreateTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\GetTutoriaMatchingRecommendationsUseCase;
use Acelerador\PanelBackend\Application\UseCases\GetAssignedProfesorDetailUseCase;
use Acelerador\PanelBackend\Application\UseCases\GetTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\ListAssignedProfesoresUseCase;
use Acelerador\PanelBackend\Application\UseCases\RemoveProfesorFromTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\SyncTutoriaProfesoresUseCase;
use Acelerador\PanelBackend\Domain\Interfaces\TutorContextProviderInterface;
use Acelerador\PanelBackend\Presentation\Validators\CreateTutoriaValidator;
use Acelerador\PanelBackend\Presentation\Validators\ListProfesoresValidator;
use Acelerador\PanelBackend\Presentation\Validators\MatchingRequestValidator;
use Acelerador\PanelBackend\Presentation\Validators\ProfesorIdsValidator;
use Acelerador\PanelBackend\Presentation\Validators\ValidatorUtils;
use Acelerador\PanelBackend\Shared\Http\Request;

final class TutoriaController
{
    private TutorContextProviderInterface $tutorContextProvider;
    private CreateTutoriaUseCase $createTutoriaUseCase;
    private GetTutoriaUseCase $getTutoriaUseCase;
    private ListAssignedProfesoresUseCase $listAssignedProfesoresUseCase;
    private GetAssignedProfesorDetailUseCase $getAssignedProfesorDetailUseCase;
    private AddProfesoresToTutoriaUseCase $addProfesoresToTutoriaUseCase;
    private RemoveProfesorFromTutoriaUseCase $removeProfesorFromTutoriaUseCase;
    private SyncTutoriaProfesoresUseCase $syncTutoriaProfesoresUseCase;
    private GetTutoriaMatchingRecommendationsUseCase $getTutoriaMatchingRecommendationsUseCase;
    private CreateTutoriaValidator $createTutoriaValidator;
    private ProfesorIdsValidator $profesorIdsValidator;
    private ListProfesoresValidator $listProfesoresValidator;
    private MatchingRequestValidator $matchingRequestValidator;
    private TutoriaOutputMapper $tutoriaOutputMapper;
    private ProfesorOutputMapper $profesorOutputMapper;

    public function __construct(
        TutorContextProviderInterface $tutorContextProvider,
        CreateTutoriaUseCase $createTutoriaUseCase,
        GetTutoriaUseCase $getTutoriaUseCase,
        ListAssignedProfesoresUseCase $listAssignedProfesoresUseCase,
        GetAssignedProfesorDetailUseCase $getAssignedProfesorDetailUseCase,
        AddProfesoresToTutoriaUseCase $addProfesoresToTutoriaUseCase,
        RemoveProfesorFromTutoriaUseCase $removeProfesorFromTutoriaUseCase,
        SyncTutoriaProfesoresUseCase $syncTutoriaProfesoresUseCase,
        GetTutoriaMatchingRecommendationsUseCase $getTutoriaMatchingRecommendationsUseCase,
        CreateTutoriaValidator $createTutoriaValidator,
        ProfesorIdsValidator $profesorIdsValidator,
        ListProfesoresValidator $listProfesoresValidator,
        MatchingRequestValidator $matchingRequestValidator,
        TutoriaOutputMapper $tutoriaOutputMapper,
        ProfesorOutputMapper $profesorOutputMapper
    ) {
        $this->tutorContextProvider = $tutorContextProvider;
        $this->createTutoriaUseCase = $createTutoriaUseCase;
        $this->getTutoriaUseCase = $getTutoriaUseCase;
        $this->listAssignedProfesoresUseCase = $listAssignedProfesoresUseCase;
        $this->getAssignedProfesorDetailUseCase = $getAssignedProfesorDetailUseCase;
        $this->addProfesoresToTutoriaUseCase = $addProfesoresToTutoriaUseCase;
        $this->removeProfesorFromTutoriaUseCase = $removeProfesorFromTutoriaUseCase;
        $this->syncTutoriaProfesoresUseCase = $syncTutoriaProfesoresUseCase;
        $this->getTutoriaMatchingRecommendationsUseCase = $getTutoriaMatchingRecommendationsUseCase;
        $this->createTutoriaValidator = $createTutoriaValidator;
        $this->profesorIdsValidator = $profesorIdsValidator;
        $this->listProfesoresValidator = $listProfesoresValidator;
        $this->matchingRequestValidator = $matchingRequestValidator;
        $this->tutoriaOutputMapper = $tutoriaOutputMapper;
        $this->profesorOutputMapper = $profesorOutputMapper;
    }

    /**
     * @param array<string, string> $routeParams
     * @return array{status:int,data:mixed,meta:array<string,mixed>}
     */
    public function createTutoria(Request $request, array $routeParams): array
    {
        $tutor = $this->tutorContextProvider->requireTutor();
        $input = $this->createTutoriaValidator->validate($request->jsonBody());
        $result = $this->createTutoriaUseCase->execute($tutor->id, $input);

        return [
            'status' => 201,
            'data' => [
                'tutoria' => $this->tutoriaOutputMapper->map($result['tutoria'], [
                    'assignedCount' => $result['assignedCount'],
                ]),
                'assignedProfesorIds' => $result['assignedProfesorIds'],
            ],
            'meta' => [],
        ];
    }

    /**
     * @param array<string, string> $routeParams
     * @return array{status:int,data:mixed,meta:array<string,mixed>}
     */
    public function getTutoria(Request $request, array $routeParams): array
    {
        $tutor = $this->tutorContextProvider->requireTutor();
        $tutoriaId = ValidatorUtils::parsePositiveInt($routeParams['tutoriaId'] ?? null, 'tutoriaId');
        $result = $this->getTutoriaUseCase->execute($tutor->id, $tutoriaId);

        return [
            'status' => 200,
            'data' => [
                'tutoria' => $this->tutoriaOutputMapper->map($result['tutoria'], [
                    'assignedCount' => $result['assignedCount'],
                ]),
            ],
            'meta' => [],
        ];
    }

    /**
     * @param array<string, string> $routeParams
     * @return array{status:int,data:mixed,meta:array<string,mixed>}
     */
    public function listProfesores(Request $request, array $routeParams): array
    {
        $tutor = $this->tutorContextProvider->requireTutor();
        $tutoriaId = ValidatorUtils::parsePositiveInt($routeParams['tutoriaId'] ?? null, 'tutoriaId');
        $listInput = $this->listProfesoresValidator->validate($request->queryParams());
        $result = $this->listAssignedProfesoresUseCase->execute($tutor->id, $tutoriaId, $listInput);

        return [
            'status' => 200,
            'data' => [
                'items' => $this->profesorOutputMapper->mapList($result['items']),
            ],
            'meta' => [
                'pagination' => $result['pagination'],
            ],
        ];
    }

    /**
     * @param array<string, string> $routeParams
     * @return array{status:int,data:mixed,meta:array<string,mixed>}
     */
    public function getProfesorDetail(Request $request, array $routeParams): array
    {
        $tutor = $this->tutorContextProvider->requireTutor();
        $tutoriaId = ValidatorUtils::parsePositiveInt($routeParams['tutoriaId'] ?? null, 'tutoriaId');
        $profesorId = ValidatorUtils::parsePositiveInt($routeParams['profesorId'] ?? null, 'profesorId');
        $result = $this->getAssignedProfesorDetailUseCase->execute($tutor->id, $tutoriaId, $profesorId);

        return [
            'status' => 200,
            'data' => [
                'profesor' => $this->profesorOutputMapper->map($result['profesor']),
            ],
            'meta' => [],
        ];
    }

    /**
     * @param array<string, string> $routeParams
     * @return array{status:int,data:mixed,meta:array<string,mixed>}
     */
    public function addProfesores(Request $request, array $routeParams): array
    {
        $tutor = $this->tutorContextProvider->requireTutor();
        $tutoriaId = ValidatorUtils::parsePositiveInt($routeParams['tutoriaId'] ?? null, 'tutoriaId');
        $input = $this->profesorIdsValidator->validate($request->jsonBody());
        $result = $this->addProfesoresToTutoriaUseCase->execute($tutor->id, $tutoriaId, $input);

        return [
            'status' => 201,
            'data' => $result,
            'meta' => [],
        ];
    }

    /**
     * @param array<string, string> $routeParams
     * @return array{status:int,data:mixed,meta:array<string,mixed>}
     */
    public function removeProfesor(Request $request, array $routeParams): array
    {
        $tutor = $this->tutorContextProvider->requireTutor();
        $tutoriaId = ValidatorUtils::parsePositiveInt($routeParams['tutoriaId'] ?? null, 'tutoriaId');
        $profesorId = ValidatorUtils::parsePositiveInt($routeParams['profesorId'] ?? null, 'profesorId');
        $result = $this->removeProfesorFromTutoriaUseCase->execute($tutor->id, $tutoriaId, $profesorId);

        return [
            'status' => 200,
            'data' => $result,
            'meta' => [],
        ];
    }

    /**
     * @param array<string, string> $routeParams
     * @return array{status:int,data:mixed,meta:array<string,mixed>}
     */
    public function syncProfesores(Request $request, array $routeParams): array
    {
        $tutor = $this->tutorContextProvider->requireTutor();
        $tutoriaId = ValidatorUtils::parsePositiveInt($routeParams['tutoriaId'] ?? null, 'tutoriaId');
        $input = $this->profesorIdsValidator->validate($request->jsonBody());
        $result = $this->syncTutoriaProfesoresUseCase->execute($tutor->id, $tutoriaId, $input);

        return [
            'status' => 200,
            'data' => $result,
            'meta' => [],
        ];
    }

    /**
     * @param array<string, string> $routeParams
     * @return array{status:int,data:mixed,meta:array<string,mixed>}
     */
    public function getMatchingRecommendations(Request $request, array $routeParams): array
    {
        $tutor = $this->tutorContextProvider->requireTutor();
        $tutoriaId = ValidatorUtils::parsePositiveInt($routeParams['tutoriaId'] ?? null, 'tutoriaId');
        $input = $this->matchingRequestValidator->validate($request->queryParams());

        $result = $this->getTutoriaMatchingRecommendationsUseCase->execute($tutor->id, $tutoriaId, $input);
        if (!$input->includeTrace) {
            unset($result['trazabilidad_interna']);
        }

        return [
            'status' => 200,
            'data' => $result,
            'meta' => [],
        ];
    }
}

