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

// Consultar los datos correspondientes en tbl_profesor
$query_perfil = mysqli_query($conn, "SELECT nombre, apellidos, DNI as dni, ORCID as orcid FROM tbl_profesor WHERE correo = '$correo'");

if ($query_perfil && mysqli_num_rows($query_perfil) > 0) {
    $datos_perfil = mysqli_fetch_array($query_perfil);
    $nombre = htmlspecialchars($datos_perfil['nombre'] ?? '');
    $apellidos = htmlspecialchars($datos_perfil['apellidos'] ?? '');
    $dni = htmlspecialchars($datos_perfil['dni'] ?? '');
    $orcid = htmlspecialchars($datos_perfil['orcid'] ?? '');
} else {
    $nombre = 'No registrado';
    $apellidos = 'No registrado';
    $dni = 'No registrado';
    $orcid = 'No registrado';
}
?>

<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador</title>
  <link rel="icon" href="img/Image__4_-removebg-preview.png" type="image/x-icon" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
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
    <div class="formulario">
      <div class="text-center mb-4 w-100">
        <i class="bi bi-person-vcard text-white mb-2" style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Perfil de Profesor</h2>
        <hr class="w-100 border-light opacity-25 mt-3 mb-1">
      </div>

      <div class="lista-perfil w-100 px-lg-4">
          <ul class="list-unstyled d-flex flex-column gap-4 mb-0 text-start w-100 mx-auto">
            <li class="d-flex flex-column bg-light bg-opacity-10 p-3 rounded-4 border border-light border-opacity-25 shadow-sm">
                <span class="text-white-50 small mb-1 text-uppercase fw-bold tracking-wide"><i class="bi bi-person me-1"></i> Nombre</span>
                <span class="fs-5 fw-medium text-white ms-1"><?php echo empty($nombre) ? '-' : $nombre; ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 p-3 rounded-4 border border-light border-opacity-25 shadow-sm">
                <span class="text-white-50 small mb-1 text-uppercase fw-bold tracking-wide"><i class="bi bi-people me-1"></i> Apellidos</span>
                <span class="fs-5 fw-medium text-white ms-1"><?php echo empty($apellidos) ? '-' : $apellidos; ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 p-3 rounded-4 border border-light border-opacity-25 shadow-sm">
                <span class="text-white-50 small mb-1 text-uppercase fw-bold tracking-wide"><i class="bi bi-card-heading me-1"></i> DNI</span>
                <span class="fs-5 fw-medium text-white ms-1"><?php echo empty($dni) ? '-' : $dni; ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 p-3 rounded-4 border border-light border-opacity-25 shadow-sm">
                <span class="text-white-50 small mb-1 text-uppercase fw-bold tracking-wide"><i class="bi bi-globe me-1"></i> ORCID</span>
                <span class="fs-5 fw-medium text-white ms-1"><?php echo empty($orcid) ? '-' : $orcid; ?></span>
            </li>
          </ul>
      </div>

      <hr class="w-100 border-light my-4 opacity-25">
      
      <div class="d-flex flex-wrap justify-content-center gap-3 w-100 mb-2">
        <!-- 1. Botón para Mostrar todos los datos -->
        <button type="button" class="btn btn-primary px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all" style="background-color: rgba(20, 88, 204, 0.8); border: none;">
            <i class="bi bi-person-lines-fill"></i> Mostrar todos mis datos
        </button>
        <!-- 2. Botón para actualizar datos desde la base de datos -->
        <button type="button" class="btn btn-outline-light px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all">
            <i class="bi bi-arrow-clockwise"></i> Actualizar mis datos
        </button>
        <!-- 3. Botón para añadir publicaciones/trabajos -->
        <button type="button" class="btn btn-outline-info px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all text-white border-info hover-text-white">
            <i class="bi bi-file-earmark-plus"></i> Añadir trabajos/artículos
        </button>
        <a href="mis_grupos.php" class="btn btn-outline-success px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all text-decoration-none">
            <i class="bi bi-diagram-3-fill"></i> Ver mis grupos
        </a>
        <a href="../../acelerador_login/fronten/logout.php" class="btn btn-outline-danger px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all text-decoration-none">
            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="js/script.js"></script>
</body>

</html>

