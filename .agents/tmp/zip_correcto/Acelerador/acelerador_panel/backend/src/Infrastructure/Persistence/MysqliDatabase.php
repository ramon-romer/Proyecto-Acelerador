<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Persistence;

use Acelerador\PanelBackend\Shared\Exceptions\ApiException;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;

final class MysqliDatabase
{
    private mysqli $connection;
    /** @var array<string, array<int, string>> */
    private array $columnsCache = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $this->connection = new mysqli(
            (string) ($config['host'] ?? 'localhost'),
            (string) ($config['user'] ?? 'root'),
            (string) ($config['password'] ?? ''),
            (string) ($config['name'] ?? ''),
            (int) ($config['port'] ?? 3306)
        );

        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $this->connection->set_charset($charset);
    }

    public function connection(): mysqli
    {
        return $this->connection;
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $result = $this->executeQuery($sql, $params);
        if (!$result instanceof mysqli_result) {
            return [];
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * @param array<int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->executeQuery($sql, $params);
        if (!$result instanceof mysqli_result) {
            return null;
        }
        $row = $result->fetch_assoc();
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<int, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $this->executeQuery($sql, $params);
        return $this->connection->affected_rows;
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback)
    {
        $this->connection->begin_transaction();
        try {
            $result = $callback();
            $this->connection->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * @return array<int, string>
     */
    public function tableColumns(string $tableName): array
    {
        if (isset($this->columnsCache[$tableName])) {
            return $this->columnsCache[$tableName];
        }

        $table = SqlIdentifier::quote($tableName);
        $rows = $this->fetchAll("SHOW COLUMNS FROM {$table}");
        $columns = [];
        foreach ($rows as $row) {
            if (isset($row['Field']) && is_string($row['Field'])) {
                $columns[] = $row['Field'];
            }
        }
        $this->columnsCache[$tableName] = $columns;
        return $columns;
    }

    /**
     * @param array<int, mixed> $params
     * @return mysqli_result|bool
     */
    private function executeQuery(string $sql, array $params = [])
    {
        try {
            return $this->connection->execute_query($sql, $params);
        } catch (mysqli_sql_exception $e) {
            throw new ApiException(
                500,
                'DB_QUERY_ERROR',
                'Error de base de datos: ' . $e->getMessage()
            );
        }
    }
}

