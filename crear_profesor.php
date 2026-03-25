<?php
// 1. Cargamos las armas que hemos construido
include("acelerador_login/fronten/config.php"); 
include("operaciones_bbdd.php"); 
include("subida_ficheros.php");

// 2. Si el usuario ha pulsado el botón "Crear Profesor"...
if (isset($_POST["btn_crear"])) {

    // VALIDACIÓN PREVENTIVA: Comprobar que es un PDF ANTES de insertar nada
    $archivo = $_FILES["archivo_cv"];
    $extension = strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION));

    if ($archivo["error"] !== 0) {
        echo "<p style='color:red;'>❌ Error crítico: Debes adjuntar un archivo válido.</p>";
    } else if ($extension != "pdf") {
        echo "<p style='color:red;'>❌ Error crítico: El currículum TIENE que ser un PDF. Operación cancelada, el profesor NO ha sido creado.</p>";
    } else {
        
        // PASO A: Guardar los datos de texto (INSERT) solo si ya hemos comprobado que es PDF
        $insercionOk = insertar($conn, "tbl_profesor", [
            "nombre"    => $_POST["nombre"],
            "apellidos" => $_POST["apellidos"],
            "correo"    => $_POST["correo"],
            "password"  => $_POST["password"]
        ]);

        if ($insercionOk) {
            // PASO B: Obtenemos el ID del profesor que acabamos de insertar
            $id_nuevo_profesor = mysqli_insert_id($conn);

            // PASO C: Subir el PDF y rellenar ESA FILA CONCRETA (UPDATE)
            $resultado_pdf = subirPDF(
                $conn, 
                "archivo_cv",                    // Nombre del input file
                "tbl_profesor",                  // Tabla
                "curriculum_pdf",                // Nombre de la columna en la BD donde se guarda
                "id_profesor = $id_nuevo_profesor" // Modificar SOLO al profesor que acabamos de crear
            );

            // Mostrar resultados
            if ($resultado_pdf["ok"]) {
                echo "<p style='color:green;'>¡✅ Profesor y currículum guardados con éxito!</p>";
            } else {
                // Si por alguna razón rarísima falla al subir el PDF (ej: falta de espacio en disco), 
                // borramos al profesor para no dejar un perfil a medias sin CV
                eliminar($conn, "tbl_profesor", "id_profesor = $id_nuevo_profesor");
                echo "<p style='color:orange;'>⚠️ Falló la subida del PDF: " . $resultado_pdf["mensaje"] . ". Se ha cancelado la creación del profesor para evitar perfiles sin currículum.</p>";
            }
        } else {
            echo "<p style='color:red;'>❌ Error crítico: No se pudo crear el profesor. Falla la base de datos.</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Crear Profesor</title>
</head>
<body>
    <h2>Alta de nuevo Profesor</h2>
    
    <!-- IMPORTANTE: El enctype tiene estar para subir archivos -->
    <form action="crear_profesor.php" method="POST" enctype="multipart/form-data">
        
        <div>
            <label>Nombre:</label>
            <input type="text" name="nombre" required>
        </div>
        
        <br>

        <div>
            <label>Apellidos:</label>
            <input type="text" name="apellidos" required>
        </div>

        <br>
        
        <div>
            <label>Correo Electrónico:</label>
            <input type="email" name="correo" required>
        </div>

        <br>
        
        <div>
            <label>Contraseña:</label>
            <input type="password" name="password" required>
        </div>

        <br><br>

        <!-- CAMPO DEL PDF -->
        <div style="background: #f0f0f0; padding: 10px; border: 1px dashed #ccc;">
            <label>Sube tu currículum (obligatorio, solo PDF):</label><br>
            <input type="file" name="archivo_cv" accept=".pdf" required>
        </div>

        <br><br>
        
        <button type="submit" name="btn_crear">Crear Profesor y Subir CV</button>
    </form>
</body>
</html>
