<?php

class PdfNativeTextExtractor
{
    private $minChars;
    private $minWords;
    private $minAlnumRatio;

    public function __construct(int $minChars = 80, int $minWords = 12, float $minAlnumRatio = 0.30)
    {
        $this->minChars = $this->limitInt($minChars, 10, 10000);
        $this->minWords = $this->limitInt($minWords, 3, 5000);
        $this->minAlnumRatio = $this->limitFloat($minAlnumRatio, 0.05, 0.98);
    }

    public function extraerPorPagina(string $pdfPath): array
    {
        if (!is_file($pdfPath)) {
            throw new Exception('No existe el PDF para extraccion nativa: ' . $pdfPath);
        }

        $this->ensurePdfParserLoaded();

        $inicioTotal = microtime(true);

        $parser = new \Smalot\PdfParser\Parser();
        $document = $parser->parseFile($pdfPath);
        $pages = $document->getPages();

        $resultadoPaginas = [];

        foreach ($pages as $index => $page) {
            $inicioPagina = microtime(true);
            $error = null;
            $rawText = '';

            try {
                $rawText = (string)$page->getText();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }

            $texto = $this->normalizarTexto($rawText);
            $quality = $this->evaluarCalidad($texto);

            if ($error !== null) {
                $quality['is_usable'] = false;
                $quality['reason'] = 'error_extraccion';
            }

            $resultadoPaginas[] = [
                'page_number' => $index + 1,
                'text' => $texto,
                'is_usable' => (bool)$quality['is_usable'],
                'quality' => $quality,
                'time_ms' => $this->elapsedMs($inicioPagina),
                'error' => $error,
            ];
        }

        return [
            'page_count' => count($pages),
            'pages' => $resultadoPaginas,
            'time_ms' => $this->elapsedMs($inicioTotal),
        ];
    }

    private function ensurePdfParserLoaded(): void
    {
        if (class_exists('\\Smalot\\PdfParser\\Parser')) {
            return;
        }

        $autoload = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        if (!is_file($autoload)) {
            throw new Exception('No se encontro vendor/autoload.php para usar smalot/pdfparser.');
        }

        require_once $autoload;

        if (!class_exists('\\Smalot\\PdfParser\\Parser')) {
            throw new Exception('No se pudo cargar la clase Smalot\\PdfParser\\Parser.');
        }
    }

    private function evaluarCalidad(string $texto): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            return [
                'char_count' => 0,
                'word_count' => 0,
                'alnum_ratio' => 0.0,
                'has_control_chars' => false,
                'has_replacement_chars' => false,
                'is_usable' => false,
                'reason' => 'vacio',
            ];
        }

        $charCount = $this->strlenUtf8($texto);

        $alnumCount = preg_match_all('/[\p{L}\p{N}]/u', $texto, $dummyAlnum);
        if ($alnumCount === false) {
            $alnumCount = 0;
        }

        $wordCount = preg_match_all('/[\p{L}\p{N}]{2,}/u', $texto, $dummyWords);
        if ($wordCount === false) {
            $wordCount = 0;
        }

        $alnumRatio = $charCount > 0 ? ((float)$alnumCount / (float)$charCount) : 0.0;
        $hasControlChars = preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $texto) === 1;
        $hasReplacementChars = preg_match('/\x{FFFD}/u', $texto) === 1;

        $isUsable =
            $charCount >= $this->minChars
            && $wordCount >= $this->minWords
            && $alnumRatio >= $this->minAlnumRatio
            && !$hasControlChars
            && !$hasReplacementChars;

        return [
            'char_count' => $charCount,
            'word_count' => $wordCount,
            'alnum_ratio' => round($alnumRatio, 4),
            'has_control_chars' => $hasControlChars,
            'has_replacement_chars' => $hasReplacementChars,
            'is_usable' => $isUsable,
            'reason' => $this->buildReason(
                $isUsable,
                $charCount,
                $wordCount,
                $alnumRatio,
                $hasControlChars,
                $hasReplacementChars
            ),
        ];
    }

    private function buildReason(
        bool $isUsable,
        int $charCount,
        int $wordCount,
        float $alnumRatio,
        bool $hasControlChars,
        bool $hasReplacementChars
    ): string {
        if ($isUsable) {
            return 'ok';
        }

        if ($charCount < $this->minChars) {
            return 'pocos_caracteres';
        }

        if ($wordCount < $this->minWords) {
            return 'pocas_palabras';
        }

        if ($alnumRatio < $this->minAlnumRatio) {
            return 'baja_relacion_alnum';
        }

        if ($hasControlChars) {
            return 'caracteres_control';
        }

        if ($hasReplacementChars) {
            return 'caracteres_reemplazo';
        }

        return 'insuficiente';
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;
        return trim($texto);
    }

    private function strlenUtf8(string $texto): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($texto, 'UTF-8');
        }

        return strlen($texto);
    }

    private function elapsedMs(float $inicio): float
    {
        return round((microtime(true) - $inicio) * 1000, 2);
    }

    private function limitInt(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private function limitFloat(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
