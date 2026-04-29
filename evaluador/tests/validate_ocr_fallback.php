<?php
declare(strict_types=1);

/**
 * Validacion reproducible del fallback OCR del pipeline principal ANECA (SIN MCP).
 *
 * Uso (desde raiz del repo):
 *   C:\xampp\php\php.exe evaluador\tests\validate_ocr_fallback.php
 *
 * Opciones:
 *   --schema=<ruta_schema_json>
 *   --json-dir=<directorio_json_salida>
 */

require_once __DIR__ . '/../src/Pipeline.php';
require_once __DIR__ . '/../src/OcrProcessor.php';

final class OcrFallbackValidationRunner
{
    private string $repoRoot;
    private string $schemaPath;
    private string $jsonDir;
    private string $schemaValidatorScript;

    public function __construct(string $repoRoot, string $schemaPath, string $jsonDir)
    {
        $this->repoRoot = $repoRoot;
        $this->schemaPath = $schemaPath;
        $this->jsonDir = $jsonDir;
        $this->schemaValidatorScript = $repoRoot
            . DIRECTORY_SEPARATOR . 'evaluador'
            . DIRECTORY_SEPARATOR . 'tests'
            . DIRECTORY_SEPARATOR . 'validate_canonical_schema.php';
    }

    public function run(): int
    {
        $processor = new OcrProcessor();
        $pipeline = new Pipeline($processor);
        $diagnostico = $processor->diagnosticoDisponibilidad();

        fwrite(STDOUT, "=== OCR Fallback Validation (ANECA pipeline principal) ===" . PHP_EOL);
        fwrite(STDOUT, "Schema: {$this->schemaPath}" . PHP_EOL);
        fwrite(STDOUT, "JSON dir: {$this->jsonDir}" . PHP_EOL);
        fwrite(STDOUT, "pdftoppm_disponible=" . ($diagnostico['pdftoppm_disponible'] ? 'true' : 'false') . PHP_EOL);
        fwrite(STDOUT, "tesseract_disponible=" . ($diagnostico['tesseract_disponible'] ? 'true' : 'false') . PHP_EOL);
        fwrite(STDOUT, "pdftoppm_path=" . (string)($diagnostico['pdftoppm_path'] ?? 'null') . PHP_EOL);
        fwrite(STDOUT, "tesseract_path=" . (string)($diagnostico['tesseract_path'] ?? 'null') . PHP_EOL);
        fwrite(STDOUT, PHP_EOL);

        $cases = $this->buildCases();
        if ($cases === []) {
            fwrite(STDERR, "[ERROR] No hay casos PDF configurados/encontrados para validar OCR fallback." . PHP_EOL);
            return 2;
        }

        $results = [];
        $hardFailures = 0;
        $blockedByDependency = 0;

        foreach ($cases as $case) {
            $results[] = $this->runCase($pipeline, $case);
            $last = $results[count($results) - 1];

            if ($last['status'] === 'fail') {
                $hardFailures++;
            } elseif ($last['status'] === 'blocked_dependency') {
                $blockedByDependency++;
            }
        }

        fwrite(STDOUT, PHP_EOL . "=== Case Summary ===" . PHP_EOL);
        foreach ($results as $result) {
            fwrite(
                STDOUT,
                sprintf(
                    "[%s] %s | fallback_ocr=%s | modo=%s | ocr_disponible=%s | detalle=%s",
                    strtoupper($result['status']),
                    $result['id'],
                    $this->stringify($result['fallback_ocr_activado']),
                    $result['modo_extraccion_texto'] ?? 'n/a',
                    $this->stringify($result['ocr_disponible']),
                    $result['detalle_extraccion_texto'] ?? 'n/a'
                ) . PHP_EOL
            );
        }

        $schemaExitCode = $this->runSchemaValidation();

        fwrite(STDOUT, PHP_EOL . "=== OCR Validation Summary ===" . PHP_EOL);
        fwrite(STDOUT, "cases_total=" . count($results) . PHP_EOL);
        fwrite(STDOUT, "hard_failures={$hardFailures}" . PHP_EOL);
        fwrite(STDOUT, "blocked_dependency={$blockedByDependency}" . PHP_EOL);
        fwrite(STDOUT, "schema_validation_exit_code={$schemaExitCode}" . PHP_EOL);

        if ($hardFailures > 0 || $blockedByDependency > 0 || $schemaExitCode !== 0) {
            return 1;
        }

        return 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCases(): array
    {
        $storagePdf = $this->pickFirstExistingPath([
            $this->repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'pdfs' . DIRECTORY_SEPARATOR . 'exp_69d4dc1f9c3ec.pdf',
            $this->pickFirstStoragePdf(),
        ]);

        $defaults = [
            [
                'id' => 'control_texto_nativo',
                'path' => $storagePdf,
                'expects_fallback_ocr' => false,
                'kind' => 'control',
            ],
            [
                'id' => 'scan_probe_low_text',
                'path' => $this->repoRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'smalot' . DIRECTORY_SEPARATOR . 'pdfparser' . DIRECTORY_SEPARATOR . 'samples' . DIRECTORY_SEPARATOR . 'grouped-by-generator' . DIRECTORY_SEPARATOR . 'SimpleImage_Generated_by_Inkscape-0.92_PDF-v1.4.pdf',
                'expects_fallback_ocr' => true,
                'kind' => 'scan_or_hybrid',
            ],
            [
                'id' => 'scan_probe_no_text',
                'path' => $this->repoRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'setasign' . DIRECTORY_SEPARATOR . 'fpdi' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'pdfs' . DIRECTORY_SEPARATOR . 'layers' . DIRECTORY_SEPARATOR . 'rect+circle+triangle.pdf',
                'expects_fallback_ocr' => true,
                'kind' => 'scan_or_hybrid',
            ],
        ];

        $cases = [];
        foreach ($defaults as $case) {
            $path = $case['path'];
            if (is_string($path) && $path !== '' && is_file($path)) {
                $cases[] = $case;
            }
        }

        return $cases;
    }

    /**
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    private function runCase(Pipeline $pipeline, array $case): array
    {
        $id = (string)$case['id'];
        $path = (string)$case['path'];
        $expectsFallback = (bool)$case['expects_fallback_ocr'];

        fwrite(STDOUT, "Case: {$id}" . PHP_EOL);
        fwrite(STDOUT, "  PDF: {$path}" . PHP_EOL);

        try {
            $result = $pipeline->procesar($path);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $blocked = $expectsFallback && str_contains($message, 'OCR no esta disponible');
            fwrite(STDOUT, "  Resultado: " . ($blocked ? 'BLOCKED_DEPENDENCY' : 'FAIL') . PHP_EOL);
            fwrite(STDOUT, "  Error: {$message}" . PHP_EOL . PHP_EOL);

            return [
                'id' => $id,
                'status' => $blocked ? 'blocked_dependency' : 'fail',
                'fallback_ocr_activado' => null,
                'modo_extraccion_texto' => null,
                'ocr_disponible' => null,
                'detalle_extraccion_texto' => $message,
            ];
        }

        $meta = is_array($result['metadatos_extraccion'] ?? null) ? $result['metadatos_extraccion'] : [];
        $fallback = ($meta['fallback_ocr_activado'] ?? null) === true;
        $ocrDisponible = $meta['ocr_disponible'] ?? null;
        $modo = is_string($meta['modo_extraccion_texto'] ?? null) ? $meta['modo_extraccion_texto'] : null;
        $detalle = is_string($meta['detalle_extraccion_texto'] ?? null) ? $meta['detalle_extraccion_texto'] : null;

        $status = 'pass';
        if ($expectsFallback && !$fallback) {
            $status = ($ocrDisponible === false || $ocrDisponible === null) ? 'blocked_dependency' : 'fail';
        }
        if (!$expectsFallback && $fallback) {
            $status = 'fail';
        }

        fwrite(STDOUT, "  Resultado: " . strtoupper($status) . PHP_EOL);
        fwrite(STDOUT, "  modo_extraccion_texto={$modo}" . PHP_EOL);
        fwrite(STDOUT, "  fallback_ocr_activado=" . $this->stringify($fallback) . PHP_EOL);
        fwrite(STDOUT, "  ocr_disponible=" . $this->stringify($ocrDisponible) . PHP_EOL);
        fwrite(STDOUT, "  detalle_extraccion_texto=" . ($detalle ?? 'n/a') . PHP_EOL);
        fwrite(STDOUT, PHP_EOL);

        return [
            'id' => $id,
            'status' => $status,
            'fallback_ocr_activado' => $fallback,
            'modo_extraccion_texto' => $modo,
            'ocr_disponible' => $ocrDisponible,
            'detalle_extraccion_texto' => $detalle,
        ];
    }

    private function runSchemaValidation(): int
    {
        if (!is_file($this->schemaValidatorScript)) {
            fwrite(STDERR, "[ERROR] Script de schema no encontrado: {$this->schemaValidatorScript}" . PHP_EOL);
            return 2;
        }

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($this->schemaValidatorScript)
            . ' --schema=' . escapeshellarg($this->schemaPath)
            . ' --dir=' . escapeshellarg($this->jsonDir)
            . ' 2>&1';

        fwrite(STDOUT, "=== Schema Validation Run ===" . PHP_EOL);
        fwrite(STDOUT, "Cmd: {$command}" . PHP_EOL);

        $output = [];
        $status = 0;
        exec($command, $output, $status);

        foreach ($output as $line) {
            fwrite(STDOUT, $line . PHP_EOL);
        }

        return $status;
    }

    private function pickFirstStoragePdf(): ?string
    {
        $dir = $this->repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'pdfs';
        if (!is_dir($dir)) {
            return null;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        sort($files);
        return $files[0] ?? null;
    }

    /**
     * @param array<int, string|null> $paths
     */
    private function pickFirstExistingPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string)$value;
    }
}

/**
 * @return array{schema:string,jsonDir:string}
 */
function parseCliArgs(array $argv): array
{
    $opts = getopt('', ['schema::', 'json-dir::']);
    $repoRoot = dirname(__DIR__, 2);

    $schema = isset($opts['schema']) && is_string($opts['schema']) && $opts['schema'] !== ''
        ? resolvePath((string)$opts['schema'])
        : $repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'contrato-canonico-aneca-v1.schema.json';

    $jsonDir = isset($opts['json-dir']) && is_string($opts['json-dir']) && $opts['json-dir'] !== ''
        ? resolvePath((string)$opts['json-dir'])
        : $repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . 'json';

    return ['schema' => $schema, 'jsonDir' => $jsonDir];
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

$args = parseCliArgs($argv);
$runner = new OcrFallbackValidationRunner(dirname(__DIR__, 2), $args['schema'], $args['jsonDir']);
exit($runner->run());

