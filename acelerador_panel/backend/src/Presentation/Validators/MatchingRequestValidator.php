<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Presentation\Validators;

use Acelerador\PanelBackend\Application\DTO\MatchingRequestInput;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class MatchingRequestValidator
{
    private int $defaultLimit;
    private int $maxLimit;

    public function __construct(int $defaultLimit = 20, int $maxLimit = 100)
    {
        $this->defaultLimit = $defaultLimit;
        $this->maxLimit = $maxLimit;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function validate(array $query): MatchingRequestInput
    {
        $modeRaw = $query['mode'] ?? MatchingRequestInput::MODE_AUTO;
        $mode = is_scalar($modeRaw) ? strtolower(trim((string)$modeRaw)) : MatchingRequestInput::MODE_AUTO;
        if (!in_array($mode, MatchingRequestInput::allowedModes(), true)) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                'mode invalido. Valores permitidos: ' . implode(', ', MatchingRequestInput::allowedModes()) . '.'
            );
        }
        if ($mode === MatchingRequestInput::MODE_MCP_ONLY) {
            $mode = MatchingRequestInput::MODE_MCP;
        }

        $limit = $this->parseNaturalNumber($query['limit'] ?? $this->defaultLimit, 'limit');
        if ($limit > $this->maxLimit) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                "limit no puede ser mayor que {$this->maxLimit}."
            );
        }

        $search = null;
        if (isset($query['search']) && is_scalar($query['search'])) {
            $search = trim((string)$query['search']);
            if ($search === '') {
                $search = null;
            }
        }

        return new MatchingRequestInput(
            $mode,
            $limit,
            $search,
            $this->toBool($query['includeAssigned'] ?? null),
            $this->toBool($query['includeTrace'] ?? null)
        );
    }

    /**
     * @param mixed $value
     */
    private function parseNaturalNumber($value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1) {
            return (int)$value;
        }

        throw new ApiException(422, 'VALIDATION_ERROR', "{$field} debe ser un entero positivo.");
    }

    /**
     * @param mixed $value
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }
        return false;
    }
}
