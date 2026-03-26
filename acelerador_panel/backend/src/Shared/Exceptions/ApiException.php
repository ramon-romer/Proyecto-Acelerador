<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Shared\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    private int $statusCode;
    private string $errorCode;
    /** @var array<int, mixed> */
    private array $details;

    /**
     * @param array<int, mixed> $details
     */
    public function __construct(
        int $statusCode,
        string $errorCode,
        string $message,
        array $details = []
    ) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<int, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}

