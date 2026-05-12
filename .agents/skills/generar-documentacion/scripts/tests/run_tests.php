<?php
declare(strict_types=1);

require_once __DIR__ . '/../generar_documentacion.php';

function run_all_tests(): void
{
    $baseDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'generar_documentacion_tests_' . uniqid('', true);
    $docsDir = $baseDir . DIRECTORY_SEPARATOR . 'docs';
    mkdir($docsDir, 0777, true);

    $date = getTodayDate();
    $technicalPath = $docsDir . DIRECTORY_SEPARATOR . 'estado-tecnico-' . $date . '.md';
    $dailyPath = $docsDir . DIRECTORY_SEPARATOR . 'registro-diario-' . $date . '.md';

    $payloadOne = [
        'author' => 'Basilio Lagares',
        'technical_updates' => [
            '1' => ['Implementacion inicial del modulo A.'],
            '2' => ['Backend de tutorias.'],
            '3' => ['Creacion de endpoint de consulta.'],
        ],
        'daily_updates' => [
            '1' => ['Avance funcional validado en entorno local.'],
            '2' => ['Pruebas manuales de la ruta critica.'],
        ],
        'run_tests' => false,
        'output_dir' => $docsDir,
    ];

    $exitCodeOne = run_main_with_payload($payloadOne);
    assert_true($exitCodeOne === 0, 'La primera ejecucion debe finalizar en codigo 0.');
    assert_true(is_file($technicalPath), 'Debe crearse el documento tecnico diario.');
    assert_true(is_file($dailyPath), 'Debe crearse el registro diario.');

    $technicalAfterFirstRun = read_file($technicalPath);
    $dailyAfterFirstRun = read_file($dailyPath);

    assert_contains($technicalAfterFirstRun, '# Estado técnico del día', 'Debe respetar el titulo del tecnico.');
    assert_contains($dailyAfterFirstRun, '# Registro diario de trabajo', 'Debe respetar el titulo del registro diario.');
    assert_contains($technicalAfterFirstRun, 'AUTOR: Basilio Lagares', 'Debe guardar autor en cabecera.');
    assert_contains($technicalAfterFirstRun, 'ROL: Desarrollo backend', 'Debe usar rol por defecto de Basilio.');
    assert_ordered_headings(
        $technicalAfterFirstRun,
        [
            '## 1. Resumen técnico de la jornada',
            '## 2. Módulos o áreas afectadas',
            '## 3. Cambios realizados',
            '## 4. Impacto en arquitectura o integración',
            '## 5. Dependencias relevantes',
            '## 6. Riesgos y pendientes',
            '## 7. Próximos pasos',
            '## 8. Validación y pruebas ejecutadas',
            '## Firma',
        ],
        'El tecnico debe mantener el orden fijo de secciones.'
    );
    assert_contains(
        $technicalAfterFirstRun,
        '- No se han realizado tests en esta ejecución.',
        'Si no se ejecutan tests debe dejar constancia explicita en la seccion 8.'
    );

    $payloadTwo = [
        'author' => 'Basilio Lagares',
        'role' => '',
        'technical_updates' => [
            '1' => ['Implementacion inicial del modulo A.'],
            '3' => ['Refactor de validaciones en endpoint de consulta.'],
            '6' => ['Pendiente de cierre de contrato JSON transversal.'],
        ],
        'daily_updates' => [
            '2' => ['Pruebas manuales de la ruta critica.'],
            '6' => ['Coordinar validacion cruzada con frontend.'],
        ],
        'run_tests' => true,
        'test_results' => [
            'executed' => true,
            'suiteName' => 'ejecutar-tests:standard-15m',
            'total' => 12,
            'passed' => 11,
            'failed' => 1,
            'errors' => ['[backend-smoke] Fallo controlado en test X'],
            'summary' => 'Bateria con incidencias controladas.',
            'timestamp' => '2026-03-27 10:30:00',
            'observations' => 'Revisar caso backend-smoke.',
        ],
        'output_dir' => $docsDir,
    ];

    $exitCodeTwo = run_main_with_payload($payloadTwo);
    assert_true($exitCodeTwo === 0, 'La segunda ejecucion debe finalizar en codigo 0.');

    $technicalAfterSecondRun = read_file($technicalPath);
    $dailyAfterSecondRun = read_file($dailyPath);

    assert_contains(
        $technicalAfterSecondRun,
        '- Refactor de validaciones en endpoint de consulta.',
        'Debe fusionar informacion nueva en tecnico.'
    );
    assert_true(
        substr_count($technicalAfterSecondRun, 'Implementacion inicial del modulo A.') === 1,
        'No debe duplicar contenido ya registrado en tecnico.'
    );
    assert_contains(
        $technicalAfterSecondRun,
        '- Batería de tests ejecutada: sí',
        'Debe registrar ejecucion real de tests en tecnico.'
    );
    assert_contains(
        $technicalAfterSecondRun,
        '- Resultado general: Bateria con incidencias controladas.',
        'Debe documentar resumen real de tests.'
    );
    assert_contains(
        $dailyAfterSecondRun,
        '- Tras la generación de la documentación se ejecutó la batería de tests.',
        'Debe registrar en diario que se ejecutaron tests.'
    );

    $payloadThree = [
        'author' => 'Ana Perez',
        'role' => 'QA',
        'technical_updates' => [
            '4' => ['Validacion de integracion con contratos REST de tutorias.'],
        ],
        'daily_updates' => [
            '3' => ['Se acuerda checklist de regresion para demo tecnica.'],
        ],
        'run_tests' => false,
        'output_dir' => $docsDir,
    ];

    $exitCodeThree = run_main_with_payload($payloadThree);
    assert_true($exitCodeThree === 0, 'La tercera ejecucion debe finalizar en codigo 0.');

    $technicalAfterThirdRun = read_file($technicalPath);
    $dailyAfterThirdRun = read_file($dailyPath);

    assert_contains(
        $technicalAfterThirdRun,
        'AUTOR: Basilio Lagares',
        'Debe conservar en cabecera el primer autor del dia.'
    );
    assert_contains(
        $technicalAfterThirdRun,
        '- [Ana Perez | QA] Validacion de integracion con contratos REST de tutorias.',
        'Debe etiquetar nuevas lineas cuando cambia el autor.'
    );
    assert_contains(
        $technicalAfterThirdRun,
        '- No se han realizado tests en esta ejecución.',
        'En ejecucion sin tests debe dejar constancia explicita.'
    );
    assert_contains(
        $technicalAfterThirdRun,
        '- Última validación registrada del día: 2026-03-27 10:30:00',
        'Si ya hubo tests previos debe conservar la ultima validacion util del dia.'
    );
    assert_contains(
        $dailyAfterThirdRun,
        '- No se han realizado tests en esta ejecución.',
        'Registro diario debe mantener constancia explicita sin tests.'
    );

    $payloadMissingRole = [
        'author' => 'Ana Perez',
        'role' => '',
        'technical_updates' => [
            '1' => ['Intento sin rol obligatorio.'],
        ],
        'daily_updates' => [],
        'run_tests' => false,
        'output_dir' => $docsDir,
    ];

    $exitCodeMissingRole = run_main_with_payload($payloadMissingRole);
    assert_true($exitCodeMissingRole === 1, 'Debe fallar si autor no es Basilio y falta rol.');

    $payloadWithoutUpdates = [
        'author' => 'Basilio Lagares',
        'role' => '',
        'technical_updates' => [],
        'daily_updates' => [],
        'run_tests' => false,
        'output_dir' => $docsDir,
    ];

    $exitCodeWithoutUpdates = run_main_with_payload($payloadWithoutUpdates);
    assert_true($exitCodeWithoutUpdates === 1, 'Debe fallar cuando no hay datos nuevos.');

    $exitCodeInvalidMode = run_main_with_args(['generar_documentacion.php', '--non-interactive']);
    assert_true(
        $exitCodeInvalidMode === 1,
        'Debe fallar si se usa --non-interactive sin payload explicito para preservar separacion de modos.'
    );

    recursive_delete($baseDir);
}

/**
 * @param array<string, mixed> $payload
 */
function run_main_with_payload(array $payload): int
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        throw new RuntimeException('No fue posible serializar el payload de prueba.');
    }

    ob_start();
    $exitCode = main(['generar_documentacion.php', '--payload-json', $json, '--non-interactive']);
    ob_end_clean();
    return $exitCode;
}

/**
 * @param array<int, string> $args
 */
function run_main_with_args(array $args): int
{
    ob_start();
    $exitCode = main($args);
    ob_end_clean();
    return $exitCode;
}

function read_file(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException(sprintf('No fue posible leer el archivo: %s', $path));
    }
    return $content;
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_contains(string $haystack, string $needle, string $message): void
{
    assert_true(str_contains($haystack, $needle), $message);
}

/**
 * @param array<int, string> $headings
 */
function assert_ordered_headings(string $content, array $headings, string $message): void
{
    $lastPosition = -1;
    foreach ($headings as $heading) {
        $position = strpos($content, $heading);
        if ($position === false || $position < $lastPosition) {
            throw new RuntimeException($message);
        }
        $lastPosition = $position;
    }
}

function recursive_delete(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path)) {
        unlink($path);
        return;
    }

    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        recursive_delete($path . DIRECTORY_SEPARATOR . $item);
    }

    rmdir($path);
}

try {
    run_all_tests();
    fwrite(STDOUT, "OK: pruebas de generar-documentacion completadas.\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'FALLO: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
