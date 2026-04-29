<?php
declare(strict_types=1);

function aneca_normalize_orcid(mixed $orcid): ?string
{
    if (!is_scalar($orcid)) {
        return null;
    }

    $normalized = strtoupper((string)$orcid);
    $normalized = preg_replace('/[^0-9X-]/', '', trim($normalized));

    return $normalized !== '' ? $normalized : null;
}

function aneca_candidate_orcid_from_payload(array $payload): ?string
{
    foreach (['orcid_candidato', 'orcid', 'ORCID'] as $key) {
        if (array_key_exists($key, $payload)) {
            $orcid = aneca_normalize_orcid($payload[$key]);
            if ($orcid !== null) {
                return $orcid;
            }
        }
    }

    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        session_start();
    }

    return aneca_normalize_orcid($_SESSION['orcid_usuario'] ?? null);
}

function aneca_attach_candidate_orcid(array &$payload): ?string
{
    $orcid = aneca_candidate_orcid_from_payload($payload);
    if ($orcid !== null) {
        $payload['orcid_candidato'] = $orcid;
    }

    return $orcid;
}

function aneca_pdo_table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}
