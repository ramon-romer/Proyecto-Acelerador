<?php

final class RealCvAnonymizer
{
    private $repoRoot;
    private $sourceDir;
    private $targetDir;
    private $sourceCases;
    private $manualReviewSources;

    public function __construct($repoRoot)
    {
        $this->repoRoot = $repoRoot;
        $this->sourceDir = $repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . 'txt';
        $this->targetDir = $repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cv_reales_anonimizados';

        $this->sourceCases = [
            ['id' => 'REAL-EXP-001', 'rama' => 'EXPERIMENTALES', 'perfil' => 'investigador', 'source' => '3111-1111-1111-1113_EXPERIMENTALES_2026-04-27_101754.txt'],
            ['id' => 'REAL-TEC-001', 'rama' => 'TECNICAS', 'perfil' => 'investigador', 'source' => '0100-1111-2202-2225_TECNICA_2026-04-27_100302.txt'],
            ['id' => 'REAL-CSYJ-001', 'rama' => 'CSYJ', 'perfil' => 'investigador', 'source' => '3111-1111-1111-1113_CSYJ_2026-04-17_132836.txt'],
            ['id' => 'REAL-HUM-001', 'rama' => 'HUMANIDADES', 'perfil' => 'docente', 'source' => '3111-1111-1111-1113_HUMANIDADES_2026-04-16_115720.txt'],
        ];

        $this->manualReviewSources = [
            '3111-1111-1111-1113_HUMANIDADES_2026-04-14_112021.txt',
        ];
    }

    public function run()
    {
        $this->resetTargetDir($this->targetDir);

        $manifestCases = [];
        $globalSubs = [];

        foreach ($this->sourceCases as $case) {
            $sourcePath = $this->sourceDir . DIRECTORY_SEPARATOR . $case['source'];
            if (!is_file($sourcePath)) {
                throw new RuntimeException('No existe fuente: ' . $sourcePath);
            }

            $raw = file_get_contents($sourcePath);
            if ($raw === false) {
                throw new RuntimeException('No se pudo leer: ' . $sourcePath);
            }

            $anon = $this->anonymize($raw, $globalSubs);
            $issues = $this->detectResidualPii($anon);
            $status = empty($issues) ? 'ANON_OK' : 'REVISION_MANUAL';

            $caseDir = $this->targetDir . DIRECTORY_SEPARATOR . strtolower($case['rama']) . DIRECTORY_SEPARATOR . $case['id'];
            $this->ensureDir($caseDir);

            $this->writeFile($caseDir . DIRECTORY_SEPARATOR . 'cv_anonimizado.txt', $anon);

            $expected = [
                'id_case' => $case['id'],
                'rama' => $case['rama'],
                'perfil' => $case['perfil'],
                'estado_anonimizacion' => $status,
                'fuente_original' => $case['source'],
                'checks_minimos' => [
                    'contiene_publicaciones' => stripos($anon, 'publica') !== false,
                    'contiene_proyectos' => stripos($anon, 'proyecto') !== false,
                    'contiene_docencia' => stripos($anon, 'docen') !== false,
                    'contiene_congresos' => stripos($anon, 'congreso') !== false,
                    'contiene_estancias' => stripos($anon, 'estancia') !== false,
                ],
                'pii_residual_detectado' => $issues,
                'apto_para_commit' => $status === 'ANON_OK',
                'observaciones' => $status === 'ANON_OK' ? 'Anonimizado con chequeos basicos sin PII residual detectada.' : 'Requiere revision manual.',
            ];
            $this->writeJson($caseDir . DIRECTORY_SEPARATOR . 'expected.json', $expected);

            $this->writeFile(
                $caseDir . DIRECTORY_SEPARATOR . 'README.md',
                '# ' . $case['id'] . PHP_EOL
                . PHP_EOL
                . '- rama: ' . $case['rama'] . PHP_EOL
                . '- perfil: ' . $case['perfil'] . PHP_EOL
                . '- fuente_original: `' . $case['source'] . '`' . PHP_EOL
                . '- estado_anonimizacion: `' . $status . '`' . PHP_EOL
                . '- apto_para_commit: `' . ($status === 'ANON_OK' ? 'true' : 'false') . '`' . PHP_EOL
            );

            if ($status === 'ANON_OK') {
                $manifestCases[] = [
                    'id_case' => $case['id'],
                    'rama' => $case['rama'],
                    'perfil' => $case['perfil'],
                    'path' => strtolower($case['rama']) . '/' . $case['id'],
                    'source' => $case['source'],
                ];
            }
        }

        $manualReview = [];
        foreach ($this->manualReviewSources as $sourceName) {
            $manualReview[] = [
                'source' => $sourceName,
                'estado' => 'REVISION_MANUAL',
                'motivo' => 'OCR ruidoso o riesgo alto de PII residual.',
                'incluido_en_dataset_final' => false,
            ];
        }

        $this->writeFile(
            $this->targetDir . DIRECTORY_SEPARATOR . 'README.md',
            "# CV reales anonimizados\n\n"
            . "Copias anonimizadas de CVs reales de prueba.\n\n"
            . "- No contiene archivos originales.\n"
            . "- Si hay duda de reidentificacion, marcar REVISION_MANUAL.\n"
            . "- No mezclar con cv_sinteticos.\n"
        );

        $this->writeFile(
            $this->targetDir . DIRECTORY_SEPARATOR . 'CHECKLIST_ANONIMIZACION.md',
            "# Checklist manual\n\n"
            . "1. Verificar ausencia de nombre/apellidos reales.\n"
            . "2. Verificar ausencia de DNI/NIE/pasaporte.\n"
            . "3. Verificar ausencia de correo/telefono/direccion.\n"
            . "4. Verificar ORCID/ResearcherID/ScopusID anonimizados.\n"
            . "5. Verificar firmas/codigos personales anonimizados.\n"
            . "6. Si hay dudas, marcar REVISION_MANUAL.\n"
        );

        $manifest = [
            'dataset_id' => 'cv_reales_anonimizados_v1',
            'generated_at' => date('c'),
            'generator' => 'evaluador/tests/tools/create_anonymized_real_cv_fixtures.php',
            'source_dir' => str_replace('\\\\', '/', $this->sourceDir),
            'target_dir' => str_replace('\\\\', '/', $this->targetDir),
            'total_cases_final' => count($manifestCases),
            'cases' => $manifestCases,
            'manual_review_cases' => $manualReview,
            'pii_sustituciones' => array_values(array_unique($globalSubs)),
            'apto_para_commit_dataset_final' => true,
            'manual_review_pending' => count($manualReview) > 0,
        ];
        $this->writeJson($this->targetDir . DIRECTORY_SEPARATOR . 'dataset_manifest.json', $manifest);

        fwrite(STDOUT, 'ANON_DATASET_DIR=' . $this->targetDir . PHP_EOL);
        fwrite(STDOUT, 'ANON_CASES_FINAL=' . count($manifestCases) . PHP_EOL);
        fwrite(STDOUT, 'ANON_CASES_MANUAL_REVIEW=' . count($manualReview) . PHP_EOL);

        return 0;
    }

    private function anonymize($raw, &$subs)
    {
        $text = str_replace(["\r\n", "\r"], "\n", $raw);

        $regexMap = [
            '/\\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}\\b/i' => '[EMAIL_ANONIMIZADO]',
            '/\\b\\d{8}[A-Z]\\b/u' => '[DNI_ANONIMIZADO]',
            '/\\b[XYZ]\\d{7}[A-Z]\\b/u' => '[NIE_ANONIMIZADO]',
            '/\\b\\d{4}-\\d{4}-\\d{4}-\\d{4}\\b/u' => '[ORCID_ANONIMIZADO]',
            '/\\b[A-Z]{1,3}-\\d{4}-\\d{4}\\b/u' => '[RESEARCHERID_ANONIMIZADO]',
            '/\\b[0-9a-f]{32}\\b/i' => '[CODIGO_PERSONAL_ANONIMIZADO]',
            '/^(?:.*firma.*)$/miu' => '[FIRMA_ANONIMIZADA]',
            '/\\+?\\d{1,3}[\\s.-]?\\(?\\d{2,4}\\)?[\\s.-]?\\d{3}[\\s.-]?\\d{3}[\\s.-]?\\d{3}/u' => '[TELEFONO_ANONIMIZADO]',
        ];

        foreach ($regexMap as $regex => $replacement) {
            $newText = preg_replace($regex, $replacement, $text);
            if ($newText !== null && $newText !== $text) {
                $text = $newText;
                $subs[] = $replacement;
            }
        }

        $literalNames = [
            'Alberto Mayorgas Reyes', 'Mayorgas Reyes', 'Alberto', 'Mayorgas',
            'Pablo Aranda Romero', 'Aranda Romero', 'Pablo', 'Aranda',
            'Antonio Prados Montaño', 'Antonio Prados Montano',
        ];
        foreach ($literalNames as $name) {
            if (stripos($text, $name) !== false) {
                $text = str_ireplace($name, '[PERSONA_ANONIMIZADA]', $text);
                $subs[] = '[PERSONA_ANONIMIZADA]';
            }
        }

        $lineMap = [
            '/^(\\s*Entidad empleadora\\s*:\\s*).+$/miu' => '$1Universidad anonimizada',
            '/^(\\s*Entidad de realizaci[oó]n\\s*:\\s*).+$/miu' => '$1Universidad anonimizada',
            '/^(\\s*Entidad de titulaci[oó]n\\s*:\\s*).+$/miu' => '$1Universidad anonimizada',
            '/^(\\s*Entidad organizadora\\s*:\\s*).+$/miu' => '$1Universidad anonimizada',
            '/^(\\s*Departamento\\s*:\\s*).+$/miu' => '$1Departamento anonimizado',
            '/^(\\s*Ciudad de nacimiento\\s*:\\s*).+$/miu' => '$1[CIUDAD_ANONIMIZADA]',
            '/^(\\s*C\\.\\s*Aut[oó]n\\.\\/Reg\\. de nacimiento\\s*:\\s*).+$/miu' => '$1[REGION_ANONIMIZADA]',
        ];
        foreach ($lineMap as $regex => $replacement) {
            $newText = preg_replace($regex, $replacement, $text);
            if ($newText !== null && $newText !== $text) {
                $text = $newText;
                $subs[] = '[LINEA_ANONIMIZADA]';
            }
        }

        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+){1,3}$/u', trim($line)) === 1) {
                $lines[$i] = '[PERSONA_ANONIMIZADA]';
                $subs[] = '[PERSONA_ANONIMIZADA]';
            }
            break;
        }
        $text = implode("\n", $lines);

        $text = preg_replace('/\\bUniversidad\\s+[A-ZÁÉÍÓÚÑ][^\\n,;:.]{2,80}/u', 'Universidad anonimizada', $text) ?: $text;

        return $text;
    }

    private function detectResidualPii($text)
    {
        $issues = [];
        $checks = [
            'email' => '/\\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}\\b/i',
            'dni' => '/\\b\\d{8}[A-Z]\\b/u',
            'nie' => '/\\b[XYZ]\\d{7}[A-Z]\\b/u',
            'orcid' => '/\\b\\d{4}-\\d{4}-\\d{4}-\\d{4}\\b/u',
        ];
        foreach ($checks as $label => $regex) {
            if (preg_match($regex, $text) === 1) {
                $issues[] = 'PII residual detectado: ' . $label;
            }
        }

        foreach (['Alberto', 'Mayorgas', 'Pablo', 'Aranda'] as $token) {
            if (stripos($text, $token) !== false) {
                $issues[] = 'Nombre potencial residual: ' . $token;
            }
        }

        return array_values(array_unique($issues));
    }

    private function resetTargetDir($dir)
    {
        if (is_dir($dir)) {
            $this->deleteRecursive($dir);
        }
        $this->ensureDir($dir);
    }

    private function deleteRecursive($dir)
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
                $this->deleteRecursive($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }

    private function ensureDir($dir)
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear directorio: ' . $dir);
        }
    }

    private function writeJson($path, $data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar JSON: ' . $path);
        }
        $this->writeFile($path, $json . PHP_EOL);
    }

    private function writeFile($path, $content)
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('No se pudo escribir: ' . $path);
        }
    }
}

try {
    $repoRoot = dirname(__DIR__, 3);
    $runner = new RealCvAnonymizer($repoRoot);
    exit($runner->run());
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
