<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/TextCleaner.php';
require_once __DIR__ . '/../src/AnecaExtractor.php';
require_once __DIR__ . '/../src/Pipeline.php';
require_once __DIR__ . '/../src/OcrProcessor.php';

final class UnitRunner
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<int,string> */
    private array $errors = [];

    public function run(string $name, callable $fn): void
    {
        try {
            $fn();
            $this->passed++;
            fwrite(STDOUT, '[PASS] ' . $name . PHP_EOL);
        } catch (Throwable $e) {
            $this->failed++;
            $this->errors[] = '[FAIL] ' . $name . ' -> ' . $e->getMessage();
            fwrite(STDOUT, '[FAIL] ' . $name . PHP_EOL);
        }
    }

    public function assertTrue(bool $cond, string $msg): void
    {
        if (!$cond) {
            throw new RuntimeException($msg);
        }
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    public function assertSame($expected, $actual, string $msg): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                $msg . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true)
            );
        }
    }

    /**
     * @param array<string,mixed> $arr
     */
    public function assertHasKey(string $key, array $arr, string $msg): void
    {
        if (!array_key_exists($key, $arr)) {
            throw new RuntimeException($msg . ' missing=' . $key);
        }
    }

    public function summary(): int
    {
        fwrite(STDOUT, PHP_EOL . '=== ANECA Unit Summary ===' . PHP_EOL);
        fwrite(STDOUT, 'passed=' . $this->passed . ' failed=' . $this->failed . PHP_EOL);
        foreach ($this->errors as $error) {
            fwrite(STDOUT, $error . PHP_EOL);
        }
        return $this->failed;
    }
}

function randomText(int $len): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789  .,;:-_()[]{}!?' . "\n\t";
    $out = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

$t = new UnitRunner();

$t->run('TextCleaner normaliza espacios y saltos', function () use ($t): void {
    $cleaner = new TextCleaner();
    $input = "Hola   mundo\r\n\r\n\r\nlinea\t\t2";
    $out = $cleaner->limpiar($input);
    $t->assertSame("Hola mundo\n\nlinea 2", $out, 'TextCleaner no normalizo como se esperaba');
});

$t->run('AnecaExtractor devuelve esquema base', function () use ($t): void {
    $extractor = new AnecaExtractor();
    $res = $extractor->extraer('texto plano sin datos');

    foreach (['bloque_1', 'bloque_2', 'bloque_3', 'bloque_4', 'metadatos_extraccion'] as $key) {
        $t->assertHasKey($key, $res, 'Falta clave de salida');
    }

    $t->assertTrue(is_array($res['bloque_1']), 'bloque_1 debe ser array');
    $t->assertTrue(is_array($res['bloque_2']), 'bloque_2 debe ser array');
    $t->assertTrue(is_array($res['bloque_3']), 'bloque_3 debe ser array');
    $t->assertTrue(is_array($res['bloque_4']), 'bloque_4 debe ser array');
    $t->assertTrue(is_array($res['metadatos_extraccion']), 'metadatos_extraccion debe ser array');
});

$t->run('AnecaExtractor detecta entidades relevantes', function () use ($t): void {
    $texto = implode("\n", [
        'Articulo JCR Q1 DOI 10.1234/example 3 autores 25 citas 2021 primer autor',
        'Libro ISBN editorial SPI coleccion de referencia',
        'Proyecto nacional IP dedicacion completa 3 anos continuidad',
        'Contrato de transferencia con software y liderazgo principal',
        'Tesis codireccion internacional con premio',
        'Congreso internacional ponencia invitada',
        '250 horas de grado',
        '120 horas master',
        'DOCENTIA excelente y encuestas de evaluacion docente',
        '60 horas formacion docente',
        'material publicado ISBN y proyecto de innovacion docente',
        'mencion internacional y beca competitiva FPU',
        '6 anos experiencia profesional en empresa del sector',
        'gestion coordinador y distincion',
    ]);

    $extractor = new AnecaExtractor();
    $res = $extractor->extraer($texto);

    $t->assertTrue(count($res['bloque_1']['publicaciones'] ?? []) > 0, 'Debe detectar publicaciones');
    $t->assertTrue(count($res['bloque_1']['libros'] ?? []) > 0, 'Debe detectar libros');
    $t->assertTrue(count($res['bloque_1']['proyectos'] ?? []) > 0, 'Debe detectar proyectos');
    $t->assertTrue(count($res['bloque_1']['transferencia'] ?? []) > 0, 'Debe detectar transferencia');
    $t->assertTrue(count($res['bloque_1']['tesis_dirigidas'] ?? []) > 0, 'Debe detectar tesis');
    $t->assertTrue(count($res['bloque_1']['congresos'] ?? []) > 0, 'Debe detectar congresos');
    $t->assertTrue(count($res['bloque_2']['docencia_universitaria'] ?? []) > 0, 'Debe detectar docencia');
    $t->assertTrue(count($res['bloque_2']['evaluacion_docente'] ?? []) > 0, 'Debe detectar evaluacion docente');
    $t->assertTrue(count($res['bloque_2']['formacion_docente'] ?? []) > 0, 'Debe detectar formacion docente');
    $t->assertTrue(count($res['bloque_2']['material_docente'] ?? []) > 0, 'Debe detectar material docente');
    $t->assertTrue(count($res['bloque_3']['formacion_academica'] ?? []) > 0, 'Debe detectar formacion academica');
    $t->assertTrue(count($res['bloque_4'] ?? []) > 0, 'Debe detectar bloque_4');
});

$t->run('AnecaExtractor fuzz mantiene contrato', function () use ($t): void {
    $extractor = new AnecaExtractor();
    for ($i = 0; $i < 200; $i++) {
        $txt = randomText(random_int(0, 3000));
        $res = $extractor->extraer($txt);
        foreach (['bloque_1', 'bloque_2', 'bloque_3', 'bloque_4', 'metadatos_extraccion'] as $key) {
            $t->assertHasKey($key, $res, 'Contrato roto en iteracion ' . $i);
        }
        $encoded = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $t->assertTrue($encoded !== false, 'JSON invalido en iteracion ' . $i);
    }
});

$t->run('Pipeline falla con PDF inexistente', function () use ($t): void {
    $pipeline = new Pipeline();
    $thrown = false;
    try {
        $pipeline->procesar(__DIR__ . '/missing-input.pdf');
    } catch (Throwable $e) {
        $thrown = true;
        $t->assertTrue(str_contains(strtolower($e->getMessage()), 'no existe'), 'Mensaje inesperado para PDF inexistente');
    }
    $t->assertTrue($thrown, 'Pipeline debio lanzar excepcion');
});

$t->run('OcrProcessor gestiona error en entrada no valida', function () use ($t): void {
    $ocr = new OcrProcessor();
    $thrown = false;
    try {
        $ocr->procesarImagenes([__FILE__]);
    } catch (Throwable $e) {
        $thrown = true;
    }
    $t->assertTrue($thrown, 'OcrProcessor debio fallar con entrada no imagen');
});

exit($t->summary() > 0 ? 1 : 0);
