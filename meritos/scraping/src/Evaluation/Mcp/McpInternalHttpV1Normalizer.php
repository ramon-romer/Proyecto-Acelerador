<?php

require_once __DIR__ . '/McpInternalHttpV1Contract.php';
require_once __DIR__ . '/McpInternalHttpV1NormalizationResult.php';

final class McpInternalHttpV1Normalizer
{
    /**
     * Evalua un payload MCP internal_http_v1 y determina si puede
     * producir el resultado requerido por runtime local actual.
     */
    public function normalize(array $decodedResponse): McpInternalHttpV1NormalizationResult
    {
        [$responseKind, $payload] = $this->extractPayload($decodedResponse);
        $legacyRequired = McpInternalHttpV1Contract::mcpLegacyRequiredKeys();
        $runtimeRequired = McpInternalHttpV1Contract::runtimeRequiredKeys();

        $missingLegacy = $this->missingKeys($payload, $legacyRequired);
        $missingRuntime = $this->missingKeys($payload, $runtimeRequired);
        $missingBusiness = $this->missingBusinessFields($payload);

        $legacyMatch = empty($missingLegacy);
        $runtimeMatch = empty($missingRuntime);

        $reasons = [];
        $notes = [];

        if (!$legacyMatch) {
            $reasons[] = 'mcp_missing_required_fields';
            $notes[] = 'No cumple ni el contrato tecnico legacy de 7 claves.';
        } else {
            $notes[] = 'Cumple contrato tecnico legacy MCP (7 claves).';
        }

        if (!$runtimeMatch) {
            $reasons[] = 'mcp_contract_legacy';
            $reasons[] = 'mcp_not_aneca_canonical';
            $notes[] = 'No cumple contrato runtime local (11 claves).';
        }

        if (!empty($missingBusiness)) {
            $reasons[] = 'mcp_partial_extraction_only';
            $notes[] = 'Hay campos de negocio faltantes o vacios en la extraccion.';
        }

        if ($runtimeMatch) {
            $notes[] = 'Puede producir resultado compatible con runtime actual.';
        } else {
            $notes[] = 'No se completan claves runtime (archivo_pdf/paginas_detectadas/txt_generado/json_generado).';
        }

        return new McpInternalHttpV1NormalizationResult(
            $runtimeMatch,
            $reasons,
            $responseKind,
            $payload,
            $missingLegacy,
            $missingRuntime,
            $missingBusiness,
            $notes
        );
    }

    /**
     * @return array{0:string,1:array}
     */
    private function extractPayload(array $decodedResponse): array
    {
        if (isset($decodedResponse['resultado']) && is_array($decodedResponse['resultado'])) {
            return ['envelope_with_resultado', $decodedResponse['resultado']];
        }

        if (McpInternalHttpV1Contract::hasAllKeys($decodedResponse, McpInternalHttpV1Contract::mcpLegacyRequiredKeys())) {
            return ['direct_legacy_payload', $decodedResponse];
        }

        return ['unknown_envelope', []];
    }

    /**
     * @param string[] $keys
     * @return string[]
     */
    private function missingKeys(array $payload, array $keys): array
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * @return string[]
     */
    private function missingBusinessFields(array $payload): array
    {
        $businessKeys = [
            'tipo_documento',
            'numero',
            'fecha',
            'total_bi',
            'iva',
            'total_a_pagar',
        ];

        $missing = [];
        foreach ($businessKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                $missing[] = $key;
                continue;
            }

            $value = $payload[$key];
            if ($value === null) {
                $missing[] = $key;
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
