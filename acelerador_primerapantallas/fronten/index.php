<?php
session_start();
require_once dirname(__DIR__, 2) . '/acelerador_login/fronten/lib/session_security.php';
include("config.php");

acelerador_apply_protected_page_session_guards();

// Notificación de error de sesión (con sistema propio)
if (!isset($_SESSION['nombredelusuario'])) {
  echo "<link rel='stylesheet' href='../../acelerador_panel/fronten/css/notifications.css'>";
  echo "<script src='../../acelerador_panel/fronten/js/notifications.js'></script>";
  echo "<script>document.addEventListener('DOMContentLoaded', () => { showNotification('Debes iniciar sesión primero', 'warning'); setTimeout(() => window.location='../../acelerador_login/fronten/login.php', 2000); });</script>";
  exit();
}

$correo = $_SESSION['nombredelusuario'];

// ===============================
// CARGAR DATOS DEL PROFESOR
// ===============================
$query = mysqli_query($conn, "SELECT * FROM tbl_profesor WHERE correo='$correo' LIMIT 1");
$fila = mysqli_fetch_assoc($query);

if (!$fila) {
  echo "<link rel='stylesheet' href='../../acelerador_panel/fronten/css/notifications.css'>";
  echo "<script src='../../acelerador_panel/fronten/js/notifications.js'></script>";
  echo "<script>document.addEventListener('DOMContentLoaded', () => { showNotification('Error: no se encontró el profesor asociado a esta cuenta', 'danger'); });</script>";
  exit();
}

// ========================================================
// ================ GUARDAR CAMBIOS =======================
// ========================================================
if (isset($_POST["guardar"])) {

  // Sanitizar entradas
  function limpiar($conn, $campo)
  {
    return mysqli_real_escape_string($conn, trim($_POST[$campo] ?? ''));
  }

  $nombre = limpiar($conn, "nombre");
  $apellidos = limpiar($conn, "apellidos");
  $dni = limpiar($conn, "dni");
  $departamento = limpiar($conn, "departamento");
  $orcid = limpiar($conn, "orcid");
  $telefono = limpiar($conn, "telefono");
  $perfil = $fila['perfil']; 
  $facultad = limpiar($conn, "facultad");
  $rama = limpiar($conn, "rama");
  $correoNuevo = limpiar($conn, "correoNuevo");
  $pass = limpiar($conn, "password");

  $errores = [];

  $ramaUp = strtoupper($rama);
  $ramaUp = preg_replace('/\s+/', ' ', $ramaUp);
  $mapRama = [
    'SALUD' => 'SALUD',
    'TECNICAS' => 'TECNICA',
    'TECNICA' => 'TECNICA',
    'CSYJ' => 'CSYJ',
    'HUMANIDADES' => 'HUMANIDADES',
    'EXPERIMENTALES' => 'EXPERIMENTALES',
  ];
  $rama = $mapRama[$ramaUp] ?? $ramaUp;

  $ramasValidas = ["SALUD", "TECNICA", "CSYJ", "HUMANIDADES", "EXPERIMENTALES"];
  if (!in_array($rama, $ramasValidas, true)) {
    $errores[] = "La rama seleccionada no es válida.";
  }

  if (!preg_match('/^[0-9]{8}[A-Za-z]$/', $dni)) $errores[] = "El DNI debe seguir el formato 12345678X";
  if (!preg_match('/^[0-9]{9}$/', $telefono)) $errores[] = "El teléfono debe tener 9 dígitos";
  if (!preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/', $orcid)) $errores[] = "El ORCID debe tener el formato 0000-0000-0000-0000";

  if (!empty($pass)) {
    if (strlen($pass) < 8) $errores[] = "La contraseña debe tener mínimo 8 caracteres";
    if (!preg_match('/[A-Z]/', $pass)) $errores[] = "La contraseña debe incluir al menos una mayúscula";
    if (!preg_match('/[\W]/', $pass)) $errores[] = "La contraseña debe incluir al menos un carácter especial";
    if (!preg_match('/[0-9]/', $pass)) $errores[] = "La contraseña debe incluir al menos un número";
  }

  if (!empty($errores)) {
    echo "<script>document.addEventListener('DOMContentLoaded', () => {";
    foreach ($errores as $e) {
      $e = addslashes($e);
      echo "showNotification('$e', 'danger');";
    }
    echo "});</script>";
  } else {
    $setPass = "";
    if (!empty($pass)) {
      $pass_cambiada = password_hash($pass, PASSWORD_DEFAULT);
      $setPass = ", password='$pass_cambiada'";
    }

    $sqlProf = "UPDATE tbl_profesor SET nombre='$nombre', apellidos='$apellidos', DNI='$dni', departamento='$departamento', ORCID='$orcid', telefono='$telefono', facultad='$facultad', rama='$rama', correo='$correoNuevo' $setPass WHERE correo='$correo'";

    if (mysqli_query($conn, $sqlProf)) {
      mysqli_query($conn, "UPDATE tbl_usuario SET correo='$correoNuevo' " . (!empty($pass) ? ", password='$pass_cambiada'" : "") . " WHERE correo='$correo'");
      $_SESSION['nombredelusuario'] = $correoNuevo;
      header("Location: index.php?success=1");
      exit();
    } else {
      echo "<script>document.addEventListener('DOMContentLoaded', () => { showNotification('Error al guardar los cambios', 'danger'); });</script>";
    }
  }
}
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador - Perfil</title>
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <style>
    .password-requirements {
        font-size: 0.8rem;
        color: rgba(255,255,255,0.7);
        margin-top: 12px;
        padding: 15px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        display: none; /* Se muestra al enfocar el campo */
        transition: all 0.3s ease;
    }
    .password-requirements.visible { display: block; animation: fadeIn 0.3s; }
    .password-requirements ul { list-style: none; padding: 0; margin: 8px 0 0 0; }
    .password-requirements li {
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: color 0.3s ease;
    }
    .requirement-item.invalid { color: #f87171; }
    .requirement-item.valid { color: #4ade80; }
    .requirement-item i { font-size: 0.9rem; }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
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

      <div class="formulario">
        <div class="text-center mb-4 w-100">
          <i class="bi bi-person-gear text-white mb-2" style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
          <h2 class="text-white fw-bold">Gestión de Perfil</h2>
          <hr class="w-100 border-light opacity-25 mt-3 mb-1">
        </div>

        <!-- ══ BLOQUE VISUALIZACIÓN ══ -->
        <div id="bloqueDatos" class="w-100 px-lg-4">
          <ul class="list-unstyled d-flex flex-column gap-2 mb-0 text-start w-100 mx-auto">
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-person me-1"></i> Nombre</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['nombre'] ?: '-') ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-people me-1"></i> Apellidos</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['apellidos'] ?: '-') ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-card-heading me-1"></i> DNI</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['DNI'] ?: '-') ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-envelope me-1"></i> Correo</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['correo'] ?: '-') ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-globe me-1"></i> ORCID</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['ORCID'] ?: '-') ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-building me-1"></i> Departamento</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['departamento'] ?: '-') ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-telephone me-1"></i> Teléfono</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['telefono'] ?: '-') ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-mortarboard me-1"></i> Facultad</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['facultad'] ?: '-') ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-person-badge me-1"></i> Perfil</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['perfil'] ?: '-') ?></span>
            </li>
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase"><i class="bi bi-diagram-2 me-1"></i> Rama</span>
              <span class="fs-5 fw-medium text-white ms-1"><?= htmlspecialchars($fila['rama'] ?: '-') ?></span>
            </li>
          </ul>

          <div class="d-flex justify-content-center gap-3 mt-4">
            <button class="btn btn-primary px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all" id="btnEditar">
              <i class="bi bi-pencil-square"></i> Editar Datos
            </button>
            <button type="button" id="btnValidar" class="btn btn-outline-warning px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all" data-perfil="<?= htmlspecialchars($fila['perfil']) ?>">
              <i class="bi bi-shield-check"></i> Validar
            </button>
          </div>
        </div>

        <!-- ══ BLOQUE EDICIÓN ══ -->
        <div id="bloqueEditar" style="display:none;" class="w-100">
          <form method="POST" class="w-100 px-lg-4">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">Nombre</label>
                <input class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3" name="nombre" value="<?= htmlspecialchars($fila['nombre']) ?>">
              </div>
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">Apellidos</label>
                <input class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3" name="apellidos" value="<?= htmlspecialchars($fila['apellidos']) ?>">
              </div>
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">DNI</label>
                <input class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3" name="dni" value="<?= htmlspecialchars($fila['DNI']) ?>">
              </div>
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">Correo</label>
                <input class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3" name="correoNuevo" value="<?= htmlspecialchars($fila['correo']) ?>">
              </div>
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">ORCID</label>
                <input class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3" name="orcid" value="<?= htmlspecialchars($fila['ORCID']) ?>">
              </div>
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">Departamento</label>
                <input class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3" name="departamento" value="<?= htmlspecialchars($fila['departamento']) ?>">
              </div>
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">Teléfono</label>
                <input class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3" name="telefono" value="<?= htmlspecialchars($fila['telefono']) ?>">
              </div>
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">Facultad</label>
                <input class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3" name="facultad" value="<?= htmlspecialchars($fila['facultad']) ?>">
              </div>
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">Perfil (Solo lectura)</label>
                <input class="form-control bg-dark bg-opacity-50 text-white border-light border-opacity-25 rounded-3" value="<?= htmlspecialchars($fila['perfil']) ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">Rama</label>
                <select class="form-select bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3" name="rama">
                  <option value="SALUD" <?= $fila['rama'] == "SALUD" ? "selected" : "" ?>>SALUD</option>
                  <option value="TECNICAS" <?= $fila['rama'] == "TECNICA" ? "selected" : "" ?>>TECNICA</option>
                  <option value="CSYJ" <?= $fila['rama'] == "CSYJ" ? "selected" : "" ?>>CSYJ</option>
                  <option value="HUMANIDADES" <?= $fila['rama'] == "HUMANIDADES" ? "selected" : "" ?>>HUMANIDADES</option>
                  <option value="EXPERIMENTALES" <?= $fila['rama'] == "EXPERIMENTALES" ? "selected" : "" ?>>EXPERIMENTALES</option>
                </select>
              </div>
              <div class="col-12">
                <label class="text-white-50 small fw-bold text-uppercase mb-1">Nueva Contraseña (opcional)</label>
                <input class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-3 w-50" type="password" name="password" id="passwordInput" placeholder="Escribe para cambiar...">
                
                <div id="password-req-box" class="password-requirements">
                    <strong class="d-block mb-2"><i class="bi bi-shield-lock me-2"></i>Seguridad de la contraseña:</strong>
                    <ul>
                        <li id="req-length" class="requirement-item"><i class="bi bi-circle"></i> Mínimo 8 caracteres</li>
                        <li id="req-upper" class="requirement-item"><i class="bi bi-circle"></i> Al menos una MAYÚSCULA</li>
                        <li id="req-special" class="requirement-item"><i class="bi bi-circle"></i> Al menos un carácter especial</li>
                        <li id="req-number" class="requirement-item"><i class="bi bi-circle"></i> Al menos un número</li>
                    </ul>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-center gap-3 mt-4">
              <button type="submit" name="guardar" class="btn btn-success px-4 py-2 rounded-pill fw-bold">
                <i class="bi bi-check-circle"></i> Guardar Cambios
              </button>
              <button type="button" id="btnCancelar" class="btn btn-danger text-white px-4 py-2 rounded-pill fw-bold">
                <i class="bi bi-x-circle"></i> Cancelar
              </button>
            </div>
          </form>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <link rel="stylesheet" href="../../acelerador_panel/fronten/css/notifications.css">
  <script src="../../acelerador_panel/fronten/js/notifications.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const btnEditar = document.getElementById("btnEditar");
      const btnCancelar = document.getElementById("btnCancelar");
      const bloqueDatos = document.getElementById("bloqueDatos");
      const bloqueEditar = document.getElementById("bloqueEditar");
      const passwordInput = document.getElementById("passwordInput");
      const reqBox = document.getElementById("password-req-box");

      if (btnEditar) {
        btnEditar.addEventListener("click", () => {
          bloqueDatos.style.display = "none";
          bloqueEditar.style.display = "block";
        });
      }

      if (btnCancelar) {
        btnCancelar.addEventListener("click", () => {
          bloqueDatos.style.display = "block";
          bloqueEditar.style.display = "none";
        });
      }

      // LÓGICA DINÁMICA DE CONTRASEÑA
      if (passwordInput) {
          passwordInput.addEventListener("focus", () => reqBox.classList.add('visible'));
          passwordInput.addEventListener("blur", () => {
              if (passwordInput.value === "") reqBox.classList.remove('visible');
          });

          passwordInput.addEventListener("input", () => {
              const val = passwordInput.value;
              if (val.length > 0) reqBox.classList.add('visible');

              // Validaciones
              const rules = {
                  length: val.length >= 8,
                  upper: /[A-Z]/.test(val),
                  special: /[\W]/.test(val),
                  number: /[0-9]/.test(val)
              };

              updateReq('req-length', rules.length);
              updateReq('req-upper', rules.upper);
              updateReq('req-special', rules.special);
              updateReq('req-number', rules.number);
          });
      }

      function updateReq(id, isValid) {
          const el = document.getElementById(id);
          const icon = el.querySelector('i');
          if (isValid) {
              el.classList.remove('invalid');
              el.classList.add('valid');
              icon.className = 'bi bi-check-circle-fill';
          } else {
              el.classList.remove('valid');
              el.classList.add('invalid');
              icon.className = 'bi bi-x-circle-fill';
          }
      }

      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('success')) {
        showNotification('Datos actualizados correctamente', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
      }

      const btnValidar = document.getElementById("btnValidar");
      if (btnValidar) {
        btnValidar.addEventListener("click", () => {
          const perfil = (btnValidar.dataset.perfil || "").toUpperCase().trim();
          const rutas = { "PROFESOR": "../../acelerador_panel/fronten/panel_profesor.php", "TUTOR": "../../acelerador_panel/fronten/panel_tutor.php" };
          const destino = rutas[perfil];
          if (destino) window.location.href = destino + "?validated=1";
          else showNotification("Perfil no reconocido", "danger");
        });
      }
    });
  </script>
</body>
</html>