<?php

final class EvaluationExecutionMode
{
    public const LOCAL_ONLY = 'local_only';
    public const MCP = 'mcp';
    // Alias legacy soportado por compatibilidad interna.
    public const MCP_ONLY = 'mcp_only';
    public const AUTO = 'auto';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::LOCAL_ONLY,
            self::MCP,
            self::MCP_ONLY,
            self::AUTO,
        ];
    }
}
