<?php
declare(strict_types=1);

require_once __DIR__ . '/../ejecutar_tests.php';

function run_all_tests(): void
{
    $repositoryRoot = dirname(__DIR__, 4);
    $checks = buildChecks($repositoryRoot, 'standard');
    assert_true(count($checks) >= 3, 'La suite standard debe contener checks base.');

    $result = executeChecks($checks, $repositoryRoot, 'ejecutar-tests:standard-15m', true);
    assert_true(is_array($result), 'La ejecucion debe devolver un array.');
    assert_true(($result['suiteName'] ?? '') === 'ejecutar-tests:standard-15m', 'Suite incorrecta.');
    assert_true(array_key_exists('summary', $result), 'Debe existir summary.');
    assert_true(array_key_exists('timestamp', $result), 'Debe existir timestamp.');
    assert_true(isset($result['checks']) && is_array($result['checks']), 'Debe existir detalle de checks.');
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

