<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\Matching;

final class ResearchGroupMatchingService
{
    /**
     * @param array<string, mixed> $aggregated
     * @return array<int, array<string, mixed>>
     */
    public function buildBaseline(array $aggregated): array
    {
        $groupName = (string)($aggregated['grupo_objetivo']['nombre'] ?? '');
        $groupDescription = (string)($aggregated['grupo_objetivo']['descripcion'] ?? '');
        $groupKeywords = $this->tokenizeKeywords($groupName . ' ' . $groupDescription);

        $profiles = is_array($aggregated['profiles'] ?? null) ? $aggregated['profiles'] : [];
        $result = [];

        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $score = 0;
            $motivos = [];
            $evidencias = [];
            $advertencias = [];

            $profesorId = (int)($profile['profesor_id'] ?? 0);
            $nombre = (string)($profile['nombre_mostrable'] ?? '');
            $orcid = $this->normalizeNullableString($profile['orcid'] ?? null);
            $departamento = $this->normalizeNullableString($profile['departamento'] ?? null);
            $yaAsignado = !empty($profile['ya_asignado_grupo']);
            $anecaCount = isset($profile['aneca_evaluaciones_count']) && is_numeric($profile['aneca_evaluaciones_count'])
                ? max(0, (int)$profile['aneca_evaluaciones_count'])
                : 0;
            $anecaUltimoResultado = $this->normalizeNullableString($profile['aneca_ultimo_resultado'] ?? null);

            if (!$yaAsignado) {
                $score += 40;
                $motivos[] = 'No esta asignado actualmente al grupo objetivo.';
                $evidencias[] = 'asignacion_actual:no';
            } else {
                $score += 5;
                $motivos[] = 'Ya pertenece al grupo; puede ser referencia interna.';
                $advertencias[] = 'candidato_ya_asignado_grupo';
                $evidencias[] = 'asignacion_actual:si';
            }

            if ($orcid !== null) {
                $score += 20;
                $motivos[] = 'Dispone de ORCID para trazabilidad academica.';
                $evidencias[] = 'orcid:' . $orcid;
            } else {
                $advertencias[] = 'orcid_no_disponible';
            }

            if ($departamento !== null) {
                $score += 15;
                $motivos[] = 'Tiene departamento informado.';
                $evidencias[] = 'departamento:' . $departamento;

                $overlap = $this->keywordOverlapCount($groupKeywords, $this->tokenizeKeywords($departamento));
                if ($overlap > 0) {
                    $bonus = min(10, $overlap * 5);
                    $score += $bonus;
                    $motivos[] = 'Coincidencia semantica basica entre grupo y departamento.';
                    $evidencias[] = 'coincidencias_keywords:' . $overlap;
                }
            } else {
                $advertencias[] = 'departamento_no_disponible';
            }

            if ($anecaCount > 0) {
                $bonus = min(20, $anecaCount * 5);
                $score += $bonus;
                $motivos[] = 'Tiene evaluaciones ANECA trazables por ORCID.';
                $evidencias[] = 'aneca_evaluaciones:' . $anecaCount;
                if ($anecaUltimoResultado !== null) {
                    $evidencias[] = 'aneca_ultimo_resultado:' . $anecaUltimoResultado;
                }
            } else {
                $advertencias[] = 'sin_senal_aneca_orcid';
            }

            $score = max(0, min(100, $score));

            $result[] = [
                'profesor_id' => $profesorId,
                'nombre_mostrable' => $nombre,
                'orcid' => $orcid,
                'score_local' => $score,
                'score_mcp' => null,
                'score_final' => $score,
                'motivos' => array_values(array_unique($motivos)),
                'evidencias' => array_values(array_unique($evidencias)),
                'advertencias' => array_values(array_unique($advertencias)),
            ];
        }

        usort(
            $result,
            static function (array $a, array $b): int {
                $scoreA = (int)($a['score_local'] ?? 0);
                $scoreB = (int)($b['score_local'] ?? 0);
                if ($scoreA === $scoreB) {
                    return strcmp((string)($a['nombre_mostrable'] ?? ''), (string)($b['nombre_mostrable'] ?? ''));
                }
                return $scoreB <=> $scoreA;
            }
        );

        return $result;
    }

    /**
     * @param array<int, string> $groupKeywords
     * @param array<int, string> $candidateKeywords
     */
    private function keywordOverlapCount(array $groupKeywords, array $candidateKeywords): int
    {
        if ($groupKeywords === [] || $candidateKeywords === []) {
            return 0;
        }

        $groupSet = array_fill_keys($groupKeywords, true);
        $overlap = 0;
        foreach ($candidateKeywords as $keyword) {
            if (isset($groupSet[$keyword])) {
                $overlap++;
            }
        }

        return $overlap;
    }

    /**
     * @return array<int, string>
     */
    private function tokenizeKeywords(string $value): array
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return [];
        }

        $tokens = preg_split('/[^a-z0-9]+/i', $value) ?: [];
        $result = [];
        foreach ($tokens as $token) {
            $token = trim((string)$token);
            if ($token === '' || strlen($token) < 3) {
                continue;
            }
            $result[] = $token;
        }

        return array_values(array_unique($result));
    }

    /**
     * @param mixed $value
     */
    private function normalizeNullableString($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $normalized = trim((string)$value);
        return $normalized === '' ? null : $normalized;
    }
}

