<?php
declare(strict_types=1);

namespace GenerarDocumentacion\Formatter;

/**
 * @param array{
 *   title: string,
 *   sections: array<int, string>,
 *   signature: string
 * } $definition
 * @param array{
 *   meta: array{project: string, document: string, date: string, author: string, role: string, status: string},
 *   sections: array<string, array<int, string>>
 * } $state
 */
function formatDocument(array $definition, array $state): string
{
    $lines = [];

    $lines[] = '# ' . $definition['title'];
    $lines[] = '';
    $lines[] = '## Cabecera';
    $lines[] = 'PROYECTO: ' . $state['meta']['project'];
    $lines[] = 'DOCUMENTO: ' . $state['meta']['document'];
    $lines[] = 'FECHA: ' . $state['meta']['date'];
    $lines[] = 'AUTOR: ' . $state['meta']['author'];
    $lines[] = 'ROL: ' . $state['meta']['role'];
    $lines[] = 'ESTADO: ' . $state['meta']['status'];
    $lines[] = '';

    foreach ($definition['sections'] as $heading) {
        $lines[] = '## ' . $heading;
        foreach ($state['sections'][$heading] ?? [] as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
    }

    $lines[] = '## Firma';
    $lines[] = sprintf($definition['signature'], $state['meta']['author']);

    return implode("\n", $lines) . "\n";
}

