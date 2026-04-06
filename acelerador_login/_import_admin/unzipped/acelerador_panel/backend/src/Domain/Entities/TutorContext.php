<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Domain\Entities;

final class TutorContext
{
    public function __construct(
        public readonly int $id,
        public readonly string $correo,
        public readonly string $nombreCompleto
    ) {
    }
}

