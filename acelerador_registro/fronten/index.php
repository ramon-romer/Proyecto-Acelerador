<?php
session_start();
include('config.php'); // <- TU conexión a BD (MySQLi): debe definir $conn

define('BASE_PATH', dirname(__DIR__));                   // .../acelerador_registro
require_once BASE_PATH . '/correo/vendor/autoload.php';  // PHPMailer via Composer
require_once BASE_PATH . '/correo/sendmail.php';         // función enviarCorreo(...)

/* ------------------ Charset de la conexión ------------------ */
if (function_exists('mysqli_set_charset')) {
  @mysqli_set_charset($conn, 'utf8mb4');
}

/* ------------------ Helpers de UI ------------------ */
function mostrarPopupError($mensaje)
{
  $msg = json_encode($mensaje, JSON_UNESCAPED_UNICODE);
  echo "
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var pop = document.getElementById('popupFaltan');
      var ul  = document.getElementById('listaCamposFaltan');
      if (pop && ul) {
        ul.innerHTML = '<li>' + $msg + '</li>';
        pop.style.display = 'flex';
      } else {
        alert($msg);
      }
    });
  </script>";
  exit();
}

/* Consulta booleana con prepared statements */
function existeValor(mysqli $conn, string $sql, string $types, ...$vals): bool
{
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt)
    return false;
  mysqli_stmt_bind_param($stmt, $types, ...$vals);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_store_result($stmt);
  $count = mysqli_stmt_num_rows($stmt);
  mysqli_stmt_close($stmt);
  return $count > 0;
}

/* Variables para mostrar UI condicional */
$showDuplicados = false;
$dup_list_html = '';
$duplicadosCampos = [];
$prev = [];
$mostrarFormularioCodigo = false; // para enseñar el form del código
$mensajeOk = '';
$mensajeError = '';

/* ============================================================
   PASO 1: el usuario envía el formulario de registro (btn)
   Validación -> duplicados -> GENERAR CÓDIGO -> ENVIAR EMAIL
   (Aún NO insertamos en BD)
   ============================================================ */
if (isset($_POST["btn"])) {

  // --- Captura & saneado mínimo ---
  $nombre = trim($_POST["nombre"] ?? '');
  $apellidos = trim($_POST["apellidos"] ?? '');
  $pass = $_POST["pass"] ?? '';
  $pass2 = $_POST["pass2"] ?? '';
  $dni = strtoupper(trim($_POST["dni"] ?? ''));
  $orcid = trim($_POST["orcid"] ?? '');
  $telefono = preg_replace('/\D+/', '', $_POST["telefono"] ?? ''); // solo dígitos
  $perfil = trim($_POST["perfil"] ?? '');
  $facultad = trim($_POST["facultad"] ?? '');
  $departamento = trim($_POST["departamento"] ?? '');
  $correo = strtolower(trim($_POST["correo"] ?? ''));
  $rama = trim($_POST["rama"] ?? '');

  // --- Validaciones servidor ---
  if ($pass !== $pass2)
    mostrarPopupError("Las contraseñas no coinciden.");
  if (strlen($pass) < 8)
    mostrarPopupError("La contraseña debe tener mínimo 8 caracteres.");
  if (!preg_match('/[A-Z]/', $pass))
    mostrarPopupError("La contraseña debe incluir al menos una mayúscula.");
  if (!preg_match('/[\W]/', $pass))
    mostrarPopupError("La contraseña debe incluir al menos un carácter especial.");
  if (!filter_var($correo, FILTER_VALIDATE_EMAIL))
    mostrarPopupError("El correo electrónico no tiene un formato válido.");
  if (!preg_match('/^[0-9]{8}[A-Za-z]$/', $dni))
    mostrarPopupError("El DNI no cumple el formato (12345678X).");
  if (!preg_match('/^[0-9]{9}$/', $telefono))
    mostrarPopupError("El teléfono debe tener 9 dígitos.");
  if (!preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/', $orcid))
    mostrarPopupError("El ORCID debe tener el formato 0000-0000-0000-0000.");

  // --- DUPLICADOS ---
  $duplicados = [];
  $duplicadosCampos = [];

  if (existeValor($conn, "SELECT 1 FROM tbl_profesor WHERE ORCID = ? LIMIT 1", "s", $orcid)) {
    $duplicados[] = "El ORCID introducido ya pertenece a una cuenta.";
    $duplicadosCampos[] = "orcid";
  }

  if (!empty($duplicados)) {
    $showDuplicados = true;
    foreach ($duplicados as $m) {
      $dup_list_html .= '<li>' . htmlspecialchars($m, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
    }
    $prev = [
      'correo' => $correo,
      'nombre' => $nombre,
      'apellidos' => $apellidos,
      'dni' => $dni,
      'orcid' => $orcid,
      'telefono' => $telefono,
      'perfil' => $perfil,
      'facultad' => $facultad,
      'departamento' => $departamento,
      'rama' => $rama
    ];
    // No exit; seguimos en la misma página mostrando popup/errores
  } else {
    /* ------- REGISTRO EN 2 PASOS --------
       1) Generamos un código, lo enviamos por email (UN ÚNICO CORREO) y
       2) Guardamos todos los datos en SESSION como "pendiente".
       3) Mostramos el formulario para introducir el código.
    */
    $numero = random_int(1000000, 9999999);   // 7 dígitos
    $letra = chr(random_int(65, 90));       // 'A'..'Z'
    $codigo = $numero . $letra;              // p.ej. 1234567X

    // Guardar en SESSION el registro pendiente
    $_SESSION['pending_reg'] = [
      'code_hash' => password_hash($codigo, PASSWORD_DEFAULT),
      'expires_at' => time() + (15 * 60), // 15 min de validez
      'attempts' => 0,
      'data' => [
        'nombre' => $nombre,
        'apellidos' => $apellidos,
        'passHash' => password_hash($pass, PASSWORD_DEFAULT),
        'dni' => $dni,
        'orcid' => $orcid,
        'telefono' => $telefono,
        'perfil' => $perfil,
        'facultad' => $facultad,
        'departamento' => $departamento,
        'correo' => $correo,
        'rama' => $rama
      ]
    ];

    // Enviar el código por correo (UN SOLO CORREO)
    $safeNombre = htmlspecialchars(trim($nombre . ' ' . $apellidos), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $asunto = 'Tu código de verificación';
    $html = "
      <p>¡Hola, {$safeNombre}!</p>
      <p>Tu código de verificación es:</p>
      <p style='font-size:20px'><strong>{$codigo}</strong></p>
      <p>Caduca en <strong>15 minutos</strong>.</p>
    ";
    $alt = "Hola, {$safeNombre}. Tu código es: {$codigo} (caduca en 15 minutos).";

    $enviado = enviarCorreo($correo, $safeNombre, $asunto, $html, $alt);
    if (!$enviado) {
      mostrarPopupError("No se pudo enviar el correo con el código. Inténtalo de nuevo en unos minutos.");
    }

    // Mostrar el formulario de código (popup de verificación)
    $mostrarFormularioCodigo = true;

  }
}

/* ============================================================
   PASO 2: el usuario envía el código (btn_verificar)
   Verificamos -> si OK -> INSERTS y redirigimos
   ============================================================ */
if (isset($_POST['btn_verificar'])) {
  $codigoIngresado = strtoupper(trim($_POST['codigo'] ?? ''));

  // Mantener popup abierto si el formato es inválido
  if (!preg_match('/^[0-9]{7}[A-Z]$/', $codigoIngresado)) {
    $mostrarFormularioCodigo = true;
    $mensajeError = "El código no tiene el formato correcto (7 dígitos y 1 letra).";
  } else {
    if (empty($_SESSION['pending_reg'])) {
      mostrarPopupError("No hay un registro pendiente. Vuelve a iniciar el proceso.");
    }

    $pend = $_SESSION['pending_reg'];

    // Caducidad
    if (time() > ($pend['expires_at'] ?? 0)) {
      unset($_SESSION['pending_reg']);
      mostrarPopupError("El código ha caducado. Vuelve a iniciar el registro.");
    }

    // Intentos
    $_SESSION['pending_reg']['attempts'] = (int) ($pend['attempts'] ?? 0) + 1;
    if ($_SESSION['pending_reg']['attempts'] > 5) {
      unset($_SESSION['pending_reg']);
      mostrarPopupError("Has superado el número de intentos. Vuelve a iniciar el registro.");
    }

    // Comparar el código
    if (!password_verify($codigoIngresado, $pend['code_hash'])) {
      $mostrarFormularioCodigo = true;
      $mensajeError = "Código incorrecto. Inténtalo de nuevo.";
    } else {
      // Código OK -> INSERTS
      $d = $pend['data'];
      mysqli_begin_transaction($conn);
      try {
        // Insert PROFESOR
        $sql1 = "INSERT INTO tbl_profesor
          (nombre, apellidos, password, DNI, ORCID, telefono, perfil, facultad, departamento, correo, rama)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt1 = mysqli_prepare($conn, $sql1);
        if (!$stmt1)
          throw new Exception("Error al preparar la inserción en profesor.");
        mysqli_stmt_bind_param(
          $stmt1,
          "sssssssssss",
          $d['nombre'],
          $d['apellidos'],
          $d['passHash'],
          $d['dni'],
          $d['orcid'],
          $d['telefono'],
          $d['perfil'],
          $d['facultad'],
          $d['departamento'],
          $d['correo'],
          $d['rama']
        );
        if (!mysqli_stmt_execute($stmt1))
          throw new Exception("Error al registrar profesor en la base de datos.");
        mysqli_stmt_close($stmt1);

        // Insert USUARIO
        $sql2 = "INSERT INTO tbl_usuario (correo, password) VALUES (?, ?)";
        $stmt2 = mysqli_prepare($conn, $sql2);
        if (!$stmt2)
          throw new Exception("Error al preparar la inserción en usuario.");
        mysqli_stmt_bind_param($stmt2, "ss", $d['correo'], $d['passHash']);
        if (!mysqli_stmt_execute($stmt2))
          throw new Exception("Error al crear la cuenta de usuario.");
        mysqli_stmt_close($stmt2);

        mysqli_commit($conn);
        unset($_SESSION['pending_reg']); // limpiar

        // SOLO se envió 1 correo (el del código). Aquí NO enviamos nada más.
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

<?php /* ============================================================
FORMULARIO DE VERIFICACIÓN (popup)
Se pinta cuando $mostrarFormularioCodigo = true o hay mensaje de error/ok.
IMPORTANTE: AÑADIMOS el <form method="post"> que faltaba.
============================================================ */ ?>
<?php if (!empty($mostrarFormularioCodigo) || !empty($mensajeError) || !empty($mensajeOk)): ?>
  <div class="mensajes">
    <?php if (!empty($mensajeOk))
      echo '<div class="ok">' . $mensajeOk . '</div>'; ?>
    <?php if (!empty($mensajeError))
      echo '<div class="err">' . $mensajeError . '</div>'; ?>
  </div>


<?php endif; ?>



<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador</title>

  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css" />
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
    <div class="contenedor">
      <div class="formulario">
        <div class="titulos">
          <h1>Formulario de registro</h1>
          <h3>Rellenar todos los campos es obligatorio</h3>
        </div>

        <form method="POST">
          <div class="conte">

            <!-- ================= ITEM 1 ================= -->
            <div class="item1">

              <!-- Email -->
              <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="email" class="form-control" id="correo" name="correo">
                  </div>
                </div>
              </div>

              <!-- Nombre -->
              <div class="mb-3">
                <label class="form-label">Nombre</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="text" class="form-control" id="nombre" name="nombre">
                  </div>
                </div>
              </div>

              <!-- Apellidos -->
              <div class="mb-3">
                <label class="form-label">Apellidos</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="text" class="form-control" id="apellidos" name="apellidos">
                  </div>
                </div>
              </div>

              <!-- Contraseña -->
              <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="password" class="form-control" id="pass" name="pass">
                    <ul id="requisitos" class="errorBox">
                      <li id="req1">• Mínimo 8 caracteres</li>
                      <li id="req2">• Al menos una mayúscula</li>
                      <li id="req3">• Al menos un carácter especial</li>
                      <li id="req4">• Al menos un número</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Repetir contraseña -->
              <div class="mb-3">
                <label class="form-label">Repetir contraseña</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="password" class="form-control" id="pass2" name="pass2">
                    <ul id="pass2Error" class="errorBox" style="display:none;">
                      <li>Las contraseñas no coinciden</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Departamento -->
              <div class="mb-3">
                <label class="form-label">Departamento</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="text" class="form-control" id="departamento" name="departamento">
                  </div>
                </div>
              </div>
            </div>

            <!-- ================= ITEM 2 ================= -->
            <div class="item2">

              <!-- DNI -->
              <div class="mb-3">
                <label class="form-label">DNI</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="text" class="form-control" id="dni" name="dni">
                    <ul id="dniError" class="errorBox">
                      <li>Formato DNI incorrecto (12345678X)</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- ORCID -->
              <div class="mb-3">
                <label class="form-label">ORCID</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="text" class="form-control" id="orcid" name="orcid">
                    <ul id="orcidError" class="errorBox">
                      <li>Formato ORCID incorrecto (0000-0000-0000-0000)</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Teléfono -->
              <div class="mb-3">
                <label class="form-label">Teléfono</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="text" class="form-control" id="telefono" name="telefono">
                    <ul id="telError" class="errorBox">
                      <li>El teléfono debe tener 9 dígitos</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Facultad -->
              <div class="mb-3">
                <label class="form-label">Facultad</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <input type="text" class="form-control" id="facultad" name="facultad">
                  </div>
                </div>
              </div>

              <!-- Perfil -->
              <div class="mb-3">
                <label class="form-label">Perfil</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <select id="select" class="form-select" name="perfil">
                      <option value="" disabled selected hidden>Seleccione un perfil</option>
                      <option value="PROFESOR">Profesor</option>
                      <option value="TUTOR">Tutor</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Rama -->
              <div class="mb-3">
                <label class="form-label">Rama</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <select id="select" class="form-select" name="rama">
                      <option value="" disabled selected hidden>Seleccione una rama</option>
                      <option value="SALUD">Salud</option>
                      <option value="TECNICA">Técnica</option>
                      <option value="CSYJ">CSYJ</option>
                      <option value="HUMANIDADES">Humanidades</option>
                      <option value="EXPERIMENTALES">Experimentales</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <div class="boton">
            <button type="submit" name="btn" class="btn btn-primary">Ingresar</button>
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

  <!-- POPUP CAMPOS VACÍOS / FORMATO -->
  <div id="popupFaltan" style="display:none; 
            position:fixed; 
            top:0; left:0; 
            color:black;
            width:100%; height:100%; 
            background:rgba(0,0,0,0.6);
            backdrop-filter: blur(2px);
            z-index:999999;
            justify-content:center; 
            align-items:center;
            cursor:pointer;">
    <div style="background:white; 
                padding:25px; 
                border-radius:10px; 
                max-width:400px; 
                width:85%; 
                box-shadow:0 4px 15px rgba(0,0,0,0.3); 
                font-size:16px;">
      <h4 style="margin-bottom:10px;">Faltan por rellenar o hay errores en el formato requerido en los siguientes
        campos:</h4>
      <ul id="listaCamposFaltan"></ul>
      <p style="font-size:12px; text-align:right; opacity:0.6;">
        (haz clic para cerrar)
      </p>
    </div>
  </div>

  <!-- POPUP CÓDIGO DE VERIFICACIÓN (misma línea de estilos que usas) -->
  <div id="popupCodigo" style="
  display:<?= (!empty($mostrarFormularioCodigo) || !empty($mensajeError) || !empty($mensajeOk)) ? 'flex' : 'none' ?>;
  position:fixed;
  top:0; left:0;
  width:100%; height:100%;
  color:black;
  background:rgba(0,0,0,0.6);
  backdrop-filter: blur(2px);
  z-index:999999;
  justify-content:center;
  align-items:center;
  cursor:pointer;">
    <div style="
    background:white;
    padding:25px;
    border-radius:10px;
    max-width:400px;
    width:85%;
    box-shadow:0 4px 15px rgba(0,0,0,0.3);
    font-size:16px;" onclick="event.stopPropagation()">

      <h4 style="margin-bottom:10px;">Verificación de correo</h4>

      <?php
      if (!empty($mensajeOk))
        echo '<div class="ok" style="margin-bottom:10px;">' . $mensajeOk . '</div>';
      if (!empty($mensajeError))
        echo '<div class="err" style="margin-bottom:10px;">' . $mensajeError . '</div>';
      ?>

      <form method="post" style="display:grid; gap:.75rem; margin-top:8px;">
        <label for="codigo" class="form-label">Introduce el código (7 dígitos + 1 letra):</label>
        <input type="text" id="codigo" name="codigo" inputmode="latin" pattern="[0-9]{7}[A-Z]" maxlength="8" required
          oninput="this.value=this.value.toUpperCase().replace(/[^0-9A-Z]/g,'');"
          style="padding:.7rem; border:1px solid #ccc; border-radius:8px">

        <div style="display:flex; gap:8px; justify-content:flex-end;">
          <button type="submit" name="btn_verificar" value="1" style="
            padding:.6rem 1rem; background:#198754; color:#fff; border:none; border-radius:8px; cursor:pointer;">
            Verificar
          </button>
          <button type="button" id="cerrarPopupCodigo" style="
            padding:.6rem 1rem; background:#6c757d; color:#fff; border:none; border-radius:8px; cursor:pointer;">
            Cerrar
          </button>
        </div>
      </form>

      <p style="font-size:12px; text-align:right; opacity:0.6; margin-top:10px;">
        (también puedes pulsar fuera para cerrar)
      </p>
    </div>
  </div>


  <!-- POPUP DATOS DUPLICADOS (MISMO ESTILO) -->
  <div id="popupDuplicados" style="
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.6);
    backdrop-filter: blur(2px);
    z-index:999999;
    justify-content:center;
    align-items:center;
    cursor:pointer;">
    <div style="
      background:white;
      padding:25px;
      border-radius:10px;
      max-width:400px;
      width:85%;
      box-shadow:0 4px 15px rgba(0,0,0,0.3);
      font-size:16px;" onclick="event.stopPropagation()">
      <h4 style="margin-bottom:10px;">Los siguientes datos ya existen en la base de datos:</h4>
      <ul id="listaDuplicados" style="margin-bottom:14px;"></ul>
      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button id="popupDuplicadosBtn" style="
          padding:8px 16px;
          background:#0d6efd; color:#fff; border:none; border-radius:6px;
          font-weight:600; cursor:pointer;">
          Aceptar
        </button>
      </div>
      <p style="font-size:12px; text-align:right; opacity:0.6; margin-top:10px;">
        (también puedes pulsar fuera para cerrar)
        /p>
    </div>
  </div>

  <!-- Cerrar popups al clicar fuera -->
  <script>
    document.getElementById("popupFaltan").addEventListener("click", function () {
      this.style.display = "none";
    });
    document.getElementById("popupDuplicados").addEventListener("click", function () {
      this.style.display = "none";
    });
  </script>

  <!-- ============== AVISO DE COOKIES ============== -->
  <div id="cookie-banner" style="
  display:none;
  position:fixed;
  bottom:0;
  left:0;
  width:100%;
  padding:16px 20px;
  background:rgba(0,0,0,0.85);
  backdrop-filter:blur(4px);
  color:white;
  text-align:center;
  z-index:999999;
  font-size:16px;
  box-shadow:0 -4px 12px rgba(0,0,0,0.4);
">
    Esta web utiliza cookies para mejorar la experiencia del usuario.
    <button id="cookie-accept" style="
      margin-left:15px;
      padding:8px 20px;
      background:#4db8ff;
      color:white;
      border:none;
      border-radius:6px;
      font-weight:bold;
      cursor:pointer;">
      Aceptar
    </button>
  </div>

  <!-- Scripts externos -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="js/script.js"></script>

  <script>
    /* ==========================================================
    ACTIVAR TOOLTIP FLOTANTE
    ========================================================== */
    function activarTooltip(idInput, idTooltip) {
      const input = document.getElementById(idInput);
      const tooltip = document.getElementById(idTooltip);

      input.addEventListener("focus", () => {
        tooltip.style.display = "block";
        tooltip.style.top = (input.offsetHeight + 5) + "px";
        tooltip.style.left = "0px";
      });

      input.addEventListener("blur", () => {
        setTimeout(() => tooltip.style.display = "none", 120);
      });
    }

    /* ==========================================================
    VALIDACIÓN EN TIEMPO REAL DE CONTRASEÑA
    ========================================================== */
    activarTooltip("pass", "requisitos");

    document.getElementById("pass").addEventListener("input", function () {
      const pass = this.value;
      document.getElementById("req1").classList.toggle("ok", pass.length >= 8);
      document.getElementById("req2").classList.toggle("ok", /[A-Z]/.test(pass));
      document.getElementById("req3").classList.toggle("ok", /[\W]/.test(pass));
      document.getElementById("req4").classList.toggle("ok", /[0-9]/.test(pass));
    });

    /* ==========================================================
    VALIDAR QUE PASS Y PASS2 COINCIDEN (SOLO AL SALIR)
    ========================================================== */
    document.getElementById("pass2").addEventListener("blur", function () {
      const pass = document.getElementById("pass").value;
      const pass2 = this.value;
      const error = document.getElementById("pass2Error");

      if (pass2.trim() === "") {
        this.classList.remove("is-invalid");
        this.classList.remove("is-valid");
        error.style.display = "none";
        return;
      }

      if (pass2 !== pass) {
        this.classList.add("is-invalid");
        this.classList.remove("is-valid");
        error.style.display = "block";
      } else {
        this.classList.remove("is-invalid");
        this.classList.add("is-valid");
        error.style.display = "none";
      }
    });

    /* ==========================================================
    VALIDACIÓN GENÉRICA (DNI, TELÉFONO, ORCID)
    ========================================================== */
    function validarCampo(idInput, idTooltip, regex) {
      const input = document.getElementById(idInput);
      const tooltip = document.getElementById(idTooltip);

      activarTooltip(idInput, idTooltip);
      input.addEventListener("input", function () {
        tooltip.style.display = regex.test(this.value) ? "none" : "block";
      });
    }

    validarCampo("dni", "dniError", /^[0-9]{8}[A-Za-z]$/);
    validarCampo("telefono", "telError", /^[0-9]{9}$/);
    validarCampo("orcid", "orcidError", /^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/);

    /* ==========================================================
    POPUP DE CAMPOS VACÍOS O FORMATO INCORRECTO (FRONT)
    ========================================================== */
    document.querySelector("form").addEventListener("submit", function (e) {
      let camposVacios = [];

      this.querySelectorAll("input, select").forEach(campo => {
        if (campo.type === "submit") return;

        let valor = campo.value.trim();
        let label = campo.closest(".mb-3")?.querySelector(".form-label")?.innerText || campo.name;

        // 1) Vacíos
        if (valor === "") {
          camposVacios.push(label);
          return;
        }
        // 2) Formatos
        if (campo.id === "dni" && !/^[0-9]{8}[A-Za-z]$/.test(valor)) {
          camposVacios.push(label);
          return;
        }
        if (campo.id === "telefono" && !/^[0-9]{9}$/.test(valor)) {
          camposVacios.push(label);
          return;
        }
        if (campo.id === "orcid" && !/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/.test(valor)) {
          camposVacios.push(label);
          return;
        }
        // 3) Selects en placeholder
        if (campo.tagName === "SELECT") {
          let texto = campo.options[campo.selectedIndex].text.trim().toLowerCase();
          if (texto.includes("seleccione")) {
            camposVacios.push(label);
            return;
          }
        }
      });

      if (camposVacios.length > 0) {
        e.preventDefault();
        const lista = document.getElementById("listaCamposFaltan");
        lista.innerHTML = "";
        camposVacios.forEach(c => {
          let li = document.createElement("li");
          li.textContent = c;
          lista.appendChild(li);
        });
        document.getElementById("popupFaltan").style.display = "flex";
        return;
      }

      // Validación final de contraseñas
      const passOK =
        document.getElementById("req1").classList.contains("ok") &&
        document.getElementById("req2").classList.contains("ok") &&
        document.getElementById("req3").classList.contains("ok") &&
        document.getElementById("req4").classList.contains("ok");

      const passMatch =
        document.getElementById("pass").value === document.getElementById("pass2").value;

      const dniOK = /^[0-9]{8}[A-Za-z]$/.test(document.getElementById("dni").value);
      const telOK = /^[0-9]{9}$/.test(document.getElementById("telefono").value);
      const orcidOK = /^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/.test(document.getElementById("orcid").value);

      if (!passOK || !passMatch || !dniOK || !telOK || !orcidOK) {
        e.preventDefault();
        if (!passMatch) {
          document.getElementById("pass2Error").style.display = "block";
          document.getElementById("pass2").classList.add("is-invalid");
        }
      }
    });
  </script>

  <!-- ==========================================================
       JS FINAL: abrir popup de DUPLICADOS si el servidor detectó repetidos
       (se queda en esta pestaña; no avanza hasta que todo esté OK)
       ========================================================== -->
  <script>
    (function () {
      const showDuplicados = <?php echo $showDuplicados ? 'true' : 'false'; ?>;
      if (!showDuplicados) return;

      const listaHTML = <?php echo json_encode($dup_list_html, JSON_UNESCAPED_UNICODE); ?>;
      const prev = <?php echo json_encode($prev, JSON_UNESCAPED_UNICODE); ?>;
      const dupFields = <?php echo json_encode($duplicadosCampos, JSON_UNESCAPED_UNICODE); ?>;

      const popup = document.getElementById('popupDuplicados');
      const ul = document.getElementById('listaDuplicados');
      const btnOk = document.getElementById('popupDuplicadosBtn');

      if (ul) ul.innerHTML = listaHTML;

      // Reponer valores previos y vaciar solo los duplicados
      Object.keys(prev).forEach((name) => {
        const el = document.querySelector('[name="' + name + '"]');
        if (!el) return;
        const isDup = dupFields.includes(name);

        if (el.tagName === 'SELECT') {
          if (isDup) {
            el.selectedIndex = 0;
            el.classList.add('is-invalid');
          } else {
            const val = prev[name];
            const has = Array.from(el.options).some(opt => opt.value === val);
            if (has) el.value = val;
          }
        } else {
          el.value = isDup ? '' : (prev[name] ?? '');
          if (isDup) el.classList.add('is-invalid');
        }
      });

      // Por seguridad, no reponemos contraseñas
      const p1 = document.querySelector('[name="pass"]');
      const p2 = document.querySelector('[name="pass2"]');
      if (p1) p1.value = '';
      if (p2) p2.value = '';

      // Mostrar popup
      if (popup) popup.style.display = 'flex';

      // Aceptar: cerrar y enfocar primer duplicado
      if (btnOk) {
        btnOk.addEventListener('click', function (ev) {
          ev.stopPropagation();
          if (popup) popup.style.display = 'none';
          if (dupFields.length) {
            const first = document.querySelector('[name="' + dupFields[0] + '"]');
            if (first) {
              first.scrollIntoView({ behavior: 'smooth', block: 'center' });
              first.focus();
            }
          }
        });
      }
    })();

    (function () {
      var pc = document.getElementById("popupCodigo");
      if (!pc) return;

      // Cerrar al pulsar fuera
      pc.addEventListener("click", function () { pc.style.display = "none"; });

      // Cerrar con botón
      var closeBtn = document.getElementById("cerrarPopupCodigo");
      if (closeBtn) closeBtn.addEventListener("click", function (ev) {
        ev.stopPropagation();
        pc.style.display = "none";
      });
    })();
  </script>

  <!-- Sistema de cookies -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const banner = document.getElementById("cookie-banner");
      const accept = document.getElementById("cookie-accept");
      if (!localStorage.getItem("cookies_accepted")) banner.style.display = "block";
      accept.addEventListener("click", function () {
        localStorage.setItem("cookies_accepted", "yes");
        banner.style.display = "none";
      });
    });
  </script>




</body>

</html>