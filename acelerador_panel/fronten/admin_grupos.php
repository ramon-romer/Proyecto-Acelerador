<?php
session_start();
include('login.php');
error_reporting(0);

if (!function_exists('admin_h')) {
  function admin_h($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('admin_csrf_token')) {
  function admin_csrf_token()
  {
    if (empty($_SESSION['admin_csrf_token'])) {
      $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
  }
}

if (!function_exists('admin_csrf_field')) {
  function admin_csrf_field()
  {
    return '<input type="hidden" name="csrf_token" value="' . admin_h(admin_csrf_token()) . '">';
  }
}

if (!function_exists('admin_csrf_is_valid')) {
  function admin_csrf_is_valid()
  {
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && hash_equals((string) ($_SESSION['admin_csrf_token'] ?? ''), $token);
  }
}

if (!function_exists('admin_stmt')) {
  function admin_stmt($conn, $sql, $types = '', &...$params)
  {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
      return false;
    }

    if ($types !== '' && !mysqli_stmt_bind_param($stmt, $types, ...$params)) {
      mysqli_stmt_close($stmt);
      return false;
    }

    if (!mysqli_stmt_execute($stmt)) {
      mysqli_stmt_close($stmt);
      return false;
    }

    return $stmt;
  }
}

if (!function_exists('admin_fetch_one')) {
  function admin_fetch_one($conn, $sql, $types = '', &...$params)
  {
    $stmt = admin_stmt($conn, $sql, $types, ...$params);
    if (!$stmt) {
      return null;
    }

    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
  }
}

if (!function_exists('admin_fetch_all')) {
  function admin_fetch_all($conn, $sql, $types = '', &...$params)
  {
    $stmt = admin_stmt($conn, $sql, $types, ...$params);
    if (!$stmt) {
      return [];
    }

    $rows = [];
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
      while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
      }
    }
    mysqli_stmt_close($stmt);

    return $rows;
  }
}

if (!function_exists('admin_execute')) {
  function admin_execute($conn, $sql, $types = '', &...$params)
  {
    $stmt = admin_stmt($conn, $sql, $types, ...$params);
    if (!$stmt) {
      return false;
    }

    mysqli_stmt_close($stmt);
    return true;
  }
}

// Protección de sesión y perfil ADMIN
if (!isset($_SESSION['nombredelusuario']) || $_SESSION['nombredelusuario'] == '') {
  header("Location: ../../acelerador_login/fronten/index.php");
  exit();
}
$correo_admin = (string) $_SESSION['nombredelusuario'];
$admin_row = admin_fetch_one($conn, "SELECT perfil FROM tbl_profesor WHERE correo = ? LIMIT 1", "s", $correo_admin);
if (!$admin_row) {
  header("Location: ../../acelerador_login/fronten/index.php");
  exit();
}
$perfil_admin = strtoupper((string) ($admin_row['perfil'] ?? ''));
if ($perfil_admin != 'ADMIN' && $perfil_admin != 'ADMINISTRADOR') {
  header("Location: ../../acelerador_login/fronten/index.php");
  exit();
}

$mensaje = '';
$tipo_mensaje = '';
$grupo_seleccionado = null;
$profesores_grupo = [];
$csrf_valido = true;

admin_csrf_token();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !admin_csrf_is_valid()) {
  $csrf_valido = false;
  $mensaje = "Operacion rechazada. Vuelve a cargar la pagina e intentalo de nuevo.";
  $tipo_mensaje = "danger";
}

// ==================== PROCESAR ACCIONES POST ====================

// --- CREAR GRUPO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'crear_grupo') {
  $nombre_grupo = trim($_POST['nombre_grupo'] ?? '');
  $id_tutor = intval($_POST['id_tutor'] ?? 0);

  if (empty($nombre_grupo) || $id_tutor == 0) {
    $mensaje = "Debes indicar un nombre y seleccionar un tutor.";
    $tipo_mensaje = "danger";
  } else {
    $check = admin_fetch_one($conn, "SELECT id_grupo FROM tbl_grupo WHERE nombre = ? LIMIT 1", "s", $nombre_grupo);
    if ($check) {
      $mensaje = "Ya existe un grupo con ese nombre.";
      $tipo_mensaje = "warning";
    } else {
      if (admin_execute($conn, "INSERT INTO tbl_grupo (nombre, id_tutor) VALUES (?, ?)", "si", $nombre_grupo, $id_tutor)) {
        $mensaje = "Grupo <strong>" . admin_h($nombre_grupo) . "</strong> creado correctamente.";
        $tipo_mensaje = "success";
      } else {
        $mensaje = "No se pudo crear el grupo.";
        $tipo_mensaje = "danger";
      }
    }
  }
}

// --- CAMBIAR TUTOR ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'cambiar_tutor') {
  $id_grupo = intval($_POST['id_grupo'] ?? 0);
  $nuevo_tutor = intval($_POST['nuevo_tutor'] ?? 0);
  if (admin_execute($conn, "UPDATE tbl_grupo SET id_tutor = ? WHERE id_grupo = ?", "ii", $nuevo_tutor, $id_grupo)) {
    $mensaje = "Tutor del grupo actualizado correctamente.";
    $tipo_mensaje = "success";
  } else {
    $mensaje = "No se pudo actualizar el tutor del grupo.";
    $tipo_mensaje = "danger";
  }
  if (isset($_POST['id_grupo_sel']))
    $_POST['ver_grupo'] = $_POST['id_grupo_sel'];
}

// --- CAMBIAR NOMBRE GRUPO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'renombrar_grupo') {
  $id_grupo = intval($_POST['id_grupo'] ?? 0);
  $nuevo_nombre = trim($_POST['nuevo_nombre'] ?? '');
  if (!empty($nuevo_nombre)) {
    if (admin_execute($conn, "UPDATE tbl_grupo SET nombre = ? WHERE id_grupo = ?", "si", $nuevo_nombre, $id_grupo)) {
      $mensaje = "Nombre del grupo actualizado.";
      $tipo_mensaje = "success";
    } else {
      $mensaje = "No se pudo actualizar el nombre del grupo.";
      $tipo_mensaje = "danger";
    }
  }
  $_POST['ver_grupo'] = $id_grupo;
}

// --- AÑADIR PROFESOR AL GRUPO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'añadir_prof') {
  $id_grupo = intval($_POST['id_grupo'] ?? 0);
  $orcid_add = trim($_POST['orcid_add'] ?? '');

  $prof = admin_fetch_one($conn, "SELECT id_profesor FROM tbl_profesor WHERE ORCID = ? LIMIT 1", "s", $orcid_add);
  if ($prof) {
    $id_prof = intval($prof['id_profesor'] ?? 0);
    $dup = admin_fetch_one($conn, "SELECT id FROM tbl_grupo_profesor WHERE id_grupo = ? AND id_profesor = ? LIMIT 1", "ii", $id_grupo, $id_prof);
    if ($dup) {
      $mensaje = "Ese profesor ya pertenece a este grupo.";
      $tipo_mensaje = "warning";
    } else {
      if (admin_execute($conn, "INSERT INTO tbl_grupo_profesor (id_grupo, id_profesor) VALUES (?, ?)", "ii", $id_grupo, $id_prof)) {
        $mensaje = "Profesor añadido al grupo correctamente.";
        $tipo_mensaje = "success";
      } else {
        $mensaje = "No se pudo añadir el profesor al grupo.";
        $tipo_mensaje = "danger";
      }
    }
  } else {
    $mensaje = "No se encontró un profesor con ese ORCID.";
    $tipo_mensaje = "warning";
  }
  $_POST['ver_grupo'] = $id_grupo;
}

// --- ELIMINAR PROFESOR DEL GRUPO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'quitar_prof') {
  $id_grupo = intval($_POST['id_grupo'] ?? 0);
  $id_prof = intval($_POST['id_profesor'] ?? 0);
  if (admin_execute($conn, "DELETE FROM tbl_grupo_profesor WHERE id_grupo = ? AND id_profesor = ?", "ii", $id_grupo, $id_prof)) {
    $mensaje = "Profesor eliminado del grupo.";
    $tipo_mensaje = "success";
  } else {
    $mensaje = "No se pudo eliminar el profesor del grupo.";
    $tipo_mensaje = "danger";
  }
  $_POST['ver_grupo'] = $id_grupo;
}

// --- ELIMINAR GRUPO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'eliminar_grupo') {
  $id_grupo = intval($_POST['id_grupo'] ?? 0);
  $del1 = admin_execute($conn, "DELETE FROM tbl_grupo_profesor WHERE id_grupo = ?", "i", $id_grupo);
  $del2 = admin_execute($conn, "DELETE FROM tbl_grupo WHERE id_grupo = ?", "i", $id_grupo);
  if ($del1 && $del2) {
    $mensaje = "Grupo eliminado correctamente.";
    $tipo_mensaje = "success";
  } else {
    $mensaje = "No se pudo eliminar el grupo.";
    $tipo_mensaje = "danger";
  }
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
if ($csrf_valido && isset($_POST['ver_grupo'])) {
  $id_ver = intval($_POST['ver_grupo']);
  $grupo_seleccionado = admin_fetch_one($conn, "SELECT g.id_grupo, g.nombre, t.nombre AS tutor_nombre, t.apellidos AS tutor_apellidos, t.id_profesor AS id_tutor FROM tbl_grupo g INNER JOIN tbl_profesor t ON g.id_tutor = t.id_profesor WHERE g.id_grupo = ? LIMIT 1", "i", $id_ver);
  if ($grupo_seleccionado) {
    // Cargar profesores del grupo
    $profesores_grupo = admin_fetch_all($conn, "SELECT p.id_profesor, p.ORCID, p.nombre, p.apellidos, p.departamento FROM tbl_grupo_profesor gp INNER JOIN tbl_profesor p ON gp.id_profesor = p.id_profesor WHERE gp.id_grupo = ? ORDER BY p.nombre ASC", "i", $id_ver);
  }
}
?>

<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador - Gestionar Grupos</title>
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
    <div class="formulario-tabla" style="max-width: 950px;">

      <div class="text-center mb-4 w-100">
        <i class="bi bi-diagram-3-fill text-white mb-2"
          style="font-size: 3.5rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Gestión de Tutorías / Grupos</h2>
        <p class="text-white-50">Crea, modifica, reasigna tutores y gestiona los miembros de cada grupo.</p>
        <hr class="w-100 border-light opacity-25 mt-3 mb-4">
      </div>

      <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show w-100 rounded-3 mb-4"
          role="alert"
          style="background-color: rgba(<?php echo $tipo_mensaje == 'success' ? '40,167,69' : ($tipo_mensaje == 'danger' ? '220,53,69' : '255,193,7'); ?>, 0.2); border: 1px solid rgba(255,255,255,0.2); color: white;">
          <i
            class="bi bi-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : ($tipo_mensaje == 'danger' ? 'x-circle' : 'exclamation-triangle'); ?>-fill me-2"></i>
          <?php echo $mensaje; ?>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
      <?php endif; ?>

      <!-- ===== CREAR GRUPO ===== -->
      <div class="w-100 p-4 rounded-4 mb-4"
        style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
        <h5 class="text-white fw-bold mb-3"><i class="bi bi-plus-circle-fill me-2"></i>Crear nuevo grupo</h5>
        <form method="POST" style="padding:0; margin:0;">
          <?php echo admin_csrf_field(); ?>
          <input type="hidden" name="accion" value="crear_grupo">
          <div class="row g-3 align-items-end">
            <div class="col-md-5">
              <label class="form-label text-light small mb-1">Nombre del grupo</label>
              <input type="text" name="nombre_grupo" class="form-control" required placeholder="Ej: Grupo Avanzado"
                style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px;">
            </div>
            <div class="col-md-5">
              <label class="form-label text-light small mb-1">Tutor responsable</label>
              <select name="id_tutor" class="form-select" required
                style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                <option value="" disabled selected>Seleccionar tutor...</option>
                <?php foreach ($tutores as $t): ?>
                  <option value="<?php echo intval($t['id_profesor']); ?>">
                    <?php echo htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-outline-success rounded-pill py-2 fw-bold text-nowrap"><i
                  class="bi bi-plus-lg"></i> Crear</button>
            </div>
          </div>
        </form>
      </div>

      <!-- ===== LISTA DE GRUPOS + FILTRO ===== -->
      <div class="w-100 p-4 rounded-4 mb-4"
        style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
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
                    <td class="border-end-0 border-bottom-0 text-white px-3 py-2 fw-bold nombre-grupo">
                      <?php echo htmlspecialchars($g['nombre']); ?>
                    </td>
                    <td class="border-end-0 border-bottom-0 text-white px-3 py-2">
                      <?php echo htmlspecialchars($g['tutor_nombre'] . ' ' . $g['tutor_apellidos']); ?>
                    </td>
                    <td class="border-end-0 border-bottom-0 text-white px-3 py-2 text-center">
                      <?php echo intval($g['num_profesores']); ?>
                    </td>
                    <td class="border-end-0 border-bottom-0 text-center px-3 py-2">
                      <form method="POST" style="display:inline; padding:0; margin:0;">
                        <?php echo admin_csrf_field(); ?>
                        <input type="hidden" name="ver_grupo" value="<?php echo intval($g['id_grupo']); ?>">
                        <button type="submit" class="btn btn-outline-info btn-sm rounded-pill px-3"><i
                            class="bi bi-pencil-square me-1"></i> Modificar</button>
                      </form>
                      <form method="POST" style="display:inline; padding:0; margin:0;">
                        <?php echo admin_csrf_field(); ?>
                        <input type="hidden" name="accion" value="eliminar_grupo">
                        <input type="hidden" name="id_grupo" value="<?php echo intval($g['id_grupo']); ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-2"
                          onclick="return confirm('¿Eliminar este grupo y todas sus asignaciones?')"><i
                            class="bi bi-trash"></i></button>
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
        <div class="w-100 p-4 rounded-4 mb-4"
          style="background-color: rgba(40,167,69,0.08); border: 1px solid rgba(40,167,69,0.3);">
          <h5 class="text-white fw-bold mb-3"><i class="bi bi-gear-fill me-2"></i>Modificar:
            <?php echo htmlspecialchars($grupo_seleccionado['nombre']); ?>
          </h5>

          <!-- Renombrar -->
          <div class="mb-4 p-3 rounded-3" style="background-color: rgba(255,255,255,0.05);">
            <h6 class="text-white-50 fw-bold mb-2"><i class="bi bi-cursor-text me-1"></i> Renombrar grupo</h6>
            <form method="POST" style="padding:0; margin:0;">
              <?php echo admin_csrf_field(); ?>
              <input type="hidden" name="accion" value="renombrar_grupo">
              <input type="hidden" name="id_grupo" value="<?php echo intval($grupo_seleccionado['id_grupo']); ?>">
              <div class="d-flex gap-2">
                <input type="text" name="nuevo_nombre" class="form-control flex-grow-1"
                  value="<?php echo admin_h($grupo_seleccionado['nombre']); ?>" required
                  style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                <button type="submit"
                  class="btn btn-outline-light btn-sm rounded-pill px-3 text-nowrap">Renombrar</button>
              </div>
            </form>
          </div>

          <!-- Cambiar Tutor -->
          <div class="mb-4 p-3 rounded-3" style="background-color: rgba(255,255,255,0.05);">
            <h6 class="text-white-50 fw-bold mb-2"><i class="bi bi-person-badge me-1"></i> Cambiar tutor (actual:
              <?php echo htmlspecialchars($grupo_seleccionado['tutor_nombre'] . ' ' . $grupo_seleccionado['tutor_apellidos']); ?>)
            </h6>
            <form method="POST" style="padding:0; margin:0;">
              <?php echo admin_csrf_field(); ?>
              <input type="hidden" name="accion" value="cambiar_tutor">
              <input type="hidden" name="id_grupo" value="<?php echo intval($grupo_seleccionado['id_grupo']); ?>">
              <input type="hidden" name="id_grupo_sel" value="<?php echo intval($grupo_seleccionado['id_grupo']); ?>">
              <div class="d-flex gap-2">
                <select name="nuevo_tutor" class="form-select flex-grow-1" required
                  style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                  <?php foreach ($tutores as $t): ?>
                    <option value="<?php echo intval($t['id_profesor']); ?>" <?php echo $t['id_profesor'] == $grupo_seleccionado['id_tutor'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit"
                  class="btn btn-outline-warning btn-sm rounded-pill px-3 text-nowrap">Cambiar</button>
              </div>
            </form>
          </div>

          <!-- Profesores del grupo -->
          <div class="mb-3 p-3 rounded-3" style="background-color: rgba(255,255,255,0.05);">
            <h6 class="text-white-50 fw-bold mb-2"><i class="bi bi-people me-1"></i> Profesores en el grupo
              (<?php echo count($profesores_grupo); ?>)</h6>
            <?php if (count($profesores_grupo) > 0): ?>
              <div class="table-responsive mb-3" style="border-radius: 10px;">
                <table class="table tabla-glass mb-0 text-start">
                  <thead>
                    <tr>
                      <th class="border-top-0 border-end-0 text-white px-3 py-2">ORCID</th>
                      <th class="border-top-0 border-end-0 text-white px-3 py-2">Nombre</th>
                      <th class="border-top-0 border-end-0 text-white px-3 py-2">Departamento</th>
                      <th class="border-top-0 border-end-0 text-white px-3 py-2 text-center">Quitar</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($profesores_grupo as $pg): ?>
                      <tr>
                        <td class="border-end-0 border-bottom-0 text-white px-3 py-2">
                          <?php echo htmlspecialchars($pg['ORCID']); ?>
                        </td>
                        <td class="border-end-0 border-bottom-0 text-white px-3 py-2">
                          <?php echo htmlspecialchars($pg['nombre'] . ' ' . $pg['apellidos']); ?>
                        </td>
                        <td class="border-end-0 border-bottom-0 text-white-50 px-3 py-2">
                          <?php echo empty($pg['departamento']) ? '-' : htmlspecialchars($pg['departamento']); ?>
                        </td>
                        <td class="border-end-0 border-bottom-0 text-center px-3 py-2">
                          <form method="POST" style="display:inline; padding:0; margin:0;">
                            <?php echo admin_csrf_field(); ?>
                            <input type="hidden" name="accion" value="quitar_prof">
                            <input type="hidden" name="id_grupo" value="<?php echo intval($grupo_seleccionado['id_grupo']); ?>">
                            <input type="hidden" name="id_profesor" value="<?php echo intval($pg['id_profesor']); ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill"
                              onclick="return confirm('¿Quitar a este profesor del grupo?')"><i
                                class="bi bi-x-circle"></i></button>
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
            <h6 class="text-white-50 fw-bold mb-2"><i class="bi bi-person-plus-fill me-1"></i> Añadir profesor por ORCID
            </h6>
            <form method="POST" style="padding:0; margin:0;">
              <?php echo admin_csrf_field(); ?>
              <input type="hidden" name="accion" value="añadir_prof">
              <input type="hidden" name="id_grupo" value="<?php echo intval($grupo_seleccionado['id_grupo']); ?>">
              <div class="d-flex gap-2">
                <input type="text" name="orcid_add" class="form-control flex-grow-1" placeholder="0000-0000-0000-0000"
                  required pattern="[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}"
                  style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                <button type="submit" class="btn btn-outline-success btn-sm rounded-pill px-3 text-nowrap"><i
                    class="bi bi-plus-circle-fill me-1"></i> Añadir</button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <!-- BOTONES NAVEGACIÓN -->
      <div class="d-flex justify-content-center w-100 mt-2 gap-3">
        <a href="panel_admin.php"
          class="btn btn-volver px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all text-decoration-none">
          <i class="bi bi-arrow-left"></i> Volver al panel
        </a>
      </div>

    </div>
  </main>

  <footer>
    <div class="mipie" id="mipie">
      <div class="direccion">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU Universidad Fernando III"
          style="height:50px; width:auto;" id="#acele" />
        <p>Glorieta Ángel Herrera Oria, s/n,<br />41930 Bormujos,<br />Sevilla</p>
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
  <script>
    // Filtro de grupos en tiempo real
    document.getElementById('filtroGrupos').addEventListener('input', function () {
      const filtro = this.value.toLowerCase();
      document.querySelectorAll('.fila-grupo').forEach(function (fila) {
        const nombre = fila.querySelector('.nombre-grupo').textContent.toLowerCase();
        fila.style.display = nombre.includes(filtro) ? '' : 'none';
      });
    });
  </script>
</body>

</html>
