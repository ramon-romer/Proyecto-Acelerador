<?php
session_start();
include('login.php');
error_reporting(0);

// Protección de sesión y perfil ADMIN
if (!isset($_SESSION['nombredelusuario']) || $_SESSION['nombredelusuario'] == '') {
    header("Location: ../../acelerador_login/fronten/index.php");
    exit();
}
$correo_admin = $_SESSION['nombredelusuario'];
$q = mysqli_query($conn, "SELECT perfil FROM tbl_profesor WHERE correo = '$correo_admin'");
if (!$q || mysqli_num_rows($q) == 0) {
    header("Location: ../../acelerador_login/fronten/index.php");
    exit();
}
$perfil_admin = strtoupper((string)(mysqli_fetch_assoc($q)['perfil'] ?? ''));
if ($perfil_admin != 'ADMIN' && $perfil_admin != 'ADMINISTRADOR') {
    header("Location: ../../acelerador_login/fronten/index.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';
$grupo_seleccionado = null;
$profesores_grupo = [];

// ==================== PROCESAR ACCIONES POST ====================

// --- CREAR GRUPO ---
if (isset($_POST['accion']) && $_POST['accion'] == 'crear_grupo') {
    $nombre_grupo = mysqli_real_escape_string($conn, trim($_POST['nombre_grupo']));
    $id_tutor = intval($_POST['id_tutor']);

    if (empty($nombre_grupo) || $id_tutor == 0) {
        $mensaje = "Debes indicar un nombre y seleccionar un tutor.";
        $tipo_mensaje = "danger";
    } else {
        $check = mysqli_query($conn, "SELECT id_grupo FROM tbl_grupo WHERE nombre = '$nombre_grupo'");
        if (mysqli_num_rows($check) > 0) {
            $mensaje = "Ya existe un grupo con ese nombre.";
            $tipo_mensaje = "warning";
        } else {
            mysqli_query($conn, "INSERT INTO tbl_grupo (nombre, id_tutor) VALUES ('$nombre_grupo', $id_tutor)");
            $mensaje = "Grupo <strong>" . htmlspecialchars($nombre_grupo) . "</strong> creado correctamente.";
            $tipo_mensaje = "success";
        }
    }
}

// --- CAMBIAR TUTOR ---
if (isset($_POST['accion']) && $_POST['accion'] == 'cambiar_tutor') {
    $id_grupo = intval($_POST['id_grupo']);
    $nuevo_tutor = intval($_POST['nuevo_tutor']);
    mysqli_query($conn, "UPDATE tbl_grupo SET id_tutor = $nuevo_tutor WHERE id_grupo = $id_grupo");
    $mensaje = "Tutor del grupo actualizado correctamente.";
    $tipo_mensaje = "success";
    if (isset($_POST['id_grupo_sel'])) $_POST['ver_grupo'] = $_POST['id_grupo_sel'];
}

// --- CAMBIAR NOMBRE GRUPO ---
if (isset($_POST['accion']) && $_POST['accion'] == 'renombrar_grupo') {
    $id_grupo = intval($_POST['id_grupo']);
    $nuevo_nombre = mysqli_real_escape_string($conn, trim($_POST['nuevo_nombre']));
    if (!empty($nuevo_nombre)) {
        mysqli_query($conn, "UPDATE tbl_grupo SET nombre = '$nuevo_nombre' WHERE id_grupo = $id_grupo");
        $mensaje = "Nombre del grupo actualizado.";
        $tipo_mensaje = "success";
    }
    $_POST['ver_grupo'] = $id_grupo;
}

// --- AÑADIR PROFESOR AL GRUPO ---
if (isset($_POST['accion']) && $_POST['accion'] == 'añadir_prof') {
    $id_grupo = intval($_POST['id_grupo']);
    $orcid_add = mysqli_real_escape_string($conn, trim($_POST['orcid_add']));

    $q_prof = mysqli_query($conn, "SELECT id_profesor FROM tbl_profesor WHERE ORCID = '$orcid_add'");
    if ($q_prof && mysqli_num_rows($q_prof) > 0) {
        $id_prof = mysqli_fetch_assoc($q_prof)['id_profesor'];
        $dup = mysqli_query($conn, "SELECT id FROM tbl_grupo_profesor WHERE id_grupo = $id_grupo AND id_profesor = $id_prof");
        if (mysqli_num_rows($dup) > 0) {
            $mensaje = "Ese profesor ya pertenece a este grupo.";
            $tipo_mensaje = "warning";
        } else {
            mysqli_query($conn, "INSERT INTO tbl_grupo_profesor (id_grupo, id_profesor) VALUES ($id_grupo, $id_prof)");
            $mensaje = "Profesor añadido al grupo correctamente.";
            $tipo_mensaje = "success";
        }
    } else {
        $mensaje = "No se encontró un profesor con ese ORCID.";
        $tipo_mensaje = "warning";
    }
    $_POST['ver_grupo'] = $id_grupo;
}

// --- ELIMINAR PROFESOR DEL GRUPO ---
if (isset($_POST['accion']) && $_POST['accion'] == 'quitar_prof') {
    $id_grupo = intval($_POST['id_grupo']);
    $id_prof = intval($_POST['id_profesor']);
    mysqli_query($conn, "DELETE FROM tbl_grupo_profesor WHERE id_grupo = $id_grupo AND id_profesor = $id_prof");
    $mensaje = "Profesor eliminado del grupo.";
    $tipo_mensaje = "success";
    $_POST['ver_grupo'] = $id_grupo;
}

// --- ELIMINAR GRUPO ---
if (isset($_POST['accion']) && $_POST['accion'] == 'eliminar_grupo') {
    $id_grupo = intval($_POST['id_grupo']);
    mysqli_query($conn, "DELETE FROM tbl_grupo_profesor WHERE id_grupo = $id_grupo");
    mysqli_query($conn, "DELETE FROM tbl_grupo WHERE id_grupo = $id_grupo");
    $mensaje = "Grupo eliminado correctamente.";
    $tipo_mensaje = "success";
}

// ==================== CARGAR DATOS ====================

// Lista de tutores para selects
$tutores = [];
$q_tutores = mysqli_query($conn, "SELECT id_profesor, nombre, apellidos FROM tbl_profesor WHERE perfil = 'TUTOR' ORDER BY nombre ASC");
while ($q_tutores && $rt = mysqli_fetch_assoc($q_tutores)) {
    $tutores[] = $rt;
}

// Lista de TODOS los grupos
$grupos = [];
$q_grupos = mysqli_query($conn, "
    SELECT g.id_grupo, g.nombre, t.nombre AS tutor_nombre, t.apellidos AS tutor_apellidos, t.id_profesor AS id_tutor,
           (SELECT COUNT(*) FROM tbl_grupo_profesor gp WHERE gp.id_grupo = g.id_grupo) AS num_profesores
    FROM tbl_grupo g
    INNER JOIN tbl_profesor t ON g.id_tutor = t.id_profesor
    ORDER BY g.nombre ASC
");
while ($q_grupos && $rg = mysqli_fetch_assoc($q_grupos)) {
    $grupos[] = $rg;
}

// Si se seleccionó un grupo para ver/modificar
if (isset($_POST['ver_grupo'])) {
    $id_ver = intval($_POST['ver_grupo']);
    $q_det = mysqli_query($conn, "SELECT g.id_grupo, g.nombre, t.nombre AS tutor_nombre, t.apellidos AS tutor_apellidos, t.id_profesor AS id_tutor FROM tbl_grupo g INNER JOIN tbl_profesor t ON g.id_tutor = t.id_profesor WHERE g.id_grupo = $id_ver");
    if ($q_det && mysqli_num_rows($q_det) > 0) {
        $grupo_seleccionado = mysqli_fetch_assoc($q_det);
        // Cargar profesores del grupo
        $q_profs = mysqli_query($conn, "SELECT p.id_profesor, p.ORCID, p.nombre, p.apellidos, p.departamento FROM tbl_grupo_profesor gp INNER JOIN tbl_profesor p ON gp.id_profesor = p.id_profesor WHERE gp.id_grupo = $id_ver ORDER BY p.nombre ASC");
        while ($q_profs && $rp = mysqli_fetch_assoc($q_profs)) {
            $profesores_grupo[] = $rp;
        }
    }
}
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador - Gestionar Grupos</title>
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
    <div class="formulario-tabla" style="max-width: 950px;">

      <div class="text-center mb-4 w-100">
        <i class="bi bi-diagram-3-fill text-white mb-2" style="font-size: 3.5rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Gestión de Tutorías / Grupos</h2>
        <p class="text-white-50">Crea, modifica, reasigna tutores y gestiona los miembros de cada grupo.</p>
        <hr class="w-100 border-light opacity-25 mt-3 mb-4">
      </div>

      <?php if ($mensaje): ?>
      <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show w-100 rounded-3 mb-4" role="alert" style="background-color: rgba(<?php echo $tipo_mensaje == 'success' ? '40,167,69' : ($tipo_mensaje == 'danger' ? '220,53,69' : '255,193,7'); ?>, 0.2); border: 1px solid rgba(255,255,255,0.2); color: white;">
        <i class="bi bi-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : ($tipo_mensaje == 'danger' ? 'x-circle' : 'exclamation-triangle'); ?>-fill me-2"></i>
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
      <?php endif; ?>

      <!-- ===== CREAR GRUPO ===== -->
      <div class="w-100 p-4 rounded-4 mb-4" style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
        <h5 class="text-white fw-bold mb-3"><i class="bi bi-plus-circle-fill me-2"></i>Crear nuevo grupo</h5>
        <form method="POST" style="padding:0; margin:0;">
          <input type="hidden" name="accion" value="crear_grupo">
          <div class="row g-3 align-items-end">
            <div class="col-md-5">
              <label class="form-label text-light small mb-1">Nombre del grupo</label>
              <input type="text" name="nombre_grupo" class="form-control" required placeholder="Ej: Grupo Avanzado"
                     style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px;">
            </div>
            <div class="col-md-5">
              <label class="form-label text-light small mb-1">Tutor responsable</label>
              <select name="id_tutor" class="form-select" required style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                <option value="" disabled selected>Seleccionar tutor...</option>
                <?php foreach ($tutores as $t): ?>
                <option value="<?php echo $t['id_profesor']; ?>"><?php echo htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-outline-success rounded-pill py-2 fw-bold text-nowrap"><i class="bi bi-plus-lg"></i> Crear</button>
            </div>
          </div>
        </form>
      </div>

      <!-- ===== LISTA DE GRUPOS + FILTRO ===== -->
      <div class="w-100 p-4 rounded-4 mb-4" style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
        <h5 class="text-white fw-bold mb-3"><i class="bi bi-list-ul me-2"></i>Grupos existentes</h5>

        <div class="mb-3">
          <input type="text" id="filtroGrupos" class="form-control" placeholder="Filtrar por nombre de grupo..."
                 style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px;">
        </div>

        <?php if (count($grupos) > 0): ?>
        <div class="table-responsive" style="border-radius: 12px;">
          <table class="table tabla-glass mb-0 text-start" id="tablaGrupos">
            <thead>
              <tr>
                <th class="border-top-0 border-end-0 text-white px-3 py-2">Nombre</th>
                <th class="border-top-0 border-end-0 text-white px-3 py-2">Tutor</th>
                <th class="border-top-0 border-end-0 text-white px-3 py-2 text-center">Profesores</th>
                <th class="border-top-0 border-end-0 text-white px-3 py-2 text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($grupos as $g): ?>
              <tr class="fila-grupo">
                <td class="border-end-0 border-bottom-0 text-white px-3 py-2 fw-bold nombre-grupo"><?php echo htmlspecialchars($g['nombre']); ?></td>
                <td class="border-end-0 border-bottom-0 text-white px-3 py-2"><?php echo htmlspecialchars($g['tutor_nombre'] . ' ' . $g['tutor_apellidos']); ?></td>
                <td class="border-end-0 border-bottom-0 text-white px-3 py-2 text-center"><?php echo $g['num_profesores']; ?></td>
                <td class="border-end-0 border-bottom-0 text-center px-3 py-2">
                  <form method="POST" style="display:inline; padding:0; margin:0;">
                    <input type="hidden" name="ver_grupo" value="<?php echo $g['id_grupo']; ?>">
                    <button type="submit" class="btn btn-outline-info btn-sm rounded-pill px-3"><i class="bi bi-pencil-square me-1"></i> Modificar</button>
                  </form>
                  <form method="POST" style="display:inline; padding:0; margin:0;">
                    <input type="hidden" name="accion" value="eliminar_grupo">
                    <input type="hidden" name="id_grupo" value="<?php echo $g['id_grupo']; ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-2" onclick="return confirm('¿Eliminar este grupo y todas sus asignaciones?')"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <p class="text-white-50 text-center mb-0">No hay grupos creados aún.</p>
        <?php endif; ?>
      </div>

      <!-- ===== DETALLE DE GRUPO SELECCIONADO ===== -->
      <?php if ($grupo_seleccionado): ?>
      <div class="w-100 p-4 rounded-4 mb-4" style="background-color: rgba(40,167,69,0.08); border: 1px solid rgba(40,167,69,0.3);">
        <h5 class="text-white fw-bold mb-3"><i class="bi bi-gear-fill me-2"></i>Modificar: <?php echo htmlspecialchars($grupo_seleccionado['nombre']); ?></h5>

        <!-- Renombrar -->
        <div class="mb-4 p-3 rounded-3" style="background-color: rgba(255,255,255,0.05);">
          <h6 class="text-white-50 fw-bold mb-2"><i class="bi bi-cursor-text me-1"></i> Renombrar grupo</h6>
          <form method="POST" style="padding:0; margin:0;">
            <input type="hidden" name="accion" value="renombrar_grupo">
            <input type="hidden" name="id_grupo" value="<?php echo $grupo_seleccionado['id_grupo']; ?>">
            <div class="d-flex gap-2">
              <input type="text" name="nuevo_nombre" class="form-control flex-grow-1" value="<?php echo htmlspecialchars($grupo_seleccionado['nombre']); ?>" required
                     style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
              <button type="submit" class="btn btn-outline-light btn-sm rounded-pill px-3 text-nowrap">Renombrar</button>
            </div>
          </form>
        </div>

        <!-- Cambiar Tutor -->
        <div class="mb-4 p-3 rounded-3" style="background-color: rgba(255,255,255,0.05);">
          <h6 class="text-white-50 fw-bold mb-2"><i class="bi bi-person-badge me-1"></i> Cambiar tutor (actual: <?php echo htmlspecialchars($grupo_seleccionado['tutor_nombre'] . ' ' . $grupo_seleccionado['tutor_apellidos']); ?>)</h6>
          <form method="POST" style="padding:0; margin:0;">
            <input type="hidden" name="accion" value="cambiar_tutor">
            <input type="hidden" name="id_grupo" value="<?php echo $grupo_seleccionado['id_grupo']; ?>">
            <input type="hidden" name="id_grupo_sel" value="<?php echo $grupo_seleccionado['id_grupo']; ?>">
            <div class="d-flex gap-2">
              <select name="nuevo_tutor" class="form-select flex-grow-1" required style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                <?php foreach ($tutores as $t): ?>
                <option value="<?php echo $t['id_profesor']; ?>" <?php echo $t['id_profesor'] == $grupo_seleccionado['id_tutor'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']); ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-outline-warning btn-sm rounded-pill px-3 text-nowrap">Cambiar</button>
            </div>
          </form>
        </div>

        <!-- Profesores del grupo -->
        <div class="mb-3 p-3 rounded-3" style="background-color: rgba(255,255,255,0.05);">
          <h6 class="text-white-50 fw-bold mb-2"><i class="bi bi-people me-1"></i> Profesores en el grupo (<?php echo count($profesores_grupo); ?>)</h6>
          <?php if (count($profesores_grupo) > 0): ?>
          <div class="table-responsive mb-3" style="border-radius: 10px;">
            <table class="table tabla-glass mb-0 text-start">
              <thead><tr>
                <th class="border-top-0 border-end-0 text-white px-3 py-2">ORCID</th>
                <th class="border-top-0 border-end-0 text-white px-3 py-2">Nombre</th>
                <th class="border-top-0 border-end-0 text-white px-3 py-2">Departamento</th>
                <th class="border-top-0 border-end-0 text-white px-3 py-2 text-center">Quitar</th>
              </tr></thead>
              <tbody>
                <?php foreach ($profesores_grupo as $pg): ?>
                <tr>
                  <td class="border-end-0 border-bottom-0 text-white px-3 py-2"><?php echo htmlspecialchars($pg['ORCID']); ?></td>
                  <td class="border-end-0 border-bottom-0 text-white px-3 py-2"><?php echo htmlspecialchars($pg['nombre'] . ' ' . $pg['apellidos']); ?></td>
                  <td class="border-end-0 border-bottom-0 text-white-50 px-3 py-2"><?php echo empty($pg['departamento']) ? '-' : htmlspecialchars($pg['departamento']); ?></td>
                  <td class="border-end-0 border-bottom-0 text-center px-3 py-2">
                    <form method="POST" style="display:inline; padding:0; margin:0;">
                      <input type="hidden" name="accion" value="quitar_prof">
                      <input type="hidden" name="id_grupo" value="<?php echo $grupo_seleccionado['id_grupo']; ?>">
                      <input type="hidden" name="id_profesor" value="<?php echo $pg['id_profesor']; ?>">
                      <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill" onclick="return confirm('¿Quitar a este profesor del grupo?')"><i class="bi bi-x-circle"></i></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <p class="text-white-50 mb-3">Este grupo aún no tiene profesores.</p>
          <?php endif; ?>

          <!-- Añadir profesor por ORCID -->
          <h6 class="text-white-50 fw-bold mb-2"><i class="bi bi-person-plus-fill me-1"></i> Añadir profesor por ORCID</h6>
          <form method="POST" style="padding:0; margin:0;">
            <input type="hidden" name="accion" value="añadir_prof">
            <input type="hidden" name="id_grupo" value="<?php echo $grupo_seleccionado['id_grupo']; ?>">
            <div class="d-flex gap-2">
              <input type="text" name="orcid_add" class="form-control flex-grow-1" placeholder="0000-0000-0000-0000" required
                     pattern="[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}"
                     style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
              <button type="submit" class="btn btn-outline-success btn-sm rounded-pill px-3 text-nowrap"><i class="bi bi-plus-circle-fill me-1"></i> Añadir</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- BOTONES NAVEGACIÓN -->
      <div class="d-flex justify-content-center w-100 mt-2 gap-3">
        <a href="panel_admin.php" class="btn btn-volver px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all text-decoration-none">
            <i class="bi bi-arrow-left"></i> Volver al panel
        </a>
      </div>

    </div>
  </main>

  <footer>
    <div class="mipie" id="mipie">
      <div class="direccion"><img src="img/Image__4_-removebg-preview.png" /><p>Glorieta Ángel Herrera Oria, s/n,<br />41930 Bormujos,<br />Sevilla</p></div>
      <div class="requerimientolegal">
        <div class="columna"><h4>La Empresa</h4><ul><li>Contacto</li><li>Preguntas Frecuentes (FAQ)</li><li>Centro de Ayuda</li><li>Soporte</li></ul></div>
        <div class="columna"><h4>Ayuda</h4><ul><li>Términos y Condiciones</li><li>Política de Cookies</li></ul></div>
        <div class="columna"><h4>Legal</h4><ul><li>Sobre nosotros</li><li>Política de Cookies</li><li>Blog</li></ul></div>
      </div>
      <div class="piepag"><p>&copy; CEU Lab. Todos los derechos reservados.</p></div>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Filtro de grupos en tiempo real
    document.getElementById('filtroGrupos').addEventListener('input', function() {
      const filtro = this.value.toLowerCase();
      document.querySelectorAll('.fila-grupo').forEach(function(fila) {
        const nombre = fila.querySelector('.nombre-grupo').textContent.toLowerCase();
        fila.style.display = nombre.includes(filtro) ? '' : 'none';
      });
    });
  </script>
</body>
</html>
