<?php
session_start();
include('login.php');


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
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <style>
    .popover-body { white-space: pre-line; }
    
    /* FIX RESPONSIVE: Forzar visibilidad y evitar rotura de texto */
    @media (max-width: 768px) {
      .panel-wrapper .formulario-tabla {
        position: relative !important;
        left: 0 !important;
        width: 100% !important;
        height: auto !important;
        margin: 20px auto !important;
        display: flex !important;
        flex-direction: column !important;
        transform: none !important;
        z-index: 1 !important;
        visibility: visible !important;
        opacity: 1 !important;
        padding: 20px 10px !important; /* Reducir padding lateral */
      }
      .panel-wrapper {
        padding: 10px !important;
      }
      .form-label {
        white-space: nowrap !important;
        font-size: 0.9rem !important;
      }
      /* Ajustar el contenedor interno para que no asfixie el texto */
      .p-4.rounded-4 {
        padding: 20px 15px !important;
      }
    }
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
        <img src="../../acelerador_login/fronten/img/AcademyAccelerator_def.png" id="academy" alt="academy" />
      </div>
    </div>
  </header>

  <main>
    <div class="panel-wrapper">
      <div class="dashboard">
        <div class="formulario-tabla w-100" style="max-width: 600px; margin: 0 auto;">
          <div class="text-center mb-4 w-100">
            <i class="bi bi-diagram-3-fill text-white mb-2"
              style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
            <h2 class="text-white fw-bold">Crear Nuevo Grupo</h2>
            <h6 class="text-white-50">Introduce un nombre para el grupo de profesores tutorizados.</h6>
            <hr class="w-100 border-light opacity-25 mt-3 mb-4">
          </div>

          <?php if ($mensaje): ?>
            <script>
              document.addEventListener('DOMContentLoaded', () => {
                showNotification("<?php echo $mensaje; ?>", "<?php echo ($tipo_mensaje === 'success') ? 'success' : ($tipo_mensaje === 'danger' ? 'danger' : 'warning'); ?>");
              });
            </script>
          <?php endif; ?>

          <div class="w-100 p-4 rounded-4 shadow-sm"
            style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
            <form method="POST" action="crear_grupo.php" class="m-0 p-0 text-start w-100">
              <div class="mb-4">
                <label for="nombre_grupo" class="form-label text-light fw-medium mb-2"><i
                    class="bi bi-cursor-text me-1"></i> Nombre del Grupo</label>
                <input type="text" class="form-control" id="nombre_grupo" name="nombre_grupo"
                  placeholder="Ej: Grupo Avanzado 1" required
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
            <a href="grupos_profesor.php"
              class="btn btn-outline-light px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all text-decoration-none">
              <i class="bi bi-arrow-left"></i> Volver a mis grupos
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
  <link rel="stylesheet" href="css/notifications.css">
  <script src="js/notifications.js"></script>
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
  </style>

  <script>
    // Inicializar todos los popovers
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el, { html: false });
      });
    });
  </script>

  <?php include('chatbot.php'); ?>

</body>

</html>