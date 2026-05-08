<?php
/**
 * LOGIN con redirección por PERFIL (PROFESOR / TUTOR)
 * Tablas esperadas:
 *  - tbl_usuario(correo, password)  -> password guardado con password_hash()
 *  - tbl_profesor(correo, perfil)   -> perfil en {'PROFESOR','TUTOR'} (mayúsculas)
 */

session_start();
require_once "config.php"; // Debe definir $conn (mysqli)
error_reporting(0);        // Mantengo tu configuración

// (Opcional) Activa esto en desarrollo para ver errores reales:
// ini_set('display_errors', '1'); ini_set('display_startup_errors', '1'); error_reporting(E_ALL);

if (isset($_POST["btn"])) {

  $correo = isset($_POST["usuario"]) ? trim($_POST["usuario"]) : '';
  $passIntroducida = $_POST["pwd"] ?? '';

  if ($correo === '' || $passIntroducida === '') {
    echo "<script>alert('Usuario o contraseña incorrecto.');</script>";
    exit;
  }

  // 1. Buscar usuario por correo (consulta preparada)
  $stmt = mysqli_prepare($conn, "SELECT password FROM tbl_usuario WHERE correo = ? LIMIT 1");
  if (!$stmt) {
    error_log("Prepare failed (tbl_usuario): " . mysqli_error($conn));
    echo "<script>alert('Error interno. Inténtalo de nuevo más tarde.');</script>";
    exit;
  }
  mysqli_stmt_bind_param($stmt, "s", $correo);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_store_result($stmt);

  if (mysqli_stmt_num_rows($stmt) == 1) {

    mysqli_stmt_bind_result($stmt, $hashEnBD);
    if (!mysqli_stmt_fetch($stmt) || $hashEnBD === null) {
      mysqli_stmt_close($stmt);
      echo "<script>alert('Usuario o contraseña incorrecto.');</script>";
      exit;
    }
    mysqli_stmt_close($stmt);

    // 2. Comparar contraseña introducida con el hash
    if (password_verify($passIntroducida, $hashEnBD)) {

      // Sesión segura
      session_regenerate_id(true);
      $_SESSION['nombredelusuario'] = $correo;

      // 3. Obtener perfil en tbl_profesor
      $stmt2 = mysqli_prepare($conn, "SELECT TRIM(UPPER(perfil)) AS p FROM tbl_profesor WHERE correo = ? LIMIT 1");
      if (!$stmt2) {
        error_log("Prepare failed (tbl_profesor): " . mysqli_error($conn));
        // Fallback razonable si falla la consulta de perfil
        header("Location: ../../acelerador_panel/fronten/panel_profesor.php");
        exit();
      }
      mysqli_stmt_bind_param($stmt2, "s", $correo);
      mysqli_stmt_execute($stmt2);
      mysqli_stmt_store_result($stmt2);

      $perfil = null;
      if (mysqli_stmt_num_rows($stmt2) === 1) {
        mysqli_stmt_bind_result($stmt2, $perfilBD);
        mysqli_stmt_fetch($stmt2);
        $perfil = $perfilBD; // ya viene TRIM + UPPER
      }
      mysqli_stmt_close($stmt2);

      // 4. Redirección según perfil (rutas indicadas por ti)
      if ($perfil === "PROFESOR") {
        header("Location: ../../acelerador_panel/fronten/panel_profesor.php");
        exit();
      } elseif ($perfil === "TUTOR") {
        header("Location: ../../acelerador_panel/fronten/panel_tutor.php");
        exit();
      } else {
        // Fallback si no hay perfil o es desconocido
        header("Location: ../../acelerador_panel/fronten/panel_profesor.php");
        exit();
      }

    } else {
      echo "<script>alert('Usuario o contraseña incorrecto.');</script>";
      exit;
    }

  } else {
    // Usuario no encontrado
    mysqli_stmt_close($stmt);
    echo "<script>alert('Usuario o contraseña incorrecto.');</script>";
    exit;
  }
}
?>

<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador</title>
  <link rel="icon" href="img/Image__4_-removebg-preview.png" type="image/x-icon" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css">
  <link href="css/styles.css" rel="stylesheet" />
</head>

<body>
  <header>
    <div class="imagen">
      <img src="img/Image__4_-removebg-preview.png" id="acele" alt="Acelerador" />
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
              <a href="../../acelerador_registro/fronten/index.php" sstyle="color:lightgray">
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
        <img src="img/Image__4_-removebg-preview.png" alt="Logo" />
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
      cursor:pointer;
    ">Aceptar</button>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="js/script.js"></script>

  <script>
    // ============== SISTEMA DE COOKIES ==============
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