<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\Matching;

final class McpMatchingAssistant
{
    private string $baseUrl;
    private int $timeoutMs;

    public function __construct(?string $baseUrl = null, ?int $timeoutMs = null)
    {
        $envBase = getenv('ACELERADOR_MCP_BASE_URL');
        $base = is_string($envBase) && trim($envBase) !== '' ? trim($envBase) : 'http://127.0.0.1:5000';
        if ($baseUrl !== null && trim($baseUrl) !== '') {
            $base = trim($baseUrl);
        }

        $this->baseUrl = rtrim($base, '/');

        $envTimeout = getenv('ACELERADOR_MCP_TIMEOUT_MS');
        $timeoutCandidate = is_string($envTimeout) && preg_match('/^\d+$/', $envTimeout) === 1
            ? (int)$envTimeout
            : 1200;
        if (is_int($timeoutMs) && $timeoutMs > 0) {
            $timeoutCandidate = $timeoutMs;
        }

        $this->timeoutMs = max(150, min(3000, $timeoutCandidate));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function enrich(array $payload): array
    {
        $probe = $this->probeAvailability();
        $candidateAnnotations = [];
        $globalWarnings = [];

        $candidates = is_array($payload['candidatos'] ?? null) ? $payload['candidatos'] : [];
        if (!empty($probe['ok'])) {
            foreach ($candidates as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }
                $profesorId = (int)($candidate['profesor_id'] ?? 0);
                if ($profesorId <= 0) {
                    continue;
                }

                $notes = [];
                $warnings = [];
                $orcid = isset($candidate['orcid']) && is_scalar($candidate['orcid'])
                    ? trim((string)$candidate['orcid'])
                    : '';
                if ($orcid !== '') {
                    $notes[] = 'MCP disponible para contraste adicional por ORCID (auxiliar, no vinculante).';
                } else {
                    $warnings[] = 'MCP sin ORCID no puede ampliar contraste externo para este candidato.';
                }

                $candidateAnnotations[$profesorId] = [
                    'motivos' => $notes,
                    'advertencias' => $warnings,
                    'score_mcp' => null,
                ];
            }
        } else {
            $reason = is_string($probe['reason'] ?? null) ? (string)$probe['reason'] : 'mcp_unavailable';
            $globalWarnings[] = 'mcp_auxiliar_no_disponible:' . $reason;
        }

        return [
            'mcp_intentado' => true,
            'mcp_disponible' => !empty($probe['ok']),
            'motivo_mcp' => $probe['reason'] ?? null,
            'candidate_annotations' => $candidateAnnotations,
            'global_warnings' => $globalWarnings,
        ];
    }

    /**
     * @return array{ok:bool,reason:string}
     */
    private function probeAvailability(): array
    {
        $url = $this->baseUrl . '/jobs/diagnostic_probe_job';
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeoutMs / 1000,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nConnection: close\r\n",
            ],
        ]);

        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });
        try {
            $raw = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($raw === false) {
            return [
                'ok' => false,
                'reason' => $this->mapReason($warning),
            ];
        }

        $statusCode = 0;
        if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d{3})/', (string)$http_response_header[0], $m) === 1) {
                $statusCode = (int)$m[1];
            }
        }

        if ($statusCode >= 200 && $statusCode < 500) {
            return ['ok' => true, 'reason' => 'mcp_available'];
        }

        return ['ok' => false, 'reason' => 'mcp_http_error'];
    }

    private function mapReason(?string $warning): string
    {
        if (!is_string($warning) || trim($warning) === '') {
            return 'mcp_unavailable';
        }

        $normalized = strtolower($warning);
        if (strpos($normalized, 'timed out') !== false || strpos($normalized, 'tiempo de espera') !== false) {
            return 'mcp_timeout';
        }
        if (strpos($normalized, 'connection refused') !== false || strpos($normalized, 'forcibly rejected') !== false) {
            return 'mcp_connection_refused';
        }

        return 'mcp_unavailable';
    }
}

