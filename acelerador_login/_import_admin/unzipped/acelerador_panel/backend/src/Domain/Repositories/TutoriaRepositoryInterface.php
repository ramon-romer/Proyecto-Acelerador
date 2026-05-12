<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Domain\Repositories;

use Acelerador\PanelBackend\Domain\Entities\Tutoria;

interface TutoriaRepositoryInterface
{
    public function createForTutor(int $tutorId, string $nombre, ?string $descripcion): Tutoria;

    public function findByIdForTutor(int $tutoriaId, int $tutorId): ?Tutoria;
}

