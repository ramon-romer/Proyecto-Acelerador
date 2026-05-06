<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\DTO;

final class MatchingRequestInput
{
    public const MODE_LOCAL_ONLY = 'local_only';
    public const MODE_MCP = 'mcp';
    // Alias legacy soportado por compatibilidad de pruebas/consumidores.
    public const MODE_MCP_ONLY = 'mcp_only';
    public const MODE_AUTO = 'auto';

    public function __construct(
        public readonly string $mode,
        public readonly int $limit,
        public readonly ?string $search,
        public readonly bool $includeAssigned,
        public readonly bool $includeTrace
    ) {
    }

    /**
     * @return array<int, string>
     */
    public static function allowedModes(): array
    {
        return [
            self::MODE_LOCAL_ONLY,
            self::MODE_MCP,
            self::MODE_MCP_ONLY,
            self::MODE_AUTO,
        ];
    }
}
