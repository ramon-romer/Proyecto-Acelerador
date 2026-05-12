<?php
declare(strict_types=1);

/**
 * Validacion automatica del contrato canonico ANECA (sin MCP) sobre salidas reales.
 *
 * Uso por defecto (desde raiz del repo):
 *   php evaluador/tests/validate_canonical_schema.php
 *
 * Opciones:
 *   --schema=<ruta_schema_json>
 *   --dir=<directorio_jsons>
 */

final class CanonicalSchemaValidationRunner
{
    private string $schemaPath;
    private string $jsonDir;

    public function __construct(string $schemaPath, string $jsonDir)
    {
        $this->schemaPath = $schemaPath;
        $this->jsonDir = $jsonDir;
    }

    public function run(): int
    {
        if (!is_file($this->schemaPath)) {
            fwrite(STDERR, "[ERROR] Schema no encontrado: {$this->schemaPath}" . PHP_EOL);
            return 2;
        }

        if (!is_dir($this->jsonDir)) {
            fwrite(STDERR, "[ERROR] Directorio de JSON no encontrado: {$this->jsonDir}" . PHP_EOL);
            return 2;
        }

        try {
            $schemaRaw = file_get_contents($this->schemaPath);
            if ($schemaRaw === false) {
                throw new RuntimeException("No se pudo leer el schema.");
            }
            $schema = json_decode($schemaRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            fwrite(STDERR, "[ERROR] Schema invalido: {$e->getMessage()}" . PHP_EOL);
            return 2;
        }

        if (!is_array($schema)) {
            fwrite(STDERR, "[ERROR] El schema debe ser un objeto JSON." . PHP_EOL);
            return 2;
        }

        $jsonFiles = glob($this->jsonDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($jsonFiles);

        if ($jsonFiles === []) {
            fwrite(STDERR, "[ERROR] No se encontraron JSON en: {$this->jsonDir}" . PHP_EOL);
            return 2;
        }

        fwrite(STDOUT, "Schema: {$this->schemaPath}" . PHP_EOL);
        fwrite(STDOUT, "Directorio: {$this->jsonDir}" . PHP_EOL);
        fwrite(STDOUT, "Archivos detectados: " . count($jsonFiles) . PHP_EOL . PHP_EOL);

        $validator = new MinimalJsonSchemaValidator($schema);
        $passed = 0;
        $failed = 0;
        $failedFiles = [];

        foreach ($jsonFiles as $file) {
            $baseName = basename($file);

            try {
                $raw = file_get_contents($file);
                if ($raw === false) {
                    throw new RuntimeException("No se pudo leer el archivo.");
                }
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                $failed++;
                $failedFiles[$baseName] = ["JSON invalido: {$e->getMessage()}"];
                fwrite(STDOUT, "[FAIL] {$baseName}" . PHP_EOL);
                continue;
            }

            $errors = $validator->validate($data);
            if ($errors === []) {
                $passed++;
                fwrite(STDOUT, "[PASS] {$baseName}" . PHP_EOL);
                continue;
            }

            $failed++;
            $failedFiles[$baseName] = $errors;
            fwrite(STDOUT, "[FAIL] {$baseName}" . PHP_EOL);
            foreach ($errors as $error) {
                fwrite(STDOUT, "  - {$error}" . PHP_EOL);
            }
        }

        fwrite(STDOUT, PHP_EOL . "=== Schema Validation Summary ===" . PHP_EOL);
        fwrite(STDOUT, "passed={$passed} failed={$failed} total=" . count($jsonFiles) . PHP_EOL);

        if ($failed > 0) {
            fwrite(STDOUT, "Archivos con incumplimientos:" . PHP_EOL);
            foreach ($failedFiles as $fileName => $errors) {
                fwrite(STDOUT, "- {$fileName} (" . count($errors) . " errores)" . PHP_EOL);
            }
            return 1;
        }

        return 0;
    }
}

final class MinimalJsonSchemaValidator
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
        if (isset($schema['$ref'])) {
            $resolved = $this->resolveRef((string)$schema['$ref']);
            if ($resolved === null) {
                $errors[] = "{$path}: referencia no resoluble {$schema['$ref']}";
                return;
            }
            $this->validateNode($data, $resolved, $path, $errors);
            return;
        }

        if (isset($schema['type'])) {
            $allowedTypes = is_array($schema['type']) ? $schema['type'] : [$schema['type']];
            if (!$this->matchesAnyType($data, $allowedTypes)) {
                $errors[] = "{$path}: tipo invalido. esperado=" . implode('|', $allowedTypes) . " actual=" . $this->detectType($data);
                return;
            }
        }

        if (isset($schema['minLength']) && is_string($data)) {
            $minLength = (int)$schema['minLength'];
            if (mb_strlen($data) < $minLength) {
                $errors[] = "{$path}: longitud minima {$minLength} no cumplida";
            }
        }

        if (isset($schema['format']) && is_string($data)) {
            $format = (string)$schema['format'];
            if ($format === 'date-time' && !$this->isValidDateTime($data)) {
                $errors[] = "{$path}: formato date-time invalido";
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

        if ($this->isArray($data) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($data as $idx => $item) {
                $this->validateNode($item, $schema['items'], $path . '[' . $idx . ']', $errors);
            }
        }
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

/**
 * @param array<int, string> $argv
 */
function parseArgs(array $argv): array
{
    $opts = getopt('', ['schema::', 'dir::']);
    $root = dirname(__DIR__, 2);

    $schema = isset($opts['schema']) && is_string($opts['schema']) && $opts['schema'] !== ''
        ? resolvePath($opts['schema'])
        : $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'contrato-canonico-aneca-v1.schema.json';

    $dir = isset($opts['dir']) && is_string($opts['dir']) && $opts['dir'] !== ''
        ? resolvePath($opts['dir'])
        : $root . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . 'json';

    return [$schema, $dir];
}

function resolvePath(string $path): string
{
    if (isAbsolutePath($path)) {
        return $path;
    }
    return getcwd() . DIRECTORY_SEPARATOR . $path;
}

function isAbsolutePath(string $path): bool
{
    return (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
        || str_starts_with($path, '\\\\')
        || str_starts_with($path, '/');
}

[$schemaPath, $jsonDir] = parseArgs($argv);
$runner = new CanonicalSchemaValidationRunner($schemaPath, $jsonDir);
exit($runner->run());

