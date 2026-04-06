<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\SqlMappers;

use Acelerador\PanelBackend\Domain\Entities\ProfesorAsignado;

final class ProfesorSqlMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function mapRowToEntity(array $row): ProfesorAsignado
    {
        return new ProfesorAsignado(
            (int) ($row['id'] ?? 0),
            (string) ($row['nombre'] ?? ''),
            (string) ($row['apellidos'] ?? ''),
            isset($row['orcid']) ? (string) $row['orcid'] : null,
            isset($row['departamento']) ? (string) $row['departamento'] : null,
            isset($row['correo']) ? (string) $row['correo'] : null
        );
    }
}

