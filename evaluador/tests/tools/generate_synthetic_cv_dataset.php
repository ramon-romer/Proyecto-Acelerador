<?php
declare(strict_types=1);

/**
 * Genera un dataset sintetico de CV por rama ANECA en formato TXT + expected.json.
 *
 * Uso (desde raiz del repo):
 *   php evaluador/tests/tools/generate_synthetic_cv_dataset.php
 *
 * Opciones:
 *   --output-dir=<ruta>
 *   --per-rama=<int>      (default: 2)
 *   --seed=<int>          (default: 20260504)
 *   --force               (sobrescribe archivos existentes)
 */

final class SyntheticCvDatasetGenerator
{
    private string $outputDir;
    private int $perBranch;
    private int $seed;
    private bool $force;

    /** @var array<int, string> */
    private array $usedIds = [];

    public function __construct(string $outputDir, int $perBranch, int $seed, bool $force)
    {
        $this->outputDir = $outputDir;
        $this->perBranch = max(2, $perBranch);
        $this->seed = $seed;
        $this->force = $force;
    }

    public function run(): int
    {
        mt_srand($this->seed);

        $this->ensureDir($this->outputDir);

        $branches = $this->branchSpecs();
        $cases = [];

        foreach ($branches as $branch) {
            $branchCases = $this->buildBranchCases($branch, $this->perBranch);
            foreach ($branchCases as $case) {
                $this->writeCase($branch, $case);
                $cases[] = [
                    'id_cv' => $case['id_cv'],
                    'rama' => $case['rama'],
                    'perfil' => $case['perfil'],
                    'path' => $branch['folder'] . '/' . $case['id_cv'],
                ];
            }
        }

        $this->writeRootReadme($branches);
        $this->writeManifest($branches, $cases);

        fwrite(STDOUT, "Dataset sintetico generado en: {$this->outputDir}" . PHP_EOL);
        fwrite(STDOUT, "Casos generados: " . count($cases) . PHP_EOL);
        fwrite(STDOUT, "Ramas: " . count($branches) . PHP_EOL);
        fwrite(STDOUT, "Casos por rama: {$this->perBranch}" . PHP_EOL);
        return 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function branchSpecs(): array
    {
        return [
            [
                'key' => 'experimentales',
                'folder' => 'experimentales',
                'prefix' => 'EXP',
                'rama' => 'EXPERIMENTALES',
                'equivalencias' => ['Experimentales'],
                'descripcion' => 'Rama con foco en JCR, DOI, proyectos competitivos y estancias de laboratorio.',
            ],
            [
                'key' => 'tecnicas',
                'folder' => 'tecnicas',
                'prefix' => 'TEC',
                'rama' => 'TECNICAS',
                'equivalencias' => ['Tecnicas'],
                'descripcion' => 'Rama con foco en I+D aplicada, congresos tecnicos, transferencia y patentes/software.',
            ],
            [
                'key' => 'csyj',
                'folder' => 'csyj',
                'prefix' => 'CSYJ',
                'rama' => 'CSYJ',
                'equivalencias' => ['Sociales', 'Juridicas'],
                'descripcion' => 'Rama canonica del proyecto para Ciencias Sociales y Juridicas.',
            ],
            [
                'key' => 'salud',
                'folder' => 'salud',
                'prefix' => 'SAL',
                'rama' => 'SALUD',
                'equivalencias' => ['Salud', 'Biomedicas', 'Ciencias de la Salud'],
                'descripcion' => 'Rama con articulos biomedicos, estudios clinicos ficticios y congresos medicos.',
            ],
            [
                'key' => 'humanidades',
                'folder' => 'humanidades',
                'prefix' => 'HUM',
                'rama' => 'HUMANIDADES',
                'equivalencias' => ['Arte y Humanidades'],
                'descripcion' => 'Rama con libros, capitulos, catalogos, proyectos culturales y docencia.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $branch
     * @return array<int, array<string, mixed>>
     */
    private function buildBranchCases(array $branch, int $perBranch): array
    {
        $profiles = ['positivo', 'problematico', 'negativo', 'frontera'];
        $branchStress = [
            'experimentales' => ['doi_incompleto', 'sin_cuartil', 'fechas_mezcladas'],
            'tecnicas' => ['orcid_mal_formado', 'duplicados_publicaciones', 'tabla_simulada'],
            'csyj' => ['duplicados_publicaciones', 'secciones_alternativas', 'fechas_mezcladas'],
            'salud' => ['orcid_ausente', 'texto_narrativo_extenso', 'doi_incompleto'],
            'humanidades' => ['orden_raro_secciones', 'sin_cuartil', 'secciones_alternativas'],
        ];
        $stressPatterns = $branchStress[(string)$branch['key']] ?? ['fechas_mezcladas'];

        $cases = [];
        for ($i = 1; $i <= $perBranch; $i++) {
            $profile = $profiles[($i - 1) % count($profiles)];
            $stress = 'none';
            if ($profile !== 'positivo') {
                $stress = $stressPatterns[($i - 2) % count($stressPatterns)];
            }
            $cases[] = $this->buildCase($branch, $i, $profile, $stress);
        }

        return $cases;
    }

    /**
     * @param array<string, mixed> $branch
     * @return array<string, mixed>
     */
    private function buildCase(array $branch, int $ordinal, string $perfil, string $stress): array
    {
        $id = sprintf('%s-%03d', $branch['prefix'], $ordinal);
        if (in_array($id, $this->usedIds, true)) {
            throw new RuntimeException("ID duplicado detectado durante generacion: {$id}");
        }
        $this->usedIds[] = $id;

        $name = $this->fakeName($ordinal, (string)$branch['key']);
        $orcid = $this->fakeOrcid($ordinal, $perfil, $stress);

        $sections = $this->buildSections((string)$branch['key'], $perfil, $ordinal, $stress);
        $expected = $this->buildExpected($id, (string)$branch['rama'], $perfil, $orcid, $sections, $stress);

        return [
            'id_cv' => $id,
            'rama' => $branch['rama'],
            'perfil' => $perfil,
            'nombre_ficticio' => $name,
            'orcid_prueba' => $orcid,
            'resumen_perfil' => $sections['resumen'],
            'formacion_academica' => $sections['formacion'],
            'docencia' => $sections['docencia'],
            'publicaciones' => $sections['publicaciones'],
            'proyectos' => $sections['proyectos'],
            'congresos' => $sections['congresos'],
            'estancias' => $sections['estancias'],
            'transferencia_patentes' => $sections['transferencia'],
            'otros_meritos' => $sections['otros'],
            'problemas_intencionados' => $sections['problemas'],
            'expected' => $expected,
        ];
    }

    /**
     * @param array<string, mixed> $branch
     * @param array<string, mixed> $case
     */
    private function writeCase(array $branch, array $case): void
    {
        $caseDir = $this->outputDir . DIRECTORY_SEPARATOR . $branch['folder'] . DIRECTORY_SEPARATOR . $case['id_cv'];
        $this->ensureDir($caseDir);

        $this->writeFile($caseDir . DIRECTORY_SEPARATOR . 'cv.txt', $this->renderCvTxt($case));
        $this->writeFile($caseDir . DIRECTORY_SEPARATOR . 'cv_cvn_like.txt', $this->renderCvCvnLike($case));
        $this->writeJson($caseDir . DIRECTORY_SEPARATOR . 'expected.json', $case['expected']);
        $this->writeFile($caseDir . DIRECTORY_SEPARATOR . 'README.md', $this->renderCaseReadme($case));
    }

    /**
     * @param array<string, mixed> $case
     */
    private function renderCvTxt(array $case): string
    {
        $lines = [];
        $lines[] = 'id_cv: ' . $case['id_cv'];
        $lines[] = 'rama: ' . $case['rama'];
        $lines[] = 'perfil: ' . $case['perfil'];
        $lines[] = 'nombre_ficticio: ' . $case['nombre_ficticio'];
        $lines[] = 'orcid_prueba: ' . ($case['orcid_prueba'] ?? '');
        $lines[] = 'resumen_perfil: ' . $case['resumen_perfil'];
        $lines[] = '';
        $lines[] = 'formacion_academica:';
        foreach ($case['formacion_academica'] as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
        $lines[] = 'docencia:';
        foreach ($case['docencia'] as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
        $lines[] = 'publicaciones:';
        foreach ($case['publicaciones'] as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
        $lines[] = 'proyectos:';
        foreach ($case['proyectos'] as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
        $lines[] = 'congresos:';
        foreach ($case['congresos'] as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
        $lines[] = 'estancias:';
        foreach ($case['estancias'] as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
        $lines[] = 'transferencia_patentes:';
        foreach ($case['transferencia_patentes'] as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
        $lines[] = 'otros_meritos:';
        foreach ($case['otros_meritos'] as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
        $lines[] = 'problemas_intencionados:';
        foreach ($case['problemas_intencionados'] as $item) {
            $lines[] = '- ' . $item;
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $case
     */
    private function renderCaseReadme(array $case): string
    {
        $lines = [];
        $lines[] = '# Caso ' . $case['id_cv'];
        $lines[] = '';
        $lines[] = '- rama: `' . $case['rama'] . '`';
        $lines[] = '- perfil: `' . $case['perfil'] . '`';
        $lines[] = '- nombre_ficticio: `' . $case['nombre_ficticio'] . '`';
        $lines[] = '- orcid_prueba: `' . ($case['orcid_prueba'] ?? '') . '`';
        $lines[] = '';
        $lines[] = '## Resumen';
        $lines[] = $case['resumen_perfil'];
        $lines[] = '';
        $lines[] = '## Archivos';
        $lines[] = '- `cv.txt`: curriculo sintetico en texto plano.';
        $lines[] = '- `cv_cvn_like.txt`: variante estructurada para extractor/pipeline.';
        $lines[] = '- `expected.json`: expectativas para validaciones del evaluador.';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $case
     */
    private function renderCvCvnLike(array $case): string
    {
        $lines = [];
        $lines[] = 'DATOS GENERALES';
        $lines[] = 'NOMBRE ' . $case['nombre_ficticio'];
        $lines[] = 'ORCID ' . ($case['orcid_prueba'] ?? '');
        $lines[] = 'RAMA ' . $case['rama'];
        $lines[] = 'PERFIL ' . $case['perfil'];
        $lines[] = '';

        foreach ($case['publicaciones'] as $idx => $pub) {
            $cuartil = $this->detectarCuartilDesdeLinea((string)$pub);
            $anio = (string)(2020 + (($idx + 1) % 6));
            $lines[] = 'Publicación';
            $lines[] = 'TITULO ' . $this->limpiarTexto((string)$pub);
            $lines[] = 'NOMBRE Revista sintetica ' . $case['rama'];
            $lines[] = 'ISSN 1234-56' . str_pad((string)(($idx % 90) + 10), 2, '0', STR_PAD_LEFT);
            $lines[] = 'CALIDAD BASE ' . ($this->contieneTermino((string)$pub, ['jcr']) ? 'JCR' : 'SJR');
            $lines[] = 'CALIDAD POSICION ' . $cuartil;
            $lines[] = 'AÑO ' . $anio;
            $lines[] = 'CALIDAD CITAS ' . (string)(8 + $idx);
            $lines[] = 'NUMERO AUTORES ' . (string)(2 + ($idx % 4));
            $lines[] = 'POSICION ' . (string)(1 + ($idx % 2));
            $lines[] = '';
        }

        $lines[] = 'LIBROS Y CAPITULOS DE LIBRO';
        $lines[] = 'Libros';
        $lines[] = 'TITULO Produccion academica sintetica ' . $case['rama'];
        $lines[] = 'EDITORIAL Editorial de prueba';
        $lines[] = '';

        $lines[] = 'PROYECTOS Y/O CONTRATOS DE INVESTIGACION';
        foreach ($case['proyectos'] as $idx => $proy) {
            $lines[] = 'Proyectos';
            $lines[] = 'TITULO ' . $this->limpiarTexto((string)$proy);
            $lines[] = 'ENTIDAD Agencia sintetica de investigacion';
            $lines[] = 'FDESDE 2021-01-01';
            $lines[] = 'FHASTA 2024-12-31';
            $lines[] = 'MESES 36';
            $lines[] = 'GRADO TIPO 10001';
            $lines[] = 'GRADO OTROS IP';
            $lines[] = 'APORTACION Coordinacion cientifica';
            $lines[] = 'DEDICACION XX001';
            $lines[] = 'CONV TIPO 09002';
            $lines[] = 'CONV OTROS Nacional';
            $lines[] = '';
        }

        $lines[] = 'TRANSFERENCIA TECNOLOGICA';
        foreach ($case['transferencia_patentes'] as $item) {
            $lines[] = '- ' . $this->limpiarTexto((string)$item);
        }
        $lines[] = '';

        $lines[] = 'CONGRESOS Y CONFERENCIAS CIENTIFICAS';
        foreach ($case['congresos'] as $idx => $cong) {
            $lines[] = 'Congresos';
            $lines[] = 'TITULO ' . $this->limpiarTexto((string)$cong);
            $lines[] = 'CONGRESO Evento sintetico ' . ($idx + 1);
            $lines[] = 'ENTIDAD Sociedad academica sintetica';
            $lines[] = 'LUGAR Madrid';
            $lines[] = 'FCELEBRACION 2024-0' . (($idx % 8) + 1) . '-15';
            $lines[] = '';
        }

        $lines[] = 'ESTANCIAS EN CENTROS ESPAÑOLES Y EXTRANJEROS';
        foreach ($case['estancias'] as $est) {
            $lines[] = '- ' . $this->limpiarTexto((string)$est);
        }
        $lines[] = '';

        $lines[] = 'PUESTOS OCUPADOS Y DOCENCIA IMPARTIDA';
        foreach ($case['docencia'] as $doc) {
            $horas = $this->extraerHorasDesdeLinea((string)$doc);
            $lines[] = 'Docencia Impartida';
            $lines[] = 'INSTITUCION Universidad sintetica';
            $lines[] = 'ASIGNATURA ' . $this->limpiarTexto((string)$doc);
            $lines[] = 'HORAS ' . (string)$horas;
            $lines[] = 'ESPECIFICAR grado';
            $lines[] = 'DENOMINACION Profesor contratado doctor';
            $lines[] = 'TIPO ASIGNATURA 15001';
            $lines[] = '';
        }

        $lines[] = 'FORMACION ACADEMICA';
        foreach ($case['formacion_academica'] as $for) {
            $lines[] = '- ' . $this->limpiarTexto((string)$for);
        }
        $lines[] = '';

        $lines[] = 'OTROS MERITOS';
        foreach ($case['otros_meritos'] as $otro) {
            $lines[] = '- ' . $this->limpiarTexto((string)$otro);
        }
        foreach ($case['problemas_intencionados'] as $problema) {
            $lines[] = '- PROBLEMA: ' . $this->limpiarTexto((string)$problema);
        }
        $lines[] = '';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<int, array<string, mixed>> $branches
     */
    private function writeRootReadme(array $branches): void
    {
        $mappingLines = [];
        foreach ($branches as $branch) {
            $mappingLines[] = '- `' . $branch['rama'] . '` <= ' . implode(', ', $branch['equivalencias']);
        }

        $content = implode(PHP_EOL, [
            '# Dataset sintetico de CV ANECA',
            '',
            'Dataset interno de prueba para estresar el pipeline de extraccion/evaluacion ANECA sin usar datos personales reales.',
            '',
            '## Objetivo',
            '- Cubrir ramas canonicas del proyecto con casos positivos, negativos, frontera y problematicos.',
            '- Mantener fixtures reproducibles y aislados en `evaluador/tests/fixtures/cv_sinteticos`.',
            '- Facilitar ampliacion gradual de 2 a 20 casos por rama.',
            '',
            '## Ramas canonicas detectadas y mapeo',
            ...$mappingLines,
            '',
            '## Estructura',
            '- `<rama>/<ID>/cv.txt`',
            '- `<rama>/<ID>/cv_cvn_like.txt`',
            '- `<rama>/<ID>/expected.json`',
            '- `<rama>/<ID>/README.md`',
            '- `dataset_manifest.json`',
            '',
            '## Generacion reproducible',
            '```bash',
            'php evaluador/tests/tools/generate_synthetic_cv_dataset.php --per-rama=2 --seed=20260504 --force',
            '```',
            '',
            'Para ampliar a 20 CV por rama:',
            '```bash',
            'php evaluador/tests/tools/generate_synthetic_cv_dataset.php --per-rama=20 --seed=20260504 --force',
            '```',
            '',
            '## Validacion del dataset',
            '```bash',
            'php evaluador/tests/validate_synthetic_cv_dataset.php',
            '```',
            '',
            '## Notas',
            '- No depende de descargas externas.',
            '- No toca `evaluador/src` ni contratos JSON existentes del core.',
            '- PDF opcional fuera de alcance de esta primera entrega; foco en TXT + expected.json.',
            '',
        ]) . PHP_EOL;

        $this->writeFile($this->outputDir . DIRECTORY_SEPARATOR . 'README.md', $content);
    }

    /**
     * @param array<int, array<string, mixed>> $branches
     * @param array<int, array<string, mixed>> $cases
     */
    private function writeManifest(array $branches, array $cases): void
    {
        $branchMapping = [];
        foreach ($branches as $branch) {
            $branchMapping[$branch['rama']] = $branch['equivalencias'];
        }

        $manifest = [
            'dataset_id' => 'aneca_cv_sinteticos_v1',
            'version' => '1.0.0',
            'generated_at' => date('c'),
            'generator' => 'evaluador/tests/tools/generate_synthetic_cv_dataset.php',
            'seed' => $this->seed,
            'target_objetivo_por_rama' => 20,
            'casos_generados_por_rama' => $this->perBranch,
            'ramas_canonicas' => array_map(
                static fn(array $branch): string => (string)$branch['rama'],
                $branches
            ),
            'mapeo_ramas_solicitadas' => $branchMapping,
            'perfiles_soportados' => ['positivo', 'negativo', 'frontera', 'problematico'],
            'cases' => $cases,
        ];

        $this->writeJson($this->outputDir . DIRECTORY_SEPARATOR . 'dataset_manifest.json', $manifest);
    }

    /**
     * @param array<string, mixed> $sections
     * @return array<string, mixed>
     */
    private function buildExpected(string $id, string $rama, string $perfil, ?string $orcid, array $sections, string $stress): array
    {
        $resultByProfile = [
            'positivo' => 'apto',
            'negativo' => 'no_apto',
            'frontera' => 'frontera',
            'problematico' => 'revisar',
        ];

        $confidenceByProfile = [
            'positivo' => 'alto',
            'negativo' => 'alto',
            'frontera' => 'medio',
            'problematico' => 'bajo',
        ];

        $pubTotal = count($sections['publicaciones']);
        $pubRelevant = 0;
        foreach ($sections['publicaciones'] as $item) {
            if (stripos($item, 'Q1') !== false || stripos($item, 'Q2') !== false || stripos($item, 'indexada') !== false) {
                $pubRelevant++;
            }
        }

        return [
            'id_cv' => $id,
            'rama' => $rama,
            'perfil' => $perfil,
            'resultado_esperado' => $resultByProfile[$perfil] ?? 'revisar',
            'orcid_esperado' => $orcid,
            'publicaciones_esperadas_total' => $pubTotal,
            'publicaciones_relevantes_esperadas' => $pubRelevant,
            'proyectos_esperados' => count($sections['proyectos']),
            'docencia_esperada' => count($sections['docencia']),
            'congresos_esperados' => count($sections['congresos']),
            'estancias_esperadas' => count($sections['estancias']),
            'transferencia_esperada' => count($sections['transferencia']),
            'problemas_intencionados' => $sections['problemas'],
            'observaciones' => 'Caso sintetico generado automaticamente para validar parser/evaluador.',
            'nivel_confianza_esperado' => $confidenceByProfile[$perfil] ?? 'medio',
            'stress_tag' => $stress,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSections(string $branchKey, string $perfil, int $ordinal, string $stress): array
    {
        $base = [
            'resumen' => '',
            'formacion' => [],
            'docencia' => [],
            'publicaciones' => [],
            'proyectos' => [],
            'congresos' => [],
            'estancias' => [],
            'transferencia' => [],
            'otros' => [],
            'problemas' => [],
        ];

        switch ($branchKey) {
            case 'experimentales':
                $base['resumen'] = $perfil === 'positivo'
                    ? 'Perfil de investigacion experimental con articulos JCR y proyectos competitivos.'
                    : 'Perfil experimental con inconsistencias de metadatos y evidencias incompletas.';
                $base['formacion'] = [
                    'Doctorado en Biologia Molecular (2015).',
                    'Master en Tecnicas Avanzadas de Laboratorio (2011).',
                ];
                $base['docencia'] = $perfil === 'positivo'
                    ? ['180 horas en Fisiologia Celular (2022-2025).', 'Direccion de 2 TFM en biociencias.']
                    : ['48 horas de seminarios puntuales sin evaluacion docente consolidada.'];
                $base['publicaciones'] = $perfil === 'positivo'
                    ? [
                        'Articulo JCR Q1: Biomarker dynamics in synthetic cohort. DOI:10.1234/exp.2024.001',
                        'Articulo JCR Q2: Lab protocol benchmark. DOI:10.1234/exp.2023.115',
                        'Articulo Q3 en revista de microbiologia aplicada. DOI:10.1234/exp.2022.220',
                    ]
                    : [
                        'Preprint sin cuartil declarado sobre cultivo celular adaptativo.',
                        'Articulo con DOI incompleto 10.1234/exp.',
                    ];
                $base['proyectos'] = $perfil === 'positivo'
                    ? ['IP de proyecto competitivo nacional BIO-SYNTH (2023-2026).']
                    : ['Colaboracion puntual en proyecto interno sin memoria final cerrada.'];
                $base['congresos'] = [
                    'Ponencia oral en congreso internacional de biotecnologia (2024).',
                    'Poster en simposio de bioquimica aplicada (2023).',
                ];
                $base['estancias'] = $perfil === 'positivo'
                    ? ['Estancia de 4 meses en laboratorio europeo de proteomica (2021).']
                    : ['Estancia declarada sin fechas exactas ni carta de acogida.'];
                $base['transferencia'] = $perfil === 'positivo'
                    ? ['Software de analisis de imagen celular registrado en OTRI (2025).']
                    : ['Sin transferencia formal acreditada.'];
                $base['otros'] = ['Revisora ad hoc de revista experimental indexada.'];
                break;

            case 'tecnicas':
                $base['resumen'] = $perfil === 'positivo'
                    ? 'Perfil tecnico con I+D aplicada, transferencia y patentes/software.'
                    : 'Perfil tecnico con produccion irregular y documentacion incompleta.';
                $base['formacion'] = [
                    'Doctorado en Ingenieria de Sistemas (2016).',
                    'Master en Inteligencia Artificial Industrial (2012).',
                ];
                $base['docencia'] = $perfil === 'positivo'
                    ? ['240 horas en Arquitectura de Computadores y Sistemas Embebidos.', 'Coordinacion de laboratorio de prototipado.']
                    : ['400 horas de docencia muy valorada, pero con baja produccion investigadora.'];
                $base['publicaciones'] = $perfil === 'positivo'
                    ? [
                        'Articulo tecnico indexado sobre edge AI en manufactura. DOI:10.2345/tec.2024.077',
                        'Paper en congreso CORE A sobre optimizacion de firmware industrial.',
                    ]
                    : [
                        'Resumen extendido en workshop local sin indexacion.',
                        'Publicacion duplicada del mismo paper en dos listados internos.',
                    ];
                $base['proyectos'] = $perfil === 'positivo'
                    ? ['IP en proyecto I+D RETECH-PLANTA (2022-2025).', 'Participacion en contrato con empresa automotriz.']
                    : ['Proyecto de transferencia cancelado en fase de pruebas.'];
                $base['congresos'] = ['Comunicacion en congreso IEEE de automatizacion (2023).'];
                $base['estancias'] = ['Estancia de 2 meses en centro de prototipado europeo (2020).'];
                $base['transferencia'] = $perfil === 'positivo'
                    ? ['Patente nacional de sistema de control predictivo (solicitud ficticia).']
                    : ['Repositorio software sin licencia clara ni evidencia de adopcion.'];
                $base['otros'] = ['Mentoria de equipo de competicion de robotica universitaria.'];
                break;

            case 'csyj':
                $base['resumen'] = $perfil === 'positivo'
                    ? 'Perfil CSYJ con equilibrio en investigacion social/juridica y docencia.'
                    : 'Perfil CSYJ con peso docente y evidencia investigadora ambigua.';
                $base['formacion'] = [
                    'Doctorado en Politicas Publicas (2014).',
                    'Licenciatura en Derecho (2009).',
                ];
                $base['docencia'] = $perfil === 'positivo'
                    ? ['210 horas en Sociologia Juridica y Metodologia de Investigacion.', 'Direccion de 3 TFG.']
                    : ['320 horas de docencia con excelentes encuestas estudiantiles.'];
                $base['publicaciones'] = $perfil === 'positivo'
                    ? [
                        'Articulo indexado en revista de sociologia del derecho (SJR Q2).',
                        'Capitulo doctrinal en libro colectivo sobre regulacion digital.',
                        'Comentario jurisprudencial en revista juridica especializada.',
                    ]
                    : [
                        'Monografia anunciada sin ISBN verificable.',
                        'Articulo juridico sin fecha clara (2023/2024?).',
                    ];
                $base['proyectos'] = $perfil === 'positivo'
                    ? ['Investigadora principal en proyecto de inclusion y acceso a justicia (2021-2024).']
                    : ['Participacion no acreditada en red tematica regional.'];
                $base['congresos'] = ['Ponencia en congreso de ciencias sociales aplicadas (2024).'];
                $base['estancias'] = ['Estancia corta de investigacion en instituto de politicas comparadas.'];
                $base['transferencia'] = ['Informe tecnico para administracion publica sobre normativa digital.'];
                $base['otros'] = ['Coordinacion de clinica juridica universitaria.'];
                break;

            case 'salud':
                $base['resumen'] = $perfil === 'positivo'
                    ? 'Perfil de ciencias de la salud con articulos biomedicos y proyectos sanitarios.'
                    : 'Perfil de salud con narrativa extensa y baja trazabilidad de meritos.';
                $base['formacion'] = [
                    'Doctorado en Ciencias de la Salud (2017).',
                    'Master en Epidemiologia Clinica (2013).',
                ];
                $base['docencia'] = $perfil === 'positivo'
                    ? ['190 horas en Farmacologia Clinica.', 'Tutorizacion de 2 residentes en metodologia investigadora.']
                    : ['50 horas de docencia aislada con gran carga asistencial.'];
                $base['publicaciones'] = $perfil === 'positivo'
                    ? [
                        'Articulo biomedico Q1 sobre marcadores inflamatorios. DOI:10.3456/salud.2024.301',
                        'Articulo Q2 en salud publica sobre adherencia terapeutica. DOI:10.3456/salud.2023.188',
                    ]
                    : [
                        'Serie narrativa de casos clinicos ficticios sin estructuracion formal.',
                        'Publicacion con autores repetidos y DOI incompleto 10.3456/salud.',
                    ];
                $base['proyectos'] = $perfil === 'positivo'
                    ? ['SubIP en proyecto multicentrico de prevencion cardiovascular (ficticio, sin datos reales).']
                    : ['Estudio observacional declarado sin protocolo fechado.'];
                $base['congresos'] = ['Comunicacion oral en congreso nacional de medicina interna (2023).'];
                $base['estancias'] = ['Estancia de 3 meses en unidad de investigacion traslacional (2022).'];
                $base['transferencia'] = ['Guia de practica clinica institucional (documento tecnico interno).'];
                $base['otros'] = ['Participacion en comision de calidad asistencial universitaria.'];
                break;

            case 'humanidades':
                $base['resumen'] = $perfil === 'positivo'
                    ? 'Perfil de humanidades con libros, capitulos y proyectos culturales.'
                    : 'Perfil de humanidades con meritos desordenados y ambiguos.';
                $base['formacion'] = [
                    'Doctorado en Historia del Arte (2012).',
                    'Master en Gestion Cultural (2008).',
                ];
                $base['docencia'] = $perfil === 'positivo'
                    ? ['260 horas en Historia del Arte Contemporaneo.', 'Direccion de 4 TFG y 1 TFM.']
                    : ['120 horas en asignaturas optativas sin coordinacion docente.'];
                $base['publicaciones'] = $perfil === 'positivo'
                    ? [
                        'Libro academico sobre patrimonio visual urbano (ISBN ficticio).',
                        'Capitulo en volumen internacional de estudios humanisticos.',
                        'Articulo en revista de historia cultural indexada.',
                    ]
                    : [
                        'Catalogo de exposicion sin autores claramente identificados.',
                        'Texto de divulgacion sin revision por pares ni fecha estable.',
                    ];
                $base['proyectos'] = $perfil === 'positivo'
                    ? ['IP en proyecto cultural de archivo digital museistico (2020-2024).']
                    : ['Participacion mencionada en comisariado sin acta de cierre.'];
                $base['congresos'] = ['Ponencia en congreso internacional de estudios visuales (2024).'];
                $base['estancias'] = ['Estancia de investigacion en museo europeo (2019).'];
                $base['transferencia'] = ['Comisariado de exposicion universitaria con catalogo tecnico.'];
                $base['otros'] = ['Coordinacion de ciclo de seminarios de patrimonio inmaterial.'];
                break;
        }

        if ($branchKey === 'tecnicas' && $perfil === 'problematico') {
            $base['resumen'] = 'Perfil con docencia fuerte y produccion investigadora insuficiente para rama tecnica.';
            $base['otros'][] = 'Patron de estres: buena docencia pero investigacion limitada.';
        }

        if ($branchKey === 'salud' && $perfil === 'problematico') {
            $base['resumen'] = 'Perfil con investigacion biomedica alta, pero docencia insuficiente y trazabilidad irregular.';
            $base['docencia'] = ['36 horas docentes anuales sin continuidad longitudinal.'];
            $base['publicaciones'] = [
                'Articulo biomedico Q1 sobre diagnostico molecular sintetico. DOI:10.3456/salud.2025.401',
                'Articulo Q1 en medicina traslacional con colaboracion multicentrica ficticia. DOI:10.3456/salud.2024.299',
            ];
            $base['otros'][] = 'Patron de estres: buena investigacion pero mala docencia.';
        }

        if ($perfil === 'negativo') {
            $base['docencia'] = ['360 horas docentes con evaluacion excelente, pero investigacion minima.'];
            $base['publicaciones'] = ['Sin publicaciones indexadas verificables.'];
            $base['proyectos'] = ['Sin proyectos competitivos cerrados.'];
            $base['estancias'] = ['Sin estancias acreditadas.'];
            $base['transferencia'] = ['Sin transferencia acreditable.'];
            $base['resumen'] = 'Perfil con buena docencia pero baja investigacion y transferencia.';
        } elseif ($perfil === 'frontera') {
            $base['publicaciones'][] = 'Articulo adicional con metrica parcial y cuartil no confirmado.';
            $base['docencia'][] = 'Docencia suficiente pero heterogenea por cursos.';
            $base['resumen'] = 'Perfil frontera con indicadores mixtos entre investigacion y docencia.';
        }

        $base['problemas'] = $this->stressToProblems($stress, $perfil, $ordinal);
        $this->injectStress($base, $stress);

        return $base;
    }

    /**
     * @return array<int, string>
     */
    private function stressToProblems(string $stress, string $perfil, int $ordinal): array
    {
        $base = [];
        switch ($stress) {
            case 'none':
                break;
            case 'orcid_ausente':
                $base[] = 'ORCID ausente en cabecera.';
                break;
            case 'orcid_mal_formado':
                $base[] = 'ORCID mal formado con longitud invalida.';
                break;
            case 'doi_incompleto':
                $base[] = 'DOI incompleto en una publicacion.';
                break;
            case 'duplicados_publicaciones':
                $base[] = 'Publicaciones duplicadas en listado.';
                break;
            case 'fechas_mezcladas':
                $base[] = 'Fechas mezcladas en formatos YYYY, YYYY-MM y texto libre.';
                break;
            case 'secciones_alternativas':
                $base[] = 'Secciones con nombres alternativos y no estandar.';
                break;
            case 'sin_cuartil':
                $base[] = 'Publicaciones sin cuartil declarado.';
                break;
            case 'texto_narrativo_extenso':
                $base[] = 'Bloques narrativos extensos sin estructura clara.';
                break;
            case 'orden_raro_secciones':
                $base[] = 'Meritos en orden no convencional.';
                break;
            case 'tabla_simulada':
                $base[] = 'Tabla simulada en texto plano.';
                break;
        }

        if ($perfil === 'problematico') {
            $base[] = 'Requiere revision manual por ambiguedad general del expediente.';
        }
        if ($ordinal % 2 === 0) {
            $base[] = 'Campos con puntuacion y separadores heterogeneos.';
        }
        return $base;
    }

    /**
     * @param array<string, mixed> $sections
     */
    private function injectStress(array &$sections, string $stress): void
    {
        switch ($stress) {
            case 'none':
                break;
            case 'duplicados_publicaciones':
                if (isset($sections['publicaciones'][0])) {
                    $sections['publicaciones'][] = $sections['publicaciones'][0];
                }
                break;
            case 'fechas_mezcladas':
                $sections['otros'][] = 'Cronologia declarada como: 2022, 03/2023, curso 23-24.';
                break;
            case 'secciones_alternativas':
                $sections['otros'][] = 'Alias de seccion detectado: Produccion Cientifica / Impacto.';
                break;
            case 'sin_cuartil':
                $sections['publicaciones'][] = 'Articulo sin cuartil informado en la fuente.';
                break;
            case 'texto_narrativo_extenso':
                $sections['otros'][] = 'Narrativa extensa: candidata relata logros sin separar evidencias por bloques.';
                break;
            case 'orden_raro_secciones':
                $sections['otros'][] = 'Las secciones aparecen en orden: proyectos, docencia, formacion, publicaciones.';
                break;
            case 'tabla_simulada':
                $sections['otros'][] = 'Tabla textual: ANO | ITEM | TIPO | INDICIO';
                break;
            case 'doi_incompleto':
                $sections['publicaciones'][] = 'Registro con DOI incompleto: 10.9999/';
                break;
        }
    }

    private function fakeName(int $ordinal, string $branchKey): string
    {
        $first = ['Alex', 'Noa', 'Izan', 'Lia', 'Dario', 'Nora', 'Saul', 'Mara', 'Teo', 'Ines'];
        $lastA = ['Rivas', 'Campos', 'Serra', 'Navarro', 'Cortes', 'Pardo', 'Molina', 'Luque', 'Iglesias', 'Soler'];
        $lastB = ['Aranda', 'Blasco', 'Cano', 'Vega', 'Nieto', 'Prado', 'Segura', 'Mendez', 'Roman', 'Salas'];
        $seed = abs(crc32($branchKey . '_' . $ordinal));
        $i = $seed % count($first);
        $j = ($seed / 3) % count($lastA);
        $k = ($seed / 7) % count($lastB);
        return $first[(int)$i] . ' ' . $lastA[(int)$j] . ' ' . $lastB[(int)$k];
    }

    private function fakeOrcid(int $ordinal, string $perfil, string $stress): ?string
    {
        if ($stress === 'orcid_ausente') {
            return null;
        }
        if ($stress === 'orcid_mal_formado') {
            return '0000-1111-22X2';
        }

        $blocks = [
            sprintf('%04d', 1000 + $ordinal),
            sprintf('%04d', 2000 + $ordinal),
            sprintf('%04d', 3000 + $ordinal),
            sprintf('%04d', 4000 + $ordinal + ($perfil === 'problematico' ? 5 : 0)),
        ];
        return implode('-', $blocks);
    }

    private function detectarCuartilDesdeLinea(string $linea): string
    {
        if (preg_match('/\bQ([1-4])\b/i', $linea, $m)) {
            return 'Q' . $m[1];
        }
        return '';
    }

    private function extraerHorasDesdeLinea(string $linea): int
    {
        if (preg_match('/(\d{1,4})\s*horas?/i', $linea, $m)) {
            return (int)$m[1];
        }
        return 60;
    }

    private function limpiarTexto(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    /**
     * @param array<int, string> $terms
     */
    private function contieneTermino(string $text, array $terms): bool
    {
        foreach ($terms as $term) {
            if (stripos($text, $term) !== false) {
                return true;
            }
        }
        return false;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear directorio: {$dir}");
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("Error serializando JSON: {$path}");
        }
        $this->writeFile($path, $json . PHP_EOL);
    }

    private function writeFile(string $path, string $content): void
    {
        if (is_file($path) && !$this->force) {
            return;
        }
        $written = file_put_contents($path, $content);
        if ($written === false) {
            throw new RuntimeException("No se pudo escribir archivo: {$path}");
        }
    }
}

/**
 * @return array{outputDir:string, perBranch:int, seed:int, force:bool}
 */
function parseArgs(array $argv): array
{
    $opts = getopt('', ['output-dir::', 'per-rama::', 'seed::', 'force']);
    $root = dirname(__DIR__, 3);

    $outputDir = isset($opts['output-dir']) && is_string($opts['output-dir']) && $opts['output-dir'] !== ''
        ? resolvePath($opts['output-dir'])
        : $root . DIRECTORY_SEPARATOR . 'evaluador' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'cv_sinteticos';

    $perBranch = isset($opts['per-rama']) ? (int)$opts['per-rama'] : 2;
    $seed = isset($opts['seed']) ? (int)$opts['seed'] : 20260504;
    $force = array_key_exists('force', $opts);

    return [
        'outputDir' => $outputDir,
        'perBranch' => $perBranch,
        'seed' => $seed,
        'force' => $force,
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
    $generator = new SyntheticCvDatasetGenerator(
        $args['outputDir'],
        $args['perBranch'],
        $args['seed'],
        $args['force']
    );
    exit($generator->run());
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
