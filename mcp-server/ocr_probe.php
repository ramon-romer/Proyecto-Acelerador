<?php
declare(strict_types=1);

require __DIR__ . '/extract_pdf.php';

if (PHP_SAPI !== 'cli') {
    echo json_encode(['error' => 'Solo CLI'], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$mode = $argv[1] ?? null;
$pdfPath = $argv[2] ?? null;

if ($mode === null || $pdfPath === null) {
    echo json_encode(
        ['error' => 'Uso: php mcp-server/ocr_probe.php <full|forced_ocr> <ruta_pdf>'],
        JSON_UNESCAPED_UNICODE
    );
    exit(1);
}

try {
    $processor = new PdfProcessor();

    if ($mode === 'full') {
        $data = $processor->procesarPdf($pdfPath);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    if ($mode === 'forced_ocr') {
        $ref = new ReflectionClass($processor);
        $method = $ref->getMethod('extraerTextoPorOcr');
        $method->setAccessible(true);
        $ocrText = (string) $method->invoke($processor, $pdfPath);
        $ocrText = trim(str_replace("\r", "\n", $ocrText));

        echo json_encode(
            [
                'ocr_len' => mb_strlen($ocrText),
                'ocr_preview' => mb_substr($ocrText, 0, 240),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit(0);
    }

    echo json_encode(['error' => 'Modo no soportado: ' . $mode], JSON_UNESCAPED_UNICODE);
    exit(1);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
}
