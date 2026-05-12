<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\SqlMappers;

use Acelerador\PanelBackend\Domain\Entities\Tutoria;

final class TutoriaSqlMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function mapRowToEntity(array $row): Tutoria
    {
        return new Tutoria(
            (int) ($row['id'] ?? 0),
            (string) ($row['nombre'] ?? ''),
            isset($row['descripcion']) ? (string) $row['descripcion'] : null,
            (int) ($row['tutor_id'] ?? 0)
        );
    }
}

