<?php

final class AnonymizedRealCvValidator
{
    private $baseDir;

    public function __construct($repoRoot)
    {
        $this->baseDir = $repoRoot . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cv_reales_anonimizados';
    }

    public function run()
    {
        if (!is_dir($this->baseDir)) {
            fwrite(STDERR, '[ERROR] No existe dataset: ' . $this->baseDir . PHP_EOL);
            return 1;
        }

        $files = $this->listFiles($this->baseDir);
        $errors = [];
        $warnings = [];

        $patterns = [
            'email' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            'dni' => '/\b\d{8}[A-Z]\b/u',
            'nie' => '/\b[XYZ]\d{7}[A-Z]\b/u',
            'orcid_real' => '/\b\d{4}-\d{4}-\d{4}-\d{3}[0-9X]\b/u',
            'telefono_es' => '/(?:\+34\s*)?[6789]\d{8}\b|\(\s*34\s*\)\s*[6789]\d{8}\b/u',
            'researcherid_real' => '/\b[A-Z]{1,3}-\d{4}-\d{4}\b/u',
            'scopusid_largo' => '/\b\d{10,16}\b/u',
            'path_windows' => '/[A-Za-z]:\\\\[^\s]+/u',
            'path_unix' => '#/(?:home|Users|var|tmp)/[^\s]+#u',
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                $errors[] = 'No se pudo leer: ' . $this->rel($file);
                continue;
            }

            foreach ($patterns as $label => $regex) {
                if (preg_match($regex, $content, $m, PREG_OFFSET_CAPTURE) === 1) {
                    $sample = $m[0][0];
                    $errors[] = $this->rel($file) . ' | ' . $label . ' | ' . $sample;
                }
            }
        }

        $manifestPath = $this->baseDir . DIRECTORY_SEPARATOR . 'dataset_manifest.json';
        if (!is_file($manifestPath)) {
            $errors[] = 'Falta dataset_manifest.json';
        } else {
            $manifestRaw = file_get_contents($manifestPath);
            $manifest = $manifestRaw !== false ? json_decode($manifestRaw, true) : null;
            if (!is_array($manifest)) {
                $errors[] = 'dataset_manifest.json invalido';
            } else {
                foreach (['source_dir', 'target_dir'] as $forbiddenKey) {
                    if (array_key_exists($forbiddenKey, $manifest)) {
                        $errors[] = 'manifest contiene clave no permitida: ' . $forbiddenKey;
                    }
                }
                if (isset($manifest['cases']) && is_array($manifest['cases'])) {
                    foreach ($manifest['cases'] as $idx => $case) {
                        if (isset($case['source'])) {
                            $errors[] = 'manifest cases[' . $idx . '] contiene source no neutralizado';
                        }
                    }
                }
            }
        }

        foreach ($this->findExpectedJson($files) as $expectedPath) {
            $raw = file_get_contents($expectedPath);
            $json = $raw !== false ? json_decode($raw, true) : null;
            if (!is_array($json)) {
                $errors[] = 'expected invalido: ' . $this->rel($expectedPath);
                continue;
            }
            if (!isset($json['pii_residual_detectado']) || !is_array($json['pii_residual_detectado'])) {
                $errors[] = 'expected sin pii_residual_detectado[]: ' . $this->rel($expectedPath);
            }
            if (!isset($json['estado_anonimizacion'])) {
                $warnings[] = 'expected sin estado_anonimizacion: ' . $this->rel($expectedPath);
            }
        }

        fwrite(STDOUT, 'Validando dataset anonimizados: ' . $this->baseDir . PHP_EOL);
        fwrite(STDOUT, 'files=' . count($files) . PHP_EOL);
        fwrite(STDOUT, 'errors=' . count($errors) . ' warnings=' . count($warnings) . PHP_EOL);

        foreach ($errors as $err) {
            fwrite(STDOUT, '[ERROR] ' . $err . PHP_EOL);
        }
        foreach ($warnings as $warn) {
            fwrite(STDOUT, '[WARN] ' . $warn . PHP_EOL);
        }

        if (!empty($errors)) {
            fwrite(STDOUT, 'ANON_VALIDATION=FAIL' . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, 'ANON_VALIDATION=PASS' . PHP_EOL);
        return 0;
    }

    private function listFiles($root)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($rii as $fileInfo) {
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    private function findExpectedJson(array $files)
    {
        $out = [];
        foreach ($files as $file) {
            if (substr($file, -13) === 'expected.json') {
                $out[] = $file;
            }
        }
        return $out;
    }

    private function rel($path)
    {
        return str_replace($this->baseDir . DIRECTORY_SEPARATOR, '', $path);
    }
}

try {
    $repoRoot = dirname(__DIR__, 2);
    $runner = new AnonymizedRealCvValidator($repoRoot);
    exit($runner->run());
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

