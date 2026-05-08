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

// Obtener id_grupo: desde POST (navegación) o desde sesión (recarga/operación)
if (isset($_POST['id_grupo_nav'])) {
    $id_grupo = intval($_POST['id_grupo_nav']);
    $_SESSION['id_grupo_gestion'] = $id_grupo;
} elseif (isset($_SESSION['id_grupo_gestion'])) {
    $id_grupo = intval($_SESSION['id_grupo_gestion']);
} else {
    header("Location: grupos_profesor.php");
    exit();
}

// Validar que el grupo pertenezca a este tutor
$query_grupo = mysqli_query($conn, "SELECT nombre FROM tbl_grupo WHERE id_grupo = $id_grupo AND id_tutor = $id_tutor");
if (!$query_grupo || mysqli_num_rows($query_grupo) == 0) {
    unset($_SESSION['id_grupo_gestion']);
    header("Location: grupos_profesor.php");
    exit();
}
$nombre_grupo = mysqli_fetch_assoc($query_grupo)['nombre'];

// ---- PROCESAMIENTO POST ----
$mensaje = '';
$tipo_mensaje = '';

if (isset($_POST['accion'])) {

    // AÑADIR profesor por ORCID
    if ($_POST['accion'] == 'añadir' && !empty($_POST['orcid'])) {
        $orcid = mysqli_real_escape_string($conn, trim($_POST['orcid']));
        
        // Buscar profesor por ORCID
        $q = mysqli_query($conn, "SELECT id_profesor, nombre, apellidos FROM tbl_profesor WHERE ORCID = '$orcid'");
        if ($q && mysqli_num_rows($q) > 0) {
            $prof = mysqli_fetch_assoc($q);
            $id_prof = $prof['id_profesor'];
            
            // Verificar que no esté ya en el grupo
            $ya_existe = mysqli_query($conn, "SELECT id FROM tbl_grupo_profesor WHERE id_grupo = $id_grupo AND id_profesor = $id_prof");
            if ($ya_existe && mysqli_num_rows($ya_existe) > 0) {
                $mensaje = 'El profesor <strong>' . htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']) . '</strong> ya está en este grupo.';
                $tipo_mensaje = 'warning';
            } else {
                mysqli_query($conn, "INSERT INTO tbl_grupo_profesor (id_grupo, id_profesor) VALUES ($id_grupo, $id_prof)");
                $mensaje = 'Profesor <strong>' . htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']) . '</strong> añadido correctamente.';
                $tipo_mensaje = 'success';
            }
        } else {
            $mensaje = 'No se encontró ningún profesor con ORCID <strong>' . htmlspecialchars($orcid) . '</strong>.';
            $tipo_mensaje = 'danger';
        }
    }

    // ELIMINAR profesor del grupo
    if ($_POST['accion'] == 'eliminar' && !empty($_POST['id_profesor'])) {
        $id_prof_eliminar = intval($_POST['id_profesor']);
        mysqli_query($conn, "DELETE FROM tbl_grupo_profesor WHERE id_grupo = $id_grupo AND id_profesor = $id_prof_eliminar");
        $mensaje = 'Profesor eliminado del grupo correctamente.';
        $tipo_mensaje = 'success';
    }
}

// Consultar profesores actuales del grupo
$query_profes = mysqli_query($conn, "
    SELECT p.id_profesor, p.ORCID, p.nombre, p.apellidos, p.departamento, p.correo
    FROM tbl_profesor p
    INNER JOIN tbl_grupo_profesor gp ON p.id_profesor = gp.id_profesor
    WHERE gp.id_grupo = $id_grupo
    ORDER BY p.nombre ASC
");
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador - Gestionar Grupo</title>
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
        <i class="bi bi-gear-fill text-white mb-2" style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Gestionar Grupo</h2>
        <h4 class="text-white-50"><?php echo htmlspecialchars($nombre_grupo); ?></h4>
        <hr class="w-100 border-light opacity-25 mt-3 mb-4">
      </div>

      <!-- Mensaje de feedback -->
      <?php if ($mensaje): ?>
      <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show w-100 rounded-3" role="alert" style="background-color: rgba(<?php echo $tipo_mensaje == 'success' ? '40,167,69' : ($tipo_mensaje == 'danger' ? '220,53,69' : '255,193,7'); ?>, 0.2); border: 1px solid rgba(255,255,255,0.2); color: white;">
        <i class="bi bi-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : ($tipo_mensaje == 'danger' ? 'x-circle' : 'exclamation-triangle'); ?>-fill me-2"></i>
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
      <?php endif; ?>

      <!-- Formulario para añadir profesor por ORCID -->
      <div class="mx-auto mb-4 p-4 rounded-4" style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); max-width: 600px; width: 100%;">
        <h6 class="text-white mb-2 fw-bold d-inline-flex align-items-center gap-1"><i class="bi bi-person-plus-fill"></i> Añadir profesor al grupo</h6>
        <form method="POST" style="padding: 0; margin: 0;">
          <input type="hidden" name="accion" value="añadir">
          <div class="mb-2" style="display: flex; flex-direction: column; width: 100%; gap: 0; margin: 0;">
            <label class="form-label text-light small mb-1 w-100 text-start" style="font-size: 0.85rem;">ORCID del profesor</label>
            <input type="text" name="orcid" class="form-control input-orcid w-100" placeholder="0000-0000-0000-0000" required 
                   pattern="[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}" title="Formato: 0000-0000-0000-0000"
                   style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px; font-size: 1rem;">
          </div>
          <div class="d-grid mt-3">
            <button type="submit" class="btn btn-outline-success rounded-pill d-inline-flex align-items-center justify-content-center gap-2 py-2 fw-medium">
              <i class="bi bi-plus-circle-fill"></i> Añadir profesor
            </button>
          </div>
        </form>
      </div>

      <!-- Tabla de profesores del grupo -->
      <div class="w-100 mb-4 p-4 rounded-4" style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
        <h6 class="text-white mb-2 fw-bold d-inline-flex align-items-center gap-1"><i class="bi bi-list-ul"></i> Profesores en este grupo</h6>
        <?php if ($query_profes && mysqli_num_rows($query_profes) > 0): ?>
        <div class="table-responsive w-100" style="border-radius: 15px;">
          <table class="table tabla-glass mb-0">
            <thead>
              <tr>
                <th scope="col" class="border-top-0 border-end-0">ORCID</th>
                <th scope="col" class="border-top-0 border-end-0">Nombre</th>
                <th scope="col" class="border-top-0 border-end-0">Departamento</th>
                <th scope="col" class="border-top-0 border-end-0">Correo</th>
                <th scope="col" class="border-top-0 border-end-0 text-center">Eliminar</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($p = mysqli_fetch_assoc($query_profes)): ?>
              <tr>
                <td class="border-end-0"><?php echo empty($p['ORCID']) ? '-' : htmlspecialchars($p['ORCID']); ?></td>
                <td class="border-end-0"><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellidos']); ?></td>
                <td class="border-end-0"><?php echo empty($p['departamento']) ? '-' : htmlspecialchars($p['departamento']); ?></td>
                <td class="border-end-0"><?php echo empty($p['correo']) ? '-' : htmlspecialchars($p['correo']); ?></td>
                <td class="border-end-0 text-center">
                  <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar a <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellidos']); ?> de este grupo?');">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_profesor" value="<?php echo $p['id_profesor']; ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill d-inline-flex align-items-center gap-1">
                      <i class="bi bi-trash-fill"></i> Eliminar
                    </button>
                  </form>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="w-100 text-center text-white-50 p-4" style="background-color: rgba(255,255,255,0.05); border-radius: 15px;">
          Este grupo aún no tiene profesores asignados.
        </div>
        <?php endif; ?>
      </div>

      <div class="d-flex justify-content-center w-100 mt-2">
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
