<?php
include('login.php');
include('config.php');

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
                    <select id="select" class="form-select" name="perfil" value="">
                      <option value="" disabled selected hidden>Seleccione un perfil</option>
                      <option>Profesor</option>
                      <option>Tutor</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Rama</label>
                <div class="cuerpo">
                  <div style="position:relative;">
                    <select class="form-select" id="rama" name="rama">
                      <option value="" disabled selected hidden>Seleccione una rama</option>
                      <option value="SALUD">SALUD</option>
                      <option value="TECNICAS">Técnicas</option>
                      <option value="SYJ">S Y J</option>
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
        <p>&copy; UF3. Todos los derechos reservados.</p>
      </div>
    </div>
  </footer>

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

      <h4 style="margin-bottom:10px;">Faltan por rellenar los siguientes campos:</h4>
      <ul id="listaCamposFaltan"></ul>

      <p style="font-size:12px; text-align:right; opacity:0.6;">
        (haz clic para cerrar)
      </p>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="js/script.js"></script>
</body>

</html>


<?php

if (isset($_POST["btn"])) {

  $nombre = $_POST["nombre"];
  $apellidos = $_POST["apellidos"];
  $pass = $_POST["pass"];
  $pass2 = $_POST["pass2"];
  $dni = $_POST["dni"];
  $orcid = $_POST["orcid"];
  $telefono = $_POST["telefono"];
  $perfil = $_POST["perfil"];
  $facultad = $_POST["facultad"];
  $departamento = $_POST["departamento"];
  $correo = $_POST["correo"];
  $rama = $_POST["rama"];  // ✔ AQUI LLEGA LA RAMA

  // VALIDACIÓN BÁSICA PHP
  if ($pass !== $pass2) {
  } elseif (strlen($pass) < 8) {
  } elseif (!preg_match('/[A-Z]/', $pass)) {
  } elseif (!preg_match('/[\W]/', $pass)) {
  } else {

    // INSERT FINAL sin teléfono personal
    $queryusuario = "INSERT INTO tbl_profesor 
      (nombre, apellidos, password, DNI, ORCID, telefono, perfil, facultad, departamento, correo, rama) 
      VALUES 
      ('$nombre', '$apellidos', '$pass', '$dni', '$orcid', '$telefono', '$perfil', '$facultad', '$departamento', '$correo', '$rama')";

    $ejecutar = mysqli_query($conn, $queryusuario);
  }
}
?>
<script>/* ==========================================================
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
     VALIDACIÓN CONTRASEÑA
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
     POPUP CAMPOS VACÍOS
  ========================================================== */
  document.querySelector("form").addEventListener("submit", function (e) {

    let camposVacios = [];

    this.querySelectorAll("input, select").forEach(campo => {

      if (campo.type === "submit") return;

      let valor = campo.value.trim();

      if (valor === "") {
        let label = campo.closest(".mb-3")?.querySelector(".form-label")?.innerText
          || campo.name;
        camposVacios.push(label);
        return;
      }

      if (campo.tagName === "SELECT") {
        let texto = campo.options[campo.selectedIndex].text.trim().toLowerCase();
        if (texto.includes("seleccione")) {
          let label = campo.closest(".mb-3")?.querySelector(".form-label")?.innerText
            || campo.name;
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

    /* ======================================================
       VALIDACIÓN DE FORMATO FINAL
    ======================================================= */
    const passOK =
      document.getElementById("req1").classList.contains("ok") &&
      document.getElementById("req2").classList.contains("ok") &&
      document.getElementById("req3").classList.contains("ok") &&
      document.getElementById("req4").classList.contains("ok");

    const dniOK = /^[0-9]{8}[A-Za-z]$/.test(document.getElementById("dni").value);
    const telOK = /^[0-9]{9}$/.test(document.getElementById("telefono").value);
    const orcidOK = /^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/.test(document.getElementById("orcid").value);

    if (!passOK || !dniOK || !telOK || !orcidOK) {
      e.preventDefault();
    }
  });

  /* ==========================================================
     CERRAR POPUP
  ========================================================== */
  document.getElementById("popupFaltan").addEventListener("click", function () {
    this.style.display = "none";
  });</script>