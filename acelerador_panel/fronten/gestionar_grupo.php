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

    // Comprobar límite de 3 profesores por grupo
    $query_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM tbl_grupo_profesor WHERE id_grupo = $id_grupo");
    $total_profes = 0;
    if ($query_count) {
      $total_profes = (int)mysqli_fetch_assoc($query_count)['total'];
    }

    if ($total_profes >= 3) {
      $mensaje = 'numero maximo de docentes por grupo alcanzado';
      $tipo_mensaje = 'warning';
    } else {
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
          // Insertar en grupo
          mysqli_query($conn, "INSERT INTO tbl_grupo_profesor (id_grupo, id_profesor) VALUES ($id_grupo, $id_prof)");

          // Insertar tarea de entrega asociada (si se proporcionaron campos de tarea)
          $titulo_tarea = isset($_POST['titulo_tarea']) ? trim($_POST['titulo_tarea']) : '';
          $desc_tarea   = isset($_POST['descripcion_tarea']) ? trim($_POST['descripcion_tarea']) : '';
          $num_entregas = isset($_POST['num_entregas']) ? max(1, intval($_POST['num_entregas'])) : 1;
          $fechas_arr   = isset($_POST['fecha_entrega']) && is_array($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : [];

          // Filtrar fechas vacías
          $fechas_arr = array_values(array_filter($fechas_arr, function($f) { return !empty(trim($f)); }));

          if (!empty($titulo_tarea) && count($fechas_arr) > 0) {
            $titulo_esc = mysqli_real_escape_string($conn, $titulo_tarea);
            $desc_esc   = mysqli_real_escape_string($conn, $desc_tarea);
            $fechas_json = mysqli_real_escape_string($conn, json_encode($fechas_arr, JSON_UNESCAPED_UNICODE));

            try {
              mysqli_query($conn,
                "INSERT INTO tbl_tarea_entrega (id_grupo, id_profesor, id_tutor, titulo_tarea, descripcion_tarea, num_entregas, fechas_entregas)
                 VALUES ($id_grupo, $id_prof, $id_tutor, '$titulo_esc', '$desc_esc', $num_entregas, '$fechas_json')"
              );
            } catch (Exception $e) {
              // La tabla puede no existir aún — no bloquear
            }
          }

          $mensaje = 'Profesor <strong>' . htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']) . '</strong> añadido correctamente.';
          $tipo_mensaje = 'success';
        }
      } else {
        $mensaje = 'No se encontró ningún profesor con ORCID <strong>' . htmlspecialchars($orcid) . '</strong>.';
        $tipo_mensaje = 'danger';
      }
    }
  }

  // ELIMINAR profesor del grupo
  if ($_POST['accion'] == 'eliminar' && !empty($_POST['id_profesor'])) {
    $id_prof_eliminar = intval($_POST['id_profesor']);

    // Asegurar que existe la tabla de notificaciones persistentes
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_notificacion_pendiente (
      id INT AUTO_INCREMENT PRIMARY KEY,
      id_profesor INT NOT NULL,
      mensaje TEXT NOT NULL,
      fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Borrar las tareas asignadas a este profesor en este grupo
    mysqli_query($conn, "DELETE FROM tbl_tarea_entrega WHERE id_profesor = $id_prof_eliminar AND id_grupo = $id_grupo");

    // Quitar al profesor del grupo
    mysqli_query($conn, "DELETE FROM tbl_grupo_profesor WHERE id_grupo = $id_grupo AND id_profesor = $id_prof_eliminar");

    // Insertar notificación persistente para el profesor eliminado
    $msg_notif = mysqli_real_escape_string($conn, "Se le ha eliminado del grupo actual, se le añadirá a otro a la mayor brevedad posible.");
    mysqli_query($conn, "INSERT INTO tbl_notificacion_pendiente (id_profesor, mensaje) VALUES ($id_prof_eliminar, '$msg_notif')");

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
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <style>
    .popover-body { white-space: pre-line; }
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
        <img src="img/AcademyAccelerator_def.png" id="academy" alt="academy" />
      </div>
    </div>
  </header>

  <main>
    <div class="panel-wrapper">
      <div class="dashboard">
        <div class="formulario-tabla w-100" style="max-width: 950px; margin: 0 auto;">
          <div class="text-center mb-4 w-100">
            <i class="bi bi-gear-fill text-white mb-2"
              style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
            <h2 class="text-white fw-bold">Gestionar Grupo</h2>
            <h4 class="text-white-50"><?php echo htmlspecialchars($nombre_grupo); ?></h4>
            <hr class="w-100 border-light opacity-25 mt-3 mb-4">
          </div>

          <!-- Mensaje de feedback -->
          <?php if ($mensaje): ?>
            <script>
              document.addEventListener('DOMContentLoaded', () => {
                showNotification("<?php echo $mensaje; ?>", "<?php echo ($tipo_mensaje === 'success') ? 'success' : ($tipo_mensaje === 'danger' ? 'danger' : 'warning'); ?>");
              });
            </script>
          <?php endif; ?>

          <!-- Formulario para añadir profesor por ORCID + asignación de tarea -->
          <div class="mx-auto mb-4 p-4 rounded-4"
            style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); max-width: 600px; width: 100%;">
            <h6 class="text-white mb-2 fw-bold d-inline-flex align-items-center gap-1"><i
                class="bi bi-person-plus-fill"></i> Añadir profesor al grupo</h6>
            <form method="POST" id="formAnadirProfesor" style="padding: 0; margin: 0;">
              <input type="hidden" name="accion" value="añadir">

              <!-- ORCID -->
              <div class="mb-2" style="display: flex; flex-direction: column; width: 100%; gap: 0; margin: 0;">
                <label class="form-label text-light small mb-1 w-100 text-start" style="font-size: 0.85rem;">ORCID del
                  profesor *</label>
                <input type="text" name="orcid" id="inputOrcid" class="form-control input-orcid w-100" placeholder="0000-0000-0000-0000"
                  required pattern="[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}" title="Formato: 0000-0000-0000-0000"
                  style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px; font-size: 1rem;">
              </div>

              <!-- Separador visual -->
              <hr style="border-color: rgba(255,255,255,0.15); margin: 16px 0;">
              <h6 class="text-white-50 small fw-bold text-uppercase mb-3"><i class="bi bi-clipboard-check me-1"></i> Asignación de tarea</h6>

              <!-- Título de la tarea -->
              <div class="mb-3" style="display:flex;flex-direction:column;width:100%;gap:0;margin:0;">
                <label class="form-label text-light small mb-1 w-100 text-start" style="font-size: 0.85rem;">Título de la tarea *</label>
                <input type="text" name="titulo_tarea" required maxlength="255" placeholder="Ej: Preparación expediente ANECA"
                  class="form-control w-100"
                  style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px; font-size: 1rem;">
              </div>

              <!-- Descripción -->
              <div class="mb-3" style="display:flex;flex-direction:column;width:100%;gap:0;margin:0;">
                <label class="form-label text-light small mb-1 w-100 text-start" style="font-size: 0.85rem;">Descripción</label>
                <textarea name="descripcion_tarea" rows="3" placeholder="Descripción opcional de la tarea..."
                  class="form-control w-100"
                  style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px; font-size: 0.95rem; resize: vertical;"></textarea>
              </div>

              <!-- Cantidad de entregas -->
              <div class="mb-3" style="display:flex;flex-direction:column;width:100%;gap:0;margin:0;">
                <label class="form-label text-light small mb-1 w-100 text-start" style="font-size: 0.85rem;">Cantidad de entregas *</label>
                <input type="number" name="num_entregas" id="inputNumEntregas" required min="1" max="20" value="1"
                  class="form-control w-100"
                  style="background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px; font-size: 1rem;"
                  oninput="generarCamposFechas()">
              </div>

              <!-- Contenedor dinámico para campos de fecha -->
              <div id="contenedorFechas"></div>

              <div class="d-grid mt-3">
                <button type="submit"
                  class="btn btn-outline-success rounded-pill d-inline-flex align-items-center justify-content-center gap-2 py-2 fw-medium">
                  <i class="bi bi-plus-circle-fill"></i> Añadir profesor con tarea
                </button>
              </div>
            </form>
          </div>

          <!-- Tabla de profesores del grupo -->
          <div class="w-100 mb-4 p-4 rounded-4"
            style="background-color: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);">
            <h6 class="text-white mb-2 fw-bold d-inline-flex align-items-center gap-1"><i class="bi bi-list-ul"></i>
              Profesores en este grupo</h6>
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
                        <td class="border-end-0">
                          <?php echo empty($p['departamento']) ? '-' : htmlspecialchars($p['departamento']); ?>
                        </td>
                        <td class="border-end-0"><?php echo empty($p['correo']) ? '-' : htmlspecialchars($p['correo']); ?></td>
                        <td class="border-end-0 text-center">
                          <form method="POST" style="display:inline;"
                            onsubmit="event.preventDefault(); customConfirm('¿Eliminar a <?php echo htmlspecialchars(addslashes($p['nombre'] . ' ' . $p['apellidos'])); ?> de este grupo?', () => this.submit());">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id_profesor" value="<?php echo $p['id_profesor']; ?>">
                            <button type="submit"
                              class="btn btn-outline-danger btn-sm rounded-pill d-inline-flex align-items-center gap-1">
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
              <div class="w-100 text-center text-white-50 p-4"
                style="background-color: rgba(255,255,255,0.05); border-radius: 15px;">
                Este grupo aún no tiene profesores asignados.
              </div>
            <?php endif; ?>
          </div>

          <div class="d-flex justify-content-center w-100 mt-2">
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

    function generarCamposFechas() {
      const n = Math.max(1, Math.min(20, parseInt(document.getElementById('inputNumEntregas').value) || 1));
      const contenedor = document.getElementById('contenedorFechas');
      if (!contenedor) return;
      contenedor.innerHTML = '';

      for (let i = 1; i <= n; i++) {
        const wrapper = document.createElement('div');
        wrapper.className = 'mb-3';
        wrapper.style.cssText = 'display:flex;flex-direction:column;width:100%;gap:0;margin:0;margin-bottom:12px;';

        const label = document.createElement('label');
        label.className = 'form-label text-light small mb-1 w-100 text-start';
        label.style.fontSize = '0.85rem';
        label.innerHTML = '<i class="bi bi-calendar-event me-1"></i> Fecha límite — Entrega ' + i + ' *';

        const input = document.createElement('input');
        input.type = 'date';
        input.name = 'fecha_entrega[]';
        input.required = true;
        input.className = 'form-control w-100';
        input.style.cssText = 'background-color: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.35); color: white; padding: 10px 15px; font-size: 1rem;';

        wrapper.appendChild(label);
        wrapper.appendChild(input);
        contenedor.appendChild(wrapper);
      }
    }

    // Generar el primer campo de fecha al cargar
    document.addEventListener('DOMContentLoaded', generarCamposFechas);
  </script>

  <?php include('chatbot.php'); ?>

</body>

</html>