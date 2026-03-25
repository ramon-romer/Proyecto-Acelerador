<?php
include('login.php');
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
</head>

<body>
  <header>
    <div class="imagen">
      <img src="img/Image__4_-removebg-preview.png" id="acele" />
    </div>
  </header>
  <main>
    <div class="cuadroPrincipal text-center">
        <div class="cuadroError">
            <div class="error-icon w-100 d-flex justify-content-center mb-3">
                <i class="bi bi-exclamation-triangle-fill text-danger text-center" style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
            </div>
            <div class="error-title mb-2">
                <h2 class="text-white fw-bold">¡Oh, no!</h2>
            </div>
            <p class="text-light fs-5 mb-1">Usuario o contraseña incorrectos.</p>
            <p class="text-white-50 mb-4">Por favor, inténtalo de nuevo.</p>
            
            <a href="index.php" class="btn btn-outline-light px-4 py-2 rounded-pill fw-bold mb-4 transition-all d-inline-flex align-items-center gap-2 text-decoration-none">
                <i class="bi bi-arrow-left"></i> Volver a intentar
            </a>
        </div>
        
        <hr class="w-100 border-light my-0 opacity-25 mb-3">
        
        <div class="textoenlace w-100">
            <a href="../../acelerador_registro/fronten/index.php" target="_blank" class="text-info hover-text-white d-block py-2">
                ¿No tienes perfil? <strong class="text-white ms-1">¡Regístrate aquí!</strong>
            </a>
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