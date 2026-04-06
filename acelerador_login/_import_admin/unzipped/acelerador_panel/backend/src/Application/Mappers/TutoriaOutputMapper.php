<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\Mappers;

use Acelerador\PanelBackend\Domain\Entities\Tutoria;

final class TutoriaOutputMapper
{
    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function map(Tutoria $tutoria, array $extra = []): array
    {
        return array_merge(
            [
                'tutoriaId' => $tutoria->id,
                'nombre' => $tutoria->nombre,
                'descripcion' => $tutoria->descripcion,
                'tutorId' => $tutoria->tutorId,
            ],
            $extra
        );
    }
}

