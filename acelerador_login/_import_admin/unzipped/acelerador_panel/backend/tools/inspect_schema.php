<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Autoload.php';

use Acelerador\PanelBackend\Infrastructure\Persistence\MysqliDatabase;
use Acelerador\PanelBackend\Infrastructure\Persistence\SchemaMap;

$dbConfig = require dirname(__DIR__) . '/config/database.php';
$schemaConfig = require dirname(__DIR__) . '/config/schema.php';

try {
    $db = new MysqliDatabase($dbConfig);
    $schema = new SchemaMap($schemaConfig, $db);

    echo "== Backend Tutorias: inspección de esquema ==\n";
    foreach (['tutoria', 'asignacion', 'profesor'] as $domain) {
        $table = $schema->table($domain);
        echo "\n[{$domain}] tabla: {$table}\n";
        $columns = $db->tableColumns($table);
        echo 'columnas detectadas: ' . implode(', ', $columns) . "\n";
    }

    echo "\nMapeos configurados:\n";
    foreach (($schemaConfig['columns'] ?? []) as $domain => $fields) {
        echo "- {$domain}\n";
        if (!is_array($fields)) {
            continue;
        }
        foreach ($fields as $field => $column) {
            $status = 'N/A';
            if (is_string($column) && $column !== '') {
                $status = $schema->hasPhysicalColumn((string) $domain, (string) $field) ? 'OK' : 'MISSING';
            }
            echo "  {$field} -> " . (string) $column . " ({$status})\n";
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}

