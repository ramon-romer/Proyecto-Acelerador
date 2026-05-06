<?php

interface McpClientInterface
{
    public function getBaseUrl(): string;

    public function getTimeoutMs(): int;

    public function diagnoseAvailability(): array;

    public function getJob(string $jobId): McpHttpResponse;

    public function extractData(array $payload): McpHttpResponse;
}
