<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Presentation\Validators;

use Acelerador\PanelBackend\Application\DTO\ProfesorIdsInput;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class ProfesorIdsValidator
{
    /**
     * @param array<string, mixed> $payload
     */
    public function validate(array $payload): ProfesorIdsInput
    {
        if (array_key_exists('profesorId', $payload)) {
            $single = ValidatorUtils::parsePositiveInt($payload['profesorId'], 'profesorId');
            return new ProfesorIdsInput([$single]);
        }

        if (!array_key_exists('profesorIds', $payload)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Debes enviar profesorId o profesorIds.');
        }

        $profesorIds = ValidatorUtils::parseProfesorIds($payload['profesorIds']);
        if ($profesorIds === []) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'profesorIds no puede ser vacío.');
        }

        return new ProfesorIdsInput($profesorIds);
    }
}

