<?php
declare(strict_types=1);

namespace GenerarDocumentacion\MergeService;

use RuntimeException;

/**
 * @param array{
 *   meta: array{project: string, document: string, date: string, author: string, role: string, status: string},
 *   sections: array<string, array<int, string>>
 * } $incomingState
 * @param array{sections: array<int, string>, title: string, replace_sections?: array<int, string>} $definition
 * @return array{
 *   meta: array{project: string, document: string, date: string, author: string, role: string, status: string},
 *   sections: array<string, array<int, string>>
 * }
 */
function mergeContent(
    ?string $existingContent,
    array $incomingState,
    array $definition,
    string $currentAuthor,
    string $currentRole
): array {
    $existingState = null;
    if ($existingContent !== null && trim($existingContent) !== '') {
        $existingState = parseExistingDocument($existingContent, $definition);
    }

    $mergedMeta = $incomingState['meta'];
    if ($existingState !== null) {
        $mergedMeta['project'] = $existingState['meta']['project'];
        $mergedMeta['document'] = $existingState['meta']['document'];
        $mergedMeta['author'] = $existingState['meta']['author'];
        $mergedMeta['role'] = $existingState['meta']['role'];
        $mergedMeta['status'] = $existingState['meta']['status'];
    }

    $requiresAuthorTag = $existingState !== null
        && ($currentAuthor !== $mergedMeta['author'] || $currentRole !== $mergedMeta['role']);
    $replaceSections = $definition['replace_sections'] ?? [];

    $mergedSections = [];
    foreach ($definition['sections'] as $heading) {
        $existingItems = $existingState['sections'][$heading] ?? [];
        $newItems = $incomingState['sections'][$heading] ?? [];

        if (in_array($heading, $replaceSections, true)) {
            $mergedSections[$heading] = count($newItems) > 0
                ? mergeSectionItems([], $newItems)
                : mergeSectionItems($existingItems, []);
            continue;
        }

        if ($requiresAuthorTag) {
            $newItems = tagItemsWithAuthor($newItems, $currentAuthor, $currentRole);
        }

        $mergedSections[$heading] = mergeSectionItems($existingItems, $newItems);
    }

    return [
        'meta' => $mergedMeta,
        'sections' => $mergedSections,
    ];
}

/**
 * @param array<int, string> $items
 * @return array<int, string>
 */
function tagItemsWithAuthor(array $items, string $author, string $role): array
{
    $taggedItems = [];
    $prefix = sprintf('[%s | %s] ', $author, $role);

    foreach ($items as $item) {
        $cleanItem = cleanListItem($item);
        if ($cleanItem === '') {
            continue;
        }

        if (preg_match('/^\[[^\]]+\]\s*/u', $cleanItem) === 1) {
            $taggedItems[] = $cleanItem;
            continue;
        }

        $taggedItems[] = $prefix . $cleanItem;
    }

    return $taggedItems;
}

/**
 * @param array<int, string> $existingItems
 * @param array<int, string> $incomingItems
 * @return array<int, string>
 */
function mergeSectionItems(array $existingItems, array $incomingItems): array
{
    $mergedItems = [];
    $seen = [];

    foreach ([$existingItems, $incomingItems] as $bucket) {
        foreach ($bucket as $item) {
            $cleanItem = cleanListItem($item);
            if ($cleanItem === '') {
                continue;
            }

            $fingerprint = normalizeFingerprint($cleanItem);
            if ($fingerprint === '' || isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $mergedItems[] = $cleanItem;
        }
    }

    return $mergedItems;
}

function cleanListItem(string $item): string
{
    $clean = trim($item);
    $clean = preg_replace('/^\s*[-*]\s*/u', '', $clean) ?? $clean;
    $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
    return trim($clean);
}

function normalizeFingerprint(string $item): string
{
    $normalized = cleanListItem($item);
    $normalized = preg_replace('/^\[[^\]]+\]\s*/u', '', $normalized) ?? $normalized;
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    return toLower(trim($normalized));
}

/**
 * @param array{title: string, sections: array<int, string>} $definition
 * @return array{
 *   meta: array{project: string, document: string, date: string, author: string, role: string, status: string},
 *   sections: array<string, array<int, string>>
 * }
 */
function parseExistingDocument(string $content, array $definition): array
{
    $normalizedContent = normalizeLineEndings($content);
    validateTitle($normalizedContent, $definition['title']);

    $blocks = splitLevelTwoBlocks($normalizedContent);

    if (!array_key_exists('Cabecera', $blocks)) {
        throw new RuntimeException('El documento existente no cumple la estructura minima. Falta la seccion "Cabecera".');
    }

    $knownSectionCount = 0;
    foreach ($definition['sections'] as $heading) {
        if (array_key_exists($heading, $blocks)) {
            $knownSectionCount++;
        }
    }

    if ($knownSectionCount === 0) {
        throw new RuntimeException('El documento existente no contiene secciones reconocibles para fusionar.');
    }

    $meta = parseHeaderBlock($blocks['Cabecera']);

    $sections = [];
    foreach ($definition['sections'] as $heading) {
        $sections[$heading] = parseSectionItems($blocks[$heading] ?? '');
    }

    return [
        'meta' => $meta,
        'sections' => $sections,
    ];
}

function normalizeLineEndings(string $content): string
{
    return str_replace(["\r\n", "\r"], "\n", $content);
}

function validateTitle(string $content, string $expectedTitle): void
{
    $pattern = '/^#\s+' . preg_quote($expectedTitle, '/') . '\s*$/um';
    if (preg_match($pattern, $content) !== 1) {
        throw new RuntimeException('El documento existente no tiene el titulo esperado.');
    }
}

/**
 * @return array<string, string>
 */
function splitLevelTwoBlocks(string $content): array
{
    preg_match_all('/^##\s+(.+)$/um', $content, $matches, PREG_OFFSET_CAPTURE);
    if (!isset($matches[0]) || count($matches[0]) === 0) {
        throw new RuntimeException('El documento existente no contiene secciones de nivel 2.');
    }

    $blocks = [];
    $totalMatches = count($matches[0]);

    for ($i = 0; $i < $totalMatches; $i++) {
        $headerTitle = trim($matches[1][$i][0]);
        $headerLine = $matches[0][$i][0];
        $headerOffset = $matches[0][$i][1];

        if (isset($blocks[$headerTitle])) {
            throw new RuntimeException(sprintf('Seccion duplicada en documento existente: %s', $headerTitle));
        }

        $start = $headerOffset + strlen($headerLine);
        if (substr($content, $start, 1) === "\n") {
            $start++;
        }

        $end = $i + 1 < $totalMatches ? $matches[0][$i + 1][1] : strlen($content);
        $blocks[$headerTitle] = trim(substr($content, $start, $end - $start));
    }

    return $blocks;
}

/**
 * @return array{project: string, document: string, date: string, author: string, role: string, status: string}
 */
function parseHeaderBlock(string $headerBlock): array
{
    $rawValues = [];
    $lines = preg_split('/\n/u', normalizeLineEndings($headerBlock)) ?: [];

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '') {
            continue;
        }

        if (preg_match('/^([^:]+):\s*(.+)$/u', $trimmedLine, $match) !== 1) {
            throw new RuntimeException('La cabecera del documento existente contiene lineas invalidas.');
        }

        $rawValues[toUpper(trim($match[1]))] = trim($match[2]);
    }

    $required = [
        'PROYECTO' => 'project',
        'DOCUMENTO' => 'document',
        'FECHA' => 'date',
        'AUTOR' => 'author',
        'ROL' => 'role',
        'ESTADO' => 'status',
    ];

    $meta = [];
    foreach ($required as $rawKey => $mappedKey) {
        if (!isset($rawValues[$rawKey]) || trim($rawValues[$rawKey]) === '') {
            throw new RuntimeException(sprintf('Falta el campo obligatorio "%s" en la cabecera existente.', $rawKey));
        }
        $meta[$mappedKey] = trim($rawValues[$rawKey]);
    }

    return $meta;
}

/**
 * @return array<int, string>
 */
function parseSectionItems(string $sectionBody): array
{
    if (trim($sectionBody) === '') {
        return [];
    }

    $items = [];
    $lines = preg_split('/\n/u', normalizeLineEndings($sectionBody)) ?: [];
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '') {
            continue;
        }

        $item = preg_replace('/^\s*-\s*/u', '', $trimmedLine) ?? $trimmedLine;
        $item = trim($item);
        if ($item !== '') {
            $items[] = $item;
        }
    }

    return $items;
}

function toLower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function toUpper(string $value): string
{
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($value, 'UTF-8');
    }

    return strtoupper($value);
}
