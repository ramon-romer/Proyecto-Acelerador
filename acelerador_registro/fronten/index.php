<?php
include('login.php');
include('config.php');
error_reporting(0);
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
          <div class="conte">
            <div class="item1">
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Coreo electronico</label>
                <div class="cuerpo">
                  <input type="email" class="form-control" id="exampleInputEmail1" name="correo"
                    aria-describedby="emailHelp">

                </div>
              </div>
              <div class="mb-3" id="contraseña">
                <label for="exampleInputPassword1" class="form-label">Nombre</label>
                <div class="cuerpo">
                  <input type="text" class="form-control" id="nombre" name="nombre">
                </div>
              </div>
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Apellidos</label>
                <div class="cuerpo">
                  <input type="text" class="form-control" id="apellidos" name="apellidos" aria-describedby="emailHelp">

                </div>
              </div>
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">contraseña</label>
                <div class="cuerpo">
                  <input type="password" class="form-control" id="exampleInputEmail1" name="pdw"
                    aria-describedby="emailHelp">

                </div>
              </div>
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">repetir contraseña</label>
                <div class="cuerpo">
                  <input type="password" class="form-control" id="exampleInputEmail1" name="pdw2"
                    aria-describedby="emailHelp">

                </div>

              </div>
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Departamento</label>
                <div class="cuerpo">
                  <input type="text" class="form-control" id="departamento" name="departamento"
                    aria-describedby="emailHelp">

                </div>
              </div>
            </div>
            <div class="item2">
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">DNI</label>
                <div class="cuerpo">
                  <input type="text" class="form-control" id="exampleInputEmail1" name="dni"
                    aria-describedby="emailHelp">

                </div>
              </div>
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">DOI</label>
                <div class="cuerpo">
                  <input type="text" class="form-control" id="exampleInputEmail1" name="orcid"
                    aria-describedby="emailHelp">

                </div>
              </div>
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Telefono</label>
                <div class="cuerpo">
                  <input type="number" class="form-control" id="exampleInputEmail1" name="telefono"
                    aria-describedby="emailHelp">

                </div>

              </div>
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">facultad</label>
                <div class="cuerpo">
                  <input type="text" class="form-control" id="exampleInputEmail1" name="facultad"
                    aria-describedby="emailHelp">

                </div>

              </div>
              <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Numero personal</label>
                <div class="cuerpo">
                  <input type="number" class="form-control" id="exampleInputEmail1" name="numerop"
                    aria-describedby="emailHelp">

                </div>

              </div>
              <div class="mb-3">
                <label for="select" class="form-label">Perfil</label>
                <div class="cuerpo">
                  <select id="select" class="form-select" name="perfil">
                    <option>Profesor</option>
                    <option>Tutor</option>
                  </select>
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="js/script.js"></script>
</body>

</html>

<?php




$correo = $_POST["correo"];
$nombre = $_POST["nombre"];
$apellidos = $_POST["apellidos"];
$pass = $_POST["pdw"];
$pass2 = $_POST["pdw2"];
$dni = $_POST["dni"];
$orcid = $_POST["orcid"];
$telefono = $_POST["telefono"];
$facultad = $_POST["facultad"];
$departamento = $_POST["departamento"];
$numerop = $_POST["numerop"];
$perfil = $_POST["perfil"];


//Para iniciar sesión
if (isset($_POST["btn"])) {



  if ($pass == $pass2) {
    // 1. Preparamos la consulta
    $queryusuario = "INSERT INTO tbl_profesor (nombre, apellidos, password, DNI, ORCID, telefono, perfil, facultad, departamento, numero_personal, correo) 
    VALUES ('$nombre', '$apellidos', '$pass', '$dni', '$orcid', '$telefono', '$perfil', '$facultad', '$departamento', '$numerop', '$correo')";

    // 2. Ejecutamos la consulta (Guardamos el resultado en $ejecutar)
    $ejecutar = mysqli_query($conn, $queryusuario);

    // 3. Comprobamos si la ejecución fue exitosa
    if ($ejecutar) {
      echo "Registro completado con éxito";
    } else {
      // Esto te ayudará a saber qué falló si no funciona
      echo "Error en el registro: " . mysqli_error($conn);
    }
  } else {
    echo "Las contraseñas no coinciden";
  }


}
?>