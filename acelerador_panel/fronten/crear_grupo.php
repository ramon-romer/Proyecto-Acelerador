<?php
session_start();
include('login.php');
error_reporting(0);

// Si no hay sesión activa, redirigir al login
if (!isset($_SESSION['nombredelusuario']) || $_SESSION['nombredelusuario'] == '') {
    header("Location: ../../acelerador_login/fronten/index.php");
    exit();
}

$correo = $_SESSION['nombredelusuario'];

// Conseguir id_tutor
$query_tutor = mysqli_query($conn, "SELECT id_profesor FROM tbl_profesor WHERE correo = '$correo'");
$id_tutor = 0;
if ($query_tutor && mysqli_num_rows($query_tutor) > 0) {
    $id_tutor = mysqli_fetch_assoc($query_tutor)['id_profesor'];
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nombre_grupo'])) {
    $nombre_grupo = mysqli_real_escape_string($conn, trim($_POST['nombre_grupo']));

    if (!empty($nombre_grupo)) {
        // Verificar que no exista un grupo con el mismo nombre para este tutor
        $check_query = "SELECT id_grupo FROM tbl_grupo WHERE nombre = '$nombre_grupo' AND id_tutor = $id_tutor";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $mensaje = "Ya tienes un grupo con este nombre. Por favor, elige otro.";
            $tipo_mensaje = "warning";
        } else {
            // Insertar nuevo grupo
            $insert_query = "INSERT INTO tbl_grupo (nombre, id_tutor) VALUES ('$nombre_grupo', $id_tutor)";
            if (mysqli_query($conn, $insert_query)) {
                $mensaje = "El grupo <strong>" . htmlspecialchars($nombre_grupo) . "</strong> se ha creado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al crear el grupo: " . mysqli_error($conn);
                $tipo_mensaje = "danger";
            }
        }
    } else {
        $mensaje = "El nombre del grupo no puede estar vacío.";
        $tipo_mensaje = "danger";
    }
}
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador - Crear Grupo</title>
  <link rel="icon" href="img/Image__4_-removebg-preview.png" type="image/x-icon" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <header>
    <div class="imagen">
      <img src="img/Image__4_-removebg-preview.png" id="acele" />
    </div>
  </header>
  <main>
    <div class="formulario-tabla" style="max-width: 600px;">
      <div class="text-center mb-4 w-100">
        <i class="bi bi-diagram-3-fill text-white mb-2" style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Crear Nuevo Grupo</h2>
        <h6 class="text-white-50">Introduce un nombre para el grupo de profesores tutorizados.</h6>
        <hr class="w-100 border-light opacity-25 mt-3 mb-4">
      </div>

      <?php if ($mensaje): ?>
      <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show w-100 rounded-3 mb-4" role="alert" style="background-color: rgba(<?php echo $tipo_mensaje == 'success' ? '40,167,69' : ($tipo_mensaje == 'danger' ? '220,53,69' : '255,193,7'); ?>, 0.2); border: 1px solid rgba(255,255,255,0.2); color: white;">
        <i class="bi bi-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : ($tipo_mensaje == 'danger' ? 'x-circle' : 'exclamation-triangle'); ?>-fill me-2"></i>
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
      <?php endif; ?>

      <div class="w-100 p-4 rounded-4 shadow-sm" style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
        <form method="POST" action="crear_grupo.php" class="m-0 p-0 text-start w-100">
          <div class="mb-4">
            <label for="nombre_grupo" class="form-label text-light fw-medium mb-2"><i class="bi bi-cursor-text me-1"></i> Nombre del Grupo</label>
            <input type="text" class="form-control" id="nombre_grupo" name="nombre_grupo" placeholder="Ej: Grupo Avanzado 1" required 
                   style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 12px 15px; font-size: 1rem; box-shadow: none;">
          </div>
          <div class="d-grid gap-2 text-center">
            <button type="submit" class="btn btn-outline-success rounded-pill fw-bold py-2 shadow-sm fs-6">
              <i class="bi bi-plus-circle-fill me-1"></i> Confirmar Creación
            </button>
          </div>
        </form>
      </div>

      <div class="d-flex justify-content-center w-100 mt-4">
        <a href="grupos_profesor.php" class="btn btn-volver px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all text-decoration-none">
            <i class="bi bi-arrow-left"></i> Volver a mis grupos
        </a>
      </div>
    </div>
  </main>

  <footer>
    <div class="mipie" id="mipie">
      <div class="direccion">
        <img src="img/Image__4_-removebg-preview.png" />
        <p>
          Glorieta Ángel Herrera Oria, s/n,<br />
          41930 Bormujos,<br />
          Sevilla
        </p>
      </div>
      <div class="requerimientolegal">
        <div class="columna">
          <h4>La Empresa</h4>
          <ul>
            <li>Contacto</li>
            <li>Preguntas Frecuentes (FAQ)</li>
            <li>Centro de Ayuda</li>
            <li>Soporte</li>
          </ul>
        </div>
        <div class="columna">
          <h4>Ayuda</h4>
          <ul>
            <li>Términos y Condiciones</li>
            <li>Política de Cookies</li>
          </ul>
        </div>
        <div class="columna">
          <h4>Legal</h4>
          <ul>
            <li>Sobre nosotros</li>
            <li>Política de Cookies</li>
            <li>Blog</li>
          </ul>
        </div>
      </div>
      <div class="piepag">
        <p>&copy; CEU Lab. Todos los derechos reservados.</p>
      </div>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
