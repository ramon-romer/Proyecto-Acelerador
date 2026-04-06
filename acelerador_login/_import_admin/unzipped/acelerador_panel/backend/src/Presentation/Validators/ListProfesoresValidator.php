<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Presentation\Validators;

use Acelerador\PanelBackend\Application\DTO\ListProfesoresInput;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class ListProfesoresValidator
{
    private int $defaultPageSize;
    private int $maxPageSize;

    public function __construct(int $defaultPageSize, int $maxPageSize)
    {
        $this->defaultPageSize = $defaultPageSize;
        $this->maxPageSize = $maxPageSize;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function validate(array $query): ListProfesoresInput
    {
        $pageRaw = $query['page'] ?? 1;
        $pageSizeRaw = $query['pageSize'] ?? $this->defaultPageSize;
        $searchRaw = $query['search'] ?? null;

        $page = $this->parseNaturalNumber($pageRaw, 'page');
        $pageSize = $this->parseNaturalNumber($pageSizeRaw, 'pageSize');
        if ($pageSize > $this->maxPageSize) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                "pageSize no puede ser mayor que {$this->maxPageSize}."
            );
        }

        $search = null;
        if (is_string($searchRaw)) {
            $search = trim($searchRaw);
            if ($search === '') {
                $search = null;
            }
        }

        return new ListProfesoresInput($page, $pageSize, $search);
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
            return (int) $value;
        }
        throw new ApiException(422, 'VALIDATION_ERROR', "{$field} debe ser un entero positivo.");
    }
}

