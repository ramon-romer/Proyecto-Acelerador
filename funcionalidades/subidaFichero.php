<?php
    /**
     * SUBIR PDF - Verifica que el archivo sea PDF, lo sube al servidor y ACTUALIZA un registro en la base de datos
     * @param $conn         - Conexión a la base de datos (mysqli)
     * @param $inputName    - Nombre del input type="file" del formulario (ej: "fileToUpload")
     * @param $tabla        - Tabla a actualizar (ej: "tbl_profesor")
     * @param $columnaRuta  - Nombre de la columna donde se guardará la ruta del archivo (ej: "ruta_cv")
     * @param $where        - Condición para saber qué registro actualizar (ej: "id_profesor = 5")
     * @param $carpeta      - Carpeta donde se guardará el archivo (por defecto "uploads/")
     * @param $maxSize      - Tamaño máximo en bytes (por defecto 5MB)
     * @return array        - ["ok" => true/false, "mensaje" => "..."]
     */
    function subirPDF($conn, $inputName, $tabla, $columnaRuta, $where, $carpeta = "uploads/", $maxSize = 5000000) {
        // 0. Asegurarnos siempre de que la carpeta termina en '/'
        $carpeta = rtrim($carpeta, '/') . '/';

        // Crear la carpeta si no existe
        if (!file_exists($carpeta)) {
            mkdir($carpeta, 0777, true);
        }

        // Comprobar que se ha enviado un archivo
        if (!isset($_FILES[$inputName]) || $_FILES[$inputName]["error"] !== 0) {
            return ["ok" => false, "mensaje" => "No se ha enviado ningún archivo."];
        }

        $nombreOriginal = basename($_FILES[$inputName]["name"]);
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        
        // Sanear el nombre base quitando espacios y caracteres extraños (para que los enlaces luego funcionen bien en la web)
        $nombreBase = pathinfo($nombreOriginal, PATHINFO_FILENAME);
        $nombreBase = preg_replace('/[^a-zA-Z0-9_-]/', '', $nombreBase);
        
        // Añadimos la fecha y hora actual (ej: 20-03-2026_11-45-30) para que no se sobreescriban
        $timestamp = date('d-m-Y_H-i-s');
        $nombreArchivo = $nombreBase . "_" . $timestamp . "." . $extension;
        $rutaDestino = $carpeta . $nombreArchivo;

        // Verificar que sea PDF
        if ($extension != "pdf") {
            return ["ok" => false, "mensaje" => "Solo se permiten archivos PDF."];
        }

        // Verificar tamaño
        if ($_FILES[$inputName]["size"] > $maxSize) {
            return ["ok" => false, "mensaje" => "El archivo es demasiado grande (máx: " . ($maxSize / 1000000) . "MB)."];
        }

        // Verificar si ya existe un archivo con el mismo nombre
        if (file_exists($rutaDestino)) {
            return ["ok" => false, "mensaje" => "Ya existe un archivo con ese nombre."];
        }

        // Subir el archivo al servidor
        if (!move_uploaded_file($_FILES[$inputName]["tmp_name"], $rutaDestino)) {
            return ["ok" => false, "mensaje" => "Error al subir el archivo al servidor."];
        }

        // --- HACER EL UPDATE EN LA BASE DE DATOS ---
        // Escapamos la ruta por seguridad
        $rutaSegura = mysqli_real_escape_string($conn, $rutaDestino);
        
        // Protegemos la tabla y la columna con backticks
        $sql = "UPDATE `$tabla` SET `$columnaRuta` = '$rutaSegura' WHERE $where";
        $resultado = mysqli_query($conn, $sql);

        // Comprobamos si la consulta no dio error Y además modificó realmente algún registro
        if ($resultado && mysqli_affected_rows($conn) > 0) {
            return ["ok" => true, "mensaje" => "PDF subido y registro actualizado en la BD."];
        } else {
            // Si falla la sintaxis SQL o si NO EXISITÍA el registro (affected_rows == 0), borramos el archivo huérfano
            unlink($rutaDestino);
            $error_msj = mysqli_error($conn) ? "Error SQL: " . mysqli_error($conn) : "No se encontró el registro para actualizar (el WHERE no coincide con ninguno).";
            return ["ok" => false, "mensaje" => "El PDF se canceló porque falló la vinculación en BD: " . $error_msj];
        }
    }
?>