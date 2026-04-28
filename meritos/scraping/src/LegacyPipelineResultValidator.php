<?php

/**
 * Validador tecnico EXPLICITO de la salida legacy/transitoria del pipeline de meritos/scraping.
 *
 * Importante:
 * - Solo valida el payload heredado (`tipo_documento`, `total_bi`, `iva`, etc.).
 * - NO valida el contrato canonico oficial ANECA.
 * - Se mantiene por compatibilidad operativa mientras se completa la migracion.
 */
class LegacyPipelineResultValidator
{
    public const STATUS_VALIDO = 'valido';
    public const STATUS_VALIDO_CON_ADVERTENCIAS = 'valido_con_advertencias';
    public const STATUS_INCOMPLETO = 'incompleto';
    public const STATUS_INVALIDO = 'invalido';

    /**
     * @param mixed $payload Array de resultado o JSON serializado.
     */
    public function validate($payload): array
    {
        $errors = [];
        $warnings = [];
        $requiredMissing = [];
        $criticalEmpty = [];

        $resultado = $this->normalizePayload($payload, $errors);
        if (!is_array($resultado)) {
            return $this->build(self::STATUS_INVALIDO, $errors, $warnings, $requiredMissing, $criticalEmpty);
        }

        $requiredKeys = $this->requiredKeys();
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $resultado)) {
                $requiredMissing[] = $key;
                $errors[] = 'falta_clave_obligatoria:' . $key;
            }
        }

        if (empty($requiredMissing)) {
            foreach (['texto_preview', 'archivo_pdf', 'txt_generado', 'json_generado'] as $field) {
                if (!$this->isNonEmptyString($resultado[$field] ?? null)) {
                    $criticalEmpty[] = $field;
                    $errors[] = 'campo_critico_vacio:' . $field;
                }
            }

            $paginasDetectadasRaw = $resultado['paginas_detectadas'] ?? null;
            $paginasDetectadas = is_numeric($paginasDetectadasRaw) ? (int)$paginasDetectadasRaw : null;
            if ($paginasDetectadas === null || $paginasDetectadas < 1) {
                $criticalEmpty[] = 'paginas_detectadas';
                $errors[] = 'campo_critico_invalido:paginas_detectadas';
            }
        }

        $camposExtraccion = ['tipo_documento', 'numero', 'fecha', 'total_bi', 'iva', 'total_a_pagar'];
        $camposConValor = 0;
        foreach ($camposExtraccion as $field) {
            if ($this->hasMeaningfulValue($resultado[$field] ?? null)) {
                $camposConValor++;
            }
        }

        if ($camposConValor === 0 && empty($errors)) {
            $warnings[] = 'resultado_sin_campos_extraidos';
        } elseif ($camposConValor < count($camposExtraccion) && empty($errors)) {
            $warnings[] = 'resultado_con_campos_extraidos_parciales';
        }

        $status = self::STATUS_VALIDO;
        if (!empty($errors)) {
            $status = self::STATUS_INVALIDO;
        } elseif ($camposConValor === 0) {
            $status = self::STATUS_INCOMPLETO;
        } elseif (!empty($warnings)) {
            $status = self::STATUS_VALIDO_CON_ADVERTENCIAS;
        }

        return $this->build($status, $errors, $warnings, $requiredMissing, $criticalEmpty);
    }

    public function isCacheable(array $validation): bool
    {
        $status = (string)($validation['validation_status'] ?? '');
        return in_array($status, [self::STATUS_VALIDO, self::STATUS_VALIDO_CON_ADVERTENCIAS], true);
    }

    public function isClean(array $validation): bool
    {
        return (string)($validation['validation_status'] ?? '') === self::STATUS_VALIDO;
    }

    public function normalizeValidation($validation): array
    {
        if (!is_array($validation)) {
            return $this->build(
                self::STATUS_INVALIDO,
                ['validation_context_invalido'],
                [],
                [],
                []
            );
        }

        $status = (string)($validation['validation_status'] ?? self::STATUS_INVALIDO);
        if (!in_array($status, [
            self::STATUS_VALIDO,
            self::STATUS_VALIDO_CON_ADVERTENCIAS,
            self::STATUS_INCOMPLETO,
            self::STATUS_INVALIDO,
        ], true)) {
            $status = self::STATUS_INVALIDO;
        }

        $errors = $this->normalizeStringList($validation['validation_errors'] ?? []);
        $warnings = $this->normalizeStringList($validation['validation_warnings'] ?? []);
        $requiredMissing = $this->normalizeStringList($validation['required_missing_keys'] ?? []);
        $criticalEmpty = $this->normalizeStringList($validation['critical_empty_fields'] ?? []);

        return $this->build($status, $errors, $warnings, $requiredMissing, $criticalEmpty);
    }

    public function summarize(array $validation, int $maxItems = 3): string
    {
        $validation = $this->normalizeValidation($validation);
        $status = (string)$validation['validation_status'];
        $errors = (array)$validation['validation_errors'];
        $warnings = (array)$validation['validation_warnings'];

        $parts = ['status=' . $status];
        if (!empty($errors)) {
            $parts[] = 'errores=' . implode(',', array_slice($errors, 0, $maxItems));
        }
        if (!empty($warnings)) {
            $parts[] = 'warnings=' . implode(',', array_slice($warnings, 0, $maxItems));
        }

        return implode(' ', $parts);
    }

    private function requiredKeys(): array
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

    /**
     * @param mixed $payload
     */
    private function normalizePayload($payload, array &$errors): ?array
    {
        if (is_array($payload)) {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                $errors[] = 'json_no_serializable';
                return null;
            }

            return $payload;
        }

        if (is_string($payload)) {
            $raw = trim($payload);
            if ($raw === '') {
                $errors[] = 'json_vacio';
                return null;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $errors[] = 'json_invalido';
                return null;
            }

            return $decoded;
        }

        $errors[] = 'payload_no_json';
        return null;
    }

    /**
     * @param mixed $value
     */
    private function isNonEmptyString($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param mixed $value
     */
    private function hasMeaningfulValue($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return true;
    }

    private function normalizeStringList($value): array
    {
        $items = is_array($value) ? $value : [];
        $out = [];
        foreach ($items as $item) {
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

    private function build(
        string $status,
        array $errors,
        array $warnings,
        array $requiredMissing,
        array $criticalEmpty
    ): array {
        return [
            'validation_status' => $status,
            'validation_errors' => $this->normalizeStringList($errors),
            'validation_warnings' => $this->normalizeStringList($warnings),
            'required_missing_keys' => $this->normalizeStringList($requiredMissing),
            'critical_empty_fields' => $this->normalizeStringList($criticalEmpty),
        ];
    }
}
