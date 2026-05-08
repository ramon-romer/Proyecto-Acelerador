<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\DTO;

final class ProfesorIdsInput
{
    /**
     * @param array<int, int> $profesorIds
     */
    public function __construct(public readonly array $profesorIds)
    {
    }
}

