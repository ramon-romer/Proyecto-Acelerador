<?php
session_start();
include('login.php');
error_reporting(0);

// Protección de sesión
if (!isset($_SESSION['nombredelusuario']) || $_SESSION['nombredelusuario'] == '') {
    header("Location: ../../acelerador_login/fronten/index.php");
    exit();
}

// Verificar que es ADMIN
$correo = $_SESSION['nombredelusuario'];
$query_perfil = mysqli_query($conn, "SELECT perfil FROM tbl_profesor WHERE correo = '$correo'");
if ($query_perfil && mysqli_num_rows($query_perfil) > 0) {
    $perfil = strtoupper(mysqli_fetch_assoc($query_perfil)['perfil']);
    if ($perfil != 'ADMIN' && $perfil != 'ADMINISTRADOR') {
        header("Location: ../../acelerador_login/fronten/index.php");
        exit();
    }
} else {
    header("Location: ../../acelerador_login/fronten/index.php");
    exit();
}
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador - Panel de Administrador</title>
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
    <div class="formulario-tabla text-center">

      <div class="mb-4 w-100">
        <i class="bi bi-shield-lock-fill text-white mb-2" style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Panel de Administrador</h2>
        <p class="text-white-50 mb-0">Gestiona usuarios, grupos y tutorías del sistema.</p>
        <hr class="w-100 border-light opacity-25 mt-3 mb-4">
      </div>

      <div class="d-flex flex-column flex-md-row justify-content-center gap-4 w-100 mb-4">

        <!-- Tarjeta Gestionar Usuarios -->
        <a href="admin_usuarios.php" class="text-decoration-none flex-fill" style="max-width: 350px;">
          <div class="p-4 rounded-4 text-center shadow-sm h-100" style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); transition: all 0.3s ease; cursor: pointer;"
               onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'; this.style.transform='translateY(-4px)';"
               onmouseout="this.style.backgroundColor='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)';">
            <i class="bi bi-people-fill text-info mb-3" style="font-size: 3rem;"></i>
            <h4 class="text-white fw-bold">Gestionar Usuarios</h4>
            <p class="text-white-50 mb-0 small">Buscar, crear, editar, asignar a grupos y eliminar usuarios del sistema.</p>
          </div>
        </a>

        <!-- Tarjeta Gestionar Tutorías / Grupos -->
        <a href="admin_grupos.php" class="text-decoration-none flex-fill" style="max-width: 350px;">
          <div class="p-4 rounded-4 text-center shadow-sm h-100" style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); transition: all 0.3s ease; cursor: pointer;"
               onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'; this.style.transform='translateY(-4px)';"
               onmouseout="this.style.backgroundColor='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)';">
            <i class="bi bi-diagram-3-fill text-success mb-3" style="font-size: 3rem;"></i>
            <h4 class="text-white fw-bold">Gestionar Grupos</h4>
            <p class="text-white-50 mb-0 small">Crear grupos, asignar tutores, modificar miembros y reasignar tutorías.</p>
          </div>
        </a>

      </div>

      <div class="d-flex justify-content-center w-100 mt-2">
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
        <p>Glorieta Ángel Herrera Oria, s/n,<br />41930 Bormujos,<br />Sevilla</p>
      </div>
      <div class="requerimientolegal">
        <div class="columna"><h4>La Empresa</h4><ul><li>Contacto</li><li>Preguntas Frecuentes (FAQ)</li><li>Centro de Ayuda</li><li>Soporte</li></ul></div>
        <div class="columna"><h4>Ayuda</h4><ul><li>Términos y Condiciones</li><li>Política de Cookies</li></ul></div>
        <div class="columna"><h4>Legal</h4><ul><li>Sobre nosotros</li><li>Política de Cookies</li><li>Blog</li></ul></div>
      </div>
      <div class="piepag"><p>&copy; CEU Lab. Todos los derechos reservados.</p></div>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
