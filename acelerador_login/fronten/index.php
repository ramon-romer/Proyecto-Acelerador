<?php
include('login.php');
require_once __DIR__ . '/lib/auth_password.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$correo = trim((string) ($_POST["usuario"] ?? ''));
$pass = (string) ($_POST["pwd"] ?? '');

if (isset($_POST["btn"])) {
  $authResult = acelerador_authenticate_usuario($conn, $correo, $pass);

  if (!($authResult['ok'] ?? false)) {
    acelerador_auth_log_event(
      $authResult['event'] ?? 'AUTH_FAIL_UNKNOWN',
      $correo,
      $authResult['context'] ?? []
    );
    header("Location: login_invalido.php");
    exit();
  }

  $perfil = strtoupper(trim((string) ($authResult['perfil'] ?? '')));

  acelerador_auth_log_event(
    $authResult['event'] ?? 'AUTH_OK',
    $correo,
    [
      'id_usuario' => (int) ($authResult['id_usuario'] ?? 0),
      'rehash_applied' => !empty($authResult['rehash_applied']) ? 1 : 0,
    ]
  );

  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }

  session_regenerate_id(true);

  $_SESSION['nombredelusuario'] = $correo;
  $_SESSION['perfil_usuario'] = $perfil;

  $stmtDatos = mysqli_prepare($conn, "SELECT ORCID, rama FROM tbl_profesor WHERE correo = ? LIMIT 1");

  if ($stmtDatos) {
    mysqli_stmt_bind_param($stmtDatos, 's', $correo);
    mysqli_stmt_execute($stmtDatos);
    $resDatos = mysqli_stmt_get_result($stmtDatos);
    $filaDatos = $resDatos ? mysqli_fetch_assoc($resDatos) : null;
    mysqli_stmt_close($stmtDatos);

    $_SESSION['orcid_usuario'] = $filaDatos['ORCID'] ?? '';
    $_SESSION['rama_usuario'] = $filaDatos['rama'] ?? '';
  } else {
    $_SESSION['orcid_usuario'] = '';
    $_SESSION['rama_usuario'] = '';
  }

  if ($perfil === "TUTOR") {
    header("Location: /Proyecto-Acelerador/Proyecto-Acelerador/acelerador_panel/fronten/panel_tutor.php");
    exit();
  }

  if ($perfil === "PROFESOR") {
    header("Location: /Proyecto-Acelerador/Proyecto-Acelerador/acelerador_panel/fronten/panel_profesor.php");
    exit();
  }

  if ($perfil === "ADMIN" || $perfil === "ADMINISTRADOR") {
    header("Location: /Proyecto-Acelerador/Proyecto-Acelerador/acelerador_panel/fronten/panel_admin.php");
    exit();
  }

  acelerador_auth_log_event('AUTH_FAIL_PROFILE_AMBIGUOUS', $correo, ['reason' => 'non_routable_post_auth']);
  header("Location: login_invalido.php");
  exit();
}
?>

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
  <link rel="stylesheet" href="css/styles.css">
  <link href="css/styles.css" rel="stylesheet" />
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
        <form method="POST" novalidate>
          <div class="mb-3">
            <label for="exampleInputEmail1" class="form-label">Correo electrónico</label>
            <div class="cuerpo">
              <input type="email" class="form-control" id="exampleInputEmail1" name="usuario"
                aria-describedby="emailHelp" placeholder="usuario@dominio.com" required>
            </div>
          </div>

          <div class="mb-3" id="contraseña">
            <label for="exampleInputPassword1" class="form-label">Contraseña</label>
            <div class="cuerpo">
              <input type="password" name="pwd" class="form-control" id="exampleInputPassword1" placeholder="••••••••"
                required>
            </div>
          </div>

          <div class="check" id="check">
            <input type="checkbox" class="form-check-input" id="exampleCheck1">
            <label class="form-check-label" for="exampleCheck1" id="check2">
              Confirmo que acepto los términos del contrato de la cuenta y que estoy de acuerdo con la política de
              privacidad.
            </label>
          </div>

          <div class="boton">
            <button type="submit" name="btn" class="btn btn-primary">Ingresar</button>
          </div>

          <div class="textoenlace"
            style="display: flex; justify-content: center; align-items: center; color: lightgray;">
            <small>
              <a href="/Proyecto-Acelerador/Proyecto-Acelerador/acelerador_registro/fronten/index.php"
                style="color:lightgray">
                <p>¿No tienes perfil? ¡Regístrate!</p>
              </a>
            </small>
          </div>
        </form>
      </div>
    </div>
  </main>

  <footer>
    <div class="mipie" id="mipie">
      <div class="direccion">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU Universidad Fernando III" />
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
      cursor:pointer;
    ">Aceptar</button>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="js/script.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const banner = document.getElementById("cookie-banner");
      const accept = document.getElementById("cookie-accept");

      if (!localStorage.getItem("cookies_accepted")) {
        banner.style.display = "block";
      }

      accept.addEventListener("click", function () {
        localStorage.setItem("cookies_accepted", "yes");
        banner.style.display = "none";
      });
    });
  </script>
</body>

</html>