<?php
declare(strict_types=1);

/**
 * Validador JSON Schema ligero para pruebas de compatibilidad sin dependencias externas.
 * Soporta un subset suficiente para contratos JSON del proyecto:
 * - $ref local (#/...)
 * - type, required, properties, additionalProperties
 * - enum, const
 * - minLength, maxLength, pattern
 * - minimum, maximum
 * - minItems, maxItems, items
 * - format=date-time
 * - anyOf, oneOf, allOf
 */
final class JsonSchemaLiteValidator
{
    /** @var array<string, mixed> */
    private array $rootSchema;

    /**
     * @param array<string, mixed> $rootSchema
     */
    public function __construct(array $rootSchema)
    {
        $this->rootSchema = $rootSchema;
    }

    /**
     * @param mixed $data
     * @return array<int, string>
     */
    public function validate(mixed $data): array
    {
        $errors = [];
        $this->validateNode($data, $this->rootSchema, '$', $errors);
        return $errors;
    }

    /**
     * @param mixed $data
     * @param array<string, mixed> $schema
     * @param array<int, string> $errors
     */
    private function validateNode(mixed $data, array $schema, string $path, array &$errors): void
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $resolved = $this->resolveRef($schema['$ref']);
            if ($resolved === null) {
                $errors[] = "{$path}: referencia no resoluble {$schema['$ref']}";
                return;
            }
            $this->validateNode($data, $resolved, $path, $errors);
            return;
        }

        if (!$this->validateComposition($data, $schema, $path, $errors)) {
            return;
        }

        if (isset($schema['type'])) {
            $allowedTypes = is_array($schema['type']) ? $schema['type'] : [$schema['type']];
            if (!$this->matchesAnyType($data, $allowedTypes)) {
                $errors[] = "{$path}: tipo invalido. esperado=" . implode('|', array_map('strval', $allowedTypes)) . " actual=" . $this->detectType($data);
                return;
            }
        }

        if (array_key_exists('const', $schema)) {
            if ($data != $schema['const']) {
                $errors[] = "{$path}: valor distinto de const";
            }
        }

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $matched = false;
            foreach ($schema['enum'] as $allowed) {
                if ($data == $allowed) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $errors[] = "{$path}: valor fuera de enum";
            }
        }

        if (is_string($data)) {
            if (isset($schema['minLength']) && is_numeric($schema['minLength']) && mb_strlen($data) < (int)$schema['minLength']) {
                $errors[] = "{$path}: longitud minima " . (int)$schema['minLength'] . " no cumplida";
            }
            if (isset($schema['maxLength']) && is_numeric($schema['maxLength']) && mb_strlen($data) > (int)$schema['maxLength']) {
                $errors[] = "{$path}: longitud maxima " . (int)$schema['maxLength'] . " excedida";
            }
            if (isset($schema['pattern']) && is_string($schema['pattern'])) {
                $pattern = '/' . str_replace('/', '\/', $schema['pattern']) . '/u';
                if (@preg_match($pattern, $data) !== 1) {
                    $errors[] = "{$path}: pattern no cumplido";
                }
            }
            if (isset($schema['format']) && $schema['format'] === 'date-time' && !$this->isValidDateTime($data)) {
                $errors[] = "{$path}: formato date-time invalido";
            }
        }

        if ((is_int($data) || is_float($data))) {
            if (isset($schema['minimum']) && is_numeric($schema['minimum']) && (float)$data < (float)$schema['minimum']) {
                $errors[] = "{$path}: valor menor que minimum";
            }
            if (isset($schema['maximum']) && is_numeric($schema['maximum']) && (float)$data > (float)$schema['maximum']) {
                $errors[] = "{$path}: valor mayor que maximum";
            }
        }

        if ($this->isArray($data)) {
            if (isset($schema['minItems']) && is_numeric($schema['minItems']) && count($data) < (int)$schema['minItems']) {
                $errors[] = "{$path}: minItems no cumplido";
            }
            if (isset($schema['maxItems']) && is_numeric($schema['maxItems']) && count($data) > (int)$schema['maxItems']) {
                $errors[] = "{$path}: maxItems excedido";
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($data as $idx => $item) {
                    $this->validateNode($item, $schema['items'], $path . '[' . $idx . ']', $errors);
                }
            }
        }

        if ($this->isObject($data)) {
            $properties = (isset($schema['properties']) && is_array($schema['properties'])) ? $schema['properties'] : [];
            $required = (isset($schema['required']) && is_array($schema['required'])) ? $schema['required'] : [];

            foreach ($required as $requiredKey) {
                $requiredKey = (string)$requiredKey;
                if (!array_key_exists($requiredKey, $data)) {
                    $errors[] = "{$path}: falta clave requerida '{$requiredKey}'";
                }
            }

            foreach ($data as $key => $value) {
                $key = (string)$key;
                if (array_key_exists($key, $properties) && is_array($properties[$key])) {
                    $this->validateNode($value, $properties[$key], $path . '.' . $key, $errors);
                    continue;
                }

                if (array_key_exists('additionalProperties', $schema)) {
                    $additional = $schema['additionalProperties'];
                    if ($additional === false) {
                        $errors[] = "{$path}: clave adicional no permitida '{$key}'";
                    } elseif (is_array($additional)) {
                        $this->validateNode($value, $additional, $path . '.' . $key, $errors);
                    }
                }
            }
        }
    }

    /**
     * @param mixed $data
     * @param array<string, mixed> $schema
     * @param array<int, string> $errors
     */
    private function validateComposition(mixed $data, array $schema, string $path, array &$errors): bool
    {
        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $idx => $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $this->validateNode($data, $sub, $path . '.allOf[' . $idx . ']', $errors);
            }
        }

        if (isset($schema['anyOf']) && is_array($schema['anyOf'])) {
            $match = false;
            $collected = [];
            foreach ($schema['anyOf'] as $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $localErrors = [];
                $this->validateNode($data, $sub, $path, $localErrors);
                if ($localErrors === []) {
                    $match = true;
                    break;
                }
                $collected[] = $localErrors;
            }
            if (!$match) {
                $errors[] = "{$path}: no cumple ningun esquema de anyOf";
                if (!empty($collected[0])) {
                    foreach ($collected[0] as $error) {
                        $errors[] = $error;
                    }
                }
                return false;
            }
        }

        if (isset($schema['oneOf']) && is_array($schema['oneOf'])) {
            $matches = 0;
            foreach ($schema['oneOf'] as $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $localErrors = [];
                $this->validateNode($data, $sub, $path, $localErrors);
                if ($localErrors === []) {
                    $matches++;
                }
            }
            if ($matches !== 1) {
                $errors[] = "{$path}: debe cumplir exactamente un esquema de oneOf";
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveRef(string $ref): ?array
    {
        if (!str_starts_with($ref, '#/')) {
            return null;
        }

        $segments = explode('/', substr($ref, 2));
        $node = $this->rootSchema;

        foreach ($segments as $rawSegment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $rawSegment);
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return is_array($node) ? $node : null;
    }

    /**
     * @param array<int, mixed> $allowedTypes
     * @param mixed $data
     */
    private function matchesAnyType(mixed $data, array $allowedTypes): bool
    {
        foreach ($allowedTypes as $type) {
            if ($this->matchesType((string)$type, $data)) {
                return true;
            }
        }
        return false;
    }

    private function matchesType(string $type, mixed $data): bool
    {
        return match ($type) {
            'object' => $this->isObject($data),
            'array' => $this->isArray($data),
            'string' => is_string($data),
            'integer' => is_int($data),
            'number' => is_int($data) || is_float($data),
            'boolean' => is_bool($data),
            'null' => $data === null,
            default => false,
        };
    }

    private function isObject(mixed $data): bool
    {
        return is_array($data) && !array_is_list($data);
    }

    private function isArray(mixed $data): bool
    {
        return is_array($data) && array_is_list($data);
    }

    private function detectType(mixed $data): string
    {
        if ($this->isObject($data)) {
            return 'object';
        }
        if ($this->isArray($data)) {
            return 'array';
        }
        if (is_string($data)) {
            return 'string';
        }
        if (is_int($data)) {
            return 'integer';
        }
        if (is_float($data)) {
            return 'number';
        }
        if (is_bool($data)) {
            return 'boolean';
        }
        if ($data === null) {
            return 'null';
        }
        return 'unknown';
    }

    private function isValidDateTime(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:\d{2})$/', $value)) {
            return false;
        }

        try {
            new DateTimeImmutable($value);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
