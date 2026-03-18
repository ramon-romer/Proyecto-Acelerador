<?php

class AnecaExtractor
{
    public function extraer(string $texto): array
    {
        return [
            'tipo_documento' => $this->extraerTipoDocumento($texto),
            'numero' => $this->extraerNumero($texto),
            'fecha' => $this->extraerFecha($texto),
            'total_bi' => $this->extraerTotalBI($texto),
            'iva' => $this->extraerIVA($texto),
            'total_a_pagar' => $this->extraerTotalPagar($texto),
            'texto_preview' => mb_substr($texto, 0, 1200)
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
        if (preg_match('/N[ºo]\s*[:\-]?\s*([A-Z0-9\-\/]+)/iu', $texto, $m)) {
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
        if (preg_match('/Total\s+BI\s+([\d\.\,]+)\s*€/i', $texto, $m)) {
            return trim($m[1]) . ' €';
        }
        return null;
    }

    private function extraerIVA(string $texto): ?string
    {
        if (preg_match('/Iva\s+\d+%\s+([\d\.\,]+)\s*€/i', $texto, $m)) {
            return trim($m[1]) . ' €';
        }

        if (preg_match('/IVA\s+\d+%\s+([\d\.\,]+)\s*€/i', $texto, $m)) {
            return trim($m[1]) . ' €';
        }

        return null;
    }

    private function extraerTotalPagar(string $texto): ?string
    {
        if (preg_match('/Total\s+a\s+pagar\s+([\d\.\,]+)\s*€/i', $texto, $m)) {
            return trim($m[1]) . ' €';
        }
        return null;
    }
}