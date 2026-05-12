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

// Asegurar tabla (por si acaso no hay ningún borrado previo)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_info_usuario_eliminado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_profesor_original INT NOT NULL,
    correo VARCHAR(255) NOT NULL,
    datos_completos LONGTEXT NOT NULL,
    fecha_eliminacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$mensaje = "";
$tipo_mensaje = "";

// --- LÓGICA DE RESTAURACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'restaurar') {
    $id_archivo = (int)$_POST['id_archivo'];
    
    // Obtener el registro archivado
    $q_arc = mysqli_query($conn, "SELECT * FROM tbl_info_usuario_eliminado WHERE id = $id_archivo");
    if ($q_arc && mysqli_num_rows($q_arc) > 0) {
        $arc = mysqli_fetch_assoc($q_arc);
        $json_data = json_decode($arc['datos_completos'], true);
        
        $correo_restaurar = $arc['correo'];
        
        // 1. Verificar si el correo ya está en uso actualmente
        $q_chk = mysqli_query($conn, "SELECT id_profesor FROM tbl_profesor WHERE correo = '$correo_restaurar'");
        if (mysqli_num_rows($q_chk) > 0) {
            $mensaje = "Error: El correo $correo_restaurar ya está en uso por un usuario activo.";
            $tipo_mensaje = "danger";
        } else {
            // Iniciar Restauración
            mysqli_autocommit($conn, false);
            $error = false;
            
            try {
                // Restaurar tbl_profesor
                if (isset($json_data['tbl_profesor']) && !empty($json_data['tbl_profesor'])) {
                    $p = $json_data['tbl_profesor'];
                    $id_p = (int)$p['id_profesor'];
                    $nombre = mysqli_real_escape_string($conn, $p['nombre']);
                    $apellidos = mysqli_real_escape_string($conn, $p['apellidos']);
                    $dni = mysqli_real_escape_string($conn, $p['DNI']);
                    $orcid = mysqli_real_escape_string($conn, $p['ORCID']);
                    $pass = mysqli_real_escape_string($conn, $p['password']);
                    $correo = mysqli_real_escape_string($conn, $p['correo']);
                    $departamento = mysqli_real_escape_string($conn, $p['departamento']);
                    $telefono = mysqli_real_escape_string($conn, $p['telefono']);
                    $facultad = mysqli_real_escape_string($conn, $p['facultad']);
                    $perfil_usu = mysqli_real_escape_string($conn, $p['perfil']);
                    $rama = mysqli_real_escape_string($conn, $p['rama']);
                    
                    $sql_p = "INSERT IGNORE INTO tbl_profesor (id_profesor, nombre, apellidos, DNI, ORCID, password, correo, departamento, telefono, facultad, perfil, rama) 
                              VALUES ($id_p, '$nombre', '$apellidos', '$dni', '$orcid', '$pass', '$correo', '$departamento', '$telefono', '$facultad', '$perfil_usu', '$rama')";
                    if(!mysqli_query($conn, $sql_p)) throw new Exception("Error al restaurar tbl_profesor");
                }
                
                // Restaurar tbl_usuario
                if (isset($json_data['tbl_usuario']) && !empty($json_data['tbl_usuario'])) {
                    $u = $json_data['tbl_usuario'];
                    $correo_u = mysqli_real_escape_string($conn, $u['correo']);
                    $pass_u = mysqli_real_escape_string($conn, $u['password']);
                    $sql_u = "INSERT IGNORE INTO tbl_usuario (correo, password) VALUES ('$correo_u', '$pass_u')";
                    if(!mysqli_query($conn, $sql_u)) throw new Exception("Error al restaurar tbl_usuario");
                }
                
                // Restaurar Grupos Creados (Si era tutor)
                if (isset($json_data['tbl_grupo_creados'])) {
                    foreach ($json_data['tbl_grupo_creados'] as $g) {
                        $id_g = (int)$g['id_grupo'];
                        $id_t = (int)$g['id_tutor'];
                        $n_g = mysqli_real_escape_string($conn, $g['nombre']);
                        $sql_g = "INSERT IGNORE INTO tbl_grupo (id_grupo, id_tutor, nombre) VALUES ($id_g, $id_t, '$n_g')";
                        mysqli_query($conn, $sql_g);
                    }
                }
                
                // Restaurar Asignaciones a grupos (tbl_grupo_profesor)
                if (isset($json_data['tbl_grupo_profesor'])) {
                    foreach ($json_data['tbl_grupo_profesor'] as $gp) {
                        $id_gp = (int)$gp['id'];
                        $id_grupo = (int)$gp['id_grupo'];
                        $id_prof = (int)$gp['id_profesor'];
                        $sql_gp = "INSERT IGNORE INTO tbl_grupo_profesor (id, id_grupo, id_profesor) VALUES ($id_gp, $id_grupo, $id_prof)";
                        mysqli_query($conn, $sql_gp);
                    }
                }
                
                // Confirmar transacción
                mysqli_commit($conn);
                
                // Eliminar del archivo
                mysqli_query($conn, "DELETE FROM tbl_info_usuario_eliminado WHERE id = $id_archivo");
                
                $mensaje = "Usuario restaurado correctamente con todo su historial.";
                $tipo_mensaje = "success";
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $mensaje = "Ocurrió un error al restaurar: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
            mysqli_autocommit($conn, true);
        }
    }
}

// --- BORRAR DEFINITIVAMENTE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'purgar') {
    $id_archivo = (int)$_POST['id_archivo'];
    mysqli_query($conn, "DELETE FROM tbl_info_usuario_eliminado WHERE id = $id_archivo");
    $mensaje = "Archivo de usuario eliminado permanentemente.";
    $tipo_mensaje = "info";
}

// Obtener la lista de usuarios archivados
$q_archivados = mysqli_query($conn, "SELECT * FROM tbl_info_usuario_eliminado ORDER BY fecha_eliminacion DESC");
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Usuarios Eliminados - Acelerador</title>
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <link rel="stylesheet" href="css/notifications.css">
</head>

<body>
  <header>
    <div class="contenedorimg">
      <div class="imagen">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU" style="height:50px;" />
      </div>
      <div class="imagen">
        <img src="img/AcademyAccelerator_def.png" alt="academy" />
      </div>
    </div>
  </header>

  <main>
    <div class="panel-wrapper">
      <div class="dashboard p-4">
        
        <div class="d-flex align-items-center mb-4 pb-3 border-bottom border-light border-opacity-25">
          <a href="panel_admin.php" class="btn btn-outline-light rounded-pill px-3 py-2 me-3"><i class="bi bi-arrow-left"></i> Volver</a>
          <i class="bi bi-person-x-fill text-danger me-3" style="font-size: 2.5rem;"></i>
          <div>
            <h3 class="text-white fw-bold mb-0">Usuarios Eliminados (Papelera de Reciclaje)</h3>
            <p class="text-white-50 small mb-0">Aquí puedes restaurar cuentas eliminadas con toda su información intacta.</p>
          </div>
        </div>

        <?php if (!empty($mensaje)): ?>
          <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="table-responsive rounded-4 overflow-hidden" style="border: 1px solid rgba(255,255,255,0.15);">
          <table class="table table-dark table-hover mb-0 align-middle">
            <thead style="background-color: rgba(255,255,255,0.05);">
              <tr>
                <th class="py-3 px-4 text-white-50"># ID Original</th>
                <th class="py-3 px-4 text-white-50">Correo / Identificador</th>
                <th class="py-3 px-4 text-white-50">Rol Original</th>
                <th class="py-3 px-4 text-white-50">Fecha de Eliminación</th>
                <th class="py-3 px-4 text-end text-white-50">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($q_archivados) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($q_archivados)): 
                    $json = json_decode($row['datos_completos'], true);
                    $rol = $json['tbl_profesor']['perfil'] ?? 'DESCONOCIDO';
                ?>
                  <tr style="background-color: rgba(255,255,255,0.02);">
                    <td class="px-4 fw-bold text-white-50"><?= htmlspecialchars($row['id_profesor_original']) ?></td>
                    <td class="px-4 text-white"><?= htmlspecialchars($row['correo']) ?></td>
                    <td class="px-4">
                        <span class="badge bg-secondary bg-opacity-25 text-white-50"><?= htmlspecialchars($rol) ?></span>
                    </td>
                    <td class="px-4 text-white-50"><?= htmlspecialchars($row['fecha_eliminacion']) ?></td>
                    <td class="px-4 text-end">
                        <form method="POST" class="d-inline-block m-0" onsubmit="event.preventDefault(); customConfirm('¿Estás seguro de que quieres RESTAURAR este usuario? Recuperará todo su historial.', () => this.submit());">
                            <input type="hidden" name="accion" value="restaurar">
                            <input type="hidden" name="id_archivo" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm"><i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar</button>
                        </form>
                        <form method="POST" class="d-inline-block m-0" onsubmit="event.preventDefault(); customConfirm('¿Estás seguro? Esta acción destruirá la copia de seguridad de este usuario PARA SIEMPRE.', () => this.submit());">
                            <input type="hidden" name="accion" value="purgar">
                            <input type="hidden" name="id_archivo" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm ms-1"><i class="bi bi-trash-fill"></i> Purgar</button>
                        </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center py-5 text-white-50">
                    <i class="bi bi-inbox-fill" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0">No hay ningún usuario en la papelera.</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="js/notifications.js"></script>
</body>

</html>
