<?php

require_once __DIR__ . '/McpInternalHttpV1Contract.php';

final class McpInternalHttpV1ContractInspector
{
    public static function inspect(): array
    {
        return [
            'provider_id' => 'internal_http_v1',
            'host_port_observed' => '127.0.0.1:5000',
            'endpoints' => [
                [
                    'name' => McpInternalHttpV1Contract::ENDPOINT_EXTRACT_PDF,
                    'request_expected' => [
                        'content_types' => ['application/pdf', 'multipart/form-data (campo file)'],
                        'body' => 'bytes PDF',
                    ],
                    'success_responses' => [
                        '200_sync' => ['ok', 'queued', 'diagnostico', 'faltantes', 'resultado'],
                        '202_async' => ['ok', 'queued', 'job_id', 'status', 'diagnostico'],
                    ],
                    'error_responses' => [
                        '400_no_pdf',
                        '400_http_request_incompleto',
                        '413_payload_too_large',
                        '500_internal_error',
                    ],
                ],
                [
                    'name' => McpInternalHttpV1Contract::ENDPOINT_EXTRACT_DATA,
                    'request_expected' => [
                        'content_type' => 'application/json (o body que parezca JSON)',
                        'body_minimum' => '{"fuente":{"tipo":"pdf|db", ...}} o {"tipo":"pdf|db", ...}',
                    ],
                    'success_responses' => [
                        '200_sync' => ['ok', 'queued', 'diagnostico?', 'faltantes', 'resultado'],
                        '202_async_pdf' => ['ok', 'queued', 'job_id', 'status', 'diagnostico'],
                    ],
                    'error_responses' => [
                        '400_json_invalido',
                        '400_fuente_invalida',
                        '400_tipo_no_soportado',
                        '500_internal_error',
                    ],
                ],
                [
                    'name' => McpInternalHttpV1Contract::ENDPOINT_GET_JOB,
                    'request_expected' => [
                        'path_param' => 'job_id [a-zA-Z0-9_-]+',
                    ],
                    'success_responses' => [
                        '200' => ['ok', 'job_id', 'status', 'created_at', 'updated_at', 'diagnostico?', 'resultado?', 'faltantes?', 'error?'],
                    ],
                    'error_responses' => [
                        '404_job_not_found',
                        '500_meta_invalida',
                    ],
                ],
            ],
            'technical_payload_keys' => McpInternalHttpV1Contract::mcpLegacyRequiredKeys(),
            'runtime_required_keys' => McpInternalHttpV1Contract::runtimeRequiredKeys(),
            'has_health_endpoint' => false,
            'notes' => [
                'No implementa MCP oficial JSON-RPC/tools.',
                'Es un HTTP server custom de extraccion.',
                'El payload tecnico es legacy de 7 claves.',
            ],
        ];
    }
}
