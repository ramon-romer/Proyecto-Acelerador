<?php

/**
 * Resuelve el artefacto interno principal (legacy o ANECA canonico)
 * para modelo de job/cache sin romper compatibilidad de resultado_json.
 */
final class InternalPreferredArtifactResolver
{
    public const FORMAT_LEGACY = 'legacy';
    public const FORMAT_ANECA = 'aneca';

    /**
     * @return array{resultado_principal_formato:string,resultado_principal_path:?string}
     */
    public static function resolvePreferredArtifact(
        ?string $legacyPath,
        ?string $anecaCanonicalPath,
        bool $anecaCanonicalReady
    ): array {
        $legacyPath = self::normalizePath($legacyPath);
        $anecaCanonicalPath = self::normalizePath($anecaCanonicalPath);

        $useAneca = $anecaCanonicalReady
            && is_string($anecaCanonicalPath)
            && $anecaCanonicalPath !== ''
            && is_file($anecaCanonicalPath);

        return [
            'resultado_principal_formato' => $useAneca ? self::FORMAT_ANECA : self::FORMAT_LEGACY,
            'resultado_principal_path' => $useAneca ? $anecaCanonicalPath : $legacyPath,
        ];
    }

    private static function normalizePath(?string $path): ?string
    {
        if (!is_string($path)) {
            return null;
        }

        $trimmed = trim($path);
        return $trimmed === '' ? null : $trimmed;
    }
}
