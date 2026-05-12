<?php
declare(strict_types=1);

require __DIR__ . '/extract_pdf.php';

$host = '127.0.0.1';
$port = 5000;
$maxRequestBytes = leerMaxRequestBytes();
$jobsDir = leerJobsDir();

$server = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Error al iniciar el servidor: {$errstr} ({$errno})" . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Servidor MCP corriendo en http://{$host}:{$port}" . PHP_EOL);

while ($conn = @stream_socket_accept($server)) {
    try {
        $request = leerRequestHttp($conn, $maxRequestBytes);
        if ($request['error'] !== null) {
            responderJson($conn, $request['status_code'], ['error' => $request['error']]);
            fclose($conn);
            continue;
        }

        if ($request['method'] === 'POST' && $request['path'] === '/extract-pdf') {
            manejarExtractPdf($conn, $request['headers'], $request['body'], $jobsDir);
            fclose($conn);
            continue;
        }

        if ($request['method'] === 'POST' && $request['path'] === '/extract-data') {
            manejarExtractData($conn, $request['headers'], $request['body'], $jobsDir);
            fclose($conn);
            continue;
        }

        if ($request['method'] === 'GET' && preg_match('#^/jobs/([a-zA-Z0-9_-]+)$#', $request['path'], $m)) {
            manejarGetJob($conn, $jobsDir, $m[1]);
            fclose($conn);
            continue;
        }

        responderJson($conn, 404, ['error' => 'Ruta no encontrada']);
    } catch (Throwable $e) {
        responderJson($conn, 500, ['error' => 'Error interno: ' . $e->getMessage()]);
    }

    fclose($conn);
}

fclose($server);

function manejarExtractPdf($conn, array $headers, string $body, string $jobsDir): void
{
    $pdfBytes = extraerPdfDesdeRequest($headers, $body);
    if ($pdfBytes === null) {
        responderJson($conn, 400, ['error' => 'No se recibio un PDF valido. Usa multipart (campo file) o application/pdf.']);
        return;
    }

    $tmpPdfPath = crearArchivoTemporalPdf();
    try {
        if (file_put_contents($tmpPdfPath, $pdfBytes) === false) {
            throw new RuntimeException('No se pudo escribir el archivo PDF temporal.');
        }

        $processor = new PdfProcessor();
        $diagnostico = $processor->diagnosticarPdf($tmpPdfPath);

        if (($diagnostico['recommended_mode'] ?? 'sync') === 'async') {
            $jobId = encolarTrabajo($jobsDir, $tmpPdfPath, $diagnostico);
            responderJson($conn, 202, [
                'ok' => true,
                'queued' => true,
                'job_id' => $jobId,
                'status' => 'pending',
                'diagnostico' => $diagnostico,
                'hint' => 'Lanza: php mcp-server/worker_jobs.php --once  o en loop para procesar la cola.',
            ]);
            return;
        }

        $result = $processor->procesarPdf($tmpPdfPath);
        $faltantes = detectarCamposNegocioFaltantes($result);

        responderJson($conn, 200, [
            'ok' => true,
            'queued' => false,
            'diagnostico' => $diagnostico,
            'faltantes' => $faltantes,
            'resultado' => $result,
        ]);
    } finally {
        if (is_file($tmpPdfPath)) {
            @unlink($tmpPdfPath);
        }
    }
}

function manejarExtractData($conn, array $headers, string $body, string $jobsDir): void
{
    $payload = extraerJsonDesdeRequest($headers, $body);
    if (!is_array($payload)) {
        responderJson($conn, 400, ['error' => 'Body JSON invalido. Esperado: {"fuente": {...}}']);
        return;
    }

    $fuente = $payload['fuente'] ?? $payload;
    if (!is_array($fuente)) {
        responderJson($conn, 400, ['error' => 'Falta objeto fuente en el request.']);
        return;
    }

    $tipo = strtolower(trim((string)($fuente['tipo'] ?? '')));
    if ($tipo === '') {
        responderJson($conn, 400, ['error' => 'Falta fuente.tipo (pdf|db).']);
        return;
    }

    $processor = new PdfProcessor();

    if ($tipo === 'pdf') {
        $rutaPdf = (string)($fuente['ruta'] ?? '');
        if ($rutaPdf === '') {
            responderJson($conn, 400, ['error' => 'Para fuente pdf debes enviar fuente.ruta.']);
            return;
        }

        try {
            $diagnostico = $processor->diagnosticarPdf($rutaPdf);
            if (($diagnostico['recommended_mode'] ?? 'sync') === 'async') {
                $tmpPdfPath = crearArchivoTemporalPdf();
                if (!@copy($rutaPdf, $tmpPdfPath)) {
                    @unlink($tmpPdfPath);
                    throw new RuntimeException('No se pudo copiar el PDF al area temporal para encolar.');
                }
                $jobId = encolarTrabajo($jobsDir, $tmpPdfPath, $diagnostico);
                responderJson($conn, 202, [
                    'ok' => true,
                    'queued' => true,
                    'job_id' => $jobId,
                    'status' => 'pending',
                    'diagnostico' => $diagnostico,
                ]);
                return;
            }

            $result = $processor->procesarFuente($fuente);
            responderJson($conn, 200, [
                'ok' => true,
                'queued' => false,
                'diagnostico' => $diagnostico,
                'faltantes' => detectarCamposNegocioFaltantes($result),
                'resultado' => $result,
            ]);
            return;
        } catch (Throwable $e) {
            responderJson($conn, 500, ['error' => $e->getMessage()]);
            return;
        }
    }

    if ($tipo === 'db') {
        try {
            $result = $processor->procesarFuente($fuente);
            responderJson($conn, 200, [
                'ok' => true,
                'queued' => false,
                'faltantes' => detectarCamposNegocioFaltantes($result),
                'resultado' => $result,
            ]);
            return;
        } catch (Throwable $e) {
            responderJson($conn, 500, ['error' => $e->getMessage()]);
            return;
        }
    }

    responderJson($conn, 400, ['error' => 'Tipo de fuente no soportado: ' . $tipo]);
}

function manejarGetJob($conn, string $jobsDir, string $jobId): void
{
    $jobPath = $jobsDir . DIRECTORY_SEPARATOR . $jobId;
    $metaPath = $jobPath . DIRECTORY_SEPARATOR . 'meta.json';
    if (!is_file($metaPath)) {
        responderJson($conn, 404, ['error' => 'Job no encontrado: ' . $jobId]);
        return;
    }

    $meta = leerJson($metaPath);
    if (!is_array($meta)) {
        responderJson($conn, 500, ['error' => 'Meta del job invalida.']);
        return;
    }

    $resp = [
        'ok' => true,
        'job_id' => $jobId,
        'status' => $meta['status'] ?? 'unknown',
        'created_at' => $meta['created_at'] ?? null,
        'updated_at' => $meta['updated_at'] ?? null,
        'diagnostico' => $meta['diagnostico'] ?? null,
    ];

    $resultPath = $jobPath . DIRECTORY_SEPARATOR . 'result.json';
    if (is_file($resultPath)) {
        $resp['resultado'] = leerJson($resultPath);
        if (is_array($resp['resultado'])) {
            $resp['faltantes'] = detectarCamposNegocioFaltantes($resp['resultado']);
        }
    }

    $errorPath = $jobPath . DIRECTORY_SEPARATOR . 'error.json';
    if (is_file($errorPath)) {
        $resp['error'] = leerJson($errorPath);
    }

    responderJson($conn, 200, $resp);
}

function encolarTrabajo(string $jobsDir, string $tmpPdfPath, array $diagnostico): string
{
    asegurarDirectorio($jobsDir);
    $jobId = 'job_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $jobPath = $jobsDir . DIRECTORY_SEPARATOR . $jobId;
    asegurarDirectorio($jobPath);

    $inputPath = $jobPath . DIRECTORY_SEPARATOR . 'input.pdf';
    if (!@rename($tmpPdfPath, $inputPath)) {
        if (!@copy($tmpPdfPath, $inputPath)) {
            throw new RuntimeException('No se pudo mover el PDF a la cola de jobs.');
        }
    }

    $meta = [
        'job_id' => $jobId,
        'status' => 'pending',
        'created_at' => date(DATE_ATOM),
        'updated_at' => date(DATE_ATOM),
        'diagnostico' => $diagnostico,
    ];
    guardarJson($jobPath . DIRECTORY_SEPARATOR . 'meta.json', $meta);

    return $jobId;
}

function crearArchivoTemporalPdf(): string
{
    $tmpPdf = tempnam(sys_get_temp_dir(), 'mcp_pdf_');
    if ($tmpPdf === false) {
        throw new RuntimeException('No se pudo crear archivo temporal.');
    }

    $tmpPdfPath = $tmpPdf . '.pdf';
    @rename($tmpPdf, $tmpPdfPath);
    return $tmpPdfPath;
}

function leerRequestHttp($conn, int $maxBodyBytes): array
{
    $buffer = '';
    while (!str_contains($buffer, "\r\n\r\n")) {
        $chunk = fread($conn, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $buffer .= $chunk;
        if (strlen($buffer) > 1024 * 1024) {
            return ['error' => 'Cabecera HTTP demasiado grande.', 'status_code' => 400, 'method' => '', 'path' => '', 'headers' => [], 'body' => ''];
        }
    }

    $headerEnd = strpos($buffer, "\r\n\r\n");
    if ($headerEnd === false) {
        return ['error' => 'Request HTTP incompleto.', 'status_code' => 400, 'method' => '', 'path' => '', 'headers' => [], 'body' => ''];
    }

    $headerText = substr($buffer, 0, $headerEnd);
    $body = substr($buffer, $headerEnd + 4);
    $lines = explode("\r\n", $headerText);
    $requestLine = (string)array_shift($lines);

    if (!preg_match('/^([A-Z]+)\s+([^\s]+)\s+HTTP\/\d\.\d$/', $requestLine, $m)) {
        return ['error' => 'Request line invalida.', 'status_code' => 400, 'method' => '', 'path' => '', 'headers' => [], 'body' => ''];
    }

    $method = $m[1];
    $path = parse_url($m[2], PHP_URL_PATH) ?? '';
    $headers = [];
    foreach ($lines as $line) {
        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }
        $name = strtolower(trim(substr($line, 0, $pos)));
        $value = trim(substr($line, $pos + 1));
        $headers[$name] = $value;
    }

    $contentLength = (int)($headers['content-length'] ?? 0);
    if ($contentLength < 0) {
        $contentLength = 0;
    }

    if (strlen($body) > $maxBodyBytes) {
        return [
            'error' => 'PDF demasiado grande para este endpoint (maximo ' . $maxBodyBytes . ' bytes).',
            'status_code' => 413,
            'method' => '',
            'path' => '',
            'headers' => [],
            'body' => '',
        ];
    }

    if ($contentLength > $maxBodyBytes) {
        return [
            'error' => 'PDF demasiado grande para este endpoint (' . $contentLength . ' bytes; maximo ' . $maxBodyBytes . ').',
            'status_code' => 413,
            'method' => '',
            'path' => '',
            'headers' => [],
            'body' => '',
        ];
    }

    $missing = $contentLength - strlen($body);
    while ($missing > 0) {
        $chunk = fread($conn, min(8192, $missing));
        if ($chunk === false || $chunk === '') {
            break;
        }
        $body .= $chunk;
        if (strlen($body) > $maxBodyBytes) {
            return [
                'error' => 'PDF demasiado grande para este endpoint (maximo ' . $maxBodyBytes . ' bytes).',
                'status_code' => 413,
                'method' => '',
                'path' => '',
                'headers' => [],
                'body' => '',
            ];
        }
        $missing = $contentLength - strlen($body);
    }

    if ($contentLength > 0 && strlen($body) < $contentLength) {
        return ['error' => 'Body incompleto.', 'status_code' => 400, 'method' => '', 'path' => '', 'headers' => [], 'body' => ''];
    }

    return [
        'error' => null,
        'status_code' => 200,
        'method' => $method,
        'path' => $path,
        'headers' => $headers,
        'body' => $body,
    ];
}

function extraerPdfDesdeRequest(array $headers, string $body): ?string
{
    $contentType = strtolower((string)($headers['content-type'] ?? ''));

    if (str_starts_with($contentType, 'application/pdf')) {
        return $body !== '' ? $body : null;
    }

    if (!str_contains($contentType, 'multipart/form-data')) {
        return null;
    }

    if (!preg_match('/boundary="?([^";]+)"?/i', $contentType, $m)) {
        return null;
    }

    $boundary = $m[1];
    $parts = explode('--' . $boundary, $body);
    foreach ($parts as $part) {
        $part = ltrim($part, "\r\n");
        $part = rtrim($part, "\r\n");
        if ($part === '' || $part === '--') {
            continue;
        }

        $partHeaderEnd = strpos($part, "\r\n\r\n");
        if ($partHeaderEnd === false) {
            continue;
        }

        $partHeaders = substr($part, 0, $partHeaderEnd);
        $partBody = substr($part, $partHeaderEnd + 4);

        if (!preg_match('/name="file"/i', $partHeaders)) {
            continue;
        }

        return rtrim($partBody, "\r\n");
    }

    return null;
}

function extraerJsonDesdeRequest(array $headers, string $body): ?array
{
    $contentType = strtolower((string)($headers['content-type'] ?? ''));
    $looksLikeJson = false;
    $bodyNormalizado = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;
    $bodyNormalizado = ltrim($bodyNormalizado);
    if ($bodyNormalizado !== '' && ($bodyNormalizado[0] === '{' || $bodyNormalizado[0] === '[')) {
        $looksLikeJson = true;
    }

    if (!str_contains($contentType, 'application/json') && !$looksLikeJson) {
        return null;
    }

    $decoded = json_decode($bodyNormalizado, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

function responderJson($conn, int $statusCode, array $payload): void
{
    $statusText = match ($statusCode) {
        200 => 'OK',
        202 => 'Accepted',
        400 => 'Bad Request',
        404 => 'Not Found',
        413 => 'Payload Too Large',
        default => 'Internal Server Error',
    };

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = '{"error":"No se pudo serializar JSON"}';
    }

    $response = "HTTP/1.1 {$statusCode} {$statusText}\r\n"
        . "Content-Type: application/json; charset=utf-8\r\n"
        . "Content-Length: " . strlen($json) . "\r\n"
        . "Connection: close\r\n\r\n"
        . $json;

    fwrite($conn, $response);
}

function leerMaxRequestBytes(): int
{
    $raw = getenv('MAX_PDF_BYTES');
    if (!is_string($raw) || trim($raw) === '') {
        return 50 * 1024 * 1024;
    }

    $value = (int)trim($raw);
    if ($value < 1024 * 1024) {
        return 1024 * 1024;
    }
    if ($value > 1024 * 1024 * 1024) {
        return 1024 * 1024 * 1024;
    }

    return $value;
}

function leerJobsDir(): string
{
    $raw = getenv('MCP_JOBS_DIR');
    if (is_string($raw) && trim($raw) !== '') {
        return trim($raw);
    }

    return __DIR__ . DIRECTORY_SEPARATOR . 'resultados' . DIRECTORY_SEPARATOR . 'jobs';
}

function asegurarDirectorio(string $dir): void
{
    if (is_dir($dir)) {
        return;
    }

    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear directorio: ' . $dir);
    }
}

function guardarJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar JSON.');
    }
    if (file_put_contents($path, $json) === false) {
        throw new RuntimeException('No se pudo escribir archivo: ' . $path);
    }
}

function leerJson(string $path)
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    return json_decode($raw, true);
}
