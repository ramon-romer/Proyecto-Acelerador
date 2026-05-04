<?php
declare(strict_types=1);

/**
 * Validador estructural del dataset sintetico de CV ANECA.
 *
 * Uso:
 *   php evaluador/tests/validate_synthetic_cv_dataset.php
 *
 * Opciones:
 *   --dataset-dir=<ruta>
 */

final class SyntheticCvDatasetValidator
{
    private string $datasetDir;

    /** @var array<int, string> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<int, string> */
    private array $requiredExpectedFields = [
        'id_cv',
        'rama',
        'perfil',
        'resultado_esperado',
        'orcid_esperado',
        'publicaciones_esperadas_total',
        'publicaciones_relevantes_esperadas',
        'proyectos_esperados',
        'docencia_esperada',
        'congresos_esperados',
        'estancias_esperadas',
        'transferencia_esperada',
        'problemas_intencionados',
        'observaciones',
        'nivel_confianza_esperado',
    ];

    /** @var array<int, string> */
    private array $requiredCvMarkers = [
        'id_cv:',
        'rama:',
        'perfil:',
        'nombre_ficticio:',
        'orcid_prueba:',
        'resumen_perfil:',
        'formacion_academica:',
        'docencia:',
        'publicaciones:',
        'proyectos:',
        'congresos:',
        'estancias:',
        'transferencia_patentes:',
        'otros_meritos:',
        'problemas_intencionados:',
    ];

    public function __construct(string $datasetDir)
    {
        $this->datasetDir = $datasetDir;
    }

    public function run(): int
    {
        fwrite(STDOUT, "Validando dataset: {$this->datasetDir}" . PHP_EOL);

        if (!is_dir($this->datasetDir)) {
            $this->errors[] = "No existe el directorio de dataset: {$this->datasetDir}";
            return $this->finish();
        }

        $manifestPath = $this->datasetDir . DIRECTORY_SEPARATOR . 'dataset_manifest.json';
        if (!is_file($manifestPath)) {
            $this->errors[] = 'No existe dataset_manifest.json.';
            return $this->finish();
        }

        $manifest = $this->decodeJsonFile($manifestPath, 'manifest');
        if (!is_array($manifest)) {
            return $this->finish();
        }

        $cases = $this->collectCasesFromFilesystem();
        $this->validateCaseFiles($cases);
        $this->validateManifestAgainstCases($manifest, $cases);
        $this->validateDuplicateIds($cases);

        return $this->finish();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function collectCasesFromFilesystem(): array
    {
        $cases = [];
        $branchDirs = glob($this->datasetDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        foreach ($branchDirs as $branchDir) {
            $branch = basename($branchDir);
            $caseDirs = glob($branchDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
            foreach ($caseDirs as $caseDir) {
                $cases[] = [
                    'branch' => $branch,
                    'id' => basename($caseDir),
                    'dir' => $caseDir,
                    'relative' => $branch . '/' . basename($caseDir),
                ];
            }
        }
        return $cases;
    }

    /**
     * @param array<int, array<string, string>> $cases
     */
    private function validateCaseFiles(array $cases): void
    {
        if ($cases === []) {
            $this->errors[] = 'No hay casos en el dataset.';
            return;
        }

        foreach ($cases as $case) {
            $cvPath = $case['dir'] . DIRECTORY_SEPARATOR . 'cv.txt';
            $cvCvnPath = $case['dir'] . DIRECTORY_SEPARATOR . 'cv_cvn_like.txt';
            $expectedPath = $case['dir'] . DIRECTORY_SEPARATOR . 'expected.json';
            $readmePath = $case['dir'] . DIRECTORY_SEPARATOR . 'README.md';

            if (!is_file($cvPath)) {
                $this->errors[] = "Falta cv.txt en {$case['relative']}";
            }
            if (!is_file($expectedPath)) {
                $this->errors[] = "Falta expected.json en {$case['relative']}";
            }
            if (!is_file($cvCvnPath)) {
                $this->errors[] = "Falta cv_cvn_like.txt en {$case['relative']}";
            }
            if (!is_file($readmePath)) {
                $this->warnings[] = "Falta README.md en {$case['relative']}";
            }

            if (is_file($cvPath)) {
                $this->validateCvMarkers($cvPath, $case['relative']);
            }
            if (is_file($expectedPath)) {
                $this->validateExpectedJson($expectedPath, $case['relative']);
            }
        }
    }

    private function validateCvMarkers(string $cvPath, string $relative): void
    {
        $raw = file_get_contents($cvPath);
        if ($raw === false) {
            $this->errors[] = "No se pudo leer cv.txt en {$relative}";
            return;
        }

        $lower = strtolower($raw);
        foreach ($this->requiredCvMarkers as $marker) {
            if (strpos($lower, strtolower($marker)) === false) {
                $this->errors[] = "cv.txt en {$relative} no contiene marcador obligatorio '{$marker}'";
            }
        }
    }

    private function validateExpectedJson(string $expectedPath, string $relative): void
    {
        $expected = $this->decodeJsonFile($expectedPath, "expected {$relative}");
        if (!is_array($expected)) {
            return;
        }

        foreach ($this->requiredExpectedFields as $field) {
            if (!array_key_exists($field, $expected)) {
                $this->errors[] = "expected.json en {$relative} no contiene campo obligatorio '{$field}'";
            }
        }
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<int, array<string, string>> $cases
     */
    private function validateManifestAgainstCases(array $manifest, array $cases): void
    {
        if (!array_key_exists('cases', $manifest) || !is_array($manifest['cases'])) {
            $this->errors[] = "El manifest no contiene 'cases' valido.";
            return;
        }

        $byRelative = [];
        foreach ($cases as $case) {
            $byRelative[$case['relative']] = $case;
        }

        $manifestPaths = [];
        foreach ($manifest['cases'] as $index => $manifestCase) {
            if (!is_array($manifestCase)) {
                $this->errors[] = "Manifest case #{$index} no es objeto.";
                continue;
            }
            foreach (['id_cv', 'rama', 'perfil', 'path'] as $field) {
                if (!array_key_exists($field, $manifestCase)) {
                    $this->errors[] = "Manifest case #{$index} no contiene '{$field}'.";
                }
            }
            if (!isset($manifestCase['path']) || !is_string($manifestCase['path'])) {
                continue;
            }

            $path = trim($manifestCase['path']);
            $manifestPaths[] = $path;
            if (!array_key_exists($path, $byRelative)) {
                $this->errors[] = "Manifest referencia caso inexistente: {$path}";
            }
        }

        foreach (array_keys($byRelative) as $relative) {
            if (!in_array($relative, $manifestPaths, true)) {
                $this->errors[] = "Caso existente no referenciado en manifest: {$relative}";
            }
        }
    }

    /**
     * @param array<int, array<string, string>> $cases
     */
    private function validateDuplicateIds(array $cases): void
    {
        $ids = [];
        foreach ($cases as $case) {
            $expectedPath = $case['dir'] . DIRECTORY_SEPARATOR . 'expected.json';
            if (!is_file($expectedPath)) {
                continue;
            }
            $expected = $this->decodeJsonFile($expectedPath, "duplicate check {$case['relative']}");
            if (!is_array($expected) || !isset($expected['id_cv']) || !is_string($expected['id_cv'])) {
                continue;
            }
            $id = $expected['id_cv'];
            if (isset($ids[$id])) {
                $this->errors[] = "ID duplicado detectado: {$id} ({$ids[$id]} y {$case['relative']})";
            } else {
                $ids[$id] = $case['relative'];
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonFile(string $path, string $label): ?array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->errors[] = "No se pudo leer {$label}: {$path}";
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->errors[] = "JSON invalido en {$label}: {$e->getMessage()}";
            return null;
        }

        if (!is_array($decoded)) {
            $this->errors[] = "{$label} no es objeto JSON.";
            return null;
        }
        return $decoded;
    }

    private function finish(): int
    {
        fwrite(STDOUT, PHP_EOL . '=== Synthetic Dataset Validation Summary ===' . PHP_EOL);
        fwrite(STDOUT, 'errors=' . count($this->errors) . ' warnings=' . count($this->warnings) . PHP_EOL);

        if ($this->warnings !== []) {
            fwrite(STDOUT, 'Warnings:' . PHP_EOL);
            foreach ($this->warnings as $warning) {
                fwrite(STDOUT, '- ' . $warning . PHP_EOL);
            }
        }
        if ($this->errors !== []) {
            fwrite(STDOUT, 'Errors:' . PHP_EOL);
            foreach ($this->errors as $error) {
                fwrite(STDOUT, '- ' . $error . PHP_EOL);
            }
            return 1;
        }

        fwrite(STDOUT, 'Validation PASS.' . PHP_EOL);
        return 0;
    }
}

/**
 * @return array{datasetDir:string}
 */
function parseArgs(array $argv): array
{
    $opts = getopt('', ['dataset-dir::']);
    $testsDir = __DIR__;

    $datasetDir = isset($opts['dataset-dir']) && is_string($opts['dataset-dir']) && $opts['dataset-dir'] !== ''
        ? resolvePath($opts['dataset-dir'])
        : $testsDir . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cv_sinteticos';

    return ['datasetDir' => $datasetDir];
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

$args = parseArgs($argv);
$validator = new SyntheticCvDatasetValidator($args['datasetDir']);
exit($validator->run());
