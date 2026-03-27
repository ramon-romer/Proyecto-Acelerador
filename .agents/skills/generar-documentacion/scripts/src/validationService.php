<?php
declare(strict_types=1);

namespace GenerarDocumentacion\ValidationService;

use function GenerarDocumentacion\MergeService\parseExistingDocument;

/**
 * @param array{title: string, sections: array<int, string>} $definition
 * @return array{
 *   suiteName: string,
 *   total: ?int,
 *   passed: ?int,
 *   failed: ?int,
 *   errors: string,
 *   summary: string,
 *   timestamp: string,
 *   observations: string
 * }|null
 */
function getLatestValidationSnapshot(?string $existingContent, array $definition): ?array
{
    if ($existingContent === null || trim($existingContent) === '') {
        return null;
    }

    try {
        $parsed = parseExistingDocument($existingContent, $definition);
    } catch (\Throwable $exception) {
        return null;
    }

    $validationHeading = end($definition['sections']);
    if (!is_string($validationHeading) || !isset($parsed['sections'][$validationHeading])) {
        return null;
    }

    return parseValidationItems($parsed['sections'][$validationHeading]);
}

/**
 * @param array{
 *   executed: bool,
 *   suiteName: string,
 *   total: ?int,
 *   passed: ?int,
 *   failed: ?int,
 *   errors: array<int, string>,
 *   summary: string,
 *   timestamp: string,
 *   observations: string
 * } $testResult
 * @param array{
 *   suiteName: string,
 *   total: ?int,
 *   passed: ?int,
 *   failed: ?int,
 *   errors: string,
 *   summary: string,
 *   timestamp: string,
 *   observations: string
 * }|null $previousSnapshot
 * @return array<int, string>
 */
function updateValidationSection(array $testResult, ?array $previousSnapshot, string $documentType): array
{
    if ($testResult['executed']) {
        return buildExecutedValidationItems($testResult, $documentType);
    }

    if ($previousSnapshot !== null) {
        return buildSkippedWithPreviousValidationItems($previousSnapshot, $documentType);
    }

    return ['No se han realizado tests en esta ejecución.'];
}

/**
 * @param array{
 *   executed: bool,
 *   suiteName: string,
 *   total: ?int,
 *   passed: ?int,
 *   failed: ?int,
 *   errors: array<int, string>,
 *   summary: string,
 *   timestamp: string,
 *   observations: string
 * } $testResult
 * @return array<int, string>
 */
function buildExecutedValidationItems(array $testResult, string $documentType): array
{
    $items = [];
    if ($documentType === 'daily') {
        $items[] = 'Tras la generación de la documentación se ejecutó la batería de tests.';
    } else {
        $items[] = 'Batería de tests ejecutada: sí';
    }

    $items[] = 'Batería/identificador: ' . fallback($testResult['suiteName'], 'ejecutar-tests');
    $items[] = 'Última validación registrada del día: ' . fallback($testResult['timestamp'], date('Y-m-d H:i:s'));
    $items[] = 'Resultado general: ' . fallback($testResult['summary'], 'Sin resumen.');

    if ($testResult['total'] !== null) {
        $items[] = 'Total de pruebas: ' . $testResult['total'];
    }
    if ($testResult['passed'] !== null) {
        $items[] = 'Superadas: ' . $testResult['passed'];
    }
    if ($testResult['failed'] !== null) {
        $items[] = 'Fallidas: ' . $testResult['failed'];
    }

    $errors = normalizeErrorSummary($testResult['errors']);
    $items[] = 'Errores relevantes: ' . ($errors === '' ? 'Sin errores relevantes reportados.' : $errors);

    $observations = trim((string) $testResult['observations']);
    $items[] = 'Observaciones: ' . ($observations === '' ? 'Sin observaciones adicionales.' : $observations);

    return $items;
}

/**
 * @param array{
 *   suiteName: string,
 *   total: ?int,
 *   passed: ?int,
 *   failed: ?int,
 *   errors: string,
 *   summary: string,
 *   timestamp: string,
 *   observations: string
 * } $previousSnapshot
 * @return array<int, string>
 */
function buildSkippedWithPreviousValidationItems(array $previousSnapshot, string $documentType): array
{
    $items = ['No se han realizado tests en esta ejecución.'];
    if ($documentType === 'daily') {
        $items[] = 'Última validación disponible del día: ' . fallback($previousSnapshot['timestamp'], 'Sin marca temporal');
    } else {
        $items[] = 'Última validación registrada del día: ' . fallback($previousSnapshot['timestamp'], 'Sin marca temporal');
    }

    $items[] = 'Batería/identificador: ' . fallback($previousSnapshot['suiteName'], 'ejecutar-tests');
    $items[] = 'Resultado general: ' . fallback($previousSnapshot['summary'], 'Sin resumen.');

    if ($previousSnapshot['total'] !== null) {
        $items[] = 'Total de pruebas: ' . $previousSnapshot['total'];
    }
    if ($previousSnapshot['passed'] !== null) {
        $items[] = 'Superadas: ' . $previousSnapshot['passed'];
    }
    if ($previousSnapshot['failed'] !== null) {
        $items[] = 'Fallidas: ' . $previousSnapshot['failed'];
    }

    $items[] = 'Errores relevantes: ' . fallback($previousSnapshot['errors'], 'Sin errores relevantes reportados.');
    $items[] = 'Observaciones: ' . fallback($previousSnapshot['observations'], 'Sin observaciones adicionales.');

    return $items;
}

/**
 * @param array<int, string> $items
 * @return array{
 *   suiteName: string,
 *   total: ?int,
 *   passed: ?int,
 *   failed: ?int,
 *   errors: string,
 *   summary: string,
 *   timestamp: string,
 *   observations: string
 * }|null
 */
function parseValidationItems(array $items): ?array
{
    $snapshot = [
        'suiteName' => '',
        'total' => null,
        'passed' => null,
        'failed' => null,
        'errors' => '',
        'summary' => '',
        'timestamp' => '',
        'observations' => '',
    ];

    $hasData = false;
    foreach ($items as $rawLine) {
        $line = trim((string) $rawLine);
        if ($line === '') {
            continue;
        }

        if (($value = valueFromPrefix($line, 'Batería/identificador:')) !== null) {
            $snapshot['suiteName'] = $value;
            $hasData = true;
            continue;
        }
        if (($value = valueFromPrefix($line, 'Bateria/identificador:')) !== null) {
            $snapshot['suiteName'] = $value;
            $hasData = true;
            continue;
        }
        if (($value = valueFromPrefix($line, 'Resultado general:')) !== null) {
            $snapshot['summary'] = $value;
            $hasData = true;
            continue;
        }
        if (($value = valueFromPrefix($line, 'Total de pruebas:')) !== null) {
            $snapshot['total'] = parseNullableInt($value);
            $hasData = true;
            continue;
        }
        if (($value = valueFromPrefix($line, 'Superadas:')) !== null) {
            $snapshot['passed'] = parseNullableInt($value);
            $hasData = true;
            continue;
        }
        if (($value = valueFromPrefix($line, 'Fallidas:')) !== null) {
            $snapshot['failed'] = parseNullableInt($value);
            $hasData = true;
            continue;
        }
        if (($value = valueFromPrefix($line, 'Errores relevantes:')) !== null) {
            $snapshot['errors'] = $value;
            $hasData = true;
            continue;
        }
        if (($value = valueFromPrefix($line, 'Observaciones:')) !== null) {
            $snapshot['observations'] = $value;
            $hasData = true;
            continue;
        }
        if (($value = valueFromPrefix($line, 'Última validación registrada del día:')) !== null) {
            $snapshot['timestamp'] = $value;
            $hasData = true;
            continue;
        }
        if (($value = valueFromPrefix($line, 'Última validación disponible del día:')) !== null) {
            $snapshot['timestamp'] = $value;
            $hasData = true;
            continue;
        }
    }

    if (!$hasData) {
        return null;
    }

    return $snapshot;
}

function valueFromPrefix(string $line, string $prefix): ?string
{
    if (!str_starts_with($line, $prefix)) {
        return null;
    }

    $value = trim(substr($line, strlen($prefix)));
    return $value === '' ? null : $value;
}

function parseNullableInt(?string $value): ?int
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    return is_numeric($value) ? (int) $value : null;
}

/**
 * @param array<int, string> $errors
 */
function normalizeErrorSummary(array $errors): string
{
    $cleanErrors = [];
    foreach ($errors as $error) {
        $clean = trim((string) $error);
        if ($clean !== '') {
            $cleanErrors[] = $clean;
        }
    }

    return implode(' | ', $cleanErrors);
}

function fallback(string $value, string $fallback): string
{
    $clean = trim($value);
    return $clean === '' ? $fallback : $clean;
}

