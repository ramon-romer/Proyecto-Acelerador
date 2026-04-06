<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\Mappers;

use Acelerador\PanelBackend\Domain\Entities\ProfesorAsignado;

final class ProfesorOutputMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(ProfesorAsignado $profesor): array
    {
        return [
            'profesorId' => $profesor->id,
            'nombre' => $profesor->nombre,
            'apellidos' => $profesor->apellidos,
            'nombreCompleto' => $profesor->nombreCompleto(),
            'orcid' => $profesor->orcid,
            'departamento' => $profesor->departamento,
            'correo' => $profesor->correo,
        ];
    }

    /**
     * @param array<int, ProfesorAsignado> $profesores
     * @return array<int, array<string, mixed>>
     */
    public function mapList(array $profesores): array
    {
        $result = [];
        foreach ($profesores as $profesor) {
            $result[] = $this->map($profesor);
        }
        return $result;
    }
}

