<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Autoload.php';

use Acelerador\PanelBackend\Application\DTO\CreateTutoriaInput;
use Acelerador\PanelBackend\Application\DTO\ProfesorIdsInput;
use Acelerador\PanelBackend\Application\Interfaces\TransactionManagerInterface;
use Acelerador\PanelBackend\Application\UseCases\AddProfesoresToTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\CreateTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\RemoveProfesorFromTutoriaUseCase;
use Acelerador\PanelBackend\Application\UseCases\SyncTutoriaProfesoresUseCase;
use Acelerador\PanelBackend\Domain\Entities\ProfesorAsignado;
use Acelerador\PanelBackend\Domain\Entities\Tutoria;
use Acelerador\PanelBackend\Domain\Interfaces\AssignmentEventPublisherInterface;
use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class InMemoryTutoriaRepository implements TutoriaRepositoryInterface
{
    /** @var array<int, Tutoria> */
    public array $items = [];
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
}

final class InMemoryProfesorRepository implements ProfesorRepositoryInterface
{
    /** @var array<int, ProfesorAsignado> */
    public array $items = [];

    public function existsById(int $profesorId): bool
    {
        return isset($this->items[$profesorId]);
    }

    public function findById(int $profesorId): ?ProfesorAsignado
    {
        return $this->items[$profesorId] ?? null;
    }
}

final class InMemoryAsignacionRepository implements AsignacionRepositoryInterface
{
    /** @var array<int, array<int, bool>> */
    public array $matrix = [];
    private InMemoryProfesorRepository $profesores;

    public function __construct(InMemoryProfesorRepository $profesores)
    {
        $this->profesores = $profesores;
    }

    public function listByTutoria(int $tutoriaId, int $page, int $pageSize, ?string $search): array
    {
        $ids = $this->getAssignedProfesorIds($tutoriaId);
        $items = [];
        foreach ($ids as $id) {
            $p = $this->profesores->findById($id);
            if ($p !== null) {
                $items[] = $p;
            }
        }
        return ['items' => $items, 'total' => count($items)];
    }

    public function findAssignedProfesor(int $tutoriaId, int $profesorId): ?ProfesorAsignado
    {
        if (!isset($this->matrix[$tutoriaId][$profesorId])) {
            return null;
        }
        return $this->profesores->findById($profesorId);
    }

    public function getAssignedProfesorIds(int $tutoriaId): array
    {
        return isset($this->matrix[$tutoriaId]) ? array_map('intval', array_keys($this->matrix[$tutoriaId])) : [];
    }

    public function insertAssignments(int $tutoriaId, array $profesorIds): void
    {
        foreach ($profesorIds as $profesorId) {
            $this->matrix[$tutoriaId][$profesorId] = true;
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

final class FakeTransactionManager implements TransactionManagerInterface
{
    public function run(callable $callback)
    {
        return $callback();
    }
}

final class NullPublisher implements AssignmentEventPublisherInterface
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

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Test failed: {$message}");
    }
}

$tutorias = new InMemoryTutoriaRepository();
$profesores = new InMemoryProfesorRepository();
$asignaciones = new InMemoryAsignacionRepository($profesores);
$tx = new FakeTransactionManager();
$publisher = new NullPublisher();

$profesores->items[10] = new ProfesorAsignado(10, 'Ana', 'Perez', null, null, null);
$profesores->items[11] = new ProfesorAsignado(11, 'Luis', 'Garcia', null, null, null);
$profesores->items[12] = new ProfesorAsignado(12, 'Marta', 'Ruiz', null, null, null);

$createUC = new CreateTutoriaUseCase($tutorias, $profesores, $asignaciones, $tx, $publisher);
$addUC = new AddProfesoresToTutoriaUseCase($tutorias, $profesores, $asignaciones, $tx, $publisher);
$removeUC = new RemoveProfesorFromTutoriaUseCase($tutorias, $profesores, $asignaciones, $tx, $publisher);
$syncUC = new SyncTutoriaProfesoresUseCase($tutorias, $profesores, $asignaciones, $tx, $publisher);

$created = $createUC->execute(99, new CreateTutoriaInput('Grupo A', null, [10]));
$tutoriaId = $created['tutoria']->id;
assertTrue($created['assignedCount'] === 1, 'create assigns initial profesores');

try {
    $addUC->execute(99, $tutoriaId, new ProfesorIdsInput([10]));
    throw new RuntimeException('Expected duplicate conflict');
} catch (ApiException $e) {
    assertTrue($e->statusCode() === 409, 'duplicate assignment returns 409');
}

$syncResult = $syncUC->execute(99, $tutoriaId, new ProfesorIdsInput([10, 11, 12]));
assertTrue(count($syncResult['added']) === 2, 'sync added 2');
assertTrue(count($syncResult['removed']) === 0, 'sync removed 0');

$removeResult = $removeUC->execute(99, $tutoriaId, 11);
assertTrue($removeResult['removedProfesorId'] === 11, 'remove one assignment');

try {
    $createUC->execute(99, new CreateTutoriaInput('Grupo B', null, [9999]));
    throw new RuntimeException('Expected not found error');
} catch (ApiException $e) {
    assertTrue($e->statusCode() === 404, 'missing profesor returns 404');
}

echo "OK: usecases smoke tests passed.\n";

