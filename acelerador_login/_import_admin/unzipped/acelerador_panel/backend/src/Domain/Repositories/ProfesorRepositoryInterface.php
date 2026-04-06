<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Domain\Repositories;

use Acelerador\PanelBackend\Domain\Entities\ProfesorAsignado;

interface ProfesorRepositoryInterface
{
    public function existsById(int $profesorId): bool;

    public function findById(int $profesorId): ?ProfesorAsignado;
}

