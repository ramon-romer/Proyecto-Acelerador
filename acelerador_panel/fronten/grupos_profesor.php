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

// Conseguir id_tutor y nombre
$query_tutor = mysqli_query($conn, "SELECT id_profesor, nombre, apellidos FROM tbl_profesor WHERE correo = '$correo'");
$id_tutor = 0;
$nombre_tutor = '';
if ($query_tutor && mysqli_num_rows($query_tutor) > 0) {
    $tutor_data = mysqli_fetch_assoc($query_tutor);
    $id_tutor = $tutor_data['id_profesor'];
    $nombre_tutor = $tutor_data['nombre'] . ' ' . $tutor_data['apellidos'];
}

// Conseguir los profesores de su grupo
$query_profesores = mysqli_query($conn, "
    SELECT p.ORCID, p.nombre, p.apellidos, p.departamento, g.nombre as grupo_nombre 
    FROM tbl_profesor p
    INNER JOIN tbl_grupo_profesor gp ON p.id_profesor = gp.id_profesor
    INNER JOIN tbl_grupo g ON gp.id_grupo = g.id_grupo
    WHERE g.id_tutor = '$id_tutor'
    ORDER BY g.nombre ASC, p.nombre ASC
");
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador - Mis Grupos</title>
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
    <div class="formulario-tabla">
      <div class="text-center mb-4 w-100">
        <i class="bi bi-people-fill text-white mb-2" style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Profesores de Mis Grupos</h2>
        <hr class="w-100 border-light opacity-25 mt-3 mb-4">
      </div>

      <div class="w-100 mb-4">
          <?php
          $current_grupo = '';
          if ($query_profesores && mysqli_num_rows($query_profesores) > 0) {
              while ($prof = mysqli_fetch_assoc($query_profesores)) {
                  // Si cambiamos de grupo, imprimimos la cabecera del nuevo grupo y su tabla
                  if ($current_grupo != $prof['grupo_nombre']) {
                      if ($current_grupo != '') {
                          // Cerrar la tabla del grupo anterior
                          echo "</tbody></table></div></div>";
                      }
                      $current_grupo = $prof['grupo_nombre'];
                      // Abrir contenedor para el nuevo grupo
                      echo "<div class='mb-5 w-100'>";
                      echo "<h5 class='text-white mb-3 text-start pb-2' style='border-bottom: 1px solid rgba(255,255,255,0.3);'>";
                      echo "<i class='bi bi-diagram-3-fill me-2'></i> Grupo: " . htmlspecialchars($current_grupo) . "<br>";
                      echo "<span class='text-white-50 fs-6 ms-4'><i class='bi bi-person-badge me-1'></i> Tutor: " . htmlspecialchars(empty(trim($nombre_tutor)) ? 'No especificado' : $nombre_tutor) . "</span>";
                      echo "</h5>";
                      
                      echo "<div class='table-responsive w-100' style='border-radius: 15px;'>";
                      echo "<table class='table tabla-glass mb-0'>";
                      echo "<thead>";
                      echo "<tr>";
                      echo "<th scope='col' class='border-top-0 border-end-0'>ORCID</th>";
                      echo "<th scope='col' class='border-top-0 border-end-0'>Nombre</th>";
                      echo "<th scope='col' class='border-top-0 border-end-0'>Departamento</th>";
                      echo "</tr>";
                      echo "</thead>";
                      echo "<tbody>";
                  }
                  
                  // Filas de profesores
                  echo "<tr>";
                  echo "<td class='border-end-0'>" . (empty($prof['ORCID']) ? '-' : htmlspecialchars($prof['ORCID'])) . "</td>";
                  echo "<td class='border-end-0'>" . htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']) . "</td>";
                  echo "<td class='border-end-0'>" . (empty($prof['departamento']) ? '-' : htmlspecialchars($prof['departamento'])) . "</td>";
                  echo "</tr>";
              }
              // Cerrar la última tabla creada
              if ($current_grupo != '') {
                  echo "</tbody></table></div></div>";
              }
          } else {
              echo "<div class='w-100 text-center text-white-50 p-4' style='background-color: rgba(255,255,255,0.05); border-radius: 15px;'>No tienes profesores asignados a tus grupos.</div>";
          }
          ?>
      </div>

      <div class="d-flex justify-content-center w-100 mt-2">
        <a href="panel_tutor.php" class="btn btn-volver px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all text-decoration-none">
            <i class="bi bi-arrow-left"></i> Volver a mi perfil
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
