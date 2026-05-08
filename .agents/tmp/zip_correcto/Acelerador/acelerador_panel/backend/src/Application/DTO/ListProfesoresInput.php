<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\DTO;

final class ListProfesoresInput
{
    public function __construct(
        public readonly int $page,
        public readonly int $pageSize,
        public readonly ?string $search
    ) {
    }
}

