<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Domain\Entities;

final class ProfesorAsignado
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $apellidos,
        public readonly ?string $orcid,
        public readonly ?string $departamento,
        public readonly ?string $correo
    ) {
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellidos);
    }
}

