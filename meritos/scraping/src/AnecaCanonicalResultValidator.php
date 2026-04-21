<?php
declare(strict_types=1);

require_once __DIR__ . '/JsonSchemaLiteValidator.php';

/**
 * Valida artefactos normalizados al contrato canonico ANECA.
 *
 * Objetivo:
 * - Verificar cumplimiento de schema.
 * - Verificar calidad minima de contenido para uso operativo.
 * - Emitir estado reutilizable en job/cache/API.
 */
class AnecaCanonicalResultValidator
{
    public const STATUS_VALIDO = 'valido';
    public const STATUS_VALIDO_CON_ADVERTENCIAS = 'valido_con_advertencias';
    public const STATUS_INCOMPLETO = 'incompleto';
    public const STATUS_INVALIDO = 'invalido';

    private ?JsonSchemaLiteValidator $schemaValidator = null;

    public function __construct(?string $schemaPath = null)
    {
        $schemaPath = $schemaPath ?? dirname(__DIR__, 3) . '/docs/schemas/contrato-canonico-aneca-v1.schema.json';
        if (!is_file($schemaPath)) {
            return;
        }

        $raw = @file_get_contents($schemaPath);
        if (!is_string($raw) || trim($raw) === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        $this->schemaValidator = new JsonSchemaLiteValidator($decoded);
    }

    /**
     * @param mixed $payload
     * @return array<string,mixed>
     */
    public function validate($payload): array
    {
        $errors = [];
        $warnings = [];
        $normalized = $this->normalizePayload($payload, $errors);

        if (!is_array($normalized)) {
            return $this->build(self::STATUS_INVALIDO, $errors, $warnings, false, []);
        }

        if ($this->schemaValidator !== null) {
            $schemaErrors = $this->schemaValidator->validate($normalized);
            if (!empty($schemaErrors)) {
                $errors[] = 'schema_invalido';
                foreach (array_slice($schemaErrors, 0, 10) as $schemaError) {
                    $errors[] = 'schema:' . trim((string)$schemaError);
                }
            }
        } else {
            $warnings[] = 'schema_canonico_no_disponible';
        }

        $texto = $this->safeString($normalized['texto_extraido'] ?? null) ?? '';
        $textoLength = $this->strlenUtf8($texto);
        $lineCount = $this->countMeaningfulLines($texto);
        if ($textoLength === 0) {
            $errors[] = 'campo_critico_vacio:texto_extraido';
        } elseif ($textoLength < 120) {
            $warnings[] = 'texto_extraido_corto';
        }

        $comite = strtoupper($this->safeString($normalized['metadatos_extraccion']['comite'] ?? null) ?? '');
        if ($comite === '' || $comite === 'DESCONOCIDO') {
            $warnings[] = 'comite_no_identificado';
        }

        $analysis = $this->analyzeEvidenceCoverage($normalized);
        if (($analysis['total_evidencias'] ?? 0) === 0) {
            $warnings[] = 'sin_evidencias_detectadas';
        }

        if (
            ($analysis['total_evidencias'] ?? 0) > 0
            && ($analysis['secciones_con_contenido'] ?? 0) < 2
            && $textoLength >= 400
        ) {
            $warnings[] = 'cobertura_semantica_baja';
        }

        $status = self::STATUS_VALIDO;
        if (!empty($errors)) {
            $status = self::STATUS_INVALIDO;
        } elseif (($analysis['total_evidencias'] ?? 0) === 0 || $lineCount < 3) {
            $status = self::STATUS_INCOMPLETO;
        } elseif (!empty($warnings)) {
            $status = self::STATUS_VALIDO_CON_ADVERTENCIAS;
        }

        $ready = in_array($status, [self::STATUS_VALIDO, self::STATUS_VALIDO_CON_ADVERTENCIAS], true);
        $analysis['texto_extraido_chars'] = $textoLength;
        $analysis['texto_lineas_significativas'] = $lineCount;

        return $this->build($status, $errors, $warnings, $ready, $analysis);
    }

    /**
     * @return array<string,mixed>
     */
    public function validateFile(string $jsonPath): array
    {
        if (!is_file($jsonPath)) {
            return $this->build(
                self::STATUS_INVALIDO,
                ['json_canonico_no_encontrado'],
                [],
                false,
                ['aneca_canonical_path' => $jsonPath]
            );
        }

        $raw = @file_get_contents($jsonPath);
        if (!is_string($raw) || trim($raw) === '') {
            return $this->build(
                self::STATUS_INVALIDO,
                ['json_canonico_vacio'],
                [],
                false,
                ['aneca_canonical_path' => $jsonPath]
            );
        }

        $decoded = json_decode($raw, true);
        $validation = $this->validate($decoded);
        $validation['aneca_canonical_path'] = $jsonPath;
        return $validation;
    }

    /**
     * @param array<string,mixed> $validation
     */
    public function isReady(array $validation): bool
    {
        return !empty($validation['aneca_canonical_ready']);
    }

    /**
     * @param mixed $payload
     * @param array<int,string> $errors
     * @return array<string,mixed>|null
     */
    private function normalizePayload($payload, array &$errors): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload)) {
            $trimmed = trim($payload);
            if ($trimmed === '') {
                $errors[] = 'json_canonico_vacio';
                return null;
            }

            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                $errors[] = 'json_canonico_invalido';
                return null;
            }

            return $decoded;
        }

        $errors[] = 'payload_canonico_no_json';
        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function analyzeEvidenceCoverage(array $payload): array
    {
        $secciones = [
            'bloque_1.publicaciones' => (array)($payload['bloque_1']['publicaciones'] ?? []),
            'bloque_1.libros' => (array)($payload['bloque_1']['libros'] ?? []),
            'bloque_1.proyectos' => (array)($payload['bloque_1']['proyectos'] ?? []),
            'bloque_1.transferencia' => (array)($payload['bloque_1']['transferencia'] ?? []),
            'bloque_1.tesis_dirigidas' => (array)($payload['bloque_1']['tesis_dirigidas'] ?? []),
            'bloque_1.congresos' => (array)($payload['bloque_1']['congresos'] ?? []),
            'bloque_1.otros_meritos_investigacion' => (array)($payload['bloque_1']['otros_meritos_investigacion'] ?? []),
            'bloque_2.docencia_universitaria' => (array)($payload['bloque_2']['docencia_universitaria'] ?? []),
            'bloque_2.evaluacion_docente' => (array)($payload['bloque_2']['evaluacion_docente'] ?? []),
            'bloque_2.formacion_docente' => (array)($payload['bloque_2']['formacion_docente'] ?? []),
            'bloque_2.material_docente' => (array)($payload['bloque_2']['material_docente'] ?? []),
            'bloque_3.formacion_academica' => (array)($payload['bloque_3']['formacion_academica'] ?? []),
            'bloque_3.experiencia_profesional' => (array)($payload['bloque_3']['experiencia_profesional'] ?? []),
            'bloque_4' => (array)($payload['bloque_4'] ?? []),
        ];

        $total = 0;
        $seccionesConContenido = 0;
        $detalle = [];

        foreach ($secciones as $section => $items) {
            $count = 0;
            foreach ($items as $item) {
                if (is_array($item) && !empty($item)) {
                    $count++;
                    continue;
                }

                if (is_scalar($item) && trim((string)$item) !== '') {
                    $count++;
                }
            }

            $detalle[$section] = $count;
            $total += $count;
            if ($count > 0) {
                $seccionesConContenido++;
            }
        }

        return [
            'total_evidencias' => $total,
            'secciones_con_contenido' => $seccionesConContenido,
            'secciones' => $detalle,
        ];
    }

    private function countMeaningfulLines(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $count = 0;
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }

            if ($this->strlenUtf8($line) < 10) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function safeString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function strlenUtf8(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    /**
     * @param array<int,string> $errors
     * @param array<int,string> $warnings
     * @param array<string,mixed> $metrics
     * @return array<string,mixed>
     */
    private function build(
        string $status,
        array $errors,
        array $warnings,
        bool $ready,
        array $metrics
    ): array {
        $allowed = [
            self::STATUS_VALIDO,
            self::STATUS_VALIDO_CON_ADVERTENCIAS,
            self::STATUS_INCOMPLETO,
            self::STATUS_INVALIDO,
        ];
        if (!in_array($status, $allowed, true)) {
            $status = self::STATUS_INVALIDO;
        }

        return [
            'aneca_canonical_ready' => $ready,
            'aneca_canonical_validation_status' => $status,
            'aneca_canonical_validation_errors' => $this->normalizeStringList($errors),
            'aneca_canonical_validation_warnings' => $this->normalizeStringList($warnings),
            'aneca_canonical_metrics' => $metrics,
        ];
    }

    /**
     * @param array<int,mixed> $value
     * @return array<int,string>
     */
    private function normalizeStringList(array $value): array
    {
        $out = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed === '') {
                continue;
            }

            $out[] = $trimmed;
        }

        return array_values(array_unique($out));
    }
}
