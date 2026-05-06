<?php

require_once __DIR__ . '/McpClientInterface.php';
require_once __DIR__ . '/McpClientException.php';
require_once __DIR__ . '/McpHttpResponse.php';

final class McpHttpClientInternalV1 implements McpClientInterface
{
    public const DEFAULT_BASE_URL = 'http://127.0.0.1:5000';
    public const DEFAULT_TIMEOUT_MS = 5000;

    private $baseUrl;
    private $timeoutMs;

    public function __construct(?string $baseUrl = null, int $timeoutMs = self::DEFAULT_TIMEOUT_MS)
    {
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl ?? self::DEFAULT_BASE_URL);
        $this->timeoutMs = $this->normalizeTimeoutMs($timeoutMs);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getTimeoutMs(): int
    {
        return $this->timeoutMs;
    }

    public function diagnoseAvailability(): array
    {
        $probeJobId = 'diagnostic_probe_job';

        try {
            $response = $this->getJob($probeJobId);
            $status = $response->getStatusCode();
            $available = $status >= 200 && $status < 500;

            return [
                'ok' => $available,
                'reason' => $available ? 'available' : 'mcp_http_error',
                'base_url' => $this->baseUrl,
                'timeout_ms' => $this->timeoutMs,
                'probe' => [
                    'method' => 'GET',
                    'path' => '/jobs/{id}',
                    'job_id' => $probeJobId,
                ],
                'http_status' => $status,
            ];
        } catch (McpClientException $e) {
            return [
                'ok' => false,
                'reason' => $e->getReason(),
                'base_url' => $this->baseUrl,
                'timeout_ms' => $this->timeoutMs,
                'probe' => [
                    'method' => 'GET',
                    'path' => '/jobs/{id}',
                    'job_id' => $probeJobId,
                ],
                'message' => $e->getMessage(),
                'error_context' => $e->getContext(),
            ];
        }
    }

    public function getJob(string $jobId): McpHttpResponse
    {
        $jobId = trim($jobId);
        if ($jobId === '' || preg_match('/^[a-zA-Z0-9_-]+$/', $jobId) !== 1) {
            throw new McpClientException(
                'mcp_invalid_request',
                'jobId invalido para GET /jobs/{id}.'
            );
        }

        return $this->sendJsonRequest('GET', '/jobs/' . rawurlencode($jobId), null);
    }

    public function extractData(array $payload): McpHttpResponse
    {
        return $this->sendJsonRequest('POST', '/extract-data', $payload);
    }

    private function sendJsonRequest(string $method, string $path, ?array $payload): McpHttpResponse
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Accept: application/json',
            'Connection: close',
        ];

        $content = '';
        if ($payload !== null) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json)) {
                throw new McpClientException('mcp_invalid_request', 'No se pudo serializar payload JSON para MCP.');
            }

            $content = $json;
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            $headers[] = 'Content-Length: ' . strlen($content);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => $this->timeoutMs / 1000,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $content,
            ],
        ]);

        $warningMessage = null;
        $previousHandler = set_error_handler(
            function (int $severity, string $message) use (&$warningMessage): bool {
                $warningMessage = $message;
                return true;
            }
        );

        try {
            $bodyRaw = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($bodyRaw === false) {
            $reason = $this->mapNetworkErrorReason($warningMessage);
            throw new McpClientException(
                $reason,
                'No se pudo conectar con MCP internal_http_v1.',
                [
                    'url' => $url,
                    'warning' => $warningMessage,
                ]
            );
        }

        $statusCode = $this->extractHttpStatusCode(isset($http_response_header) && is_array($http_response_header) ? $http_response_header : []);
        $responseHeaders = $this->extractResponseHeaders(isset($http_response_header) && is_array($http_response_header) ? $http_response_header : []);
        $body = (string)$bodyRaw;

        if (trim($body) === '') {
            throw new McpClientException(
                'mcp_empty_response',
                'MCP internal_http_v1 devolvio una respuesta vacia.',
                [
                    'url' => $url,
                    'http_status' => $statusCode,
                ]
            );
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new McpClientException(
                'mcp_invalid_json',
                'MCP internal_http_v1 devolvio JSON invalido o no objeto JSON.',
                [
                    'url' => $url,
                    'http_status' => $statusCode,
                    'body_preview' => $this->safePreview($body, 240),
                ]
            );
        }

        return new McpHttpResponse($statusCode, $responseHeaders, $body, $decoded);
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $normalized = rtrim(trim($baseUrl), '/');
        if ($normalized === '') {
            throw new InvalidArgumentException('baseUrl MCP no puede estar vacia.');
        }

        $parts = parse_url($normalized);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('baseUrl MCP invalida: ' . $baseUrl);
        }

        $scheme = strtolower((string)$parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('baseUrl MCP debe usar http o https.');
        }

        return $normalized;
    }

    private function normalizeTimeoutMs(int $timeoutMs): int
    {
        if ($timeoutMs < 100) {
            return 100;
        }
        if ($timeoutMs > 120000) {
            return 120000;
        }
        return $timeoutMs;
    }

    private function extractHttpStatusCode(array $rawHeaders): int
    {
        if (empty($rawHeaders)) {
            return 0;
        }

        $first = (string)$rawHeaders[0];
        if (preg_match('/^HTTP\/\d\.\d\s+(\d{3})/', $first, $m) === 1) {
            return (int)$m[1];
        }

        return 0;
    }

    private function extractResponseHeaders(array $rawHeaders): array
    {
        $headers = [];
        foreach ($rawHeaders as $line) {
            if (!is_string($line)) {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }

            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            if ($name === '') {
                continue;
            }
            $headers[$name] = $value;
        }

        return $headers;
    }

    private function mapNetworkErrorReason(?string $warningMessage): string
    {
        if (!is_string($warningMessage) || trim($warningMessage) === '') {
            return 'mcp_unavailable';
        }

        $text = strtolower($warningMessage);
        if (strpos($text, 'connection refused') !== false || strpos($text, 'forcibly rejected') !== false) {
            return 'mcp_connection_refused';
        }
        if (strpos($text, 'timed out') !== false || strpos($text, 'tiempo de espera') !== false) {
            return 'mcp_timeout';
        }

        return 'mcp_unavailable';
    }

    private function safePreview(string $value, int $maxLen): string
    {
        if (function_exists('mb_substr')) {
            return (string)mb_substr($value, 0, $maxLen, 'UTF-8');
        }

        return substr($value, 0, $maxLen);
    }
}
