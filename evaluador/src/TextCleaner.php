<?php

class TextCleaner
{
    public function limpiar(string $texto): string
    {
        $texto = str_replace("\r", "\n", $texto);
        $texto = preg_replace('/[ \t]+/', ' ', $texto);
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

        return trim($texto);
    }
}