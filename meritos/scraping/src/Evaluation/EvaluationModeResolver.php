<?php

require_once __DIR__ . '/EvaluationExecutionMode.php';

final class EvaluationModeResolver
{
    public const ENV_EXECUTION_MODE = 'ACELERADOR_EXECUTION_MODE';

    public static function resolveFromEnvironment(): string
    {
        $raw = getenv(self::ENV_EXECUTION_MODE);
        $value = is_string($raw) ? $raw : null;
        return self::resolveFromRaw($value);
    }

    public static function resolveFromRaw(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return EvaluationExecutionMode::LOCAL_ONLY;
        }

        $mode = strtolower(trim($raw));
        if (!in_array($mode, EvaluationExecutionMode::all(), true)) {
            throw new InvalidArgumentException(
                'Valor invalido para ' . self::ENV_EXECUTION_MODE . ': ' . $raw
                . '. Valores permitidos: ' . implode(', ', EvaluationExecutionMode::all()) . '.'
            );
        }

        if ($mode === EvaluationExecutionMode::MCP_ONLY) {
            return EvaluationExecutionMode::MCP;
        }

        return $mode;
    }
}
