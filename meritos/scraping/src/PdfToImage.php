<?php

class PdfToImage
{
    public function convertir(string $pdfPath, string $outputPrefix): array
    {
        $pdftoppm = '"C:\\poppler\\Library\\bin\\pdftoppm.exe"';

        $cmd = $pdftoppm . ' -png ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($outputPrefix);
        exec($cmd, $output, $status);

        if ($status !== 0) {
            throw new Exception("Error al ejecutar pdftoppm");
        }

        $imagenes = glob($outputPrefix . "-*.png");

        sort($imagenes);

        return $imagenes;
    }
}