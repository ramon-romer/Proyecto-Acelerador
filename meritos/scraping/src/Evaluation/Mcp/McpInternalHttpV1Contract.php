<?php

final class McpInternalHttpV1Contract
{
    public const ENDPOINT_EXTRACT_PDF = 'POST /extract-pdf';
    public const ENDPOINT_EXTRACT_DATA = 'POST /extract-data';
    public const ENDPOINT_GET_JOB = 'GET /jobs/{id}';

    /**
     * Contrato tecnico legacy de mcp-server (7 claves).
     *
     * @return string[]
     */
    public static function mcpLegacyRequiredKeys(): array
    {
        return [
            'tipo_documento',
            'numero',
            'fecha',
            'total_bi',
            'iva',
            'total_a_pagar',
            'texto_preview',
        ];
    }

    /**
     * Contrato minimo exigido por runtime local en meritos/scraping (11 claves).
     *
     * @return string[]
     */
    public static function runtimeRequiredKeys(): array
    {
        return [
            'tipo_documento',
            'numero',
            'fecha',
            'total_bi',
            'iva',
            'total_a_pagar',
            'texto_preview',
            'archivo_pdf',
            'paginas_detectadas',
            'txt_generado',
            'json_generado',
        ];
    }

    public static function hasAllKeys(array $payload, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                return false;
            }
        }

        return true;
    }
}
