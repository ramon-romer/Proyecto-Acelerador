<?php
session_start();
include('login.php'); // db connection
error_reporting(0);

// Redirigir si no hay sesión
if (!isset($_SESSION['nombredelusuario']) || $_SESSION['nombredelusuario'] == '') {
  header("Location: ../../acelerador_login/fronten/index.php");
  exit();
}

$correo = $_SESSION['nombredelusuario'];

// Obtener id_profesor
$query_prof = mysqli_query($conn, "SELECT id_profesor, nombre, apellidos FROM tbl_profesor WHERE correo = '$correo'");
$id_profesor = 0;
$nombre_profesor = '';

if ($query_prof && mysqli_num_rows($query_prof) > 0) {
  $prof_data = mysqli_fetch_assoc($query_prof);
  $id_profesor = $prof_data['id_profesor'];
  $nombre_profesor = $prof_data['nombre'] . ' ' . $prof_data['apellidos'];
}

// Obtener los grupos a los que pertenece este profesor, y sus respectivos tutores
$query_grupos = mysqli_query($conn, "
    SELECT g.nombre AS grupo_nombre, t.nombre AS tutor_nombre, t.apellidos AS tutor_apellidos, t.correo AS tutor_correo
    FROM tbl_grupo_profesor gp
    INNER JOIN tbl_grupo g ON gp.id_grupo = g.id_grupo
    INNER JOIN tbl_profesor t ON g.id_tutor = t.id_profesor
    WHERE gp.id_profesor = '$id_profesor'
    ORDER BY g.nombre ASC
");

$mis_grupos = [];
if ($query_grupos && mysqli_num_rows($query_grupos) > 0) {
  while ($row = mysqli_fetch_assoc($query_grupos)) {
    $mis_grupos[] = $row;
  }
}
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
    <div class="formulario text-center" style="max-width: 800px; width: 90%; margin: 50px auto;">

      <div class="mb-4">
        <i class="bi bi-diagram-3 text-white mb-2"
          style="font-size: 3.5rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Mis Grupos Asignados</h2>
        <p class="text-white-50">Gestiona y revisa los grupos en los que participas como profesor.</p>
        <hr class="w-100 border-light opacity-25 mt-3 mb-4">
      </div>

      <?php if (count($mis_grupos) > 0): ?>
        <div class="table-responsive w-100 mb-4 p-3 p-md-4"
          style="border-radius: 15px; background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15);">
          <table class="table tabla-glass mb-0 text-start">
            <thead>
              <tr>
                <th scope="col" class="border-top-0 border-end-0 border-bottom text-white px-4 py-3"><i
                    class="bi bi-tag-fill me-1"></i> Nombre del Grupo</th>
                <th scope="col" class="border-top-0 border-end-0 border-bottom text-white py-3"><i
                    class="bi bi-person-badge-fill me-1"></i> Tutor Asignado</th>
                <th scope="col" class="border-top-0 border-end-0 border-bottom text-white px-4 py-3"><i
                    class="bi bi-envelope-fill me-1"></i> Contacto</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mis_grupos as $grupo): ?>
                <tr>
                  <td class="border-end-0 fw-bold border-bottom-0 text-white px-4 py-3">
                    <?php echo htmlspecialchars($grupo['grupo_nombre']); ?>
                  </td>
                  <td class="border-end-0 border-bottom-0 text-white py-3">
                    <?php echo htmlspecialchars($grupo['tutor_nombre'] . ' ' . $grupo['tutor_apellidos']); ?>
                  </td>
                  <td class="border-end-0 border-bottom-0 text-white-50 px-4 py-3">
                    <a href="mailto:<?php echo htmlspecialchars($grupo['tutor_correo']); ?>"
                      class="text-white text-decoration-none">
                      <?php echo htmlspecialchars($grupo['tutor_correo']); ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="w-100 text-center p-5 rounded-4 mb-4"
          style="background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15);">
          <i class="bi bi-info-circle text-white-50 fs-1 mb-3 d-block"></i>
          <h5 class="text-white">Aún no estás asignado a ningún grupo</h5>
          <p class="text-white-50 mb-0">Cuando un tutor te añada a su grupo de investigación, aparecerá en esta lista.</p>
        </div>
      <?php endif; ?>

      <div class="d-flex justify-content-center w-100 mt-2 mb-5">
        <a href="panel_profesor.php"
          class="btn btn-volver px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all text-decoration-none">
          <i class="bi bi-arrow-left"></i> Volver a mi perfil
        </a>
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
        <p>&copy; CEU Lab. Todos los derechos reservados.</p>
      </div>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>