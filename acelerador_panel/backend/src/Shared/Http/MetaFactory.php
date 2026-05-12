<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Shared\Http;

class MetaFactory
{
    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function build(string $requestId, array $extra = []): array
    {
        return array_merge(
            [
                'requestId' => $requestId,
                'timestamp' => date(DATE_ATOM),
            ],
            $extra
        );
    }
}

