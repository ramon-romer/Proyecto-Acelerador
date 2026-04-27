<?php

/**
 * Resuelve de forma centralizada la salida preferente (ANECA vs legacy)
 * sin romper compatibilidad del campo `resultado`.
 */
final class PreferredResultResolver
{
    public const FORMAT_LEGACY = 'legacy';
    public const FORMAT_ANECA = 'aneca';

    public static function shouldPreferAneca(?string $requestValue): bool
    {
        if ($requestValue !== null) {
            $normalized = trim($requestValue);
            if ($normalized === '1') {
                return true;
            }
            if ($normalized === '0') {
                return false;
            }
        }

        return self::preferAnecaDefault();
    }

    public static function preferAnecaDefault(): bool
    {
        $raw = getenv('PREFER_ANECA_DEFAULT');
        if (!is_string($raw)) {
            return false;
        }

        $normalized = strtolower(trim($raw));
        return in_array($normalized, ['1', 'true', 'yes', 'on', 'si'], true);
    }

    /**
     * @param array<string,mixed> $legacyPayload
     * @param array<string,mixed>|null $anecaPayload
     * @return array{resultado_preferente_formato: string, resultado_preferente: array<string,mixed>}
     */
    public static function resolvePreferredResult(
        array $legacyPayload,
        bool $preferAneca,
        bool $anecaReady,
        ?array $anecaPayload
    ): array {
        $useAneca = $preferAneca && $anecaReady && is_array($anecaPayload);

        return [
            'resultado_preferente_formato' => $useAneca ? self::FORMAT_ANECA : self::FORMAT_LEGACY,
            'resultado_preferente' => $useAneca ? $anecaPayload : $legacyPayload,
        ];
    }
}

