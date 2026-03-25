<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Autoload.php';

use Acelerador\PanelBackend\Application\DTO\CreateTutoriaInput;
use Acelerador\PanelBackend\Application\DTO\ListProfesoresInput;
use Acelerador\PanelBackend\Application\DTO\ProfesorIdsInput;
use Acelerador\PanelBackend\Application\Interfaces\TransactionManagerInterface;
use Acelerador\PanelBackend\Application\UseCases\AddProfesoresToTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\CreateTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\GetAssignedProfesorDetailUseCase;
use Acelerador\PanelBackend\Application\UseCases\GetTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\ListAssignedProfesoresUseCase;
use Acelerador\PanelBackend\Application\UseCases\RemoveProfesorFromTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\SyncTutoriaProfesoresUseCase;
use Acelerador\PanelBackend\Domain\Entities\ProfesorAsignado;
use Acelerador\PanelBackend\Domain\Entities\Tutoria;
use Acelerador\PanelBackend\Domain\Interfaces\AssignmentEventPublisherInterface;
use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;
use Acelerador\PanelBackend\Presentation\Validators\CreateTutoriaValidator;
use Acelerador\PanelBackend\Presentation\Validators\ListProfesoresValidator;
use Acelerador\PanelBackend\Presentation\Validators\ProfesorIdsValidator;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class InMemoryTutoriaRepository implements TutoriaRepositoryInterface
{
    /** @var array<int, Tutoria> */
    private array $items = [];
    private int $nextId = 1;

    public function createForTutor(int $tutorId, string $nombre, ?string $descripcion): Tutoria
    {
        $entity = new Tutoria($this->nextId++, $nombre, $descripcion, $tutorId);
        $this->items[$entity->id] = $entity;
        return $entity;
    }

    public function findByIdForTutor(int $tutoriaId, int $tutorId): ?Tutoria
    {
        $item = $this->items[$tutoriaId] ?? null;
        if ($item === null || $item->tutorId !== $tutorId) {
            return null;
        }
        return $item;
    }

    /**
     * @return array<int, Tutoria>
     */
    public function all(): array
    {
        return $this->items;
    }
}

final class InMemoryProfesorRepository implements ProfesorRepositoryInterface
{
    /** @var array<int, ProfesorAsignado> */
    private array $items = [];

    public function seedProfesor(ProfesorAsignado $profesor): void
    {
        $this->items[$profesor->id] = $profesor;
    }

    public function existsById(int $profesorId): bool
    {
        return isset($this->items[$profesorId]);
    }

    public function findById(int $profesorId): ?ProfesorAsignado
    {
        return $this->items[$profesorId] ?? null;
    }

    /**
     * @return array<int, int>
     */
    public function allIds(): array
    {
        return array_map('intval', array_keys($this->items));
    }
}

final class InMemoryAsignacionRepository implements AsignacionRepositoryInterface
{
    /** @var array<int, array<int, bool>> */
    private array $matrix = [];
    private InMemoryProfesorRepository $profesorRepository;

    public function __construct(InMemoryProfesorRepository $profesorRepository)
    {
        $this->profesorRepository = $profesorRepository;
    }

    public function listByTutoria(int $tutoriaId, int $page, int $pageSize, ?string $search): array
    {
        $ids = $this->getAssignedProfesorIds($tutoriaId);
        $items = [];
        foreach ($ids as $id) {
            $profesor = $this->profesorRepository->findById($id);
            if ($profesor === null) {
                continue;
            }
            if ($search !== null && $search !== '') {
                $haystack = mb_strtolower($profesor->nombreCompleto() . ' ' . (string) $profesor->orcid . ' ' . (string) $profesor->correo);
                if (!str_contains($haystack, mb_strtolower($search))) {
                    continue;
                }
            }
            $items[] = $profesor;
        }

        usort($items, static function (ProfesorAsignado $a, ProfesorAsignado $b): int {
            $cmp = strcmp($a->nombre, $b->nombre);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a->apellidos, $b->apellidos);
        });

        $total = count($items);
        $offset = max(0, ($page - 1) * $pageSize);
        $paged = array_slice($items, $offset, $pageSize);

        return [
            'items' => $paged,
            'total' => $total,
        ];
    }

    public function findAssignedProfesor(int $tutoriaId, int $profesorId): ?ProfesorAsignado
    {
        if (!isset($this->matrix[$tutoriaId][$profesorId])) {
            return null;
        }
        return $this->profesorRepository->findById($profesorId);
    }

    public function getAssignedProfesorIds(int $tutoriaId): array
    {
        if (!isset($this->matrix[$tutoriaId])) {
            return [];
        }
        $ids = array_map('intval', array_keys($this->matrix[$tutoriaId]));
        sort($ids);
        return $ids;
    }

    public function insertAssignments(int $tutoriaId, array $profesorIds): void
    {
        foreach ($profesorIds as $id) {
            $this->matrix[$tutoriaId][$id] = true;
        }
    }

    public function deleteAssignment(int $tutoriaId, int $profesorId): bool
    {
        if (!isset($this->matrix[$tutoriaId][$profesorId])) {
            return false;
        }
        unset($this->matrix[$tutoriaId][$profesorId]);
        return true;
    }

    public function deleteAssignments(int $tutoriaId, array $profesorIds): int
    {
        $deleted = 0;
        foreach ($profesorIds as $id) {
            if (isset($this->matrix[$tutoriaId][$id])) {
                unset($this->matrix[$tutoriaId][$id]);
                $deleted++;
            }
        }
        return $deleted;
    }

    public function countByTutoria(int $tutoriaId): int
    {
        return count($this->matrix[$tutoriaId] ?? []);
    }
}

final class PassthroughTransactionManager implements TransactionManagerInterface
{
    public function run(callable $callback)
    {
        return $callback();
    }
}

final class NoopAssignmentEventPublisher implements AssignmentEventPublisherInterface
{
    public function tutoriaCreated(Tutoria $tutoria, array $profesorIds): void
    {
    }
    public function profesoresAdded(int $tutoriaId, array $profesorIds): void
    {
    }
    public function profesorRemoved(int $tutoriaId, int $profesorId): void
    {
    }
    public function profesoresSynced(int $tutoriaId, array $added, array $removed, array $unchanged): void
    {
    }
}

/**
 * @param array<int, string> $argv
 * @return array<string, mixed>
 */
function parseArgs(array $argv): array
{
    $opts = [
        'duration-seconds' => 3600,
        'progress-interval' => 30,
        'seed' => null,
        'report-file' => dirname(__DIR__) . '/tests/results/aggressive_battery_report.json',
    ];

    foreach ($argv as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $parts = explode('=', substr($arg, 2), 2);
        $key = $parts[0];
        $value = $parts[1] ?? '1';
        $opts[$key] = $value;
    }

    return $opts;
}

function fail(string $message): void
{
    throw new RuntimeException($message);
}

/**
 * @param array<int, int> $items
 * @return array<int, int>
 */
function randomSample(array $items, int $count): array
{
    if ($count <= 0 || $items === []) {
        return [];
    }

    $total = count($items);
    if ($count >= $total) {
        return array_values($items);
    }

    $keys = array_rand($items, $count);
    if (!is_array($keys)) {
        $keys = [$keys];
    }

    $result = [];
    foreach ($keys as $key) {
        $result[] = $items[$key];
    }
    return $result;
}

/**
 * @param array<int, int> $ids
 */
function sortIds(array &$ids): void
{
    sort($ids);
}

$args = parseArgs($argv);
$durationSeconds = max(1, (int) $args['duration-seconds']);
$progressInterval = max(1, (int) $args['progress-interval']);
$reportFile = (string) $args['report-file'];
$seed = $args['seed'] !== null ? (int) $args['seed'] : random_int(1, PHP_INT_MAX);
mt_srand($seed);

$reportDir = dirname($reportFile);
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0777, true);
}

$stats = [
    'startedAt' => date(DATE_ATOM),
    'targetDurationSeconds' => $durationSeconds,
    'seed' => $seed,
    'totalOperations' => 0,
    'assertions' => 0,
    'invariantChecks' => 0,
    'operationStats' => [],
    'expectedErrors' => [],
    'unexpectedErrorCount' => 0,
    'unexpectedErrors' => [],
    'failedAssertions' => [],
];

$registerOp = static function (string $opName, string $bucket) use (&$stats): void {
    if (!isset($stats['operationStats'][$opName])) {
        $stats['operationStats'][$opName] = [
            'ok' => 0,
            'expected_error' => 0,
            'unexpected_error' => 0,
        ];
    }
    $stats['operationStats'][$opName][$bucket]++;
};

$recordExpectedError = static function (string $errorCode) use (&$stats): void {
    if (!isset($stats['expectedErrors'][$errorCode])) {
        $stats['expectedErrors'][$errorCode] = 0;
    }
    $stats['expectedErrors'][$errorCode]++;
};

$recordUnexpectedError = static function (string $opName, Throwable $e) use (&$stats): void {
    $stats['unexpectedErrorCount']++;
    if (count($stats['unexpectedErrors']) >= 200) {
        return;
    }
    $stats['unexpectedErrors'][] = [
        'operation' => $opName,
        'type' => get_class($e),
        'message' => $e->getMessage(),
    ];
};

$assert = static function (bool $condition, string $message) use (&$stats): void {
    $stats['assertions']++;
    if (!$condition) {
        $stats['failedAssertions'][] = $message;
        fail($message);
    }
};

try {
    $tutoriaRepository = new InMemoryTutoriaRepository();
    $profesorRepository = new InMemoryProfesorRepository();
    $asignacionRepository = new InMemoryAsignacionRepository($profesorRepository);
    $tx = new PassthroughTransactionManager();
    $events = new NoopAssignmentEventPublisher();

    $createUseCase = new CreateTutoriaUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository, $tx, $events);
    $getTutoriaUseCase = new GetTutoriaUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository);
    $listUseCase = new ListAssignedProfesoresUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository);
    $detailUseCase = new GetAssignedProfesorDetailUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository);
    $addUseCase = new AddProfesoresToTutoriaUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository, $tx, $events);
    $removeUseCase = new RemoveProfesorFromTutoriaUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository, $tx, $events);
    $syncUseCase = new SyncTutoriaProfesoresUseCase($tutoriaRepository, $profesorRepository, $asignacionRepository, $tx, $events);

    $createValidator = new CreateTutoriaValidator();
    $profesorIdsValidator = new ProfesorIdsValidator();
    $listValidator = new ListProfesoresValidator(20, 100);

    $totalProfesores = 800;
    for ($i = 1; $i <= $totalProfesores; $i++) {
        $profesorRepository->seedProfesor(
            new ProfesorAsignado(
                $i,
                'Nombre' . $i,
                'Apellido' . $i,
                sprintf('0000-0000-0000-%04d', $i),
                'Departamento' . (($i % 10) + 1),
                'profesor' . $i . '@example.com'
            )
        );
    }
    $allProfesorIds = $profesorRepository->allIds();

    $knownTutorIds = range(1001, 1015);
    $maxTutorias = 20000;
    /** @var array<int, array<int, int>> $tutorTutoriaIds */
    $tutorTutoriaIds = [];
    foreach ($knownTutorIds as $tutorId) {
        $tutorTutoriaIds[$tutorId] = [];
    }

    $start = microtime(true);
    $deadline = $start + $durationSeconds;
    $nextProgress = $start + $progressInterval;
    $operationCounter = 0;
    $tutoriaCount = 0;

    $pickExistingTutoria = static function () use (&$tutorTutoriaIds): ?array {
        $nonEmpty = [];
        foreach ($tutorTutoriaIds as $tutorId => $ids) {
            if ($ids !== []) {
                $nonEmpty[] = (int) $tutorId;
            }
        }
        if ($nonEmpty === []) {
            return null;
        }
        $tutorId = $nonEmpty[array_rand($nonEmpty)];
        $tutoriaIds = $tutorTutoriaIds[$tutorId];
        $tutoriaId = $tutoriaIds[array_rand($tutoriaIds)];
        return ['tutorId' => $tutorId, 'tutoriaId' => $tutoriaId];
    };

    $checkInvariants = static function () use (
        &$tutorTutoriaIds,
        $asignacionRepository,
        $profesorRepository,
        $listUseCase,
        &$stats,
        $assert
    ): void {
        $stats['invariantChecks']++;
        foreach ($tutorTutoriaIds as $tutorId => $tutoriaIds) {
            if ($tutoriaIds === []) {
                continue;
            }

            $sample = randomSample($tutoriaIds, min(5, count($tutoriaIds)));
            foreach ($sample as $tutoriaId) {
                $ids = $asignacionRepository->getAssignedProfesorIds($tutoriaId);
                $unique = array_values(array_unique($ids));
                sortIds($ids);
                sortIds($unique);
                $assert($ids === $unique, "Asignaciones duplicadas detectadas en tutoría {$tutoriaId}");

                foreach ($ids as $id) {
                    $assert($profesorRepository->existsById($id), "Asignación con profesor inexistente {$id}");
                }

                $count = $asignacionRepository->countByTutoria($tutoriaId);
                $assert($count === count($ids), "countByTutoria inconsistente en tutoría {$tutoriaId}");

                $list = $listUseCase->execute((int) $tutorId, $tutoriaId, new ListProfesoresInput(1, 1000, null));
                $assert(
                    (int) ($list['pagination']['total'] ?? -1) === count($ids),
                    "ListUseCase total inconsistente en tutoría {$tutoriaId}"
                );
            }
        }
    };

    echo 'Starting aggressive battery. seed=' . $seed . ', duration=' . $durationSeconds . "s\n";
    flush();

    while (microtime(true) < $deadline) {
        $operationCounter++;
        $stats['totalOperations']++;
        $opRoll = mt_rand(1, 100);

        $hasAnyTutoria = $pickExistingTutoria() !== null;
        if (($opRoll <= 20 && $tutoriaCount < $maxTutorias) || !$hasAnyTutoria) {
            $opName = 'create_tutoria';
            try {
                $tutorId = $knownTutorIds[array_rand($knownTutorIds)];
                $requestedInitial = randomSample($allProfesorIds, mt_rand(0, 12));
                $payload = [
                    'nombre' => 'Tutoria #' . mt_rand(1, 999999),
                    'descripcion' => mt_rand(0, 1) === 1 ? 'Descripcion de prueba ' . mt_rand(1, 999) : null,
                    'profesorIds' => $requestedInitial,
                ];

                $validated = $createValidator->validate($payload);
                $result = $createUseCase->execute($tutorId, $validated);
                $tutoriaId = $result['tutoria']->id;
                $tutorTutoriaIds[$tutorId][] = $tutoriaId;
                $tutoriaCount++;

                $currentIds = $asignacionRepository->getAssignedProfesorIds($tutoriaId);
                sortIds($currentIds);
                $expectedIds = array_values(array_unique($requestedInitial));
                sortIds($expectedIds);
                $assert($currentIds === $expectedIds, 'create_tutoria no preserva asignaciones iniciales');

                $registerOp($opName, 'ok');
            } catch (Throwable $e) {
                $registerOp($opName, 'unexpected_error');
                $recordUnexpectedError($opName, $e);
            }
        } elseif ($opRoll <= 40) {
            $opName = 'add_profesores';
            try {
                $picked = $pickExistingTutoria();
                if ($picked === null) {
                    continue;
                }
                $tutorId = $picked['tutorId'];
                $tutoriaId = $picked['tutoriaId'];
                $currentIds = $asignacionRepository->getAssignedProfesorIds($tutoriaId);

                $scenario = mt_rand(1, 10);
                if ($scenario <= 3 && $currentIds !== []) {
                    $payload = ['profesorIds' => randomSample($currentIds, mt_rand(1, min(4, count($currentIds))))];
                    $input = $profesorIdsValidator->validate($payload);
                    $addUseCase->execute($tutorId, $tutoriaId, $input);
                    $registerOp($opName, 'unexpected_error');
                    $recordUnexpectedError($opName, new RuntimeException('Esperado 409 por duplicado y no ocurrió'));
                } elseif ($scenario === 4) {
                    $invalidId = $totalProfesores + mt_rand(1, 2000);
                    $input = $profesorIdsValidator->validate(['profesorIds' => [$invalidId]]);
                    $addUseCase->execute($tutorId, $tutoriaId, $input);
                    $registerOp($opName, 'unexpected_error');
                    $recordUnexpectedError($opName, new RuntimeException('Esperado 404 por profesor inexistente y no ocurrió'));
                } else {
                    $available = array_values(array_diff($allProfesorIds, $currentIds));
                    if ($available === []) {
                        $registerOp($opName, 'ok');
                        continue;
                    }
                    $toAdd = randomSample($available, mt_rand(1, min(8, count($available))));
                    $input = $profesorIdsValidator->validate(['profesorIds' => $toAdd]);
                    $result = $addUseCase->execute($tutorId, $tutoriaId, $input);

                    $newIds = $asignacionRepository->getAssignedProfesorIds($tutoriaId);
                    foreach ($toAdd as $id) {
                        $assert(in_array($id, $newIds, true), 'add_profesores no añadió id esperado');
                    }
                    $assert((int) $result['addedCount'] === count($toAdd), 'addedCount inconsistente');
                    $registerOp($opName, 'ok');
                }
            } catch (ApiException $e) {
                if (in_array($e->errorCode(), ['ASSIGNMENT_DUPLICATE', 'PROFESOR_NOT_FOUND'], true)) {
                    $registerOp($opName, 'expected_error');
                    $recordExpectedError($e->errorCode());
                } else {
                    $registerOp($opName, 'unexpected_error');
                    $recordUnexpectedError($opName, $e);
                }
            } catch (Throwable $e) {
                $registerOp($opName, 'unexpected_error');
                $recordUnexpectedError($opName, $e);
            }
        } elseif ($opRoll <= 58) {
            $opName = 'sync_profesores';
            try {
                $picked = $pickExistingTutoria();
                if ($picked === null) {
                    continue;
                }
                $tutorId = $picked['tutorId'];
                $tutoriaId = $picked['tutoriaId'];
                $scenario = mt_rand(1, 10);

                if ($scenario === 1) {
                    $invalid = $totalProfesores + mt_rand(1, 3000);
                    $syncUseCase->execute($tutorId, $tutoriaId, new ProfesorIdsInput([$invalid]));
                    $registerOp($opName, 'unexpected_error');
                    $recordUnexpectedError($opName, new RuntimeException('Esperado 404 por sync con profesor inexistente'));
                } else {
                    $target = randomSample($allProfesorIds, mt_rand(0, 25));
                    $result = $syncUseCase->execute($tutorId, $tutoriaId, new ProfesorIdsInput($target));
                    $current = $asignacionRepository->getAssignedProfesorIds($tutoriaId);
                    $expected = array_values(array_unique($target));
                    sortIds($current);
                    sortIds($expected);
                    $assert($current === $expected, 'sync_profesores no dejó estado esperado');

                    $added = $result['added'];
                    $removed = $result['removed'];
                    $unchanged = $result['unchanged'];
                    $merged = array_merge($added, $removed, $unchanged);
                    $mergedUnique = array_values(array_unique($merged));
                    sortIds($merged);
                    sortIds($mergedUnique);
                    $assert($merged === $mergedUnique, 'sync_profesores diff con ids repetidos');

                    foreach ($added as $id) {
                        $assert(in_array($id, $expected, true), 'sync_profesores added fuera de target');
                    }
                    foreach ($unchanged as $id) {
                        $assert(in_array($id, $expected, true), 'sync_profesores unchanged fuera de target');
                    }
                    foreach ($removed as $id) {
                        $assert(!in_array($id, $expected, true), 'sync_profesores removed dentro de target');
                    }
                    $registerOp($opName, 'ok');
                }
            } catch (ApiException $e) {
                if ($e->errorCode() === 'PROFESOR_NOT_FOUND') {
                    $registerOp($opName, 'expected_error');
                    $recordExpectedError($e->errorCode());
                } else {
                    $registerOp($opName, 'unexpected_error');
                    $recordUnexpectedError($opName, $e);
                }
            } catch (Throwable $e) {
                $registerOp($opName, 'unexpected_error');
                $recordUnexpectedError($opName, $e);
            }
        } elseif ($opRoll <= 72) {
            $opName = 'remove_profesor';
            try {
                $picked = $pickExistingTutoria();
                if ($picked === null) {
                    continue;
                }
                $tutorId = $picked['tutorId'];
                $tutoriaId = $picked['tutoriaId'];
                $currentIds = $asignacionRepository->getAssignedProfesorIds($tutoriaId);
                $scenario = mt_rand(1, 10);

                if ($scenario <= 2 || $currentIds === []) {
                    $candidate = $totalProfesores + mt_rand(1, 2000);
                } elseif ($scenario <= 5) {
                    $candidate = mt_rand(1, $totalProfesores);
                    if (in_array($candidate, $currentIds, true)) {
                        $candidate = $totalProfesores + mt_rand(1, 2000);
                    }
                } else {
                    $candidate = $currentIds[array_rand($currentIds)];
                }

                $result = $removeUseCase->execute($tutorId, $tutoriaId, $candidate);
                $assert((int) $result['removedProfesorId'] === $candidate, 'remove_profesor devuelve id incorrecto');
                $after = $asignacionRepository->getAssignedProfesorIds($tutoriaId);
                $assert(!in_array($candidate, $after, true), 'remove_profesor no eliminó el id');
                $registerOp($opName, 'ok');
            } catch (ApiException $e) {
                if (in_array($e->errorCode(), ['PROFESOR_NOT_FOUND', 'ASSIGNMENT_NOT_FOUND'], true)) {
                    $registerOp($opName, 'expected_error');
                    $recordExpectedError($e->errorCode());
                } else {
                    $registerOp($opName, 'unexpected_error');
                    $recordUnexpectedError($opName, $e);
                }
            } catch (Throwable $e) {
                $registerOp($opName, 'unexpected_error');
                $recordUnexpectedError($opName, $e);
            }
        } elseif ($opRoll <= 84) {
            $opName = 'list_profesores';
            try {
                $picked = $pickExistingTutoria();
                if ($picked === null) {
                    continue;
                }
                $tutorId = $picked['tutorId'];
                $tutoriaId = $picked['tutoriaId'];
                $query = [
                    'page' => (string) mt_rand(1, 5),
                    'pageSize' => (string) mt_rand(1, 60),
                    'search' => mt_rand(0, 1) === 1 ? 'Nombre' . mt_rand(1, 60) : '',
                ];
                $input = $listValidator->validate($query);
                $result = $listUseCase->execute($tutorId, $tutoriaId, $input);
                $items = $result['items'];
                $total = (int) ($result['pagination']['total'] ?? -1);
                $assert($total >= 0, 'list_profesores total inválido');
                $assert(count($items) <= $input->pageSize, 'list_profesores retorna más que pageSize');
                $registerOp($opName, 'ok');
            } catch (Throwable $e) {
                $registerOp($opName, 'unexpected_error');
                $recordUnexpectedError($opName, $e);
            }
        } elseif ($opRoll <= 92) {
            $opName = 'get_profesor_detail';
            try {
                $picked = $pickExistingTutoria();
                if ($picked === null) {
                    continue;
                }
                $tutorId = $picked['tutorId'];
                $tutoriaId = $picked['tutoriaId'];
                $currentIds = $asignacionRepository->getAssignedProfesorIds($tutoriaId);
                $useAssigned = ($currentIds !== []) && mt_rand(0, 1) === 1;
                $profesorId = $useAssigned ? $currentIds[array_rand($currentIds)] : ($totalProfesores + mt_rand(1, 5000));

                $result = $detailUseCase->execute($tutorId, $tutoriaId, $profesorId);
                $assert($useAssigned, 'detail devolvió éxito para profesor no asignado');
                $assert((int) $result['profesor']->id === $profesorId, 'detail devuelve profesor incorrecto');
                $registerOp($opName, 'ok');
            } catch (ApiException $e) {
                if ($e->errorCode() === 'ASSIGNMENT_NOT_FOUND') {
                    $registerOp($opName, 'expected_error');
                    $recordExpectedError($e->errorCode());
                } else {
                    $registerOp($opName, 'unexpected_error');
                    $recordUnexpectedError($opName, $e);
                }
            } catch (Throwable $e) {
                $registerOp($opName, 'unexpected_error');
                $recordUnexpectedError($opName, $e);
            }
        } elseif ($opRoll <= 97) {
            $opName = 'get_tutoria';
            try {
                $picked = $pickExistingTutoria();
                if ($picked === null) {
                    continue;
                }
                $valid = mt_rand(1, 10) <= 8;
                $tutorId = $valid ? $picked['tutorId'] : $knownTutorIds[array_rand($knownTutorIds)];
                if (!$valid && $tutorId === $picked['tutorId']) {
                    $tutorId += 2000;
                }
                $result = $getTutoriaUseCase->execute($tutorId, $picked['tutoriaId']);
                $assert($valid, 'get_tutoria devolvió éxito para tutor no dueño');
                $assert((int) $result['tutoria']->id === $picked['tutoriaId'], 'get_tutoria id inconsistente');
                $registerOp($opName, 'ok');
            } catch (ApiException $e) {
                if ($e->errorCode() === 'TUTORIA_NOT_FOUND') {
                    $registerOp($opName, 'expected_error');
                    $recordExpectedError($e->errorCode());
                } else {
                    $registerOp($opName, 'unexpected_error');
                    $recordUnexpectedError($opName, $e);
                }
            } catch (Throwable $e) {
                $registerOp($opName, 'unexpected_error');
                $recordUnexpectedError($opName, $e);
            }
        } else {
            $opName = 'validators_negative';
            try {
                try {
                    $createValidator->validate(['nombre' => '', 'profesorIds' => ['x']]);
                    fail('createValidator aceptó nombre vacío');
                } catch (ApiException $e) {
                    if ($e->errorCode() !== 'VALIDATION_ERROR') {
                        throw $e;
                    }
                }

                try {
                    $profesorIdsValidator->validate(['profesorIds' => []]);
                    fail('profesorIdsValidator aceptó array vacío');
                } catch (ApiException $e) {
                    if ($e->errorCode() !== 'VALIDATION_ERROR') {
                        throw $e;
                    }
                }

                try {
                    $listValidator->validate(['page' => '0', 'pageSize' => '1000']);
                    fail('listValidator aceptó page/pageSize inválidos');
                } catch (ApiException $e) {
                    if ($e->errorCode() !== 'VALIDATION_ERROR') {
                        throw $e;
                    }
                }

                $registerOp($opName, 'ok');
            } catch (Throwable $e) {
                $registerOp($opName, 'unexpected_error');
                $recordUnexpectedError($opName, $e);
            }
        }

        if ($operationCounter % 250 === 0) {
            $checkInvariants();
        }

        $now = microtime(true);
        if ($now >= $nextProgress) {
            $elapsed = (int) floor($now - $start);
            $opsPerSec = $elapsed > 0 ? round($stats['totalOperations'] / $elapsed, 2) : 0.0;
            $unexpectedCount = (int) $stats['unexpectedErrorCount'];
            echo "[progress] elapsed={$elapsed}s ops={$stats['totalOperations']} ops/s={$opsPerSec} unexpected={$unexpectedCount}\n";
            flush();
            $nextProgress = $now + $progressInterval;
        }
    }

    $endedAt = microtime(true);
    $elapsedTotal = $endedAt - $start;
    $stats['endedAt'] = date(DATE_ATOM);
    $stats['actualDurationSeconds'] = round($elapsedTotal, 3);
    $stats['opsPerSecond'] = round($stats['totalOperations'] / max($elapsedTotal, 0.000001), 2);
    $stats['peakMemoryMB'] = round(memory_get_peak_usage(true) / (1024 * 1024), 2);
    $stats['totalTutorias'] = count($tutoriaRepository->all());
    $stats['finalStatus'] = ((int) $stats['unexpectedErrorCount'] === 0) && $stats['failedAssertions'] === []
        ? 'PASS'
        : 'FAIL';

    file_put_contents($reportFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    echo "Aggressive battery finished. status={$stats['finalStatus']} report={$reportFile}\n";
    echo 'Summary: operations=' . $stats['totalOperations']
        . ', assertions=' . $stats['assertions']
        . ', invariantChecks=' . $stats['invariantChecks']
        . ', unexpectedErrors=' . (int) $stats['unexpectedErrorCount']
        . ", duration={$stats['actualDurationSeconds']}s\n";

    exit($stats['finalStatus'] === 'PASS' ? 0 : 1);
} catch (Throwable $e) {
    $stats['endedAt'] = date(DATE_ATOM);
    $stats['finalStatus'] = 'FAIL';
    $stats['fatalError'] = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
    ];
    file_put_contents($reportFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    fwrite(STDERR, "Fatal error in aggressive battery: {$e->getMessage()}\n");
    exit(1);
}
