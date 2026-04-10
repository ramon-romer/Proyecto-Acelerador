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

// Conseguir los profesores de su grupo — todos los campos excepto password
$query_profesores = mysqli_query($conn, "
    SELECT p.id_profesor, p.ORCID, p.nombre, p.apellidos, p.DNI, p.telefono, 
           p.perfil, p.facultad, p.departamento, p.correo, p.rama,
           g.nombre as grupo_nombre, g.id_grupo
    FROM tbl_grupo g
    LEFT JOIN tbl_grupo_profesor gp ON g.id_grupo = gp.id_grupo
    LEFT JOIN tbl_profesor p ON gp.id_profesor = p.id_profesor
    WHERE g.id_tutor = '$id_tutor'
    ORDER BY g.nombre ASC, p.nombre ASC
");

// Guardar resultados en un array para poder generar modales después
$profesores = [];
if ($query_profesores && mysqli_num_rows($query_profesores) > 0) {
  while ($row = mysqli_fetch_assoc($query_profesores)) {
    $profesores[] = $row;
  }
}
?>

<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador - Mis Grupos</title>
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css">
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
    <div class="formulario-tabla">
      <div class="text-center mb-4 w-100">
        <i class="bi bi-people-fill text-white mb-2"
          style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Mis Grupos de Profesores</h2>
        <hr class="w-100 border-light opacity-25 mt-3 mb-4">
      </div>

      <div class="w-100 mb-4">
        <?php
        $current_grupo = '';
        $current_id_grupo = 0;
        if (count($profesores) > 0) {
          foreach ($profesores as $prof) {
            // Si cambiamos de grupo, imprimimos la cabecera del nuevo grupo y su tabla
            if ($current_grupo != $prof['grupo_nombre']) {
              if ($current_grupo != '') {
                // Cerrar la tabla del grupo anterior
                echo "</tbody></table></div></div>";
              }
              $current_grupo = $prof['grupo_nombre'];
              $current_id_grupo = $prof['id_grupo'];
              // Abrir contenedor para el nuevo grupo
              echo "<div class='mb-5 w-100'>";
              echo "<div class='d-flex align-items-center justify-content-between w-100 mb-3 pb-2 flex-nowrap' style='border-bottom: 1px solid rgba(255,255,255,0.3); gap: 1rem;'>";
              echo "<div class='text-start flex-grow-1 overflow-hidden'>";
              echo "<h5 class='text-white mb-0 lh-base text-truncate'>";
              echo "<i class='bi bi-diagram-3-fill me-1'></i> Grupo: " . htmlspecialchars($current_grupo) . "<br>";
              echo "<span class='text-white-50 fs-6 ms-4'><i class='bi bi-person-badge me-1'></i> Tutor: " . htmlspecialchars(empty(trim($nombre_tutor)) ? 'No especificado' : $nombre_tutor) . "</span>";
              echo "</h5>";
              echo "</div>";
              echo "<div class='text-end flex-shrink-0'>";
              echo "<form method='POST' action='gestionar_grupo.php' class='m-0 p-0 text-md-end'>";
              echo "<input type='hidden' name='id_grupo_nav' value='" . intval($current_id_grupo) . "'>";
              echo "<button type='submit' class='btn btn-outline-warning btn-sm rounded-pill d-inline-flex align-items-center gap-1 text-nowrap'>";
              echo "<i class='bi bi-gear-fill'></i> Gestionar grupo";
              echo "</button>";
              echo "</form>";
              echo "</div>";
              echo "</div>";

              echo "<div class='table-responsive w-100' style='border-radius: 15px;'>";
              echo "<table class='table tabla-glass mb-0'>";
              echo "<thead>";
              echo "<tr>";
              echo "<th scope='col' class='border-top-0 border-end-0'>ORCID</th>";
              echo "<th scope='col' class='border-top-0 border-end-0'>Nombre</th>";
              echo "<th scope='col' class='border-top-0 border-end-0'>Departamento</th>";
              echo "<th scope='col' class='border-top-0 border-end-0 text-center'>Acciones</th>";
              echo "</tr>";
              echo "</thead>";
              echo "<tbody>";
            }

            if (!empty($prof['id_profesor'])) {
              $modalId = 'modalProf' . $prof['id_profesor'];

              // Filas de profesores
              echo "<tr>";
              echo "<td class='border-end-0'>" . (empty($prof['ORCID']) ? '-' : htmlspecialchars($prof['ORCID'])) . "</td>";
              echo "<td class='border-end-0'>" . htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']) . "</td>";
              echo "<td class='border-end-0'>" . (empty($prof['departamento']) ? '-' : htmlspecialchars($prof['departamento'])) . "</td>";
              echo "<td class='border-end-0 text-center'>";
              echo "<button class='btn btn-outline-info btn-sm rounded-pill d-inline-flex align-items-center gap-1' data-bs-toggle='modal' data-bs-target='#$modalId'>";
              echo "<i class='bi bi-eye-fill'></i> Ver datos";
              echo "</button>";
              echo "</td>";
              echo "</tr>";
            } else {
              echo "<tr><td colspan='4' class='text-center text-white-50 py-3 border-end-0 border-bottom-0' style='background-color: rgba(255,255,255,0.02);'>Aún no hay profesores asignados a este grupo.</td></tr>";
            }
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

      <div class="d-flex justify-content-center w-100 mt-4 gap-3">
        <a href="crear_grupo.php"
          class="btn btn-outline-success px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all text-decoration-none">
          <i class="bi bi-plus-circle-fill"></i> Crear nuevo grupo
        </a>
        <a href="panel_tutor.php"
          class="btn btn-volver px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all text-decoration-none">
          <i class="bi bi-arrow-left"></i> Volver a mi perfil
        </a>
      </div>
    </div>
  </main>

  <!-- Modales de detalle de profesor -->
  <?php foreach ($profesores as $prof):
    if (empty($prof['id_profesor']))
      continue;
    $modalId = 'modalProf' . $prof['id_profesor'];
    ?>
    <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $modalId; ?>Label"
      aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content"
          style="background-color: rgba(10, 50, 120, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; color: white;">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold" id="<?php echo $modalId; ?>Label">
              <i
                class="bi bi-person-vcard me-2"></i><?php echo htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']); ?>
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body pt-2">
            <hr class="border-light opacity-25 mt-1 mb-3">
            <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
              <?php
              $campos = [
                ['icon' => 'bi-globe', 'label' => 'ORCID', 'value' => $prof['ORCID']],
                ['icon' => 'bi-person', 'label' => 'Nombre', 'value' => $prof['nombre']],
                ['icon' => 'bi-people', 'label' => 'Apellidos', 'value' => $prof['apellidos']],
                ['icon' => 'bi-card-heading', 'label' => 'DNI', 'value' => $prof['DNI']],
                ['icon' => 'bi-telephone', 'label' => 'Teléfono', 'value' => $prof['telefono']],
                ['icon' => 'bi-shield-check', 'label' => 'Perfil', 'value' => $prof['perfil']],
                ['icon' => 'bi-building', 'label' => 'Facultad', 'value' => $prof['facultad']],
                ['icon' => 'bi-briefcase', 'label' => 'Departamento', 'value' => $prof['departamento']],
                ['icon' => 'bi-envelope', 'label' => 'Correo', 'value' => $prof['correo']],
                ['icon' => 'bi-bookmark', 'label' => 'Rama', 'value' => $prof['rama']],
              ];
              foreach ($campos as $campo):
                ?>
                <li
                  class="d-flex flex-column bg-light bg-opacity-10 p-2 px-3 rounded-3 border border-light border-opacity-10">
                  <span class="text-white-50 small text-uppercase fw-bold"><i
                      class="bi <?php echo $campo['icon']; ?> me-1"></i> <?php echo $campo['label']; ?></span>
                  <span
                    class="fs-6 fw-medium text-white"><?php echo empty($campo['value']) ? '-' : htmlspecialchars($campo['value']); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-4"
              data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

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
        <p>&copy; CEU Lab. Todos los derechos reservados.</p>
      </div>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>