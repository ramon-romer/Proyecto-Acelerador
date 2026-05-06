<?php

final class McpInternalHttpV1NormalizationResult
{
    private $canProduceRuntimeResult;
    private $compatibilityReasons;
    private $responseKind;
    private $normalizedPayload;
    private $missingLegacyRequiredFields;
    private $missingRuntimeRequiredFields;
    private $missingBusinessFields;
    private $notes;

    public function __construct(
        bool $canProduceRuntimeResult,
        array $compatibilityReasons,
        string $responseKind,
        array $normalizedPayload,
        array $missingLegacyRequiredFields,
        array $missingRuntimeRequiredFields,
        array $missingBusinessFields,
        array $notes = []
    ) {
        $this->canProduceRuntimeResult = $canProduceRuntimeResult;
        $this->compatibilityReasons = $this->normalizeStringList($compatibilityReasons);
        $this->responseKind = $responseKind;
        $this->normalizedPayload = $normalizedPayload;
        $this->missingLegacyRequiredFields = $this->normalizeStringList($missingLegacyRequiredFields);
        $this->missingRuntimeRequiredFields = $this->normalizeStringList($missingRuntimeRequiredFields);
        $this->missingBusinessFields = $this->normalizeStringList($missingBusinessFields);
        $this->notes = $this->normalizeStringList($notes);
    }

    public function canProduceRuntimeResult(): bool
    {
        return $this->canProduceRuntimeResult;
    }

    public function getCompatibilityReasons(): array
    {
        return $this->compatibilityReasons;
    }

    public function getResponseKind(): string
    {
        return $this->responseKind;
    }

    public function getNormalizedPayload(): array
    {
        return $this->normalizedPayload;
    }

    public function toArray(): array
    {
        return [
            'can_produce_runtime_result' => $this->canProduceRuntimeResult,
            'compatibility_reasons' => $this->compatibilityReasons,
            'response_kind' => $this->responseKind,
            'normalized_payload' => $this->normalizedPayload,
            'missing_legacy_required_fields' => $this->missingLegacyRequiredFields,
            'missing_runtime_required_fields' => $this->missingRuntimeRequiredFields,
            'missing_business_fields' => $this->missingBusinessFields,
            'notes' => $this->notes,
        ];
    }

    private function normalizeStringList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed === '') {
                continue;
            }

            $out[] = $trimmed;
        }

        return array_values(array_unique($out));
    }
}
