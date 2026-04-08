<?php
declare(strict_types=1);

/**
 * Probe de evaluador por area para ANECA.
 *
 * Ejecuta N iteraciones con datos sinteticos y valida invariantes de salida:
 * - limites por bloque
 * - coherencia de totales
 * - coherencia de reglas de decision
 */

/**
 * @return array{area:string,iterations:int,seed:int}
 */
function parseArgs(array $argv): array
{
    $opts = [
        'area' => 'tecnicas',
        'iterations' => 100,
        'seed' => random_int(1, PHP_INT_MAX),
    ];

    foreach ($argv as $arg) {
        if (!str_starts_with((string) $arg, '--')) {
            continue;
        }

        $parts = explode('=', substr((string) $arg, 2), 2);
        $key = trim($parts[0]);
        $value = $parts[1] ?? '';

        if ($key === 'area' && $value !== '') {
            $opts['area'] = strtolower($value);
            continue;
        }

        if ($key === 'iterations' && $value !== '') {
            $opts['iterations'] = max(1, (int) $value);
            continue;
        }

        if ($key === 'seed' && $value !== '') {
            $opts['seed'] = (int) $value;
        }
    }

    return $opts;
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function randomOf(array $values)
{
    return $values[array_rand($values)];
}

function randomBool(int $percentTrue = 50): bool
{
    return mt_rand(1, 100) <= $percentTrue;
}

/**
 * @return array<string,mixed>
 */
function buildCommonData(): array
{
    $pubCount = mt_rand(1, 8);
    $libCount = mt_rand(0, 4);
    $proyCount = mt_rand(0, 4);
    $transCount = mt_rand(0, 4);
    $tesisCount = mt_rand(0, 3);
    $congCount = mt_rand(0, 5);
    $otrosCount = mt_rand(0, 3);

    $publicaciones = [];
    for ($i = 0; $i < $pubCount; $i++) {
        $isPatent = randomBool(20);
        $publicaciones[] = [
            'tipo' => $isPatent ? 'patente' : 'articulo',
            'es_valida' => randomBool(85),
            'tipo_indice' => randomOf(['JCR', 'SJR', 'FECYT', 'RESH', 'CORE', 'CSIE', 'PATENTE']),
            'cuartil' => randomOf(['Q1', 'Q2', 'Q3', 'Q4']),
            'tercil' => randomOf(['T1', 'T2', 'T3']),
            'subtipo_indice' => randomOf(['A', 'A+', 'CLASE_1', 'C1', 'C2', 'C3', 'PLUS', 'STANDARD', 'B1', 'B2']),
            'tipo_aportacion' => randomOf(['articulo', 'edicion_critica', 'analisis_empirico', 'estudio_juridico']),
            'tipo_estudio' => randomOf(['ensayo_clinico', 'metaanalisis', 'revision_sistematica', 'cohorte', 'observacional']),
            'afinidad' => randomOf(['total', 'relacionada', 'periferica', 'ajena']),
            'posicion_autor' => randomOf(['autor_unico', 'primero', 'ultimo', 'correspondencia', 'intermedio', 'secundario']),
            'numero_autores' => mt_rand(1, 18),
            'citas' => mt_rand(0, 200),
            'anios_desde_publicacion' => mt_rand(0, 12),
            'liderazgo' => randomBool(35),
        ];
    }

    $libros = [];
    for ($i = 0; $i < $libCount; $i++) {
        $libros[] = [
            'tipo' => randomOf(['libro', 'capitulo']),
            'es_valido' => randomBool(85),
            'es_libro_investigacion' => randomBool(85),
            'es_autoedicion' => randomBool(8),
            'es_acta_congreso' => randomBool(8),
            'es_labor_edicion' => randomBool(8),
            'nivel_editorial' => randomOf(['prestigiosa', 'secundaria', 'baja']),
            'afinidad' => randomOf(['total', 'relacionada', 'periferica', 'ajena']),
            'posicion_autor' => randomOf(['autor_unico', 'primero', 'ultimo', 'intermedio']),
            'numero_autores' => mt_rand(1, 8),
        ];
    }

    $proyectos = [];
    for ($i = 0; $i < $proyCount; $i++) {
        $proyectos[] = [
            'es_valido' => randomBool(85),
            'esta_certificado' => randomBool(85),
            'tipo_proyecto' => randomOf([
                'internacional',
                'nacional',
                'autonomico',
                'universidad',
                'hospitalario',
                'ensayo_clinico',
                'contrato_empresa',
                'proyecto_patrimonio',
                'transferencia_institucional',
            ]),
            'rol' => randomOf(['ip', 'coip', 'investigador', 'participacion_menor']),
            'dedicacion' => randomOf(['completa', 'parcial', 'residual']),
            'anios_duracion' => mt_rand(0, 8),
        ];
    }

    $transferencia = [];
    for ($i = 0; $i < $transCount; $i++) {
        $transferencia[] = [
            'es_valido' => randomBool(85),
            'tipo' => randomOf([
                'patente_b1',
                'patente_b2',
                'contrato_empresa',
                'software_explotacion',
                'ebt',
                'guia_clinica',
                'protocolo_implantado',
                'innovacion_asistencial',
                'comisariado',
                'catalogo_critico',
                'transferencia_cultural',
                'informe_institucional',
                'dictamen_juridico',
                'transferencia_social',
                'contrato_institucion',
            ]),
            'impacto_externo' => randomBool(60),
            'liderazgo' => randomBool(40),
            'participacion_menor' => randomBool(20),
        ];
    }

    $tesis = [];
    for ($i = 0; $i < $tesisCount; $i++) {
        $tesis[] = [
            'es_valido' => randomBool(85),
            'tipo' => randomOf(['direccion_unica', 'codireccion']),
            'calidad_especial' => randomBool(30),
        ];
    }

    $congresos = [];
    for ($i = 0; $i < $congCount; $i++) {
        $eventId = 'ev_' . mt_rand(1, 5);
        $congresos[] = [
            'es_valido' => randomBool(85),
            'ambito' => randomOf(['internacional', 'nacional', 'regional', 'local']),
            'tipo' => randomOf(['ponencia_invitada', 'comunicacion_oral', 'poster']),
            'id_evento' => $eventId,
        ];
    }

    $otros = [];
    for ($i = 0; $i < $otrosCount; $i++) {
        $otros[] = [
            'es_valido' => randomBool(85),
            'tipo' => randomOf([
                'revisor',
                'consejo_editorial',
                'tribunal_tesis',
                'premio',
                'grupo_investigacion',
                'sociedad_cientifica',
            ]),
        ];
    }

    $docencia = [];
    for ($i = 0; $i < mt_rand(1, 5); $i++) {
        $docencia[] = [
            'es_valido' => randomBool(90),
            'horas' => mt_rand(10, 350),
            'nivel' => randomOf(['grado', 'master']),
            'responsabilidad' => randomOf(['alta', 'media', 'baja']),
            'docencia_clinica' => randomBool(20),
        ];
    }

    $evalDocente = [];
    for ($i = 0; $i < mt_rand(0, 3); $i++) {
        $evalDocente[] = [
            'es_valido' => randomBool(90),
            'tipo' => randomOf(['docentia', 'encuestas']),
            'resultado' => randomOf(['excelente', 'positiva', 'aceptable']),
        ];
    }

    $formDoc = [];
    for ($i = 0; $i < mt_rand(0, 3); $i++) {
        $formDoc[] = [
            'es_valido' => randomBool(90),
            'horas' => mt_rand(5, 160),
            'rol' => randomOf(['docente', 'asistente']),
        ];
    }

    $materialDoc = [];
    for ($i = 0; $i < mt_rand(0, 3); $i++) {
        $materialDoc[] = [
            'es_valido' => randomBool(90),
            'tipo' => randomOf([
                'material_publicado',
                'proyecto_innovacion',
                'publicacion_docente',
                'simulacion_clinica',
                'recurso_digital',
                'menor',
            ]),
        ];
    }

    $formacion = [];
    for ($i = 0; $i < mt_rand(0, 4); $i++) {
        $formacion[] = [
            'es_valido' => randomBool(90),
            'tipo' => randomOf([
                'doctorado_internacional',
                'especialidad_salud',
                'beca_competitiva',
                'estancia',
                'master',
                'curso_especializacion',
                'acreditacion_lenguas',
                'acreditacion_profesional',
            ]),
        ];
    }

    $experiencia = [];
    for ($i = 0; $i < mt_rand(0, 3); $i++) {
        $experiencia[] = [
            'es_valido' => randomBool(90),
            'anios' => mt_rand(0, 10),
            'relacion' => randomOf(['alta', 'media', 'baja']),
            'actividad_asistencial' => randomBool(25),
        ];
    }

    $bloque4 = [];
    for ($i = 0; $i < mt_rand(0, 3); $i++) {
        $bloque4[] = [
            'es_valido' => randomBool(90),
            'tipo' => randomOf(['gestion', 'servicio_academico', 'distincion', 'sociedad_cientifica', 'otro']),
        ];
    }

    return [
        'bloque_1' => [
            'publicaciones' => $publicaciones,
            'libros' => $libros,
            'proyectos' => $proyectos,
            'transferencia' => $transferencia,
            'tesis_dirigidas' => $tesis,
            'congresos' => $congresos,
            'otros_meritos_investigacion' => $otros,
        ],
        'bloque_2' => [
            'docencia_universitaria' => $docencia,
            'evaluacion_docente' => $evalDocente,
            'formacion_docente' => $formDoc,
            'material_docente' => $materialDoc,
        ],
        'bloque_3' => [
            'formacion_academica' => $formacion,
            'experiencia_profesional' => $experiencia,
        ],
        'bloque_4' => $bloque4,
    ];
}

/**
 * @return array<string,mixed>
 */
function buildExperimentalesData(): array
{
    $buildItems = static function (int $count, float $maxItem): array {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = round(((float) mt_rand(0, (int) ($maxItem * 100))) / 100, 2);
            $value = randomBool(30) ? str_replace('.', ',', (string) $raw) : $raw;
            $items[] = ['puntuacion' => $value];
        }
        return $items;
    };

    return [
        'bloque_1' => [
            'publicaciones' => $buildItems(mt_rand(0, 8), 8.0),
            'libros' => $buildItems(mt_rand(0, 4), 3.0),
            'proyectos' => $buildItems(mt_rand(0, 4), 3.0),
            'transferencia' => $buildItems(mt_rand(0, 4), 2.0),
            'tesis_dirigidas' => $buildItems(mt_rand(0, 3), 2.0),
            'congresos' => $buildItems(mt_rand(0, 5), 1.5),
            'otros_meritos_investigacion' => $buildItems(mt_rand(0, 3), 1.0),
        ],
        'bloque_2' => [
            'docencia_universitaria' => $buildItems(mt_rand(0, 5), 5.0),
            'evaluacion_docente' => $buildItems(mt_rand(0, 3), 1.5),
            'formacion_docente' => $buildItems(mt_rand(0, 3), 1.5),
            'material_docente' => $buildItems(mt_rand(0, 3), 2.0),
        ],
        'bloque_3' => [
            'formacion_academica' => $buildItems(mt_rand(0, 4), 2.0),
            'experiencia_profesional' => $buildItems(mt_rand(0, 3), 1.5),
        ],
        'bloque_4' => $buildItems(mt_rand(0, 3), 1.0),
    ];
}

/**
 * @return array<string,mixed>
 */
function evaluateArea(string $area, array $datos): array
{
    $root = dirname(__DIR__);

    if ($area === 'tecnicas') {
        require_once $root . '/evaluador_aneca_tecnicas/funciones_evaluador_tecnicas.php';
        return evaluar_expediente($datos);
    }

    if ($area === 'salud') {
        require_once $root . '/evaluador_aneca_salud/funciones_evaluador_salud.php';
        return evaluar_expediente($datos);
    }

    if ($area === 'humanidades') {
        require_once $root . '/evaluador_aneca_humanidades/funciones_evaluador_humanidades.php';
        return evaluar_expediente($datos);
    }

    if ($area === 'csyj') {
        require_once $root . '/evaluador_aneca_csyj/funciones_evaluador_csyj.php';
        return evaluar_expediente($datos);
    }

    if ($area === 'experimentales') {
        require_once $root . '/evaluador_aneca_experimentales/funciones_evaluador_experimentales.php';
        $b1 = calcular_bloque_1_experimentales($datos['bloque_1'] ?? []);
        $b2 = calcular_bloque_2_experimentales($datos['bloque_2'] ?? []);
        $b3 = calcular_bloque_3_experimentales($datos['bloque_3'] ?? []);
        $b4 = calcular_bloque_4_experimentales($datos['bloque_4'] ?? []);
        $totales = calcular_totales_experimentales($b1, $b2, $b3, $b4);
        $decision = evaluar_experimentales($totales);

        return [
            'bloque_1' => $b1,
            'bloque_2' => $b2,
            'bloque_3' => $b3,
            'bloque_4' => $b4,
            'totales' => $totales,
            'decision' => $decision,
        ];
    }

    throw new InvalidArgumentException('Area no soportada: ' . $area);
}

/**
 * @param array<string,mixed> $resultado
 */
function validateResult(array $resultado): void
{
    foreach (['bloque_1', 'bloque_2', 'bloque_3', 'bloque_4', 'totales', 'decision'] as $key) {
        assertTrue(array_key_exists($key, $resultado), 'Falta clave de salida: ' . $key);
        assertTrue(is_array($resultado[$key]), 'La clave ' . $key . ' debe ser array');
    }

    $b1 = (float) ($resultado['bloque_1']['B1'] ?? 0.0);
    $b2 = (float) ($resultado['bloque_2']['B2'] ?? 0.0);
    $b3 = (float) ($resultado['bloque_3']['B3'] ?? 0.0);
    $b4 = (float) ($resultado['bloque_4']['B4'] ?? 0.0);

    assertTrue($b1 >= 0.0 && $b1 <= 60.0, 'B1 fuera de limites');
    assertTrue($b2 >= 0.0 && $b2 <= 30.0, 'B2 fuera de limites');
    assertTrue($b3 >= 0.0 && $b3 <= 8.0, 'B3 fuera de limites');
    assertTrue($b4 >= 0.0 && $b4 <= 2.0, 'B4 fuera de limites');

    $totalB1B2 = (float) ($resultado['totales']['total_b1_b2'] ?? 0.0);
    $totalFinal = (float) ($resultado['totales']['total_final'] ?? 0.0);

    assertTrue(abs($totalB1B2 - round($b1 + $b2, 2)) < 0.01, 'total_b1_b2 inconsistente');
    assertTrue(abs($totalFinal - round($b1 + $b2 + $b3 + $b4, 2)) < 0.01, 'total_final inconsistente');

    $regla1 = (bool) ($resultado['decision']['cumple_regla_1'] ?? false);
    $regla2 = (bool) ($resultado['decision']['cumple_regla_2'] ?? false);

    assertTrue($regla1 === ($totalB1B2 >= 50.0), 'Regla 1 inconsistente');
    assertTrue($regla2 === ($totalFinal >= 55.0), 'Regla 2 inconsistente');

    assertTrue(json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== false, 'Salida no serializable');
}

function main(array $argv): int
{
    try {
        $opts = parseArgs($argv);
        $validAreas = ['tecnicas', 'salud', 'humanidades', 'csyj', 'experimentales'];
        if (!in_array($opts['area'], $validAreas, true)) {
            throw new InvalidArgumentException('Area invalida: ' . $opts['area']);
        }

        mt_srand($opts['seed']);

        $positivas = 0;
        $negativas = 0;

        for ($i = 0; $i < $opts['iterations']; $i++) {
            $data = $opts['area'] === 'experimentales'
                ? buildExperimentalesData()
                : buildCommonData();

            $resultado = evaluateArea($opts['area'], $data);
            validateResult($resultado);

            $isPositive = false;
            if (array_key_exists('positiva', $resultado['decision'] ?? [])) {
                $isPositive = (bool) $resultado['decision']['positiva'];
            } else {
                $isPositive = strtoupper((string) ($resultado['decision']['resultado'] ?? 'NEGATIVA')) === 'POSITIVA';
            }

            if ($isPositive) {
                $positivas++;
            } else {
                $negativas++;
            }
        }

        $payload = [
            'ok' => true,
            'area' => $opts['area'],
            'iterations' => $opts['iterations'],
            'seed' => $opts['seed'],
            'positivas' => $positivas,
            'negativas' => $negativas,
        ];

        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        return 0;
    } catch (Throwable $e) {
        $error = [
            'ok' => false,
            'error' => $e->getMessage(),
        ];
        fwrite(STDERR, json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        return 1;
    }
}

if (isset($argv[0]) && realpath((string) $argv[0]) === __FILE__) {
    exit(main($argv));
}
