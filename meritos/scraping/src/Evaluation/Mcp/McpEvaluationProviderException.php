<?php

class McpEvaluationProviderException extends RuntimeException
{
    private $reason;
    private $context;

    public function __construct(
        string $reason,
        string $message,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->reason = $reason;
        $this->context = $context;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
