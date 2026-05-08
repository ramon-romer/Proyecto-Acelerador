<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Domain\Repositories;

use Acelerador\PanelBackend\Domain\Entities\ProfesorAsignado;

interface AsignacionRepositoryInterface
{
    /**
     * @return array{items: array<int, ProfesorAsignado>, total: int}
     */
    public function listByTutoria(int $tutoriaId, int $page, int $pageSize, ?string $search): array;

    public function findAssignedProfesor(int $tutoriaId, int $profesorId): ?ProfesorAsignado;

    /**
     * @return array<int, int>
     */
    public function getAssignedProfesorIds(int $tutoriaId): array;

    /**
     * @param array<int, int> $profesorIds
     */
    public function insertAssignments(int $tutoriaId, array $profesorIds): void;

    public function deleteAssignment(int $tutoriaId, int $profesorId): bool;

    /**
     * @param array<int, int> $profesorIds
     */
    public function deleteAssignments(int $tutoriaId, array $profesorIds): int;

    public function countByTutoria(int $tutoriaId): int;
}

