<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\Matching;

final class MatchingPrivacyFilter
{
    /**
     * @param array<string, mixed> $aggregated
     * @param array<int, array<string, mixed>> $candidates
     * @return array<string, mixed>
     */
    public function buildMcpPayload(array $aggregated, array $candidates): array
    {
        $group = is_array($aggregated['grupo_objetivo'] ?? null) ? $aggregated['grupo_objetivo'] : [];
        $groupName = (string)($group['nombre'] ?? '');
        $groupDescription = (string)($group['descripcion'] ?? '');

        $minimizedCandidates = [];
        $limit = min(10, count($candidates));
        for ($i = 0; $i < $limit; $i++) {
            $candidate = $candidates[$i];
            $minimizedCandidates[] = [
                'profesor_id' => (int)($candidate['profesor_id'] ?? 0),
                'orcid' => $candidate['orcid'] ?? null,
                'score_local' => (int)($candidate['score_local'] ?? 0),
            ];
        }

        return [
            'grupo_objetivo' => [
                'id' => $group['id'] ?? null,
                'nombre' => $groupName,
                'keywords' => $this->keywords($groupName . ' ' . $groupDescription),
            ],
            'candidatos' => $minimizedCandidates,
            'fuentes' => $aggregated['fuentes_usadas'] ?? [],
            'datos_minimizados' => true,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function keywords(string $value): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($value)) ?: [];
        $keywords = [];
        foreach ($tokens as $token) {
            $token = trim((string)$token);
            if ($token === '' || strlen($token) < 3) {
                continue;
            }
            $keywords[] = $token;
        }
        return array_values(array_unique($keywords));
    }
}

