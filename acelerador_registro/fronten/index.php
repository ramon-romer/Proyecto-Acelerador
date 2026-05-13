<?php
session_start();
include('config.php');

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/correo/vendor/autoload.php';
require_once BASE_PATH . '/correo/sendmail.php';

if (function_exists('mysqli_set_charset')) {
  @mysqli_set_charset($conn, 'utf8mb4');
}

if (isset($_GET['cancel_reg'])) {
  unset($_SESSION['pending_reg']);
  header("Location: index.php");
  exit();
}

function mostrarPopupError($mensaje)
{
  $msg = json_encode($mensaje, JSON_UNESCAPED_UNICODE);
  echo "
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      showNotification($msg, 'danger');
    });
  </script>";
  exit();
}

function existeValor(mysqli $conn, string $sql, string $types, ...$vals): bool
{
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) return false;
  mysqli_stmt_bind_param($stmt, $types, ...$vals);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_store_result($stmt);
  $count = mysqli_stmt_num_rows($stmt);
  mysqli_stmt_close($stmt);
  return $count > 0;
}

$showDuplicados = false;
$dup_list_html = '';
$duplicadosCampos = [];
$prev = [];
$mostrarFormularioCodigo = false;
$mensajeOk = '';
$mensajeError = '';

if (isset($_POST["btn"])) {
  $nombre = trim($_POST["nombre"] ?? '');
  $apellidos = trim($_POST["apellidos"] ?? '');
  $pass = $_POST["pass"] ?? '';
  $pass2 = $_POST["pass2"] ?? '';
  $dni = strtoupper(trim($_POST["dni"] ?? ''));
  $orcid = trim($_POST["orcid"] ?? '');
  $telefono = preg_replace('/\D+/', '', $_POST["telefono"] ?? '');
  $perfil = trim($_POST["perfil"] ?? '');
  $facultad = trim($_POST["facultad"] ?? '');
  $departamento = trim($_POST["departamento"] ?? '');
  $correo = strtolower(trim($_POST["correo"] ?? ''));
  $rama = trim($_POST["rama"] ?? '');

  $errorValidacion = "";

  if ($pass !== $pass2) $errorValidacion = "Las contraseñas no coinciden.";
  elseif (strlen($pass) < 8) $errorValidacion = "La contraseña debe tener mínimo 8 caracteres.";
  elseif (!preg_match('/[A-Z]/', $pass)) $errorValidacion = "La contraseña debe incluir al menos una mayúscula.";
  elseif (!preg_match('/[\W]/', $pass)) $errorValidacion = "La contraseña debe incluir al menos un carácter especial.";
  elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errorValidacion = "El correo electrónico no tiene un formato válido.";
  elseif (!preg_match('/^[0-9]{8}[A-Za-z]$/', $dni)) $errorValidacion = "El DNI no cumple el formato (12345678X).";
  elseif (!preg_match('/^[0-9]{9}$/', $telefono)) $errorValidacion = "El teléfono debe tener 9 dígitos.";
  elseif (!preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/', $orcid)) $errorValidacion = "El ORCID debe tener el formato 0000-0000-0000-0000.";
  
  // VERIFICACIÓN DE DUPLICADOS RIGUROSA
  elseif (existeValor($conn, "SELECT 1 FROM tbl_profesor WHERE ORCID = ? LIMIT 1", "s", $orcid)) {
    $errorValidacion = "El ORCID introducido ya pertenece a una cuenta registrada.";
  }
  elseif (existeValor($conn, "SELECT 1 FROM tbl_profesor WHERE correo = ? LIMIT 1", "s", $correo) || existeValor($conn, "SELECT 1 FROM tbl_usuario WHERE correo = ? LIMIT 1", "s", $correo)) {
    $errorValidacion = "Este correo electrónico ya está registrado en el sistema.";
  }
  elseif (existeValor($conn, "SELECT 1 FROM tbl_profesor WHERE DNI = ? LIMIT 1", "s", $dni)) {
    $errorValidacion = "Este DNI ya se encuentra asociado a una cuenta activa.";
  }
  elseif (existeValor($conn, "SELECT 1 FROM tbl_profesor WHERE telefono = ? LIMIT 1", "s", $telefono)) {
    $errorValidacion = "Este número de teléfono ya está registrado por otro usuario.";
  }

  if (!empty($errorValidacion)) {
    $mensajeErrorGlobal = $errorValidacion;
  } else {
    $numero = random_int(1000000, 9999999);
    $letra = chr(random_int(65, 90));
    $codigo = $numero . $letra;

    $_SESSION['pending_reg'] = [
      'code_hash' => password_hash($codigo, PASSWORD_DEFAULT),
      'expires_at' => time() + (15 * 60),
      'attempts' => 0,
      'data' => [
        'nombre' => $nombre, 'apellidos' => $apellidos,
        'passHash' => password_hash($pass, PASSWORD_DEFAULT),
        'dni' => $dni, 'orcid' => $orcid, 'telefono' => $telefono,
        'perfil' => $perfil, 'facultad' => $facultad, 'departamento' => $departamento,
        'correo' => $correo, 'rama' => $rama
      ]
    ];

    $safeNombre = htmlspecialchars(trim($nombre . ' ' . $apellidos), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $asunto = 'Tu código de verificación';
    $html = "<p>¡Hola, {$safeNombre}!</p><p>Tu código de verificación es:</p><p style='font-size:20px'><strong>{$codigo}</strong></p><p>Caduca en <strong>15 minutos</strong>.</p>";
    $alt = "Hola, {$safeNombre}. Tu código es: {$codigo} (caduca en 15 minutos).";

    if (enviarCorreo($correo, $safeNombre, $asunto, $html, $alt)) {
      $mostrarFormularioCodigo = true;
    } else {
      $mensajeErrorGlobal = "No se pudo enviar el correo de verificación. Inténtalo de nuevo.";
    }
  }
}

if (isset($_POST['btn_verificar'])) {
  $codigoIngresado = strtoupper(trim($_POST['codigo'] ?? ''));
  if (!preg_match('/^[0-9]{7}[A-Z]$/', $codigoIngresado)) {
    $mostrarFormularioCodigo = true;
    $mensajeError = "Formato incorrecto.";
  } else {
    $pend = $_SESSION['pending_reg'] ?? null;
    if (!$pend || time() > $pend['expires_at']) {
      mostrarPopupError("Código caducado o no válido.");
    }
    if (!password_verify($codigoIngresado, $pend['code_hash'])) {
      $mostrarFormularioCodigo = true;
      $mensajeError = "Código incorrecto.";
    } else {
      $d = $pend['data'];
      mysqli_begin_transaction($conn);
      try {
        $stmt1 = mysqli_prepare($conn, "INSERT INTO tbl_profesor (nombre, apellidos, password, DNI, ORCID, telefono, perfil, facultad, departamento, correo, rama) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt1, "sssssssssss", $d['nombre'], $d['apellidos'], $d['passHash'], $d['dni'], $d['orcid'], $d['telefono'], $d['perfil'], $d['facultad'], $d['departamento'], $d['correo'], $d['rama']);
        mysqli_stmt_execute($stmt1);
        $stmt2 = mysqli_prepare($conn, "INSERT INTO tbl_usuario (correo, password) VALUES (?,?)");
        mysqli_stmt_bind_param($stmt2, "ss", $d['correo'], $d['passHash']);
        mysqli_stmt_execute($stmt2);
        mysqli_commit($conn);
        unset($_SESSION['pending_reg']);
        $_SESSION['nombredelusuario'] = $d['correo'];
        header("Location: ../../acelerador_primerapantallas/fronten/index.php");
        exit();
      } catch (Exception $e) {
        mysqli_rollback($conn);
        mostrarPopupError($e->getMessage());
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registro - Acelerador</title>
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <style>
    .popover-body { white-space: pre-line; }
    .password-requirements {
        font-size: 0.8rem;
        color: rgba(255,255,255,0.7);
        margin-top: 12px;
        padding: 15px;
        background: rgba(0, 0, 0, 0.4);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        display: none;
        transition: all 0.3s ease;
        backdrop-filter: blur(5px);
        position: absolute;
        z-index: 100;
        width: 250px;
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

    /* Contenedor de Error Central */
    #center-notification-container {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 11000;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        animation: fadeInCenter 0.3s ease;
    }
    .center-toast {
        background: rgba(15, 23, 42, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-top: 6px solid #f87171;
        padding: 40px 60px;
        border-radius: 30px;
        text-align: center;
        box-shadow: 0 30px 100px rgba(0,0,0,0.6);
        max-width: 500px;
        width: 90%;
        animation: zoomInCenter 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .center-toast i { font-size: 4rem; color: #f87171; margin-bottom: 20px; display: block; }
    .center-toast h2 { color: white; font-weight: 800; margin-bottom: 10px; }
    .center-toast p { color: rgba(255,255,255,0.7); font-size: 1.1rem; margin-bottom: 25px; }
    
    @keyframes fadeInCenter { from { opacity: 0; } to { opacity: 1; } }
    @keyframes zoomInCenter { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
  </style>
</head>
<body>
  <!-- Contenedor de Error Central -->
  <div id="center-notification-container" onclick="this.style.display='none'">
      <div class="center-toast">
          <i class="bi bi-person-fill-exclamation"></i>
          <h2>DATOS DUPLICADOS</h2>
          <p id="center-error-msg">Ya existe una cuenta con estos datos.</p>
          <button class="btn btn-outline-danger rounded-pill px-5 py-2 fw-bold">CORREGIR DATOS</button>
      </div>
  </div>
  <header>
    <div class="contenedorimg">
      <div class="imagen">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU Universidad Fernando III"
          style="height:50px; width:auto;" id="#acele" />
      </div>
      <div class="imagen">
        <img src="../../acelerador_login/fronten/img/AcademyAccelerator_def.png" id="academy" alt="academy" />
      </div>
    </div>
  </header>

  <main>
    <div class="contenedor">
      <div class="formulario px-4 py-5" style="max-width: 700px; width: 95%; margin: 50px auto;">
        <div class="text-center mb-5">
            <div class="d-inline-block p-3 rounded-circle bg-white bg-opacity-10 mb-3 shadow-lg">
                <i class="bi bi-person-plus-fill text-white" style="font-size: 3rem;"></i>
            </div>
            <h2 class="text-white fw-800 mb-1">Crea tu Cuenta</h2>
            <p class="text-white-50 small text-uppercase fw-bold">Únete al ecosistema Acelerador</p>
        </div>

        <form method="POST" class="w-100" id="registrationForm" novalidate>
          
          <div class="mb-5">
              <h5 class="text-primary fw-bold mb-3 d-flex align-items-center"><i class="bi bi-shield-lock me-2"></i>Seguridad de Acceso</h5>
              <div class="row g-3">
                  <div class="col-12">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Correo Electrónico Oficial</label>
                      <input type="email" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="correo" name="correo" required>
                  </div>
                  <div class="col-md-6 position-relative">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Contraseña</label>
                      <input type="password" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="pass" name="pass" required>
                      <div id="password-req-box" class="password-requirements">
                          <strong class="d-block mb-2">Requisitos de seguridad:</strong>
                          <ul>
                              <li id="req-length" class="requirement-item"><i class="bi bi-circle"></i> Mínimo 8 caracteres</li>
                              <li id="req-upper" class="requirement-item"><i class="bi bi-circle"></i> Al menos una MAYÚSCULA</li>
                              <li id="req-special" class="requirement-item"><i class="bi bi-circle"></i> Al menos un carácter especial</li>
                              <li id="req-number" class="requirement-item"><i class="bi bi-circle"></i> Al menos un número</li>
                          </ul>
                      </div>
                  </div>
                  <div class="col-md-6 position-relative">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Confirmar Contraseña</label>
                      <input type="password" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="pass2" name="pass2" required>
                      <div id="pass2-req-box" class="password-requirements">
                          <ul>
                              <li id="req-match" class="requirement-item"><i class="bi bi-circle"></i> Las contraseñas deben coincidir</li>
                          </ul>
                      </div>
                  </div>
              </div>
          </div>

          <div class="mb-5">
              <h5 class="text-info fw-bold mb-3 d-flex align-items-center"><i class="bi bi-person-vcard me-2"></i>Identidad Personal</h5>
              <div class="row g-3">
                  <div class="col-md-6">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Nombre</label>
                      <input type="text" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="nombre" name="nombre" required>
                  </div>
                  <div class="col-md-6">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Apellidos</label>
                      <input type="text" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="apellidos" name="apellidos" required>
                  </div>
                  <div class="col-md-6 position-relative">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">DNI / Documento</label>
                      <input type="text" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="dni" name="dni" required>
                      <div id="dni-req-box" class="password-requirements">
                          <ul>
                              <li id="req-dni" class="requirement-item"><i class="bi bi-circle"></i> Formato válido: 12345678X</li>
                          </ul>
                      </div>
                  </div>
              </div>
          </div>

          <div class="mb-4">
              <h5 class="text-warning fw-bold mb-3 d-flex align-items-center"><i class="bi bi-briefcase me-2"></i>Trayectoria Profesional</h5>
              <div class="row g-3">
                  <div class="col-md-6">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Departamento</label>
                      <input type="text" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="departamento" name="departamento" required>
                  </div>
                  <div class="col-md-6">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Facultad</label>
                      <input type="text" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="facultad" name="facultad" required>
                  </div>
                  <div class="col-md-6 position-relative">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">ORCID</label>
                      <input type="text" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="orcid" name="orcid" required>
                      <div id="orcid-req-box" class="password-requirements">
                          <ul>
                              <li id="req-orcid" class="requirement-item"><i class="bi bi-circle"></i> Formato: 0000-0000-0000-0000</li>
                          </ul>
                      </div>
                  </div>
                  <div class="col-md-6 position-relative">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Teléfono</label>
                      <input type="text" class="form-control bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="telefono" name="telefono" required>
                      <div id="tel-req-box" class="password-requirements">
                          <ul>
                              <li id="req-tel" class="requirement-item"><i class="bi bi-circle"></i> Debe tener 9 dígitos</li>
                          </ul>
                      </div>
                  </div>
                  <div class="col-md-6">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Perfil Académico</label>
                      <select class="form-select bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="perfil" name="perfil" required>
                          <option value="" disabled selected hidden>Seleccionar...</option>
                          <option value="PROFESOR">PROFESOR</option>
                          <option value="TUTOR">TUTOR</option>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="text-white-50 small fw-bold text-uppercase mb-1">Rama de Conocimiento</label>
                      <select class="form-select bg-dark bg-opacity-25 text-white border-light border-opacity-25 rounded-pill px-4 py-2" id="rama" name="rama" required>
                          <option value="" disabled selected hidden>Seleccionar...</option>
                          <option value="SALUD">SALUD</option>
                          <option value="TECNICA">TÉCNICA</option>
                          <option value="CSYJ">CSYJ</option>
                          <option value="HUMANIDADES">HUMANIDADES</option>
                          <option value="EXPERIMENTALES">EXPERIMENTALES</option>
                      </select>
                  </div>
              </div>
          </div>

          <div class="text-center mt-5">
            <button type="submit" name="btn" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-lg mb-4">
                REGISTRAR MI CUENTA <i class="bi bi-arrow-right-short ms-2"></i>
            </button>
            <div class="mt-2">
                <a href="../../acelerador_login/fronten/index.php" class="text-white-50 text-decoration-none small fw-bold">
                    <i class="bi bi-arrow-left me-1"></i> Ya tengo cuenta, iniciar sesión
                </a>
            </div>
          </div>
        </form>
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

  <!-- POPUP CÓDIGO -->
  <!-- POPUP CÓDIGO (Verificación de Correo) -->
  <div id="popupCodigo" style="display:<?= $mostrarFormularioCodigo ? 'flex' : 'none' ?>; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index:999999; justify-content:center; align-items:center;">
    <div class="bg-dark border border-white border-opacity-10 p-5 rounded-5 shadow-2xl text-center" style="max-width:500px; width:90%;">
      <div class="mb-4">
          <i class="bi bi-envelope-check-fill text-primary" style="font-size: 4rem;"></i>
      </div>
      <h3 class="text-white fw-bold mb-3">Verificación de Correo</h3>
      <p class="text-white-50 mb-4">Hemos enviado un código de 8 caracteres a tu email. Por favor, introdúcelo para activar tu cuenta.</p>
      
      <?php if($mensajeError) echo "<div class='alert alert-danger bg-danger bg-opacity-25 border-danger text-white rounded-4 mb-4'>$mensajeError</div>"; ?>
      
      <form method="post" class="text-start">
        <label class="text-white-50 small fw-bold text-uppercase mb-2">Código de Verificación</label>
        <input type="text" name="codigo" class="form-control bg-white bg-opacity-10 text-white border-light border-opacity-25 rounded-pill px-4 py-3 mb-4 text-center fw-bold" style="letter-spacing: 5px; font-size: 1.5rem;" required maxlength="8" oninput="this.value=this.value.toUpperCase()">
        
        <div class="d-grid gap-2">
            <button type="submit" name="btn_verificar" class="btn btn-primary btn-lg rounded-pill fw-bold py-3 shadow-lg">
                VERIFICAR Y ACTIVAR <i class="bi bi-check-all ms-2"></i>
            </button>
            <div class="text-center mt-3 d-flex flex-column gap-2">
                <a href="index.php?cancel_reg=1" class="text-white-50 text-decoration-none small">
                    <i class="bi bi-pencil-square me-1"></i> Corregir datos del formulario
                </a>
                <a href="../../acelerador_login/fronten/index.php" class="text-danger text-decoration-none small fw-bold">
                    <i class="bi bi-x-circle me-1"></i> Cancelar todo y volver al inicio
                </a>
            </div>
        </div>
      </form>
    </div>
  </div>

  <!-- POPUP CAMPOS VACÍOS -->
  <div id="popupFaltan" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index:999999; justify-content:center; align-items:center;">
    <div class="bg-dark border border-white border-opacity-10 p-5 rounded-5 shadow-2xl text-center" style="max-width:500px; width:90%;">
      <div class="mb-4"><i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i></div>
      <h3 class="text-white fw-bold mb-3">Información Incompleta</h3>
      <p class="text-white-50 mb-4">Por favor completa los siguientes campos obligatorios:</p>
      <div class="bg-white p-3 rounded-4 mb-4 text-start shadow-inner">
          <ul id="listaCamposFaltan" class="text-black mb-0 fw-bold" style="list-style: none; padding: 0;"></ul>
      </div>
      <button id="cerrarPopupFaltan" class="btn btn-outline-light rounded-pill px-5 fw-bold py-2">ENTENDIDO</button>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <link rel="stylesheet" href="../../acelerador_panel/fronten/css/notifications.css">
  <script src="../../acelerador_panel/fronten/js/notifications.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const passInput = document.getElementById("pass");
      const pass2Input = document.getElementById("pass2");
      const dniInput = document.getElementById("dni");
      const orcidInput = document.getElementById("orcid");
      const telInput = document.getElementById("telefono");

      function setupReq(input, boxId) {
          const box = document.getElementById(boxId);
          input.addEventListener("focus", () => {
              // Cerrar todos antes de abrir el nuevo
              document.querySelectorAll('.password-requirements').forEach(b => b.classList.remove('visible'));
              box.classList.add('visible');
          });
          input.addEventListener("blur", () => { 
              // Ocultar inmediatamente al salir
              setTimeout(() => box.classList.remove('visible'), 150); 
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

      setupReq(passInput, "password-req-box");
      setupReq(pass2Input, "pass2-req-box");
      setupReq(dniInput, "dni-req-box");
      setupReq(orcidInput, "orcid-req-box");
      setupReq(telInput, "tel-req-box");

      passInput.addEventListener("input", () => {
          const v = passInput.value;
          updateReq('req-length', v.length >= 8);
          updateReq('req-upper', /[A-Z]/.test(v));
          updateReq('req-special', /[\W]/.test(v));
          updateReq('req-number', /[0-9]/.test(v));
      });

      pass2Input.addEventListener("input", () => {
          updateReq('req-match', pass2Input.value === passInput.value && pass2Input.value !== "");
      });

      dniInput.addEventListener("input", () => updateReq('req-dni', /^[0-9]{8}[A-Z]$/.test(dniInput.value)));
      orcidInput.addEventListener("input", () => updateReq('req-orcid', /^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/.test(orcidInput.value)));
      telInput.addEventListener("input", () => updateReq('req-tel', /^[0-9]{9}$/.test(telInput.value)));

      // VALIDACIÓN DE CAMPOS VACÍOS
      const form = document.getElementById("registrationForm");
      const popupFaltan = document.getElementById("popupFaltan");
      const listaCamposFaltan = document.getElementById("listaCamposFaltan");
      const btnCerrarPopup = document.getElementById("cerrarPopupFaltan");

      if (form) {
          form.addEventListener("submit", function(e) {
              let vacios = [];
              const inputs = form.querySelectorAll("input[required], select[required]");
              
              inputs.forEach(input => {
                  if (!input.value || input.value.trim() === "") {
                      // Buscar el label asociado al input dentro de su contenedor padre
                      let parent = input.parentElement;
                      let labelEl = parent.querySelector("label");
                      
                      // Si no está en el padre inmediato, buscar un nivel más arriba (por si hay wrappers)
                      if (!labelEl) labelEl = parent.parentElement.querySelector("label");
                      
                      let labelText = labelEl ? labelEl.innerText.replace(':', '').trim() : input.placeholder || input.name;
                      vacios.push(labelText);
                  }
              });

              if (vacios.length > 0) {
                  e.preventDefault(); // Detener envío
                  listaCamposFaltan.innerHTML = "";
                  vacios.forEach(campo => {
                      const li = document.createElement("li");
                      li.style.padding = "5px 0";
                      li.style.borderBottom = "1px solid rgba(0,0,0,0.1)";
                      li.style.color = "black";
                      li.innerHTML = `<i class="bi bi-dot text-danger me-2"></i>${campo}`;
                      listaCamposFaltan.appendChild(li);
                  });
                  popupFaltan.style.display = "flex";
              }
          });
      }

      if (btnCerrarPopup) btnCerrarPopup.addEventListener("click", () => popupFaltan.style.display = "none");
    });
  </script>
  
  <?php if (isset($mensajeErrorGlobal)): ?>
  <script>
      function showCenterError(msg) {
          document.getElementById('center-error-msg').innerText = msg;
          document.getElementById('center-notification-container').style.display = 'flex';
      }
      
      document.addEventListener("DOMContentLoaded", () => {
          showCenterError(<?= json_encode($mensajeErrorGlobal) ?>);
      });
  </script>
  <?php endif; ?>
</body>
</html>