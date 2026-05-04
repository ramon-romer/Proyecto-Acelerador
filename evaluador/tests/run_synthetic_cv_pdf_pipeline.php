<?php
declare(strict_types=1);

/**
 * Runner de validacion PDF/OCR/Pipeline sobre subbateria sintetica reducida.
 *
 * Uso:
 *   php evaluador/tests/run_synthetic_cv_pdf_pipeline.php
 *   php evaluador/tests/run_synthetic_cv_pdf_pipeline.php --json
 */

require_once __DIR__ . '/../src/compat_mbstring.php';
require_once __DIR__ . '/../src/Pipeline.php';
require_once __DIR__ . '/../src/OcrProcessor.php';
require_once __DIR__ . '/../src/AnecaExtractor.php';
require_once __DIR__ . '/../src/AnecaExtractorCsyj.php';

final class SyntheticCvPdfPipelineRunner
{
    private string $subsetManifestPath;
    private string $reportRoot;
    private bool $jsonOutput;
    private string $repoRoot;

    public function __construct(string $subsetManifestPath, string $reportRoot, bool $jsonOutput, string $repoRoot)
    {
        $this->subsetManifestPath = $subsetManifestPath;
        $this->reportRoot = $reportRoot;
        $this->jsonOutput = $jsonOutput;
        $this->repoRoot = $repoRoot;
    }

    public function run(): int
    {
        $startedAt = microtime(true);

        if (!is_file($this->subsetManifestPath)) {
            throw new RuntimeException("No existe manifest PDF subset: {$this->subsetManifestPath}");
        }

        $manifest = $this->readJson($this->subsetManifestPath);
        $cases = $manifest['cases'] ?? null;
        if (!is_array($cases)) {
            throw new RuntimeException('Manifest PDF subset invalido: falta cases[].');
        }

        $manifestDir = dirname($this->subsetManifestPath);
        $ocr = new OcrProcessor();
        $ocrDiag = $ocr->diagnosticoDisponibilidad();
        $pdftotextAvailable = $this->isPdftotextAvailable();

        $summary = [
            'total_pdfs' => 0,
            'pass' => 0,
            'warn' => 0,
            'fail' => 0,
            'skip_env' => 0,
            'by_branch' => [],
        ];

        $results = [];
        foreach ($cases as $case) {
            if (!is_array($case)) {
                continue;
            }

            $result = $this->runCase($case, $manifestDir, $ocrDiag, $pdftotextAvailable);
            $results[] = $result;

            $summary['total_pdfs']++;
            $status = $result['status'];
            $rama = (string)$result['rama'];

            if (!isset($summary['by_branch'][$rama])) {
                $summary['by_branch'][$rama] = ['total' => 0, 'pass' => 0, 'warn' => 0, 'fail' => 0, 'skip_env' => 0];
            }
            $summary['by_branch'][$rama]['total']++;

            if ($status === 'PASS') {
                $summary['pass']++;
                $summary['by_branch'][$rama]['pass']++;
            } elseif ($status === 'WARN') {
                $summary['warn']++;
                $summary['by_branch'][$rama]['warn']++;
            } elseif ($status === 'FAIL') {
                $summary['fail']++;
                $summary['by_branch'][$rama]['fail']++;
            } else {
                $summary['skip_env']++;
                $summary['by_branch'][$rama]['skip_env']++;
            }
        }

        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

        $payload = [
            'suite' => 'synthetic-cv-pdf-pipeline',
            'timestamp' => date('c'),
            'subset_manifest' => $this->subsetManifestPath,
            'environment' => [
                'pdftotext_disponible' => $pdftotextAvailable,
                'ocr' => $ocrDiag,
            ],
            'summary' => $summary + ['duration_ms' => $durationMs],
            'cases' => $results,
        ];

        $reportDir = $this->reportRoot . DIRECTORY_SEPARATOR . date('Ymd-His') . '-synthetic-cv-pdf-pipeline';
        $this->ensureDir($reportDir);
        $jsonReport = $reportDir . DIRECTORY_SEPARATOR . 'synthetic_cv_pdf_pipeline_report.json';
        $mdReport = $reportDir . DIRECTORY_SEPARATOR . 'synthetic_cv_pdf_pipeline_report.md';

        $this->writeJson($jsonReport, $payload);
        $this->writeFile($mdReport, $this->renderMarkdown($payload));

        if ($this->jsonOutput) {
            fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        } else {
            $this->printHuman($payload, $jsonReport, $mdReport);
        }

        if ($summary['fail'] > 0) {
            return 1;
        }
        return 0;
    }

    /**
     * @param array<string, mixed> $case
     * @param array<string, mixed> $ocrDiag
     * @return array<string, mixed>
     */
    private function runCase(array $case, string $manifestDir, array $ocrDiag, bool $pdftotextAvailable): array
    {
        $idCv = (string)($case['id_cv'] ?? '');
        $rama = strtoupper((string)($case['rama'] ?? ''));
        $perfil = strtolower((string)($case['perfil'] ?? ''));
        $pdfRel = (string)($case['pdf'] ?? '');
        $expectedRel = (string)($case['expected'] ?? '');

        $pdfPath = $manifestDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pdfRel);
        $expectedPath = $manifestDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedRel);

        $diffs = [];
        $status = 'PASS';

        if (!is_file($pdfPath)) {
            return [
                'id_cv' => $idCv,
                'rama' => $rama,
                'perfil' => $perfil,
                'status' => 'FAIL',
                'resultado_esperado' => null,
                'resultado_obtenido' => 'pdf_missing',
                'diferencias' => ['No existe PDF: ' . $pdfPath],
            ];
        }

        if (!is_file($expectedPath)) {
            return [
                'id_cv' => $idCv,
                'rama' => $rama,
                'perfil' => $perfil,
                'status' => 'FAIL',
                'resultado_esperado' => null,
                'resultado_obtenido' => 'expected_missing',
                'diferencias' => ['No existe expected.json: ' . $expectedPath],
            ];
        }

        $expected = $this->readJson($expectedPath);
        $resultadoEsperado = (string)($expected['resultado_esperado'] ?? 'revisar');

        $extractor = $rama === 'CSYJ' ? new AnecaExtractorCsyj() : new AnecaExtractor();
        $pipeline = new Pipeline($extractor);

        try {
            $data = $pipeline->procesar($pdfPath);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $skipEnv = $this->isEnvironmentFailure($message, $ocrDiag, $pdftotextAvailable);

            return [
                'id_cv' => $idCv,
                'rama' => $rama,
                'perfil' => $perfil,
                'status' => $skipEnv ? 'SKIP_ENV' : 'FAIL',
                'resultado_esperado' => $resultadoEsperado,
                'resultado_obtenido' => $skipEnv ? 'skip_env' : 'pipeline_error',
                'diferencias' => [$message],
                'pipeline_error' => $message,
            ];
        }

        $texto = (string)($data['texto_extraido'] ?? '');
        $ramaExtraida = $this->extractRama($texto);
        $orcidExtraido = $this->extractOrcid($texto);
        $orcidEsperado = isset($expected['orcid_esperado']) ? trim((string)$expected['orcid_esperado']) : '';

        if ($ramaExtraida === null) {
            $status = 'WARN';
            $diffs[] = 'No se pudo detectar rama en texto extraido.';
        } elseif (strtoupper($ramaExtraida) !== $rama) {
            $status = 'FAIL';
            $diffs[] = "Rama extraida distinta. esperado={$rama} obtenido={$ramaExtraida}";
        }

        if ($orcidEsperado !== '') {
            if ($orcidExtraido === null) {
                if ($status !== 'FAIL') {
                    $status = 'WARN';
                }
                $diffs[] = 'ORCID esperado no detectado en texto extraido.';
            } elseif ($orcidExtraido !== $orcidEsperado) {
                $status = 'FAIL';
                $diffs[] = "ORCID distinto. esperado={$orcidEsperado} obtenido={$orcidExtraido}";
            }
        }

        $b1 = is_array($data['bloque_1'] ?? null) ? $data['bloque_1'] : [];
        $pubs = is_array($b1['publicaciones'] ?? null) ? $b1['publicaciones'] : [];
        $projects = is_array($b1['proyectos'] ?? null) ? $b1['proyectos'] : [];

        $expectedPubCount = (int)($expected['publicaciones_esperadas_total'] ?? 0);
        $expectedProjectCount = (int)($expected['proyectos_esperados'] ?? 0);

        if ($expectedPubCount > 0 && count($pubs) === 0) {
            $status = 'FAIL';
            $diffs[] = 'El pipeline no detecto publicaciones y expected indica presencia.';
        }

        if ($expectedProjectCount > 0 && count($projects) === 0) {
            $status = 'FAIL';
            $diffs[] = 'El pipeline no detecto proyectos y expected indica presencia.';
        }

        $canonicalCheck = $this->validateCanonicalMinimal($data);
        if ($canonicalCheck !== []) {
            $status = 'FAIL';
            foreach ($canonicalCheck as $err) {
                $diffs[] = $err;
            }
        }

        $jsonGeneratedName = (string)($data['json_generado'] ?? '');
        $jsonGeneratedPath = $this->repoRoot
            . DIRECTORY_SEPARATOR . 'evaluador'
            . DIRECTORY_SEPARATOR . 'output'
            . DIRECTORY_SEPARATOR . 'json'
            . DIRECTORY_SEPARATOR . $jsonGeneratedName;

        if ($jsonGeneratedName === '' || !is_file($jsonGeneratedPath)) {
            $status = 'FAIL';
            $diffs[] = 'No se localiza JSON canónico generado por pipeline.';
        }

        if ($diffs === []) {
            $diffs[] = 'sin diferencias';
        }

        return [
            'id_cv' => $idCv,
            'rama' => $rama,
            'perfil' => $perfil,
            'status' => $status,
            'resultado_esperado' => $resultadoEsperado,
            'resultado_obtenido' => $status === 'PASS' ? 'pipeline_ok' : strtolower($status),
            'diferencias' => $diffs,
            'validacion_obtenida' => [
                'rama_extraida' => $ramaExtraida,
                'orcid_extraido' => $orcidExtraido,
                'publicaciones_detectadas' => count($pubs),
                'proyectos_detectados' => count($projects),
                'json_generado' => $jsonGeneratedName,
            ],
        ];
    }

    private function extractRama(string $text): ?string
    {
        if (preg_match('/\bRAMA\s+([A-Z_]+)/i', $text, $m) === 1) {
            return strtoupper(trim($m[1]));
        }
        return null;
    }

    private function extractOrcid(string $text): ?string
    {
        if (preg_match('/\b(\d{4}-\d{4}-\d{4}-\d{4})\b/', $text, $m) === 1) {
            return $m[1];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function validateCanonicalMinimal(array $data): array
    {
        $errors = [];
        $requiredTop = ['bloque_1', 'bloque_2', 'bloque_3', 'bloque_4', 'metadatos_extraccion'];
        foreach ($requiredTop as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = "JSON canónico incompleto: falta {$key}";
            }
        }
        return $errors;
    }

    /**
     * @param array<string, mixed> $ocrDiag
     */
    private function isEnvironmentFailure(string $message, array $ocrDiag, bool $pdftotextAvailable): bool
    {
        $msg = strtolower($message);
        if (str_contains($msg, 'ocr no esta disponible') || str_contains($msg, 'ocr no est')) {
            return true;
        }
        if (str_contains($msg, 'pdftotext') && !$pdftotextAvailable) {
            return true;
        }

        $ocrUnavailable = (($ocrDiag['pdftoppm_disponible'] ?? false) !== true)
            || (($ocrDiag['tesseract_disponible'] ?? false) !== true);

        if ($ocrUnavailable && str_contains($msg, 'no se pudo extraer texto del pdf')) {
            return true;
        }

        return false;
    }

    private function isPdftotextAvailable(): bool
    {
        $candidates = [
            'C:\\poppler-25.12.0\\Library\\bin\\pdftotext.exe',
            'C:\\poppler\\Library\\bin\\pdftotext.exe',
            'C:\\poppler\\bin\\pdftotext.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return true;
            }
        }

        $cmd = stripos(PHP_OS_FAMILY, 'Windows') === 0
            ? 'where pdftotext 2>NUL'
            : 'command -v pdftotext 2>/dev/null';

        $output = [];
        $status = 1;
        exec($cmd, $output, $status);

        return $status === 0 && !empty($output);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function printHuman(array $payload, string $jsonReport, string $mdReport): void
    {
        fwrite(STDOUT, "=== Synthetic CV PDF Pipeline ===" . PHP_EOL);
        fwrite(STDOUT, 'subset_manifest=' . (string)$payload['subset_manifest'] . PHP_EOL);
        fwrite(STDOUT, 'SYNTHETIC_CV_PDF_PIPELINE_REPORT=' . $jsonReport . PHP_EOL);
        fwrite(STDOUT, 'SYNTHETIC_CV_PDF_PIPELINE_MARKDOWN=' . $mdReport . PHP_EOL);

        foreach (($payload['cases'] ?? []) as $case) {
            if (!is_array($case)) {
                continue;
            }
            fwrite(
                STDOUT,
                sprintf(
                    "[%s] %s | rama=%s | perfil=%s | esperado=%s | obtenido=%s",
                    (string)$case['status'],
                    (string)$case['id_cv'],
                    (string)$case['rama'],
                    (string)$case['perfil'],
                    (string)($case['resultado_esperado'] ?? 'n/a'),
                    (string)($case['resultado_obtenido'] ?? 'n/a')
                ) . PHP_EOL
            );

            foreach (($case['diferencias'] ?? []) as $diff) {
                fwrite(STDOUT, '  - ' . (string)$diff . PHP_EOL);
            }
        }

        $summary = $payload['summary'] ?? [];
        fwrite(STDOUT, PHP_EOL . '=== Resumen Global ===' . PHP_EOL);
        fwrite(STDOUT, 'total_pdfs=' . (int)($summary['total_pdfs'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, 'pass=' . (int)($summary['pass'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, 'warn=' . (int)($summary['warn'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, 'fail=' . (int)($summary['fail'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, 'skip_env=' . (int)($summary['skip_env'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, 'duration_ms=' . (int)($summary['duration_ms'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, 'by_branch=' . json_encode($summary['by_branch'] ?? [], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderMarkdown(array $payload): string
    {
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];

        $lines = [];
        $lines[] = '# Synthetic CV PDF Pipeline';
        $lines[] = '';
        $lines[] = '- timestamp: ' . (string)($payload['timestamp'] ?? '');
        $lines[] = '- total_pdfs: ' . (string)($summary['total_pdfs'] ?? 0);
        $lines[] = '- pass: ' . (string)($summary['pass'] ?? 0);
        $lines[] = '- warn: ' . (string)($summary['warn'] ?? 0);
        $lines[] = '- fail: ' . (string)($summary['fail'] ?? 0);
        $lines[] = '- skip_env: ' . (string)($summary['skip_env'] ?? 0);
        $lines[] = '- duration_ms: ' . (string)($summary['duration_ms'] ?? 0);
        $lines[] = '';
        $lines[] = '## By Branch';
        foreach (($summary['by_branch'] ?? []) as $rama => $stats) {
            if (!is_array($stats)) {
                continue;
            }
            $lines[] = '- ' . $rama
                . ': total=' . (string)($stats['total'] ?? 0)
                . ', pass=' . (string)($stats['pass'] ?? 0)
                . ', warn=' . (string)($stats['warn'] ?? 0)
                . ', fail=' . (string)($stats['fail'] ?? 0)
                . ', skip_env=' . (string)($stats['skip_env'] ?? 0);
        }
        $lines[] = '';
        $lines[] = '## Cases';
        foreach (($payload['cases'] ?? []) as $case) {
            if (!is_array($case)) {
                continue;
            }
            $lines[] = '- [' . (string)$case['status'] . '] '
                . (string)$case['id_cv']
                . ' (' . (string)$case['rama'] . ') => '
                . implode(' | ', array_map('strval', is_array($case['diferencias'] ?? null) ? $case['diferencias'] : []));
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

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException("JSON invalido: {$path}");
        }

        return $decoded;
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
        $ok = file_put_contents($path, $content);
        if ($ok === false) {
            throw new RuntimeException("No se pudo escribir archivo: {$path}");
        }
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException("No se pudo crear directorio: {$path}");
        }
    }
}

/**
 * @return array{subsetManifest:string,reportRoot:string,json:bool}
 */
function parseArgs(array $argv): array
{
    $opts = getopt('', ['subset-manifest::', 'report-root::', 'json']);
    $repoRoot = dirname(__DIR__, 2);

    $subsetManifest = isset($opts['subset-manifest']) && is_string($opts['subset-manifest']) && $opts['subset-manifest'] !== ''
        ? resolvePath($opts['subset-manifest'])
        : $repoRoot
            . DIRECTORY_SEPARATOR . 'evaluador'
            . DIRECTORY_SEPARATOR . 'tests'
            . DIRECTORY_SEPARATOR . 'fixtures'
            . DIRECTORY_SEPARATOR . 'cv_sinteticos_pdf'
            . DIRECTORY_SEPARATOR . 'pdf_subset_manifest.json';

    $reportRoot = isset($opts['report-root']) && is_string($opts['report-root']) && $opts['report-root'] !== ''
        ? resolvePath($opts['report-root'])
        : $repoRoot . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'test-validation';

    return [
        'subsetManifest' => $subsetManifest,
        'reportRoot' => $reportRoot,
        'json' => array_key_exists('json', $opts),
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
    return (bool)preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
        || str_starts_with($path, '\\\\')
        || str_starts_with($path, '/');
}

try {
    $args = parseArgs($argv);
    $runner = new SyntheticCvPdfPipelineRunner(
        $args['subsetManifest'],
        $args['reportRoot'],
        $args['json'],
        dirname(__DIR__, 2)
    );
    exit($runner->run());
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
