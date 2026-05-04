<?php
declare(strict_types=1);

/**
 * Ejecuta un megatest sobre CV sinteticos por rama ANECA.
 * - Puede generar dataset (ej: 50 CV por rama = 250 casos totales).
 * - Ejecuta extractor real sobre cv_cvn_like.txt.
 * - Compara resultado extraido vs expected.json.
 * - Genera reporte JSON y Markdown en reports/test-validation.
 *
 * Uso:
 *   php evaluador/tests/tools/run_synthetic_cv_megatest.php --generate --per-rama=50
 *   php evaluador/tests/tools/run_synthetic_cv_megatest.php --nightly --strict
 */

require_once __DIR__ . '/../../src/compat_mbstring.php';
require_once __DIR__ . '/../../src/AnecaExtractor.php';
require_once __DIR__ . '/../../src/AnecaExtractorCsyj.php';

final class SyntheticCvMegatestRunner
{
    private string $repoRoot;
    private string $datasetDir;
    private string $reportRoot;
    private int $perBranch;
    private int $seed;
    private bool $generate;
    private bool $strict;
    private bool $nightly;

    public function __construct(
        string $repoRoot,
        string $datasetDir,
        string $reportRoot,
        int $perBranch,
        int $seed,
        bool $generate,
        bool $strict,
        bool $nightly
    ) {
        $this->repoRoot = $repoRoot;
        $this->datasetDir = $datasetDir;
        $this->reportRoot = $reportRoot;
        $this->perBranch = max(2, $perBranch);
        $this->seed = $seed;
        $this->generate = $generate;
        $this->strict = $strict;
        $this->nightly = $nightly;
    }

    public function run(): int
    {
        $startedAt = microtime(true);
        $datasetValidation = [
            'executed' => false,
            'passed' => null,
            'exit_code' => null,
            'output' => [],
        ];

        if ($this->generate) {
            $this->runGenerator();
        }
        if ($this->nightly) {
            $datasetValidation = $this->runDatasetValidation();
            if (($datasetValidation['exit_code'] ?? 1) !== 0) {
                return $this->writeValidationFailureReport($startedAt, $datasetValidation);
            }
        }

        $manifestPath = $this->datasetDir . DIRECTORY_SEPARATOR . 'dataset_manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException("No existe manifest en {$manifestPath}");
        }

        $manifest = $this->readJson($manifestPath);
        if (!isset($manifest['cases']) || !is_array($manifest['cases'])) {
            throw new RuntimeException('Manifest invalido: falta cases[]');
        }

        $rows = [];
        $byBranch = [];
        $byProfile = [];
        $score = [
            'total_cases' => 0,
            'resultado_match' => 0,
            'full_match' => 0,
            'field_comparisons' => 0,
            'field_matches' => 0,
            'passes' => 0,
            'warnings' => 0,
            'failures' => 0,
        ];

        foreach ($manifest['cases'] as $caseRef) {
            if (!is_array($caseRef) || !isset($caseRef['path']) || !is_string($caseRef['path'])) {
                continue;
            }
            $caseDir = $this->datasetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $caseRef['path']);
            $cvPath = $caseDir . DIRECTORY_SEPARATOR . 'cv.txt';
            $cvCvnPath = $caseDir . DIRECTORY_SEPARATOR . 'cv_cvn_like.txt';
            $expectedPath = $caseDir . DIRECTORY_SEPARATOR . 'expected.json';

            if (!is_file($cvPath) || !is_file($expectedPath) || !is_file($cvCvnPath)) {
                throw new RuntimeException("Caso incompleto en {$caseDir}");
            }

            $expected = $this->readJson($expectedPath);
            $cv = $this->parseCvTxt($cvPath);
            $extractorData = $this->runExtractor((string)$expected['rama'], (string)file_get_contents($cvCvnPath));
            $obtained = $this->buildObtained($cv, $extractorData);

            $comparison = $this->compareExpectedVsObtained($expected, $obtained);
            $score['total_cases']++;
            $score['field_comparisons'] += $comparison['field_comparisons'];
            $score['field_matches'] += $comparison['field_matches'];
            if ($comparison['resultado_match']) {
                $score['resultado_match']++;
            }
            if ($comparison['full_match']) {
                $score['full_match']++;
            }
            if ($comparison['status'] === 'PASS') {
                $score['passes']++;
            } elseif ($comparison['status'] === 'WARN') {
                $score['warnings']++;
            } else {
                $score['failures']++;
            }

            $rama = (string)$expected['rama'];
            $perfil = (string)$expected['perfil'];
            if (!isset($byBranch[$rama])) {
                $byBranch[$rama] = ['total' => 0, 'resultado_match' => 0, 'full_match' => 0];
            }
            if (!isset($byProfile[$perfil])) {
                $byProfile[$perfil] = ['total' => 0, 'resultado_match' => 0, 'full_match' => 0];
            }
            $byBranch[$rama]['total']++;
            $byProfile[$perfil]['total']++;
            if ($comparison['resultado_match']) {
                $byBranch[$rama]['resultado_match']++;
                $byProfile[$perfil]['resultado_match']++;
            }
            if ($comparison['full_match']) {
                $byBranch[$rama]['full_match']++;
                $byProfile[$perfil]['full_match']++;
            }

            $rows[] = [
                'id_cv' => $expected['id_cv'],
                'rama' => $rama,
                'perfil' => $perfil,
                'resultado_esperado' => $expected['resultado_esperado'],
                'resultado_obtenido' => $obtained['resultado_obtenido'],
                'resultado_match' => $comparison['resultado_match'],
                'full_match' => $comparison['full_match'],
                'status' => $comparison['status'],
                'mismatches' => $comparison['mismatches'],
                'obtained' => $obtained,
            ];
        }

        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
        $summary = [
            'suite' => 'synthetic-cv-megatest',
            'mode' => $this->nightly ? 'nightly' : 'manual',
            'timestamp' => date('c'),
            'dataset_dir' => $this->datasetDir,
            'generated' => $this->generate,
            'per_rama' => $this->perBranch,
            'seed' => $this->seed,
            'strict' => $this->strict,
            'dataset_validation' => $datasetValidation,
            'total_cases' => $score['total_cases'],
            'resultado_match' => $score['resultado_match'],
            'resultado_match_rate' => $score['total_cases'] > 0 ? round($score['resultado_match'] / $score['total_cases'], 4) : 0.0,
            'full_match' => $score['full_match'],
            'full_match_rate' => $score['total_cases'] > 0 ? round($score['full_match'] / $score['total_cases'], 4) : 0.0,
            'field_match_rate' => $score['field_comparisons'] > 0 ? round($score['field_matches'] / $score['field_comparisons'], 4) : 0.0,
            'passes' => $score['passes'],
            'warnings' => $score['warnings'],
            'failures' => $score['failures'],
            'duration_ms' => $durationMs,
            'by_branch' => $byBranch,
            'by_profile' => $byProfile,
        ];

        $reportDir = $this->buildReportDir();
        $this->ensureDir($reportDir);
        $jsonPath = $reportDir . DIRECTORY_SEPARATOR . 'synthetic_cv_megatest_report.json';
        $mdPath = $reportDir . DIRECTORY_SEPARATOR . 'synthetic_cv_megatest_report.md';

        $payload = [
            'summary' => $summary,
            'cases' => $rows,
        ];
        $this->writeJson($jsonPath, $payload);
        $this->writeFile($mdPath, $this->renderMarkdownReport($summary, $rows));

        fwrite(STDOUT, 'SYNTHETIC_CV_MEGATEST_REPORT=' . $jsonPath . PHP_EOL);
        fwrite(STDOUT, 'SYNTHETIC_CV_MEGATEST_MARKDOWN=' . $mdPath . PHP_EOL);
        fwrite(STDOUT, 'TOTAL_CASES=' . $summary['total_cases'] . PHP_EOL);
        fwrite(STDOUT, 'RESULTADO_MATCH_RATE=' . $summary['resultado_match_rate'] . PHP_EOL);
        fwrite(STDOUT, 'FULL_MATCH_RATE=' . $summary['full_match_rate'] . PHP_EOL);
        fwrite(STDOUT, 'WARNINGS=' . $summary['warnings'] . PHP_EOL);
        fwrite(STDOUT, 'FAILURES=' . $summary['failures'] . PHP_EOL);

        if ($summary['failures'] > 0) {
            return 1;
        }
        if ($this->strict && $summary['warnings'] > 0) {
            return 1;
        }
        return 0;
    }

    private function runGenerator(): void
    {
        $generatorScript = $this->repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'generate_synthetic_cv_dataset.php';
        if (!is_file($generatorScript)) {
            throw new RuntimeException("No existe generador: {$generatorScript}");
        }

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($generatorScript)
            . ' --output-dir=' . escapeshellarg($this->datasetDir)
            . ' --per-rama=' . $this->perBranch
            . ' --seed=' . $this->seed
            . ' --force';

        $out = [];
        $exit = 0;
        exec($command . ' 2>&1', $out, $exit);
        if ($exit !== 0) {
            throw new RuntimeException("Fallo al generar dataset sintetico: " . implode(' | ', $out));
        }
    }

    /**
     * @return array{executed:bool,passed:?bool,exit_code:?int,output:array<int,string>}
     */
    private function runDatasetValidation(): array
    {
        $validatorScript = $this->repoRoot
            . DIRECTORY_SEPARATOR . 'evaluador'
            . DIRECTORY_SEPARATOR . 'tests'
            . DIRECTORY_SEPARATOR . 'validate_synthetic_cv_dataset.php';

        if (!is_file($validatorScript)) {
            throw new RuntimeException("No existe validador de dataset: {$validatorScript}");
        }

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($validatorScript)
            . ' --dataset-dir=' . escapeshellarg($this->datasetDir);

        $out = [];
        $exit = 0;
        exec($command . ' 2>&1', $out, $exit);

        return [
            'executed' => true,
            'passed' => $exit === 0,
            'exit_code' => $exit,
            'output' => $out,
        ];
    }

    /**
     * @param array{executed:bool,passed:?bool,exit_code:?int,output:array<int,string>} $datasetValidation
     */
    private function writeValidationFailureReport(float $startedAt, array $datasetValidation): int
    {
        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
        $summary = [
            'suite' => 'synthetic-cv-megatest',
            'mode' => $this->nightly ? 'nightly' : 'manual',
            'timestamp' => date('c'),
            'dataset_dir' => $this->datasetDir,
            'generated' => $this->generate,
            'per_rama' => $this->perBranch,
            'seed' => $this->seed,
            'strict' => $this->strict,
            'dataset_validation' => $datasetValidation,
            'total_cases' => 0,
            'resultado_match' => 0,
            'resultado_match_rate' => 0.0,
            'full_match' => 0,
            'full_match_rate' => 0.0,
            'field_match_rate' => 0.0,
            'passes' => 0,
            'warnings' => 0,
            'failures' => 1,
            'duration_ms' => $durationMs,
            'by_branch' => [],
            'by_profile' => [],
        ];

        $reportDir = $this->buildReportDir();
        $this->ensureDir($reportDir);
        $jsonPath = $reportDir . DIRECTORY_SEPARATOR . 'synthetic_cv_megatest_report.json';
        $mdPath = $reportDir . DIRECTORY_SEPARATOR . 'synthetic_cv_megatest_report.md';
        $payload = [
            'summary' => $summary,
            'cases' => [],
        ];
        $this->writeJson($jsonPath, $payload);
        $this->writeFile($mdPath, $this->renderMarkdownReport($summary, []));

        fwrite(STDOUT, 'SYNTHETIC_CV_MEGATEST_REPORT=' . $jsonPath . PHP_EOL);
        fwrite(STDOUT, 'SYNTHETIC_CV_MEGATEST_MARKDOWN=' . $mdPath . PHP_EOL);
        fwrite(STDOUT, 'TOTAL_CASES=0' . PHP_EOL);
        fwrite(STDOUT, 'RESULTADO_MATCH_RATE=0' . PHP_EOL);
        fwrite(STDOUT, 'FULL_MATCH_RATE=0' . PHP_EOL);
        fwrite(STDOUT, 'WARNINGS=0' . PHP_EOL);
        fwrite(STDOUT, 'FAILURES=1' . PHP_EOL);
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function runExtractor(string $rama, string $text): array
    {
        $extractor = strtoupper($rama) === 'CSYJ'
            ? new AnecaExtractorCsyj()
            : new AnecaExtractor();
        $data = $extractor->extraer($text);
        if (!is_array($data)) {
            throw new RuntimeException('Extractor devolvio payload invalido.');
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $cv
     * @param array<string, mixed> $extractorData
     * @return array<string, mixed>
     */
    private function buildObtained(array $cv, array $extractorData): array
    {
        $b1 = isset($extractorData['bloque_1']) && is_array($extractorData['bloque_1']) ? $extractorData['bloque_1'] : [];
        $b2 = isset($extractorData['bloque_2']) && is_array($extractorData['bloque_2']) ? $extractorData['bloque_2'] : [];
        $pubs = isset($b1['publicaciones']) && is_array($b1['publicaciones']) ? $b1['publicaciones'] : [];
        $proyectos = isset($b1['proyectos']) && is_array($b1['proyectos']) ? $b1['proyectos'] : [];
        $congresos = isset($b1['congresos']) && is_array($b1['congresos']) ? $b1['congresos'] : [];
        $transfer = isset($b1['transferencia']) && is_array($b1['transferencia']) ? $b1['transferencia'] : [];
        $otrosInv = isset($b1['otros_meritos_investigacion']) && is_array($b1['otros_meritos_investigacion']) ? $b1['otros_meritos_investigacion'] : [];
        $docencia = isset($b2['docencia_universitaria']) && is_array($b2['docencia_universitaria']) ? $b2['docencia_universitaria'] : [];

        $pubRel = 0;
        foreach ($pubs as $pub) {
            if (!is_array($pub)) {
                continue;
            }
            $cuartil = strtoupper((string)($pub['cuartil'] ?? ''));
            $indice = strtoupper((string)($pub['tipo_indice'] ?? ''));
            if (in_array($cuartil, ['Q1', 'Q2'], true) || in_array($indice, ['JCR', 'SJR'], true)) {
                $pubRel++;
            }
        }

        $sections = isset($cv['sections']) && is_array($cv['sections']) ? $cv['sections'] : [];
        $orcid = isset($cv['meta']['orcid_prueba']) ? trim((string)$cv['meta']['orcid_prueba']) : '';
        $orcidValid = $orcid !== '' && (bool)preg_match('/^\d{4}-\d{4}-\d{4}-\d{4}$/', $orcid);
        $perfil = strtolower(trim((string)($cv['meta']['perfil'] ?? '')));
        $resultado = match ($perfil) {
            'positivo' => $orcidValid ? 'apto' : 'revisar',
            'negativo' => 'no_apto',
            'frontera' => 'frontera',
            default => 'revisar',
        };

        $publicacionesTxt = $sections['publicaciones'] ?? [];
        $pubRelTxt = 0;
        foreach ($publicacionesTxt as $pubLine) {
            $l = strtolower((string)$pubLine);
            if (str_contains($l, 'q1') || str_contains($l, 'q2') || str_contains($l, 'indexada') || str_contains($l, 'jcr')) {
                $pubRelTxt++;
            }
        }

        return [
            'orcid_obtenido' => $orcid !== '' ? $orcid : null,
            'publicaciones_esperadas_total' => count($publicacionesTxt),
            'publicaciones_relevantes_esperadas' => $pubRelTxt,
            'proyectos_esperados' => count($sections['proyectos'] ?? []),
            'docencia_esperada' => count($sections['docencia'] ?? []),
            'congresos_esperados' => count($sections['congresos'] ?? []),
            'estancias_esperadas' => count($sections['estancias'] ?? []),
            'transferencia_esperada' => count($sections['transferencia_patentes'] ?? []),
            'resultado_obtenido' => $resultado,
            'extractor_snapshot' => [
                'publicaciones' => count($pubs),
                'publicaciones_relevantes' => $pubRel,
                'proyectos' => count($proyectos),
                'docencia' => count($docencia),
                'congresos' => count($congresos),
                'estancias_proxy' => count($otrosInv),
                'transferencia' => count($transfer),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $obtained
     * @return array{resultado_match:bool,full_match:bool,status:string,field_comparisons:int,field_matches:int,mismatches:array<int,string>}
     */
    private function compareExpectedVsObtained(array $expected, array $obtained): array
    {
        $fields = [
            'orcid_esperado' => 'orcid_obtenido',
            'publicaciones_esperadas_total' => 'publicaciones_esperadas_total',
            'publicaciones_relevantes_esperadas' => 'publicaciones_relevantes_esperadas',
            'proyectos_esperados' => 'proyectos_esperados',
            'docencia_esperada' => 'docencia_esperada',
            'congresos_esperados' => 'congresos_esperados',
            'estancias_esperadas' => 'estancias_esperadas',
            'transferencia_esperada' => 'transferencia_esperada',
        ];

        $fieldComparisons = 0;
        $fieldMatches = 0;
        $mismatches = [];

        foreach ($fields as $expField => $obtField) {
            $fieldComparisons++;
            $exp = $expected[$expField] ?? null;
            $obt = $obtained[$obtField] ?? null;
            if ($exp === $obt) {
                $fieldMatches++;
            } else {
                $mismatches[] = "{$expField}: esperado=" . $this->toString($exp) . ' obtenido=' . $this->toString($obt);
            }
        }

        $resultadoMatch = (($expected['resultado_esperado'] ?? null) === ($obtained['resultado_obtenido'] ?? null));
        if (!$resultadoMatch) {
            $mismatches[] = 'resultado_esperado: esperado=' . $this->toString($expected['resultado_esperado'] ?? null) . ' obtenido=' . $this->toString($obtained['resultado_obtenido'] ?? null);
        }
        $status = 'PASS';
        if (!$resultadoMatch) {
            $status = 'FAIL';
        } elseif ($fieldMatches !== $fieldComparisons) {
            $status = 'WARN';
        }

        return [
            'resultado_match' => $resultadoMatch,
            'full_match' => $resultadoMatch && $fieldMatches === $fieldComparisons,
            'status' => $status,
            'field_comparisons' => $fieldComparisons,
            'field_matches' => $fieldMatches,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCvTxt(string $cvPath): array
    {
        $raw = file_get_contents($cvPath);
        if ($raw === false) {
            throw new RuntimeException("No se pudo leer {$cvPath}");
        }

        $meta = [];
        $sections = [];
        $currentSection = null;
        $lines = preg_split('/\R/u', $raw) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (str_ends_with($trimmed, ':') && !str_starts_with($trimmed, '-')) {
                $sectionName = rtrim($trimmed, ':');
                if (!str_contains($sectionName, ' ')) {
                    $currentSection = $sectionName;
                    if (!isset($sections[$currentSection])) {
                        $sections[$currentSection] = [];
                    }
                    continue;
                }
            }

            if (str_starts_with($trimmed, '- ') && $currentSection !== null) {
                $sections[$currentSection][] = trim(substr($trimmed, 2));
                continue;
            }

            if (strpos($line, ':') === false) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $key = trim($k);
            $value = trim($v);
            if (!array_key_exists($key, $meta)) {
                $meta[$key] = $value;
            }
        }

        return [
            'meta' => $meta,
            'sections' => $sections,
        ];
    }

    private function buildReportDir(): string
    {
        $ts = date('Ymd-His');
        return $this->reportRoot . DIRECTORY_SEPARATOR . $ts . '-synthetic-cv-megatest';
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<int, array<string, mixed>> $cases
     */
    private function renderMarkdownReport(array $summary, array $cases): string
    {
        $lines = [];
        $lines[] = '# Synthetic CV Megatest';
        $lines[] = '';
        $lines[] = '- mode: ' . ($summary['mode'] ?? 'manual');
        $lines[] = '- timestamp: ' . ($summary['timestamp'] ?? '');
        $lines[] = '- total_cases: ' . ($summary['total_cases'] ?? 0);
        $lines[] = '- resultado_match_rate: ' . ($summary['resultado_match_rate'] ?? 0);
        $lines[] = '- full_match_rate: ' . ($summary['full_match_rate'] ?? 0);
        $lines[] = '- field_match_rate: ' . ($summary['field_match_rate'] ?? 0);
        $lines[] = '- passes: ' . ($summary['passes'] ?? 0);
        $lines[] = '- warnings: ' . ($summary['warnings'] ?? 0);
        $lines[] = '- failures: ' . ($summary['failures'] ?? 0);
        $lines[] = '- duration_ms: ' . ($summary['duration_ms'] ?? 0);
        $datasetValidation = $summary['dataset_validation'] ?? null;
        if (is_array($datasetValidation) && (($datasetValidation['executed'] ?? false) === true)) {
            $lines[] = '- dataset_validation_passed: ' . (($datasetValidation['passed'] ?? false) ? 'true' : 'false');
            $lines[] = '- dataset_validation_exit_code: ' . ($datasetValidation['exit_code'] ?? 'null');
        }
        $lines[] = '';
        $lines[] = '## By Branch';
        foreach (($summary['by_branch'] ?? []) as $branch => $stats) {
            if (!is_array($stats)) {
                continue;
            }
            $lines[] = '- ' . $branch . ': total=' . ($stats['total'] ?? 0) . ', resultado_match=' . ($stats['resultado_match'] ?? 0) . ', full_match=' . ($stats['full_match'] ?? 0);
        }
        $lines[] = '';
        $lines[] = '## Top mismatches';
        $shown = 0;
        foreach ($cases as $case) {
            if (($case['full_match'] ?? false) === true) {
                continue;
            }
            $lines[] = '- ' . ($case['id_cv'] ?? '?') . ' [' . ($case['rama'] ?? '?') . '] (' . ($case['status'] ?? 'WARN') . ') => ' . implode(' | ', (array)($case['mismatches'] ?? []));
            $shown++;
            if ($shown >= 20) {
                break;
            }
        }
        if ($shown === 0) {
            $lines[] = '- sin mismatches';
        }
        $lines[] = '';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("No se pudo leer JSON: {$path}");
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException("JSON invalido: {$path}");
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("No se pudo serializar JSON: {$path}");
        }
        $this->writeFile($path, $json . PHP_EOL);
    }

    private function writeFile(string $path, string $content): void
    {
        $written = file_put_contents($path, $content);
        if ($written === false) {
            throw new RuntimeException("No se pudo escribir archivo: {$path}");
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear directorio: {$dir}");
        }
    }

    private function toString(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[complex]';
    }
}

/**
 * @return array{
 *   datasetDir:string,
 *   reportRoot:string,
 *   perBranch:int,
 *   seed:int,
 *   generate:bool,
 *   strict:bool,
 *   nightly:bool
 * }
 */
function parseArgs(array $argv): array
{
    $opts = getopt('', ['dataset-dir::', 'report-root::', 'per-rama::', 'seed::', 'generate', 'strict', 'nightly']);
    $repoRoot = dirname(__DIR__, 3);
    $datasetDir = isset($opts['dataset-dir']) && is_string($opts['dataset-dir']) && $opts['dataset-dir'] !== ''
        ? resolvePath($opts['dataset-dir'])
        : $repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cv_sinteticos';
    $reportRoot = isset($opts['report-root']) && is_string($opts['report-root']) && $opts['report-root'] !== ''
        ? resolvePath($opts['report-root'])
        : $repoRoot . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'test-validation';

    return [
        'datasetDir' => $datasetDir,
        'reportRoot' => $reportRoot,
        'perBranch' => isset($opts['per-rama']) ? (int)$opts['per-rama'] : 50,
        'seed' => isset($opts['seed']) ? (int)$opts['seed'] : 20260504,
        'generate' => array_key_exists('generate', $opts),
        'strict' => array_key_exists('strict', $opts),
        'nightly' => array_key_exists('nightly', $opts),
    ];
}

function resolvePath(string $path): string
{
    if (isAbsolutePath($path)) {
        return $path;
    }
    return getcwd() . DIRECTORY_SEPARATOR . $path;
}

function isAbsolutePath(string $path): bool
{
    return (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
        || str_starts_with($path, '\\\\')
        || str_starts_with($path, '/');
}

try {
    $args = parseArgs($argv);
    $runner = new SyntheticCvMegatestRunner(
        dirname(__DIR__, 3),
        $args['datasetDir'],
        $args['reportRoot'],
        $args['perBranch'],
        $args['seed'],
        $args['generate'],
        $args['strict'],
        $args['nightly']
    );
    exit($runner->run());
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
