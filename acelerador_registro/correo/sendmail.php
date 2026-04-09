<?php
// acelerador_registro/correo/sendmail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Carga credenciales desde /acelerador_registro/mail.config.php si existe:
 *   return ['smtp_user' => '...', 'smtp_pass' => '...', 'from_name' => '...'];
 */
$BASE_PATH = dirname(__DIR__);
$config = [
  'smtp_user' => 'ramonelhermano@gmail.com',     // <-- tu Gmail remitente
  'smtp_pass' => 'ussgxeldupnzhamn',        // <-- App Password (no tu clave normal)
<<<<<<< Updated upstream
  'from_name' => 'ICorreo Verficación', // <-- nombre remitente (opcional)
=======
  'from_name' => 'Ignacio',
>>>>>>> Stashed changes
];
if (is_file($BASE_PATH . '/mail.config.php')) {
  $fileCfg = require $BASE_PATH . '/mail.config.php';
  if (is_array($fileCfg)) {
    $config = array_merge($config, $fileCfg);
  }
}

/**
 * Envía un correo HTML con PHPMailer. Devuelve true si se envía; false si falla.
 */
function enviarCorreo(string $toEmail, string $toName, string $subject, string $html, ?string $altText = null): bool
{
  global $config;

  $mail = new PHPMailer(true);

  // ---- Activa SOLO para depurar (coméntalo luego) ----
  // use PHPMailer\PHPMailer\SMTP;
  // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

  try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_pass']; // App Password (16 chars sin espacios)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Si 587 falla: SMTPS + 465
    $mail->Port = 587;

    $mail->setFrom($config['smtp_user'], $config['from_name'] ?? 'Sistema');
    $mail->addAddress($toEmail, $toName);

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $altText ?: strip_tags($html);

    return $mail->send();
  } catch (Exception $e) {
    error_log('Mailer error: ' . $e->getMessage() . ' | ' . ($mail->ErrorInfo ?? ''));
    return false;
  }
}



