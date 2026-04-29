<?php
declare(strict_types=1);

require_once __DIR__ . '/../ejecutar_tests.php';

function run_all_tests(): void
{
    $repositoryRoot = dirname(__DIR__, 4);
    $built = buildChecks($repositoryRoot, 'standard', '15m', 'toda-app', 'auto', 'json');
    assert_true(isset($built['checks']) && is_array($built['checks']), 'buildChecks debe devolver lista de checks.');
    assert_true(count($built['checks']) >= 3, 'La suite standard debe contener checks base.');
    assert_true(isset($built['plan']) && is_array($built['plan']), 'buildChecks debe devolver plan.');

    $result = executeChecks($built['checks'], $repositoryRoot, 'ejecutar-tests:standard-15m', true, $built['plan']);
    assert_true(is_array($result), 'La ejecucion debe devolver un array.');
    assert_true(($result['suiteName'] ?? '') === 'ejecutar-tests:standard-15m', 'Suite incorrecta.');
    assert_true(array_key_exists('summary', $result), 'Debe existir summary.');
    assert_true(array_key_exists('timestamp', $result), 'Debe existir timestamp.');
    assert_true(isset($result['checks']) && is_array($result['checks']), 'Debe existir detalle de checks.');
    assert_true(isset($result['executionPlan']) && is_array($result['executionPlan']), 'Debe incluir executionPlan.');
    assert_true(isset($result['checkStats']) && is_array($result['checkStats']), 'Debe incluir checkStats.');
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    run_all_tests();
    fwrite(STDOUT, "OK: pruebas de ejecutar-tests completadas.\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'FALLO: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

