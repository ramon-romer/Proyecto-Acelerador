<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Shared\Http;

class Request
{
    private string $method;
    private string $path;
    /** @var array<string, string> */
    private array $headers;
    /** @var array<string, mixed> */
    private array $queryParams;
    private string $rawBody;
    /** @var array<string, mixed>|null */
    private ?array $jsonBody = null;
    private bool $jsonParsed = false;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        string $method,
        string $path,
        array $headers,
        array $queryParams,
        string $rawBody
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->headers = $headers;
        $this->queryParams = $queryParams;
        $this->rawBody = $rawBody;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($scriptDir !== '' && $scriptDir !== '.' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
            if ($path === '') {
                $path = '/';
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        $rawBody = file_get_contents('php://input') ?: '';

        return new self($method, $path, $headers, $_GET, $rawBody);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParams(): array
    {
        return $this->queryParams;
    }

    public function queryInt(string $key, int $default): int
    {
        $value = $this->queryParams[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }

    public function queryString(string $key, ?string $default = null): ?string
    {
        $value = $this->queryParams[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return is_scalar($value) ? (string) $value : $default;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonBody(): array
    {
        if ($this->jsonParsed) {
            return $this->jsonBody ?? [];
        }

        $this->jsonParsed = true;
        $body = trim($this->rawBody);
        if ($body === '') {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $this->jsonBody = $decoded;
        return $this->jsonBody;
    }
}

