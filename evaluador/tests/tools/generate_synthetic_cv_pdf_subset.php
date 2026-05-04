<?php
declare(strict_types=1);

/**
 * Genera una subbateria reducida de PDFs sinteticos (2 casos por rama) a partir
 * del dataset de cv_sinteticos, sin tocar el core del evaluador.
 *
 * Uso:
 *   php evaluador/tests/tools/generate_synthetic_cv_pdf_subset.php --force
 */

require_once __DIR__ . '/../../../vendor/setasign/fpdf/fpdf.php';

final class DeterministicFpdf extends FPDF
{
    protected function _putinfo(): void
    {
        $this->metadata['Producer'] = 'FPDF ' . FPDF_VERSION;
        $this->metadata['CreationDate'] = 'D:20260101000000';
        foreach ($this->metadata as $key => $value) {
            $this->_put('/' . $key . ' ' . $this->_textstring((string)$value));
        }
    }
}

final class SyntheticCvPdfSubsetGenerator
{
    private string $sourceDatasetDir;
    private string $outputDir;
    private bool $force;

    public function __construct(string $sourceDatasetDir, string $outputDir, bool $force)
    {
        $this->sourceDatasetDir = $sourceDatasetDir;
        $this->outputDir = $outputDir;
        $this->force = $force;
    }

    public function run(): int
    {
        $manifestPath = $this->sourceDatasetDir . DIRECTORY_SEPARATOR . 'dataset_manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException("No existe dataset_manifest.json en {$this->sourceDatasetDir}");
        }

        if (is_dir($this->outputDir)) {
            $existing = glob($this->outputDir . DIRECTORY_SEPARATOR . '*');
            if (!$this->force && !empty($existing)) {
                throw new RuntimeException('El directorio de salida ya contiene archivos. Usa --force para regenerar.');
            }
            $this->deleteDirectoryContents($this->outputDir);
        }
        $this->ensureDir($this->outputDir);

        $sourceManifest = $this->readJson($manifestPath);
        $cases = $sourceManifest['cases'] ?? null;
        if (!is_array($cases)) {
            throw new RuntimeException('Manifest origen invalido: falta cases[].');
        }

        $selected = $this->selectCases($cases, $sourceManifest['ramas_canonicas'] ?? []);
        if (count($selected) !== 10) {
            throw new RuntimeException('No se pudieron seleccionar 10 casos (2 por rama).');
        }

        $outputCases = [];
        foreach ($selected as $ref) {
            $relative = (string)$ref['path'];
            $idCv = (string)$ref['id_cv'];
            $rama = (string)$ref['rama'];
            $perfil = (string)$ref['perfil'];

            $sourceCaseDir = $this->sourceDatasetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $cvCvnPath = $sourceCaseDir . DIRECTORY_SEPARATOR . 'cv_cvn_like.txt';
            $expectedPath = $sourceCaseDir . DIRECTORY_SEPARATOR . 'expected.json';

            if (!is_file($cvCvnPath) || !is_file($expectedPath)) {
                throw new RuntimeException("Caso incompleto en origen: {$relative}");
            }

            $targetCaseDir = $this->outputDir . DIRECTORY_SEPARATOR . $idCv;
            $this->ensureDir($targetCaseDir);

            $pdfPath = $targetCaseDir . DIRECTORY_SEPARATOR . 'cv.pdf';
            $targetExpectedPath = $targetCaseDir . DIRECTORY_SEPARATOR . 'expected.json';
            $sourceCaseTxtPath = $targetCaseDir . DIRECTORY_SEPARATOR . 'source_case.txt';
            $targetReadmePath = $targetCaseDir . DIRECTORY_SEPARATOR . 'README.md';

            $cvRaw = (string)file_get_contents($cvCvnPath);
            $this->renderPdf($pdfPath, $idCv, $rama, $perfil, $cvRaw);

            copy($expectedPath, $targetExpectedPath);

            $sourceCaseTxt = [
                'source_dataset: evaluador/tests/fixtures/cv_sinteticos',
                'source_case_path: ' . $relative,
                'source_cv_cvn_like: ' . str_replace('\\', '/', $cvCvnPath),
                'selected_id_cv: ' . $idCv,
                'selected_rama: ' . $rama,
                'selected_perfil: ' . $perfil,
            ];
            $this->writeFile($sourceCaseTxtPath, implode(PHP_EOL, $sourceCaseTxt) . PHP_EOL);

            $caseReadme = [
                '# ' . $idCv,
                '',
                '- rama: ' . $rama,
                '- perfil: ' . $perfil,
                '- source_case: ' . $relative,
                '- files: cv.pdf, expected.json, source_case.txt',
            ];
            $this->writeFile($targetReadmePath, implode(PHP_EOL, $caseReadme) . PHP_EOL);

            $outputCases[] = [
                'id_cv' => $idCv,
                'rama' => $rama,
                'perfil' => $perfil,
                'source_case_path' => $relative,
                'path' => $idCv,
                'pdf' => $idCv . '/cv.pdf',
                'expected' => $idCv . '/expected.json',
                'source_case_txt' => $idCv . '/source_case.txt',
            ];
        }

        $subsetManifest = [
            'dataset_id' => 'aneca_cv_sinteticos_pdf_subset_v1',
            'version' => '1.0.0',
            'generated_at' => date('c'),
            'generator' => 'evaluador/tests/tools/generate_synthetic_cv_pdf_subset.php',
            'source_dataset_manifest' => str_replace('\\', '/', $manifestPath),
            'selection_policy' => '2_por_rama: primero_positivo + primero_problematico_frontera_negativo',
            'total_cases' => count($outputCases),
            'ramas_canonicas' => ['EXPERIMENTALES', 'TECNICAS', 'CSYJ', 'SALUD', 'HUMANIDADES'],
            'cases' => $outputCases,
        ];

        $subsetManifestPath = $this->outputDir . DIRECTORY_SEPARATOR . 'pdf_subset_manifest.json';
        $this->writeJson($subsetManifestPath, $subsetManifest);

        $readmePath = $this->outputDir . DIRECTORY_SEPARATOR . 'README.md';
        $readme = [
            '# Subbateria PDF sintetica ANECA',
            '',
            'Subconjunto reducido para validar el flujo real PDF/OCR/Pipeline sin convertir los 250 casos.',
            '',
            '## Cobertura',
            '- 2 casos por rama canonica.',
            '- 10 PDFs en total.',
            '- Seleccion preferente: positivo + problematico/frontera/negativo.',
            '',
            '## Archivos',
            '- `pdf_subset_manifest.json`',
            '- `<ID>/cv.pdf`',
            '- `<ID>/expected.json`',
            '- `<ID>/source_case.txt`',
            '- `<ID>/README.md`',
            '',
            '## Regeneracion reproducible',
            '```bash',
            'php evaluador/tests/tools/generate_synthetic_cv_pdf_subset.php --force',
            '```',
            '',
            '## Runner de pipeline',
            '```bash',
            'php evaluador/tests/run_synthetic_cv_pdf_pipeline.php',
            '```',
        ];
        $this->writeFile($readmePath, implode(PHP_EOL, $readme) . PHP_EOL);

        fwrite(STDOUT, 'PDF_SUBSET_MANIFEST=' . $subsetManifestPath . PHP_EOL);
        fwrite(STDOUT, 'PDF_SUBSET_TOTAL=' . count($outputCases) . PHP_EOL);

        return 0;
    }

    /**
     * @param array<int, mixed> $cases
     * @param array<int, mixed> $orderedRamas
     * @return array<int, array<string, string>>
     */
    private function selectCases(array $cases, array $orderedRamas): array
    {
        $byRama = [];
        foreach ($cases as $case) {
            if (!is_array($case)) {
                continue;
            }
            $rama = strtoupper((string)($case['rama'] ?? ''));
            if ($rama === '') {
                continue;
            }
            if (!isset($byRama[$rama])) {
                $byRama[$rama] = [];
            }
            $byRama[$rama][] = $case;
        }

        $ramas = [];
        foreach ($orderedRamas as $ramaRaw) {
            $rama = strtoupper((string)$ramaRaw);
            if ($rama !== '') {
                $ramas[] = $rama;
            }
        }
        $ramas = array_values(array_unique($ramas));

        $selected = [];
        foreach ($ramas as $rama) {
            $bucket = $byRama[$rama] ?? [];
            if ($bucket === []) {
                throw new RuntimeException("No hay casos para rama {$rama}");
            }

            $positive = $this->findFirstByProfile($bucket, ['positivo']);
            $challenging = $this->findFirstByProfile($bucket, ['problematico', 'frontera', 'negativo']);

            if ($positive === null) {
                $positive = $bucket[0] ?? null;
            }

            if ($challenging === null) {
                foreach ($bucket as $candidate) {
                    if ((string)($candidate['id_cv'] ?? '') !== (string)($positive['id_cv'] ?? '')) {
                        $challenging = $candidate;
                        break;
                    }
                }
            }

            if (!is_array($positive) || !is_array($challenging)) {
                throw new RuntimeException("No se pudieron seleccionar 2 casos para rama {$rama}");
            }

            $selected[] = $this->normalizeCaseRef($positive);
            $selected[] = $this->normalizeCaseRef($challenging);
        }

        return $selected;
    }

    /**
     * @param array<int, array<string, mixed>> $bucket
     * @param array<int, string> $profiles
     * @return array<string, mixed>|null
     */
    private function findFirstByProfile(array $bucket, array $profiles): ?array
    {
        foreach ($profiles as $profile) {
            foreach ($bucket as $case) {
                $perfil = strtolower((string)($case['perfil'] ?? ''));
                if ($perfil === $profile) {
                    return $case;
                }
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, string>
     */
    private function normalizeCaseRef(array $case): array
    {
        return [
            'id_cv' => (string)$case['id_cv'],
            'rama' => strtoupper((string)$case['rama']),
            'perfil' => strtolower((string)$case['perfil']),
            'path' => (string)$case['path'],
        ];
    }

    private function renderPdf(string $pdfPath, string $idCv, string $rama, string $perfil, string $rawText): void
    {
        $pdf = new DeterministicFpdf('P', 'mm', 'A4');
        $pdf->SetCreator('Proyecto-Acelerador synthetic-cv-pdf-subset', true);
        $pdf->SetAuthor('Synthetic Test Harness', true);
        $pdf->SetTitle($this->toPdfText($idCv . ' ' . $rama), true);
        $pdf->SetSubject('Synthetic CV PDF for pipeline tests', true);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();
        $pdf->SetFont('Courier', '', 9);

        $header = [
            'ID_CV ' . $idCv,
            'RAMA ' . $rama,
            'PERFIL ' . $perfil,
            str_repeat('-', 80),
        ];

        foreach ($header as $line) {
            $pdf->MultiCell(0, 5, $this->toPdfText($line));
        }

        $lines = preg_split('/\R/u', $rawText) ?: [];
        foreach ($lines as $line) {
            $pdf->MultiCell(0, 5, $this->toPdfText((string)$line));
        }

        $pdf->Output('F', $pdfPath);
    }

    private function toPdfText(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $normalized);
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E\n]/', '?', $normalized) ?? $normalized;
        }
        return $converted;
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

    private function deleteDirectoryContents(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectoryContents($path);
                @rmdir($path);
                continue;
            }
            @unlink($path);
        }
    }
}

/**
 * @return array{sourceDatasetDir:string,outputDir:string,force:bool}
 */
function parseArgs(array $argv): array
{
    $opts = getopt('', ['source-dataset-dir::', 'output-dir::', 'force']);
    $repoRoot = dirname(__DIR__, 3);

    $sourceDatasetDir = isset($opts['source-dataset-dir']) && is_string($opts['source-dataset-dir']) && $opts['source-dataset-dir'] !== ''
        ? resolvePath($opts['source-dataset-dir'])
        : $repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cv_sinteticos';

    $outputDir = isset($opts['output-dir']) && is_string($opts['output-dir']) && $opts['output-dir'] !== ''
        ? resolvePath($opts['output-dir'])
        : $repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cv_sinteticos_pdf';

    return [
        'sourceDatasetDir' => $sourceDatasetDir,
        'outputDir' => $outputDir,
        'force' => array_key_exists('force', $opts),
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
    $runner = new SyntheticCvPdfSubsetGenerator($args['sourceDatasetDir'], $args['outputDir'], $args['force']);
    exit($runner->run());
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
