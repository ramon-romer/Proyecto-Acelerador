<?php

final class McpHttpResponse
{
    private $statusCode;
    private $headers;
    private $body;
    private $json;

    public function __construct(int $statusCode, array $headers, string $body, ?array $json)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->json = $json;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getJson(): ?array
    {
        return $this->json;
    }

    public function is2xx(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
