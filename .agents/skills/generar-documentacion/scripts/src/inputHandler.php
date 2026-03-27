<?php
declare(strict_types=1);

namespace GenerarDocumentacion\InputHandler;

use InvalidArgumentException;

const DEFAULT_AUTHOR = 'Basilio Lagares';
const DEFAULT_ROLE_FOR_DEFAULT_AUTHOR = 'Desarrollo backend';

/**
 * @param array{by_number: array<string, string>, by_alias: array<string, string>} $sectionMap
 * @return array<string, array<int, string>>
 */
function normalizeUpdates($rawUpdates, array $sectionMap): array
{
    $normalized = [];
    foreach ($sectionMap['by_number'] as $heading) {
        $normalized[$heading] = [];
    }

    if ($rawUpdates === null || $rawUpdates === '') {
        return $normalized;
    }

    if (!is_array($rawUpdates)) {
        throw new InvalidArgumentException('Las actualizaciones deben enviarse como objeto JSON por seccion.');
    }

    foreach ($rawUpdates as $rawKey => $rawValue) {
        $heading = resolveSectionHeading($rawKey, $sectionMap);
        foreach (normalizeItems($rawValue) as $item) {
            if ($item !== '') {
                $normalized[$heading][] = $item;
            }
        }
    }

    return $normalized;
}

function resolveAuthor(?string $author): string
{
    $resolvedAuthor = trim(stripBom((string) $author));
    return $resolvedAuthor === '' ? DEFAULT_AUTHOR : $resolvedAuthor;
}

function resolveRole(string $author, ?string $role): string
{
    $resolvedRole = trim(stripBom((string) $role));

    if ($author === DEFAULT_AUTHOR) {
        return $resolvedRole === '' ? DEFAULT_ROLE_FOR_DEFAULT_AUTHOR : $resolvedRole;
    }

    if ($resolvedRole === '') {
        throw new InvalidArgumentException('El rol es obligatorio cuando el autor no es Basilio Lagares.');
    }

    return $resolvedRole;
}

/**
 * @param array<string, array<int, string>> $technicalUpdates
 * @param array<string, array<int, string>> $dailyUpdates
 */
function validateHasNewData(array $technicalUpdates, array $dailyUpdates): void
{
    $totalItems = countSectionItems($technicalUpdates) + countSectionItems($dailyUpdates);

    if ($totalItems === 0) {
        throw new InvalidArgumentException('No hay datos nuevos para registrar en la documentacion diaria.');
    }
}

/**
 * @param array<string, array<int, string>> $sections
 */
function countSectionItems(array $sections): int
{
    $count = 0;
    foreach ($sections as $items) {
        $count += count($items);
    }
    return $count;
}

/**
 * @param array{by_number: array<string, string>, by_alias: array<string, string>} $sectionMap
 */
function resolveSectionHeading($rawKey, array $sectionMap): string
{
    if (is_int($rawKey) || (is_string($rawKey) && ctype_digit(trim($rawKey)))) {
        $number = (string) ((int) $rawKey);
        if (isset($sectionMap['by_number'][$number])) {
            return $sectionMap['by_number'][$number];
        }
    }

    $normalizedKey = normalizeSectionKey((string) $rawKey);
    if (isset($sectionMap['by_alias'][$normalizedKey])) {
        return $sectionMap['by_alias'][$normalizedKey];
    }

    throw new InvalidArgumentException(sprintf('Seccion no reconocida: "%s".', (string) $rawKey));
}

/**
 * @return array<int, string>
 */
function normalizeItems($rawValue): array
{
    if (is_string($rawValue)) {
        return splitNormalizedLines($rawValue);
    }

    if (!is_array($rawValue)) {
        throw new InvalidArgumentException('Cada seccion debe contener texto o una lista de textos.');
    }

    $items = [];
    foreach ($rawValue as $item) {
        if (is_array($item) || is_object($item)) {
            throw new InvalidArgumentException('No se permiten objetos anidados en items de seccion.');
        }

        if (!is_scalar($item) && $item !== null) {
            throw new InvalidArgumentException('Cada item de seccion debe ser texto simple.');
        }

        foreach (splitNormalizedLines((string) $item) as $line) {
            if ($line !== '') {
                $items[] = $line;
            }
        }
    }

    return $items;
}

/**
 * @return array<int, string>
 */
function splitNormalizedLines(string $text): array
{
    $lines = preg_split('/\R/u', $text) ?: [];

    if (count($lines) === 0) {
        return [];
    }

    $normalized = [];
    foreach ($lines as $line) {
        $cleanLine = normalizeLine($line);
        if ($cleanLine !== '') {
            $normalized[] = $cleanLine;
        }
    }

    return $normalized;
}

function normalizeLine(string $line): string
{
    $normalized = trim(stripBom($line));
    $normalized = preg_replace('/^\s*[-*]\s*/u', '', $normalized) ?? $normalized;
    $normalized = preg_replace('/^\s*\d+[.)]\s*/u', '', $normalized) ?? $normalized;
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    return trim($normalized);
}

function normalizeSectionKey(string $key): string
{
    $normalized = trim($key);
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

function stripBom(string $value): string
{
    return preg_replace('/^\x{FEFF}/u', '', $value) ?? $value;
}
