<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Persistence;

use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class SchemaMap
{
    /** @var array<string, mixed> */
    private array $schemaConfig;
    private MysqliDatabase $db;

    /**
     * @param array<string, mixed> $schemaConfig
     */
    public function __construct(array $schemaConfig, MysqliDatabase $db)
    {
        $this->schemaConfig = $schemaConfig;
        $this->db = $db;
    }

    public function table(string $domainName): string
    {
        $table = $this->schemaConfig['tables'][$domainName] ?? null;
        if (!is_string($table) || $table === '') {
            throw new ApiException(
                500,
                'SCHEMA_TABLE_MAPPING_MISSING',
                "No existe mapeo de tabla para '{$domainName}'."
            );
        }

        return $table;
    }

    public function requiredColumn(string $domainName, string $field): string
    {
        $column = $this->column($domainName, $field);
        if ($column === null || $column === '') {
            throw new ApiException(
                500,
                'SCHEMA_COLUMN_MAPPING_MISSING',
                "No existe mapeo de columna obligatorio {$domainName}.{$field}."
            );
        }

        return $column;
    }

    public function column(string $domainName, string $field): ?string
    {
        $column = $this->schemaConfig['columns'][$domainName][$field] ?? null;
        return is_string($column) && $column !== '' ? $column : null;
    }

    public function hasMappedColumn(string $domainName, string $field): bool
    {
        return $this->column($domainName, $field) !== null;
    }

    public function hasPhysicalColumn(string $domainName, string $field): bool
    {
        $column = $this->column($domainName, $field);
        if ($column === null) {
            return false;
        }

        $table = $this->table($domainName);
        $columns = $this->db->tableColumns($table);
        return in_array($column, $columns, true);
    }
}

