<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Persistence;

use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class SqlIdentifier
{
    public static function quote(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new ApiException(
                500,
                'INVALID_SCHEMA_MAPPING',
                'Identificador SQL inválido en configuración.'
            );
        }

        return '`' . $identifier . '`';
    }
}

