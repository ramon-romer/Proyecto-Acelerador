<?php

class TextCleaner
{
    public function limpiar(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        // Normaliza saltos de página de pdftotext y elimina numeración huérfana pegada al salto.
        $texto = preg_replace('/\n\s*\d{1,3}\s*\n\f/u', "\n", $texto) ?? $texto;
        $texto = str_replace("\f", "\n", $texto);

        // Recompone cortes de palabra al final de línea: investi-\ngación -> investigación.
        $texto = preg_replace('/([[:alpha:]])-\n([[:alpha:]])/u', '$1$2', $texto) ?? $texto;

        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;

        return trim($texto);
    }
}
