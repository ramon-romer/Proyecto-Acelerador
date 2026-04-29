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
$usuario_encontrado = null;
$grupos_usuario = [];
$todos_grupos = [];
$csrf_valido = true;

admin_csrf_token();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !admin_csrf_is_valid()) {
  $csrf_valido = false;
  $mensaje = "Operacion rechazada. Vuelve a cargar la pagina e intentalo de nuevo.";
  $tipo_mensaje = "danger";
}

// ==================== PROCESAR ACCIONES POST ====================

// --- CREAR USUARIO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'crear') {
  $nombre = trim($_POST['nombre'] ?? '');
  $apellidos = trim($_POST['apellidos'] ?? '');
  $correo_nuevo = trim($_POST['correo'] ?? '');
  $pass_plano = trim($_POST['password'] ?? '');
  $dni = trim($_POST['dni'] ?? '');
  $orcid = trim($_POST['orcid'] ?? '');
  $telefono = trim($_POST['telefono'] ?? '');
  $perfil = trim($_POST['perfil'] ?? '');
  $facultad = trim($_POST['facultad'] ?? '');
  $departamento = trim($_POST['departamento'] ?? '');
  $rama = trim($_POST['rama'] ?? '');

  if (empty($nombre) || empty($apellidos) || empty($correo_nuevo) || empty($pass_plano) || empty($orcid)) {
    $mensaje = "Todos los campos obligatorios deben estar completos.";
    $tipo_mensaje = "danger";
  } else {
    $check = admin_fetch_one($conn, "SELECT id_profesor FROM tbl_profesor WHERE ORCID = ? LIMIT 1", "s", $orcid);
    if ($check) {
      $mensaje = "Ya existe un usuario con ese ORCID.";
      $tipo_mensaje = "warning";
    } else {
      $pass_hash = password_hash($pass_plano, PASSWORD_DEFAULT);
      if ($pass_hash === false) {
        $mensaje = "No se pudo generar la contraseña segura para el nuevo usuario.";
        $tipo_mensaje = "danger";
      } else {
        mysqli_begin_transaction($conn);
        $ins1 = admin_execute($conn, "INSERT INTO tbl_profesor (ORCID, nombre, apellidos, password, DNI, telefono, perfil, facultad, departamento, correo, rama) VALUES (?,?,?,?,?,?,?,?,?,?,?)", "sssssssssss", $orcid, $nombre, $apellidos, $pass_hash, $dni, $telefono, $perfil, $facultad, $departamento, $correo_nuevo, $rama);
        $ins2 = admin_execute($conn, "INSERT INTO tbl_usuario (correo, password) VALUES (?,?)", "ss", $correo_nuevo, $pass_hash);

        if ($ins1 && $ins2) {
          mysqli_commit($conn);
          $mensaje = "Usuario <strong>" . admin_h($nombre) . " " . admin_h($apellidos) . "</strong> creado correctamente.";
          $tipo_mensaje = "success";
        } else {
          mysqli_rollback($conn);
          $mensaje = "No se pudo crear el usuario.";
          $tipo_mensaje = "danger";
        }
      }
    }
  }
}

// --- EDITAR USUARIO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
  $id_edit = intval($_POST['id_profesor'] ?? 0);
  $nombre = trim($_POST['nombre'] ?? '');
  $apellidos = trim($_POST['apellidos'] ?? '');
  $correo_edit = trim($_POST['correo'] ?? '');
  $dni = trim($_POST['dni'] ?? '');
  $telefono = trim($_POST['telefono'] ?? '');
  $perfil_edit = trim($_POST['perfil'] ?? '');
  $facultad = trim($_POST['facultad'] ?? '');
  $departamento = trim($_POST['departamento'] ?? '');
  $rama = trim($_POST['rama'] ?? '');

  // Obtener correo antiguo para actualizar tbl_usuario
  $old = admin_fetch_one($conn, "SELECT correo FROM tbl_profesor WHERE id_profesor = ? LIMIT 1", "i", $id_edit);
  if (!$old) {
    $mensaje = "No se encontro el usuario indicado.";
    $tipo_mensaje = "warning";
  } else {
    $correo_old = (string) ($old['correo'] ?? '');
    $upd1 = admin_execute($conn, "UPDATE tbl_profesor SET nombre=?, apellidos=?, correo=?, DNI=?, telefono=?, perfil=?, facultad=?, departamento=?, rama=? WHERE id_profesor = ?", "sssssssssi", $nombre, $apellidos, $correo_edit, $dni, $telefono, $perfil_edit, $facultad, $departamento, $rama, $id_edit);
    $upd2 = admin_execute($conn, "UPDATE tbl_usuario SET correo=? WHERE correo=?", "ss", $correo_edit, $correo_old);

    if ($upd1 && $upd2) {
      $mensaje = "Datos del usuario actualizados correctamente.";
      $tipo_mensaje = "success";
    } else {
      $mensaje = "No se pudieron actualizar los datos del usuario.";
      $tipo_mensaje = "danger";
    }
  }
  $_POST['orcid_buscar'] = $_POST['orcid_original'];
}

// --- ASIGNAR A GRUPO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'asignar_grupo') {
  $id_prof = intval($_POST['id_profesor'] ?? 0);
  $id_grupo = intval($_POST['id_grupo'] ?? 0);

  $check_dup = admin_fetch_one($conn, "SELECT id FROM tbl_grupo_profesor WHERE id_grupo = ? AND id_profesor = ? LIMIT 1", "ii", $id_grupo, $id_prof);
  if ($check_dup) {
    $mensaje = "El usuario ya pertenece a ese grupo.";
    $tipo_mensaje = "warning";
  } else {
    if (admin_execute($conn, "INSERT INTO tbl_grupo_profesor (id_grupo, id_profesor) VALUES (?, ?)", "ii", $id_grupo, $id_prof)) {
      $mensaje = "Usuario asignado al grupo correctamente.";
      $tipo_mensaje = "success";
    } else {
      $mensaje = "No se pudo asignar el usuario al grupo.";
      $tipo_mensaje = "danger";
    }
  }
  $_POST['orcid_buscar'] = $_POST['orcid_original'];
}

// --- ELIMINAR DE GRUPO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'quitar_grupo') {
  $id_prof = intval($_POST['id_profesor'] ?? 0);
  $id_grupo = intval($_POST['id_grupo'] ?? 0);
  if (admin_execute($conn, "DELETE FROM tbl_grupo_profesor WHERE id_grupo = ? AND id_profesor = ?", "ii", $id_grupo, $id_prof)) {
    $mensaje = "Usuario eliminado del grupo.";
    $tipo_mensaje = "success";
  } else {
    $mensaje = "No se pudo eliminar el usuario del grupo.";
    $tipo_mensaje = "danger";
  }
  $_POST['orcid_buscar'] = $_POST['orcid_original'];
}

// --- ELIMINAR USUARIO ---
if ($csrf_valido && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
  $id_del = intval($_POST['id_profesor'] ?? 0);
  $correo_del_row = admin_fetch_one($conn, "SELECT correo FROM tbl_profesor WHERE id_profesor = ? LIMIT 1", "i", $id_del);

  if (!$correo_del_row) {
    $mensaje = "No se encontro el usuario indicado.";
    $tipo_mensaje = "warning";
  } else {
    $correo_del = (string) ($correo_del_row['correo'] ?? '');
    $del1 = admin_execute($conn, "DELETE FROM tbl_grupo_profesor WHERE id_profesor = ?", "i", $id_del);
    $del2 = admin_execute($conn, "DELETE FROM tbl_profesor WHERE id_profesor = ?", "i", $id_del);
    $del3 = admin_execute($conn, "DELETE FROM tbl_usuario WHERE correo = ?", "s", $correo_del);

    if ($del1 && $del2 && $del3) {
      $mensaje = "Usuario eliminado del sistema correctamente.";
      $tipo_mensaje = "success";
    } else {
      $mensaje = "No se pudo eliminar el usuario.";
      $tipo_mensaje = "danger";
    }
  }
}

// ==================== BUSCAR USUARIO POR ORCID ====================
if ($csrf_valido && isset($_POST['orcid_buscar']) && !empty($_POST['orcid_buscar'])) {
  $orcid_buscar = trim($_POST['orcid_buscar']);
  $usuario_encontrado = admin_fetch_one($conn, "SELECT * FROM tbl_profesor WHERE ORCID = ? LIMIT 1", "s", $orcid_buscar);
  if ($usuario_encontrado) {
    $id_usuario_encontrado = intval($usuario_encontrado['id_profesor'] ?? 0);

    // Obtener grupos del usuario
    $grupos_usuario = admin_fetch_all($conn, "SELECT g.id_grupo, g.nombre, t.nombre AS tutor_nombre, t.apellidos AS tutor_apellidos FROM tbl_grupo_profesor gp INNER JOIN tbl_grupo g ON gp.id_grupo = g.id_grupo INNER JOIN tbl_profesor t ON g.id_tutor = t.id_profesor WHERE gp.id_profesor = ?", "i", $id_usuario_encontrado);

    // Obtener todos los grupos para el selector de asignar
    $q_todos = mysqli_query($conn, "SELECT g.id_grupo, g.nombre, t.nombre AS tutor_nombre, t.apellidos AS tutor_apellidos FROM tbl_grupo g INNER JOIN tbl_profesor t ON g.id_tutor = t.id_profesor ORDER BY g.nombre ASC");
    while ($q_todos && $rg = mysqli_fetch_assoc($q_todos)) {
      $todos_grupos[] = $rg;
    }
  } else {
    $mensaje = "No se encontró ningún usuario con el ORCID <strong>" . admin_h($orcid_buscar) . "</strong>.";
    $tipo_mensaje = "warning";
  }
}
?>

<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador - Gestionar Usuarios</title>
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
    <div class="formulario-tabla" style="max-width: 900px;">

      <div class="text-center mb-4 w-100">
        <i class="bi bi-people-fill text-white mb-2"
          style="font-size: 3.5rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
        <h2 class="text-white fw-bold">Gestión de Usuarios</h2>
        <p class="text-white-50">Busca, crea, edita, asigna a grupos o elimina usuarios del sistema.</p>
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

      <!-- ===== BUSCADOR POR ORCID ===== -->
      <div class="w-100 p-4 rounded-4 mb-4"
        style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
        <h5 class="text-white fw-bold mb-3"><i class="bi bi-search me-2"></i>Buscar usuario por ORCID</h5>
        <form method="POST" style="padding:0; margin:0;">
          <?php echo admin_csrf_field(); ?>
          <div class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
              <input type="text" name="orcid_buscar" class="form-control" placeholder="0000-0000-0000-0000" required
                pattern="[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}" title="Formato: 0000-0000-0000-0000"
                value="<?php echo admin_h($_POST['orcid_buscar'] ?? ''); ?>"
                style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px;">
            </div>
            <button type="submit"
              class="btn btn-outline-info rounded-pill px-4 py-2 fw-medium d-inline-flex align-items-center gap-1 text-nowrap">
              <i class="bi bi-search"></i> Buscar
            </button>
          </div>
        </form>
      </div>

      <!-- ===== RESULTADO DE BÚSQUEDA ===== -->
      <?php if ($usuario_encontrado): ?>
        <div class="w-100 p-4 rounded-4 mb-4"
          style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">

          <!-- Tabs -->
          <ul class="nav nav-pills mb-4 justify-content-center gap-2" id="userTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active rounded-pill px-3 py-2 text-white" data-bs-toggle="pill"
                data-bs-target="#tab-ver" style="background-color: rgba(255,255,255,0.15);"><i
                  class="bi bi-eye-fill me-1"></i> Ver datos</button></li>
            <li class="nav-item"><button class="nav-link rounded-pill px-3 py-2 text-white" data-bs-toggle="pill"
                data-bs-target="#tab-editar" style="background-color: rgba(255,255,255,0.08);"><i
                  class="bi bi-pencil-fill me-1"></i> Editar</button></li>
            <li class="nav-item"><button class="nav-link rounded-pill px-3 py-2 text-white" data-bs-toggle="pill"
                data-bs-target="#tab-grupos" style="background-color: rgba(255,255,255,0.08);"><i
                  class="bi bi-diagram-3 me-1"></i> Grupos</button></li>
            <li class="nav-item"><button class="nav-link rounded-pill px-3 py-2 text-white" data-bs-toggle="pill"
                data-bs-target="#tab-eliminar" style="background-color: rgba(255,255,255,0.08);"><i
                  class="bi bi-trash me-1"></i> Eliminar</button></li>
          </ul>

          <div class="tab-content">

            <!-- TAB VER DATOS -->
            <div class="tab-pane fade show active" id="tab-ver">
              <h5 class="text-white fw-bold mb-3"><i
                  class="bi bi-person-vcard me-2"></i><?php echo htmlspecialchars($usuario_encontrado['nombre'] . ' ' . $usuario_encontrado['apellidos']); ?>
              </h5>
              <ul class="list-unstyled d-flex flex-column gap-2">
                <?php
                $campos_ver = [
                  ['icon' => 'bi-globe', 'label' => 'ORCID', 'value' => $usuario_encontrado['ORCID']],
                  ['icon' => 'bi-person', 'label' => 'Nombre', 'value' => $usuario_encontrado['nombre']],
                  ['icon' => 'bi-people', 'label' => 'Apellidos', 'value' => $usuario_encontrado['apellidos']],
                  ['icon' => 'bi-card-heading', 'label' => 'DNI', 'value' => $usuario_encontrado['DNI']],
                  ['icon' => 'bi-telephone', 'label' => 'Teléfono', 'value' => $usuario_encontrado['telefono']],
                  ['icon' => 'bi-shield-check', 'label' => 'Perfil', 'value' => $usuario_encontrado['perfil']],
                  ['icon' => 'bi-building', 'label' => 'Facultad', 'value' => $usuario_encontrado['facultad']],
                  ['icon' => 'bi-briefcase', 'label' => 'Departamento', 'value' => $usuario_encontrado['departamento']],
                  ['icon' => 'bi-envelope', 'label' => 'Correo', 'value' => $usuario_encontrado['correo']],
                  ['icon' => 'bi-bookmark', 'label' => 'Rama', 'value' => $usuario_encontrado['rama']],
                ];
                foreach ($campos_ver as $c): ?>
                  <li
                    class="d-flex flex-column bg-light bg-opacity-10 p-2 px-3 rounded-3 border border-light border-opacity-10">
                    <span class="text-white-50 small text-uppercase fw-bold"><i
                        class="bi <?php echo $c['icon']; ?> me-1"></i> <?php echo $c['label']; ?></span>
                    <span
                      class="fs-6 fw-medium text-white"><?php echo empty($c['value']) ? '-' : htmlspecialchars($c['value']); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <!-- TAB EDITAR -->
            <div class="tab-pane fade" id="tab-editar">
              <form method="POST" style="padding:0; margin:0;">
                <?php echo admin_csrf_field(); ?>
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_profesor" value="<?php echo intval($usuario_encontrado['id_profesor']); ?>">
                <input type="hidden" name="orcid_original"
                  value="<?php echo admin_h($usuario_encontrado['ORCID']); ?>">
                <div class="row g-3">
                  <?php
                  $campos_edit = [
                    ['name' => 'nombre', 'label' => 'Nombre', 'value' => $usuario_encontrado['nombre'], 'type' => 'text'],
                    ['name' => 'apellidos', 'label' => 'Apellidos', 'value' => $usuario_encontrado['apellidos'], 'type' => 'text'],
                    ['name' => 'correo', 'label' => 'Correo', 'value' => $usuario_encontrado['correo'], 'type' => 'email'],
                    ['name' => 'dni', 'label' => 'DNI', 'value' => $usuario_encontrado['DNI'], 'type' => 'text'],
                    ['name' => 'telefono', 'label' => 'Teléfono', 'value' => $usuario_encontrado['telefono'], 'type' => 'text'],
                    ['name' => 'facultad', 'label' => 'Facultad', 'value' => $usuario_encontrado['facultad'], 'type' => 'text'],
                    ['name' => 'departamento', 'label' => 'Departamento', 'value' => $usuario_encontrado['departamento'], 'type' => 'text'],
                  ];
                  foreach ($campos_edit as $c): ?>
                    <div class="col-md-6">
                      <label class="form-label text-light small mb-1"><?php echo $c['label']; ?></label>
                      <input type="<?php echo $c['type']; ?>" name="<?php echo $c['name']; ?>" class="form-control"
                        value="<?php echo admin_h($c['value']); ?>"
                        style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                    </div>
                  <?php endforeach; ?>
                  <div class="col-md-6">
                    <label class="form-label text-light small mb-1">Perfil</label>
                    <select name="perfil" class="form-select"
                      style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                      <option value="PROFESOR" <?php echo $usuario_encontrado['perfil'] == 'PROFESOR' ? 'selected' : ''; ?>>
                        Profesor</option>
                      <option value="TUTOR" <?php echo $usuario_encontrado['perfil'] == 'TUTOR' ? 'selected' : ''; ?>>Tutor
                      </option>
                      <option value="ADMIN" <?php echo $usuario_encontrado['perfil'] == 'ADMIN' ? 'selected' : ''; ?>>
                        Administrador</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-light small mb-1">Rama</label>
                    <select name="rama" class="form-select"
                      style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                      <?php
                      $ramas = ['SALUD', 'TECNICA', 'S Y J', 'HUMANIDADES', 'EXPERIMENTALES'];
                      foreach ($ramas as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo $usuario_encontrado['rama'] == $r ? 'selected' : ''; ?>>
                          <?php echo $r; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="d-grid mt-4">
                  <button type="submit" class="btn btn-outline-success rounded-pill fw-bold py-2"><i
                      class="bi bi-check-circle-fill me-1"></i> Guardar cambios</button>
                </div>
              </form>
            </div>

            <!-- TAB GRUPOS -->
            <div class="tab-pane fade" id="tab-grupos">
              <h6 class="text-white fw-bold mb-3"><i class="bi bi-diagram-3 me-1"></i> Grupos asignados</h6>
              <?php if (count($grupos_usuario) > 0): ?>
                <div class="table-responsive mb-4" style="border-radius: 12px;">
                  <table class="table tabla-glass mb-0 text-start">
                    <thead>
                      <tr>
                        <th class="border-top-0 border-end-0 text-white px-3 py-2">Grupo</th>
                        <th class="border-top-0 border-end-0 text-white px-3 py-2">Tutor</th>
                        <th class="border-top-0 border-end-0 text-white px-3 py-2 text-center">Acción</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($grupos_usuario as $gu): ?>
                        <tr>
                          <td class="border-end-0 border-bottom-0 text-white px-3 py-2">
                            <?php echo htmlspecialchars($gu['nombre']); ?>
                          </td>
                          <td class="border-end-0 border-bottom-0 text-white px-3 py-2">
                            <?php echo htmlspecialchars($gu['tutor_nombre'] . ' ' . $gu['tutor_apellidos']); ?>
                          </td>
                          <td class="border-end-0 border-bottom-0 text-center px-3 py-2">
                            <form method="POST" style="display:inline; padding:0; margin:0;">
                              <?php echo admin_csrf_field(); ?>
                              <input type="hidden" name="accion" value="quitar_grupo">
                              <input type="hidden" name="id_profesor"
                                value="<?php echo intval($usuario_encontrado['id_profesor']); ?>">
                              <input type="hidden" name="id_grupo" value="<?php echo intval($gu['id_grupo']); ?>">
                              <input type="hidden" name="orcid_original"
                                value="<?php echo admin_h($usuario_encontrado['ORCID']); ?>">
                              <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill"
                                onclick="return confirm('¿Eliminar de este grupo?')"><i class="bi bi-x-circle"></i></button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-white-50 mb-3">Este usuario no está asignado a ningún grupo.</p>
              <?php endif; ?>

              <!-- Asignar a nuevo grupo -->
              <h6 class="text-white fw-bold mb-2"><i class="bi bi-plus-circle me-1"></i> Asignar a un grupo</h6>
              <?php if (count($todos_grupos) > 0): ?>
                <form method="POST" style="padding:0; margin:0;">
                  <?php echo admin_csrf_field(); ?>
                  <input type="hidden" name="accion" value="asignar_grupo">
                  <input type="hidden" name="id_profesor" value="<?php echo intval($usuario_encontrado['id_profesor']); ?>">
                  <input type="hidden" name="orcid_original"
                    value="<?php echo admin_h($usuario_encontrado['ORCID']); ?>">
                  <div class="d-flex gap-2 align-items-end">
                    <div class="flex-grow-1">
                      <select name="id_grupo" class="form-select" required
                        style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                        <option value="" disabled selected>Seleccionar grupo...</option>
                        <?php foreach ($todos_grupos as $tg): ?>
                          <option value="<?php echo intval($tg['id_grupo']); ?>">
                            <?php echo htmlspecialchars($tg['nombre'] . ' (Tutor: ' . $tg['tutor_nombre'] . ' ' . $tg['tutor_apellidos'] . ')'); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <button type="submit" class="btn btn-outline-success btn-sm rounded-pill px-3 py-2 text-nowrap"><i
                        class="bi bi-plus-circle-fill me-1"></i> Asignar</button>
                  </div>
                </form>
              <?php else: ?>
                <p class="text-white-50">No hay grupos creados aún.</p>
              <?php endif; ?>
            </div>

            <!-- TAB ELIMINAR -->
            <div class="tab-pane fade" id="tab-eliminar">
              <div class="text-center p-4">
                <i class="bi bi-exclamation-triangle-fill text-warning mb-3" style="font-size: 3rem;"></i>
                <h5 class="text-white fw-bold">¿Eliminar a
                  <?php echo htmlspecialchars($usuario_encontrado['nombre'] . ' ' . $usuario_encontrado['apellidos']); ?>?
                </h5>
                <p class="text-white-50">Esta acción eliminará el usuario de <strong>todos los grupos</strong>, de
                  <code>tbl_profesor</code> y de <code>tbl_usuario</code>. No se puede deshacer.
                </p>
                <form method="POST" style="padding:0; margin:0;">
                  <?php echo admin_csrf_field(); ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id_profesor" value="<?php echo intval($usuario_encontrado['id_profesor']); ?>">
                  <button type="submit" class="btn btn-danger rounded-pill px-4 py-2 fw-bold"
                    onclick="return confirm('¿Estás SEGURO de eliminar este usuario del sistema?')">
                    <i class="bi bi-trash-fill me-1"></i> Eliminar usuario permanentemente
                  </button>
                </form>
              </div>
            </div>

          </div>
        </div>
      <?php endif; ?>

      <!-- ===== CREAR USUARIO NUEVO ===== -->
      <div class="w-100 mb-4">
        <button class="btn btn-outline-info w-100 rounded-pill py-2 fw-bold" type="button" data-bs-toggle="collapse"
          data-bs-target="#crearUsuarioCollapse">
          <i class="bi bi-person-plus-fill me-1"></i> Crear nuevo usuario
        </button>
        <div class="collapse mt-3" id="crearUsuarioCollapse">
          <div class="p-4 rounded-4"
            style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
            <form method="POST" style="padding:0; margin:0;">
              <?php echo admin_csrf_field(); ?>
              <input type="hidden" name="accion" value="crear">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label text-light small mb-1">Nombre *</label>
                  <input type="text" name="nombre" class="form-control" required
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-light small mb-1">Apellidos *</label>
                  <input type="text" name="apellidos" class="form-control" required
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-light small mb-1">Correo *</label>
                  <input type="email" name="correo" class="form-control" required
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-light small mb-1">Contraseña *</label>
                  <input type="password" name="password" class="form-control" required
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-light small mb-1">ORCID *</label>
                  <input type="text" name="orcid" class="form-control" placeholder="0000-0000-0000-0000" required
                    pattern="[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}"
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-light small mb-1">DNI</label>
                  <input type="text" name="dni" class="form-control"
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-light small mb-1">Teléfono</label>
                  <input type="text" name="telefono" class="form-control"
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-light small mb-1">Perfil</label>
                  <select name="perfil" class="form-select"
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                    <option value="PROFESOR">Profesor</option>
                    <option value="TUTOR">Tutor</option>
                    <option value="ADMIN">Administrador</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label text-light small mb-1">Facultad</label>
                  <input type="text" name="facultad" class="form-control"
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                </div>
                <div class="col-md-4">
                  <label class="form-label text-light small mb-1">Departamento</label>
                  <input type="text" name="departamento" class="form-control"
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                </div>
                <div class="col-md-6">
                  <label class="form-label text-light small mb-1">Rama</label>
                  <select name="rama" class="form-select"
                    style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white;">
                    <option value="SALUD">SALUD</option>
                    <option value="TECNICA">TÉCNICA</option>
                    <option value="S Y J">S Y J</option>
                    <option value="HUMANIDADES">HUMANIDADES</option>
                    <option value="EXPERIMENTALES">EXPERIMENTALES</option>
                  </select>
                </div>
              </div>
              <div class="d-grid mt-4">
                <button type="submit" class="btn btn-outline-success rounded-pill fw-bold py-2"><i
                    class="bi bi-person-plus-fill me-1"></i> Crear usuario</button>
              </div>
            </form>
          </div>
        </div>
      </div>

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
</body>

</html>
