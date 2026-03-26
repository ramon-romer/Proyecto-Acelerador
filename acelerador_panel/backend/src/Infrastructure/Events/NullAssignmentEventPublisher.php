<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Events;

use Acelerador\PanelBackend\Domain\Entities\Tutoria;
use Acelerador\PanelBackend\Domain\Interfaces\AssignmentEventPublisherInterface;

final class NullAssignmentEventPublisher implements AssignmentEventPublisherInterface
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

