<?php
session_start();
include('config.php');
error_reporting(0);

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
  $rama = $_POST["rama"];

  // VALIDACIONES
  if ($pass !== $pass2) {
    echo "<script>alert('Las contraseñas no coinciden');</script>";
  } elseif (strlen($pass) < 8) {
    echo "<script>alert('La contraseña debe tener mínimo 8 caracteres');</script>";
  } elseif (!preg_match('/[A-Z]/', $pass)) {
    echo "<script>alert('Debe incluir una mayúscula');</script>";
  } elseif (!preg_match('/[\W]/', $pass)) {
    echo "<script>alert('Debe incluir un carácter especial');</script>";
  } else {

    // INSERTAR EN PROFESOR
    mysqli_query($conn, "
        INSERT INTO tbl_profesor
        (nombre, apellidos, password, DNI, ORCID, telefono, perfil, facultad, departamento, correo, rama)
        VALUES
        ('$nombre', '$apellidos', '$pass', '$dni', '$orcid', '$telefono', '$perfil', '$facultad', '$departamento', '$correo', '$rama')
      ");

    // INSERTAR EN USUARIO
    mysqli_query($conn, "
        INSERT INTO tbl_usuario (correo, password)
        VALUES ('$correo', '$pass')
      ");

    // GUARDAR SESION
    $_SESSION['nombredelusuario'] = $correo;

    // REDIRIGIR
    header("Location: ../../acelerador_primerapantallas/fronten/index.php");
    exit();
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

      <h4 style="margin-bottom:10px;">Faltan por rellenar o hay errores en el formato requerido en los siguientes
        campos:</h4>
      <ul id="listaCamposFaltan"></ul>

      <p style="font-size:12px; text-align:right; opacity:0.6;">
        (haz clic para cerrar)
      </p>
    </div>
  </div>





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
</body>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
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
  POPUP DE CAMPOS VACÍOS O FORMATO INCORRECTO
  ========================================================== */
  document.querySelector("form").addEventListener("submit", function (e) {

    let camposVacios = [];

    this.querySelectorAll("input, select").forEach(campo => {

      if (campo.type === "submit") return;

      let valor = campo.value.trim();
      let label = campo.closest(".mb-3")?.querySelector(".form-label")?.innerText
        || campo.name;

      /* =======================
      1️⃣ CAMPO VACÍO
      ======================= */
      if (valor === "") {
        camposVacios.push(label);
        return;
      }

      /* =======================
      2️⃣ FORMATO INCORRECTO → CONTAR COMO VACÍO
      ======================= */

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

      /* =======================
      3️⃣ SELECT PLACEHOLDER
      ======================= */
      if (campo.tagName === "SELECT") {
        let texto = campo.options[campo.selectedIndex].text.trim().toLowerCase();

        if (texto.includes("seleccione")) {
          camposVacios.push(label);
          return;
        }
      }

    });

    /* =======================
    MOSTRAR POPUP
    ======================= */
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
    VALIDACIÓN FINAL ANTES DE ENVIAR
    ======================================================= */
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

  /* ==========================================================
  CERRAR POPUP
  ========================================================== */
  document.getElementById("popupFaltan").addEventListener("click", function () {
    this.style.display = "none";
  });


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

</html>