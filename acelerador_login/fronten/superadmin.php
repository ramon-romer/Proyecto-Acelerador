<?php
/**
 * ACELERADOR - ADMINISTRATOR MODE DASHBOARD (TOTAL RECONSTRUCTION)
 * Arquitecto: Senior Software Engineer & UI/UX Designer
 */

session_start();

// Control de acceso de Élite
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once dirname(__DIR__, 2) . '/acelerador_frontend_db.php';
$conn = acelerador_frontend_db_connect();

/**
 * LÓGICA DE INTERVENCIÓN (AJAX)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['status' => 'error', 'message' => 'Acción no permitida'];

    // 1. GESTIÓN DE USUARIOS
    if ($_POST['action'] === 'edit_user') {
        $id = (int)$_POST['id'];
        $nombre = mysqli_real_escape_string($conn, $_POST['nombre']);
        $apellidos = mysqli_real_escape_string($conn, $_POST['apellidos']);
        $correo = mysqli_real_escape_string($conn, $_POST['correo']);
        $perfil = mysqli_real_escape_string($conn, $_POST['perfil']);
        $dni = mysqli_real_escape_string($conn, $_POST['dni']);
        $orcid = mysqli_real_escape_string($conn, $_POST['orcid']);
        $telefono = mysqli_real_escape_string($conn, $_POST['telefono']);
        $facultad = mysqli_real_escape_string($conn, $_POST['facultad']);
        $departamento = mysqli_real_escape_string($conn, $_POST['departamento']);
        $rama = mysqli_real_escape_string($conn, $_POST['rama']);
        $pass = $_POST['password'];

        $qOld = mysqli_query($conn, "SELECT correo FROM tbl_profesor WHERE id_profesor = $id");
        $oldMail = ($row = mysqli_fetch_assoc($qOld)) ? $row['correo'] : '';

        $updateProf = "UPDATE tbl_profesor SET nombre='$nombre', apellidos='$apellidos', correo='$correo', perfil='$perfil', DNI='$dni', ORCID='$orcid', telefono='$telefono', facultad='$facultad', departamento='$departamento', rama='$rama' ";
        if (!empty($pass)) {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $updateProf .= ", password='$hashed' ";
        }
        $updateProf .= " WHERE id_profesor = $id";
        
        if(mysqli_query($conn, $updateProf)) {
            if ($oldMail) {
                $updateUser = "UPDATE tbl_usuario SET correo='$correo' ";
                if (!empty($pass)) {
                    $updateUser .= ", password='$hashed' ";
                }
                $updateUser .= " WHERE correo = '$oldMail'";
                mysqli_query($conn, $updateUser);
            }
            $response = ['status' => 'ok', 'message' => 'Usuario actualizado correctamente'];
        } else {
            $response = ['status' => 'error', 'message' => mysqli_error($conn)];
        }
    }

    if ($_POST['action'] === 'delete_user') {
        $id = (int)$_POST['id'];
        $q = mysqli_query($conn, "SELECT correo, perfil FROM tbl_profesor WHERE id_profesor = $id");
        if ($row = mysqli_fetch_assoc($q)) {
            $email = $row['correo'];
            $perfil = $row['perfil'];

            // LÓGICA DE BORRADO EN CASCADA (SÓLO SI ES TUTOR)
            if ($perfil === 'TUTOR') {
                // 1. Borrar tareas creadas por el tutor
                mysqli_query($conn, "DELETE FROM tbl_tarea_entrega WHERE id_tutor = $id");
                
                // 2. Borrar asignaciones de alumnos a los grupos de este tutor
                mysqli_query($conn, "DELETE FROM tbl_grupo_profesor WHERE id_grupo IN (SELECT id_grupo FROM tbl_grupo WHERE id_tutor = $id)");
                
                // 3. Borrar los grupos del tutor
                mysqli_query($conn, "DELETE FROM tbl_grupo WHERE id_tutor = $id");
            }

            // Borrado del perfil y la cuenta de acceso
            mysqli_query($conn, "DELETE FROM tbl_profesor WHERE id_profesor = $id");
            mysqli_query($conn, "DELETE FROM tbl_usuario WHERE correo = '$email'");
            
            $response = ['status' => 'ok', 'message' => 'Usuario y todos sus registros vinculados eliminados permanentemente'];
        }
    }

    // 2. GESTIÓN DE TAREAS (DINÁMICA)
    if ($_POST['action'] === 'get_user_tasks') {
        $prof_id = (int)$_POST['prof_id'];
        $q = mysqli_query($conn, "SELECT * FROM tbl_tarea_entrega WHERE id_profesor = $prof_id ORDER BY fecha_creacion DESC");
        $tasks = [];
        while($t = mysqli_fetch_assoc($q)) {
            $tasks[] = $t;
        }
        $response = ['status' => 'ok', 'tasks' => $tasks];
    }

    if ($_POST['action'] === 'intervene_task') {
        $id = (int)$_POST['id'];
        $fechas = mysqli_real_escape_string($conn, $_POST['fechas']);
        $reales = mysqli_real_escape_string($conn, $_POST['reales']);
        mysqli_query($conn, "UPDATE tbl_tarea_entrega SET fechas_entregas = '$fechas', fechas_reales_entregas = '$reales' WHERE id = $id");
        $response = ['status' => 'ok', 'message' => 'Tarea intervenida con éxito'];
    }

    if ($_POST['action'] === 'reset_task') {
        $id = (int)$_POST['id'];
        mysqli_query($conn, "UPDATE tbl_tarea_entrega SET fechas_reales_entregas = NULL WHERE id = $id");
        $response = ['status' => 'ok', 'message' => 'Plazos reseteados'];
    }

    if ($_POST['action'] === 'delete_task') {
        $id = (int)$_POST['id'];
        if (mysqli_query($conn, "DELETE FROM tbl_tarea_entrega WHERE id = $id")) {
            $response = ['status' => 'ok', 'message' => 'Tarea eliminada permanentemente'];
        } else {
            $response = ['status' => 'error', 'message' => 'Error al eliminar la tarea: ' . mysqli_error($conn)];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Carga de Usuarios
$users = mysqli_query($conn, "SELECT id_profesor AS id, nombre, apellidos, correo, perfil, DNI, ORCID, telefono, facultad, departamento, rama FROM tbl_profesor ORDER BY id_profesor DESC");
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISTEMA ADMINISTRATOR MODE - Acelerador</title>
    
    <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../acelerador_panel/fronten/css/notifications.css">
    <link rel="stylesheet" href="css/superadmin.css?v=<?= time() ?>">
</head>
<body class="superadmin-body">

    <div id="toast-container"></div>

    <header>
        <div class="contenedorimg">
            <div class="d-flex align-items-center">
                <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" style="height:45px;"/>
                <div class="ms-3 border-start ps-3 border-opacity-25 border-light">
                    <h4 class="text-white fw-800 mb-0">ADMINISTRATOR MODE</h4>
                    <small class="text-white-50 text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 2px;">Gestión Dinámica de Usuarios</small>
                </div>
            </div>
            <div class="d-none d-md-block">
                <span class="badge bg-danger rounded-pill px-3 py-2">ACCESO TOTAL ACTIVADO</span>
            </div>
        </div>
    </header>

    <main class="container p-4 mt-2">
        <div class="row">
            <div class="col-12">
                <div class="admin-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="mb-0"><i class="bi bi-people-fill me-3 text-primary"></i>Panel de Control</h3>
                        <span class="badge bg-white bg-opacity-10 text-white-50"><?= mysqli_num_rows($users) ?> Registros</span>
                    </div>
                    
                    <div class="table-responsive custom-scrollbar" style="max-height: 700px;">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Completo</th>
                                    <th>Correo Electrónico</th>
                                    <th>Rol</th>
                                    <th class="text-end">Herramientas de Gestión</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($u = mysqli_fetch_assoc($users)): 
                                    $jsData = htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr id="u-<?= $u['id'] ?>">
                                    <td class="fw-bold text-white-50"><?= $u['id'] ?></td>
                                    <td class="fw-medium"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                                    <td class="text-white-50"><?= htmlspecialchars($u['correo']) ?></td>
                                    <td>
                                        <span class="badge <?= $u['perfil'] === 'ADMIN' ? 'bg-danger' : ($u['perfil'] === 'TUTOR' ? 'bg-primary' : 'bg-info') ?> bg-opacity-25 text-white">
                                            <?= $u['perfil'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <?php if($u['perfil'] === 'PROFESOR'): ?>
                                            <button class="btn btn-sm btn-warning rounded-pill px-3 fw-bold d-flex align-items-center gap-1" onclick="loadUserTasks(<?= $u['id'] ?>, '<?= addslashes($u['nombre']) ?>')">
                                                <i class="bi bi-list-task"></i> Tareas
                                            </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn-action btn-edit" onclick="openEditUser(<?= $jsData ?>)" title="Editar perfil">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button class="btn-action btn-delete" onclick="deleteUser(<?= $u['id'] ?>)" title="Eliminar cuenta">
                                                <i class="bi bi-trash3-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-center mt-5 mb-4">
            <a href="logout.php" class="btn btn-outline-danger px-5 py-3 rounded-pill fw-bold shadow-lg border-2" style="background: rgba(248, 113, 113, 0.05);">
                <i class="bi bi-box-arrow-right me-2"></i>SALIR DEL ADMINISTRATOR MODE
            </a>
        </div>
    </main>

    <!-- MODAL: EDITAR USUARIO COMPLETO -->
    <div class="modal fade" id="modalEditUser" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="formEditUser">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-white"><i class="bi bi-person-gear me-2"></i>Edición Maestro de Perfil</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="id" id="edit_user_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small fw-bold">NOMBRE</label>
                                <input type="text" name="nombre" id="edit_user_nombre" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small fw-bold">APELLIDOS</label>
                                <input type="text" name="apellidos" id="edit_user_apellidos" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small fw-bold">CORREO ELECTRÓNICO</label>
                                <input type="email" name="correo" id="edit_user_correo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small fw-bold">DNI</label>
                                <input type="text" name="dni" id="edit_user_dni" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small fw-bold">ORCID</label>
                                <input type="text" name="orcid" id="edit_user_orcid" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small fw-bold">TELÉFONO</label>
                                <input type="text" name="telefono" id="edit_user_telefono" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small fw-bold">FACULTAD</label>
                                <input type="text" name="facultad" id="edit_user_facultad" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small fw-bold">DEPARTAMENTO</label>
                                <input type="text" name="departamento" id="edit_user_departamento" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white-50 small fw-bold">ROL / PERFIL</label>
                                <select name="perfil" id="edit_user_perfil" class="form-control">
                                    <option value="PROFESOR">PROFESOR</option>
                                    <option value="TUTOR">TUTOR</option>
                                    <option value="ADMIN">ADMINISTRADOR</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white-50 small fw-bold">RAMA</label>
                                <select name="rama" id="edit_user_rama" class="form-control">
                                    <option value="SALUD">SALUD</option>
                                    <option value="TECNICA">TÉCNICA</option>
                                    <option value="CSYJ">CSYJ</option>
                                    <option value="HUMANIDADES">HUMANIDADES</option>
                                    <option value="EXPERIMENTALES">EXPERIMENTALES</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white-50 small fw-bold">NUEVA PASSWORD</label>
                                <input type="password" name="password" id="edit_user_pass" class="form-control" placeholder="Opcional">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-light rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Guardar Cambios Globales</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: LISTADO DE TAREAS DEL PROFESOR -->
    <div class="modal fade" id="modalUserTasks" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-journal-text me-2"></i>Tareas de <span id="task_prof_name"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive custom-scrollbar" style="max-height: 500px;">
                        <table class="table table-hover align-middle mb-0" id="tableTasksList">
                            <thead class="bg-dark bg-opacity-50">
                                <tr>
                                    <th class="ps-4">Título Tarea</th>
                                    <th>Hitos</th>
                                    <th class="text-end pe-4">Intervenir</th>
                                </tr>
                            </thead>
                            <tbody id="user_tasks_body">
                                <!-- Se llena vía AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: INTERVENCIÓN ESPECÍFICA -->
    <div class="modal fade" id="modalIntervention" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: 1px solid var(--accent-gold);">
                <div class="modal-header" style="border-bottom: 1px solid rgba(251, 191, 36, 0.2);">
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-sliders me-2 text-warning"></i>Modificar Hitos</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="inter_task_id">
                    <div id="hitos_container" class="custom-scrollbar" style="max-height: 400px; overflow-y: auto;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(251, 191, 36, 0.2);">
                    <button type="button" class="btn btn-outline-light rounded-pill px-4" data-bs-dismiss="modal">Volver</button>
                    <button type="button" id="btnSaveIntervention" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: CONFIRMACIÓN PERSONALIZADA (REEMPLAZO DE confirm()) -->
    <div id="custom-confirm-container" style="display: none;">
        <div class="custom-confirm-overlay">
            <div class="custom-confirm-box text-center">
                <i class="bi bi-exclamation-octagon-fill text-danger fs-1 mb-3 d-block"></i>
                <h4 class="fw-bold text-white mb-2" id="confirm-title">¿Estás seguro?</h4>
                <p class="text-white-50 mb-4" id="confirm-msg">Esta acción no se puede deshacer.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button class="btn btn-outline-light rounded-pill px-4 fw-bold" id="confirm-cancel">CANCELAR</button>
                    <button class="btn btn-danger rounded-pill px-4 fw-bold" id="confirm-ok">SÍ, ELIMINAR</button>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="piepag">
            <p>Nivel de Acceso: <strong>PROPIETARIO (ADMINISTRATOR MODE)</strong> | CEU Universidad Fernando III &copy; <?= date('Y') ?></p>
            <p class="small text-white-25 mt-1">Refinado con Estándares de Ingeniería de Software de Élite</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/superadmin.js?v=<?= time() ?>"></script>
</body>
</html>