<?php
include("conexion.php");

$termino = $_GET['termino'] ?? '';
$campo = $_GET['campo'] ?? 'nombre'; // Campo por defecto

// Lista blanca de campos permitidos (por seguridad)
$camposPermitidos = ['nombre', 'DNI', 'correo', 'facultad', 'departamento'];
if (!in_array($campo, $camposPermitidos)) {
    $campo = 'nombre';
}

// Consulta con LIKE para buscar coincidencias parciales
// Usamos sentencias preparadas para evitar inyecciones SQL
$query = "SELECT * FROM tbl_profesor WHERE $campo LIKE ?";
$stmt = mysqli_prepare($conn, $query);
$buscarTermino = "%$termino%";
mysqli_stmt_bind_param($stmt, "s", $buscarTermino);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($resultado) > 0) {
    while ($profesor = mysqli_fetch_assoc($resultado)) {
        // Aquí imprimes la estructura de tu tarjeta (.card)
        echo '
        <div class="card" style="width: 18rem; margin: 10px;">
            <div class="card-body">
                <h5 class="card-title">' . htmlspecialchars($profesor['nombre']) . '</h5>
                <p class="habilidadap"><b>' . ucfirst($campo) . ':</b> ' . htmlspecialchars($profesor[$campo]) . '</p>
                <p class="card-text">' . htmlspecialchars($profesor['correo']) . '</p>
            </div>
        </div>';
    }
} else {
    echo "<p style='color:white;'>No se encontraron resultados.</p>";
}
?>