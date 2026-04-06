<?php
include('login.php');
require_once __DIR__ . '/lib/auth_password.php';
error_reporting(0);

$correo = $_POST["correo"] ?? '';
$pass = $_POST["pwd"] ?? '';

// Para iniciar sesion
if (isset($_POST["btn"])) {
  $correo = trim((string)$correo);
  $authResult = acelerador_authenticate_usuario($conn, $correo, (string)$pass);

  if (!($authResult['ok'] ?? false)) {
    acelerador_auth_log_event(
      $authResult['event'] ?? 'AUTH_FAIL_UNKNOWN',
      $correo,
      $authResult['context'] ?? []
    );
    header("Location: login_invalido.php");
    exit();
  }

  $perfil = strtoupper(trim((string)($authResult['perfil'] ?? '')));

  acelerador_auth_log_event(
    $authResult['event'] ?? 'AUTH_OK',
    $correo,
    [
      'id_usuario' => (int)($authResult['id_usuario'] ?? 0),
      'rehash_applied' => !empty($authResult['rehash_applied']) ? 1 : 0,
    ]
  );

  session_start();
  $_SESSION['nombredelusuario'] = $correo;
  $_SESSION['perfil_usuario'] = $perfil;

  if ($perfil == "TUTOR") {
    header("Location: ../../acelerador_panel/fronten/panel_tutor.php");
    exit();
  } elseif ($perfil == "PROFESOR") {
    header("Location: ../../acelerador_panel/fronten/panel_profesor.php");
    exit();
  } elseif ($perfil == "ADMIN" || $perfil == "ADMINISTRADOR") {
    header("Location: ../../acelerador_panel/fronten/panel_admin.php");
    exit();
  }

  acelerador_auth_log_event('AUTH_FAIL_PROFILE_AMBIGUOUS', $correo, ['reason' => 'non_routable_post_auth']);
  header("Location: login_invalido.php");
  exit();
}
?>
