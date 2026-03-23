<?php
session_start();
include("config.php");

// ===============================
// PROTEGER LA PANTALLA
// ===============================
if (!isset($_SESSION['nombredelusuario'])) {
  echo "<script>alert('Debes iniciar sesión primero'); window.location='../../acelerador_registro/login.php';</script>";
  exit();
}

$correo = $_SESSION['nombredelusuario'];

// ===============================
// CARGAR DATOS DEL PROFESOR
// ===============================
$query = mysqli_query($conn, "SELECT * FROM tbl_profesor WHERE correo='$correo' LIMIT 1");
$fila = mysqli_fetch_assoc($query);

if (!$fila) {
  echo "<script>alert('Error: no se encontró el profesor asociado a esta cuenta');</script>";
  exit();
}

// ========================================================
// ================ GUARDAR CAMBIOS ========================
// ========================================================
if (isset($_POST["guardar"])) {

  // Sanitizar entradas
  function limpiar($conn, $campo)
  {
    return mysqli_real_escape_string($conn, trim($_POST[$campo]));
  }

  $nombre = limpiar($conn, "nombre");
  $apellidos = limpiar($conn, "apellidos");
  $dni = limpiar($conn, "dni");
  $departamento = limpiar($conn, "departamento");
  $orcid = limpiar($conn, "orcid");
  $telefono = limpiar($conn, "telefono");
  $perfil = limpiar($conn, "perfil");
  $facultad = limpiar($conn, "facultad");
  $rama = limpiar($conn, "rama");
  $correoNuevo = limpiar($conn, "correoNuevo");
  $pass = limpiar($conn, "password");

  $errores = [];

  // Validaciones
  if (!preg_match('/^[0-9]{8}[A-Za-z]$/', $dni)) {
    $errores[] = "El DNI debe seguir el formato 12345678X";
  }

  if (!preg_match('/^[0-9]{9}$/', $telefono)) {
    $errores[] = "El teléfono debe tener 9 dígitos";
  }

  if (!preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/', $orcid)) {
    $errores[] = "El ORCID debe tener el formato 0000-0000-0000-0000";
  }

  $ramasValidas = ["SALUD", "TECNICAS", "SYJ", "HUMANIDADES", "EXPERIMENTALES"];
  if (!in_array($rama, $ramasValidas)) {
    $errores[] = "La rama seleccionada no es válida.";
  }

  if (!empty($pass)) {
    if (strlen($pass) < 8)
      $errores[] = "La contraseña debe tener mínimo 8 caracteres";
    if (!preg_match('/[A-Z]/', $pass))
      $errores[] = "La contraseña debe incluir al menos una mayúscula";
    if (!preg_match('/[\W]/', $pass))
      $errores[] = "La contraseña debe incluir al menos un carácter especial";
    if (!preg_match('/[0-9]/', $pass))
      $errores[] = "La contraseña debe incluir al menos un número";
  }

  // ========================================================
  // SI HAY ERRORES → MOSTRAR POPUP
  // ========================================================
  if (!empty($errores)) {

    echo "<script>
            let lista = document.getElementById('listaErroresEditar');
            lista.innerHTML = '';
          </script>";

    foreach ($errores as $e) {
      $e = addslashes($e);
      echo "<script>
              let lista = document.getElementById('listaErroresEditar');
              let li = document.createElement('li');
              li.textContent = '$e';
              lista.appendChild(li);
            </script>";
    }

    echo "<script>document.getElementById('popupEditar').style.display = 'flex';</script>";
    exit();
  }

  // ========================================================
  // UPDATE PROFESOR
  // ========================================================
  mysqli_query($conn, "
        UPDATE tbl_profesor SET
            nombre='$nombre',
            apellidos='$apellidos',
            DNI='$dni',
            departamento='$departamento',
            ORCID='$orcid',
            telefono='$telefono',
            perfil='$perfil',
            facultad='$facultad',
            rama='$rama',
            correo='$correoNuevo'
        WHERE correo='$correo'
    ");

  // UPDATE USUARIO (correo)
  mysqli_query($conn, "
        UPDATE tbl_usuario SET correo='$correoNuevo'
        WHERE correo='$correo'
    ");

  // UPDATE CONTRASEÑA
  if (!empty($pass)) {
    mysqli_query($conn, "
            UPDATE tbl_usuario SET password='$pass'
            WHERE correo='$correoNuevo'
        ");
  }

  // Actualizar sesión
  $_SESSION['nombredelusuario'] = $correoNuevo;

  echo "<script>alert('Datos actualizados correctamente'); window.location='index.php';</script>";
  exit();
}
?>

<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador</title>

  <link rel="icon" type="image/x-icon" href="img/Image__4_-removebg-preview.png">
  <link rel="stylesheet" href="css/styles.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    .listas ul li {
      color: lightgray !important;
    }
  </style>
</head>

<body>

  <header>
    <div class="imagen">
      <img src="img/Image__4_-removebg-preview.png" id="acele">
    </div>
  </header>

  <main>
    <div class="formulario">

      <div id="bloqueDatos">

        <div class="listas">

          <div class="lista1">
            <ul>
              <li>Correo electrónico: <?= $fila['correo'] ?></li>
              <li>Nombre: <?= $fila['nombre'] ?></li>
              <li>Apellidos: <?= $fila['apellidos'] ?></li>
              <li>DNI: <?= $fila['DNI'] ?></li>
              <li>Departamento: <?= $fila['departamento'] ?></li>
            </ul>
          </div>

          <div class="lista2">
            <ul>
              <li>ORCID: <?= $fila['ORCID'] ?></li>
              <li>Teléfono: <?= $fila['telefono'] ?></li>
              <li>Perfil: <?= $fila['perfil'] ?></li>
              <li>Facultad: <?= $fila['facultad'] ?></li>
              <li>Rama: <?= $fila['rama'] ?></li>
            </ul>
          </div>

        </div>

        <button id="btnEditar" class="btn btn-primary mt-3">Editar</button>

      </div>

      <div id="bloqueEditar" style="display:none;">

        <form method="POST">

          <div class="listas">

            <div class="lista1">
              <ul>
                <li>Nombre:
                  <input class="form-control" name="nombre" value="<?= $fila['nombre'] ?>">
                </li>

                <li>Apellidos:
                  <input class="form-control" name="apellidos" value="<?= $fila['apellidos'] ?>">
                </li>

                <li>DNI:
                  <input class="form-control" name="dni" value="<?= $fila['DNI'] ?>">
                </li>

                <li>Departamento:
                  <input class="form-control" name="departamento" value="<?= $fila['departamento'] ?>">
                </li>

                <li>Correo:
                  <input class="form-control" name="correoNuevo" value="<?= $fila['correo'] ?>">
                </li>
              </ul>
            </div>

            <div class="lista2">
              <ul>

                <li>ORCID:
                  <input class="form-control" name="orcid" value="<?= $fila['ORCID'] ?>">
                </li>

                <li>Teléfono:
                  <input class="form-control" name="telefono" value="<?= $fila['telefono'] ?>">
                </li>

                <li>Perfil:
                  <select class="form-select" name="perfil">
                    <option value="Profesor" <?= $fila['perfil'] == "Profesor" ? "selected" : "" ?>>Profesor</option>
                    <option value="Tutor" <?= $fila['perfil'] == "Tutor" ? "selected" : "" ?>>Tutor</option>
                  </select>
                </li>

                <li>Facultad:
                  <input class="form-control" name="facultad" value="<?= $fila['facultad'] ?>">
                </li>

                <li>Rama:
                  <select class="form-select" name="rama">
                    <option value="SALUD" <?= $fila['rama'] == "SALUD" ? "selected" : "" ?>>SALUD</option>
                    <option value="TECNICAS" <?= $fila['rama'] == "TECNICAS" ? "selected" : "" ?>>Técnicas</option>
                    <option value="SYJ" <?= $fila['rama'] == "SYJ" ? "selected" : "" ?>>S Y J</option>
                    <option value="HUMANIDADES" <?= $fila['rama'] == "HUMANIDADES" ? "selected" : "" ?>>Humanidades
                    </option>
                    <option value="EXPERIMENTALES" <?= $fila['rama'] == "EXPERIMENTALES" ? "selected" : "" ?>>
                      Experimentales</option>
                  </select>
                </li>

                <li>Contraseña nueva (opcional):
                  <input class="form-control" type="password" name="password" id="passwordInput">

                  <ul id="requisitosEditar" style="
      margin-top: 10px;
      padding: 10px;
      border-radius: 10px;
      background: #f8f8f8;
      border: 1px solid #ddd;
      list-style: none;
      font-size: 14px;">
                    <li id="reEdit1">• Mínimo 8 caracteres</li>
                    <li id="reEdit2">• Al menos una mayúscula</li>
                    <li id="reEdit3">• Al menos un carácter especial</li>
                    <li id="reEdit4">• Al menos un número</li>
                  </ul>
                </li>

              </ul>
            </div>

          </div>

          <button type="submit" name="guardar" class="btn btn-success mt-3">Guardar</button>
          <button type="button" id="btnCancelar" class="btn btn-danger mt-3">Cancelar</button>

        </form>

      </div>

    </div>
  </main>

  <!-- POPUP DE ERRORES -->
  <div id="popupEditar" style="
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.6);
    backdrop-filter: blur(3px);
    z-index:999999;
    justify-content:center;
    align-items:center;
    cursor:pointer;">

    <div style="
      background:white;
      padding:20px;
      border-radius:10px;
      max-width:400px;
      width:85%;
      font-size:16px;
      box-shadow:0 4px 15px rgba(0,0,0,0.3);">

      <h4 style="margin-bottom:10px;">Debes corregir los siguientes errores:</h4>

      <ul id="listaErroresEditar"></ul>

      <p style="font-size:12px; opacity:0.6; text-align:right;">
        (haz clic para cerrar)
      </p>
    </div>
  </div>

  <script>
    /* ========================================================
       MOSTRAR / OCULTAR BLOQUES
    ======================================================== */
    document.getElementById("btnEditar").addEventListener("click", () => {
      document.getElementById("bloqueDatos").style.display = "none";
      document.getElementById("bloqueEditar").style.display = "block";
    });

    document.getElementById("btnCancelar").addEventListener("click", () => {
      document.getElementById("bloqueDatos").style.display = "block";
      document.getElementById("bloqueEditar").style.display = "none";
    });

    /* ========================================================
       POPUP – LETRA NEGRA + CLICK PARA CERRAR
    ======================================================== */
    document.getElementById("popupEditar").addEventListener("click", () => {
      document.getElementById("popupEditar").style.display = "none";
    });

    document.getElementById("popupEditar").style.color = "black";

    /* ========================================================
       VALIDACIÓN DINÁMICA DE CONTRASEÑA (CON REQUISITOS)
    ======================================================== */
    const passEdit = document.getElementById("passwordInput");
    const reqEdit = document.getElementById("requisitosEditar");

    // Ocultamos de inicio
    reqEdit.style.display = "none";

    // Mostrar al hacer clic
    passEdit.addEventListener("focus", () => {
      reqEdit.style.display = "block";
    });

    // Ocultar al salir
    passEdit.addEventListener("blur", () => {
      setTimeout(() => reqEdit.style.display = "none", 150);
    });

    // Cambiar colores en tiempo real
    passEdit.addEventListener("input", () => {
      const v = passEdit.value;

      document.getElementById("reEdit1").style.color = (v.length >= 8) ? "green" : "red";
      document.getElementById("reEdit2").style.color = /[A-Z]/.test(v) ? "green" : "red";
      document.getElementById("reEdit3").style.color = /[\W]/.test(v) ? "green" : "red";
      document.getElementById("reEdit4").style.color = /[0-9]/.test(v) ? "green" : "red";
    });

    /* ========================================================
       VALIDACIÓN DE DNI / ORCID / TELÉFONO EN TIEMPO REAL
    ======================================================== */
    function validarCampo(nombre, regex) {
      const input = document.querySelector(`input[name="${nombre}"]`);

      input.addEventListener("input", e => {
        e.target.style.borderColor = regex.test(e.target.value) ? "green" : "red";
      });
    }

    validarCampo("dni", /^[0-9]{8}[A-Za-z]$/);
    validarCampo("orcid", /^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/);
    validarCampo("telefono", /^[0-9]{9}$/);

    /* ========================================================
       BLOQUEAR ENVÍO SI HAY ERRORES → MOSTRAR POPUP
    ======================================================== */
    document.querySelector("form").addEventListener("submit", function (e) {
      let errores = [];

      const dni = document.querySelector('input[name="dni"]').value.trim();
      const orcid = document.querySelector('input[name="orcid"]').value.trim();
      const telefono = document.querySelector('input[name="telefono"]').value.trim();
      const pass = passEdit.value.trim();

      // Validación de DNI
      if (!/^[0-9]{8}[A-Za-z]$/.test(dni)) {
        errores.push("El DNI no tiene el formato correcto (12345678X).");
      }

      // Validación de ORCID
      if (!/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/.test(orcid)) {
        errores.push("El ORCID no tiene el formato correcto (0000-0000-0000-0000).");
      }

      // Validación de Teléfono
      if (!/^[0-9]{9}$/.test(telefono)) {
        errores.push("El teléfono debe contener 9 dígitos.");
      }

      // Validación de Contraseña (si el usuario la escribe)
      if (pass.length > 0) {
        if (pass.length < 8) errores.push("La contraseña debe tener mínimo 8 caracteres.");
        if (!/[A-Z]/.test(pass)) errores.push("La contraseña debe incluir una mayúscula.");
        if (!/[\W]/.test(pass)) errores.push("La contraseña debe incluir un carácter especial.");
        if (!/[0-9]/.test(pass)) errores.push("La contraseña debe incluir un número.");
      }

      // SI HAY ERRORES → BLOQUEAR ENVÍO Y MOSTRAR POPUP
      if (errores.length > 0) {
        e.preventDefault();

        const lista = document.getElementById("listaErroresEditar");
        lista.innerHTML = "";

        errores.forEach(err => {
          let li = document.createElement("li");
          li.textContent = err;
          li.style.color = "black";
          lista.appendChild(li);
        });

        document.getElementById("popupEditar").style.display = "flex";
      }
    });
  </script>

</body>

</html>