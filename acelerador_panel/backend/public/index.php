<?php
declare(strict_types=1);

use Acelerador\PanelBackend\Application\Mappers\ProfesorOutputMapper;
use Acelerador\PanelBackend\Application\Mappers\TutoriaOutputMapper;
use Acelerador\PanelBackend\Application\UseCases\AddProfesoresToTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\CreateTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\GetAssignedProfesorDetailUseCase;
use Acelerador\PanelBackend\Application\UseCases\GetTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\ListAssignedProfesoresUseCase;
use Acelerador\PanelBackend\Application\UseCases\RemoveProfesorFromTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\SyncTutoriaProfesoresUseCase;
use Acelerador\PanelBackend\Infrastructure\Auth\SessionTutorContextProvider;
use Acelerador\PanelBackend\Infrastructure\Events\NullAssignmentEventPublisher;
use Acelerador\PanelBackend\Infrastructure\Persistence\MysqliDatabase;
use Acelerador\PanelBackend\Infrastructure\Persistence\MysqliTransactionManager;
use Acelerador\PanelBackend\Infrastructure\Persistence\SchemaMap;
use Acelerador\PanelBackend\Infrastructure\Repositories\MySqlAsignacionRepository;
use Acelerador\PanelBackend\Infrastructure\Repositories\MySqlProfesorRepository;
use Acelerador\PanelBackend\Infrastructure\Repositories\MySqlTutoriaRepository;
use Acelerador\PanelBackend\Infrastructure\SqlMappers\ProfesorSqlMapper;
use Acelerador\PanelBackend\Infrastructure\SqlMappers\TutoriaSqlMapper;
use Acelerador\PanelBackend\Presentation\Controllers\TutoriaController;
use Acelerador\PanelBackend\Presentation\Routes\TutoriaRoutes;
use Acelerador\PanelBackend\Presentation\Validators\CreateTutoriaValidator;
use Acelerador\PanelBackend\Presentation\Validators\ListProfesoresValidator;
use Acelerador\PanelBackend\Presentation\Validators\ProfesorIdsValidator;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;
use Acelerador\PanelBackend\Shared\Http\JsonResponder;
use Acelerador\PanelBackend\Shared\Http\MetaFactory;
use Acelerador\PanelBackend\Shared\Http\Request;
use Acelerador\PanelBackend\Shared\Routing\Router;

require dirname(__DIR__) . '/src/Autoload.php';

$appConfig = require dirname(__DIR__) . '/config/app.php';
$dbConfig = require dirname(__DIR__) . '/config/database.php';
$schemaConfig = require dirname(__DIR__) . '/config/schema.php';

date_default_timezone_set((string) ($appConfig['timezone'] ?? 'UTC'));

header('Access-Control-Allow-Headers: Content-Type, X-Request-Id');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$request = Request::fromGlobals();
$requestId = $request->header('x-request-id') ?? bin2hex(random_bytes(8));

if ($request->method() === 'OPTIONS') {
    JsonResponder::respond(204, null, MetaFactory::build($requestId), null);
    exit;
}

try {
    $database = new MysqliDatabase($dbConfig);
    $schema = new SchemaMap($schemaConfig, $database);
    $transactionManager = new MysqliTransactionManager($database);

    $profesorMapper = new ProfesorSqlMapper();
    $tutoriaMapper = new TutoriaSqlMapper();
    $profesorRepository = new MySqlProfesorRepository($database, $schema, $profesorMapper);
    $tutoriaRepository = new MySqlTutoriaRepository($database, $schema, $tutoriaMapper);
    $asignacionRepository = new MySqlAsignacionRepository($database, $schema, $profesorMapper);

    $eventPublisher = new NullAssignmentEventPublisher();
    $authProvider = new SessionTutorContextProvider(
        $database,
        $schema,
        (string) ($appConfig['sessionUserKey'] ?? 'nombredelusuario')
    );

    $createUseCase = new CreateTutoriaUseCase(
        $tutoriaRepository,
        $profesorRepository,
        $asignacionRepository,
        $transactionManager,
        $eventPublisher
    );
    $getUseCase = new GetTutoriaUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository);
    $listUseCase = new ListAssignedProfesoresUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository);
    $getProfesorUseCase = new GetAssignedProfesorDetailUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository);
    $addUseCase = new AddProfesoresToTutoriaUseCase(
        $tutoriaRepository,
        $profesorRepository,
        $asignacionRepository,
        $transactionManager,
        $eventPublisher
    );
    $removeUseCase = new RemoveProfesorFromTutoriaUseCase(
        $tutoriaRepository,
        $profesorRepository,
        $asignacionRepository,
        $transactionManager,
        $eventPublisher
    );
    $syncUseCase = new SyncTutoriaProfesoresUseCase(
        $tutoriaRepository,
        $profesorRepository,
        $asignacionRepository,
        $transactionManager,
        $eventPublisher
    );

    $controller = new TutoriaController(
        $authProvider,
        $createUseCase,
        $getUseCase,
        $listUseCase,
        $getProfesorUseCase,
        $addUseCase,
        $removeUseCase,
        $syncUseCase,
        new CreateTutoriaValidator(),
        new ProfesorIdsValidator(),
        new ListProfesoresValidator(
            (int) ($appConfig['defaultPageSize'] ?? 20),
            (int) ($appConfig['maxPageSize'] ?? 100)
        ),
        new TutoriaOutputMapper(),
        new ProfesorOutputMapper()
    );

    $router = new Router();
    TutoriaRoutes::register($router, $controller);

    $matched = $router->match($request);
    if ($matched['handler'] === null) {
        throw new ApiException(404, 'NOT_FOUND', 'Ruta no encontrada.');
    }

    $result = call_user_func($matched['handler'], $request, $matched['params']);
    $status = (int) ($result['status'] ?? 200);
    $data = $result['data'] ?? null;
    $meta = MetaFactory::build($requestId, is_array($result['meta'] ?? null) ? $result['meta'] : []);

    JsonResponder::respond($status, $data, $meta, null);
} catch (ApiException $e) {
    $meta = MetaFactory::build($requestId);
    $error = [
        'code' => $e->errorCode(),
        'message' => $e->getMessage(),
        'details' => $e->details(),
    ];
    JsonResponder::respond($e->statusCode(), null, $meta, $error);
} catch (\Throwable $e) {
    $meta = MetaFactory::build($requestId);
    $error = [
        'code' => 'INTERNAL_ERROR',
        'message' => 'Error interno del servidor.',
        'details' => [],
    ];
    JsonResponder::respond(500, null, $meta, $error);
}

