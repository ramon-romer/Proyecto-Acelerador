<?php
declare(strict_types=1);

require __DIR__ . '/../extract_pdf.php';

final class UnitTestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function run(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            fwrite(STDOUT, "[PASS] {$name}" . PHP_EOL);
        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[] = "[FAIL] {$name}: {$e->getMessage()}";
            fwrite(STDOUT, "[FAIL] {$name}" . PHP_EOL);
        }
    }

    public function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                $message . " | esperado=" . var_export($expected, true) . " actual=" . var_export($actual, true)
            );
        }
    }

    public function assertArrayHasKey(string $key, array $arr, string $message): void
    {
        if (!array_key_exists($key, $arr)) {
            throw new RuntimeException($message . " | falta clave: {$key}");
        }
    }

    public function summary(): int
    {
        fwrite(STDOUT, PHP_EOL . "=== Unit Summary ===" . PHP_EOL);
        fwrite(STDOUT, "passed={$this->passed} failed={$this->failed}" . PHP_EOL);
        if ($this->failed > 0) {
            foreach ($this->failures as $failure) {
                fwrite(STDOUT, $failure . PHP_EOL);
            }
        }
        return $this->failed;
    }
}

function keysContrato(): array
{
    return ['tipo_documento', 'numero', 'fecha', 'total_bi', 'iva', 'total_a_pagar', 'texto_preview'];
}

function crearTextoFacturaCompleta(): string
{
    return implode("\n", [
        'FACTURA',
        'Nº: FAC-2026/001',
        'Fecha: 20/03/2026',
        'Total BI 100,00 €',
        'IVA 21% 21,00 €',
        'Total a pagar 121,00 €',
    ]);
}

function randomText(int $len): string
{
    $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 \n\t:;,.!@#€/%-";
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

$t = new UnitTestRunner();

$t->run('DocumentoExtractor extrae campos completos', function () use ($t): void {
    $extractor = new DocumentoExtractor();
    $data = $extractor->extraerCamposDesdeTexto(crearTextoFacturaCompleta());

    $t->assertSame('FACTURA', $data['tipo_documento'], 'Debe detectar FACTURA');
    $t->assertSame('FAC-2026/001', $data['numero'], 'Debe detectar numero');
    $t->assertSame('20/03/2026', $data['fecha'], 'Debe detectar fecha');
    $t->assertSame('100,00 €', $data['total_bi'], 'Debe detectar BI');
    $t->assertSame('21,00 €', $data['iva'], 'Debe detectar IVA');
    $t->assertSame('121,00 €', $data['total_a_pagar'], 'Debe detectar total pagar');
    $t->assertTrue(mb_strlen($data['texto_preview']) > 0, 'texto_preview no debe quedar vacio');
});

$t->run('DocumentoExtractor soporta numero con Numero:', function () use ($t): void {
    $extractor = new DocumentoExtractor();
    $data = $extractor->extraerCamposDesdeTexto("FACTURA\nNumero: AB-77\n");
    $t->assertSame('AB-77', $data['numero'], 'Debe detectar formato Numero');
});

$t->run('DocumentoExtractor detecta fecha YYYY-MM-DD', function () use ($t): void {
    $extractor = new DocumentoExtractor();
    $data = $extractor->extraerCamposDesdeTexto("Fecha emision 2026-03-20");
    $t->assertSame('2026-03-20', $data['fecha'], 'Debe detectar fecha YYYY-MM-DD');
});

$t->run('DocumentoExtractor devuelve null si faltan campos', function () use ($t): void {
    $extractor = new DocumentoExtractor();
    $data = $extractor->extraerCamposDesdeTexto("Texto generico sin datos estructurados");
    $t->assertSame(null, $data['tipo_documento'], 'tipo_documento debe ser null');
    $t->assertSame(null, $data['numero'], 'numero debe ser null');
    $t->assertSame(null, $data['fecha'], 'fecha debe ser null');
    $t->assertSame(null, $data['total_bi'], 'total_bi debe ser null');
    $t->assertSame(null, $data['iva'], 'iva debe ser null');
    $t->assertSame(null, $data['total_a_pagar'], 'total_a_pagar debe ser null');
});

$t->run('texto_preview truncado a 1200', function () use ($t): void {
    $extractor = new DocumentoExtractor();
    $long = str_repeat('A', 3000);
    $data = $extractor->extraerCamposDesdeTexto($long);
    $t->assertSame(1200, mb_strlen($data['texto_preview']), 'texto_preview debe truncar a 1200');
});

$t->run('detectarCamposNegocioFaltantes identifica null y vacios', function () use ($t): void {
    $missing = detectarCamposNegocioFaltantes([
        'tipo_documento' => 'FACTURA',
        'numero' => null,
        'fecha' => '',
        'total_bi' => '100,00 €',
        'iva' => ' ',
        'total_a_pagar' => '121,00 €',
        'texto_preview' => 'x',
    ]);
    sort($missing);
    $t->assertSame(['fecha', 'iva', 'numero'], $missing, 'Missing detectados incorrectamente');
});

$t->run('detectarCamposNegocioFaltantes sin faltantes', function () use ($t): void {
    $missing = detectarCamposNegocioFaltantes([
        'tipo_documento' => 'FACTURA',
        'numero' => 'A-1',
        'fecha' => '20/03/2026',
        'total_bi' => '100,00 €',
        'iva' => '21,00 €',
        'total_a_pagar' => '121,00 €',
        'texto_preview' => 'ok',
    ]);
    $t->assertSame([], $missing, 'No deberia detectar faltantes');
});

$t->run('PdfProcessor lanza error para archivo inexistente', function () use ($t): void {
    $processor = new PdfProcessor();
    $thrown = false;
    try {
        $processor->procesarPdf('Z:/archivo-inexistente-prueba.pdf');
    } catch (Throwable $e) {
        $thrown = true;
        $t->assertTrue(
            str_contains($e->getMessage(), 'El archivo no existe'),
            'Mensaje de archivo inexistente incorrecto'
        );
    }
    $t->assertTrue($thrown, 'Debe lanzar excepcion en archivo inexistente');
});

$t->run('PdfProcessor devuelve claves exactas en PDF real', function () use ($t): void {
    $processor = new PdfProcessor();
    $result = $processor->procesarPdf(__DIR__ . '/../pdf/prueba.pdf');
    $keys = array_keys($result);
    $t->assertSame(keysContrato(), $keys, 'El contrato de salida debe mantener las 7 claves en orden');
});

$t->run('Fuzz extractor no rompe contrato en 200 iteraciones', function () use ($t): void {
    $extractor = new DocumentoExtractor();
    for ($i = 0; $i < 200; $i++) {
        $txt = randomText(random_int(0, 4000));
        $result = $extractor->extraerCamposDesdeTexto($txt);

        $keys = array_keys($result);
        $t->assertSame(keysContrato(), $keys, "Contrato alterado en iteracion {$i}");
        foreach (['tipo_documento', 'numero', 'fecha', 'total_bi', 'iva', 'total_a_pagar'] as $k) {
            $v = $result[$k];
            $t->assertTrue($v === null || is_string($v), "Tipo invalido en {$k} iteracion {$i}");
        }
        $t->assertTrue(is_string($result['texto_preview']), "texto_preview debe ser string iteracion {$i}");
        $t->assertTrue(mb_strlen($result['texto_preview']) <= 1200, "texto_preview > 1200 iteracion {$i}");
    }
});

$t->run('PdfProcessor procesa fuente db con SQLite', function () use ($t): void {
    if (!extension_loaded('pdo_sqlite')) {
        $t->assertTrue(true, 'pdo_sqlite no disponible, se omite test');
        return;
    }

    $dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_unit_' . uniqid('', true) . '.db';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE docs (texto TEXT)');
    $texto = "FACTURA\nNumero: DB-2026/99\nFecha: 20/03/2026\nTotal BI 10,00 EUR\nIVA 21% 2,10 EUR\nTotal a pagar 12,10 EUR";
    $stmt = $pdo->prepare('INSERT INTO docs(texto) VALUES(:t)');
    $stmt->execute([':t' => $texto]);

    $processor = new PdfProcessor();
    $res = $processor->procesarFuente([
        'tipo' => 'db',
        'dsn' => 'sqlite:' . $dbPath,
        'query' => 'SELECT texto FROM docs',
        'text_column' => 'texto',
    ]);

    @unlink($dbPath);

    $t->assertSame('FACTURA', $res['tipo_documento'], 'Debe extraer tipo en fuente db');
    $t->assertSame('DB-2026/99', $res['numero'], 'Debe extraer numero en fuente db');
    $t->assertSame('20/03/2026', $res['fecha'], 'Debe extraer fecha en fuente db');
});

$t->run('parsearArgumentosCli mantiene modo legacy PDF', function () use ($t): void {
    $fuente = parsearArgumentosCli(['extract_pdf.php', 'mcp-server/pdf/prueba.pdf']);
    $t->assertSame('pdf', $fuente['tipo'], 'Tipo debe ser pdf en modo legacy');
    $t->assertSame('mcp-server/pdf/prueba.pdf', $fuente['ruta'], 'Ruta legacy incorrecta');
});

$t->run('parsearArgumentosCli soporta fuente db por flags', function () use ($t): void {
    $fuente = parsearArgumentosCli([
        'extract_pdf.php',
        '--fuente=db',
        '--dsn=sqlite:demo.db',
        '--query=SELECT texto FROM docs',
        '--text_column=texto',
    ]);
    $t->assertSame('db', $fuente['tipo'], 'Tipo db no detectado');
    $t->assertSame('sqlite:demo.db', $fuente['dsn'], 'DSN db incorrecto');
    $t->assertSame('SELECT texto FROM docs', $fuente['query'], 'Query db incorrecta');
    $t->assertSame('texto', $fuente['text_column'], 'Text column incorrecta');
});

exit($t->summary() > 0 ? 1 : 0);
