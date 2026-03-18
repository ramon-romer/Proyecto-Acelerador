<?php

require_once __DIR__ . '/../src/Pipeline.php';

// Ruta de la carpeta donde se guardarán los PDFs
$carpetaDestino = __DIR__ . '/../pdfs/';
 
// 1. Comprobar que se ha enviado el archivo  **no hace falta el campo  de subir archivo ya te alerta**
 
/*if (!isset($_FILES['pdf'])) {
    die("No se ha enviado ningún archivo.");
}
*/
 
// Comprobar si hay errores en la subida
if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    die("Error al subir el archivo.");
}
 
// Obtener datos del archivo
$nombreOriginal = $_FILES['pdf']['name'];
$rutaTemporal = $_FILES['pdf']['tmp_name'];
$tamano = $_FILES['pdf']['size'];
 
 
// Validar tipo MIME real (seguridad)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$tipoMime = finfo_file($finfo, $rutaTemporal);
finfo_close($finfo);
 
if ($tipoMime !== 'application/pdf') {
    die("El archivo no es un PDF válido.");
}
 
// Guardar en la carpeta de pdfs
if (!is_dir($carpetaDestino)) {
    mkdir($carpetaDestino, 0777, true);
}
 
// Generar un nombre seguro (evita sobrescrituras)
//genera un nombre único para evitar sobreescrituras
$nombreNuevo = uniqid('pdf_', true) . '.pdf';
 
// Ruta final
$rutaFinal = $carpetaDestino . $nombreNuevo;
 
//  Mover el archivo a la carpeta pdfs
if (move_uploaded_file($rutaTemporal, $rutaFinal)) {
    echo "✅ PDF subido correctamente.<br>";
    echo "📁 Guardado en: pdfs/" . $nombreNuevo;
} else {
    echo "❌ Error al guardar el archivo.";
}

// Lanzar pipeline OCR
try {
    $pipeline = new Pipeline();
    $resultado = $pipeline->procesar($rutaFinal);

    echo "<h2>Resultado del procesamiento</h2>";
    echo "<pre>";
    print_r($resultado);
    echo "</pre>";

} catch (Exception $e) {
    echo "<h2>Error en el procesamiento OCR</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}