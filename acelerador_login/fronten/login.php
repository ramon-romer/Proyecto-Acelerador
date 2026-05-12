<?php
/**
 * ACELERADOR - LOGIN CONSOLIDADO (CUSTOM PLACEMENT NOTIFICATIONS)
 */

require_once dirname(__DIR__, 2) . '/acelerador_frontend_db.php';
require_once __DIR__ . '/lib/auth_password.php';

$conn = acelerador_frontend_db_connect();

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Limpiar alertas al refrescar (F5) para UX superior
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['error'])) {
    unset($_SESSION['error_terms']);
}

$error_login = false;
$error_terms = $_SESSION['error_terms'] ?? false;
unset($_SESSION['error_terms']);

if (isset($_GET['error']) && $_GET['error'] === 'auth_fail') {
  $error_login = true;
}

$correo = trim((string) ($_POST["usuario"] ?? ''));
$pass = (string) ($_POST["pwd"] ?? '');

if (isset($_POST["btn"])) {
  // VALIDACIÓN DE CHECKBOX (MANDATORY - BACKEND FALLBACK)
  if (!isset($_POST['terms'])) {
    $_SESSION['error_terms'] = true;
    header("Location: login.php");
    exit();
  }

  // ADMINISTRATOR MODE (BYPASS)
  if ($correo === 'admin_acelerador' && $pass === '12345678Y#') {
    $_SESSION['rol'] = 'admin';
    $_SESSION['nombredelusuario'] = 'SUPERADMIN';
    header("Location: superadmin.php");
    exit();
  }

  // FLUJO ESTÁNDAR
  $authResult = acelerador_authenticate_usuario($conn, $correo, $pass);

  if (!($authResult['ok'] ?? false)) {
    acelerador_auth_log_event($authResult['event'] ?? 'AUTH_FAIL_UNKNOWN', $correo, $authResult['context'] ?? []);
    $error_login = true;
  } else {
    $perfil = strtoupper(trim((string) ($authResult['perfil'] ?? '')));
    session_regenerate_id(true);
    $_SESSION['nombredelusuario'] = $correo;
    $_SESSION['perfil_usuario'] = $perfil;

    // Persistencia de datos adicionales
    $stmt = mysqli_prepare($conn, "SELECT ORCID, rama FROM tbl_profesor WHERE correo = ? LIMIT 1");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 's', $correo);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      if ($fila = mysqli_fetch_assoc($res)) {
        $_SESSION['orcid_usuario'] = $fila['ORCID'];
        $_SESSION['rama_usuario'] = $fila['rama'];
      }
    }

    $redirects = [
        'TUTOR' => '../../acelerador_panel/fronten/panel_tutor.php', 
        'PROFESOR' => '../../acelerador_panel/fronten/panel_profesor.php', 
        'ADMIN' => '../../acelerador_panel/fronten/panel_admin.php'
    ];
    header("Location: " . ($redirects[$perfil] ?? 'login.php?error=auth_fail'));
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
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css">
  <link href="css/styles.css" rel="stylesheet" />
  <link rel="stylesheet" href="../../acelerador_panel/fronten/css/notifications.css">
  <link rel="stylesheet" href="css/login.css">
  
  <style>
    /* 1. ELEMENTO FLOTANTE PARA CHECKBOX */
    #floating-check-warning {
        position: absolute;
        top: -65px;
        left: 0;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(5px);
        color: #1e293b;
        padding: 12px 18px;
        border-radius: 15px;
        border: 2px solid #fbbf24;
        font-size: 0.85rem;
        font-weight: 600;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: none;
        z-index: 1000;
        animation: slideUp 0.3s ease-out;
        white-space: nowrap;
    }
    #floating-check-warning::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 25px;
        border-left: 10px solid transparent;
        border-right: 10px solid transparent;
        border-top: 10px solid #fbbf24;
    }
    #floating-check-warning.visible { display: block; }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* 2. NOTIFICACIÓN CENTRAL PARA ERRORES */
    #center-notification-container {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 11000;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        animation: fadeIn 0.3s ease;
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
        animation: zoomIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .center-toast i { font-size: 4rem; color: #f87171; margin-bottom: 20px; display: block; }
    .center-toast h2 { color: white; font-weight: 800; margin-bottom: 10px; }
    .center-toast p { color: rgba(255,255,255,0.7); font-size: 1.1rem; margin-bottom: 25px; }
    
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes zoomIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
  </style>
</head>

<body>
  <!-- Contenedor de Error Central -->
  <div id="center-notification-container" onclick="this.style.display='none'">
      <div class="center-toast">
          <i class="bi bi-shield-lock-fill"></i>
          <h2>ACCESO DENEGADO</h2>
          <p id="center-error-msg">Credenciales incorrectas o usuario no válido.</p>
          <button class="btn btn-outline-danger rounded-pill px-5 py-2 fw-bold">REINTENTAR</button>
      </div>
  </div>

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
        <form method="POST" id="loginForm">
          <div class="mb-3">
            <label for="exampleInputEmail1" class="form-label">Correo electrónico</label>
            <div class="cuerpo">
              <input type="text" class="form-control" id="exampleInputEmail1" name="usuario"
                placeholder="usuario@dominio.com" required>
            </div>
          </div>

          <div class="mb-3" id="contraseña">
            <label for="exampleInputPassword1" class="form-label">Contraseña</label>
            <div class="cuerpo">
              <input type="password" name="pwd" class="form-control" id="exampleInputPassword1" placeholder="••••••••"
                required>
            </div>
          </div>

          <div class="check" id="check" style="position: relative;">
            <input type="checkbox" class="form-check-input" id="exampleCheck1" name="terms">
            <label class="form-check-label" for="exampleCheck1" id="check2">
              Confirmo que acepto los términos del contrato de la cuenta y la política de privacidad.
            </label>
            <!-- Elemento Flotante para Checkbox -->
            <div id="floating-check-warning">
                <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>
                Es obligatorio marcar esta casilla
            </div>
          </div>

          <div class="boton">
            <button type="submit" name="btn" id="boton" class="btn btn-primary">Ingresar</button>
          </div>

          <div class="textoenlace">
            <small>
              <a href="../../acelerador_registro/fronten/index.php" style="color:lightgray; text-decoration: none;">
                <p style="text-align: center;">¿No tienes perfil? ¡Regístrate!</p>
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

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    function showCenterError(msg) {
        $('#center-error-msg').text(msg);
        $('#center-notification-container').css('display', 'flex');
    }

    function showFloatingWarning() {
        const warning = $('#floating-check-warning');
        warning.addClass('visible');
        
        // Shake animation on the parent container
        $('.check').addClass('shake-horizontal');
        setTimeout(() => $('.check').removeClass('shake-horizontal'), 500);
        
        // Auto hide after 4s
        setTimeout(() => warning.removeClass('visible'), 4000);
    }

    $(document).ready(function() {
        // Alertas PHP al cargar
        <?php if ($error_login): ?>
            showCenterError('El correo electrónico o la contraseña no son correctos. Por favor, inténtalo de nuevo.');
        <?php endif; ?>
        
        <?php if ($error_terms): ?>
            showFloatingWarning();
        <?php endif; ?>

        // Validación Frontend
        $('#loginForm').on('submit', function(e) {
            if (!$('#exampleCheck1').is(':checked')) {
                e.preventDefault();
                showFloatingWarning();
            }
        });

        // Ocultar flotante al marcar
        $('#exampleCheck1').on('change', function() {
            if ($(this).is(':checked')) {
                $('#floating-check-warning').removeClass('visible');
            }
        });
    });
  </script>
</body>

</html>