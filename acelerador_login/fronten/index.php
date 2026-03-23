<?php
include("config.php"); // ← ESTE ES TU ARCHIVO DE CONEXIÓN REAL
session_start();
error_reporting(0);

$nombre = $_POST["usuario"];
$pass = $_POST["pwd"];

//Para iniciar sesión
if (isset($_POST["btn"])) {

  $queryusuario = mysqli_query($conn, "SELECT * FROM tbl_usuario WHERE correo = '$nombre' and password ='$pass' ");
  $nr = mysqli_num_rows($queryusuario);

  if ($nr == 1) {

    // 🔥 Guardamos el usuario logueado (tal como tú lo llamaste)
    $_SESSION['nombredelusuario'] = $nombre;

    // 🔥 Redirigir a tu pantalla de perfil
    header("Location: ../../acelerador_primerapantallas/fronten/index.php");
    exit();

  } else {
    echo "<script>alert('Usuario o contraseña incorrecto.');</script>";
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
      <img src="img/Image__4_-removebg-preview.png" id="acele" />
    </div>
  </header>
  <main>
    <div class="contenedor">
      <div class="formulario">
        <form method="POST">
          <div class="mb-3">
            <label for="exampleInputEmail1" class="form-label">Coreo electronico</label>
            <div class="cuerpo">
              <input type="email" class="form-control" id="exampleInputEmail1" name="usuario"
                aria-describedby="emailHelp">

            </div>

          </div>
          <div class="mb-3" id="contraseña">
            <label for="exampleInputPassword1" class="form-label">Contraseña</label>
            <div class="cuerpo">
              <input type="password" name="pwd" class="form-control" id="exampleInputPassword1">

            </div>

          </div>
          <div class="check" id="check">
            <input type="checkbox" class="form-check-input" id="exampleCheck1">
            <label class="form-check-label" for="exampleCheck1" id="check2">Confirmo que acepto los términos
              del
              contrato de
              la
              cuenta y que estoy de acuerdo con la politica de privacidad.</label>
          </div>
          <div class="boton">
            <button type="submit" name="btn" class="btn btn-primary">Ingresar</button>
          </div>
          <div class="textoenlace"
            style="display: flex; justify-content: center; align-items: center;color: lightsgray;">
            <small><a href="../../acelerador_registro/fronten/index.php" target="_blank" style="color:lightgray">
                <p>¿No tienes perfil?Registrate!!</p>
              </a></small>
          </div>
        </form>
      </div>
    </div>

  </main>
  <footer>
    <div class="mipie" id="mipie">
      <div class="direccion">
        <img src="img/Image__4_-removebg-preview.png" />

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
        <p>&copy; CEU Lab. Todos los derechos reservados.</p>
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

    // Mostrar al cargar si no está aceptado
    document.addEventListener("DOMContentLoaded", function () {

      const banner = document.getElementById("cookie-banner");
      const accept = document.getElementById("cookie-accept");

      // Si no existe la clave → mostrar banner
      if (!localStorage.getItem("cookies_accepted")) {
        banner.style.display = "block";
      }

      // Al aceptar
      accept.addEventListener("click", function () {
        localStorage.setItem("cookies_accepted", "yes");
        banner.style.display = "none";
      });

    });
  </script>
</body>

</html>