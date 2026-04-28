<?php

require_once __DIR__ . '/LegacyPipelineResultValidator.php';
require_once __DIR__ . '/AnecaCanonicalResultValidator.php';

/**
 * Resuelve de forma coherente la decision operativa ANECA-first con fallback legacy.
 *
 * Uso:
 * - Service y Worker pueden compartir exactamente el mismo criterio de cacheabilidad/uso.
 * - Mantiene degradacion segura a legacy cuando ANECA no esta lista o no es utilizable.
 */
final class OperationalArtifactDecisionResolver
{
    /**
     * @param array<string,mixed> $artifactContext
     * @param array<string,mixed> $legacyValidation
     * @return array<string,mixed>
     */
    public static function decide(array $artifactContext, array $legacyValidation): array
    {
        $legacyStatus = self::safeString($legacyValidation['validation_status'] ?? null)
            ?? LegacyPipelineResultValidator::STATUS_INVALIDO;
        $legacyCacheable = in_array(
            $legacyStatus,
            [
                LegacyPipelineResultValidator::STATUS_VALIDO,
                LegacyPipelineResultValidator::STATUS_VALIDO_CON_ADVERTENCIAS,
            ],
            true
        );
        $legacyClean = $legacyStatus === LegacyPipelineResultValidator::STATUS_VALIDO;

        $declaredPrimaryFormat = self::safeString($artifactContext['resultado_principal_formato'] ?? null);
        if (!in_array($declaredPrimaryFormat, ['aneca', 'legacy'], true)) {
            $declaredPrimaryFormat = null;
        }

        $declaredPrimaryPath = self::safeString($artifactContext['resultado_principal_path'] ?? null);
        $anecaPath = self::safeString($artifactContext['aneca_canonical_path'] ?? null);
        $anecaReady = !empty($artifactContext['aneca_canonical_ready']);
        $anecaStatus = self::safeString($artifactContext['aneca_canonical_validation_status'] ?? null);
        $anecaPathReady = $anecaPath !== null && is_file($anecaPath);
        $anecaUsable = $anecaReady
            && $anecaPathReady
            && self::isAnecaStatusUsable($anecaStatus);

        if ($declaredPrimaryFormat === null) {
            $declaredPrimaryFormat = ($anecaReady && $anecaPathReady) ? 'aneca' : 'legacy';
        }
        if ($declaredPrimaryPath === null && $declaredPrimaryFormat === 'aneca') {
            $declaredPrimaryPath = $anecaPath;
        }

        if ($anecaUsable && ($declaredPrimaryFormat === 'aneca' || ($declaredPrimaryFormat === 'legacy' && $anecaReady))) {
            return [
                'criterion' => 'aneca_operativo',
                'artifact_format' => 'aneca',
                'artifact_path' => $anecaPath,
                'cacheable' => true,
                'is_clean' => self::isAnecaStatusClean($anecaStatus),
                'fallback_reason' => null,
                'invalidation_reason' => null,
                'declared_primary_format' => $declaredPrimaryFormat,
                'declared_primary_path' => $declaredPrimaryPath,
                'legacy_validation_status' => $legacyStatus,
                'aneca_validation_status' => $anecaStatus,
            ];
        }

        $fallbackReason = 'legacy_sin_aneca';
        if (!$anecaReady) {
            $fallbackReason = 'aneca_not_ready';
        } elseif (!$anecaPathReady) {
            $fallbackReason = 'aneca_path_inexistente';
        } elseif (!self::isAnecaStatusUsable($anecaStatus)) {
            $fallbackReason = 'aneca_status_no_utilizable_' . ($anecaStatus ?? 'desconocido');
        } elseif ($declaredPrimaryFormat === 'legacy') {
            $fallbackReason = 'principal_declara_legacy';
        }

        return [
            'criterion' => 'legacy_fallback',
            'artifact_format' => 'legacy',
            'artifact_path' => $declaredPrimaryFormat === 'legacy' ? $declaredPrimaryPath : null,
            'cacheable' => $legacyCacheable,
            'is_clean' => $legacyClean,
            'fallback_reason' => $fallbackReason,
            'invalidation_reason' => $legacyCacheable ? null : 'legacy_validation_status_' . $legacyStatus,
            'declared_primary_format' => $declaredPrimaryFormat,
            'declared_primary_path' => $declaredPrimaryPath,
            'legacy_validation_status' => $legacyStatus,
            'aneca_validation_status' => $anecaStatus,
        ];
    }

    /**
     * @param array<string,mixed> $decision
     */
    public static function describe(array $decision): string
    {
        $parts = [
            'criterio=' . (string)($decision['criterion'] ?? 'legacy_fallback'),
            'formato=' . (string)($decision['artifact_format'] ?? 'legacy'),
            'cacheable=' . (!empty($decision['cacheable']) ? 'true' : 'false'),
            'clean=' . (!empty($decision['is_clean']) ? 'true' : 'false'),
            'legacy_status=' . (string)($decision['legacy_validation_status'] ?? ''),
            'aneca_status=' . (string)($decision['aneca_validation_status'] ?? ''),
            'principal_declarado=' . (string)($decision['declared_primary_format'] ?? ''),
        ];

        $fallbackReason = self::safeString($decision['fallback_reason'] ?? null);
        if ($fallbackReason !== null) {
            $parts[] = 'fallback=' . $fallbackReason;
        }

        $invalidationReason = self::safeString($decision['invalidation_reason'] ?? null);
        if ($invalidationReason !== null) {
            $parts[] = 'motivo=' . $invalidationReason;
        }

        return implode(' ', $parts);
    }

    private static function isAnecaStatusUsable(?string $status): bool
    {
        return in_array(
            $status,
            [
                AnecaCanonicalResultValidator::STATUS_VALIDO,
                AnecaCanonicalResultValidator::STATUS_VALIDO_CON_ADVERTENCIAS,
                AnecaCanonicalResultValidator::STATUS_INCOMPLETO,
            ],
            true
        );
    }

    private static function isAnecaStatusClean(?string $status): bool
    {
        return $status === AnecaCanonicalResultValidator::STATUS_VALIDO;
    }

    private static function safeString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
