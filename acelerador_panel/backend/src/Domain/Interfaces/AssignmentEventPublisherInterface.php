<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Domain\Interfaces;

use Acelerador\PanelBackend\Domain\Entities\Tutoria;

interface AssignmentEventPublisherInterface
{
    /**
     * @param array<int, int> $profesorIds
     */
    public function tutoriaCreated(Tutoria $tutoria, array $profesorIds): void;

    /**
     * @param array<int, int> $profesorIds
     */
    public function profesoresAdded(int $tutoriaId, array $profesorIds): void;

    public function profesorRemoved(int $tutoriaId, int $profesorId): void;

    /**
     * @param array<int, int> $added
     * @param array<int, int> $removed
     * @param array<int, int> $unchanged
     */
    public function profesoresSynced(int $tutoriaId, array $added, array $removed, array $unchanged): void;
}

