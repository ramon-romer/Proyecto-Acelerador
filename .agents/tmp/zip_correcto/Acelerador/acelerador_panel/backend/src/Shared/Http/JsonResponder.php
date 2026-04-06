<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Shared\Http;

class JsonResponder
{
    /**
     * @param mixed $data
     * @param array<string, mixed> $meta
     * @param array<string, mixed>|null $error
     */
    public static function respond(int $statusCode, $data, array $meta, ?array $error): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            [
                'data' => $data,
                'meta' => $meta,
                'error' => $error,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}

