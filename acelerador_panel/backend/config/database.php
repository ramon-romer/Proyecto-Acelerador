<?php
declare(strict_types=1);

$readEnv = static function (array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        $value = getenv($key);
        if (!is_string($value)) {
            continue;
        }

        $value = trim($value);
        if ($value !== '') {
            return $value;
        }
    }

    return $default;
};

$portRaw = $readEnv(['DB_PORT', 'ACELERADOR_DB_PORT'], '3306');
$port = (int) $portRaw;
if ($port <= 0) {
    $port = 3306;
}

$dbNameFromEnv = $readEnv(['DB_NAME', 'ACELERADOR_DB_NAME'], '');
$nameCandidates = [];
foreach ([$dbNameFromEnv, 'Acelerador', 'acelerador_staging_20260406', 'acelerador'] as $candidate) {
    if (!is_string($candidate)) {
        continue;
    }

    $candidate = trim($candidate);
    if ($candidate === '' || in_array($candidate, $nameCandidates, true)) {
        continue;
    }

    $nameCandidates[] = $candidate;
}

if ($nameCandidates === []) {
    $nameCandidates[] = 'Acelerador';
}

return [
    'host' => $readEnv(['DB_HOST', 'ACELERADOR_DB_HOST'], 'localhost'),
    'user' => $readEnv(['DB_USER', 'ACELERADOR_DB_USER'], 'root'),
    'password' => $readEnv(['DB_PASS', 'ACELERADOR_DB_PASS'], ''),
    'name' => $nameCandidates[0],
    'nameCandidates' => $nameCandidates,
    'port' => $port,
    'charset' => $readEnv(['DB_CHARSET', 'ACELERADOR_DB_CHARSET'], 'utf8mb4'),
];

