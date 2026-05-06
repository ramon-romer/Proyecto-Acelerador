<?php
declare(strict_types=1);

use Acelerador\PanelBackend\Application\DTO\MatchingRequestInput;
use Acelerador\PanelBackend\Application\Matching\MatchingOrchestrator;
use Acelerador\PanelBackend\Application\Matching\MatchingPrivacyFilter;
use Acelerador\PanelBackend\Application\Matching\McpMatchingAssistant;
use Acelerador\PanelBackend\Application\Matching\ResearchGroupMatchingService;

require dirname(__DIR__) . '/src/Autoload.php';

function assertCheck(array &$checks, string $name, bool $ok): void
{
    $checks[] = [
        'name' => $name,
        'ok' => $ok,
    ];
}

$checks = [];
$aggregated = [
    'grupo_objetivo' => [
        'id' => 10,
        'nombre' => 'Grupo Inteligencia Artificial',
        'descripcion' => 'Lineas de IA aplicada y ciencia de datos',
    ],
    'fuentes_usadas' => [
        'perfil_profesor' => true,
        'grupos' => true,
        'evaluacion_aneca' => false,
        'cv_procesado' => false,
        'publicaciones' => false,
        'fuentes_externas' => false,
    ],
    'fuentes_faltantes' => ['evaluaciones_aneca_por_orcid_no_disponible'],
    'profiles' => [
        [
            'profesor_id' => 101,
            'nombre_mostrable' => 'Ana Perez',
            'orcid' => '0000-0001-1234-5678',
            'departamento' => 'Inteligencia Artificial',
            'ya_asignado_grupo' => false,
            'aneca_evaluaciones_count' => 2,
            'aneca_ultimo_resultado' => 'POSITIVA',
            'aneca_signal_available' => true,
        ],
        [
            'profesor_id' => 102,
            'nombre_mostrable' => 'Luis Gomez',
            'orcid' => null,
            'departamento' => null,
            'ya_asignado_grupo' => false,
            'aneca_evaluaciones_count' => 0,
            'aneca_ultimo_resultado' => null,
            'aneca_signal_available' => true,
        ],
    ],
];

$baselineService = new ResearchGroupMatchingService();
$privacyFilter = new MatchingPrivacyFilter();
$mcpUnavailable = new McpMatchingAssistant('http://127.0.0.1:65534', 200);
$orchestrator = new MatchingOrchestrator($baselineService, $mcpUnavailable, $privacyFilter);

$localInput = new MatchingRequestInput(MatchingRequestInput::MODE_LOCAL_ONLY, 20, null, false, false);
$autoInput = new MatchingRequestInput(MatchingRequestInput::MODE_AUTO, 20, null, false, false);
$mcpInput = new MatchingRequestInput(MatchingRequestInput::MODE_MCP, 20, null, false, false);

$localResult = $orchestrator->recommend($aggregated, $localInput);
$autoResult = $orchestrator->recommend($aggregated, $autoInput);
$mcpResult = $orchestrator->recommend($aggregated, $mcpInput);

$localCandidates = is_array($localResult['candidatos'] ?? null) ? $localResult['candidatos'] : [];
$autoCandidates = is_array($autoResult['candidatos'] ?? null) ? $autoResult['candidatos'] : [];

assertCheck(
    $checks,
    'matching_local_devuelve_estructura_controlada',
    count($localCandidates) === 2
        && isset($localCandidates[0]['profesor_id'], $localCandidates[0]['nombre_mostrable'], $localCandidates[0]['score_local'])
        && is_array($localCandidates[0]['motivos'] ?? null)
        && is_array($localCandidates[0]['evidencias'] ?? null)
        && is_array($localCandidates[0]['advertencias'] ?? null)
);

assertCheck(
    $checks,
    'matching_auto_devuelve_baseline_si_mcp_no_esta',
    count($autoCandidates) === count($localCandidates)
        && (bool)($autoResult['trace']['mcp_intentado'] ?? false) === true
        && (bool)($autoResult['trace']['mcp_disponible'] ?? true) === false
        && (bool)($autoResult['trace']['fallback_activado'] ?? false) === true
);

$scoresUnchanged = true;
for ($i = 0; $i < count($autoCandidates); $i++) {
    $scoreLocal = (int)($autoCandidates[$i]['score_local'] ?? -1);
    $scoreFinal = (int)($autoCandidates[$i]['score_final'] ?? -2);
    if ($scoreLocal !== $scoreFinal) {
        $scoresUnchanged = false;
        break;
    }
}
assertCheck(
    $checks,
    'mcp_no_cambia_puntuacion_final',
    $scoresUnchanged
);

assertCheck(
    $checks,
    'matching_mcp_fuerza_mcp_y_marca_error_controlado',
    (bool)($mcpResult['trace']['mcp_intentado'] ?? false) === true
        && (bool)($mcpResult['trace']['mcp_disponible'] ?? true) === false
        && (bool)($mcpResult['trace']['mcp_required_failed'] ?? false) === true
);

$ok = true;
foreach ($checks as $check) {
    if (empty($check['ok'])) {
        $ok = false;
        break;
    }
}

echo json_encode(
    [
        'ok' => $ok,
        'checks' => $checks,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) . PHP_EOL;

exit($ok ? 0 : 1);
