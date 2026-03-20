<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

final class DocumentoExtractor
{
    public function extraerCamposDesdeTexto(string $texto): array
    {
        return [
            'tipo_documento' => $this->extraerTipoDocumento($texto),
            'numero' => $this->extraerNumero($texto),
            'fecha' => $this->extraerFecha($texto),
            'total_bi' => $this->extraerTotalBI($texto),
            'iva' => $this->extraerIVA($texto),
            'total_a_pagar' => $this->extraerTotalPagar($texto),
            'texto_preview' => mb_substr($texto, 0, 1200),
        ];
    }

    private function extraerTipoDocumento(string $texto): ?string
    {
        if (preg_match('/\bFACTURA\b/i', $texto)) {
            return 'FACTURA';
        }

        return null;
    }

    private function extraerNumero(string $texto): ?string
    {
        if (preg_match('/(?:\bN(?:[\x{00BA}\x{00B0}]|o\.?)\b|\bN(?:u|\x{00FA})mero\b)\s*[:\-]?\s*([A-Z0-9\-\/]+)/iu', $texto, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function extraerFecha(string $texto): ?string
    {
        if (preg_match('/\b\d{2}[\/\-]\d{2}[\/\-]\d{4}\b/', $texto, $m)) {
            return $m[0];
        }

        if (preg_match('/\b\d{4}[\/\-]\d{2}[\/\-]\d{2}\b/', $texto, $m)) {
            return $m[0];
        }

        return null;
    }

    private function extraerTotalBI(string $texto): ?string
    {
        if (preg_match('/Total\s+BI\s+([\d\.\,]+)\s*(?:\x{20AC}|EUR)/iu', $texto, $m)) {
            return trim($m[1]) . ' ' . "\u{20AC}";
        }

        return null;
    }

    private function extraerIVA(string $texto): ?string
    {
        if (preg_match('/IVA\s+\d+%\s+([\d\.\,]+)\s*(?:\x{20AC}|EUR)/iu', $texto, $m)) {
            return trim($m[1]) . ' ' . "\u{20AC}";
        }

        if (preg_match('/Iva\s+\d+%\s+([\d\.\,]+)\s*(?:\x{20AC}|EUR)/iu', $texto, $m)) {
            return trim($m[1]) . ' ' . "\u{20AC}";
        }

        return null;
    }

    private function extraerTotalPagar(string $texto): ?string
    {
        if (preg_match('/Total\s+a\s+pagar\s+([\d\.\,]+)\s*(?:\x{20AC}|EUR)/iu', $texto, $m)) {
            return trim($m[1]) . ' ' . "\u{20AC}";
        }

        return null;
    }
}

final class PdfProcessor
{
    private Parser $parser;
    private DocumentoExtractor $documentoExtractor;
    private ?string $pdftoppmPath;
    private ?string $tesseractPath;

    public function __construct(?DocumentoExtractor $documentoExtractor = null)
    {
        $this->parser = new Parser();
        $this->documentoExtractor = $documentoExtractor ?? new DocumentoExtractor();
        $this->pdftoppmPath = $this->resolverRutaBinario(
            'PDFTOPPM_PATH',
            [
                'C:\\poppler\\Library\\bin\\pdftoppm.exe',
                'C:\\poppler\\bin\\pdftoppm.exe',
            ],
            'pdftoppm'
        );
        $this->tesseractPath = $this->resolverRutaBinario(
            'TESSERACT_PATH',
            ['C:\\Program Files\\Tesseract-OCR\\tesseract.exe'],
            'tesseract'
        );
    }

    public function procesarPdf(string $rutaPdf): array
    {
        if (!is_file($rutaPdf)) {
            throw new RuntimeException('El archivo no existe: ' . $rutaPdf);
        }

        if (!is_readable($rutaPdf)) {
            throw new RuntimeException('El archivo no es legible: ' . $rutaPdf);
        }

        $texto = $this->obtenerTextoDesdePdf($rutaPdf);
        return $this->extraerCamposDesdeTexto($texto);
    }

    public function procesarFuente(array $fuente): array
    {
        $tipo = strtolower(trim((string)($fuente['tipo'] ?? 'pdf')));

        if ($tipo === 'pdf') {
            $rutaPdf = (string)($fuente['ruta'] ?? '');
            if ($rutaPdf === '') {
                throw new RuntimeException('Para fuente tipo pdf debes indicar "ruta".');
            }
            return $this->procesarPdf($rutaPdf);
        }

        if ($tipo === 'db') {
            $texto = $this->obtenerTextoDesdeBaseDatos($fuente);
            return $this->extraerCamposDesdeTexto($texto);
        }

        throw new RuntimeException('Tipo de fuente no soportado: ' . $tipo);
    }

    public function obtenerTextoDesdePdf(string $rutaPdf): string
    {
        $textoNativo = $this->normalizarTexto($this->extraerTextoNativo($rutaPdf));

        if ($this->esTextoNativoSuficiente($textoNativo)) {
            return $textoNativo;
        }

        if (!$this->ocrDisponible()) {
            if (trim($textoNativo) !== '') {
                return $textoNativo;
            }

            throw new RuntimeException(
                'No fue posible extraer texto del PDF y OCR no esta disponible. ' .
                'Configura PDFTOPPM_PATH y TESSERACT_PATH o agrega los binarios al PATH.'
            );
        }

        $textoOcr = $this->normalizarTexto($this->extraerTextoPorOcr($rutaPdf));

        if (trim($textoNativo) === '') {
            return $textoOcr;
        }

        if (trim($textoOcr) === '') {
            return $textoNativo;
        }

        return $this->combinarTextoNativoYOcr($textoNativo, $textoOcr);
    }

    public function extraerCamposDesdeTexto(string $texto): array
    {
        return $this->documentoExtractor->extraerCamposDesdeTexto($texto);
    }

    public function obtenerTextoDesdeBaseDatos(array $fuente): string
    {
        $dsn = trim((string)($fuente['dsn'] ?? ''));
        $query = trim((string)($fuente['query'] ?? ''));

        if ($dsn === '') {
            throw new RuntimeException('Falta "dsn" para fuente de base de datos.');
        }
        if ($query === '') {
            throw new RuntimeException('Falta "query" para fuente de base de datos.');
        }

        $usuario = $fuente['usuario'] ?? $fuente['user'] ?? null;
        $password = $fuente['password'] ?? $fuente['pass'] ?? null;
        $columnaTexto = trim((string)($fuente['text_column'] ?? $fuente['columna_texto'] ?? ''));
        $maxRows = (int)($fuente['max_rows'] ?? 1000);
        if ($maxRows <= 0) {
            $maxRows = 1000;
        }
        if ($maxRows > 10000) {
            $maxRows = 10000;
        }

        $params = $fuente['params'] ?? [];
        if (is_string($params) && trim($params) !== '') {
            $decoded = json_decode($params, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('El valor de "params" debe ser un JSON objeto o array.');
            }
            $params = $decoded;
        }
        if (!is_array($params)) {
            throw new RuntimeException('El valor de "params" debe ser un array.');
        }

        try {
            $pdo = new PDO(
                $dsn,
                is_string($usuario) ? $usuario : null,
                is_string($password) ? $password : null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (Throwable $e) {
            throw new RuntimeException('Error al consultar base de datos: ' . $e->getMessage());
        }

        if (!is_array($rows) || count($rows) === 0) {
            throw new RuntimeException('La consulta a base de datos no devolvio filas.');
        }

        $partesTexto = [];
        $count = 0;
        foreach ($rows as $row) {
            if ($count >= $maxRows) {
                break;
            }
            $count++;

            if (!is_array($row)) {
                continue;
            }

            if ($columnaTexto !== '' && array_key_exists($columnaTexto, $row)) {
                $valor = $row[$columnaTexto];
                if ($valor !== null && !is_array($valor) && !is_object($valor)) {
                    $partesTexto[] = (string)$valor;
                }
                continue;
            }

            $linea = [];
            foreach ($row as $key => $valor) {
                if ($valor === null || is_array($valor) || is_object($valor)) {
                    continue;
                }
                $linea[] = (string)$key . ': ' . (string)$valor;
            }
            if (!empty($linea)) {
                $partesTexto[] = implode(' | ', $linea);
            }
        }

        $texto = $this->normalizarTexto(implode("\n", $partesTexto));
        if ($texto === '') {
            throw new RuntimeException('No se pudo construir texto util desde la base de datos.');
        }

        return $texto;
    }

    private function extraerTextoNativo(string $rutaPdf): string
    {
        $pdf = $this->parser->parseFile($rutaPdf);
        return (string) $pdf->getText();
    }

    private function extraerTextoPorOcr(string $rutaPdf): string
    {
        $directorioTemporal = $this->crearDirectorioTemporal();
        $textoCompleto = '';

        try {
            $imagenes = $this->convertirPdfAImagenes($rutaPdf, $directorioTemporal);

            if (empty($imagenes)) {
                throw new RuntimeException('No se generaron imagenes para OCR.');
            }

            $textoCompleto = $this->procesarImagenesConOcr($imagenes, $directorioTemporal);
        } finally {
            $this->eliminarDirectorioRecursivo($directorioTemporal);
        }

        return $textoCompleto;
    }

    private function convertirPdfAImagenes(string $rutaPdf, string $directorioTemporal): array
    {
        if ($this->pdftoppmPath === null) {
            throw new RuntimeException('pdftoppm no disponible para conversion a imagen.');
        }

        $prefijoSalida = $directorioTemporal . DIRECTORY_SEPARATOR . 'pagina';
        $comando = escapeshellarg($this->pdftoppmPath)
            . ' -png '
            . escapeshellarg($rutaPdf)
            . ' '
            . escapeshellarg($prefijoSalida)
            . ' 2>&1';

        $salida = [];
        $estado = 0;
        exec($comando, $salida, $estado);

        if ($estado !== 0) {
            throw new RuntimeException('Error al ejecutar pdftoppm: ' . implode("\n", $salida));
        }

        $imagenes = glob($prefijoSalida . '-*.png') ?: [];
        sort($imagenes);

        return $imagenes;
    }

    private function procesarImagenesConOcr(array $imagenes, string $directorioTemporal): string
    {
        if ($this->tesseractPath === null) {
            throw new RuntimeException('tesseract no disponible para OCR.');
        }

        $textoCompleto = '';

        foreach (array_values($imagenes) as $indice => $rutaImagen) {
            $baseSalida = $directorioTemporal . DIRECTORY_SEPARATOR . 'ocr_' . $indice;
            $comando = escapeshellarg($this->tesseractPath)
                . ' '
                . escapeshellarg($rutaImagen)
                . ' '
                . escapeshellarg($baseSalida)
                . ' -l spa 2>&1';

            $salida = [];
            $estado = 0;
            exec($comando, $salida, $estado);

            if ($estado !== 0) {
                throw new RuntimeException(
                    'Error al ejecutar tesseract sobre ' . basename($rutaImagen) . ': ' . implode("\n", $salida)
                );
            }

            $txtGenerado = $baseSalida . '.txt';
            if (!is_file($txtGenerado)) {
                throw new RuntimeException('Tesseract no genero salida TXT para ' . basename($rutaImagen));
            }

            $textoCompleto .= file_get_contents($txtGenerado) . "\n\n";
        }

        return $textoCompleto;
    }

    private function esTextoNativoSuficiente(string $texto): bool
    {
        $textoSinEspacios = preg_replace('/\s+/u', '', trim($texto)) ?? '';
        if ($textoSinEspacios === '') {
            return false;
        }

        $totalCaracteres = mb_strlen($textoSinEspacios);
        if ($totalCaracteres < 80) {
            return false;
        }

        $alnum = preg_match_all('/[\p{L}\p{N}]/u', $textoSinEspacios);
        if ($alnum === false) {
            return false;
        }

        $ratioAlnum = $alnum / max(1, $totalCaracteres);
        return $ratioAlnum >= 0.45;
    }

    private function combinarTextoNativoYOcr(string $textoNativo, string $textoOcr): string
    {
        $resultado = [];
        $lineasVistas = [];

        foreach ([$textoNativo, $textoOcr] as $bloqueTexto) {
            $lineas = preg_split('/\R/u', $bloqueTexto) ?: [];
            foreach ($lineas as $linea) {
                $lineaTrim = trim($linea);
                if ($lineaTrim === '') {
                    continue;
                }

                $llave = mb_strtolower(preg_replace('/\s+/u', ' ', $lineaTrim) ?? $lineaTrim);
                if (isset($lineasVistas[$llave])) {
                    continue;
                }

                $lineasVistas[$llave] = true;
                $resultado[] = $lineaTrim;
            }
        }

        return implode("\n", $resultado);
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = str_replace("\r", "\n", $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;
        return trim($texto);
    }

    private function ocrDisponible(): bool
    {
        return $this->pdftoppmPath !== null && $this->tesseractPath !== null;
    }

    private function resolverRutaBinario(string $envVar, array $rutasFallback, string $nombreComando): ?string
    {
        $rutaEnv = getenv($envVar);
        if (is_string($rutaEnv) && trim($rutaEnv) !== '') {
            $rutaEnv = trim($rutaEnv);
            if (is_file($rutaEnv)) {
                return $rutaEnv;
            }
        }

        foreach ($rutasFallback as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        if ($this->comandoExisteEnPath($nombreComando)) {
            return $nombreComando;
        }

        return null;
    }

    private function comandoExisteEnPath(string $comando): bool
    {
        $comandoBusqueda = stripos(PHP_OS_FAMILY, 'Windows') === 0
            ? 'where ' . escapeshellarg($comando) . ' 2>NUL'
            : 'command -v ' . escapeshellarg($comando) . ' 2>/dev/null';

        $salida = [];
        $estado = 0;
        exec($comandoBusqueda, $salida, $estado);

        return $estado === 0 && !empty($salida);
    }

    private function crearDirectorioTemporal(): string
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_extract_' . uniqid('', true);
        if (!mkdir($base, 0700, true) && !is_dir($base)) {
            throw new RuntimeException('No se pudo crear directorio temporal para OCR.');
        }
        return $base;
    }

    private function eliminarDirectorioRecursivo(string $ruta): void
    {
        if (!is_dir($ruta)) {
            return;
        }

        $items = scandir($ruta);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $rutaItem = $ruta . DIRECTORY_SEPARATOR . $item;
            if (is_dir($rutaItem)) {
                $this->eliminarDirectorioRecursivo($rutaItem);
            } elseif (is_file($rutaItem)) {
                @unlink($rutaItem);
            }
        }

        @rmdir($ruta);
    }
}

function detectarCamposNegocioFaltantes(array $resultado): array
{
    $camposNegocio = [
        'tipo_documento',
        'numero',
        'fecha',
        'total_bi',
        'iva',
        'total_a_pagar',
    ];

    $faltantes = [];
    foreach ($camposNegocio as $campo) {
        if (!array_key_exists($campo, $resultado)) {
            $faltantes[] = $campo;
            continue;
        }

        $valor = $resultado[$campo];
        if ($valor === null) {
            $faltantes[] = $campo;
            continue;
        }

        if (is_string($valor) && trim($valor) === '') {
            $faltantes[] = $campo;
        }
    }

    return $faltantes;
}

function usoCli(): string
{
    return implode(PHP_EOL, [
        'Uso:',
        '  php mcp-server/extract_pdf.php <ruta_pdf>',
        '  php mcp-server/extract_pdf.php --fuente=pdf --ruta=<ruta_pdf>',
        '  php mcp-server/extract_pdf.php --fuente=db --dsn=<dsn> --query=<sql> [--user=<u>] [--pass=<p>] [--text_column=<col>] [--params=<json>]',
        '  php mcp-server/extract_pdf.php --fuente=db --config=<archivo_json>',
    ]);
}

function parsearArgumentosCli(array $argv): array
{
    $args = array_slice($argv, 1);
    if (count($args) === 0) {
        throw new RuntimeException('No se proporciono una fuente de datos.');
    }

    if (count($args) === 1 && !str_starts_with((string)$args[0], '--')) {
        return [
            'tipo' => 'pdf',
            'ruta' => (string)$args[0],
        ];
    }

    $options = [];
    $posicionales = [];

    foreach ($args as $arg) {
        $arg = (string)$arg;
        if (str_starts_with($arg, '--')) {
            $raw = substr($arg, 2);
            if (str_contains($raw, '=')) {
                [$key, $value] = explode('=', $raw, 2);
                $options[strtolower(trim($key))] = $value;
            } else {
                $options[strtolower(trim($raw))] = true;
            }
        } else {
            $posicionales[] = $arg;
        }
    }

    $tipo = strtolower((string)($options['fuente'] ?? $options['source'] ?? ($posicionales[0] ?? 'pdf')));
    if ($tipo === 'pdf') {
        $ruta = (string)($options['ruta'] ?? $options['path'] ?? '');
        if ($ruta === '') {
            if (!empty($posicionales)) {
                if (strtolower((string)$posicionales[0]) === 'pdf' && isset($posicionales[1])) {
                    $ruta = (string)$posicionales[1];
                } elseif (!str_starts_with((string)$posicionales[0], '--')) {
                    $ruta = (string)$posicionales[0];
                }
            }
        }

        if ($ruta === '') {
            throw new RuntimeException('Falta la ruta del PDF.');
        }

        return [
            'tipo' => 'pdf',
            'ruta' => $ruta,
        ];
    }

    if ($tipo === 'db') {
        if (isset($options['config'])) {
            $rutaConfig = (string)$options['config'];
            if (!is_file($rutaConfig)) {
                throw new RuntimeException('El archivo de configuracion no existe: ' . $rutaConfig);
            }
            $raw = file_get_contents($rutaConfig);
            if ($raw === false) {
                throw new RuntimeException('No se pudo leer el archivo de configuracion: ' . $rutaConfig);
            }
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
            $cfg = json_decode($raw, true);
            if (!is_array($cfg)) {
                throw new RuntimeException('El archivo de configuracion no contiene JSON valido.');
            }
            $cfg['tipo'] = 'db';
            return $cfg;
        }

        $dsn = trim((string)($options['dsn'] ?? ''));
        $query = trim((string)($options['query'] ?? ''));
        if ($dsn === '' || $query === '') {
            throw new RuntimeException('Para fuente db debes proporcionar --dsn y --query.');
        }

        $fuente = [
            'tipo' => 'db',
            'dsn' => $dsn,
            'query' => $query,
        ];
        if (isset($options['user'])) {
            $fuente['user'] = (string)$options['user'];
        }
        if (isset($options['usuario'])) {
            $fuente['usuario'] = (string)$options['usuario'];
        }
        if (isset($options['pass'])) {
            $fuente['pass'] = (string)$options['pass'];
        }
        if (isset($options['password'])) {
            $fuente['password'] = (string)$options['password'];
        }
        if (isset($options['text_column'])) {
            $fuente['text_column'] = (string)$options['text_column'];
        }
        if (isset($options['columna_texto'])) {
            $fuente['columna_texto'] = (string)$options['columna_texto'];
        }
        if (isset($options['max_rows'])) {
            $fuente['max_rows'] = (int)$options['max_rows'];
        }
        if (isset($options['params'])) {
            $fuente['params'] = (string)$options['params'];
        }

        return $fuente;
    }

    throw new RuntimeException('Tipo de fuente no soportado: ' . $tipo);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $fuente = parsearArgumentosCli($argv);
        $procesador = new PdfProcessor();
        $resultado = $procesador->procesarFuente($fuente);
        $faltantes = detectarCamposNegocioFaltantes($resultado);
        if (!empty($faltantes)) {
            foreach ($faltantes as $campoFaltante) {
                fwrite(STDERR, 'Te falta este campo: ' . $campoFaltante . '. Debes introducirlo manualmente.' . PHP_EOL);
            }
        }
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit(0);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage(), 'uso' => usoCli()], JSON_UNESCAPED_UNICODE);
        exit(1);
    }
}
