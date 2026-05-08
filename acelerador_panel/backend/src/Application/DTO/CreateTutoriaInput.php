<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\DTO;

final class CreateTutoriaInput
{
    /**
     * @param array<int, int> $profesorIds
     */
    public function __construct(
        public readonly string $nombre,
        public readonly ?string $descripcion,
        public readonly array $profesorIds
    ) {
    }
}

