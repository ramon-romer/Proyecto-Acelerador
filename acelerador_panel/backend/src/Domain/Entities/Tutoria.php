<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Domain\Entities;

final class Tutoria
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly ?string $descripcion,
        public readonly int $tutorId
    ) {
    }
}

