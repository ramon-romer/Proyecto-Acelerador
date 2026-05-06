<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Persistence;

final class AnecaEvaluationSignalLookup
{
    private MysqliDatabase $db;
    private bool $capabilitiesResolved = false;
    private bool $tableAvailable = false;
    private bool $hasOrcidColumn = false;
    private bool $hasResultColumn = false;
    private bool $hasDateColumn = false;
    private bool $hasIdColumn = false;

    public function __construct(MysqliDatabase $db)
    {
        $this->db = $db;
    }

    /**
     * @return array{available:bool,count:int|null,latest_result:?string}
     */
    public function getSignalsByOrcid(?string $orcid): array
    {
        $this->resolveCapabilities();
        if (!$this->tableAvailable || !$this->hasOrcidColumn) {
            return [
                'available' => false,
                'count' => null,
                'latest_result' => null,
            ];
        }

        $normalizedOrcid = $this->normalizeOrcid($orcid);
        if ($normalizedOrcid === null) {
            return [
                'available' => true,
                'count' => 0,
                'latest_result' => null,
            ];
        }

        $countRow = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM evaluaciones WHERE UPPER(TRIM(orcid_candidato)) = ?',
            [$normalizedOrcid]
        ) ?? ['total' => 0];
        $count = (int)($countRow['total'] ?? 0);

        $latestResult = null;
        if ($count > 0 && $this->hasResultColumn) {
            $orderBy = $this->hasDateColumn
                ? 'fecha_creacion DESC'
                : ($this->hasIdColumn ? 'id DESC' : 'resultado DESC');

            $row = $this->db->fetchOne(
                "SELECT resultado FROM evaluaciones WHERE UPPER(TRIM(orcid_candidato)) = ? ORDER BY {$orderBy} LIMIT 1",
                [$normalizedOrcid]
            );
            if (is_array($row) && isset($row['resultado']) && is_scalar($row['resultado'])) {
                $latestResult = trim((string)$row['resultado']);
                if ($latestResult === '') {
                    $latestResult = null;
                }
            }
        }

        return [
            'available' => true,
            'count' => $count,
            'latest_result' => $latestResult,
        ];
    }

    /**
     * @return array{available:bool,has_orcid:bool}
     */
    public function getAvailability(): array
    {
        $this->resolveCapabilities();
        return [
            'available' => $this->tableAvailable,
            'has_orcid' => $this->hasOrcidColumn,
        ];
    }

    private function resolveCapabilities(): void
    {
        if ($this->capabilitiesResolved) {
            return;
        }

        $rows = $this->db->fetchAll(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'evaluaciones'"
        );

        $columns = [];
        foreach ($rows as $row) {
            $name = $row['COLUMN_NAME'] ?? null;
            if (is_string($name) && $name !== '') {
                $columns[] = strtolower($name);
            }
        }

        $this->tableAvailable = $columns !== [];
        $this->hasOrcidColumn = in_array('orcid_candidato', $columns, true);
        $this->hasResultColumn = in_array('resultado', $columns, true);
        $this->hasDateColumn = in_array('fecha_creacion', $columns, true);
        $this->hasIdColumn = in_array('id', $columns, true);
        $this->capabilitiesResolved = true;
    }

    private function normalizeOrcid(?string $orcid): ?string
    {
        if (!is_string($orcid)) {
            return null;
        }

        $normalized = strtoupper(trim($orcid));
        $normalized = preg_replace('/[^0-9X-]/', '', $normalized);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        return $normalized;
    }
}

