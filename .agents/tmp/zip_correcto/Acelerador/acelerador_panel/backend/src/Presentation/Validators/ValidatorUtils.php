<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Presentation\Validators;

use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class ValidatorUtils
{
    /**
     * @param mixed $value
     * @return array<int, int>
     */
    public static function parseProfesorIds($value): array
    {
        if (!is_array($value)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'profesorIds debe ser un array.');
        }

        $result = [];
        foreach ($value as $item) {
            if (is_int($item) && $item > 0) {
                $result[] = $item;
                continue;
            }
            if (is_string($item) && preg_match('/^[1-9]\d*$/', $item) === 1) {
                $result[] = (int) $item;
                continue;
            }
            throw new ApiException(422, 'VALIDATION_ERROR', 'profesorIds solo admite enteros positivos.');
        }

        return array_values(array_unique($result));
    }

    /**
     * @param mixed $value
     */
    public static function parsePositiveInt($value, string $fieldName): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1) {
            return (int) $value;
        }
        throw new ApiException(422, 'VALIDATION_ERROR', "El campo {$fieldName} debe ser un entero positivo.");
    }
}

