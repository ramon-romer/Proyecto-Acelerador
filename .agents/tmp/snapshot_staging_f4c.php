<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Europe/Madrid');
$dbName = 'acelerador_staging_20260406';
$timestamp = date('Ymd_His');
$runId = 'F4C_' . $timestamp;
$outDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'db';
$outDir = realpath($outDir) ?: $outDir;
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$outFile = $outDir . DIRECTORY_SEPARATOR . "snapshot_{$dbName}_{$timestamp}.sql";
$metaFile = $outDir . DIRECTORY_SEPARATOR . "snapshot_{$dbName}_{$timestamp}.meta.json";

$conn = new mysqli('localhost', 'root', '', $dbName);
$conn->set_charset('utf8mb4');

$conn->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$conn->query('START TRANSACTION');

$fh = fopen($outFile, 'wb');
if (!$fh) {
    throw new RuntimeException('No se pudo crear el archivo de snapshot.');
}

fwrite($fh, "-- Snapshot lógico generado automáticamente\n");
fwrite($fh, "-- Run ID: {$runId}\n");
fwrite($fh, "-- Database: {$dbName}\n");
fwrite($fh, "-- Generated at: " . date('c') . "\n\n");
fwrite($fh, "SET NAMES utf8mb4;\n");
fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = [];
$resTables = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
while ($row = $resTables->fetch_row()) {
    $tables[] = $row[0];
}
sort($tables);

$rowCounts = [];
foreach ($tables as $table) {
    $safeTable = str_replace('`', '``', $table);

    fwrite($fh, "-- ----------------------------\n");
    fwrite($fh, "-- Table structure for `{$safeTable}`\n");
    fwrite($fh, "-- ----------------------------\n");
    fwrite($fh, "DROP TABLE IF EXISTS `{$safeTable}`;\n");

    $resCreate = $conn->query("SHOW CREATE TABLE `{$safeTable}`");
    $rowCreate = $resCreate->fetch_assoc();
    $createSql = $rowCreate['Create Table'] ?? '';
    fwrite($fh, $createSql . ";\n\n");

    fwrite($fh, "-- ----------------------------\n");
    fwrite($fh, "-- Records of `{$safeTable}`\n");
    fwrite($fh, "-- ----------------------------\n");

    $resData = $conn->query("SELECT * FROM `{$safeTable}`");
    $fields = $resData->fetch_fields();
    $columns = array_map(static fn($f) => '`' . str_replace('`', '``', $f->name) . '`', $fields);
    $columnList = implode(', ', $columns);

    $count = 0;
    while ($dataRow = $resData->fetch_assoc()) {
        $values = [];
        foreach ($fields as $field) {
            $value = $dataRow[$field->name];
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $escaped = $conn->real_escape_string((string)$value);
                $values[] = "'{$escaped}'";
            }
        }
        $valuesSql = implode(', ', $values);
        fwrite($fh, "INSERT INTO `{$safeTable}` ({$columnList}) VALUES ({$valuesSql});\n");
        $count++;
    }
    fwrite($fh, "\n");
    $rowCounts[$table] = $count;
}

fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fh);

$conn->query('COMMIT');

$sha256 = hash_file('sha256', $outFile);
$meta = [
    'run_id' => $runId,
    'database' => $dbName,
    'generated_at' => date('c'),
    'snapshot_file' => $outFile,
    'sha256' => $sha256,
    'bytes' => filesize($outFile),
    'tables' => $tables,
    'row_counts' => $rowCounts,
];

file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
