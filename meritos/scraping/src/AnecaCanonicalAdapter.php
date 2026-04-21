<?php

/**
 * Adaptador de normalizacion al contrato canonico oficial ANECA.
 *
 * Este adaptador NO cambia el runtime legacy existente de golpe; permite
 * generar en paralelo una salida normalizada a:
 * docs/schemas/contrato-canonico-aneca-v1.schema.json
 */
class AnecaCanonicalAdapter
{
    private const MAX_EVIDENCES_PER_SECTION = 12;
    private const MIN_LINE_LENGTH = 18;

    /**
     * @return array<string,mixed>
     */
    public function adaptFromLegacyPipelineResult(array $legacyResult, array $context = []): array
    {
        $archivoPdf = $this->resolveRequiredFileName(
            $legacyResult['archivo_pdf'] ?? null,
            $context['archivo_pdf'] ?? null,
            'documento.pdf'
        );

        $jsonGenerado = $this->resolveRequiredFileName(
            $legacyResult['json_generado'] ?? null,
            $context['json_generado'] ?? null,
            'resultado.json'
        );

        $textoExtraido = $this->resolveText(
            $context['texto_extraido'] ?? null,
            $legacyResult['texto_preview'] ?? null
        );

        $comiteDefault = $this->safeString(getenv('ANECA_COMITE_DEFAULT'));
        if ($comiteDefault === null) {
            $comiteDefault = 'DESCONOCIDO';
        }

        $versionEsquemaDefault = $this->safeString(getenv('ANECA_SCHEMA_VERSION'));
        if ($versionEsquemaDefault === null) {
            $versionEsquemaDefault = '1.0';
        }

        $fechaExtraccion = $this->safeString($context['fecha_extraccion'] ?? null);
        if ($fechaExtraccion === null) {
            $fechaExtraccion = gmdate('c');
        }

        $evidenceMap = $this->extractEvidenceMap($textoExtraido);
        $legacyFallback = $this->buildLegacyFallbackEvidence($legacyResult);
        $bloque4 = array_merge($legacyFallback, $evidenceMap['bloque_4']);

        $totalEvidences = $this->countTotalEvidences($evidenceMap, $bloque4);
        $requiresManualReview = array_key_exists('requiere_revision_manual', $context)
            ? (bool)$context['requiere_revision_manual']
            : ($totalEvidences < 3);

        return [
            'bloque_1' => [
                'publicaciones' => $evidenceMap['bloque_1.publicaciones'],
                'libros' => $evidenceMap['bloque_1.libros'],
                'proyectos' => $evidenceMap['bloque_1.proyectos'],
                'transferencia' => $evidenceMap['bloque_1.transferencia'],
                'tesis_dirigidas' => $evidenceMap['bloque_1.tesis_dirigidas'],
                'congresos' => $evidenceMap['bloque_1.congresos'],
                'otros_meritos_investigacion' => $evidenceMap['bloque_1.otros_meritos_investigacion'],
            ],
            'bloque_2' => [
                'docencia_universitaria' => $evidenceMap['bloque_2.docencia_universitaria'],
                'evaluacion_docente' => $evidenceMap['bloque_2.evaluacion_docente'],
                'formacion_docente' => $evidenceMap['bloque_2.formacion_docente'],
                'material_docente' => $evidenceMap['bloque_2.material_docente'],
            ],
            'bloque_3' => [
                'formacion_academica' => $evidenceMap['bloque_3.formacion_academica'],
                'experiencia_profesional' => $evidenceMap['bloque_3.experiencia_profesional'],
            ],
            'bloque_4' => $bloque4,
            'metadatos_extraccion' => [
                'comite' => $this->safeString($context['comite'] ?? null) ?? $comiteDefault,
                'subcomite' => $this->safeString($context['subcomite'] ?? null),
                'archivo_pdf' => $archivoPdf,
                'fecha_extraccion' => $fechaExtraccion,
                'version_esquema' => $this->safeString($context['version_esquema'] ?? null) ?? $versionEsquemaDefault,
                'requiere_revision_manual' => $requiresManualReview,
                'origen_adaptacion' => 'pipeline_legacy_meritos_scraping',
                'resumen_adaptacion' => [
                    'lineas_analizadas' => $evidenceMap['stats']['lineas_analizadas'],
                    'lineas_clasificadas' => $evidenceMap['stats']['lineas_clasificadas'],
                    'evidencias_generadas' => $totalEvidences,
                    'secciones_con_contenido' => $evidenceMap['stats']['secciones_con_contenido'],
                ],
            ],
            'archivo_pdf' => $archivoPdf,
            'json_generado' => $jsonGenerado,
            'texto_extraido' => $textoExtraido,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function extractEvidenceMap(string $text): array
    {
        $map = [
            'bloque_1.publicaciones' => [],
            'bloque_1.libros' => [],
            'bloque_1.proyectos' => [],
            'bloque_1.transferencia' => [],
            'bloque_1.tesis_dirigidas' => [],
            'bloque_1.congresos' => [],
            'bloque_1.otros_meritos_investigacion' => [],
            'bloque_2.docencia_universitaria' => [],
            'bloque_2.evaluacion_docente' => [],
            'bloque_2.formacion_docente' => [],
            'bloque_2.material_docente' => [],
            'bloque_3.formacion_academica' => [],
            'bloque_3.experiencia_profesional' => [],
            'bloque_4' => [],
            'stats' => [
                'lineas_analizadas' => 0,
                'lineas_clasificadas' => 0,
                'secciones_con_contenido' => 0,
            ],
        ];

        if (trim($text) === '') {
            return $map;
        }

        $keywords = [
            'bloque_1.publicaciones' => ['publicacion', 'revista', 'doi', 'scopus', 'jcr', 'articulo'],
            'bloque_1.libros' => ['libro', 'isbn', 'editorial', 'capitulo'],
            'bloque_1.proyectos' => ['proyecto', 'investigador principal', 'ip ', 'financiacion', 'horizon'],
            'bloque_1.transferencia' => ['patente', 'transferencia', 'spin-off', 'licencia', 'contrato otri'],
            'bloque_1.tesis_dirigidas' => ['tesis', 'dirigida', 'codireccion', 'doctoral'],
            'bloque_1.congresos' => ['congreso', 'ponencia', 'comunicacion', 'proceedings'],
            'bloque_1.otros_meritos_investigacion' => ['sexenio', 'indice h', 'investigacion'],
            'bloque_2.docencia_universitaria' => ['docencia', 'asignatura', 'creditos', 'grado', 'master'],
            'bloque_2.evaluacion_docente' => ['evaluacion docente', 'quinquenio', 'encuesta docente'],
            'bloque_2.formacion_docente' => ['formacion docente', 'innovacion docente', 'curso docente'],
            'bloque_2.material_docente' => ['material docente', 'guia docente', 'manual docente'],
            'bloque_3.formacion_academica' => ['doctor', 'master', 'grado', 'licenciatura', 'acreditacion'],
            'bloque_3.experiencia_profesional' => ['experiencia profesional', 'empresa', 'puesto', 'responsable'],
        ];

        $lines = preg_split('/\R/u', $text) ?: [];
        $seenBySection = [];
        $fallbackGeneric = [];

        foreach ($lines as $index => $line) {
            $line = $this->compactWhitespace((string)$line);
            if ($line === '' || $this->strlenUtf8($line) < self::MIN_LINE_LENGTH) {
                continue;
            }

            $map['stats']['lineas_analizadas']++;
            $normalized = $this->normalizeForSearch($line);
            $matchedSection = null;

            foreach ($keywords as $section => $tokens) {
                if (!$this->containsAnyToken($normalized, $tokens)) {
                    continue;
                }

                $seenKey = $section . '|' . $normalized;
                if (isset($seenBySection[$seenKey])) {
                    $matchedSection = $section;
                    break;
                }

                $seenBySection[$seenKey] = true;
                if (count($map[$section]) < self::MAX_EVIDENCES_PER_SECTION) {
                    $map[$section][] = $this->buildEvidenceItem($line, (int)$index + 1, 'texto_extraido');
                }
                $matchedSection = $section;
                break;
            }

            if ($matchedSection !== null) {
                $map['stats']['lineas_clasificadas']++;
                continue;
            }

            if (count($fallbackGeneric) < 6) {
                $fallbackGeneric[] = $this->buildEvidenceItem($line, (int)$index + 1, 'texto_extraido');
            }
        }

        $map['bloque_4'] = $fallbackGeneric;
        $map['stats']['secciones_con_contenido'] = $this->countSectionsWithContent($map);

        return $map;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildLegacyFallbackEvidence(array $legacyResult): array
    {
        $fields = [
            'tipo_documento',
            'numero',
            'fecha',
            'total_bi',
            'iva',
            'total_a_pagar',
        ];

        $pairs = [];
        foreach ($fields as $field) {
            $value = $this->safeString($legacyResult[$field] ?? null);
            if ($value === null) {
                continue;
            }
            $pairs[] = $field . ': ' . $value;
        }

        if (empty($pairs)) {
            return [];
        }

        return [[
            'descripcion' => implode(' | ', $pairs),
            'fuente' => 'pipeline_legacy',
            'confianza' => 'baja',
        ]];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildEvidenceItem(string $line, int $lineIndex, string $source): array
    {
        $line = $this->truncate($line, 320);
        return [
            'descripcion' => $line,
            'fuente' => $source,
            'linea' => $lineIndex,
        ];
    }

    /**
     * @param array<string,mixed> $evidenceMap
     */
    private function countSectionsWithContent(array $evidenceMap): int
    {
        $count = 0;
        foreach ($evidenceMap as $section => $items) {
            if ($section === 'stats') {
                continue;
            }

            if (is_array($items) && !empty($items)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string,mixed> $evidenceMap
     * @param array<int,array<string,mixed>> $bloque4
     */
    private function countTotalEvidences(array $evidenceMap, array $bloque4): int
    {
        $total = 0;
        foreach ($evidenceMap as $section => $items) {
            if ($section === 'stats' || $section === 'bloque_4') {
                continue;
            }

            $total += is_array($items) ? count($items) : 0;
        }

        $total += count($bloque4);
        return $total;
    }

    private function containsAnyToken(string $text, array $tokens): bool
    {
        foreach ($tokens as $token) {
            $token = $this->normalizeForSearch((string)$token);
            if ($token === '') {
                continue;
            }

            if (strpos($text, $token) !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizeForSearch(string $value): string
    {
        $value = $this->compactWhitespace($value);
        if ($value === '') {
            return '';
        }

        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $replace = [
            'a' => ['á', 'à', 'ä', 'â'],
            'e' => ['é', 'è', 'ë', 'ê'],
            'i' => ['í', 'ì', 'ï', 'î'],
            'o' => ['ó', 'ò', 'ö', 'ô'],
            'u' => ['ú', 'ù', 'ü', 'û'],
            'n' => ['ñ'],
        ];

        foreach ($replace as $ascii => $variants) {
            $value = str_replace($variants, $ascii, $value);
        }

        return $value;
    }

    private function compactWhitespace(string $value): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function truncate(string $value, int $max): string
    {
        if ($this->strlenUtf8($value) <= $max) {
            return $value;
        }

        if (function_exists('mb_substr')) {
            return rtrim(mb_substr($value, 0, $max - 1, 'UTF-8')) . '...';
        }

        return rtrim(substr($value, 0, $max - 1)) . '...';
    }

    private function strlenUtf8(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private function resolveText($primary, $fallback): string
    {
        $text = $this->safeString($primary);
        if ($text !== null) {
            return $text;
        }

        $text = $this->safeString($fallback);
        if ($text !== null) {
            return $text;
        }

        return '';
    }

    private function resolveRequiredFileName($first, $second, string $fallback): string
    {
        $value = $this->safeString($first);
        if ($value === null) {
            $value = $this->safeString($second);
        }

        if ($value === null) {
            return $fallback;
        }

        return basename($value);
    }

    private function safeString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
    }
}
