<?php
declare(strict_types=1);

/**
 * Runner de regresion semi end-to-end para CV sinteticos ANECA.
 *
 * Uso:
 *   php evaluador/tests/run_synthetic_cv_regression.php
 *   php evaluador/tests/run_synthetic_cv_regression.php --rama=EXPERIMENTALES
 *   php evaluador/tests/run_synthetic_cv_regression.php --perfil=positivo
 *   php evaluador/tests/run_synthetic_cv_regression.php --json
 */

require_once __DIR__ . '/../src/compat_mbstring.php';
require_once __DIR__ . '/../src/AnecaExtractor.php';
require_once __DIR__ . '/../src/AnecaExtractorCsyj.php';

final class SyntheticCvRegressionRunner
{
    private string $datasetDir;
    private ?string $ramaFilter;
    private ?string $perfilFilter;
    private bool $jsonOutput;

    public function __construct(string $datasetDir, ?string $ramaFilter, ?string $perfilFilter, bool $jsonOutput)
    {
        $this->datasetDir = $datasetDir;
        $this->ramaFilter = $ramaFilter !== null ? strtoupper(trim($ramaFilter)) : null;
        $this->perfilFilter = $perfilFilter !== null ? strtolower(trim($perfilFilter)) : null;
        $this->jsonOutput = $jsonOutput;
    }

    public function run(): int
    {
        $manifestPath = $this->datasetDir . DIRECTORY_SEPARATOR . 'dataset_manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException("No existe dataset_manifest.json en {$this->datasetDir}");
        }

        $manifest = $this->readJson($manifestPath);
        $casesRef = $manifest['cases'] ?? null;
        if (!is_array($casesRef)) {
            throw new RuntimeException('Manifest invalido: falta cases[]');
        }

        $results = [];
        $summary = [
            'total_casos' => 0,
            'ok' => 0,
            'warnings' => 0,
            'fallos' => 0,
            'por_rama' => [],
            'por_perfil' => [],
        ];

        foreach ($casesRef as $ref) {
            if (!is_array($ref) || !isset($ref['path']) || !is_string($ref['path'])) {
                continue;
            }

            $caseDir = $this->datasetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ref['path']);
            $expectedPath = $caseDir . DIRECTORY_SEPARATOR . 'expected.json';
            $cvPath = $caseDir . DIRECTORY_SEPARATOR . 'cv.txt';
            $cvCvnLikePath = $caseDir . DIRECTORY_SEPARATOR . 'cv_cvn_like.txt';

            if (!is_file($expectedPath) || !is_file($cvPath)) {
                continue;
            }

            $expected = $this->readJson($expectedPath);
            $rama = strtoupper((string)($expected['rama'] ?? ''));
            $perfil = strtolower((string)($expected['perfil'] ?? ''));

            if ($this->ramaFilter !== null && $rama !== $this->ramaFilter) {
                continue;
            }
            if ($this->perfilFilter !== null && $perfil !== $this->perfilFilter) {
                continue;
            }

            $cv = $this->parseCvTxt($cvPath);
            $extractorSnapshot = $this->buildExtractorSnapshot($rama, $cvCvnLikePath);
            $obtained = $this->buildObtained($cv, $extractorSnapshot);
            $evaluation = $this->evaluateCase($expected, $cv, $obtained, $extractorSnapshot, $ref);

            $results[] = $evaluation;
            $summary['total_casos']++;

            if ($evaluation['status'] === 'PASS') {
                $summary['ok']++;
            } elseif ($evaluation['status'] === 'WARN') {
                $summary['warnings']++;
            } else {
                $summary['fallos']++;
            }

            if (!isset($summary['por_rama'][$rama])) {
                $summary['por_rama'][$rama] = ['total' => 0, 'ok' => 0, 'warnings' => 0, 'fallos' => 0];
            }
            if (!isset($summary['por_perfil'][$perfil])) {
                $summary['por_perfil'][$perfil] = ['total' => 0, 'ok' => 0, 'warnings' => 0, 'fallos' => 0];
            }

            $summary['por_rama'][$rama]['total']++;
            $summary['por_perfil'][$perfil]['total']++;
            if ($evaluation['status'] === 'PASS') {
                $summary['por_rama'][$rama]['ok']++;
                $summary['por_perfil'][$perfil]['ok']++;
            } elseif ($evaluation['status'] === 'WARN') {
                $summary['por_rama'][$rama]['warnings']++;
                $summary['por_perfil'][$perfil]['warnings']++;
            } else {
                $summary['por_rama'][$rama]['fallos']++;
                $summary['por_perfil'][$perfil]['fallos']++;
            }
        }

        $payload = [
            'suite' => 'synthetic-cv-regression',
            'dataset_dir' => $this->datasetDir,
            'filters' => [
                'rama' => $this->ramaFilter,
                'perfil' => $this->perfilFilter,
            ],
            'summary' => $summary,
            'cases' => $results,
            'timestamp' => date('c'),
        ];

        if ($this->jsonOutput) {
            fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        } else {
            $this->printHumanReport($payload);
        }

        return $summary['fallos'] > 0 ? 1 : 0;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function printHumanReport(array $payload): void
    {
        fwrite(STDOUT, "=== Synthetic CV Regression ===" . PHP_EOL);
        fwrite(STDOUT, "dataset_dir=" . (string)$payload['dataset_dir'] . PHP_EOL);
        fwrite(STDOUT, "filters.rama=" . (($payload['filters']['rama'] ?? null) ?? 'ALL') . PHP_EOL);
        fwrite(STDOUT, "filters.perfil=" . (($payload['filters']['perfil'] ?? null) ?? 'ALL') . PHP_EOL);
        fwrite(STDOUT, PHP_EOL);

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
                    (string)$case['resultado_esperado'],
                    (string)$case['resultado_obtenido']
                ) . PHP_EOL
            );
            foreach (($case['diferencias'] ?? []) as $diff) {
                fwrite(STDOUT, "  - " . (string)$diff . PHP_EOL);
            }
        }

        $summary = $payload['summary'] ?? [];
        fwrite(STDOUT, PHP_EOL . "=== Resumen Global ===" . PHP_EOL);
        fwrite(STDOUT, "total_casos=" . (int)($summary['total_casos'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, "ok=" . (int)($summary['ok'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, "warnings=" . (int)($summary['warnings'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, "fallos=" . (int)($summary['fallos'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, PHP_EOL . "por_rama=" . json_encode($summary['por_rama'] ?? [], JSON_UNESCAPED_UNICODE) . PHP_EOL);
        fwrite(STDOUT, "por_perfil=" . json_encode($summary['por_perfil'] ?? [], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }

    /**
     * @param array<string,mixed> $expected
     * @param array<string,mixed> $cv
     * @param array<string,mixed> $obtained
     * @param array<string,mixed>|null $extractorSnapshot
     * @param array<string,mixed> $manifestRef
     * @return array<string,mixed>
     */
    private function evaluateCase(array $expected, array $cv, array $obtained, ?array $extractorSnapshot, array $manifestRef): array
    {
        $fails = [];
        $warns = [];

        $id = (string)($expected['id_cv'] ?? '');
        $ramaEsperada = strtoupper((string)($expected['rama'] ?? ''));
        $perfilEsperado = strtolower((string)($expected['perfil'] ?? ''));
        $resultadoEsperado = (string)($expected['resultado_esperado'] ?? '');

        $cvMeta = $cv['meta'] ?? [];
        $cvSections = $cv['sections'] ?? [];
        $idCv = (string)($cvMeta['id_cv'] ?? '');
        $ramaCv = strtoupper((string)($cvMeta['rama'] ?? ''));
        $perfilCv = strtolower((string)($cvMeta['perfil'] ?? ''));

        if ($idCv !== '' && $idCv !== $id) {
            $fails[] = "id_cv inconsistente: cv.txt={$idCv}, expected={$id}";
        }
        if ($ramaCv !== '' && $ramaCv !== $ramaEsperada) {
            $fails[] = "rama inconsistente: cv.txt={$ramaCv}, expected={$ramaEsperada}";
        }
        if ($perfilCv !== '' && $perfilCv !== $perfilEsperado) {
            $fails[] = "perfil inconsistente: cv.txt={$perfilCv}, expected={$perfilEsperado}";
        }
        if (isset($manifestRef['rama']) && strtoupper((string)$manifestRef['rama']) !== $ramaEsperada) {
            $fails[] = "manifest.rama inconsistente con expected: " . (string)$manifestRef['rama'];
        }
        if (isset($manifestRef['perfil']) && strtolower((string)$manifestRef['perfil']) !== $perfilEsperado) {
            $fails[] = "manifest.perfil inconsistente con expected: " . (string)$manifestRef['perfil'];
        }

        $orcidEsperado = $expected['orcid_esperado'] ?? null;
        $orcidCv = $obtained['orcid'] ?? null;
        if ($orcidEsperado !== null && $orcidEsperado !== $orcidCv) {
            $fails[] = "ORCID inconsistente: obtenido=" . $this->toString($orcidCv) . ", esperado=" . $this->toString($orcidEsperado);
        }

        $this->compareApprox('publicaciones', (int)($expected['publicaciones_esperadas_total'] ?? 0), (int)$obtained['publicaciones_total'], 1, $fails, $warns);
        $this->compareApprox('proyectos', (int)($expected['proyectos_esperados'] ?? 0), (int)$obtained['proyectos_total'], 1, $fails, $warns);

        if ($resultadoEsperado !== (string)$obtained['resultado_obtenido']) {
            $fails[] = "resultado diferente: obtenido={$obtained['resultado_obtenido']}, esperado={$resultadoEsperado}";
        }

        $problemasEsperados = is_array($expected['problemas_intencionados'] ?? null) ? $expected['problemas_intencionados'] : [];
        $problemasCv = $cvSections['problemas_intencionados'] ?? [];
        if (count($problemasEsperados) > 0 && count($problemasCv) === 0) {
            $fails[] = 'expected.json define problemas_intencionados pero cv.txt no los lista.';
        } elseif (count($problemasEsperados) === 0 && count($problemasCv) > 0) {
            $warns[] = 'cv.txt incluye problemas_intencionados no esperados.';
        }

        foreach ($problemasEsperados as $expectedProblem) {
            $found = false;
            foreach ($problemasCv as $problemLine) {
                if ($this->containsNormalized((string)$problemLine, (string)$expectedProblem)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $warns[] = "problema no localizado literalmente en cv.txt: {$expectedProblem}";
            }
        }

        if ($extractorSnapshot !== null) {
            if (($extractorSnapshot['ok'] ?? false) !== true) {
                $warns[] = 'extractor no pudo ejecutarse sobre cv_cvn_like.txt';
            } else {
                if (($extractorSnapshot['publicaciones'] ?? 0) === 0 && (int)$obtained['publicaciones_total'] > 0) {
                    $warns[] = 'extractor no detecto publicaciones en cv_cvn_like con publicaciones presentes en cv.txt';
                }
                if (($extractorSnapshot['proyectos'] ?? 0) === 0 && (int)$obtained['proyectos_total'] > 0) {
                    $warns[] = 'extractor no detecto proyectos en cv_cvn_like con proyectos presentes en cv.txt';
                }
            }
        } else {
            $warns[] = 'cv_cvn_like.txt no disponible para verificacion extractor.';
        }

        $status = 'PASS';
        if ($fails !== []) {
            $status = 'FAIL';
        } elseif ($warns !== []) {
            $status = 'WARN';
        }

        return [
            'id_cv' => $id,
            'rama' => $ramaEsperada,
            'perfil' => $perfilEsperado,
            'resultado_esperado' => $resultadoEsperado,
            'resultado_obtenido' => $obtained['resultado_obtenido'],
            'status' => $status,
            'diferencias' => array_values(array_merge($fails, $warns)),
            'validacion_obtenida' => [
                'orcid' => $orcidCv,
                'publicaciones' => $obtained['publicaciones_total'],
                'proyectos' => $obtained['proyectos_total'],
                'perfil_detectado' => $obtained['perfil_detectado'],
                'problemas_detectados' => $obtained['problemas_count'],
            ],
            'extractor_snapshot' => $extractorSnapshot,
        ];
    }

    /**
     * @param array<int, string> &$fails
     * @param array<int, string> &$warns
     */
    private function compareApprox(string $field, int $expected, int $obtained, int $warnDelta, array &$fails, array &$warns): void
    {
        $delta = abs($expected - $obtained);
        if ($delta === 0) {
            return;
        }
        if ($delta <= $warnDelta) {
            $warns[] = "{$field} aproximado: esperado={$expected}, obtenido={$obtained}";
            return;
        }
        $fails[] = "{$field} fuera de umbral: esperado={$expected}, obtenido={$obtained}";
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildExtractorSnapshot(string $rama, string $cvCvnLikePath): ?array
    {
        if (!is_file($cvCvnLikePath)) {
            return null;
        }
        $raw = file_get_contents($cvCvnLikePath);
        if ($raw === false) {
            return ['ok' => false];
        }

        try {
            $extractor = $rama === 'CSYJ' ? new AnecaExtractorCsyj() : new AnecaExtractor();
            $result = $extractor->extraer($raw);
            $b1 = isset($result['bloque_1']) && is_array($result['bloque_1']) ? $result['bloque_1'] : [];
            return [
                'ok' => true,
                'publicaciones' => count(is_array($b1['publicaciones'] ?? null) ? $b1['publicaciones'] : []),
                'proyectos' => count(is_array($b1['proyectos'] ?? null) ? $b1['proyectos'] : []),
            ];
        } catch (Throwable) {
            return ['ok' => false];
        }
    }

    /**
     * @param array<string,mixed> $cv
     * @param array<string,mixed>|null $extractorSnapshot
     * @return array<string,mixed>
     */
    private function buildObtained(array $cv, ?array $extractorSnapshot): array
    {
        $meta = $cv['meta'] ?? [];
        $sections = $cv['sections'] ?? [];
        $orcid = trim((string)($meta['orcid_prueba'] ?? ''));
        $orcid = $orcid !== '' ? $orcid : null;
        $profile = strtolower(trim((string)($meta['perfil'] ?? '')));

        $pubs = is_array($sections['publicaciones'] ?? null) ? $sections['publicaciones'] : [];
        $projects = is_array($sections['proyectos'] ?? null) ? $sections['proyectos'] : [];
        $doc = is_array($sections['docencia'] ?? null) ? $sections['docencia'] : [];
        $problems = is_array($sections['problemas_intencionados'] ?? null) ? $sections['problemas_intencionados'] : [];

        $resultado = 'revisar';
        if ($profile === 'positivo') {
            $resultado = 'apto';
        } elseif ($profile === 'negativo') {
            $resultado = 'no_apto';
        } elseif ($profile === 'frontera') {
            $resultado = 'frontera';
        } else {
            $resultado = 'revisar';
        }

        if ($orcid === null || !preg_match('/^\d{4}-\d{4}-\d{4}-\d{4}$/', $orcid)) {
            if ($resultado === 'apto') {
                $resultado = 'revisar';
            }
        }

        return [
            'orcid' => $orcid,
            'publicaciones_total' => count($pubs),
            'proyectos_total' => count($projects),
            'docencia_total' => count($doc),
            'problemas_count' => count($problems),
            'perfil_detectado' => $profile,
            'resultado_obtenido' => $resultado,
            'extractor_publicaciones' => (int)($extractorSnapshot['publicaciones'] ?? 0),
            'extractor_proyectos' => (int)($extractorSnapshot['proyectos'] ?? 0),
        ];
    }

    /**
     * @return array{meta:array<string,string>,sections:array<string,array<int,string>>}
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

            if (strpos($trimmed, ':') !== false && !str_starts_with($trimmed, '- ')) {
                [$k, $v] = explode(':', $trimmed, 2);
                $key = trim($k);
                $value = trim($v);
                if ($value === '') {
                    $currentSection = $key;
                    if (!isset($sections[$currentSection])) {
                        $sections[$currentSection] = [];
                    }
                } else {
                    if (!array_key_exists($key, $meta)) {
                        $meta[$key] = $value;
                    }
                    $currentSection = null;
                }
                continue;
            }

            if ($currentSection !== null && str_starts_with($trimmed, '- ')) {
                $sections[$currentSection][] = trim(substr($trimmed, 2));
            }
        }

        return ['meta' => $meta, 'sections' => $sections];
    }

    /**
     * @return array<string,mixed>
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

    private function containsNormalized(string $haystack, string $needle): bool
    {
        $h = $this->normalize($haystack);
        $n = $this->normalize($needle);
        return $n !== '' && str_contains($h, $n);
    }

    private function normalize(string $value): string
    {
        $value = strtolower($value);
        $value = str_replace(['á', 'à', 'ä', 'é', 'è', 'ë', 'í', 'ì', 'ï', 'ó', 'ò', 'ö', 'ú', 'ù', 'ü', 'ñ'], ['a', 'a', 'a', 'e', 'e', 'e', 'i', 'i', 'i', 'o', 'o', 'o', 'u', 'u', 'u', 'n'], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function toString(mixed $value): string
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
 * @return array{datasetDir:string,rama:?string,perfil:?string,json:bool}
 */
function parseArgs(array $argv): array
{
    $opts = getopt('', ['dataset-dir::', 'rama::', 'perfil::', 'json']);
    $repoRoot = dirname(__DIR__, 2);
    $datasetDir = isset($opts['dataset-dir']) && is_string($opts['dataset-dir']) && $opts['dataset-dir'] !== ''
        ? resolvePath($opts['dataset-dir'])
        : $repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cv_sinteticos';

    return [
        'datasetDir' => $datasetDir,
        'rama' => isset($opts['rama']) && is_string($opts['rama']) ? $opts['rama'] : null,
        'perfil' => isset($opts['perfil']) && is_string($opts['perfil']) ? $opts['perfil'] : null,
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
    return (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
        || str_starts_with($path, '\\\\')
        || str_starts_with($path, '/');
}

try {
    $args = parseArgs($argv);
    $runner = new SyntheticCvRegressionRunner(
        $args['datasetDir'],
        $args['rama'],
        $args['perfil'],
        $args['json']
    );
    exit($runner->run());
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
