<?php
declare(strict_types=1);

namespace GenerarDocumentacion\DocumentBuilder;

/**
 * @return array{
 *   file_prefix: string,
 *   title: string,
 *   document: string,
 *   project: string,
 *   status: string,
 *   sections: array<int, string>,
 *   replace_sections: array<int, string>,
 *   signature: string
 * }
 */
function technicalDefinition(): array
{
    return [
        'file_prefix' => 'estado-tecnico',
        'title' => 'Estado técnico del día',
        'document' => 'Estado técnico del día',
        'project' => 'Acelerador',
        'status' => 'En progreso',
        'sections' => [
            '1. Resumen técnico de la jornada',
            '2. Módulos o áreas afectadas',
            '3. Cambios realizados',
            '4. Impacto en arquitectura o integración',
            '5. Dependencias relevantes',
            '6. Riesgos y pendientes',
            '7. Próximos pasos',
            '8. Validación y pruebas ejecutadas',
        ],
        'replace_sections' => [
            '8. Validación y pruebas ejecutadas',
        ],
        'signature' => 'Documento elaborado por %s como reflejo del estado técnico real del trabajo realizado en la fecha indicada.',
    ];
}

/**
 * @return array{
 *   file_prefix: string,
 *   title: string,
 *   document: string,
 *   project: string,
 *   status: string,
 *   sections: array<int, string>,
 *   replace_sections: array<int, string>,
 *   signature: string
 * }
 */
function dailyDefinition(): array
{
    return [
        'file_prefix' => 'registro-diario',
        'title' => 'Registro diario de trabajo',
        'document' => 'Registro diario',
        'project' => 'Acelerador',
        'status' => 'En progreso',
        'sections' => [
            '1. Resumen del día',
            '2. Trabajo realizado',
            '3. Decisiones técnicas',
            '4. Problemas encontrados',
            '5. Soluciones aplicadas',
            '6. Pendientes',
            '7. Siguiente paso',
            '8. Validación realizada',
        ],
        'replace_sections' => [
            '8. Validación realizada',
        ],
        'signature' => 'Registro elaborado por %s como constancia del trabajo técnico realizado durante la fecha indicada.',
    ];
}

/**
 * @param array<int, string> $sections
 * @return array{by_number: array<string, string>, by_alias: array<string, string>}
 */
function buildSectionMap(array $sections): array
{
    $byNumber = [];
    $byAlias = [];

    foreach ($sections as $index => $heading) {
        $number = (string) ($index + 1);
        $byNumber[$number] = $heading;

        $headingWithoutNumber = preg_replace('/^\d+\.\s*/u', '', $heading) ?? $heading;
        foreach ([$heading, $headingWithoutNumber] as $alias) {
            $normalizedAlias = normalizeSectionAlias($alias);
            if ($normalizedAlias !== '') {
                $byAlias[$normalizedAlias] = $heading;
            }
        }
    }

    return [
        'by_number' => $byNumber,
        'by_alias' => $byAlias,
    ];
}

/**
 * @param array<string, array<int, string>> $normalizedUpdates
 * @return array{
 *   meta: array{project: string, document: string, date: string, author: string, role: string, status: string},
 *   sections: array<string, array<int, string>>
 * }
 */
function createDocumentState(array $definition, string $date, string $author, string $role, array $normalizedUpdates): array
{
    $sections = [];
    foreach ($definition['sections'] as $heading) {
        $sections[$heading] = array_values($normalizedUpdates[$heading] ?? []);
    }

    return [
        'meta' => [
            'project' => $definition['project'],
            'document' => $definition['document'],
            'date' => $date,
            'author' => $author,
            'role' => $role,
            'status' => $definition['status'],
        ],
        'sections' => $sections,
    ];
}

function normalizeSectionAlias(string $alias): string
{
    $normalized = trim($alias);
    $normalized = preg_replace('/^##\s*/u', '', $normalized) ?? $normalized;
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    return toLower($normalized);
}

function toLower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}
