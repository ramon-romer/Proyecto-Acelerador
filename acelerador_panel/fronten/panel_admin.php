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
  <title>Acelerador</title>
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <style>
    .popover-body { white-space: pre-line; }
  </style>
</head>

<body>
  <header>
    <div class="contenedorimg">
      <div class="imagen">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU Universidad Fernando III"
          style="height:50px; width:auto;" id="#acele" />
      </div>
      <div class="imagen">
        <img src="img/AcademyAccelerator_def.png" id="academy" alt="academy" />
      </div>
    </div>
  </header>

  <main>
    <div class="panel-wrapper">
      <div class="dashboard">
        <div class="formulario-tabla text-center w-100" style="max-width: 950px; margin: 0 auto;">

          <div class="mb-4 w-100">
            <i class="bi bi-shield-lock-fill text-white mb-2"
              style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
            <h2 class="text-white fw-bold">Panel de Administrador</h2>
            <p class="text-white-50 mb-0">Gestiona usuarios, grupos y tutorías del sistema.</p>
            <hr class="w-100 border-light opacity-25 mt-3 mb-4">
          </div>

          <div class="d-flex flex-column flex-md-row justify-content-center gap-4 w-100 mb-4">

            <!-- Tarjeta Gestionar Usuarios -->
            <a href="admin_usuarios.php" class="text-decoration-none flex-fill" style="max-width: 350px;">
              <div class="p-4 rounded-4 text-center shadow-sm h-100"
                style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); transition: all 0.3s ease; cursor: pointer;"
                onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'; this.style.transform='translateY(-4px)';"
                onmouseout="this.style.backgroundColor='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)';">
                <i class="bi bi-people-fill text-info mb-3" style="font-size: 3rem;"></i>
                <h4 class="text-white fw-bold">Gestionar Usuarios</h4>
                <p class="text-white-50 mb-0 small">Buscar, crear, edita, asignar a grupos y eliminar usuarios del sistema.
                </p>
              </div>
            </a>

            <!-- Tarjeta Gestionar Tutorías / Grupos -->
            <a href="admin_grupos.php" class="text-decoration-none flex-fill" style="max-width: 350px;">
              <div class="p-4 rounded-4 text-center shadow-sm h-100"
                style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); transition: all 0.3s ease; cursor: pointer;"
                onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'; this.style.transform='translateY(-4px)';"
                onmouseout="this.style.backgroundColor='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)';">
                <i class="bi bi-diagram-3-fill text-success mb-3" style="font-size: 3rem;"></i>
                <h4 class="text-white fw-bold">Gestionar Grupos</h4>
                <p class="text-white-50 mb-0 small">Crear grupos, asignar tutores, modificar miembros y reasignar tutorías.
                </p>
              </div>
            </a>

            <!-- Tarjeta Usuarios Eliminados -->
            <a href="admin_eliminados.php" class="text-decoration-none flex-fill" style="max-width: 350px;">
              <div class="p-4 rounded-4 text-center shadow-sm h-100"
                style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); transition: all 0.3s ease; cursor: pointer;"
                onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'; this.style.transform='translateY(-4px)';"
                onmouseout="this.style.backgroundColor='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)';">
                <i class="bi bi-person-x-fill text-danger mb-3" style="font-size: 3rem;"></i>
                <h4 class="text-white fw-bold">Usuarios Eliminados</h4>
                <p class="text-white-50 mb-0 small">Ver el archivo de usuarios eliminados y restaurar cuentas si es necesario.
                </p>
              </div>
            </a>

          </div>

          <div class="d-flex justify-content-center w-100 mt-2">
            <a href="../../acelerador_login/fronten/logout.php"
              class="btn btn-outline-danger px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all text-decoration-none">
              <i class="bi bi-box-arrow-right"></i> Cerrar sesión
            </a>
          </div>

        </div>
      </div>
    </div>
  </main>

  <footer>
    <div class="mipie" id="mipie">
      <div class="direccion">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU Universidad Fernando III"
          style="height:50px; width:auto;" id="#acele" />
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
        <p>&copy; UF3. Todos los derechos reservados.</p>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="js/script.js"></script>

  <style>
    /* Scrollbar personalizada minimalista (Fina línea) */
    .custom-scrollbar::-webkit-scrollbar {
      width: 3px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.05); 
      border-radius: 10px;
      margin: 10px 0;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.5); 
      border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.8); 
    }
    .custom-scrollbar {
      scrollbar-width: thin;
      scrollbar-color: rgba(255, 255, 255, 0.5) rgba(255, 255, 255, 0.05);
    }

    /* Botón de info flotante en tarjetas */
    .info-popover-btn {
      position: absolute;
      top: 10px;
      right: 12px;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      color: rgba(255,255,255,0.45);
      padding: 0;
      line-height: 1;
      transition: color .2s ease;
    }
    .info-popover-btn:hover, .info-popover-btn:focus {
      color: rgba(255,255,255,0.9);
      outline: none;
    }
  </style>
  <link rel="stylesheet" href="css/notifications.css">
  <script src="js/notifications.js"></script>

  <script>
    // Inicializar todos los popovers
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el, { html: false });
      });

      // Notificación de validación correcta
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('validated')) {
        showNotification('Validación correcta', 'success');
        // Limpiar URL
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    });
  </script>

  <?php include('chatbot.php'); ?>

</body>

</html>