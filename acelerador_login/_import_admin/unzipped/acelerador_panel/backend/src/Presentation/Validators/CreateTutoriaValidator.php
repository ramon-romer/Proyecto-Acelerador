<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Presentation\Validators;

use Acelerador\PanelBackend\Application\DTO\CreateTutoriaInput;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class CreateTutoriaValidator
{
    /**
     * @param array<string, mixed> $payload
     */
    public function validate(array $payload): CreateTutoriaInput
    {
        $nombre = isset($payload['nombre']) && is_string($payload['nombre'])
            ? trim($payload['nombre'])
            : '';
        if ($nombre === '') {
            throw new ApiException(422, 'VALIDATION_ERROR', 'El campo nombre es obligatorio.');
        }
        if (mb_strlen($nombre) > 150) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'El nombre supera el máximo de 150 caracteres.');
        }

        $descripcion = null;
        if (array_key_exists('descripcion', $payload) && $payload['descripcion'] !== null) {
            if (!is_string($payload['descripcion'])) {
                throw new ApiException(422, 'VALIDATION_ERROR', 'descripcion debe ser string o null.');
            }
            $descripcion = trim($payload['descripcion']);
            if ($descripcion !== '' && mb_strlen($descripcion) > 1000) {
                throw new ApiException(422, 'VALIDATION_ERROR', 'La descripción supera el máximo de 1000 caracteres.');
            }
        }

        $profesorIds = [];
        if (isset($payload['profesorIds'])) {
            $profesorIds = ValidatorUtils::parseProfesorIds($payload['profesorIds']);
        }

        return new CreateTutoriaInput($nombre, $descripcion, $profesorIds);
    }
}

